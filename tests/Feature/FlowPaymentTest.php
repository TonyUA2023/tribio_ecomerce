<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\FlowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class FlowPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function migrateFreshUsing(): array
    {
        return ['--path' => array_values(array_filter(glob(database_path('migrations/*.php')),
            fn ($path) => !str_ends_with($path, '2026_09_11_182936_update_role_column_in_users_table.php'))), '--realpath' => true];
    }

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Http::preventStrayRequests();
        config(['app.url' => 'http://localhost']);
    }

    private function store(): Store
    {
        return Store::create([
            'user_id' => User::factory()->create()->id, 'name' => 'Maetek Store', 'slug' => 'maetek-store',
            'status' => 'active', 'template_name' => 'elegant-refurbished', 'checkout_mode' => 'mixed',
            'payment_gateway' => 'flow',
            'flow_enabled' => true, 'flow_api_key' => 'test-key', 'flow_secret_key' => 'test-secret',
            'flow_mode' => 'sandbox', 'flow_currency' => 'PEN',
        ]);
    }

    private function order(Store $store): Order
    {
        return Order::create(['store_id' => $store->id, 'order_number' => 'FLOW-TEST-1',
            'customer_name' => 'Comprador', 'customer_email' => 'buyer@example.test',
            'total' => 49.90, 'currency' => 'PEN', 'payment_method' => 'flow',
            'payment_status' => 'pending', 'status' => 'pending', 'flow_token' => 'opaque-token', 'flow_order_id' => '123']);
    }

    private function flowStatus(int $status = 2, array $override = []): array
    {
        return array_replace(['commerceOrder' => 'FLOW-TEST-1', 'flowOrder' => 123,
            'currency' => 'PEN', 'amount' => 49.90, 'status' => $status], $override);
    }

    public function test_creation_signs_form_and_never_exposes_credentials(): void
    {
        $store = $this->store();
        $order = $this->order($store);
        Http::fake(['*/payment/create' => Http::response(['url' => 'https://sandbox.flow.cl/app/web/pay.php', 'token' => 'new-token', 'flowOrder' => 456])]);
        $url = app(FlowService::class)->createPayment($store, $order);
        $this->assertStringEndsWith('?token=new-token', $url);
        Http::assertSent(function ($request) {
            $p = $request->data();
            $signature = $p['s']; unset($p['s']); ksort($p);
            $message = ''; foreach ($p as $key => $value) { $message .= $key . $value; }
            return $signature === hash_hmac('sha256', $message, 'test-secret')
                && $p['amount'] === '49.90' && $p['currency'] === 'PEN' && $p['paymentMethod'] === 9
                && str_starts_with($p['urlConfirmation'], 'http://localhost/api/flow/');
        });
        $this->assertNotEquals('test-secret', $store->getRawOriginal('flow_secret_key'));
        $this->assertArrayNotHasKey('flow_secret_key', $store->toArray());
        $this->assertArrayNotHasKey('flow_token', $order->toArray());
    }

    public function test_callbacks_are_verified_idempotent_and_cannot_downgrade_paid_orders(): void
    {
        $store = $this->store(); $order = $this->order($store);
        Http::fake(['*/payment/getStatus*' => Http::sequence()->push($this->flowStatus())->push($this->flowStatus())->push($this->flowStatus(1))]);
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/flow/'.$store->id.'/confirmation', ['token' => 'opaque-token'])->assertOk();
        }
        $this->assertEquals('paid', $order->fresh()->payment_status);
        $this->assertEquals('confirmed', $order->fresh()->status);
    }

    public function test_amount_currency_and_identity_mismatches_never_mark_paid(): void
    {
        $store = $this->store(); $order = $this->order($store);
        foreach ([['amount' => 1], ['currency' => 'USD'], ['commerceOrder' => 'OTHER'], ['flowOrder' => 999]] as $override) {
            Http::fake(['*/payment/getStatus*' => Http::response($this->flowStatus(2, $override))]);
            $this->postJson('/api/flow/'.$store->id.'/confirmation', ['token' => 'opaque-token'])->assertStatus(503);
            $this->assertEquals('pending', $order->fresh()->payment_status);
        }
    }

    public function test_unknown_token_and_other_store_are_rejected_without_network_call(): void
    {
        $store = $this->store(); $this->order($store);
        $other = Store::create(['user_id' => $store->user_id, 'name' => 'Otra', 'slug' => 'otra']);
        $this->postJson('/api/flow/'.$other->id.'/confirmation', ['token' => 'opaque-token'])->assertNotFound();
        $this->postJson('/api/flow/'.$store->id.'/confirmation', [])->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_pending_failed_paid_and_unavailable_return_screens(): void
    {
        $store = $this->store(); $this->order($store);
        Http::fake(['*/payment/getStatus*' => Http::sequence()->push($this->flowStatus(1))->push([], 500)->push($this->flowStatus(3))->push($this->flowStatus(2))]);
        foreach (['Tu pago está pendiente', 'No pudimos consultar Flow', 'El pago no se completó', 'Tu pago está confirmado'] as $message) {
            $this->post('/api/flow/'.$store->id.'/return', ['token' => 'opaque-token'])->assertOk()->assertSee($message)->assertHeader('Referrer-Policy', 'no-referrer');
        }
    }

    public function test_checkout_routes_flow_independently_of_mercadopago(): void
    {
        $store = $this->store();
        $store->update(['checkout_mode' => 'card', 'mp_access_token' => 'also-configured']);
        $product = Product::create(['store_id' => $store->id, 'name' => 'Producto', 'slug' => 'producto', 'price' => 50, 'price_usd' => 15, 'stock' => 10, 'track_stock' => true, 'is_active' => true]);
        Http::fake(['*/payment/create' => Http::response(['url' => 'https://sandbox.flow.cl/app/web/pay.php', 'token' => 'checkout-token', 'flowOrder' => 567])]);
        $this->postJson('/tienda/'.$store->slug.'/checkout', ['payment_method' => 'flow', 'customer_name' => 'Cliente', 'customer_email' => 'buyer@example.test', 'customer_phone' => '999999999', 'items' => [['id' => $product->id, 'quantity' => 2]]])
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('whatsapp_url', null)->assertJsonPath('payment_url', 'https://sandbox.flow.cl/app/web/pay.php?token=checkout-token');
        $this->assertDatabaseHas('orders', ['payment_method' => 'flow', 'payment_status' => 'pending', 'total' => 100]);
        $this->assertEquals(8, $product->fresh()->stock);
        Http::assertSentCount(1);
    }

    public function test_failed_initialization_rolls_back_stock_and_order(): void
    {
        $store = $this->store();
        $product = Product::create(['store_id' => $store->id, 'name' => 'Producto', 'slug' => 'producto', 'price' => 50, 'price_usd' => 15, 'stock' => 10, 'track_stock' => true, 'is_active' => true]);
        Http::fake(['*/payment/create' => Http::response([], 500)]);
        $this->postJson('/tienda/'.$store->slug.'/checkout', ['payment_method' => 'flow', 'customer_name' => 'Cliente', 'customer_email' => 'buyer@example.test', 'customer_phone' => '999999999', 'items' => [['id' => $product->id, 'quantity' => 2]]])->assertStatus(502);
        $this->assertEquals(10, $product->fresh()->stock);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_disabled_flow_and_currency_mismatch_reject_before_stock_changes(): void
    {
        $store = $this->store();
        $store->update(['flow_enabled' => false]);
        $this->postJson('/tienda/'.$store->slug.'/checkout', ['payment_method' => 'flow'])->assertUnprocessable();
        $store->update(['flow_enabled' => true, 'flow_currency' => 'USD']);
        $this->postJson('/tienda/'.$store->slug.'/checkout', ['payment_method' => 'flow'])->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
        Http::assertNothingSent();
    }

    public function test_flow_only_store_drawer_defaults_to_flow_and_renders_shared_option(): void
    {
        $store = $this->store(); $store->update(['checkout_mode' => 'card']);
        $html = view('components.checkout.drawer', compact('store'))->render();
        $this->assertStringContainsString("paymentMethod: 'flow'", $html);
        $this->assertStringContainsString('Continuar a Flow', $html);
        $this->assertStringContainsString('pay-flow-option', $html);
        $this->assertStringNotContainsString('test-secret', $html);
    }

    public function test_legacy_confirmation_cannot_confirm_flow_from_query_parameters(): void
    {
        $store = $this->store(); $store->update(['template_name' => 'minimal-light']);
        $order = $this->order($store);
        $this->get('/tienda/'.$store->slug.'/pedido/'.$order->id.'/confirmacion?status=approved&payment_id=forged')->assertOk();
        $this->assertEquals('pending', $order->fresh()->payment_status);
        $this->assertEquals('flow', $order->fresh()->payment_method);
        $other = Store::create(['user_id' => $store->user_id, 'name' => 'Otra', 'slug' => 'otra', 'status' => 'active']);
        $this->get('/tienda/'.$other->slug.'/pedido/'.$order->id.'/confirmacion?status=approved')->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_settings_preserve_blank_secrets_and_never_render_them(): void
    {
        $store = $this->store();
        $this->actingAs($store->user);
        $this->get(route('dashboard.store.edit'))->assertOk()->assertSee('Secret Key')->assertDontSee('test-secret');
        // flow_enabled ya no es un checkbox del formulario: se deriva de payment_gateway.
        $payload = ['name' => $store->name, 'category' => 'otros', 'build_mode' => 'builder', 'checkout_mode' => 'mixed',
            'payment_gateway' => 'flow', 'flow_mode' => 'sandbox', 'flow_currency' => 'PEN', 'flow_api_key' => '', 'flow_secret_key' => ''];
        $this->post(route('dashboard.store.update'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals('test-secret', $store->fresh()->flow_secret_key);
        $this->assertTrue($store->fresh()->flow_enabled);
        $this->post(route('dashboard.store.update'), array_replace($payload, ['payment_gateway' => '']))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFalse($store->fresh()->flow_enabled);
        $this->assertNull($store->fresh()->payment_gateway);
    }
}

