<?php

namespace App\Services\Pos;

use App\Models\Contact;
use App\Models\Product;
use App\Models\ProductListPrice;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Price lists (ERP Fase 4c). Retail ("menor") is the product's own price; the mayorista and
 * distribuidor lists hold a price per product. A contact's relationship picks their list at
 * the register; a quantity tier ([[Wholesale-Pricing]]) still wins when it is lower.
 */
class PriceListService
{
    public const LISTS = [
        'mayorista'    => 'Mayorista',
        'distribuidor' => 'Distribuidor / socio',
    ];

    public function __construct(private PosSchema $schema)
    {
    }

    public function ready(): bool
    {
        return $this->schema->extrasReady();
    }

    /** The list a customer buys at: their relationship, when it has a list. */
    public function forContact(?Contact $contact): ?string
    {
        return $contact && isset(self::LISTS[$contact->relationship]) ? $contact->relationship : null;
    }

    /**
     * @param  list<int>  $productIds
     * @return array<int, array<string, float>> product id → list → price
     */
    public function prices(array $productIds): array
    {
        if (!$this->ready() || $productIds === []) {
            return [];
        }

        $prices = [];
        foreach (ProductListPrice::whereIn('product_id', $productIds)->get() as $row) {
            $prices[$row->product_id][$row->list] = (float) $row->price;
        }

        return $prices;
    }

    /**
     * Unit price of a product (or one of its variants) on a list: the list price scaled like
     * the variant is to the product, so "XL +S/ 5" keeps its proportion.
     */
    public function unitPrice(Product $product, float $base, ?string $list, array $prices): ?float
    {
        $listed = $list ? ($prices[$product->id][$list] ?? null) : null;
        if ($listed === null) {
            return null;
        }

        return (float) $product->price > 0 ? round($base * $listed / (float) $product->price, 4) : $listed;
    }

    /**
     * Saves the editor's grid: `rows[product_id][list] = price` (empty clears it).
     *
     * @return int prices written or cleared
     */
    public function save(Store $store, array $rows): int
    {
        $products = Product::where('store_id', $store->id)->whereIn('id', array_keys($rows))->pluck('id')->flip();
        $changed = 0;

        DB::transaction(function () use ($rows, $products, &$changed) {
            foreach ($rows as $productId => $lists) {
                if (!isset($products[$productId]) || !is_array($lists)) {
                    continue;
                }
                foreach (self::LISTS as $list => $label) {
                    if (!array_key_exists($list, $lists)) {
                        continue;
                    }
                    $value = $lists[$list];
                    if ($value === null || $value === '') {
                        $changed += ProductListPrice::where('product_id', $productId)->where('list', $list)->delete();
                        continue;
                    }
                    if (!is_numeric($value) || (float) $value <= 0 || (float) $value > 1000000) {
                        throw ValidationException::withMessages(['rows' => 'Los precios deben ser mayores que cero.']);
                    }
                    ProductListPrice::updateOrCreate(['product_id' => $productId, 'list' => $list], ['price' => round((float) $value, 2)]);
                    $changed++;
                }
            }
        });

        return $changed;
    }

    /** Fills a list with the retail price minus a percentage (only the empty ones, or all). */
    public function applyPercent(Store $store, string $list, float $percent, bool $overwrite): int
    {
        if (!isset(self::LISTS[$list])) {
            throw ValidationException::withMessages(['list' => 'Elige una lista de precios.']);
        }
        $taken = $overwrite ? collect() : ProductListPrice::where('list', $list)
            ->whereHas('product', fn ($q) => $q->where('store_id', $store->id))->pluck('product_id')->flip();
        $count = 0;

        DB::transaction(function () use ($store, $list, $percent, $taken, &$count) {
            Product::where('store_id', $store->id)->where('price', '>', 0)->orderBy('id')->each(function (Product $product) use ($list, $percent, $taken, &$count) {
                if (isset($taken[$product->id])) {
                    return;
                }
                ProductListPrice::updateOrCreate(['product_id' => $product->id, 'list' => $list],
                    ['price' => max(0.01, round((float) $product->price * (1 - $percent / 100), 2))]);
                $count++;
            });
        });

        return $count;
    }
}
