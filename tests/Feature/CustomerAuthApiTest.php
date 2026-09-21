<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerAuthApiTest extends TestCase
{
    use RefreshDatabase;

    protected function migrateFreshUsing(): array
    {
        if (config('database.default') !== 'sqlite') {
            return [];
        }
        // The legacy role conversion uses MySQL-only MODIFY syntax. These tests
        // use the original store_owner/cliente roles and keep production migrations intact.
        return ['--path' => array_values(array_filter(glob(database_path('migrations/*.php')),
            fn ($path) => !str_ends_with($path, '2026_09_11_182936_update_role_column_in_users_table.php'))), '--realpath' => true];
    }

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    // ─── Registration: send-otp ─────────────────────────────────────────

    public function test_send_otp_caches_code_and_does_not_call_brevo_without_a_key(): void
    {
        config(['services.brevo.api_key' => null]);
        Http::fake();

        $this->postJson('/api/customer/register/send-otp', [
            'name' => 'Compradora Nueva', 'email' => 'nueva@example.test', 'password' => 'secret123',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertNotNull(Cache::get('otp_nueva@example.test'));
        Http::assertNothingSent();
    }

    public function test_send_otp_calls_brevo_with_expected_payload_when_key_is_configured(): void
    {
        config(['services.brevo.api_key' => 'fake-brevo-key']);
        Http::fake(['api.brevo.com/*' => Http::response(['messageId' => 'abc'], 201)]);

        $this->postJson('/api/customer/register/send-otp', [
            'name' => 'Compradora Nueva', 'email' => 'nueva@example.test', 'password' => 'secret123',
        ])->assertOk();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.brevo.com/v3/smtp/email'
                && $request->hasHeader('api-key', 'fake-brevo-key')
                && $request['to'][0]['email'] === 'nueva@example.test'
                && $request['sender']['name'] === 'Tribio Pass';
        });
    }

    public function test_send_otp_rejects_an_already_registered_email(): void
    {
        User::factory()->create(['email' => 'existente@example.test']);
        Http::fake();

        $this->postJson('/api/customer/register/send-otp', [
            'name' => 'Alguien', 'email' => 'existente@example.test', 'password' => 'secret123',
        ])->assertStatus(422);
    }

    // ─── Registration: verify ────────────────────────────────────────────

    public function test_verify_otp_creates_a_cliente_account_and_issues_a_token(): void
    {
        config(['services.brevo.api_key' => null]);
        Cache::put('otp_nueva@example.test', '123456', now()->addMinutes(15));

        $response = $this->postJson('/api/customer/register/verify', [
            'email' => 'nueva@example.test', 'token' => '123456',
            'name' => 'Compradora Nueva', 'password' => 'secret123',
        ])->assertOk()->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role', 'phone'], 'store']);

        $response->assertJsonPath('user.role', 'cliente')->assertJsonPath('store', null);
        $this->assertDatabaseHas('users', ['email' => 'nueva@example.test', 'role' => 'cliente']);
        $this->assertNull(Cache::get('otp_nueva@example.test'));
    }

    public function test_verify_otp_reassociates_prior_guest_orders_to_the_new_account(): void
    {
        $store = Store::create(['user_id' => User::factory()->create(['role' => 'store_owner'])->id, 'name' => 'Tienda', 'slug' => 'tienda', 'status' => 'active']);
        $guestOrder = Order::create(['store_id' => $store->id, 'order_number' => 'GUEST-001', 'customer_name' => 'Invitada', 'customer_email' => 'nueva@example.test', 'total' => 50, 'user_id' => null]);

        Cache::put('otp_nueva@example.test', '123456', now()->addMinutes(15));
        $this->postJson('/api/customer/register/verify', [
            'email' => 'nueva@example.test', 'token' => '123456',
            'name' => 'Compradora Nueva', 'password' => 'secret123',
        ])->assertOk();

        $user = User::where('email', 'nueva@example.test')->firstOrFail();
        $this->assertEquals($user->id, $guestOrder->fresh()->user_id);
    }

    public function test_verify_otp_backdoor_only_works_when_brevo_key_is_not_configured(): void
    {
        config(['services.brevo.api_key' => null]);
        $this->postJson('/api/customer/register/verify', [
            'email' => 'backdoor@example.test', 'token' => '000000',
            'name' => 'Backdoor', 'password' => 'secret123',
        ])->assertOk();
        $this->assertDatabaseHas('users', ['email' => 'backdoor@example.test']);

        config(['services.brevo.api_key' => 'fake-brevo-key']);
        $this->postJson('/api/customer/register/verify', [
            'email' => 'sinbackdoor@example.test', 'token' => '000000',
            'name' => 'Sin Backdoor', 'password' => 'secret123',
        ])->assertStatus(422);
        $this->assertDatabaseMissing('users', ['email' => 'sinbackdoor@example.test']);
    }

    public function test_verify_otp_rejects_a_wrong_or_expired_code(): void
    {
        Cache::put('otp_nueva@example.test', '123456', now()->addMinutes(15));

        $this->postJson('/api/customer/register/verify', [
            'email' => 'nueva@example.test', 'token' => '654321',
            'name' => 'Compradora Nueva', 'password' => 'secret123',
        ])->assertStatus(422);

        $this->assertDatabaseMissing('users', ['email' => 'nueva@example.test']);
    }

    // ─── Login ───────────────────────────────────────────────────────────

    public function test_customer_login_works_for_a_store_owner_and_returns_their_store(): void
    {
        $owner = User::factory()->create(['role' => 'store_owner', 'password' => Hash::make('secret123')]);
        $store = Store::create(['user_id' => $owner->id, 'name' => 'Mi Tienda', 'slug' => 'mi-tienda', 'status' => 'active']);

        $this->postJson('/api/customer/login', ['email' => $owner->email, 'password' => 'secret123'])
            ->assertOk()
            ->assertJsonPath('user.role', 'store_owner')
            ->assertJsonPath('store.id', $store->id);
    }

    public function test_customer_login_works_for_a_pure_buyer_with_no_store(): void
    {
        $buyer = User::factory()->create(['role' => 'cliente', 'password' => Hash::make('secret123')]);

        $this->postJson('/api/customer/login', ['email' => $buyer->email, 'password' => 'secret123'])
            ->assertOk()
            ->assertJsonPath('user.role', 'cliente')
            ->assertJsonPath('store', null);
    }

    public function test_customer_login_rejects_super_admin(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'password' => Hash::make('secret123')]);

        $this->postJson('/api/customer/login', ['email' => $admin->email, 'password' => 'secret123'])
            ->assertStatus(403);

        $this->assertSame(0, $admin->tokens()->count());
    }

    public function test_customer_login_rejects_wrong_password(): void
    {
        $buyer = User::factory()->create(['role' => 'cliente', 'password' => Hash::make('secret123')]);

        $this->postJson('/api/customer/login', ['email' => $buyer->email, 'password' => 'wrong'])
            ->assertStatus(422);
    }

    public function test_store_owner_login_via_original_door_is_unchanged(): void
    {
        $owner = User::factory()->create(['role' => 'store_owner', 'password' => Hash::make('secret123')]);
        $store = Store::create(['user_id' => $owner->id, 'name' => 'Mi Tienda', 'slug' => 'mi-tienda', 'status' => 'active']);

        $this->postJson('/api/login', ['email' => $owner->email, 'password' => 'secret123'])
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role', 'phone'], 'store' => ['id', 'name', 'slug', 'status', 'logo_url']])
            ->assertJsonPath('store.id', $store->id);
    }

    // ─── Orders (Mis Compras) ────────────────────────────────────────────

    public function test_customer_orders_lists_only_the_authenticated_buyers_orders_across_stores(): void
    {
        $buyer = User::factory()->create(['role' => 'cliente']);
        $otherBuyer = User::factory()->create(['role' => 'cliente']);
        $ownerA = User::factory()->create(['role' => 'store_owner']);
        $ownerB = User::factory()->create(['role' => 'store_owner']);
        $storeA = Store::create(['user_id' => $ownerA->id, 'name' => 'Tienda A', 'slug' => 'tienda-a', 'status' => 'active']);
        $storeB = Store::create(['user_id' => $ownerB->id, 'name' => 'Tienda B', 'slug' => 'tienda-b', 'status' => 'active']);

        Order::create(['store_id' => $storeA->id, 'order_number' => 'A-001', 'customer_name' => 'Compradora', 'customer_email' => $buyer->email, 'total' => 30, 'user_id' => $buyer->id, 'status' => 'confirmed']);
        Order::create(['store_id' => $storeB->id, 'order_number' => 'B-001', 'customer_name' => 'Compradora', 'customer_email' => $buyer->email, 'total' => 45, 'user_id' => null, 'status' => 'pending']);
        Order::create(['store_id' => $storeA->id, 'order_number' => 'A-002', 'customer_name' => 'Otra', 'customer_email' => $otherBuyer->email, 'total' => 99, 'user_id' => $otherBuyer->id]);

        Sanctum::actingAs($buyer);

        $response = $this->getJson('/api/customer/orders')->assertOk()->assertJsonPath('success', true);
        $orderNumbers = collect($response->json('orders'))->pluck('order_number')->all();

        $this->assertEqualsCanonicalizing(['A-001', 'B-001'], $orderNumbers);
    }

    public function test_customer_orders_rejects_super_admin_and_unauthenticated_requests(): void
    {
        $this->getJson('/api/customer/orders')->assertStatus(401);

        $admin = User::factory()->create(['role' => 'super_admin']);
        Sanctum::actingAs($admin);
        $this->getJson('/api/customer/orders')->assertStatus(403);
    }
}
