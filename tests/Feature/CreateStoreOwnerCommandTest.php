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
}
