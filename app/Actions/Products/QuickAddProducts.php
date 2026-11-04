<?php

namespace App\Actions\Products;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Services\Inventory\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * "Alta rápida": many products in one go from a table (typed, scanned or pasted from Excel).
 * Only what a counter needs: code, name, category, cost, price, opening stock and unit. Stock
 * enters through StockService as a lot at its cost, so margins are right from the first sale.
 * All or nothing: one bad row saves none and says which one.
 */
class QuickAddProducts
{
    public const MAX_ROWS = 200;

    public function __construct(private StockService $stock)
    {
    }

    /**
     * @param  list<array{sku?: ?string, name?: ?string, category?: ?string, cost?: mixed, price?: mixed, stock?: mixed, unit?: ?string}>  $rows
     * @return list<Product>
     *
     * @throws ValidationException errors keyed rows.N.field (N = position in $rows)
     */
    public function handle(Store $store, array $rows, ?int $userId): array
    {
        $this->validate($store, $rows);

        return DB::transaction(function () use ($store, $rows, $userId) {
            $categories = [];
            $order = (int) $store->products()->max('sort_order');
            $created = [];

            foreach ($rows as $row) {
                $categoryName = trim((string) ($row['category'] ?? ''));
                $category = $categoryName === '' ? null : ($categories[mb_strtolower($categoryName)] ??= $this->category($store, $categoryName));
                $cost = $this->number($row['cost'] ?? null);

                $product = Product::create([
                    'store_id'        => $store->id,
                    'category_id'     => $category?->id,
                    'name'            => trim($row['name']),
                    'slug'            => $this->slug($store, $row['name']),
                    'sku'             => filled($row['sku'] ?? null) ? trim($row['sku']) : null,
                    'price'           => $this->number($row['price']),
                    'cost_price'      => $cost,
                    'stock'           => 0,
                    'track_stock'     => true,
                    'low_stock_alert' => 5,
                    'unit'            => filled($row['unit'] ?? null) ? trim($row['unit']) : 'unidad',
                    'is_active'       => true,
                    'sort_order'      => ++$order,
                ]);

                $quantity = (int) $this->number($row['stock'] ?? null);
                if ($quantity > 0) {
                    $this->stock->adjust($product, 'in', $quantity, 'Stock inicial (alta rápida)', $userId, ['unit_cost' => $cost]);
                }
                $created[] = $product->fresh();
            }

            return $created;
        });
    }

    private function validate(Store $store, array $rows): void
    {
        $validator = Validator::make(['rows' => $rows], [
            'rows'            => ['required', 'array', 'min:1', 'max:' . self::MAX_ROWS],
            'rows.*.sku'      => ['nullable', 'string', 'max:50'],
            'rows.*.name'     => ['required', 'string', 'max:255'],
            'rows.*.category' => ['nullable', 'string', 'max:80'],
            'rows.*.cost'     => ['nullable', fn ($a, $v, $fail) => $this->isMoney($v, 9999999) || $fail('El costo no es un número válido.')],
            'rows.*.price'    => ['required', fn ($a, $v, $fail) => ($this->isMoney($v, 9999999) && $this->number($v) > 0) || $fail('Pon un precio mayor a 0.')],
            'rows.*.stock'    => ['nullable', fn ($a, $v, $fail) => ($this->isMoney($v, 10000000) && floor($this->number($v)) == $this->number($v)) || $fail('El stock es un número entero.')],
            'rows.*.unit'     => ['nullable', 'string', 'max:20'],
        ], [
            'rows.required'       => 'Agrega al menos un producto.',
            'rows.max'            => 'Puedes guardar hasta ' . self::MAX_ROWS . ' productos a la vez.',
            'rows.*.name.required' => 'Falta el nombre.',
            'rows.*.price.required' => 'Pon un precio mayor a 0.',
        ]);

        $validator->after(function ($validator) use ($store, $rows) {
            $existing = $store->products()->whereNotNull('sku')->pluck('name', 'sku')->mapWithKeys(fn ($name, $sku) => [mb_strtolower($sku) => $name]);
            $seen = [];
            foreach ($rows as $i => $row) {
                $sku = mb_strtolower(trim((string) ($row['sku'] ?? '')));
                if ($sku === '') {
                    continue;
                }
                if ($existing->has($sku)) {
                    $validator->errors()->add("rows.{$i}.sku", "Ese código ya es de «{$existing[$sku]}».");
                } elseif (isset($seen[$sku])) {
                    $validator->errors()->add("rows.{$i}.sku", 'Ese código está repetido en la fila ' . ($seen[$sku] + 1) . '.');
                }
                $seen[$sku] ??= $i;
            }
        });

        $validator->validate();
    }

    private function isMoney(mixed $value, float $max): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        $number = $this->number($value);

        return $number !== null && $number >= 0 && $number <= $max;
    }

    /** "12,50", "S/ 12.50" or 12.5 → 12.5; null when it is not a number. */
    private function number(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        $clean = str_replace(',', '.', preg_replace('/[^\d,.\-]/', '', (string) $value));

        return is_numeric($clean) ? (float) $clean : null;
    }

    private function category(Store $store, string $name): Category
    {
        $name = Str::limit($name, 80, '');
        $found = Category::where('store_id', $store->id)->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
        if ($found) {
            return $found;
        }

        $slug = Str::slug($name) ?: 'categoria';
        $base = $slug;
        for ($n = 2; Category::where('store_id', $store->id)->where('slug', $slug)->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        return Category::create(['store_id' => $store->id, 'name' => $name, 'slug' => $slug, 'is_active' => true, 'sort_order' => (int) Category::where('store_id', $store->id)->max('sort_order') + 1]);
    }

    private function slug(Store $store, string $name): string
    {
        $base = Str::slug($name) ?: 'producto';
        $slug = $base;
        for ($n = 2; Product::withTrashed()->where('store_id', $store->id)->where('slug', $slug)->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        return $slug;
    }
}
