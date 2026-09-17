<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function migrateFreshUsing(): array
    {
        // The legacy role conversion uses MySQL-only MODIFY syntax. These tests
        // use the original store_owner role and keep the production migrations intact.
        return ['--path' => array_values(array_filter(glob(database_path('migrations/*.php')),
            fn($path) => !str_ends_with($path, '2026_09_11_182936_update_role_column_in_users_table.php'))), '--realpath' => true];
    }

    public function test_workspace_and_search_only_show_the_authenticated_stores_data(): void
    {
        $owner = User::factory()->create(['role' => 'store_owner']);
        $otherOwner = User::factory()->create(['role' => 'store_owner']);
        $store = Store::create(['user_id' => $owner->id, 'name' => 'Mi tienda visible', 'slug' => 'mi-tienda', 'status' => 'active']);
        $otherStore = Store::create(['user_id' => $otherOwner->id, 'name' => 'Tienda privada ajena', 'slug' => 'otra-tienda', 'status' => 'active']);
        $product = Product::create(['store_id' => $store->id, 'name' => 'Producto visible', 'slug' => 'visible', 'price' => 25, 'is_active' => true]);
        $otherProduct = Product::create(['store_id' => $otherStore->id, 'name' => 'Producto privado ajeno', 'slug' => 'ajeno', 'price' => 999, 'is_active' => true]);
        $order = Order::create(['store_id' => $store->id, 'order_number' => 'VISIBLE-001', 'customer_name' => 'Cliente visible', 'total' => 25]);
        $otherOrder = Order::create(['store_id' => $otherStore->id, 'order_number' => 'PRIVADO-001', 'customer_name' => 'Cliente privado ajeno', 'total' => 999]);

        $this->actingAs($owner)->get(route('dashboard.index'))
            ->assertOk()->assertSee('Mi tienda visible')->assertSee('Producto visible')->assertSee('Cliente visible')
            ->assertDontSee('Tienda privada ajena')->assertDontSee('Producto privado ajeno')->assertDontSee('Cliente privado ajeno')
            ->assertSee(route('dashboard.productos.edit', $product))->assertSee(route('dashboard.pedidos.show', $order));

        $this->get(route('dashboard.productos.index', ['search' => 'Producto']))
            ->assertOk()->assertSee('Producto visible')->assertDontSee('Producto privado ajeno');
        $this->get(route('dashboard.pedidos.index', ['search' => 'Cliente']))
            ->assertOk()->assertViewHas('orders', fn($orders) => $orders->count() === 1 && $orders->first()->id === $order->id);
        $this->get(route('dashboard.productos.edit', $otherProduct))->assertForbidden();
        $this->get(route('dashboard.pedidos.show', $otherOrder))->assertForbidden();
    }

    public function test_empty_inactive_store_retains_setup_and_catalog_actions(): void
    {
        $owner = User::factory()->create(['role' => 'store_owner']);
        Store::create(['user_id' => $owner->id, 'name' => 'Nueva tienda', 'slug' => 'nueva', 'status' => 'draft']);

        $this->actingAs($owner)->get(route('dashboard.index'))->assertOk()
            ->assertSee('Tu tienda no está activa')->assertSee('Agregar producto')
            ->assertSee(route('dashboard.store.edit'))->assertSee('Todo listo para tu primera venta');
    }
}
