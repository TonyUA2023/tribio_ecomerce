<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductComponent;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Un negocio con miles de productos: las pantallas no cargan todo el catálogo. */
class LargeCatalogTest extends TestCase
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
        $store = Store::create(['user_id' => $user->id, 'name' => 'Ferretería Grande', 'slug' => 'grande', 'status' => 'active', 'template_name' => 'soft-market', 'mode' => $mode]);

        return [$user, $store];
    }

    /** @return int[] ids */
    private function bulk(Store $store, int $count, array $extra = [], string $prefix = 'Producto'): array
    {
        $rows = [];
        for ($i = 1; $i <= $count; $i++) {
            $rows[] = $extra + ['store_id' => $store->id, 'name' => "{$prefix} {$i}", 'slug' => strtolower($prefix) . "-{$store->id}-{$i}", 'sku' => strtoupper(substr($prefix, 0, 3)) . str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                'price' => 10, 'stock' => 30, 'track_stock' => true, 'is_active' => true, 'sold_count' => 0, 'created_at' => now()->subDays(200), 'updated_at' => now()];
        }
        foreach (array_chunk($rows, 300) as $chunk) {
            Product::insert($chunk);
        }

        return Product::where('store_id', $store->id)->where('name', 'like', "{$prefix} %")->pluck('id')->all();
    }

    public function test_reposicion_shows_the_most_valuable_rows_and_counts_all_of_them(): void
    {
        [$user, $store] = $this->owner();
        $this->bulk($store, 130, ['cost_price' => 4]);

        $rows = fn (string $html) => preg_match_all('~href="[^"]*/dashboard/inventario/\d+"~', $html);

        $page = $this->actingAs($user)->get(route('dashboard.inventario.reorder'))->assertOk();
        $page->assertSee('130 productos')->assertSee('Mostrando los 60 con más dinero detenido de 130')->assertSee('Dinero detenido');
        $this->assertSame(60, $rows($page->getContent()));
        $page->assertSee('S/ ' . number_format(130 * 30 * 4, 2), false); // the money counts every product, not only the rows shown

        $all = $this->actingAs($user)->get(route('dashboard.inventario.reorder', ['todo' => 1]))->assertOk();
        $this->assertSame(130, $rows($all->getContent()));
        $this->assertStringNotContainsString('Mostrando los', $all->getContent());
    }

    public function test_the_combo_form_searches_products_on_the_server_instead_of_carrying_them_all(): void
    {
        [$user, $store] = $this->owner();
        $ids = $this->bulk($store, 150, [], 'Cemento');
        $this->bulk($store, 3, ['track_stock' => false], 'Servicio');
        $kitId = $this->bulk($store, 1, [], 'Pack')[0];
        ProductComponent::create(['kit_product_id' => $kitId, 'component_product_id' => $ids[0], 'quantity' => 2]);

        $page = $this->actingAs($user)->get(route('dashboard.inventario.product', $ids[5]))->assertOk();
        $page->assertSee('combo_buscar_producto', false)->assertSee('componentes?except=' . $ids[5], false);
        $this->assertStringNotContainsString('Cemento 77', $page->getContent(), 'the products are not shipped with the page');
        $this->assertLessThan(150, substr_count($page->getContent(), '<option'));

        $found = $this->actingAs($user)->getJson(route('dashboard.inventario.components', ['q' => 'cemento 14', 'except' => $ids[5]]))->assertOk()->json();
        $this->assertNotEmpty($found);
        $this->assertContains('Cemento 14', array_column($found, 'name'));
        $this->assertNotContains($ids[5], array_column($found, 'id') , 'the combo being edited is left out');
        $this->assertSame([], $this->actingAs($user)->getJson(route('dashboard.inventario.components', ['q' => 'servicio']))->assertOk()->json(), 'products without stock control cannot be components');
        $this->assertNotContains('Pack 1', array_column($this->actingAs($user)->getJson(route('dashboard.inventario.components', ['q' => 'pack']))->json(), 'name'), 'a combo cannot be a component');
        $this->assertCount(20, $this->actingAs($user)->getJson(route('dashboard.inventario.components', ['q' => 'cemento']))->json());

        // The combo still saves as before.
        $basket = $this->bulk($store, 1, ['stock' => 0], 'Canasta')[0];
        $this->actingAs($user)->put(route('dashboard.inventario.kit.update', $basket), ['components_json' => json_encode([['product_id' => $ids[10], 'quantity' => 3]])])->assertSessionHasNoErrors();
        $this->assertSame([[$ids[10], 3]], ProductComponent::where('kit_product_id', $basket)->get()->map(fn ($c) => [$c->component_product_id, $c->quantity])->all());
        $this->actingAs($user)->get(route('dashboard.inventario.product', $basket))->assertOk()->assertSee(Product::find($ids[10])->name)->assertSee('1 productos');
    }

    public function test_the_simple_screen_finds_products_beyond_the_ones_it_carries(): void
    {
        [$user, $store] = $this->owner(Store::MODE_SIMPLE);
        $this->bulk($store, 405, ['sold_count' => 9], 'Surtido');           // the 400 best sellers fill the screen
        $this->bulk($store, 1, ['sold_count' => 0, 'price' => 7.5], 'Rarisimo');
        $this->bulk($store, 1, ['sold_count' => 0, 'has_variants' => true], 'Rarisimo variante');

        $page = $this->actingAs($user)->get(route('dashboard.simple.index'))->assertOk();
        $page->assertSee('vender\\/buscar', false);
        $this->assertStringNotContainsString('Rarisimo 1', $page->getContent(), 'not among the 400 on the screen');

        $found = $this->actingAs($user)->getJson(route('dashboard.simple.search', ['q' => 'rarisimo']))->assertOk()->json();
        $this->assertSame(['Rarisimo 1'], array_column($found, 'name'), 'a product with variants is not sold from this screen');
        $this->assertSame(7.5, $found[0]['price']);
        $this->assertSame([], $this->actingAs($user)->getJson(route('dashboard.simple.search', ['q' => '']))->json());
        $this->assertSame([], $this->actingAs($user)->getJson(route('dashboard.simple.search', ['q' => 'zzzz']))->json());

        // A small business does not carry the extra search at all.
        [$small] = $this->smallOwner();
        $this->actingAs($small)->get(route('dashboard.simple.index'))->assertOk()->assertDontSee('vender\\/buscar', false);
    }

    private function smallOwner(): array
    {
        $user = User::factory()->create(['role' => 'store_owner']);
        $store = Store::create(['user_id' => $user->id, 'name' => 'Bodega', 'slug' => 'bodega', 'status' => 'active', 'template_name' => 'soft-market', 'mode' => Store::MODE_SIMPLE]);
        $this->bulk($store, 5, [], 'Gaseosa');

        return [$user, $store];
    }
}
