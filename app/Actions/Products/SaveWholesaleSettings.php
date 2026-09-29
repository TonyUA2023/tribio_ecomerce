<?php

namespace App\Actions\Products;

use App\Models\Product;
use App\Services\Pricing\WholesalePricing;
use Illuminate\Http\Request;

/**
 * The "Precios por mayor" block of the product form. validate() runs before the product is
 * saved, so bad tiers never leave a half-saved product; apply() runs after. Inert until the
 * wholesale migration has run (the form doesn't show the block either).
 */
class SaveWholesaleSettings
{
    public function validate(Request $request): ?array
    {
        if (!WholesalePricing::ready() || !$request->has('price_tiers_json')) {
            return null;
        }

        $request->validate([
            'price_tiers_json' => 'nullable|string|max:4000',
            'min_quantity' => 'nullable|integer|min:1|max:' . WholesalePricing::MAX_QUANTITY,
        ], [
            'min_quantity.min' => 'El pedido mínimo debe ser de al menos 1 unidad.',
        ]);

        $json = trim((string) $request->input('price_tiers_json', ''));
        $decoded = $json === '' ? null : json_decode($json, true);
        $tiers = WholesalePricing::normalize($json !== '' && $decoded === null ? 'invalid' : $decoded, (float) $request->input('price'));
        $minimum = $request->filled('min_quantity') ? (int) $request->input('min_quantity') : null;

        return [
            'price_tiers' => $tiers,
            'min_quantity' => $minimum && $minimum > 1 ? $minimum : null,
        ];
    }

    public function apply(Product $product, ?array $settings): void
    {
        if ($settings !== null) {
            $product->forceFill($settings)->save();
        }
    }
}
