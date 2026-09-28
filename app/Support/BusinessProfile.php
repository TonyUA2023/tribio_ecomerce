<?php

namespace App\Support;

use App\Models\Store;

/**
 * A store's "rubro" (stores.category) and what it changes in the platform, read from
 * config('tribio.business_categories'). Selectors, validation rules and rubro-specific
 * defaults go through here, so adding or tuning a rubro is a config edit.
 */
final class BusinessProfile
{
    private function __construct(public readonly string $key, private readonly array $config)
    {
    }

    public static function for(?string $category): self
    {
        $categories = config('tribio.business_categories', []);
        $key = array_key_exists((string) $category, $categories) ? (string) $category : 'otros';

        return new self($key, array_merge(config('tribio.business_profile_defaults', []), $categories[$key] ?? []));
    }

    public static function forStore(?Store $store): self
    {
        return self::for($store?->category);
    }

    /** @return list<string> valid values for stores.category */
    public static function keys(): array
    {
        return array_keys(config('tribio.business_categories', []));
    }

    /** @return array<string, string> key => "icon label", for selects */
    public static function options(): array
    {
        return collect(config('tribio.business_categories', []))
            ->map(fn (array $c) => trim(($c['icon'] ?? '') . ' ' . $c['label']))
            ->all();
    }

    public function label(): string
    {
        return $this->config['label'] ?? 'Otros';
    }

    public function icon(): string
    {
        return $this->config['icon'] ?? '🛍️';
    }

    /** Stores of this rubro start with "Ventas por encargo" on and see it recommended. */
    public function recommendsMadeToOrder(): bool
    {
        return (bool) ($this->config['made_to_order'] ?? false);
    }

    /** @return list<string> suggested sizes for a made-to-order size grid */
    public function sizes(): array
    {
        return array_values($this->config['sizes'] ?? []);
    }

    /** @return array<string, list<string>> option name => suggested values, for new variants */
    public function variantOptions(): array
    {
        return $this->config['variant_options'] ?? [];
    }

    /** @return list<string> template keys recommended for this rubro */
    public function recommendedTemplates(): array
    {
        return array_values($this->config['templates'] ?? []);
    }

    /**
     * Rubro defaults for a store being created. Only ever fills what the store doesn't
     * set itself, and only at creation — changing rubro later never flips a setting.
     */
    public function creationDefaults(): array
    {
        if (!$this->recommendsMadeToOrder()) {
            return [];
        }

        return array_filter([
            'made_to_order_enabled' => true,
            'deposit_percent' => $this->config['deposit_percent'] ?? null,
        ], fn ($value) => $value !== null);
    }
}
