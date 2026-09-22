<?php

namespace Tests\Feature;

use App\Builder\Blocks\HeroBlock;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LogoPaletteTest extends TestCase
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

    public function test_uploading_logo_prepares_a_readable_hero_palette_without_rewriting_custom_colors(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('La extracción de color necesita GD.');
        }

        Storage::fake('public');
        $owner = User::factory()->create(['role' => 'store_owner']);
        $store = Store::create(['user_id' => $owner->id, 'name' => 'Azul', 'slug' => 'azul', 'status' => 'active']);
        $hero = $store->sections()->create(['type' => 'hero', 'order' => 0, 'data' => (new HeroBlock())->getDefaultData()]);

        $this->actingAs($owner)->post(route('dashboard.store.logo'), ['logo' => $this->logo(0, 120, 210)])
            ->assertRedirect()->assertSessionHasNoErrors();

        $store->refresh();
        $palette = $store->logo_palette;
        $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $palette['primary']);
        $this->assertSame($palette['primary'], $store->accent_color);
        $this->assertSame($palette['secondary'], $store->secondary_color);
        $this->assertSame($palette['background'], $hero->fresh()->data['background_color']);
        $this->assertSame($palette['primary'], $hero->fresh()->data['blocks'][2]['background_color']);
        $this->assertSame('auto', $hero->fresh()->data['palette_mode']);

        $this->get(route('dashboard.store.builder'))->assertOk()->assertSee('Aplicar colores del logo al hero');
        $this->get(route('dashboard.store.edit'))->assertOk()->assertSee('Colores detectados en tu logo');

        $this->post(route('dashboard.store.logo'), ['logo' => $this->logo(20, 155, 85)])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($store->fresh()->logo_palette['background'], $hero->fresh()->data['background_color']);
        $this->assertSame($store->fresh()->logo_palette['primary'], $hero->fresh()->data['blocks'][2]['background_color']);

        $custom = $hero->fresh()->data;
        $custom['background_color'] = '#FFF0D0';
        $custom['palette_mode'] = 'manual';
        $hero->update(['data' => $custom]);
        $this->post(route('dashboard.store.logo'), ['logo' => $this->logo(220, 30, 50)])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('#FFF0D0', $hero->fresh()->data['background_color']);
        $this->assertNotSame($palette['primary'], $store->fresh()->logo_palette['primary']);

        $response = $this->postJson(route('dashboard.store.builder.store'), ['type' => 'hero', 'order' => 1])
            ->assertOk();
        $this->assertSame($store->fresh()->logo_palette['background'], $response->json('section.data.background_color'));
    }

    public function test_existing_logo_can_be_analyzed_from_the_hero_settings(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('La extracción de color necesita GD.');
        }

        Storage::fake('public');
        $owner = User::factory()->create(['role' => 'store_owner']);
        $store = Store::create(['user_id' => $owner->id, 'name' => 'Logo anterior', 'slug' => 'anterior',
            'status' => 'active', 'logo_path' => 'logos/previous.png']);
        Storage::disk('public')->put('logos/previous.png', $this->logo(25, 120, 185)->getContent());

        $this->actingAs($owner)->get(route('dashboard.store.builder'))
            ->assertOk()->assertSee('Extraer colores de mi logo');
        $this->post(route('dashboard.store.logo.palette'))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNotNull($store->fresh()->logo_palette);
        $this->get(route('dashboard.store.builder'))->assertOk()->assertSee('Aplicar colores del logo al hero');
    }

    private function logo(int $red, int $green, int $blue): UploadedFile
    {
        $image = imagecreatetruecolor(96, 96);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 255, 255, 255, 127));
        imagefilledellipse($image, 48, 48, 76, 76, imagecolorallocatealpha($image, $red, $green, $blue, 0));
        ob_start();
        imagepng($image);
        $content = ob_get_clean();
        imagedestroy($image);

        return UploadedFile::fake()->createWithContent('logo.png', $content);
    }
}
