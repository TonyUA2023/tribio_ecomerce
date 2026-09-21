<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
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

    private function fakeGoogleUser(array $attributes = []): SocialiteUser
    {
        return SocialiteUser::fake($attributes);
    }

    public function test_redirect_sends_the_visitor_to_google(): void
    {
        $response = $this->get('/auth/google/redirect');

        $response->assertRedirect();
        $this->assertStringContainsString('accounts.google.com', $response->headers->get('Location'));
    }

    public function test_new_google_signup_creates_a_cliente_account(): void
    {
        Socialite::shouldReceive('driver->user')->andReturn($this->fakeGoogleUser([
            'id' => 'g-new-1', 'email' => 'nueva.google@example.test', 'name' => 'Google Nueva',
        ]));

        $response = $this->get('/auth/google/callback');

        $user = User::where('email', 'nueva.google@example.test')->firstOrFail();
        $this->assertSame('g-new-1', $user->google_id);
        $this->assertSame('cliente', $user->role);
        $this->assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('tribio-pass'));
    }

    public function test_google_signin_auto_links_an_existing_store_owner_by_email(): void
    {
        $owner = User::factory()->create(['role' => 'store_owner', 'email' => 'duena@example.test', 'google_id' => null]);
        Store::create(['user_id' => $owner->id, 'name' => 'Su Tienda', 'slug' => 'su-tienda', 'status' => 'active']);

        Socialite::shouldReceive('driver->user')->andReturn($this->fakeGoogleUser([
            'id' => 'g-owner-1', 'email' => 'duena@example.test', 'name' => 'Dueña',
        ]));

        $response = $this->get('/auth/google/callback');

        $this->assertSame(1, User::where('email', 'duena@example.test')->count());
        $owner->refresh();
        $this->assertSame('g-owner-1', $owner->google_id);
        $this->assertSame('store_owner', $owner->role);
        $this->assertAuthenticatedAs($owner);
        $response->assertRedirect(route('dashboard.index'));
    }

    public function test_google_signin_reuses_an_already_linked_account(): void
    {
        $buyer = User::factory()->create(['role' => 'cliente', 'google_id' => 'g-existing-1']);

        Socialite::shouldReceive('driver->user')->andReturn($this->fakeGoogleUser([
            'id' => 'g-existing-1', 'email' => 'otro-correo@example.test', 'name' => 'Otra Persona',
        ]));

        $this->get('/auth/google/callback');

        $this->assertSame(1, User::count());
        $this->assertAuthenticatedAs($buyer);
    }

    public function test_super_admin_cannot_authenticate_through_the_google_door(): void
    {
        User::factory()->create(['role' => 'super_admin', 'email' => 'admin@example.test']);

        Socialite::shouldReceive('driver->user')->andReturn($this->fakeGoogleUser([
            'id' => 'g-admin-1', 'email' => 'admin@example.test', 'name' => 'Admin',
        ]));

        $response = $this->get('/auth/google/callback');

        $this->assertGuest();
        $response->assertSessionHasErrors('google');
    }

    public function test_pending_plan_selection_survives_the_redirect_round_trip(): void
    {
        $this->get('/auth/google/redirect?plan_key=basic');

        Socialite::shouldReceive('driver->user')->andReturn($this->fakeGoogleUser([
            'id' => 'g-plan-1', 'email' => 'compra.plan@example.test', 'name' => 'Compra Plan',
        ]));

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect(route('home', ['plan_key' => 'basic']) . '#precios');
    }

    public function test_socialite_failure_redirects_home_with_an_error_and_creates_no_user(): void
    {
        Socialite::shouldReceive('driver->user')->andThrow(new \Exception('invalid_state'));

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect(route('home'));
        $response->assertSessionHasErrors('google');
        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_existing_login_doors_are_unaffected(): void
    {
        $owner = User::factory()->create(['role' => 'store_owner', 'password' => bcrypt('secret123')]);

        $this->postJson('/customer/login', ['email' => $owner->email, 'password' => 'secret123'])
            ->assertOk()->assertJsonPath('success', true);
    }

    public function test_google_signin_from_a_store_checkout_drawer_returns_to_that_store(): void
    {
        $owner = User::factory()->create(['role' => 'store_owner']);
        $store = Store::create(['user_id' => $owner->id, 'name' => 'Tienda Drawer', 'slug' => 'tienda-drawer', 'status' => 'active']);

        $this->get('/auth/google/redirect?store=tienda-drawer');

        Socialite::shouldReceive('driver->user')->andReturn($this->fakeGoogleUser([
            'id' => 'g-drawer-1', 'email' => 'comprador.drawer@example.test', 'name' => 'Comprador Drawer',
        ]));

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect($store->url . '?tribio_pass_login=1');
        $this->assertAuthenticated();
    }

    public function test_google_signin_failure_from_a_store_drawer_returns_to_that_store_without_the_reopen_flag(): void
    {
        $owner = User::factory()->create(['role' => 'store_owner']);
        $store = Store::create(['user_id' => $owner->id, 'name' => 'Tienda Drawer 2', 'slug' => 'tienda-drawer-2', 'status' => 'active']);

        $this->get('/auth/google/redirect?store=tienda-drawer-2');
        Socialite::shouldReceive('driver->user')->andThrow(new \Exception('invalid_state'));

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect($store->url);
        $response->assertSessionHasErrors('google');
    }
}
