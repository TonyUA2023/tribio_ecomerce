<?php

namespace App\Services\Catalog;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard → Productos: the list a business with thousands of products can work in. One place builds the
 * query from the URL (search, filters, sort) so the screen, its counters and the CSV export always agree.
 *
 * Search: every word must match the name, the code (SKU / código de origen), a packaging barcode, the brand or
 * the category — so typing, or scanning, a code finds the product.
 */
class ProductListing
{
    public const SORTS = [
        'nombre'      => 'Nombre A–Z',
        'nombre_desc' => 'Nombre Z–A',
        'recientes'   => 'Más recientes',
        'precio_asc'  => 'Precio: menor a mayor',
        'precio_desc' => 'Precio: mayor a menor',
        'stock_asc'   => 'Stock: menor a mayor',
        'stock_desc'  => 'Stock: mayor a menor',
        'vendidos'    => 'Más vendidos',
    ];

    public const STOCK = ['ok' => 'Con stock', 'low' => 'Stock bajo', 'out' => 'Sin stock', 'none' => 'Sin control de stock'];

    public const QUALITY = ['nocode' => 'Sin código', 'nocost' => 'Sin costo', 'nocat' => 'Sin categoría', 'nophoto' => 'Sin foto'];

    public const PER_PAGE = [25, 50, 100];

