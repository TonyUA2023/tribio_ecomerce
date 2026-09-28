<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use App\Services\Storefront\CatalogFacets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sport Pro (templates/sport-pro): sneaker/sportswear template. Also covers what it added to
 * the shared storefront: the ?attr[Talla]=40 variant filter, the fixed "in stock" filter and
 * the "sale" link token.
 */
class SportProTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function migrateFreshUsing(): array
    {
        if (config('database.default') !== 'sqlite') {
            return [];
        }
        // Same exclusion as StorefrontTemplatesTest: the legacy role migration is MySQL-only.
        return ['--path' => array_values(array_filter(glob(database_path('migrations/*.php')),
            fn($path) => !str_ends_with($path, '2026_09_11_182936_update_role_column_in_users_table.php'))), '--realpath' => true];
    }

    private function sportStore(): Store
    {
        $owner = User::factory()->create(['role' => 'store_owner']);

        return Store::create([
            'user_id' => $owner->id, 'name' => 'Kicks Lima', 'slug' => 'kicks-' . $owner->id,
            'status' => 'active', 'template_name' => 'sport-pro',
        ]);
    }

    /** A sneaker with Color × Talla variants; 40/Negro is sold out. */
    private function sneaker(Store $store, array $attributes = []): Product
    {
        $product = Product::create($attributes + [
            'store_id' => $store->id, 'name' => 'Zapatillas Runner', 'slug' => 'runner', 'price' => 299, 'compare_price' => 399,
            'stock' => 0, 'track_stock' => true, 'is_active' => true, 'is_featured' => true, 'has_variants' => true,
            'variant_options' => [['name' => 'Color', 'values' => ['Negro', 'Blanco']], ['name' => 'Talla', 'values' => ['39', '40', '41']]],
        ]);
        foreach (['Negro', 'Blanco'] as $color) {
            foreach (['39', '40', '41'] as $size) {
                ProductVariant::create([
                    'product_id' => $product->id, 'sku' => "RUN-{$color}-{$size}", 'price' => 299,
                    'stock' => $color === 'Negro' && $size === '40' ? 0 : 5,
                    'attributes' => ['Color' => $color, 'Talla' => $size], 'is_active' => true,
                ]);
            }
        }

        return $product;
    }

    public function test_home_renders_with_defaults_and_the_sale_menu_entry(): void
    {
        $store = $this->sportStore();
        $this->sneaker($store);

        $this->get(route('store.show', $store->slug))->assertOk()
            ->assertSee('class="sp-header"', false)
            ->assertSee('--t-primary: #D0021B', false)
            ->assertSee('Hechas para moverte')
            ->assertSee('Envío en 48 horas para Lima y Callao*')
            ->assertSee(route('store.catalog', ['slug' => $store->slug, 'on_sale' => 1]), false)
            ->assertSee('Zapatillas Runner')
            ->assertSee('2 colores')
            ->assertSee('-25%')
            ->assertSee('Regístrate y compra más rápido')
            // Banners 3 y 4 vienen apagados.
            ->assertDontSee('data-tpl-text="hero.items.2.title"', false);
    }

    public function test_every_page_renders_and_the_product_page_offers_colors_and_sizes(): void
    {
        $store = $this->sportStore();
        $product = $this->sneaker($store);

        foreach ([route('store.catalog', $store->slug), route('store.contact', $store->slug), route('store.gallery', $store->slug)] as $url) {
            $this->get($url)->assertOk()->assertSee('class="sp-header"', false)->assertSee('sp-footer', false);
        }

        $this->get(route('store.product', [$store->slug, $product->slug]))->assertOk()
            ->assertSee('spProduct(', false)
            ->assertSee('sizeNames', false)
            ->assertSee('RUN-Negro-40')
            ->assertSee('Selecciona tu talla')
            ->assertSee('Envíos, cambios y devoluciones');
    }

    public function test_catalog_filters_by_variant_size_and_color_and_lists_the_facets(): void
    {
        $store = $this->sportStore();
        $this->sneaker($store);
        $this->sneaker($store, ['name' => 'Zapatillas Court', 'slug' => 'court', 'variant_options' => [['name' => 'Talla', 'values' => ['42']]]])
            ->variants()->delete();
        Product::create(['store_id' => $store->id, 'name' => 'Medias Pack', 'slug' => 'medias', 'price' => 29, 'stock' => 10, 'is_active' => true]);

        $this->assertSame(['Talla' => ['39', '40', '41', '42'], 'Color' => ['Blanco', 'Negro']], CatalogFacets::forStore($store));

        $this->get(route('store.catalog', ['slug' => $store->slug, 'attr' => ['Talla' => '40']]))->assertOk()
            ->assertSee('Zapatillas Runner')->assertDontSee('Medias Pack')->assertDontSee('Zapatillas Court')
            ->assertSee('Talla: <strong>40</strong>', false);

        $this->get(route('store.catalog', ['slug' => $store->slug, 'attr' => ['Color' => 'Rojo']]))->assertOk()
            ->assertDontSee('Zapatillas Runner')->assertSee('No encontramos productos');

        // Nombres de atributo raros se ignoran en vez de romper la consulta.
        $this->get(route('store.catalog', ['slug' => $store->slug, 'attr' => ["Talla') or 1=1 --" => '40']]))->assertOk()
            ->assertSee('Medias Pack');
    }

    public function test_in_stock_and_on_sale_filters_work(): void
    {
        $store = $this->sportStore();
        Product::create(['store_id' => $store->id, 'name' => 'Gorra Agotada', 'slug' => 'gorra', 'price' => 49, 'stock' => 0, 'track_stock' => true, 'is_active' => true]);
        Product::create(['store_id' => $store->id, 'name' => 'Short Oferta', 'slug' => 'short', 'price' => 59, 'compare_price' => 89, 'stock' => 3, 'track_stock' => true, 'is_active' => true]);

        $this->get(route('store.catalog', ['slug' => $store->slug, 'in_stock' => 1]))->assertOk()
            ->assertSee('Short Oferta')->assertDontSee('Gorra Agotada');
        $this->get(route('store.catalog', ['slug' => $store->slug, 'on_sale' => 1]))->assertOk()
            ->assertSee('Short Oferta')->assertDontSee('Gorra Agotada');
    }

    public function test_complaints_book_and_newsletter_really_reach_the_store(): void
    {
        $store = $this->sportStore();

        $this->get(route('store.contact', ['slug' => $store->slug, 'libro' => 1]))->assertOk()
            ->assertSee('Libro de Reclamaciones')
            ->assertSee('INDECOPI');

        $this->post(route('store.contact.submit', $store->slug), [
            'name' => 'Ana Pérez', 'email' => 'ana@example.com', 'phone' => '999888777',
            'subject' => 'Libro de Reclamaciones — Reclamo', 'message' => "LIBRO DE RECLAMACIONES — RECLAMO\nDocumento: DNI 12345678",
        ])->assertRedirect();

        $this->postJson(route('store.contact.submit', $store->slug), [
            'name' => 'fan', 'email' => 'fan@example.com', 'subject' => 'Suscripción a novedades', 'message' => 'Quiero recibir novedades',
        ])->assertStatus(302);

        $this->assertSame(
            ['Libro de Reclamaciones — Reclamo', 'Suscripción a novedades'],
            $store->contactMessages()->orderBy('id')->pluck('subject')->all()
        );
    }

    public function test_customizer_offers_the_template_settings_and_the_sale_link(): void
    {
        $store = $this->sportStore();
        $this->actingAs($store->user);

        $this->get(route('dashboard.plantillas.customize'))->assertOk()
            ->assertSee('Personalizar · Sport Pro', false)
            ->assertSee('Banner 4')
            ->assertSee('Campaña 2')
            ->assertSee('Ofertas (productos con descuento)')
            ->assertSee('Guía de tallas (opcional)');

        $this->put(route('dashboard.plantillas.update'), ['settings' => [
            'menu' => ['sale_label' => 'OUTLET', 'sale_link' => 'sale'],
            'product' => ['size_guide' => "39 = 25 cm\n40 = 25.5 cm"],
            'style' => ['header' => 'light', 'corners' => 'soft'],
        ]])->assertSessionHasNoErrors();

        $product = $this->sneaker($store);
        $this->get(route('store.product', [$store->slug, $product->slug]))->assertOk()
            ->assertSee('OUTLET')
            ->assertSee('39 = 25 cm')
            ->assertSee('data-tpl-choice="style.header" data-choice="light"', false)
            ->assertSee('data-tpl-choice="style.corners" data-choice="soft"', false);

        $this->get(route('dashboard.plantillas.frame', 'sport-pro'))->assertOk()
            ->assertSee('data-tpl-img="hero.items.3.image"', false)
            ->assertSee('data-src="sale"', false);
    }
}
