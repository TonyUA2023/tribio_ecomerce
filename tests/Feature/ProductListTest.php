<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Productos: buscador por código/marca/categoría, filtros, vistas rápidas, exportación y opciones solo de tienda virtual. */
class ProductListTest extends TestCase
{
    use RefreshDatabase;

    protected function migrateFreshUsing(): array
    {
        return ['--path' => array_values(array_filter(glob(database_path('migrations/*.php')),
            fn ($path) => !str_ends_with($path, '2026_09_11_182936_update_role_column_in_users_table.php'))), '--realpath' => true];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function owner(string $mode = Store::MODE_BOTH): array
    {
        $user = User::factory()->create(['role' => 'store_owner']);
        $store = Store::create(['user_id' => $user->id, 'name' => 'Ferretería Norte', 'slug' => 'norte', 'status' => 'active', 'template_name' => 'soft-market', 'mode' => $mode]);

        return [$user, $store];
    }

    private function product(Store $store, string $name, array $extra = []): Product
    {
        return Product::create($extra + ['store_id' => $store->id, 'name' => $name, 'slug' => str($name)->slug() . '-' . uniqid(), 'price' => 10, 'cost_price' => 6, 'stock' => 20,
            'low_stock_alert' => 5, 'track_stock' => true, 'is_active' => true]);
    }

    private function names($response): array
    {
        return $response->viewData('products')->pluck('name')->all();
    }

    public function test_search_finds_by_name_words_code_barcode_brand_and_category(): void
    {
        [$user, $store] = $this->owner();
        $bosch = Brand::create(['store_id' => $store->id, 'name' => 'Bosch', 'slug' => 'bosch']);
        $tools = Category::create(['store_id' => $store->id, 'name' => 'Herramientas eléctricas', 'slug' => 'electricas']);
        $drill = $this->product($store, 'Taladro percutor 13 mm', ['sku' => 'TAL-001', 'origin_code' => 'PRV-9981', 'brand_id' => $bosch->id, 'category_id' => $tools->id]);
        $this->product($store, 'Cemento Sol 42.5 kg', ['sku' => 'CEM-001']);
        ProductUnit::create(['product_id' => $drill->id, 'name' => 'Caja', 'factor' => 6, 'barcode' => '7750001234567']);

        $find = fn (string $q) => $this->names($this->actingAs($user)->get(route('dashboard.productos.index', ['q' => $q]))->assertOk());
        $this->assertSame(['Taladro percutor 13 mm'], $find('percutor taladro'), 'every word, any order');
        $this->assertSame(['Taladro percutor 13 mm'], $find('TAL-001'));
        $this->assertSame(['Taladro percutor 13 mm'], $find('PRV-9981'), 'supplier code');
        $this->assertSame(['Taladro percutor 13 mm'], $find('7750001234567'), 'scanning a packaging barcode');
        $this->assertSame(['Taladro percutor 13 mm'], $find('bosch'));
        $this->assertSame(['Taladro percutor 13 mm'], $find('eléctricas'));
        $this->assertSame([], $find('martillo'));
        $this->assertSame([], $find('100%'), 'wildcards are literal');
        // The old top-bar link still works.
        $this->assertSame(['Cemento Sol 42.5 kg'], $this->names($this->actingAs($user)->get(route('dashboard.productos.index', ['search' => 'cemento']))));
    }

    public function test_filters_sort_and_quick_view_counters(): void
    {
        [$user, $store] = $this->owner();
        $cat = Category::create(['store_id' => $store->id, 'name' => 'Pinturas', 'slug' => 'pinturas']);
        $this->product($store, 'Látex blanco', ['sku' => 'LAT1', 'price' => 50, 'stock' => 3, 'category_id' => $cat->id]);      // low
        $this->product($store, 'Brocha 3"', ['sku' => 'BRO1', 'price' => 8, 'stock' => 0]);                                    // out
        $this->product($store, 'Lija N°120', ['sku' => '', 'price' => 2, 'cost_price' => null, 'stock' => 90]);                // no code, no cost
        $this->product($store, 'Servicio de corte', ['sku' => 'SER1', 'price' => 15, 'track_stock' => false]);

        $get = fn (array $q) => $this->names($this->actingAs($user)->get(route('dashboard.productos.index', $q))->assertOk());
        $this->assertSame(['Látex blanco'], $get(['stock' => 'low']));
        $this->assertSame(['Brocha 3"'], $get(['stock' => 'out']));
        $this->assertSame(['Servicio de corte'], $get(['stock' => 'none']));
        $this->assertEqualsCanonicalizing(['Látex blanco', 'Lija N°120'], $get(['stock' => 'ok']));
        $this->assertSame(['Lija N°120'], $get(['calidad' => 'nocode']));
        $this->assertSame(['Lija N°120'], $get(['calidad' => 'nocost']));
        $this->assertSame(['Látex blanco'], $get(['categoria' => $cat->id]));
        $this->assertEqualsCanonicalizing(['Látex blanco', 'Servicio de corte'], $get(['precio_min' => 10]));
        $this->assertSame(['Brocha 3"', 'Lija N°120'], $get(['precio_max' => 9, 'orden' => 'precio_desc']));
        $this->assertSame(['Lija N°120', 'Brocha 3"', 'Servicio de corte', 'Látex blanco'], $get(['orden' => 'precio_asc']));
        $this->assertSame(['Látex blanco', 'Servicio de corte', 'Brocha 3"', 'Lija N°120'], $get(['orden' => 'precio_desc']));

        $page = $this->actingAs($user)->get(route('dashboard.productos.index'))->assertOk();
        $this->assertSame(['total' => 4, 'low' => 1, 'out' => 1, 'nocode' => 1, 'nocost' => 1], $page->viewData('counts'));
        $page->assertSee('Stock bajo')->assertSee('Sin código')->assertSee('código de barras', false);
        $this->actingAs($user)->get(route('dashboard.productos.index', ['stock' => 'low', 'q' => 'zzz']))->assertOk()->assertSee('Ningún producto coincide')->assertSee('Limpiar');
    }

    public function test_online_store_options_only_appear_when_the_business_has_an_online_store(): void
    {
        [$user, $store] = $this->owner(Store::MODE_BOTH);
        $this->product($store, 'Martillo', ['sku' => 'MAR1', 'is_featured' => true, 'is_active' => false]);

        $this->actingAs($user)->get(route('dashboard.productos.index'))->assertOk()->assertSee('Destacado')->assertSee('Inactivo')->assertSee('name="estado"', false)->assertSee('Solo destacados');
        $this->assertSame([], $this->names($this->actingAs($user)->get(route('dashboard.productos.index', ['estado' => 'active']))));

        $store->update(['mode' => Store::MODE_POS]);
        $page = $this->actingAs($user)->get(route('dashboard.productos.index', ['estado' => 'active']))->assertOk();
        $page->assertDontSee('Destacado')->assertDontSee('Inactivo')->assertDontSee('name="estado"', false)->assertDontSee('Solo destacados')->assertDontSee('Compartir por WhatsApp');
        $this->assertSame(['Martillo'], $this->names($page), 'the state filter is ignored without an online store');
    }

    public function test_the_list_exports_what_is_on_screen_as_csv(): void
    {
        [$user, $store] = $this->owner(Store::MODE_POS);
        $this->product($store, 'Cemento "Sol"', ['sku' => 'CEM1', 'price' => 30, 'cost_price' => 24, 'stock' => 7]);
        $this->product($store, 'Clavo 2"', ['sku' => 'CLA1']);

        $csv = $this->actingAs($user)->get(route('dashboard.productos.export', ['q' => 'cemento']))->assertOk();
        $this->assertStringContainsString('text/csv', $csv->headers->get('Content-Type'));
        $body = $csv->streamedContent();
        $this->assertStringContainsString('Código,"Código de origen",Producto', $body);
        $this->assertStringContainsString('CEM1,,"Cemento ""Sol""",', $body);
        $this->assertStringContainsString('24.00,30.00,20,7,5', $body);
        $this->assertStringNotContainsString('Clavo', $body);
        $this->assertStringNotContainsString('Estado', $body, 'no online store, no state column');
    }

    public function test_a_big_catalog_is_one_page_and_a_handful_of_queries(): void
    {
        [$user, $store] = $this->owner();
        $rows = [];
        for ($i = 1; $i <= 1500; $i++) {
            $rows[] = ['store_id' => $store->id, 'name' => "Producto {$i}", 'slug' => "producto-{$i}", 'sku' => 'P' . str_pad((string) $i, 5, '0', STR_PAD_LEFT), 'price' => 10, 'cost_price' => 5, 'stock' => 10, 'track_stock' => 1, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()];
        }
        foreach (array_chunk($rows, 300) as $chunk) {
            Product::insert($chunk);
        }

        $this->actingAs($user)->get(route('dashboard.productos.index'));
        DB::flushQueryLog();
        DB::enableQueryLog();
        $page = $this->actingAs($user)->get(route('dashboard.productos.index', ['q' => 'producto 14']))->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(20, $queries);
        $this->assertLessThanOrEqual(25, $page->viewData('products')->count());
        $this->assertSame(100, $this->actingAs($user)->get(route('dashboard.productos.index', ['por_pagina' => 100]))->viewData('products')->count());
        $this->assertSame(25, $this->actingAs($user)->get(route('dashboard.productos.index', ['por_pagina' => 7]))->viewData('products')->count(), 'only 25, 50 or 100');
    }

    public function test_categories_hide_the_online_store_options_without_an_online_store(): void
    {
        [$user, $store] = $this->owner(Store::MODE_BOTH);
        $cat = Category::create(['store_id' => $store->id, 'name' => 'Pinturas', 'slug' => 'pinturas', 'is_featured' => true, 'show_in_header' => true]);

        $this->actingAs($user)->get(route('dashboard.categorias.index'))->assertOk()->assertSee('Encabezado')->assertSee('Destacar');
        $this->actingAs($user)->get(route('dashboard.categorias.edit', $cat))->assertOk()->assertSee('Mostrar en el encabezado')->assertSee('Foto de la Categoría');

        $store->update(['mode' => Store::MODE_POS]);
        $this->actingAs($user)->get(route('dashboard.categorias.index'))->assertOk()->assertDontSee('Encabezado')->assertDontSee('Destacar')->assertDontSee('En encabezado');
        $this->actingAs($user)->get(route('dashboard.categorias.edit', $cat))->assertOk()->assertDontSee('Mostrar en el encabezado')->assertDontSee('Foto de la Categoría')->assertDontSee('Categoría destacada');

        // The form keeps what the owner had set (hidden fields), so saving it does not wipe it for a future online store.
        $this->actingAs($user)->get(route('dashboard.categorias.edit', $cat))->assertSee('name="is_featured" value="1"', false)->assertSee('name="show_in_header" value="1"', false);
        $this->actingAs($user)->put(route('dashboard.categorias.update', $cat), ['name' => 'Pinturas y acabados', 'is_active' => '1', 'is_featured' => '1', 'show_in_header' => '1'])->assertSessionHasNoErrors();
        $this->assertSame([true, true, 'Pinturas y acabados'], [$cat->fresh()->is_featured, $cat->fresh()->show_in_header, $cat->fresh()->name]);
    }

    public function test_registering_a_product_in_a_pos_only_business_is_short_and_the_manufacturer_code_is_searchable(): void
    {
        [$user, $store] = $this->owner(Store::MODE_BOTH);
        $both = $this->actingAs($user)->get(route('dashboard.productos.create'))->assertOk();
        $both->assertSee('Estado y Visibilidad')->assertSee('Descripción completa')->assertSee('Video corto');

        $store->update(['mode' => Store::MODE_POS]);
        $page = $this->actingAs($user)->get(route('dashboard.productos.create'))->assertOk();
        $page->assertDontSee('Estado y Visibilidad')->assertDontSee('Descripción completa')->assertDontSee('Video corto')->assertDontSee('Mensaje cuando el stock llega a 0')
            ->assertSee('name="is_active" value="1"', false)->assertSee('Código del fabricante o del repuesto')->assertSee('SKU / Código')->assertSee('Nombre del producto');

        $product = $this->product($store, 'Filtro de aceite tractor', ['sku' => 'FIL-1', 'origin_code' => 'RE504836']);
        $this->actingAs($user)->get(route('dashboard.productos.edit', $product))->assertOk()->assertDontSee('Estado y Visibilidad')->assertSee('name="is_active" value="1"', false);

        // The register finds it by the manufacturer's code, typed or scanned.
        $catalog = app(\App\Services\Pos\PosCatalog::class);
        $this->assertSame([$product->id], array_column($catalog->search($store, 'RE5048')['products'], 'id'));
        $this->assertSame($product->id, $catalog->search($store, 'RE504836')['exact']['product_id']);
    }
}
