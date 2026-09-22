<?php

namespace App\Actions\Storefront;

use App\Models\Store;
use App\Services\Storefront\TemplateRegistry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Switches a store's storefront design. Only the template key changes: products, orders,
 * settings and any customization saved for other templates are left untouched, so going
 * back to a previous design restores it exactly as the owner left it.
 */
class ApplyStoreTemplate
{
    public function __construct(private readonly TemplateRegistry $registry)
    {
    }

    public function handle(Store $store, string $templateKey): Store
    {
        if (!$this->registry->canManage($store)) {
            throw new AuthorizationException('Esta tienda tiene un diseño exclusivo protegido.');
        }
        if (!$this->registry->isSelectable($templateKey)) {
            throw ValidationException::withMessages(['template' => 'Esta plantilla todavía no está disponible.']);
        }

        $store->forceFill(['template_name' => $templateKey])->save();

        return $store;
    }
}
