<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Sitemaps, robots.txt and the SEO / link-preview tags of the storefronts. */
class SeoFoundationTest extends TestCase
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

    private function store(array $attributes = []): Store
    {
        $owner = User::factory()->create(['role' => 'store_owner']);

        return Store::create($attributes + [
            'user_id' => $owner->id, 'name' => 'Inti Boutique', 'slug' => 'inti-' . $owner->id, 'status' => 'active',
            'template_name' => 'soft-market', 'tagline' => 'Moda con sol de los Andes',
        ]);
    }

    private function product(Store $store, array $attributes = []): Product
    {
        return Product::create($attributes + ['store_id' => $store->id, 'name' => 'Poncho andino', 'slug' => 'poncho-andino', 'price' => 120, 'is_active' => true, 'stock' => 5,
            'short_description' => 'Poncho tejido a mano en alpaca.']);
    }

    public function test_robots_blocks_private_areas_and_points_to_the_sitemap_of_each_host(): void
    {
        $this->get('/robots.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Disallow: /dashboard', false)->assertSee('Disallow: /api/', false)
            ->assertSee('Sitemap: http://localhost/sitemap.xml', false);

        $this->store(['custom_domain' => 'intiboutique.test']);
        $this->get('http://intiboutique.test/robots.txt')->assertOk()->assertSee('Sitemap: http://intiboutique.test/sitemap.xml', false);
    }

    public function test_platform_index_lists_only_active_stores_without_their_own_domain(): void
    {
        $plain = $this->store();
        $custom = $this->store(['custom_domain' => 'otra.test']);
        $draft = $this->store(['status' => 'draft']);

        $res = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $res->assertSee(route('store.sitemap', $plain->slug), false)
            ->assertSee('/sitemap-pages.xml', false)
            ->assertDontSee($custom->slug, false)->assertDontSee($draft->slug, false);
        $this->get('/sitemap-pages.xml')->assertOk()->assertSee('/negocios', false);
    }

    public function test_store_sitemap_lists_real_pages_and_products_only(): void
    {
        $store = $this->store();
        $this->product($store);
        $this->product($store, ['name' => 'Oculto', 'slug' => 'oculto', 'is_active' => false]);
        $this->product($store, ['name' => 'Sin precio', 'slug' => 'sin-precio', 'price' => 0]);

        $this->get(route('store.sitemap', $store->slug))->assertOk()
            ->assertSee("/tienda/{$store->slug}</loc>", false)
            ->assertSee("/tienda/{$store->slug}/catalogo</loc>", false)
            ->assertSee("/tienda/{$store->slug}/producto/poncho-andino</loc>", false)
            ->assertDontSee('oculto', false)->assertDontSee('sin-precio', false);
    }

    public function test_a_custom_domain_serves_its_own_sitemap_with_its_own_addresses(): void
    {
        $store = $this->store(['custom_domain' => 'intiboutique.test']);
        $this->product($store);

        $this->get('http://intiboutique.test/sitemap.xml')->assertOk()
            ->assertSee('http://intiboutique.test/producto/poncho-andino</loc>', false)
            ->assertSee('http://intiboutique.test</loc>', false)
            ->assertDontSee('/tienda/', false);
    }

    public function test_home_and_product_pages_carry_title_description_canonical_and_share_image(): void
    {
        $store = $this->store(['meta_title' => 'Inti Boutique | Ropa de alpaca en Cusco', 'meta_description' => 'Ponchos y chompas de alpaca hechos a mano.', 'logo_path' => 'logos/inti.png']);
        $product = $this->product($store, ['image_path' => 'products/poncho.jpg']);

        $this->get(route('store.show', $store->slug))->assertOk()
            ->assertSee('<title>Inti Boutique | Ropa de alpaca en Cusco</title>', false)
            ->assertSee('<meta name="description" content="Ponchos y chompas de alpaca hechos a mano.">', false)
            ->assertSee('<link rel="canonical" href="' . route('store.show', $store->slug) . '">', false)
            ->assertSee('<meta property="og:type" content="website">', false)
            ->assertSee('<meta property="og:image" content="' . $store->logo_url . '">', false);

        $this->get(route('store.product', [$store->slug, $product->slug]) . '?sort=price&utm_source=wa')->assertOk()
            ->assertSee('<meta property="og:type" content="product">', false)
            ->assertSee('<meta name="description" content="Poncho tejido a mano en alpaca.">', false)
            ->assertSee('<meta property="og:image" content="' . $product->image_url . '">', false)
            ->assertSee('<meta property="product:price:amount" content="120.00">', false)
            // Tracking and sorting parameters never create a second address.
            ->assertSee('<link rel="canonical" href="' . route('store.product', [$store->slug, $product->slug]) . '">', false);
    }

    public function test_the_canonical_points_to_the_custom_domain_even_when_opened_from_the_platform(): void
    {
        $store = $this->store(['custom_domain' => 'intiboutique.test']);
        $product = $this->product($store);

        $this->get(route('store.product', [$store->slug, $product->slug]))->assertOk()
            ->assertSee('<link rel="canonical" href="http://intiboutique.test/producto/poncho-andino">', false);
        $this->get('http://intiboutique.test/producto/poncho-andino')->assertOk()
            ->assertSee('<link rel="canonical" href="http://intiboutique.test/producto/poncho-andino">', false);
    }

    public function test_the_dashboard_offers_the_share_kit_and_per_product_whatsapp_links(): void
    {
        $store = $this->store(['name' => 'Inti Boutique']);
        $product = $this->product($store);
        $this->actingAs($store->user);

        $this->get(route('dashboard.index'))->assertOk()
            ->assertSee('Comparte tu tienda y empieza a vender')
            ->assertSee('https://wa.me/?text=', false)
            ->assertSee(route('store.show', $store->slug) . '/sitemap.xml', false)
            ->assertSee('Solicitar indexación');

        $this->get(route('dashboard.productos.index'))->assertOk()
            ->assertSee('https://wa.me/?text=', false)
            ->assertSee(rawurlencode(route('store.product', [$store->slug, $product->slug])), false);
    }
}
