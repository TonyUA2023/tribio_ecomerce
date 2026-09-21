<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DashboardWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function migrateFreshUsing(): array
    {
        if (config('database.default') !== 'sqlite') {
            return [];
        }
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

        foreach (['dashboard.productos.create', 'dashboard.categorias.index', 'dashboard.categorias.create',
            'dashboard.marcas.index', 'dashboard.marcas.create', 'dashboard.galeria.index',
            'dashboard.inventario.index', 'dashboard.shipping.index', 'dashboard.gateway.edit',
            'dashboard.store.edit', 'dashboard.store.templates'] as $screen) {
            $this->get(route($screen))->assertOk()->assertSee('dashboard-navigation');
        }
    }

    public function test_empty_inactive_store_retains_setup_and_catalog_actions(): void
    {
        $owner = User::factory()->create(['role' => 'store_owner']);
        Store::create(['user_id' => $owner->id, 'name' => 'Nueva tienda', 'slug' => 'nueva', 'status' => 'draft']);

        $this->actingAs($owner)->get(route('dashboard.index'))->assertOk()
            ->assertSee('Tu tienda no está activa')->assertSee('Agregar producto')
            ->assertSee(route('dashboard.store.edit'))->assertSee('Todo listo para tu primera venta');
    }

    public function test_image_controls_keep_existing_upload_and_removal_flows(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create(['role' => 'store_owner']);
        $store = Store::create(['user_id' => $owner->id, 'name' => 'Fotos', 'slug' => 'fotos', 'status' => 'active']);
        $product = Product::create(['store_id' => $store->id, 'name' => 'Producto con foto', 'slug' => 'foto', 'price' => 20, 'stock' => 2]);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
        $upload = fn() => UploadedFile::fake()->createWithContent('foto.png', $png);
        $this->actingAs($owner);

        $this->put(route('dashboard.productos.update', $product), [
            'name' => $product->name, 'price' => 20, 'stock' => 2, 'image' => $upload(), 'gallery' => [$upload()],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $product->refresh();
        Storage::disk('public')->assertExists($product->image_path);
        Storage::disk('public')->assertExists($product->gallery_images[0]);
        $this->get(route('dashboard.productos.edit', $product))->assertOk()->assertSee($product->image_url)
            ->assertSee('data-image-picker', false)->assertSee('Deshacer selección');

        foreach (['logo', 'cover'] as $field) {
            $this->post(route('dashboard.store.' . $field), [$field => $upload()])->assertSessionHasNoErrors()->assertRedirect();
            Storage::disk('public')->assertExists($store->fresh()->{$field . '_path'});
        }
        $this->post(route('dashboard.galeria.store'), ['images' => [$upload(), $upload()]])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(2, $store->galleryItems()->count());

        $galleryItem = $store->galleryItems()->first();
        $this->post(route('dashboard.galeria.toggle-hero', $galleryItem))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('hero', $galleryItem->fresh()->type);
        $this->post(route('dashboard.galeria.toggle-hero', $galleryItem))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('photo', $galleryItem->fresh()->type);

        $category = Category::create(['store_id' => $store->id, 'name' => 'Categoría con foto', 'slug' => 'categoria-foto']);
        $this->put(route('dashboard.categorias.update', $category), ['name' => $category->name, 'image' => $upload()])->assertSessionHasNoErrors()->assertRedirect();
        $path = $category->fresh()->image_path;
        Storage::disk('public')->assertExists($path);
        $this->get(route('dashboard.categorias.edit', $category))->assertOk()->assertSee('Quitar imagen guardada al guardar');
        $this->put(route('dashboard.categorias.update', $category), ['name' => $category->name, 'remove_image' => 1])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertNull($category->fresh()->image_path);
        Storage::disk('public')->assertMissing($path);
    }
}
