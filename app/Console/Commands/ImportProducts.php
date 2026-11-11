<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Services\Inventory\StockService;
use App\Services\Pricing\WholesalePricing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Loads a client's inventory from a CSV (UTF-8, comma or semicolon) into one store:
 * nombre, stock, ubicacion, costo, precio, precio_x6, categoria, marca, activo, unidad, sku.
 * Only nombre and precio are required. Opening stock enters as a lot at its cost, so margins are
 * right from the first sale. Safe to run twice: products whose name already exists are skipped.
 */
class ImportProducts extends Command
{
    protected $signature = 'tribio:import-products
        {store : Slug of the store, e.g. el-yunque}
        {file : Path of the CSV}
        {--dry-run : Read and check everything, save nothing}
        {--tier-qty=6 : Units from which the "precio_x6" column applies}';

    protected $description = 'Import products (with stock, cost, location, wholesale price, category and brand) from a CSV into a store';

    public function handle(StockService $stock): int
    {
        $store = Store::where('slug', $this->argument('store'))->first();
        if (!$store) {
            $this->error("No store with slug '{$this->argument('store')}'.");

            return self::FAILURE;
        }
        $rows = $this->read((string) $this->argument('file'));
        if ($rows === null) {
            return self::FAILURE;
        }

        $hasLocation = Schema::hasColumn('products', 'location');
        $tiersReady = WholesalePricing::ready();
        $tierQty = max(2, (int) $this->option('tier-qty'));
        $existing = $store->products()->withTrashed()->pluck('name')->map(fn ($n) => mb_strtolower(trim($n)))->flip();
        $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $store->slug), 0, 3)) ?: 'PROD';

        $errors = [];
        $plan = [];
        foreach ($rows as $line => $row) {
            $name = trim((string) ($row['nombre'] ?? ''));
            $price = $this->number($row['precio'] ?? null);
            if ($name === '') {
                $errors[] = "Fila {$line}: falta el nombre.";
            } elseif (mb_strlen($name) > 255) {
                $errors[] = "Fila {$line}: el nombre pasa de 255 letras.";
            } elseif ($price === null || $price < 0) {
                $errors[] = "Fila {$line}: el precio no es un número válido.";
            } else {
                $plan[] = ['line' => $line, 'name' => $name, 'price' => $price] + $row;
            }
        }
        if ($errors) {
            collect($errors)->take(30)->each(fn ($e) => $this->error($e));
            $this->error('Nada se guardó. Corrige el archivo y vuelve a intentar.');

            return self::FAILURE;
        }

        $new = array_values(array_filter($plan, fn ($r) => !$existing->has(mb_strtolower($r['name']))));
        $skipped = count($plan) - count($new);
        $this->line(count($plan) . ' filas leídas · ' . count($new) . ' productos nuevos · ' . $skipped . ' ya existían (se omiten)');
        if (!$hasLocation) {
            $this->warn('Falta la migración de «ubicación»: corre php artisan migrate --force para guardarla. Se omite por ahora.');
        }
        if (!$tiersReady) {
            $this->warn('Falta la migración de precios por mayor: se omite el precio por cantidad.');
        }
        if ($this->option('dry-run')) {
            $this->info('Prueba terminada: no se guardó nada.');

            return self::SUCCESS;
        }
        if (!$new) {
            return self::SUCCESS;
        }

        $created = 0;
        $withStock = 0;
        DB::transaction(function () use ($new, $store, $stock, $hasLocation, $tiersReady, $tierQty, $prefix, &$created, &$withStock) {
            $categories = [];
            $brands = [];
            $order = (int) $store->products()->max('sort_order');
            $number = $store->products()->count();
            $bar = $this->output->createProgressBar(count($new));

            foreach ($new as $row) {
                $categoryName = trim((string) ($row['categoria'] ?? ''));
                $brandName = trim((string) ($row['marca'] ?? ''));
                $cost = $this->number($row['costo'] ?? null);
                $tier = $tiersReady ? $this->number($row['precio_x6'] ?? null) : null;
                $active = !isset($row['activo']) || trim((string) $row['activo']) === '' || in_array(strtolower(trim((string) $row['activo'])), ['1', 'si', 'sí', 'true'], true);

                $product = Product::create(array_filter([
                    'store_id'        => $store->id,
                    'category_id'     => $categoryName === '' ? null : ($categories[mb_strtolower($categoryName)] ??= $this->category($store, $categoryName))->id,
                    'brand_id'        => $brandName === '' ? null : ($brands[mb_strtolower($brandName)] ??= $this->brand($store, $brandName))->id,
                    'name'            => $row['name'],
                    'slug'            => $this->slug($store, $row['name']),
                    'sku'             => filled($row['sku'] ?? null) ? trim($row['sku']) : $this->sku($store, $prefix, ++$number),
                    'price'           => $row['price'],
                    'cost_price'      => $cost,
                    'stock'           => 0,
                    'track_stock'     => true,
                    'low_stock_alert' => 5,
                    'unit'            => filled($row['unidad'] ?? null) ? trim($row['unidad']) : 'unidad',
                    'location'        => $hasLocation && filled($row['ubicacion'] ?? null) ? Str::limit(trim($row['ubicacion']), 80, '') : null,
                    'price_tiers'     => $tier && $tier > 0 && $tier < $row['price'] ? [['min_qty' => $tierQty, 'price' => round($tier, 2)]] : null,
                    'is_active'       => $active,
                    'sort_order'      => ++$order,
                ], fn ($v) => $v !== null) + ['is_active' => $active]);

                $quantity = (int) $this->number($row['stock'] ?? null);
                if ($quantity > 0) {
                    $stock->adjust($product, 'in', $quantity, 'Stock inicial (importación)', null, ['unit_cost' => $cost]);
                    $withStock++;
                }
                $created++;
                $bar->advance();
            }
            $bar->finish();
            $this->newLine();
        });

        $this->info("Listo: {$created} productos creados ({$withStock} con stock inicial).");

        return self::SUCCESS;
    }

    /** @return array<int, array<string, string>>|null rows keyed by header, indexed by file line */
    private function read(string $path): ?array
    {
        if (!is_file($path) || !is_readable($path)) {
            $this->error("No encuentro el archivo: {$path}");

            return null;
        }
        $handle = fopen($path, 'r');
        $first = fgets($handle);
        rewind($handle);
        $delimiter = substr_count((string) $first, ';') > substr_count((string) $first, ',') ? ';' : ',';

        $header = fgetcsv($handle, 0, $delimiter);
        if (!$header) {
            $this->error('El archivo está vacío.');

            return null;
        }
        $header = array_map(fn ($h) => Str::of((string) $h)->replace("\xEF\xBB\xBF", '')->lower()->ascii()->trim()->replace(' ', '_')->toString(), $header);
        if (!in_array('nombre', $header, true) || !in_array('precio', $header, true)) {
            $this->error('El archivo necesita al menos las columnas «nombre» y «precio».');

            return null;
        }

        $rows = [];
        for ($line = 2; ($data = fgetcsv($handle, 0, $delimiter)) !== false; $line++) {
            if (count(array_filter($data, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $rows[$line] = array_combine($header, array_pad(array_slice($data, 0, count($header)), count($header), ''));
        }
        fclose($handle);

        return $rows;
    }

    private function number(mixed $value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
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
        $slug = $base = Str::slug($name) ?: 'categoria';
        for ($n = 2; Category::where('store_id', $store->id)->where('slug', $slug)->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        return Category::create(['store_id' => $store->id, 'name' => $name, 'slug' => $slug, 'is_active' => true, 'sort_order' => (int) Category::where('store_id', $store->id)->max('sort_order') + 1]);
    }

    private function brand(Store $store, string $name): Brand
    {
        $name = Str::limit($name, 80, '');
        $found = Brand::where('store_id', $store->id)->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
        if ($found) {
            return $found;
        }
        $slug = $base = Str::slug($name) ?: 'marca';
        for ($n = 2; Brand::where('store_id', $store->id)->where('slug', $slug)->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        return Brand::create(['store_id' => $store->id, 'name' => $name, 'slug' => $slug]);
    }

    private function slug(Store $store, string $name): string
    {
        $slug = $base = Str::slug($name) ?: 'producto';
        for ($n = 2; Product::withTrashed()->where('store_id', $store->id)->where('slug', $slug)->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        return $slug;
    }

    private function sku(Store $store, string $prefix, int $number): string
    {
        do {
            $sku = $prefix . '-' . str_pad((string) $number++, 4, '0', STR_PAD_LEFT);
        } while (Product::withTrashed()->where('store_id', $store->id)->where('sku', $sku)->exists());

        return $sku;
    }
}
