<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class GoogleAccountLinkTest extends TestCase
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

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google.client_id' => 'fake-client-id', 'services.google.client_secret' => 'fake-secret']);
    }

    private function owner(): User
    {
        $owner = User::factory()->create(['role' => 'store_owner', 'password' => Hash::make('secret123')]);
        Store::create(['user_id' => $owner->id, 'name' => 'Su Tienda', 'slug' => 'su-tienda-' . $owner->id, 'status' => 'active']);
        return $owner;
    }

    public function test_dashboard_google_connect_requires_authentication(): void
    {
        $this->get('/dashboard/mi-cuenta/google/conectar')->assertRedirect('/login');
    }

    public function test_store_owner_can_link_their_account_to_google(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->get('/dashboard/mi-cuenta/google/conectar')->assertRedirect();

        Socialite::shouldReceive('driver->redirectUrl->user')->andReturn(SocialiteUser::fake([
            'id' => 'g-link-1', 'email' => 'otro-correo-google@example.test', 'name' => 'Dueño Vinculado',
        ]));

        $response = $this->actingAs($owner)->get('/dashboard/mi-cuenta/google/callback');

        $owner->refresh();
        $this->assertSame('g-link-1', $owner->google_id);
        $response->assertRedirect(route('dashboard.password.edit'));
        $response->assertSessionHas('success');
    }

    public function test_linking_google_disables_the_old_password(): void
    {
        $owner = $this->owner();

        Socialite::shouldReceive('driver->redirectUrl->user')->andReturn(SocialiteUser::fake([
            'id' => 'g-link-2', 'email' => 'otro2@example.test', 'name' => 'Dueño',
        ]));
        $this->actingAs($owner)->get('/dashboard/mi-cuenta/google/callback');

        $this->postJson('/api/login', ['email' => $owner->email, 'password' => 'secret123'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Esta cuenta usa Google para iniciar sesión. Por ahora, ingresa desde tribioshop.com con el botón de Google.');
    }

    public function test_cannot_link_a_google_account_already_linked_to_someone_else(): void
    {
        $ownerA = $this->owner();
        $ownerB = $this->owner();
        $ownerA->update(['google_id' => 'g-taken']);

        Socialite::shouldReceive('driver->redirectUrl->user')->andReturn(SocialiteUser::fake([
            'id' => 'g-taken', 'email' => 'irrelevant@example.test', 'name' => 'Owner B',
        ]));

        $response = $this->actingAs($ownerB)->get('/dashboard/mi-cuenta/google/callback');

        $ownerB->refresh();
        $this->assertNull($ownerB->google_id);
        $response->assertSessionHas('error');
    }

    public function test_web_login_shows_a_google_specific_message_once_linked(): void
    {
        $owner = $this->owner();
        $owner->update(['google_id' => 'g-already-linked']);

        $this->post('/login', ['email' => $owner->email, 'password' => 'wrong-or-random-hash'])
            ->assertSessionHasErrors(['email' => 'Esta cuenta está conectada con Google. Inicia sesión con el botón "Continuar con Google".']);
    }

    public function test_customer_portal_login_shows_a_google_specific_message_once_linked(): void
    {
        $owner = $this->owner();
        $owner->update(['google_id' => 'g-already-linked-2']);

        $this->postJson('/customer/login', ['email' => $owner->email, 'password' => 'whatever'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Esta cuenta está conectada con Google. Inicia sesión con el botón "Continuar con Google".');
    }

    public function test_mobile_customer_login_points_to_the_web_once_linked(): void
    {
        $buyer = User::factory()->create(['role' => 'cliente', 'google_id' => 'g-buyer-linked']);

        $this->postJson('/api/customer/login', ['email' => $buyer->email, 'password' => 'whatever'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Esta cuenta usa Google para iniciar sesión. Por ahora, ingresa desde tribioshop.com con el botón de Google.');
    }

    public function test_accounts_without_google_still_see_the_generic_message(): void
    {
        $owner = $this->owner();

        $this->post('/login', ['email' => $owner->email, 'password' => 'wrong'])
            ->assertSessionHasErrors(['email' => 'Las credenciales no coinciden con nuestros registros.']);
    }
}
