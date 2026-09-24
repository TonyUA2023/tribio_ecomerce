<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Urban Style (templates/urban-style): the fashion template with owner-designed hero
 * banners. Covers the field types it introduced to the template module — image uploads,
 * in-store links and dates — plus every storefront page rendering on it.
 */
class UrbanStyleTemplateTest extends TestCase
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

    private function urbanStore(): Store
    {
        $owner = User::factory()->create(['role' => 'store_owner']);

        return Store::create([
            'user_id' => $owner->id, 'name' => 'Moda Lima', 'slug' => 'moda-lima-' . $owner->id,
            'status' => 'active', 'template_name' => 'urban-style',
        ]);
    }

    private function save(Store $store, array $settings, array $extra = [])
    {
        return $this->actingAs($store->user)->put(route('dashboard.plantillas.update'), ['settings' => $settings] + $extra);
    }

    public function test_home_renders_the_default_banner_and_topbars_without_any_setup(): void
    {
        $store = $this->urbanStore();
        Product::create(['store_id' => $store->id, 'name' => 'Blusa Almu Coco', 'slug' => 'blusa-almu', 'price' => 53.94, 'compare_price' => 89.90, 'stock' => 5, 'is_active' => true, 'is_featured' => true]);

        $this->get(route('store.show', $store->slug))->assertOk()
            ->assertSee('--t-primary: #E0157A', false)
            ->assertSee('data-tpl-text="hero.items.0.title"', false)
            ->assertSee('Fashion Days')
            ->assertSee('data-tpl-text="hero.items.0.highlight"', false)
            ->assertSee('Envío gratis por compras mayores a S/149')
            ->assertSee('Blusa Almu Coco')
            ->assertSee('40%', false)
            // Banner 3 is off by default and the pop-up is opt-in.
            ->assertDontSee('data-tpl-text="hero.items.2.title"', false)
            ->assertDontSee('data-tpl-text="popup.code"', false);
    }

    public function test_every_storefront_page_renders_on_urban_style(): void
    {
        $store = $this->urbanStore();
        $product = Product::create(['store_id' => $store->id, 'name' => 'Polo Oversize', 'slug' => 'polo-oversize', 'price' => 49.90, 'stock' => 8, 'is_active' => true]);

        // Home and catalog eager-load categories.products with latest(), which SQLite rejects
        // as ambiguous (MySQL accepts it) — pre-existing, so they are checked before categories exist.
        foreach ([route('store.show', $store->slug), route('store.catalog', $store->slug)] as $url) {
            $this->get($url)->assertOk()->assertSee('class="us-header"', false)->assertSee('Polo Oversize');
        }

        $category = Category::create(['store_id' => $store->id, 'name' => 'Polos', 'slug' => 'polos', 'show_in_header' => true]);
        $product->update(['category_id' => $category->id]);
        foreach ([
            route('store.product', [$store->slug, $product->slug]),
            route('store.contact', $store->slug),
            route('store.gallery', $store->slug),
        ] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('class="us-header"', false)
                ->assertSee('>Polos</a>', false)
                ->assertSee('name="q"', false);
        }
    }

    public function test_owner_uploads_replaces_and_removes_banner_images(): void
    {
        Storage::fake('public');
        $store = $this->urbanStore();

        $this->save($store, ['hero' => ['items' => [
            ['image' => UploadedFile::fake()->image('verano.jpg', 1920, 900), 'title' => 'Verano', 'tone' => 'dark', 'align' => 'center', 'link' => 'new'],
        ]]])->assertSessionHasNoErrors()->assertRedirect(route('dashboard.plantillas.customize'));

        $first = $store->fresh()->template_settings['urban-style']['hero']['items'][0]['image'];
        $this->assertStringStartsWith("stores/{$store->id}/templates/urban-style/", $first);
        Storage::disk('public')->assertExists($first);

        $this->get(route('store.show', $store->slug))->assertOk()
            ->assertSee('storage/' . $first, false)
            ->assertSee('data-tpl-choice="hero.items.0.tone" data-choice="dark"', false)
            ->assertSee(route('store.catalog', ['slug' => $store->slug, 'sort' => 'newest']), false);

        // Saving again without a new file keeps the image.
        $this->save($store, ['hero' => ['items' => [['title' => 'Verano 2']]]])->assertSessionHasNoErrors();
        $this->assertSame($first, $store->fresh()->template_settings['urban-style']['hero']['items'][0]['image']);

        // A new file replaces it and the old one is deleted.
        $this->save($store, ['hero' => ['items' => [['image' => UploadedFile::fake()->image('otono.png', 1920, 900)]]]])->assertSessionHasNoErrors();
        $second = $store->fresh()->template_settings['urban-style']['hero']['items'][0]['image'];
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);

        // Removing clears the setting and the file.
        $this->save($store, [], ['remove_images' => ['hero.items.0.image']])->assertSessionHasNoErrors();
        $this->assertNull(data_get($store->fresh()->template_settings, 'urban-style.hero.items.0.image'));
        Storage::disk('public')->assertMissing($second);
    }

    public function test_reset_deletes_the_templates_uploaded_images(): void
    {
        Storage::fake('public');
        $store = $this->urbanStore();
        $this->save($store, ['popup' => ['enabled' => '1', 'image' => UploadedFile::fake()->image('cupon.jpg', 800, 1000)]])->assertSessionHasNoErrors();
        $path = $store->fresh()->template_settings['urban-style']['popup']['image'];
        Storage::disk('public')->assertExists($path);

        $this->delete(route('dashboard.plantillas.reset'))->assertRedirect(route('dashboard.plantillas.customize'));

        $this->assertNull($store->fresh()->template_settings);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_rejects_non_images_unknown_links_bad_dates_and_foreign_remove_paths(): void
    {
        Storage::fake('public');
        $store = $this->urbanStore();

        $this->save($store, [
            'hero' => ['items' => [['image' => UploadedFile::fake()->create('virus.pdf', 10, 'application/pdf'), 'link' => 'https://evil.test']]],
            'promo' => ['ends_at' => '31/12/2026'],
        ], ['remove_images' => ['../../.env']])->assertSessionHasErrors([
            'settings.hero.items.0.image', 'settings.hero.items.0.link', 'settings.promo.ends_at', 'remove_images.0',
        ]);

        $this->assertNull($store->fresh()->template_settings);
    }

    public function test_optional_banner_copy_can_be_cleared_to_hide_it_and_links_can_target_a_category(): void
    {
        $store = $this->urbanStore();

        $this->save($store, [
            'hero' => ['items' => [['highlight' => '', 'highlight_prefix' => '', 'title' => 'Denim Week', 'link' => 'category:jeans']]],
            'promo' => ['ends_at' => '2030-01-31'],
        ])->assertSessionHasNoErrors();

        $settings = $store->fresh()->template_settings['urban-style'];
        $this->assertSame('', $settings['hero']['items'][0]['highlight'], 'A cleared optional text is kept as "hide it"');
        $this->assertSame('2030-01-31', $settings['promo']['ends_at']);

        $this->get(route('store.show', $store->slug))->assertOk()
            ->assertDontSee('>50%</span>', false)
            ->assertSee('Denim Week')
            ->assertSee(route('store.catalog', ['slug' => $store->slug, 'category' => 'jeans']), false)
            ->assertSee('data-choice="2030-01-31"', false);
    }

    public function test_customizer_and_preview_frame_expose_the_new_field_types(): void
    {
        $store = $this->urbanStore();
        $category = Category::create(['store_id' => $store->id, 'name' => 'Casacas', 'slug' => 'casacas']);
        $this->actingAs($store->user);

        $this->get(route('dashboard.plantillas.customize'))->assertOk()
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('Banners de portada')
            ->assertSee('Banner 2')
            ->assertSee('name="settings[hero][items][0][image]"', false)
            ->assertSee('Categoría · Casacas')
            ->assertSee('type="date"', false);

        $category->delete(); // see the SQLite note in test_every_storefront_page_renders_on_urban_style
        $this->get(route('dashboard.plantillas.frame', 'urban-style'))->assertOk()
            ->assertSee('data-tpl-img="hero.items.0.image"', false)
            ->assertSee('Ver pop-up promocional')
            ->assertSee('data-tpl-text="hero.items.2.title"', false);
    }
}
