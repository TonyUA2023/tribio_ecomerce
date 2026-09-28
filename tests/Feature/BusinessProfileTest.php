<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use App\Support\BusinessProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rubros: Textilería, Tienda de ropa and Tienda de zapatillas are separate segments,
 * each one able to tune the platform (made-to-order defaults, sizes, variant options,
 * recommended templates) from config('tribio.business_categories').
 */
class BusinessProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function migrateFreshUsing(): array
    {
        return ['--path' => array_values(array_filter(glob(database_path('migrations/*.php')),
            fn ($path) => !str_ends_with($path, '2026_09_11_182936_update_role_column_in_users_table.php'))), '--realpath' => true];
    }

    private function signup(string $category, string $slug)
    {
        return $this->post(route('plan.checkout'), [
            'plan_key' => 'basic', 'store_name' => 'Mi negocio', 'store_category' => $category, 'store_slug' => $slug,
            'name' => 'Ana Pérez', 'email' => "{$slug}@example.test", 'password' => 'secreto123', 'password_confirmation' => 'secreto123',
            'phone' => '987654321',
        ]);
    }

    private function ownerWithStore(string $category, array $attributes = []): array
    {
        $owner = User::factory()->create(['role' => 'store_owner']);
        $store = Store::create($attributes + [
            'user_id' => $owner->id, 'name' => 'Tienda ' . $category, 'slug' => 'tienda-' . $category,
            'status' => 'active', 'template_name' => 'soft-market', 'category' => $category,
        ]);

        return [$owner, $store];
    }

    public function test_the_three_fashion_rubros_exist_with_their_own_labels(): void
    {
        $this->assertSame(['textileria', 'moda', 'calzado'], array_slice(BusinessProfile::keys(), 0, 3));
        $this->assertSame('Textilería y confección', BusinessProfile::for('textileria')->label());
        $this->assertSame('Tienda de ropa', BusinessProfile::for('moda')->label());
        $this->assertSame('Tienda de zapatillas', BusinessProfile::for('calzado')->label());
        $this->assertSame('otros', BusinessProfile::for('no-existe')->key, 'Unknown rubros fall back to "Otros"');
    }

    public function test_a_new_textile_store_starts_selling_made_to_order_with_a_deposit(): void
    {
        $this->signup('textileria', 'bordados-sol')->assertRedirect();
        $textile = Store::where('slug', 'bordados-sol')->sole();
        $this->assertTrue($textile->made_to_order_enabled);
        $this->assertSame(50, $textile->deposit_percent);

        $this->post(route('logout'));
        $this->signup('moda', 'boutique-luna')->assertRedirect();
        $clothes = Store::where('slug', 'boutique-luna')->sole();
        $this->assertFalse((bool) $clothes->made_to_order_enabled, 'Other rubros keep the regular defaults');
    }

    public function test_unknown_rubros_are_rejected_at_signup_and_in_settings(): void
    {
        $this->signup('armas', 'mala-tienda')->assertSessionHasErrors('store_category');
        $this->assertSame(0, Store::where('slug', 'mala-tienda')->count());

        [$owner, $store] = $this->ownerWithStore('moda');
        $this->actingAs($owner)->post(route('dashboard.store.update'), ['name' => 'Tienda', 'category' => 'armas', 'build_mode' => 'builder'])
            ->assertSessionHasErrors('category');
        $this->post(route('dashboard.store.update'), ['name' => 'Tienda', 'category' => 'textileria', 'build_mode' => 'builder'])
            ->assertSessionHasNoErrors();
        $this->assertSame('textileria', $store->fresh()->category);
        $this->assertFalse((bool) $store->fresh()->made_to_order_enabled, 'Changing rubro later never flips a setting by itself');
    }

    public function test_settings_list_every_rubro_and_recommend_made_to_order_to_textile_stores(): void
    {
        [$owner] = $this->ownerWithStore('textileria');

        $this->actingAs($owner)->get(route('dashboard.store.edit'))->assertOk()
            ->assertSee('🧵 Textilería y confección')->assertSee('👗 Tienda de ropa')->assertSee('👟 Tienda de zapatillas')
            ->assertSee('Recomendado para Textilería y confección');

        [$shoeOwner] = $this->ownerWithStore('calzado');
        $this->actingAs($shoeOwner)->get(route('dashboard.store.edit'))->assertOk()->assertDontSee('Recomendado para');
    }

    public function test_product_forms_suggest_the_sizes_of_the_rubro(): void
    {
        [$shoeOwner] = $this->ownerWithStore('calzado');
        $this->actingAs($shoeOwner)->get(route('dashboard.productos.create'))->assertOk()
            ->assertSee('38, 39, 40, 41, 42, 43');

        [$textileOwner] = $this->ownerWithStore('textileria', ['made_to_order_enabled' => true]);
        $this->actingAs($textileOwner)->get(route('dashboard.productos.create'))->assertOk()
            ->assertSee('S, M, L, XL')
            ->assertViewHas('store', fn ($store) => BusinessProfile::forStore($store)->sizes() === ['S', 'M', 'L', 'XL', 'XXL']);
    }

    public function test_the_template_catalog_puts_the_rubro_templates_first(): void
    {
        [$shoeOwner] = $this->ownerWithStore('calzado');

        $this->actingAs($shoeOwner)->get(route('dashboard.plantillas.index'))->assertOk()
            ->assertSee('Recomendada para Tienda de zapatillas')
            ->assertViewHas('available', fn ($available) => array_key_first($available) === 'sport-pro');
    }

    public function test_the_directory_finds_stores_by_their_rubro_name(): void
    {
        $this->ownerWithStore('textileria');

        $this->getJson(route('directory.search', ['q' => 'textil']))->assertOk()
            ->assertJsonPath('stores.0.category_label', 'Textilería y confección');
    }
}