    /** Normalizes what came in the URL (old links used `search`, `status`, `stock=low|out`). @return array<string, mixed> */
    public function filters(array $input, Store $store): array
    {
        $number = fn ($value) => is_numeric($value) && (float) $value >= 0 ? round((float) $value, 2) : null;
        $perPage = (int) ($input['por_pagina'] ?? 25);

        return [
            'q'          => mb_substr(trim((string) ($input['q'] ?? $input['search'] ?? '')), 0, 80),
            'categoria'  => ctype_digit((string) ($input['categoria'] ?? $input['category'] ?? '')) ? (int) ($input['categoria'] ?? $input['category']) : null,
            'marca'      => ctype_digit((string) ($input['marca'] ?? '')) ? (int) $input['marca'] : null,
            'stock'      => isset(self::STOCK[$input['stock'] ?? '']) ? $input['stock'] : null,
            // Visibility and "destacado" belong to the online store: a POS-only business never filters by them.
            'estado'     => $store->hasStorefront() && in_array($input['estado'] ?? $input['status'] ?? '', ['active', 'inactive'], true) ? ($input['estado'] ?? $input['status']) : null,
            'destacado'  => $store->hasStorefront() && filter_var($input['destacado'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'calidad'    => isset(self::QUALITY[$input['calidad'] ?? '']) ? $input['calidad'] : null,
            'precio_min' => $number($input['precio_min'] ?? null),
            'precio_max' => $number($input['precio_max'] ?? null),
            'orden'      => isset(self::SORTS[$input['orden'] ?? '']) ? $input['orden'] : 'nombre',
            'por_pagina' => in_array($perPage, self::PER_PAGE, true) ? $perPage : 25,
        ];
    }

    /** @return Builder<Product> */
    public function query(Store $store, array $f): Builder
    {
        $query = Product::query()->where('store_id', $store->id);

        foreach (array_slice(array_values(array_filter(explode(' ', (string) preg_replace('/\s+/u', ' ', $f['q'])))), 0, 6) as $word) {
            $like = '%' . $this->escape($word) . '%';
            $query->where(fn ($w) => $w->whereRaw("name LIKE ? ESCAPE '!'", [$like])
                ->orWhereRaw("sku LIKE ? ESCAPE '!'", [$like])
                ->orWhereRaw("origin_code LIKE ? ESCAPE '!'", [$like])
                ->orWhereRaw("location LIKE ? ESCAPE '!'", [$like])
                ->orWhereHas('units', fn ($u) => $u->where('barcode', $word))
                ->orWhereHas('brand', fn ($b) => $b->whereRaw("name LIKE ? ESCAPE '!'", [$like]))
                ->orWhereHas('category', fn ($c) => $c->whereRaw("name LIKE ? ESCAPE '!'", [$like])));
        }

        if ($f['categoria']) {
            $ids = $this->withChildren($store, $f['categoria']);
            $query->where(fn ($w) => $w->whereIn('category_id', $ids)->orWhereHas('categories', fn ($c) => $c->whereIn('categories.id', $ids)));
        }
        if ($f['marca']) {
            $query->where('brand_id', $f['marca']);
        }
        $query->when($f['stock'] === 'ok', fn ($q) => $q->where('track_stock', true)->where('stock', '>', 0))
            ->when($f['stock'] === 'low', fn ($q) => $q->where('track_stock', true)->where('stock', '>', 0)->whereColumn('stock', '<=', 'low_stock_alert'))
            ->when($f['stock'] === 'out', fn ($q) => $q->where('track_stock', true)->where('stock', '<=', 0))
            ->when($f['stock'] === 'none', fn ($q) => $q->where('track_stock', false))
            ->when($f['estado'] === 'active', fn ($q) => $q->where('is_active', true))
            ->when($f['estado'] === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($f['destacado'], fn ($q) => $q->where('is_featured', true))
            ->when($f['calidad'] === 'nocode', fn ($q) => $q->where(fn ($w) => $w->whereNull('sku')->orWhere('sku', '')))
            ->when($f['calidad'] === 'nocost', fn ($q) => $q->where(fn ($w) => $w->whereNull('cost_price')->orWhere('cost_price', '<=', 0)))
            ->when($f['calidad'] === 'nocat', fn ($q) => $q->whereNull('category_id')->whereDoesntHave('categories'))
            ->when($f['calidad'] === 'nophoto', fn ($q) => $q->where(fn ($w) => $w->whereNull('image_path')->orWhere('image_path', '')))
            ->when($f['precio_min'] !== null, fn ($q) => $q->where('price', '>=', $f['precio_min']))
            ->when($f['precio_max'] !== null, fn ($q) => $q->where('price', '<=', $f['precio_max']));

        return match ($f['orden']) {
            'nombre_desc' => $query->orderByDesc('name'),
            'recientes'   => $query->orderByDesc('id'),
            'precio_asc'  => $query->orderBy('price')->orderBy('name'),
            'precio_desc' => $query->orderByDesc('price')->orderBy('name'),
            'stock_asc'   => $query->orderBy('stock')->orderBy('name'),
            'stock_desc'  => $query->orderByDesc('stock')->orderBy('name'),
            'vendidos'    => $query->orderByDesc('sold_count')->orderBy('name'),
            default       => $query->orderBy('name'),
        };
    }

    /**
     * The numbers over the table: everything the business has, regardless of the filters in use.
     *
     * @return array{total: int, low: int, out: int, nocode: int, nocost: int}
     */
    public function counts(Store $store): array
    {
        $row = DB::table('products')->where('store_id', $store->id)->whereNull('deleted_at')->selectRaw(
            "COUNT(*) as total,
             SUM(CASE WHEN track_stock = 1 AND stock > 0 AND stock <= low_stock_alert THEN 1 ELSE 0 END) as low,
             SUM(CASE WHEN track_stock = 1 AND stock <= 0 THEN 1 ELSE 0 END) as out_of_stock,
             SUM(CASE WHEN sku IS NULL OR sku = '' THEN 1 ELSE 0 END) as nocode,
             SUM(CASE WHEN cost_price IS NULL OR cost_price <= 0 THEN 1 ELSE 0 END) as nocost"
        )->first();

        return ['total' => (int) $row->total, 'low' => (int) $row->low, 'out' => (int) $row->out_of_stock, 'nocode' => (int) $row->nocode, 'nocost' => (int) $row->nocost];
    }

    /** True when the URL asks for anything beyond the plain list. */
    public function isFiltered(array $f): bool
    {
        return $f['q'] !== '' || $f['categoria'] || $f['marca'] || $f['stock'] || $f['estado'] || $f['destacado'] || $f['calidad'] || $f['precio_min'] !== null || $f['precio_max'] !== null;
    }

    /** @return list<int> the category and the ones inside it */
    private function withChildren(Store $store, int $categoryId): array
    {
        $children = Category::where('store_id', $store->id)->where('parent_id', $categoryId)->pluck('id')->all();

        return array_merge([$categoryId], $children);
    }

    private function escape(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}
