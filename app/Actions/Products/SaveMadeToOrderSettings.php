<?php

namespace App\Actions\Products;

use App\Models\Product;
use App\Models\Store;
use App\Services\MadeToOrder\CustomizationSchema;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The "Venta por encargo" part of the product form. validate() runs before the product
 * (and its images) are saved, so a bad customization schema never leaves a half-saved
 * product behind; apply() runs after. Inert for stores that don't sell made-to-order.
 */
class SaveMadeToOrderSettings
{
    public function validate(Request $request, Store $store): ?array
    {
        if (!$store->made_to_order_enabled || !$request->has('sale_mode')) {
            return null;
        }

        $request->validate([
            'sale_mode' => 'required|in:' . Product::SALE_STOCK . ',' . Product::SALE_MADE_TO_ORDER,
            'lead_time_days' => 'nullable|integer|min:0|max:365',
            'customization_schema_json' => 'nullable|string|max:20000',
        ], [
            'lead_time_days.max' => 'El tiempo de producción puede ser de hasta 365 días.',
        ]);

        if ($request->input('sale_mode') !== Product::SALE_MADE_TO_ORDER) {
            // Back to stock: the schema is kept, so switching again restores it.
            return ['sale_mode' => Product::SALE_STOCK];
        }

        $json = trim((string) $request->input('customization_schema_json', ''));
        $decoded = $json === '' ? [] : json_decode($json, true);
        if ($json !== '' && json_last_error() !== JSON_ERROR_NONE) {
            throw ValidationException::withMessages(['customization_schema' => 'La personalización no tiene un formato válido.']);
        }

        return [
            'sale_mode' => Product::SALE_MADE_TO_ORDER,
            'lead_time_days' => $request->filled('lead_time_days') ? (int) $request->input('lead_time_days') : null,
            'customization_schema' => CustomizationSchema::normalize($decoded) ?: null,
            // Produced per order: never "agotado", never decremented at checkout.
            'track_stock' => false,
        ];
    }

    public function apply(Product $product, ?array $settings): void
    {
        if ($settings !== null) {
            $product->forceFill($settings)->save();
        }
    }
}
