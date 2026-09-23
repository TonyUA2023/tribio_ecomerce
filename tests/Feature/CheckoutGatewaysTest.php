<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PendingCheckout;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Characterization of every storefront payment path (WhatsApp, Mercado Pago card and
 * Checkout Pro, PayPal) — written against the pre-refactor checkout so the pricing
 * extraction and the payments ledger can prove they changed nothing a buyer pays.
 * Shared cart: 2 × S/50 + 1 variant at S/25 = S/125; 10% bulk discount from 3 units
 * (−12.50); S/10 national shipping + S/15 express = S/25; total S/137.50.
 */
class CheckoutGatewaysTest extends TestCase
{
    use RefreshDatabase;

    private array $mpRequests = [];

    protected function migrateFreshUsing(): array
    {
        return ['--path' => array_values(array_filter(glob(database_path('migrations/*.php')),
            fn ($path) => !str_ends_with($path, '2026_09_11_182936_update_role_column_in_users_table.php'))), '--realpath' => true];
    }

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['app.url' => 'http://localhost']);
    }

    private function store(array $attributes = []): Store
    {
        return Store::create($attributes + [
            'user_id' => User::factory()->create()->id, 'name' => 'Bordados Lima', 'slug' => 'bordados-lima',
            'status' => 'active', 'template_name' => 'soft-market', 'checkout_mode' => 'mixed',
            'whatsapp_phone' => '51 999 888 777', 'national_shipping_cost' => 10,
            'bulk_discount_min_quantity' => 3, 'bulk_discount_type' => 'percentage', 'bulk_discount_value' => 10,
            'is_express_shipping_enabled' => true, 'express_shipping_cost' => 15,
        ]);
    }

    /** @return array{0: Product, 1: Product, 2: ProductVariant} */
    private function catalog(Store $store): array
    {
        $polo = Product::create(['store_id' => $store->id, 'name' => 'Polo bordado', 'slug' => 'polo', 'price' => 50, 'price_usd' => 14, 'stock' => 10, 'track_stock' => true, 'is_active' => true]);
        $gorra = Product::create(['store_id' => $store->id, 'name' => 'Gorra', 'slug' => 'gorra', 'price' => 20, 'price_usd' => 6, 'stock' => 5, 'track_stock' => true, 'is_active' => true, 'has_variants' => true]);
        $variant = ProductVariant::create(['product_id' => $gorra->id, 'sku' => 'GOR-NEG', 'price' => 25, 'price_usd' => 7, 'stock' => 5, 'attributes' => ['Color' => 'Negro'], 'is_active' => true]);

        return [$polo, $gorra, $variant];
    }

    private function cart(Product $polo, ProductVariant $variant, array $extra = []): array
    {
        return $extra + [
            'customer_name' => 'Ana Pérez', 'customer_phone' => '987654321', 'customer_email' => 'ana@example.test',
            'customer_address' => 'Av. Siempre Viva 123', 'customer_country' => 'PE', 'customer_city' => 'Lima',
            'express_shipping' => true,
            'items' => [
                ['id' => $polo->id, 'quantity' => 2],
                ['id' => $variant->product_id, 'quantity' => 1, 'variant_id' => $variant->id],
            ],
        ];
    }

    private function fakeMercadoPago(array $responses): void
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->mpRequests));
        $this->app->bind(Client::class, fn () => new Client(['handler' => $stack]));
    }

    private function mpBody(int $index = 0): array
    {
        return json_decode((string) $this->mpRequests[$index]['request']->getBody(), true);
    }

    public function test_whatsapp_checkout_prices_discount_shipping_and_express_and_decrements_stock(): void
    {
        $store = $this->store();
        [$polo, $gorra, $variant] = $this->catalog($store);

        $response = $this->postJson(route('store.checkout', $store->slug), $this->cart($polo, $variant))->assertOk()
            ->assertJson(['success' => true]);
        $this->assertStringStartsWith('https://wa.me/51999888777?text=', $response->json('whatsapp_url'));

        $order = Order::with('items')->sole();
        $this->assertSame($response->json('order_number'), $order->order_number);
        $this->assertEquals([125.0, 12.5, 25.0, 137.5], [(float) $order->subtotal, (float) $order->discount, (float) $order->shipping_cost, (float) $order->total]);
        $this->assertSame(['pending', 'pending', 'whatsapp'], [$order->status, $order->payment_status, $order->payment_method]);
        $this->assertTrue((bool) $order->is_express_shipping);
        $this->assertEquals([50.0, 25.0], $order->items->pluck('price')->map(fn ($p) => (float) $p)->all());
        $this->assertSame('GOR-NEG', $order->items[1]->product_sku);
        $this->assertSame('Negro', $order->items[1]->variant_title, 'Variant title must list the chosen options, not every DB column');

        $this->assertSame(8, $polo->fresh()->stock);
        $this->assertSame(4, $variant->fresh()->stock);
        $this->assertSame(4, $gorra->fresh()->stock);

        $this->assertSame(0, $order->payments()->count());
        $this->assertEquals([0.0, 137.5], [(float) $order->amount_paid, (float) $order->balance_due]);
        $order->update(['status' => 'cancelled']);
        $this->assertEquals(0.0, (float) $order->fresh()->balance_due, 'A cancelled order owes nothing');
    }

    public function test_insufficient_stock_is_rejected_before_anything_is_created(): void
    {
        $store = $this->store();
        [$polo, , $variant] = $this->catalog($store);
        $cart = $this->cart($polo, $variant);
        $cart['items'][0]['quantity'] = 11;

        $this->postJson(route('store.checkout', $store->slug), $cart)->assertStatus(422)
            ->assertJson(['error' => 'Stock insuficiente para Polo bordado. Disponibles: 10.']);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('pending_checkouts', 0);
    }

    public function test_free_shipping_waives_base_rate_but_keeps_express_and_backorder_items_sell_at_zero_stock(): void
    {
        $store = $this->store(['free_shipping_min_amount' => 100]);
        [$polo, , $variant] = $this->catalog($store);
        $polo->update(['stock' => 0, 'out_of_stock_message' => 'Se fabrica en 5 días']);

        $this->postJson(route('store.checkout', $store->slug), $this->cart($polo, $variant))->assertOk();

        $order = Order::sole();
        $this->assertEquals([15.0, 127.5], [(float) $order->shipping_cost, (float) $order->total]);
        $this->assertSame(0, $polo->fresh()->stock);
    }

    public function test_an_abandoned_gateway_checkout_does_not_block_the_next_checkout(): void
    {
        $store = $this->store();
        [$polo, , $variant] = $this->catalog($store);
        // A buyer opened a gateway's payment page and never came back: the draft keeps its reference forever.
        PendingCheckout::create(['store_id' => $store->id, 'reference' => Order::generateOrderNumber($store->id), 'gateway' => 'mercadopago', 'payload' => ['items' => []]]);

        $this->postJson(route('store.checkout', $store->slug), $this->cart($polo, $variant))->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_mercado_pago_embedded_card_approved_charges_the_exact_total(): void
    {
        $store = $this->store(['payment_gateway' => 'mercado_pago', 'checkout_mode' => 'card', 'mp_access_token' => 'TEST-token']);
        [$polo, , $variant] = $this->catalog($store);
        $this->fakeMercadoPago([new Response(201, [], json_encode(['id' => 987654, 'status' => 'approved']))]);

        $this->postJson(route('store.checkout', $store->slug), $this->cart($polo, $variant, [
            'payment_method' => 'mercadopago',
            'mp_form_data' => ['token' => 'card-token', 'installments' => 1, 'payment_method_id' => 'visa'],
        ]))->assertOk()->assertJson(['success' => true]);

        $this->assertSame(137.5, $this->mpBody()['transaction_amount']);
        $this->assertStringEndsWith('/v1/payments', (string) $this->mpRequests[0]['request']->getUri());
        $order = Order::sole();
        $this->assertSame(['paid', 'confirmed', 'mercadopago'], [$order->payment_status, $order->status, $order->payment_method]);
        $this->assertStringContainsString('987654', $order->internal_notes);
        $this->assertSame(8, $polo->fresh()->stock);
        $this->assertLedger($order, [['full', 137.5, 'mercadopago', '987654']]);
    }

    public function test_mercado_pago_embedded_card_rejected_keeps_stock_and_marks_the_attempt_failed(): void
    {
        $store = $this->store(['payment_gateway' => 'mercado_pago', 'checkout_mode' => 'card', 'mp_access_token' => 'TEST-token']);
        [$polo, , $variant] = $this->catalog($store);
        $this->fakeMercadoPago([new Response(201, [], json_encode(['id' => 111, 'status' => 'rejected', 'status_detail' => 'cc_rejected_other_reason']))]);

        $this->postJson(route('store.checkout', $store->slug), $this->cart($polo, $variant, [
            'payment_method' => 'mercadopago', 'mp_form_data' => ['token' => 'card-token'],
        ]))->assertOk()->assertJson(['success' => false]);

        $order = Order::sole();
        $this->assertSame('failed', $order->payment_status);
        $this->assertSame(10, $polo->fresh()->stock);
        $this->assertLedger($order, []);
        $this->assertEquals(0.0, (float) $order->balance_due);
    }

    public function test_mercado_pago_checkout_pro_preference_matches_the_order_and_a_replayed_webhook_is_idempotent(): void
    {
        $store = $this->store(['payment_gateway' => 'mercado_pago', 'mp_access_token' => 'TEST-token']);
        [$polo, , $variant] = $this->catalog($store);
        $this->fakeMercadoPago([
            new Response(201, [], json_encode(['id' => 'pref-1', 'init_point' => 'https://mp.test/live', 'sandbox_init_point' => 'https://mp.test/sandbox'])),
        ]);

        $response = $this->postJson(route('store.checkout', $store->slug), $this->cart($polo, $variant, ['payment_method' => 'mercadopago']))
            ->assertOk()->assertJson(['success' => true, 'payment_url' => 'https://mp.test/sandbox']);
        $reference = $response->json('order_number');

        $items = collect($this->mpBody()['items']);
        $this->assertEquals(137.5, round($items->sum(fn ($i) => $i['unit_price'] * $i['quantity']), 2));
        $this->assertEquals(-12.5, $items->firstWhere('title', 'Descuento por cantidad')['unit_price']);
        $this->assertEquals(25.0, $items->firstWhere('title', 'Costo de Envío')['unit_price']);
        $this->assertDatabaseCount('orders', 0);

        $this->get(route('store.checkout.return', [$store->slug, $reference]) . '?collection_status=approved&payment_id=555')->assertRedirect();
        $this->assertSame('paid', Order::sole()->payment_status);
        $this->assertSame(8, $polo->fresh()->stock);

        $this->fakeMercadoPago([new Response(200, [], json_encode(['id' => 555, 'status' => 'approved', 'external_reference' => $reference]))]);
        $this->postJson(route('api.mercadopago.webhook', $store->id) . '?type=payment&data_id=555')->assertOk();
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(8, $polo->fresh()->stock);
        $this->assertLedger(Order::sole(), [['full', 137.5, 'mercadopago', 'pref-1']]);
    }

    public function test_paypal_order_is_created_in_usd_with_discount_and_shipping_then_captured(): void
    {
        $store = $this->store(['payment_gateway' => 'paypal', 'paypal_client_id' => 'pp-id', 'paypal_client_secret' => 'pp-secret', 'paypal_mode' => 'sandbox']);
        [$polo, , $variant] = $this->catalog($store);
        Http::fake([
            'open.er-api.com/*' => Http::response([], 500),
            '*/v1/oauth2/token' => Http::response(['access_token' => 'pp-token', 'expires_in' => 3600]),
            '*/v2/checkout/orders/PP-1/capture' => Http::response(['status' => 'COMPLETED', 'purchase_units' => [['payments' => ['captures' => [['id' => 'CAP-1']]]]]]),
            '*/v2/checkout/orders' => Http::response(['id' => 'PP-1', 'status' => 'CREATED']),
        ]);

        $response = $this->postJson(route('store.checkout', $store->slug), $this->cart($polo, $variant, ['payment_method' => 'paypal']))
            ->assertOk()->assertJson(['success' => true, 'paypal_order_id' => 'PP-1']);

        Http::assertSent(function ($request) {
            if (!str_ends_with($request->url(), '/v2/checkout/orders')) {
                return false;
            }
            $amount = $request->data()['purchase_units'][0]['amount'];
            $usd = fn (float $pen) => round($pen * 0.268, 2);
            return $amount['breakdown']['item_total']['value'] === '35.00'   // 2 × 14 + 7 (explicit USD prices)
                && $amount['breakdown']['shipping']['value'] === number_format($usd(25), 2, '.', '')
                && $amount['breakdown']['discount']['value'] === number_format($usd(12.5), 2, '.', '');
        });
        $this->assertDatabaseCount('orders', 0);

        $this->postJson(route('store.checkout.paypal.capture', $store->slug), [
            'order_number' => $response->json('order_number'), 'paypal_order_id' => 'PP-1',
        ])->assertOk()->assertJson(['success' => true]);

        $order = Order::sole();
        $this->assertSame(['paid', 'confirmed', 'paypal'], [$order->payment_status, $order->status, $order->payment_method]);
        $this->assertSame(8, $polo->fresh()->stock);
        $this->assertLedger($order, [['full', 137.5, 'paypal', 'PP-1']]);
        $this->assertSame('PEN', $order->payments()->sole()->currency);
    }

    public function test_flow_confirmation_credits_the_ledger_once(): void
    {
        $store = $this->store();
        $pending = PendingCheckout::openWithFreshReference($store->id, [
            'gateway' => 'flow', 'gateway_ref' => 'flow-token-1',
            'payload' => ['customer_name' => 'Ana', 'customer_phone' => '9', 'customer_email' => 'a@example.test', 'subtotal' => 49.9,
                'discount' => 0, 'shipping_cost' => 0, 'total' => 49.9, 'currency' => 'PEN', 'payment_method' => 'flow', 'items' => []],
        ]);

        $order = $pending->materialize('paid', 'confirmed', decrementStock: true);
        $pending->materialize('paid', 'confirmed', decrementStock: true);

        $this->assertLedger($order->fresh(), [['full', 49.9, 'flow', 'flow-token-1']]);
    }

    public function test_a_pending_voucher_that_clears_later_is_credited_when_it_clears(): void
    {
        $store = $this->store(['payment_gateway' => 'mercado_pago', 'mp_access_token' => 'TEST-token']);
        [$polo, , $variant] = $this->catalog($store);
        $this->fakeMercadoPago([new Response(201, [], json_encode(['id' => 'pref-2', 'sandbox_init_point' => 'https://mp.test/sandbox']))]);
        $reference = $this->postJson(route('store.checkout', $store->slug), $this->cart($polo, $variant, ['payment_method' => 'mercadopago']))->json('order_number');

        $this->get(route('store.checkout.return', [$store->slug, $reference]) . '?collection_status=pending');
        $order = Order::sole();
        $this->assertLedger($order, []);
        $this->assertEquals(137.5, (float) $order->balance_due);

        $this->fakeMercadoPago([new Response(200, [], json_encode(['id' => 777, 'status' => 'approved', 'external_reference' => $reference]))]);
        $this->postJson(route('api.mercadopago.webhook', $store->id) . '?type=payment&data_id=777')->assertOk();
        $this->assertLedger($order->fresh(), [['full', 137.5, 'mercadopago', 'pref-2']]);
        $this->assertEquals(0.0, (float) $order->fresh()->balance_due);
    }

    public function test_a_ledger_conflict_never_loses_an_order_that_was_already_charged(): void
    {
        $store = $this->store(['payment_gateway' => 'mercado_pago', 'checkout_mode' => 'card', 'mp_access_token' => 'TEST-token']);
        [$polo, , $variant] = $this->catalog($store);
        $other = Order::create(['store_id' => $store->id, 'order_number' => 'OTRA-1', 'customer_name' => 'X', 'total' => 10]);
        \App\Models\OrderPayment::create(['order_id' => $other->id, 'kind' => 'full', 'amount' => 10, 'currency' => 'PEN',
            'gateway' => 'mercadopago', 'gateway_ref' => '987654', 'paid_at' => now()]);
        $this->fakeMercadoPago([new Response(201, [], json_encode(['id' => 987654, 'status' => 'approved']))]);

        $this->postJson(route('store.checkout', $store->slug), $this->cart($polo, $variant, [
            'payment_method' => 'mercadopago', 'mp_form_data' => ['token' => 'card-token'],
        ]))->assertOk()->assertJson(['success' => true]);

        $order = Order::where('order_number', '!=', 'OTRA-1')->sole();
        $this->assertSame('paid', $order->payment_status, 'The paid order must exist even if the ledger write fails');
        $this->assertLedger($order, []);
        $this->assertSame(1, $other->payments()->count(), 'The reference must stay credited only to the order it belongs to');
    }

    public function test_migration_backfills_the_ledger_from_existing_orders(): void
    {
        $store = $this->store();
        $rows = [
            ['TRB-OLD-1', 'confirmed', 'paid', 80], ['TRB-OLD-2', 'pending', 'pending', 60],
            ['TRB-OLD-3', 'refunded', 'refunded', 40], ['TRB-OLD-4', 'pending', 'failed', 30],
        ];
        foreach ($rows as [$number, $status, $paymentStatus, $total]) {
            Order::create(['store_id' => $store->id, 'order_number' => $number, 'customer_name' => 'X', 'total' => $total,
                'status' => $status, 'payment_status' => $paymentStatus, 'payment_method' => 'mercadopago', 'currency' => 'PEN']);
        }

        $migration = require database_path('migrations/2026_09_22_200000_create_order_payments_table.php');
        $migration->down();
        $migration->up();

        $byNumber = Order::with('payments')->get()->keyBy('order_number');
        $this->assertEquals([80.0, 0.0], [(float) $byNumber['TRB-OLD-1']->amount_paid, (float) $byNumber['TRB-OLD-1']->balance_due]);
        $this->assertSame(['full', 'paid'], [$byNumber['TRB-OLD-1']->payments->sole()->kind, $byNumber['TRB-OLD-1']->payments->sole()->status]);
        $this->assertEquals([0.0, 60.0], [(float) $byNumber['TRB-OLD-2']->amount_paid, (float) $byNumber['TRB-OLD-2']->balance_due]);
        $this->assertSame('refunded', $byNumber['TRB-OLD-3']->payments->sole()->status);
        $this->assertEquals([0.0, 0.0], [(float) $byNumber['TRB-OLD-3']->amount_paid, (float) $byNumber['TRB-OLD-3']->balance_due]);
        $this->assertCount(0, $byNumber['TRB-OLD-4']->payments);
        $this->assertEquals(0.0, (float) $byNumber['TRB-OLD-4']->balance_due);
    }

    /** @param array<array{0: string, 1: float, 2: string, 3: ?string}> $expected kind, amount, gateway, ref */
    private function assertLedger(Order $order, array $expected): void
    {
        $actual = $order->payments()->orderBy('id')->get()
            ->map(fn ($p) => [$p->kind, (float) $p->amount, $p->gateway, $p->gateway_ref])->all();
        $this->assertSame($expected, $actual);
        $this->assertEquals(array_sum(array_column($expected, 1)), (float) $order->fresh()->amount_paid);
    }
}
