<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordChangeTest extends TestCase
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

    private function owner(): User
    {
        return User::factory()->create(['role' => 'store_owner', 'password' => 'old-password-123']);
    }

    public function test_store_owner_can_change_their_password(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);

        $this->get(route('dashboard.password.edit'))->assertOk()->assertSee('Cambiar contraseña');

        $this->post(route('dashboard.password.update'), [
            'current_password' => 'old-password-123',
            'password' => 'new-password-456',
            'password_confirmation' => 'new-password-456',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('new-password-456', $owner->fresh()->password));
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $owner = $this->owner();
        $originalHash = $owner->password;
        $this->actingAs($owner);

        $this->post(route('dashboard.password.update'), [
            'current_password' => 'not-the-real-password',
            'password' => 'new-password-456',
            'password_confirmation' => 'new-password-456',
        ])->assertSessionHasErrors('current_password');

        $this->assertSame($originalHash, $owner->fresh()->password);
    }

    public function test_new_password_must_be_confirmed_and_long_enough(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);

        $this->post(route('dashboard.password.update'), [
            'current_password' => 'old-password-123',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');

        $this->post(route('dashboard.password.update'), [
            'current_password' => 'old-password-123',
            'password' => 'new-password-456',
            'password_confirmation' => 'does-not-match',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('old-password-123', $owner->fresh()->password));
    }
}
