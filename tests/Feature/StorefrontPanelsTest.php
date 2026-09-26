<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The cart drawer and the Tribio Pass modal must be hidden by the HTML itself, before
 * Alpine (a deferred CDN script) runs. Each root once carried two style="" attributes;
 * browsers keep only the first, so display:none was dropped and both panels flashed
 * open and empty on every storefront load.
 */
class StorefrontPanelsTest extends TestCase
{
    use RefreshDatabase;

    protected function migrateFreshUsing(): array
    {
        return ['--path' => array_values(array_filter(glob(database_path('migrations/*.php')),
            fn ($path) => !str_ends_with($path, '2026_09_11_182936_update_role_column_in_users_table.php'))), '--realpath' => true];
    }

    public function test_checkout_panels_are_hidden_before_alpine_loads_on_every_template(): void
    {
        $owner = User::factory()->create(['role' => 'store_owner']);

        foreach (array_keys(config('storefront.templates')) as $template) {
            $store = Store::create([
                'user_id' => $owner->id, 'name' => "Tienda {$template}", 'slug' => "tienda-{$template}",
                'status' => 'active', 'template_name' => $template,
            ]);
            $html = $this->get(route('store.show', $store->slug))->assertOk()->getContent();

            foreach (['cartDrawer' => 'z-[999]', 'tribioCustomerModal' => 'z-[1000]'] as $id => $rootClass) {
                $start = strpos($html, "id=\"{$id}\"");
                $this->assertNotFalse($start, "{$template}: #{$id} is rendered");
                // Attributes of the root, from its id up to its own class list.
                $attributes = substr($html, $start, strpos($html, $rootClass, $start) - $start);
                preg_match_all('/\sstyle="([^"]*)"/', $attributes, $styles);
                $this->assertCount(1, $styles[1], "{$template}: #{$id} must have exactly one style attribute");
                $this->assertStringStartsWith('display: none;', $styles[1][0], "{$template}: #{$id} starts hidden");
            }
        }
    }
}
