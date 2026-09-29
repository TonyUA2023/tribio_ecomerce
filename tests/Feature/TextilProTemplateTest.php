<?php

namespace Tests\Feature;

use App\Models\GalleryItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use App\Services\MadeToOrder\CustomizationSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Textil Pro (templates/textil-pro): template for printing / garment workshops that sell
 * wholesale and retail — quote on WhatsApp, services, process, volume prices, per-metre
 * printing, segments, FAQ and location, built on Sport Pro's one-layout structure.
 */
class TextilProTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function migrateFreshUsing(): array
    {
        if (config('database.default') !== 'sqlite') {
            return [];
        }

        return ['--path' => array_values(array_filter(glob(database_path('migrations/*.php')),
            fn ($path) => !str_ends_with($path, '2026_09_11_182936_update_role_column_in_users_table.php'))), '--realpath' => true];
    }

    private function textileStore(array $attributes = []): Store
    {
        $owner = User::factory()->create(['role' => 'store_owner']);

        return Store::create($attributes + [
            'user_id' => $owner->id, 'name' => 'Estampados Sol', 'slug' => 'estampados-' . $owner->id,
            'status' => 'active', 'template_name' => 'textil-pro', 'category' => 'textileria',
            'whatsapp_phone' => '+51 971 000 111', 'address' => 'Av. Los Olivos 123', 'city' => 'Lima',
            'instagram_url' => 'https://instagram.com/estampadossol',
        ]);
    }

    private function polo(Store $store, array $attributes = []): Product
    {
        $product = Product::create($attributes + [
            'store_id' => $store->id, 'name' => 'Polo algodón estampado', 'slug' => 'polo-estampado', 'price' => 35,
            'stock' => 20, 'track_stock' => true, 'is_active' => true, 'is_featured' => true, 'has_variants' => true,
            'variant_options' => [['name' => 'Talla', 'values' => ['S', 'M', 'L']]],
        ]);
        foreach (['S', 'M', 'L'] as $size) {
            ProductVariant::create([
                'product_id' => $product->id, 'sku' => "POLO-{$size}", 'price' => 35, 'stock' => 5,
                'attributes' => ['Talla' => $size], 'is_active' => true,
            ]);
        }

        return $product;
    }

    public function test_an_empty_catalog_home_still_sells_with_quotes_services_and_location(): void
    {
        $store = $this->textileStore();

        $home = $this->get(route('store.show', $store->slug))->assertOk();
        // Sin precio configurado, la etiqueta de precio de la banda promocional queda oculta.
        $this->assertMatchesRegularExpression('/class="tx-price-tag"\s+hidden/', $home->getContent());
        $home
            ->assertSee('class="tx-header"', false)
            ->assertSee('--t-primary: #FFC400', false)
            ->assertSee('--t-on-secondary: #FFFFFF', false)
            ->assertSee('Tu diseño, estampado')
            ->assertSee('Estampamos todo lo que imaginas')
            ->assertSee('Pedir es así de fácil')
            ->assertSee('12 – 49 unidades')
            ->assertSee('Imprimimos tus diseños por metro')
            ->assertSee('¿Para quién trabajamos?')
            ->assertSee('¿Tienes un diseño en mente?')
            ->assertSee('¿Cuál es el pedido mínimo?')
            ->assertSee('Av. Los Olivos 123')
            ->assertSee('https://wa.me/51971000111?text=' . rawurlencode('Hola, quiero cotizar un pedido de estampado.'), false)
            ->assertSee('#servicios', false)
            ->assertSee('#por-mayor', false)
            ->assertSee('class="tx-wa"', false);
    }

    public function test_every_page_renders_with_products(): void
    {
        $store = $this->textileStore();
        $polo = $this->polo($store);

        $this->get(route('store.show', $store->slug))->assertOk()->assertSee('Polo algodón estampado')->assertDontSee('¿Tienes un diseño en mente?');
        $this->get(route('store.catalog', $store->slug))->assertOk()->assertSee('Polo algodón estampado');
        $this->get(route('store.product', [$store->slug, $polo->slug]))->assertOk()->assertSee('Polo algodón estampado')->assertSee('Talla');
        $this->get(route('store.contact', $store->slug))->assertOk();
        $this->get(route('store.gallery', $store->slug))->assertOk();
    }

    public function test_products_are_shown_right_below_the_hero(): void
    {
        $store = $this->textileStore();
        foreach (range(1, 5) as $i) {
            Product::create([
                'store_id' => $store->id, 'name' => "Polo destacado {$i}", 'slug' => "polo-destacado-{$i}", 'price' => 30,
                'stock' => 10, 'is_active' => true, 'is_featured' => true,
            ]);
        }
        Product::create([
            'store_id' => $store->id, 'name' => 'Casaca en oferta', 'slug' => 'casaca-oferta', 'price' => 80,
            'compare_price' => 120, 'stock' => 10, 'is_active' => true,
        ]);

        $html = $this->get(route('store.show', $store->slug))->assertOk()
            ->assertSee('id="destacados"', false)
            ->assertSee('Lo más pedido')
            ->assertSee('Todo el catálogo')
            ->getContent();

        // Arriba: justo después de la portada y antes de las cifras y los servicios.
        $section = strpos($html, 'id="destacados"');
        $this->assertGreaterThan(strpos($html, 'class="tx-hero'), $section);
        $this->assertLessThan(strpos($html, 'id="servicios"'), $section);
        // El carrusel no se queda en los 3 destacados que trae el controlador.
        $rail = substr($html, $section, strpos($html, 'id="servicios"') - $section);
        foreach (range(1, 5) as $i) {
            $this->assertStringContainsString("Polo destacado {$i}", $rail);
        }
        $this->assertStringNotContainsString('Casaca en oferta', $rail);

        $store->update(['template_settings' => ['textil-pro' => ['showcase' => ['source' => 'sale', 'title' => 'Ofertas del taller']]]]);
        $html = $this->get(route('store.show', $store->slug))->assertOk()->assertSee('Ofertas del taller')->getContent();
        $section = strpos($html, 'id="destacados"');
        $rail = substr($html, $section, strpos($html, 'id="servicios"') - $section);
        $this->assertStringContainsString('Casaca en oferta', $rail);
        $this->assertStringNotContainsString('Polo destacado 1', $rail);

        $store->update(['template_settings' => ['textil-pro' => ['showcase' => ['enabled' => false]]]]);
        $this->get(route('store.show', $store->slug))->assertOk()->assertDontSee('id="destacados"', false);
    }

    public function test_without_products_the_top_section_shows_gallery_work_or_nothing(): void
    {
        $store = $this->textileStore();
        $this->get(route('store.show', $store->slug))->assertOk()->assertDontSee('id="destacados"', false);

        GalleryItem::create([
            'store_id' => $store->id, 'image_path' => 'gallery/polos-promocion.jpg', 'title' => 'Polos para promoción 2026',
            'type' => 'photo', 'is_active' => true, 'sort_order' => 1,
        ]);
        GalleryItem::create([
            'store_id' => $store->id, 'image_path' => 'gallery/oculto.jpg', 'title' => 'Trabajo oculto',
            'type' => 'photo', 'is_active' => false, 'sort_order' => 2,
        ]);

        $this->get(route('store.show', $store->slug))->assertOk()
            ->assertSee('id="destacados"', false)
            ->assertSee('Polos para promoción 2026')
            ->assertSee('storage/gallery/polos-promocion.jpg', false)
            ->assertDontSee('Trabajo oculto');

        $store->update(['template_settings' => ['textil-pro' => ['showcase' => ['use_gallery' => false]]]]);
        $this->get(route('store.show', $store->slug))->assertOk()->assertDontSee('id="destacados"', false);
    }

    public function test_made_to_order_products_show_the_customization_form(): void
    {
        $store = $this->textileStore(['made_to_order_enabled' => true, 'deposit_percent' => 50]);
        $product = Product::create([
            'store_id' => $store->id, 'name' => 'Polo promoción', 'slug' => 'polo-promo', 'price' => 30, 'stock' => 0,
            'track_stock' => false, 'is_active' => true, 'sale_mode' => Product::SALE_MADE_TO_ORDER, 'lead_time_days' => 5,
            'customization_schema' => CustomizationSchema::normalize([
                ['type' => 'text', 'label' => 'Nombre en la espalda', 'required' => true, 'max' => 15],
                ['type' => 'sizes', 'label' => 'Tallas', 'required' => true, 'sizes' => ['S', 'M', 'L']],
            ]),
        ]);

        $this->get(route('store.product', [$store->slug, $product->slug]))->assertOk()
            ->assertSee('Nombre en la espalda')
            ->assertSee('madeToOrderForm', false);
    }

    public function test_customizer_saves_the_workshop_texts_and_the_store_shows_them(): void
    {
        $store = $this->textileStore();
        $this->actingAs($store->user);

        $this->get(route('dashboard.plantillas.customize'))->assertOk()
            ->assertSee('Personalizar · Textil Pro', false)
            ->assertSee('Servicio 6')
            ->assertSee('Rango 4')
            ->assertSee('Mensaje con el que el cliente abre WhatsApp');

        $this->put(route('dashboard.plantillas.update'), ['settings' => [
            'colors' => ['primary' => '#FDD835', 'secondary' => '#27306B'],
            'promo' => ['price' => 'S/25', 'title' => 'Impresión DTF por metro'],
            'stats' => ['items' => [['value' => '9 años', 'label' => 'estampando']]],
            'contact' => ['whatsapp_message' => 'Hola Estampados Sol, quiero una cotización'],
            'faq' => ['enabled' => '0'],
        ]])->assertSessionHasNoErrors();

        $this->get(route('store.show', $store->slug))->assertOk()
            ->assertSee('--t-primary: #FDD835', false)
            ->assertSee('--t-secondary: #27306B', false)
            ->assertSee('S/25')
            ->assertSee('Impresión DTF por metro')
            ->assertSee('9 años')
            ->assertSee(rawurlencode('Hola Estampados Sol, quiero una cotización'), false)
            ->assertDontSee('¿Cuál es el pedido mínimo?');

        $this->get(route('dashboard.plantillas.frame', 'textil-pro'))->assertOk()
            ->assertSee('data-tpl-img="hero.image"', false)
            ->assertSee('data-tpl-show="faq.enabled"', false)
            ->assertSee('data-tpl-show="showcase.enabled"', false)
            ->assertSee('Aquí aparecerán tus productos destacados');
    }

    public function test_it_is_the_first_template_recommended_to_textile_stores(): void
    {
        $store = $this->textileStore(['template_name' => 'soft-market']);

        $this->actingAs($store->user)->get(route('dashboard.plantillas.index'))->assertOk()
            ->assertSee('Textil Pro')
            ->assertViewHas('available', fn ($available) => array_key_first($available) === 'textil-pro');
    }
}
