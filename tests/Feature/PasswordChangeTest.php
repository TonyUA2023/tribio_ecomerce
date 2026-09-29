<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Mail\AccountEmailChanged;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
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

    public function test_owner_can_change_their_login_email_and_the_old_address_is_told(): void
    {
        Mail::fake();
        $owner = $this->owner();
        $old = $owner->email;
        $this->actingAs($owner);

        $this->get(route('dashboard.password.edit'))->assertOk()->assertSee('Correo de acceso')->assertSee($old);

        $this->post(route('dashboard.account.email.update'), [
            'email' => '  Ventas@ApachiPeru.com ', 'email_current_password' => 'old-password-123',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('ventas@apachiperu.com', $owner->fresh()->email);
        Mail::assertQueued(AccountEmailChanged::class, fn ($mail) => $mail->hasTo($old) && $mail->newEmail === 'ventas@apachiperu.com');
        Mail::assertQueued(AccountEmailChanged::class, fn ($mail) => str_contains($mail->render(), 've•••') && !str_contains($mail->render(), 'ventas@apachiperu.com'));

        // The new address works for signing in.
        $this->post(route('logout'));
        $this->post(route('login'), ['email' => 'ventas@apachiperu.com', 'password' => 'old-password-123']);
        $this->assertAuthenticatedAs($owner->fresh());
    }

    public function test_email_change_needs_the_password_and_a_free_address(): void
    {
        Mail::fake();
        $owner = $this->owner();
        $taken = User::factory()->create(['email' => 'otro@example.test']);
        $this->actingAs($owner);

        $this->post(route('dashboard.account.email.update'), ['email' => 'nuevo@example.test', 'email_current_password' => 'mala'])
            ->assertSessionHasErrors('email_current_password');
        $this->post(route('dashboard.account.email.update'), ['email' => 'OTRO@example.test', 'email_current_password' => 'old-password-123'])
            ->assertSessionHasErrors('email');
        $this->assertNotSame('nuevo@example.test', $owner->fresh()->email);
        Mail::assertNothingQueued();
    }

    public function test_google_linked_accounts_cannot_change_email_here(): void
    {
        $owner = User::factory()->create(['role' => 'store_owner', 'google_id' => 'g-123']);
        $this->actingAs($owner)->post(route('dashboard.account.email.update'), ['email' => 'x@example.test', 'email_current_password' => 'x'])
            ->assertForbidden();
    }
}
