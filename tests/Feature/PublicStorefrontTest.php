<?php

namespace Tests\Feature;

use App\Models\GalleryItem;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicStorefrontTest extends TestCase
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

    /**
     * The minimal-light footer only links to the photo gallery once a store has at
     * least one gallery item — that branch called route('gallery', ...) instead of
     * the actually-registered store.gallery name, so it 500'd in production the
     * moment a real store finally had a gallery photo, with nothing catching it
     * until then. Guards against that class of "only breaks once data exists" bug.
     */
    public function test_minimal_light_storefront_renders_with_a_gallery_item(): void
    {
        $owner = User::factory()->create(['role' => 'store_owner']);
        $store = Store::create([
            'user_id' => $owner->id, 'name' => 'Galeria Store', 'slug' => 'galeria-store',
            'status' => 'active', 'template_name' => 'minimal-light',
        ]);
        GalleryItem::create(['store_id' => $store->id, 'image_path' => 'gallery/photo.jpg']);

        $this->get(route('store.show', $store->slug))
            ->assertOk()
            ->assertSee(route('store.gallery', $store->slug), false);
    }

    /**
     * The hero carousel used to be 3 hardcoded slots (Portada + 2 baked-in stock
     * photos). It now pulls whichever Galería photos are tagged type=hero, in
     * sort order, and only falls back to the stock photos when none are tagged —
     * this is the non-regression case (a store that hasn't tagged anything yet
     * must look exactly like it always has).
     */
    public function test_hero_falls_back_to_stock_photos_when_nothing_is_tagged(): void
    {
        $owner = User::factory()->create(['role' => 'store_owner']);
        $store = Store::create([
            'user_id' => $owner->id, 'name' => 'Sin Hero Tienda', 'slug' => 'sin-hero-tienda',
            'status' => 'active', 'template_name' => 'minimal-light',
        ]);
        GalleryItem::create(['store_id' => $store->id, 'image_path' => 'gallery/untagged.jpg', 'type' => 'photo']);

        $this->get(route('store.show', $store->slug))
            ->assertOk()
            ->assertSee('images.unsplash.com', false)
            ->assertDontSee(asset('storage/gallery/untagged.jpg'), false);
    }

    public function test_hero_uses_gallery_photos_tagged_as_hero_instead_of_stock_photos(): void
    {
        $owner = User::factory()->create(['role' => 'store_owner']);
        $store = Store::create([
            'user_id' => $owner->id, 'name' => 'Hero Tienda', 'slug' => 'hero-tienda',
            'status' => 'active', 'template_name' => 'minimal-light',
        ]);
        GalleryItem::create(['store_id' => $store->id, 'image_path' => 'gallery/not-hero.jpg', 'type' => 'photo', 'sort_order' => 0]);
        GalleryItem::create(['store_id' => $store->id, 'image_path' => 'gallery/hero-one.jpg', 'type' => 'hero', 'sort_order' => 1]);
        GalleryItem::create(['store_id' => $store->id, 'image_path' => 'gallery/hero-two.jpg', 'type' => 'hero', 'sort_order' => 2]);

        $response = $this->get(route('store.show', $store->slug))->assertOk();
        $response->assertSee(asset('storage/gallery/hero-one.jpg'), false);
        $response->assertSee(asset('storage/gallery/hero-two.jpg'), false);
        $response->assertDontSee(asset('storage/gallery/not-hero.jpg'), false);
        $response->assertDontSee('images.unsplash.com', false);
    }
}
