<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\ProductComponent;
use App\Models\ProductUnit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Inventario → producto: its packagings ("Caja" = 100 und) and, for a combo, its components.
 *
 * Combo rules: components are stock-tracked products of the same store, never combos
 * themselves (no nesting) and never the combo; a combo has no variants and is not a
 * component of another combo. Turning a product with its own stock into a combo is
 * refused — its stock would silently be replaced by the derived one.
 */
class ProductStructureService
{
    public const MAX_COMPONENTS = 20;

    public function __construct(private StockService $stock)
    {
    }

    public function addUnit(Product $product, string $name, int $factor, ?string $barcode): ProductUnit
    {
        $name = trim($name);
        if ($product->units()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->exists()) {
            throw ValidationException::withMessages(['name' => "Ya existe la presentación «{$name}»."]);
        }

        return $product->units()->create(['name' => $name, 'factor' => $factor, 'barcode' => $barcode ?: null]);
    }

    /** @param list<array{product_id: int, quantity: int}> $components empty = stop being a combo */
    public function saveComponents(Product $kit, array $components): void
    {
        $wasKit = $kit->components()->exists();

        if ($components !== []) {
            $this->validateKit($kit, $components, $wasKit);
        }

        DB::transaction(function () use ($kit, $components) {
            $kit->components()->delete();
            foreach ($components as $line) {
                $kit->components()->create(['component_product_id' => $line['product_id'], 'quantity' => $line['quantity']]);
            }
            if ($components !== []) {
                // A combo must be stock-tracked so the storefront respects what its components allow.
                $kit->update(['track_stock' => true]);
                $this->stock->refreshKit($kit->id);
            }
        });
    }

    private function validateKit(Product $kit, array $components, bool $wasKit): void
    {
        if (count($components) > self::MAX_COMPONENTS) {
            throw ValidationException::withMessages(['components' => 'Un combo puede tener hasta ' . self::MAX_COMPONENTS . ' productos.']);
        }
        if ($kit->has_variants) {
            throw ValidationException::withMessages(['components' => 'Un producto con variantes (tallas, colores) no puede ser combo.']);
        }
        if (ProductComponent::where('component_product_id', $kit->id)->exists()) {
            throw ValidationException::withMessages(['components' => 'Este producto ya es parte de otro combo: no puede ser combo a la vez.']);
        }
        if (!$wasKit && (int) $kit->stock > 0) {
            throw ValidationException::withMessages(['components' => "Este producto tiene {$kit->stock} und en stock. Registra su salida (déjalo en 0) antes de convertirlo en combo."]);
        }

        $ids = array_column($components, 'product_id');
        if (in_array($kit->id, $ids, true)) {
            throw ValidationException::withMessages(['components' => 'Un combo no puede contenerse a sí mismo.']);
        }
        $valid = Product::where('store_id', $kit->store_id)->whereIn('id', $ids)->where('track_stock', true)
            ->whereDoesntHave('components')->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (array_diff($ids, $valid) !== []) {
            throw ValidationException::withMessages(['components' => 'Los productos del combo deben ser de tu tienda, tener control de stock y no ser combos.']);
        }
    }
}
