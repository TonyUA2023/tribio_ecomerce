<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Compras → Producto: server-side type-ahead instead of a select with the whole catalog. */
class PurchaseProductSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function migrateFreshUsing(): array
    {
        return ['--path' => array_values(array_filter(glob(database_path('migrations/*.php')),
            fn ($path) => !str_ends_with($path, '2026_09_11_182936_update_role_column_in_users_table.php'))), '--realpath' => true];
    }

    private function owner(string $slug = 'ferreteria'): array
    {
        $user = User::factory()->create(['role' => 'store_owner']);
        $store = Store::create(['user_id' => $user->id, 'name' => 'Ferretería ' . $slug, 'slug' => $slug, 'status' => 'active', 'template_name' => 'soft-market']);

        return [$user, $store];
    }

    private function product(Store $store, string $name, array $attributes = []): Product
    {
        return Product::create($attributes + [
            'store_id' => $store->id, 'name' => $name, 'slug' => str($name)->slug() . '-' . uniqid(), 'price' => 10,
            'stock' => 20, 'low_stock_alert' => 5, 'track_stock' => true, 'is_active' => true,
        ]);
    }

    private function search(User $user, string $q = ''): array
    {
        return $this->actingAs($user)->getJson(route('dashboard.compras.products', ['q' => $q]))->assertOk()->json();
    }

    public function test_matches_every_word_in_any_order_and_by_sku(): void
    {
        [$user, $store] = $this->owner();
        $this->product($store, 'Tubo PVC 1/2 pulgada', ['sku' => 'PVC-12']);
        $this->product($store, 'Tubo PVC 3/4 pulgada', ['sku' => 'PVC-34']);
        $this->product($store, 'Cemento Sol 42.5 kg');

        $this->assertCount(2, $this->search($user, 'pvc tubo'));
        $this->assertSame(['Tubo PVC 3/4 pulgada'], array_column($this->search($user, 'pulgada 3/4'), 'name'));
        $this->assertSame(['Tubo PVC 1/2 pulgada'], array_column($this->search($user, 'pvc-12'), 'name'));
        $this->assertSame([], $this->search($user, 'martillo'));
    }

    public function test_exact_sku_comes_first_then_names_starting_with_the_text(): void
    {
        [$user, $store] = $this->owner();
        $this->product($store, 'Adaptador clavo', ['sku' => 'X1']);
        $this->product($store, 'Clavo 2 pulgadas', ['sku' => 'CL2']);
        $this->product($store, 'Alambre', ['sku' => 'clavo']);

        $this->assertSame(['Alambre', 'Clavo 2 pulgadas', 'Adaptador clavo'], array_column($this->search($user, 'clavo'), 'name'));
    }

    public function test_like_wildcards_are_literal(): void
    {
        [$user, $store] = $this->owner();
        $this->product($store, 'Cinta 100% algodón');
        $this->product($store, 'Cinta aislante');

        $this->assertSame(['Cinta 100% algodón'], array_column($this->search($user, '100%'), 'name'));
        $this->assertSame([], $this->search($user, '_'));
    }

    public function test_only_buyable_products_of_my_store_and_capped_at_twenty(): void
    {
        [$user, $store] = $this->owner();
        [, $other] = $this->owner('otra');
        $this->product($other, 'Tornillo ajeno');
        $this->product($store, 'Tornillo sin control', ['track_stock' => false]);
        foreach (range(1, 25) as $i) {
            $this->product($store, sprintf('Tornillo %02d', $i));
        }

        $found = $this->search($user, 'tornillo');
        $this->assertCount(20, $found);
        $this->assertNotContains('Tornillo ajeno', array_column($found, 'name'));
        $this->assertNotContains('Tornillo sin control', array_column($found, 'name'));
        $this->assertEqualsCanonicalizing(['id', 'name', 'sku', 'cost', 'stock', 'low', 'units'], array_keys($found[0]));
    }

    public function test_empty_query_suggests_what_is_running_low(): void
    {
        [$user, $store] = $this->owner();
        $this->product($store, 'Cemento', ['stock' => 2, 'cost_price' => 24]);
        $this->product($store, 'Pintura', ['stock' => 50]);

        $found = $this->search($user);
        $this->assertSame(['Cemento'], array_column($found, 'name'));
        $this->assertTrue($found[0]['low']);
        $this->assertEquals(24.0, $found[0]['cost']);
    }

    public function test_requires_login_and_form_no_longer_ships_the_catalog(): void
    {
        [$user, $store] = $this->owner();
        foreach (range(1, 30) as $i) {
            $this->product($store, "Repuesto {$i}");
        }

        $this->getJson(route('dashboard.compras.products'))->assertUnauthorized();
        $this->actingAs($user)->get(route('dashboard.compras.create'))->assertOk()
            ->assertSee('compra_buscar_producto', false)->assertDontSee('Repuesto 7', false);
    }

    public function test_dashboard_sidebar_keeps_its_scroll_between_pages(): void
    {
        [$user] = $this->owner();

        $this->actingAs($user)->get(route('dashboard.compras.index'))->assertOk()
            ->assertSee('data-keep-scroll="dashboard"', false)->assertSee("tribio:nav-scroll:", false);
    }
}
