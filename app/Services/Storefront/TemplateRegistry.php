<?php

namespace App\Services\Storefront;

use App\Models\Store;
use Illuminate\Support\Facades\View;

/**
 * Single source of truth for which storefront templates exist and what a store may do
 * with them. Manifests live in config/storefront.php; every "can this be offered?"
 * decision goes through here so the dashboard, the preview and the apply action can
 * never disagree.
 */
class TemplateRegistry
{
    /** Every page StoreController can render for a template. */
    public const REQUIRED_VIEWS = ['store', 'catalog', 'product', 'gallery', 'contact'];

    public const STATUS_AVAILABLE = 'available';
    public const STATUS_DEVELOPMENT = 'development';
    public const STATUS_PRIVATE = 'private';

    public function all(): array
    {
        return config('storefront.templates', []);
    }

    public function find(?string $key): ?array
    {
        return $key !== null ? ($this->all()[$key] ?? null) : null;
    }

    public function exists(?string $key): bool
    {
        return $this->find($key) !== null;
    }

    /** Templates shown in the dashboard gallery (private bespoke builds are never listed). */
    public function catalog(): array
    {
        return array_filter($this->all(), fn (array $template) => ($template['status'] ?? null) !== self::STATUS_PRIVATE);
    }

    /**
     * Offered to store owners: explicitly published AND every page actually exists.
     * The view check is the safety net for a developer flipping a half-built template
     * to 'available' — it just silently stays unselectable instead of 500ing a store.
     */
    public function isSelectable(?string $key): bool
    {
        $template = $this->find($key);

        return $template !== null
            && ($template['status'] ?? null) === self::STATUS_AVAILABLE
            && $this->missingViews($key) === [];
    }

    public function missingViews(string $key): array
    {
        return array_values(array_filter(self::REQUIRED_VIEWS, fn ($view) => !View::exists("templates.{$key}.{$view}")));
    }

    public function isCustomizable(?string $key): bool
    {
        return !empty($this->find($key)['settings'] ?? []);
    }

    /** `['colors.primary' => [...field + 'group' => groupKey], ...]` in declaration order. */
    public function fields(string $key): array
    {
        $fields = [];
        foreach ($this->find($key)['settings'] ?? [] as $group) {
            foreach ($group['fields'] as $path => $field) {
                $fields[$path] = $field + ['group' => $group['key']];
            }
        }

        return $fields;
    }

    /** Locked (bespoke) stores are fully outside the module: no switching, no customizing. */
    public function canManage(Store $store): bool
    {
        return !$store->template_locked;
    }

    public function fontOptions(): array
    {
        return config('storefront.fonts', []);
    }
}
