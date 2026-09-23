<?php

namespace App\Actions\Products;

use App\Models\Product;
use App\Models\StoreMarketingIntegration;
use App\Services\Marketing\MarketingSchema;
use Illuminate\Http\Request;

/**
 * The "Publicidad" part of the product form (dashboard/products/_ads): whether the
 * product goes to the Meta catalog, and its condition. Inert when the form didn't
 * include the section (stores without Meta, the mobile app) or the marketing
 * migration hasn't run — so product saves never depend on those columns existing.
 */
class SaveAdsCatalogSettings
{
    public function __construct(private MarketingSchema $schema) {}

    public function validate(Request $request): ?array
    {
        if (!$request->has('ads_settings') || !$this->schema->ready()) {
            return null;
        }

        $request->validate([
            'include_in_ads' => 'nullable|boolean',
            'condition'      => 'nullable|in:' . implode(',', array_keys(StoreMarketingIntegration::CONDITIONS)),
        ]);

        return [
            'exclude_from_ads' => !$request->boolean('include_in_ads'),
            'condition'        => $request->input('condition') ?: null,
        ];
    }

    public function apply(Product $product, ?array $settings): void
    {
        if ($settings !== null) {
            $product->forceFill($settings)->save();
        }
    }
}
