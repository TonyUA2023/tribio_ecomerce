<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateStoreOwnerCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function migrateFreshUsing(): array
    {
        return ['--path' => array_values(array_filter(glob(database_path('migrations/*.php')),
            fn ($path) => !str_ends_with($path, '2026_09_11_182936_update_role_column_in_users_table.php'))), '--realpath' => true];
    }

    public function test_creates_an_owner_who_can_log_in_and_an_active_store(): void
    {
        $this->artisan('tribio:create-owner', ['email' => 'Nintai@Example.test', 'store_name' => 'NINTAI FASHION', '--password' => 'Temporal2026x'])
            ->assertSuccessful();

        $user = User::where('email', 'nintai@example.test')->firstOrFail();
        $this->assertSame(User::ROLE_STORE_OWNER, $user->role);
        $this->assertTrue(Hash::check('Temporal2026x', $user->password));
        $store = Store::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('nintai-fashion', $store->slug);
        $this->assertSame('active', $store->status);
        $this->assertSame('moda', $store->category);
    }

    public function test_refuses_an_existing_email_without_changing_anything(): void
    {
        User::factory()->create(['email' => 'nintai@example.test']);

        $this->artisan('tribio:create-owner', ['email' => 'nintai@example.test', 'store_name' => 'NINTAI FASHION'])->assertFailed();
        $this->assertSame(0, Store::count());
    }

    public function test_applies_template_brand_colors_and_settings(): void
    {
        $this->artisan('tribio:create-owner', [
            'email' => 'rahhver@example.test', 'store_name' => 'Rahhver Jeans', '--password' => 'Temporal2026x',
            '--template' => 'urban-style', '--primary' => '#1F4E8C', '--secondary' => '#111111', '--font' => 'playfair',
            '--tagline' => 'Jeans para toda la familia', '--whatsapp' => '51 900 000 000',
            '--set' => ['promo.enabled=0', 'hero.items.0.title=Jeans para toda la familia', 'hero.items.0.highlight='],
        ])->assertSuccessful();

        $store = Store::where('slug', 'rahhver-jeans')->firstOrFail();
        $this->assertSame('urban-style', $store->template_name);
        $this->assertSame('#1F4E8C', $store->accent_color);
        $this->assertSame('51900000000', $store->whatsapp_phone);
        $settings = $store->template_settings['urban-style'];
        $this->assertSame(['primary' => '#1F4E8C', 'secondary' => '#111111'], $settings['colors']);
        $this->assertSame('playfair', $settings['typography']['heading']);
        $this->assertFalse($settings['promo']['enabled']);
        $this->assertSame('', $settings['hero']['items'][0]['highlight']);

        $this->get(route('store.show', $store->slug))->assertOk()->assertSee('Jeans para toda la familia');
    }

    public function test_rejects_an_unknown_template_a_bad_color_and_an_unknown_setting(): void
    {
        $base = ['email' => 'a@example.test', 'store_name' => 'Tienda A'];
        $this->artisan('tribio:create-owner', $base + ['--template' => 'nope'])->assertFailed();
        $this->artisan('tribio:create-owner', $base + ['--primary' => 'azul'])->assertFailed();
        $this->artisan('tribio:create-owner', $base + ['--set' => ['no.such.field=1']])->assertFailed();
        $this->assertSame(0, Store::count());
    }
}
