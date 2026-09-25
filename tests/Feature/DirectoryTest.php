<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\Directory\DirectoryCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * /negocios: every active store's products in one search, a fair daily showcase, and a
 * live preview that doesn't inflate the store's visit counter.
 */
class DirectoryTest extends TestCase
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
        config(['app.url' => 'http://localhost']);
    }

    private function store(string $slug, array $attributes = []): Store
    {
        return Store::create($attributes + [
            'user_id' => User::factory()->create()->id, 'name' => ucwords(str_replace('-', ' ', $slug)), 'slug' => $slug,
            'status' => 'active', 'template_name' => 'soft-market', 'category' => 'alimentos',
        ]);
    }

    private function product(Store $store, string $name, array $attributes = []): Product
    {
        return Product::create($attributes + [
            'store_id' => $store->id, 'name' => $name, 'slug' => \Illuminate\Support\Str::slug($name) . '-' . $store->id,
            'price' => 30, 'stock' => 5, 'track_stock' => true, 'is_active' => true, 'image_path' => 'products/' . \Illuminate\Support\Str::slug($name) . '.jpg',
        ]);
    }

    private function search(array $params)
    {
        return $this->getJson(route('directory.search', $params));
    }

    public function test_the_page_shows_every_active_store_and_its_products_but_nothing_inactive(): void
    {
        $cakes = $this->store('sandras-cakes', ['tagline' => 'Tortas por encargo en Huancayo']);
        $this->product($cakes, 'Torta de chocolate');
        $this->product($cakes, 'Receta secreta', ['is_active' => false]);
        $closed = $this->store('tienda-cerrada', ['status' => 'inactive']);
        $this->product($closed, 'Torta prohibida');

        $this->get(route('directory'))->assertOk()
            ->assertSee('Sandras Cakes')->assertSee('Torta de chocolate')->assertSee('Tortas por encargo en Huancayo')
            ->assertSee('Vista previa en vivo')
            ->assertDontSee('Receta secreta')->assertDontSee('Tienda Cerrada')->assertDontSee('Torta prohibida');
    }

    public function test_search_finds_products_across_stores_ignoring_accents_case_and_plurals(): void
    {
        $cakes = $this->store('sandras-cakes');
        $parts = $this->store('tfl-parts', ['category' => 'otros']);
        $this->product($cakes, 'Torta de Chocolate', ['compare_price' => 40]);
        $this->product($cakes, 'Galletas decoradas');
        $this->product($parts, 'Bomba hidraulica', ['tags' => 'repuesto tractor']);

        $response = $this->search(['q' => 'TORTAS chocolate'])->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('products.0.name', 'Torta de Chocolate')
            ->assertJsonPath('products.0.discount_percent', 25)
            ->assertJsonPath('products.0.store.name', 'Sandras Cakes');
        $this->assertSame(['torta', 'chocolate'], $response->json('terms'));
        $this->assertStringContainsString('/tienda/sandras-cakes/producto/torta-de-chocolate-' . $cakes->id, $response->json('products.0.url'));
        $this->assertStringContainsString('utm_source=tribio&utm_medium=directorio&utm_campaign=busqueda', $response->json('products.0.url'));

        $this->search(['q' => 'repuestos'])->assertJsonPath('products.0.name', 'Bomba hidraulica');
        $this->search(['q' => 'tfl'])->assertJsonPath('total', 1)->assertJsonPath('stores.0.name', 'Tfl Parts');
        $this->search(['q' => 'torta galleta'])->assertJsonPath('total', 0);
    }

    public function test_name_matches_rank_above_description_matches_and_sorting_works(): void
    {
        $store = $this->store('dulce-hogar');
        $this->product($store, 'Caja de regalo', ['description' => 'Incluye una mini torta', 'price' => 80]);
        $this->product($store, 'Torta tres leches', ['price' => 45]);
        $this->product($store, 'Torta helada', ['price' => 25]);

        $names = array_column($this->search(['q' => 'torta'])->json('products'), 'name');
        $this->assertEqualsCanonicalizing(['Torta tres leches', 'Torta helada'], array_slice($names, 0, 2));
        $this->assertSame('Caja de regalo', $names[2], 'A description-only match ranks below name matches');
        $this->assertSame(['Torta helada', 'Torta tres leches', 'Caja de regalo'], array_column($this->search(['q' => 'torta', 'orden' => 'precio_asc'])->json('products'), 'name'));
        $this->assertSame(['Caja de regalo', 'Torta tres leches', 'Torta helada'], array_column($this->search(['q' => 'torta', 'orden' => 'precio_desc'])->json('products'), 'name'));
    }

    public function test_wildcards_and_junk_cannot_match_everything_and_bad_filters_are_rejected(): void
    {
        $store = $this->store('dulce-hogar');
        $this->product($store, 'Torta helada');

        $this->search(['q' => '%'])->assertOk()->assertJsonPath('total', 0);
        $this->search(['q' => '_'])->assertOk()->assertJsonPath('total', 0);
        $this->search(['q' => str_repeat('a', 81)])->assertStatus(422)->assertJsonValidationErrors('q');
        $this->search(['categoria' => 'armas'])->assertStatus(422)->assertJsonValidationErrors('categoria');
        $this->search(['orden' => 'random'])->assertStatus(422)->assertJsonValidationErrors('orden');

        // The page itself ignores a bad filter in a shared link instead of erroring.
        $this->get(route('directory', ['categoria' => 'armas', 'orden' => 'random']))->assertOk();
    }

    public function test_browsing_a_category_or_all_deals_needs_no_words(): void
    {
        $cakes = $this->store('sandras-cakes');
        $tech = $this->store('istack', ['category' => 'tecnologia']);
        $this->product($cakes, 'Torta helada', ['compare_price' => 60]);
        $this->product($tech, 'Cargador rapido');
        $this->product($tech, 'Audifonos', ['price' => 40, 'compare_price' => 100]);

        $this->search(['categoria' => 'tecnologia'])->assertJsonPath('total', 2)->assertJsonPath('stores.0.name', 'Istack');
        $this->assertSame(['Audifonos', 'Torta helada'], array_column($this->search(['orden' => 'ofertas'])->json('products'), 'name'));
        $this->search([])->assertJsonPath('total', 0);
    }

    public function test_the_showcase_gives_every_store_a_turn_before_anyone_gets_two(): void
    {
        $big = $this->store('tienda-grande');
        foreach (range(1, 10) as $i) {
            $this->product($big, "Producto grande {$i}");
        }
        $small = $this->store('tienda-chica');
        $this->product($small, 'Unico producto');
        $third = $this->store('tercera');
        $this->product($third, 'Otro producto');
        $this->product($third, 'Sin foto', ['image_path' => null]);

        $catalog = app(DirectoryCatalog::class);
        $showcase = $catalog->showcase($catalog->stores(), 3);

        $this->assertEqualsCanonicalizing([$big->id, $small->id, $third->id], $showcase->pluck('store_id')->all());
        $this->assertFalse($catalog->showcase($catalog->stores(), 12)->contains('name', 'Sin foto'), 'The showcase only shows products with a photo');
    }

    public function test_links_respect_custom_domains_and_the_live_preview_does_not_count_as_a_visit(): void
    {
        $store = $this->store('mi-marca', ['custom_domain' => 'mimarca.pe']);
        $this->product($store, 'Polo negro');

        $url = $this->search(['q' => 'polo'])->json('products.0.url');
        $this->assertMatchesRegularExpression('#^http://mimarca\.pe(:\d+)?/producto/polo-negro-' . $store->id . '\?utm_source=tribio#', $url);

        $previewUrl = $this->search(['q' => 'mi marca'])->json('stores.0.preview_url');
        $this->assertSame(route('store.show', 'mi-marca') . '?vitrina=1', $previewUrl);
        $this->get($previewUrl)->assertOk();
        $this->assertSame(0, (int) $store->fresh()->total_views);
        $this->get(route('store.show', 'mi-marca'))->assertOk();
        $this->assertSame(1, (int) $store->fresh()->total_views);
    }

    public function test_the_old_search_page_redirects_into_the_directory(): void
    {
        $this->get('/buscar?q=tortas&categoria=alimentos')
            ->assertStatus(301)->assertRedirect(route('directory', ['q' => 'tortas', 'categoria' => 'alimentos']));
    }

    public function test_a_search_link_renders_its_results_for_the_page_to_start_with(): void
    {
        $store = $this->store('sandras-cakes');
        $this->product($store, 'Torta helada');

        $this->get(route('directory', ['q' => 'torta']))->assertOk()
            ->assertViewHas('results', fn ($results) => $results['total'] === 1 && $results['products'][0]['name'] === 'Torta helada')
            ->assertSee('noindex', false);
    }
}
