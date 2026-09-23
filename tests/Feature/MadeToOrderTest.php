<?php

namespace Tests\Feature;

use App\Mail\OrderProductionUpdated;
use App\Models\Attachment;
use App\Models\Order;
use App\Models\PendingCheckout;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\CustomerIdentityService;
use App\Services\ExchangeRateService;
use App\Services\MadeToOrder\CustomizationSchema;
use App\Services\Checkout\CheckoutPricingException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Phase 1 of made-to-order selling ("Por encargo B2C"). Shared cart: an embroidered polo
 * at S/40 + S/5 text + S/3 gold thread = S/48 each, sizes S×2 + M×3 = 5 units = S/240,
 * plus S/10 shipping = S/250. The store asks a 50% deposit: S/125 today, S/125 later.
 */
class MadeToOrderTest extends TestCase
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
        Storage::fake('local');
        config(['app.url' => 'http://localhost']);
    }

    private function store(array $attributes = [], ?User $owner = null): Store
    {
        return Store::create($attributes + [
            'user_id' => ($owner ?? User::factory()->create(['role' => 'store_owner']))->id,
            'name' => 'Bordados Lima', 'slug' => 'bordados-lima', 'status' => 'active', 'template_name' => 'soft-market',
            'checkout_mode' => 'mixed', 'whatsapp_phone' => '51 999 888 777', 'national_shipping_cost' => 10,
            'made_to_order_enabled' => true, 'deposit_percent' => 50,
        ]);
    }

    private function schema(array $extra = []): array
    {
        return CustomizationSchema::normalize(array_merge([
            ['type' => 'text', 'label' => 'Texto a bordar', 'required' => true, 'max' => 20, 'price' => 5],
            ['type' => 'choice', 'label' => 'Color de hilo', 'required' => true, 'options' => [
                ['label' => 'Dorado', 'price' => 3, 'color' => '#D4AF37'], ['label' => 'Blanco'],
            ]],
            ['type' => 'sizes', 'label' => 'Tallas', 'required' => true, 'sizes' => ['S', 'M', 'L']],
        ], $extra));
    }

    private function product(Store $store, array $attributes = []): Product
    {
        return Product::create($attributes + [
            'store_id' => $store->id, 'name' => 'Polo bordado con tu logo', 'slug' => 'polo-logo', 'price' => 40, 'price_usd' => 11,
            'stock' => 0, 'track_stock' => false, 'is_active' => true,
            'sale_mode' => Product::SALE_MADE_TO_ORDER, 'lead_time_days' => 7, 'customization_schema' => $this->schema(),
        ]);
    }

    private function answers(array $override = []): array
    {
        return array_replace(['texto_a_bordar' => 'Colegio San José', 'color_de_hilo' => 'Dorado', 'tallas' => ['S' => 2, 'M' => 3]], $override);
    }

    private function cart(Product $product, array $extra = [], ?array $answers = null): array
    {
        return $extra + [
            'customer_name' => 'Ana Pérez', 'customer_phone' => '987654321', 'customer_email' => 'ana@example.test',
            'customer_address' => 'Av. Siempre Viva 123', 'customer_country' => 'PE', 'customer_city' => 'Lima',
            'items' => [['id' => $product->id, 'quantity' => 1, 'customization' => $answers ?? $this->answers()]],
        ];
    }

    private function fakeMercadoPago(array $responses): void
    {
        $this->mpRequests = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->mpRequests));
        $this->app->bind(Client::class, fn () => new Client(['handler' => $stack]));
    }

    private function mpBody(int $index = 0): array
    {
        return json_decode((string) $this->mpRequests[$index]['request']->getBody(), true);
    }

    /** @param array<array{0: string, 1: float, 2: string, 3: ?string}> $expected kind, amount, gateway, ref */
    private function assertLedger(Order $order, array $expected): void
    {
        $this->assertSame($expected, $order->payments()->orderBy('id')->get()
            ->map(fn ($p) => [$p->kind, (float) $p->amount, $p->gateway, $p->gateway_ref])->all());
    }

    /** A made-to-order order whose 50% deposit was already paid online. */
    private function orderWithDeposit(Store $store): Order
    {
        $order = Order::create([
            'store_id' => $store->id, 'order_number' => 'TRB-2026-000077', 'customer_name' => 'Ana Pérez',
            'customer_email' => 'ana@example.test', 'customer_phone' => '987654321', 'subtotal' => 240, 'shipping_cost' => 10,
            'total' => 250, 'currency' => 'PEN', 'status' => 'confirmed', 'payment_status' => 'pending',
            'payment_method' => 'mercadopago', 'production_stage' => 'received', 'deposit_amount' => 125,
            'estimated_ready_at' => today()->addDays(7)->toDateString(),
        ]);
        $order->items()->create(['product_name' => 'Polo bordado con tu logo', 'price' => 48, 'quantity' => 5, 'subtotal' => 240,
            'customization' => [['key' => 'texto_a_bordar', 'label' => 'Texto a bordar', 'type' => 'text', 'value' => 'Colegio San José', 'price' => 5]]]);
        $order->recordPayment(125, 'deposit', 'mercadopago', 'dep-1');

        return $order->fresh();
    }

    // ─── Schema ──────────────────────────────────────────────────

    public function test_the_schema_is_normalized_strictly_and_rejects_what_it_cannot_store(): void
    {
        $schema = CustomizationSchema::normalize([
            ['type' => 'text', 'label' => '  Nombre   a bordar ', 'max' => 9999, 'price' => -4],
            ['type' => 'text', 'label' => 'Nombre a bordar'],
            ['type' => 'choice', 'label' => 'Hilo', 'options' => [['label' => 'Rojo', 'color' => 'not-a-color'], ['label' => '']]],
        ]);
        $this->assertSame(['nombre_a_bordar', 'nombre_a_bordar_2', 'hilo'], array_column($schema, 'key'));
        $this->assertSame([200, 0.0], [$schema[0]['max'], $schema[0]['price']], 'Length and price are clamped');
        $this->assertSame([['label' => 'Rojo', 'price' => 0.0, 'color' => null]], $schema[2]['options']);

        foreach ([
            [['type' => 'script', 'label' => 'X']],
            [['type' => 'choice', 'label' => 'Hilo', 'options' => []]],
            [['type' => 'sizes', 'label' => 'A', 'sizes' => ['S']], ['type' => 'sizes', 'label' => 'B', 'sizes' => ['M']]],
            ['not-a-list' => ['type' => 'text', 'label' => 'X']],
        ] as $invalid) {
            try {
                CustomizationSchema::normalize($invalid);
                $this->fail('Invalid schema was accepted: ' . json_encode($invalid));
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('customization_schema', $e->errors());
            }
        }
    }

    public function test_answers_are_priced_server_side_and_sizes_decide_the_quantity(): void
    {
        $store = $this->store();
        $evaluated = CustomizationSchema::evaluate($this->schema([['type' => 'date', 'label' => 'Para cuándo']]),
            $this->answers(['para_cuando' => today()->addDays(10)->toDateString()]), 1, 7, $store->id, 'Polo');

        $this->assertSame([8.0, 5], [$evaluated->extraPerUnit, $evaluated->quantity]);
        $this->assertSame(['Colegio San José', 'Dorado', 'S × 2, M × 3'], array_slice(array_column($evaluated->rows, 'value'), 0, 3));
        $this->assertSame('#D4AF37', $evaluated->rows[1]['color']);
        $this->assertSame(today()->addDays(10)->toDateString(), $evaluated->requiredBy);

        $rejections = [
            [['texto_a_bordar' => ''], 'Polo: completa «Texto a bordar».'],
            [['texto_a_bordar' => str_repeat('x', 21)], 'Polo: «Texto a bordar» admite hasta 20 caracteres.'],
            [['color_de_hilo' => 'Plateado'], 'Polo: la opción elegida en «Color de hilo» ya no está disponible.'],
            [['tallas' => ['S' => 0]], 'Polo: indica cuántas unidades quieres por talla en «Tallas».'],
            [['para_cuando' => today()->addDays(2)->toDateString()], 'Polo: la fecha más próxima posible es el ' . today()->addDays(7)->format('d/m/Y') . '.'],
        ];
        foreach ($rejections as [$override, $message]) {
            try {
                CustomizationSchema::evaluate($this->schema([['type' => 'date', 'label' => 'Para cuándo']]),
                    $this->answers($override), 1, 7, $store->id, 'Polo');
                $this->fail("Accepted: {$message}");
            } catch (CheckoutPricingException $e) {
                $this->assertSame($message, $e->getMessage());
            }
        }
    }

    // ─── Dashboard: product form & store settings ────────────────

    public function test_the_product_form_saves_made_to_order_settings_only_for_stores_that_sell_that_way(): void
    {
        $owner = User::factory()->create(['role' => 'store_owner']);
        $store = $this->store([], $owner);
        $form = ['name' => 'Gorra bordada', 'price' => 35, 'stock' => 0, 'is_active' => 1, 'sale_mode' => 'made_to_order', 'lead_time_days' => 10,
            'customization_schema_json' => json_encode([['type' => 'text', 'label' => 'Iniciales', 'required' => true, 'max' => 3]])];

        $this->actingAs($owner)->post(route('dashboard.productos.store'), $form)->assertRedirect(route('dashboard.productos.index'));
        $product = Product::where('name', 'Gorra bordada')->sole();
        $this->assertSame(['made_to_order', 10, false], [$product->sale_mode, $product->lead_time_days, (bool) $product->track_stock]);
        $this->assertSame('iniciales', $product->customization_schema[0]['key']);

        $this->post(route('dashboard.productos.store'), ['customization_schema_json' => '[{"type":"script","label":"x"}]', 'name' => 'Mala'] + $form)
            ->assertSessionHasErrors('customization_schema');
        $this->assertSame(0, Product::where('name', 'Mala')->count(), 'A bad schema never leaves a half-saved product');

        $store->update(['made_to_order_enabled' => false]);
        $this->post(route('dashboard.productos.store'), ['name' => 'Taza'] + $form)->assertRedirect();
        $this->assertSame('stock', Product::where('name', 'Taza')->sole()->sale_mode, 'Inert while the store does not sell made-to-order');
    }

    // ─── Checkout ────────────────────────────────────────────────

    public function test_whatsapp_order_snapshots_the_customization_opens_the_workshop_and_leaves_stock_alone(): void
    {
        $store = $this->store();
        $product = $this->product($store, ['stock' => 3, 'track_stock' => true]);

        $response = $this->postJson(route('store.checkout', $store->slug), $this->cart($product))->assertOk()->assertJson(['success' => true]);

        $order = Order::with('items')->sole();
        $this->assertEquals([240.0, 10.0, 250.0, 125.0], [(float) $order->subtotal, (float) $order->shipping_cost, (float) $order->total, (float) $order->deposit_amount]);
        $this->assertSame(['received', 'pending', 'pending'], [$order->production_stage, $order->payment_status, $order->status]);
        $this->assertSame(today()->addDays(7)->toDateString(), $order->estimated_ready_at->toDateString());
        $item = $order->items->sole();
        $this->assertSame([5, 48.0], [$item->quantity, (float) $item->price], 'Sizes set the quantity; extras raise the unit price');
        $this->assertSame(['Colegio San José', 'Dorado', 'S × 2, M × 3'], array_column($item->customization, 'value'));
        $this->assertSame(3, $product->fresh()->stock, 'Made-to-order lines are never taken from stock');

        $message = urldecode($response->json('whatsapp_url'));
        $this->assertStringContainsString('✂️ Texto a bordar: Colegio San José', $message);
        $this->assertStringContainsString('Adelanto a pagar', $message);
        $this->assertEquals([0.0, 250.0], [(float) $order->amount_paid, (float) $order->balance_due]);
    }

    public function test_invalid_answers_are_rejected_before_anything_is_created(): void
    {
        $store = $this->store();
        $product = $this->product($store);

        $this->postJson(route('store.checkout', $store->slug), $this->cart($product, [], $this->answers(['texto_a_bordar' => ' '])))
            ->assertStatus(422)->assertJson(['error' => 'Polo bordado con tu logo: completa «Texto a bordar».']);
        $this->assertDatabaseCount('pending_checkouts', 0);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_a_store_that_turns_made_to_order_off_sells_the_product_as_regular_stock(): void
    {
        $store = $this->store(['made_to_order_enabled' => false]);
        $product = $this->product($store, ['stock' => 10, 'track_stock' => true]);

        $this->postJson(route('store.checkout', $store->slug), $this->cart($product))->assertOk();

        $order = Order::with('items')->sole();
        $this->assertEquals(50.0, (float) $order->total, 'Plain price × requested quantity, no extras, no deposit');
        $this->assertNull($order->production_stage);
        $this->assertNull($order->deposit_amount);
        $this->assertNull($order->items->sole()->customization);
        $this->assertSame(9, $product->fresh()->stock);
    }

    public function test_mercado_pago_card_charges_only_the_deposit_and_the_order_stays_partial(): void
    {
        $store = $this->store(['payment_gateway' => 'mercado_pago', 'checkout_mode' => 'card', 'mp_access_token' => 'TEST-token']);
        $product = $this->product($store);
        $this->fakeMercadoPago([new Response(201, [], json_encode(['id' => 4242, 'status' => 'approved']))]);

        $this->postJson(route('store.checkout', $store->slug), $this->cart($product, [
            'payment_method' => 'mercadopago', 'mp_form_data' => ['token' => 'card-token', 'installments' => 1, 'payment_method_id' => 'visa'],
        ]))->assertOk()->assertJson(['success' => true]);

        $this->assertEquals(125, $this->mpBody()['transaction_amount']);
        $order = Order::sole();
        $this->assertSame(['partial', 'received'], [$order->payment_status, $order->production_stage]);
        $this->assertLedger($order, [['deposit', 125.0, 'mercadopago', '4242']]);
        $this->assertEquals([125.0, 125.0], [(float) $order->amount_paid, (float) $order->balance_due]);
    }

    public function test_checkout_pro_asks_for_one_deposit_line_and_the_webhook_credits_it(): void
    {
        $store = $this->store(['payment_gateway' => 'mercado_pago', 'mp_access_token' => 'TEST-token']);
        $product = $this->product($store);
        $this->fakeMercadoPago([new Response(201, [], json_encode(['id' => 'pref-9', 'sandbox_init_point' => 'https://mp.test/sandbox']))]);

        $reference = $this->postJson(route('store.checkout', $store->slug), $this->cart($product, ['payment_method' => 'mercadopago']))
            ->assertOk()->json('order_number');

        $items = $this->mpBody()['items'];
        $this->assertCount(1, $items);
        $this->assertSame(["Adelanto 50% · Pedido {$reference}", 1, 125], [$items[0]['title'], $items[0]['quantity'], $items[0]['unit_price']]);

        $this->fakeMercadoPago([new Response(200, [], json_encode(['id' => 31, 'status' => 'approved', 'external_reference' => $reference]))]);
        $this->postJson(route('api.mercadopago.webhook', $store->id) . '?type=payment&data_id=31')->assertOk();
        $order = Order::sole();
        $this->assertSame('partial', $order->payment_status);
        $this->assertLedger($order, [['deposit', 125.0, 'mercadopago', 'pref-9']]);
    }

    public function test_paypal_charges_the_deposit_share_of_the_usd_total(): void
    {
        $store = $this->store(['payment_gateway' => 'paypal', 'paypal_client_id' => 'pp-id', 'paypal_client_secret' => 'pp-secret', 'paypal_mode' => 'sandbox']);
        $product = $this->product($store);
        Http::fake([
            'open.er-api.com/*' => Http::response([], 500),
            '*/v1/oauth2/token' => Http::response(['access_token' => 'pp-token', 'expires_in' => 3600]),
            '*/v2/checkout/orders/PP-7/capture' => Http::response(['status' => 'COMPLETED', 'purchase_units' => [['payments' => ['captures' => [['id' => 'CAP-7']]]]]]),
            '*/v2/checkout/orders' => Http::response(['id' => 'PP-7', 'status' => 'CREATED']),
        ]);

        $reference = $this->postJson(route('store.checkout', $store->slug), $this->cart($product, ['payment_method' => 'paypal']))
            ->assertOk()->assertJson(['success' => true])->json('order_number');

        $rates = app(ExchangeRateService::class);
        $fullUsd = round(11 + $rates->convert(8, 'PEN', 'USD'), 2) * 5 + round($rates->convert(10, 'PEN', 'USD'), 2);
        Http::assertSent(function ($request) use ($fullUsd) {
            if (!str_ends_with($request->url(), '/v2/checkout/orders')) {
                return false;
            }
            $unit = $request->data()['purchase_units'][0];
            return count($unit['items']) === 1
                && str_starts_with($unit['items'][0]['name'], 'Adelanto 50%')
                && $unit['amount']['value'] === number_format(round($fullUsd * 0.5, 2), 2, '.', '');
        });

        $this->postJson(route('store.checkout.paypal.capture', $store->slug), ['order_number' => $reference, 'paypal_order_id' => 'PP-7'])->assertOk();
        $order = Order::sole();
        $this->assertSame('partial', $order->payment_status);
        $this->assertLedger($order, [['deposit', 125.0, 'paypal', 'PP-7']]);
    }

    public function test_flow_is_asked_to_charge_the_deposit(): void
    {
        $store = $this->store(['payment_gateway' => 'flow', 'flow_enabled' => true, 'flow_api_key' => 'k', 'flow_secret_key' => 's',
            'flow_mode' => 'sandbox', 'flow_currency' => 'PEN']);
        $product = $this->product($store);
        Http::fake(['*/payment/create' => Http::response(['url' => 'https://sandbox.flow.cl/app/web/pay.php', 'token' => 'flow-tok', 'flowOrder' => 9])]);

        $this->postJson(route('store.checkout', $store->slug), $this->cart($product, ['payment_method' => 'flow']))->assertOk()->assertJson(['success' => true]);

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/payment/create') && $request->data()['amount'] === '125.00');
        $this->assertSame(125.0, PendingCheckout::sole()->chargeAmount());
    }

    public function test_uploaded_files_are_linked_to_the_order_line_and_shown_to_the_owner(): void
    {
        $owner = User::factory()->create(['role' => 'store_owner']);
        $store = $this->store([], $owner);
        $product = $this->product($store, ['customization_schema' => $this->schema([['type' => 'file', 'label' => 'Tu logo', 'required' => true, 'price' => 2]])]);
        $token = $this->postJson(route('store.attachments.upload', $store->slug), ['file' => UploadedFile::fake()->image('logo.png')])
            ->assertCreated()->json('token');

        $this->postJson(route('store.checkout', $store->slug), $this->cart($product, [], $this->answers(['tu_logo' => $token])))->assertOk();

        $order = Order::with('items')->sole();
        $item = $order->items->sole();
        $this->assertEquals(50.0, (float) $item->price, 'S/40 + text 5 + thread 3 + logo 2');
        $attachment = Attachment::sole();
        $this->assertSame([$item->getMorphClass(), $item->id], [$attachment->attachable_type, $attachment->attachable_id]);

        $this->actingAs($owner)->get(route('dashboard.pedidos.show', $order))->assertOk()
            ->assertSee('Texto a bordar')->assertSee('Colegio San José')->assertSee('Descargar logo.png')
            ->assertSee('✂️ Producción')->assertSee('Registrar pago recibido')->assertSee('/saldo?', false);
    }

    // ─── Dashboard: production & manual payments ─────────────────

    public function test_the_owner_moves_the_production_stage_and_the_buyer_is_told(): void
    {
        $owner = User::factory()->create(['role' => 'store_owner']);
        $store = $this->store([], $owner);
        $order = $this->orderWithDeposit($store);

        $this->actingAs($owner)->patch(route('dashboard.pedidos.production', $order), [
            'production_stage' => 'in_production', 'notify_customer' => 1, 'message' => 'Ya estamos bordando.',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame('in_production', $order->fresh()->production_stage);
        Mail::assertQueued(OrderProductionUpdated::class, fn ($mail) => $mail->hasTo('ana@example.test'));

        $this->patch(route('dashboard.pedidos.production', $order), ['production_stage' => 'shipped'])->assertSessionHasErrors('production_stage');

        $intruder = User::factory()->create(['role' => 'store_owner']);
        $this->store(['slug' => 'otra-tienda'], $intruder);
        $this->actingAs($intruder)->patch(route('dashboard.pedidos.production', $order), ['production_stage' => 'ready'])->assertForbidden();
        $this->assertSame('in_production', $order->fresh()->production_stage);
    }

    public function test_manual_payments_complete_the_balance_and_cannot_exceed_it(): void
    {
        $owner = User::factory()->create(['role' => 'store_owner']);
        $store = $this->store([], $owner);
        $order = $this->orderWithDeposit($store);

        $this->actingAs($owner)->post(route('dashboard.pedidos.payments.store', $order), ['amount' => 200, 'method' => 'yape'])
            ->assertSessionHasErrors('amount');
        $this->post(route('dashboard.pedidos.payments.store', $order), ['amount' => 100, 'method' => 'yape', 'note' => 'Op. 123'])->assertRedirect();
        $this->assertSame('partial', $order->fresh()->payment_status);
        $this->post(route('dashboard.pedidos.payments.store', $order), ['amount' => 25, 'method' => 'efectivo'])->assertRedirect();

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertEquals([250.0, 0.0], [(float) $order->amount_paid, (float) $order->balance_due]);
        $manual = $order->payments()->where('gateway', 'manual')->orderBy('id')->get();
        $this->assertSame(['balance', 'balance'], $manual->pluck('kind')->all());
        $this->assertSame(['Yape · Op. 123', 'Efectivo'], $manual->pluck('notes')->all());
        $this->assertSame($owner->id, $manual->first()->recorded_by);
    }

    // ─── Buyer: balance page & online balance payment ────────────

    public function test_the_balance_page_needs_the_signed_link(): void
    {
        $store = $this->store(['payment_gateway' => 'mercado_pago', 'mp_access_token' => 'TEST-token']);
        $order = $this->orderWithDeposit($store);

        $this->get(route('store.balance.show', [$store->slug, $order->order_number]))->assertForbidden();
        $this->get($order->balancePaymentUrl())->assertOk()
            ->assertSee('Pedido #TRB-2026-000077')->assertSee('Pedido recibido')->assertSee('Colegio San José')
            ->assertSee('Pagar saldo · S/ 125.00')->assertDontSee('987654321')->assertDontSee('ana@example.test');

        $stockOrder = Order::create(['store_id' => $store->id, 'order_number' => 'TRB-2026-000078', 'customer_name' => 'X', 'total' => 10]);
        $this->get(URL::temporarySignedRoute('store.balance.show', now()->addDay(), [$store->slug, $stockOrder->order_number]))->assertNotFound();

        $store->update(['payment_gateway' => 'flow']);
        $this->get($order->balancePaymentUrl())->assertOk()->assertDontSee('Pagar saldo')->assertSee('Coordinar el pago por WhatsApp');
    }

    public function test_the_balance_is_paid_online_once_even_when_mercado_pago_reports_it_twice(): void
    {
        $store = $this->store(['payment_gateway' => 'mercado_pago', 'mp_access_token' => 'TEST-token']);
        $order = $this->orderWithDeposit($store);
        $this->fakeMercadoPago([new Response(201, [], json_encode(['id' => 'pref-bal', 'sandbox_init_point' => 'https://mp.test/saldo']))]);

        $startUrl = URL::temporarySignedRoute('store.balance.start', now()->addHour(), [$store->slug, $order->order_number]);
        $this->post($startUrl)->assertRedirect('https://mp.test/saldo');

        $body = $this->mpBody();
        $this->assertSame([['title' => 'Saldo del pedido TRB-2026-000077', 'quantity' => 1, 'unit_price' => 125, 'currency_id' => 'PEN']], $body['items']);
        $this->assertSame('TRB-2026-000077-S1', $body['external_reference']);
        $pending = PendingCheckout::where('reference', 'TRB-2026-000077-S1')->sole();
        $this->assertStringEndsWith('/saldo/retorno/TRB-2026-000077-S1/' . $pending->gateway_meta['return_token'], $body['back_urls']['success']);

        $approved = ['id' => 9001, 'status' => 'approved', 'transaction_amount' => 125, 'external_reference' => 'TRB-2026-000077-S1'];
        $this->fakeMercadoPago([new Response(200, [], json_encode($approved))]);
        $this->get(route('store.balance.return', [$store->slug, 'TRB-2026-000077-S1', $pending->gateway_meta['return_token']]) . '?collection_status=approved&payment_id=9001')
            ->assertRedirect()->assertSessionHas('balance_state', 'paid');

        $this->fakeMercadoPago([new Response(200, [], json_encode($approved))]);
        $this->postJson(route('api.mercadopago.webhook', $store->id) . '?type=payment&data_id=9001')->assertOk();

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertLedger($order, [['deposit', 125.0, 'mercadopago', 'dep-1'], ['balance', 125.0, 'mercadopago', '9001']]);
        $this->assertNull($order->internal_notes, 'The balance never rewrites the original order');
        $this->assertDatabaseCount('orders', 1);
        $this->get($order->balancePaymentUrl())->assertOk()->assertSee('Pedido pagado por completo')->assertDontSee('Pagar saldo');
    }

    public function test_a_forged_return_credits_nothing_but_the_real_webhook_does(): void
    {
        $store = $this->store(['payment_gateway' => 'mercado_pago', 'mp_access_token' => 'TEST-token']);
        $order = $this->orderWithDeposit($store);
        $this->fakeMercadoPago([new Response(201, [], json_encode(['id' => 'pref-bal', 'sandbox_init_point' => 'https://mp.test/saldo']))]);
        $this->post(URL::temporarySignedRoute('store.balance.start', now()->addHour(), [$store->slug, $order->order_number]));
        $pending = PendingCheckout::where('reference', 'TRB-2026-000077-S1')->sole();
        $token = $pending->gateway_meta['return_token'];

        $this->get(route('store.balance.return', [$store->slug, 'TRB-2026-000077-S1', 'wrong-token']) . '?collection_status=approved&payment_id=1')->assertNotFound();
        $this->get(route('store.checkout.return', [$store->slug, 'TRB-2026-000077-S1']) . '?collection_status=approved')->assertNotFound();

        // Someone else's approved payment, replayed against this reference.
        $this->fakeMercadoPago([new Response(200, [], json_encode(['id' => 5, 'status' => 'approved', 'transaction_amount' => 125, 'external_reference' => 'OTHER']))]);
        $this->get(route('store.balance.return', [$store->slug, 'TRB-2026-000077-S1', $token]) . '?collection_status=approved&payment_id=5')
            ->assertRedirect()->assertSessionHas('balance_state', 'pending');
        $this->assertSame('partial', $order->fresh()->payment_status);

        $this->fakeMercadoPago([new Response(200, [], json_encode(['id' => 6, 'status' => 'approved', 'transaction_amount' => 125, 'external_reference' => 'TRB-2026-000077-S1']))]);
        $this->postJson(route('api.mercadopago.webhook', $store->id) . '?type=payment&data_id=6')->assertOk();
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertLedger($order, [['deposit', 125.0, 'mercadopago', 'dep-1'], ['balance', 125.0, 'mercadopago', '6']]);
    }

    public function test_tribio_pass_orders_expose_the_stage_and_the_balance_link(): void
    {
        $store = $this->store();
        $order = $this->orderWithDeposit($store);
        Order::create(['store_id' => $store->id, 'order_number' => 'TRB-2026-000078', 'customer_name' => 'Ana', 'customer_email' => 'ana@example.test', 'total' => 30]);
        $buyer = User::factory()->create(['email' => 'ana@example.test']);

        $orders = app(CustomerIdentityService::class)->ordersFor($buyer)->keyBy('order_number');

        $made = $orders[$order->order_number];
        $this->assertSame(['received', 'Pedido recibido', 'partial', 125.0, 125.0],
            [$made['production_stage'], $made['production_stage_label'], $made['payment_status'], $made['amount_paid'], $made['balance_due']]);
        $this->assertStringContainsString('/pedido/TRB-2026-000077/saldo?', $made['balance_url']);
        $this->assertNull($orders['TRB-2026-000078']['production_stage']);
        $this->assertNull($orders['TRB-2026-000078']['balance_url']);
    }

    public function test_the_storefront_product_page_shows_the_customization_form(): void
    {
        $store = $this->store();
        $product = $this->product($store);

        $this->get(route('store.product', [$store->slug, $product->slug]))->assertOk()
            ->assertSee('Hecho a pedido')->assertSee('madeToOrderForm', false)->assertSee('Texto a bordar');
    }
}
