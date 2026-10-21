<?php

namespace Tests\Feature;

use App\Mail\OrderPaymentConfirmed;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** "Pago por confirmación": Yape/Plin/transfer paid outside the site, confirmed by the seller. */
class ManualPaymentsTest extends TestCase
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
        Storage::fake('public');
    }

    private function seller(array $storeAttributes = []): array
    {
        $owner = User::factory()->create(['role' => 'store_owner']);
        $store = Store::create($storeAttributes + [
            'user_id' => $owner->id, 'name' => 'Inti Boutique', 'slug' => 'inti', 'status' => 'active',
            'template_name' => 'soft-market', 'checkout_mode' => 'whatsapp', 'whatsapp_phone' => '51 901 317 207',
            'national_shipping_cost' => 10,
            'manual_payments' => ['yape' => ['enabled' => true, 'phone' => '901317207', 'holder' => 'Nair Salazar'], 'cod' => ['enabled' => true]],
        ]);
        $product = Product::create(['store_id' => $store->id, 'name' => 'Blusa', 'slug' => 'blusa', 'price' => 50, 'stock' => 10, 'track_stock' => true, 'is_active' => true]);

        return [$owner, $store, $product];
    }

    private function cart(Product $product, array $extra = []): array
    {
        return $extra + [
            'customer_name' => 'Ana Pérez', 'customer_phone' => '987654321', 'customer_email' => 'ana@example.test',
            'customer_address' => 'Av. Arequipa 123', 'customer_country' => 'PE', 'customer_state' => 'Lima', 'customer_city' => 'Miraflores',
            'items' => [['id' => $product->id, 'quantity' => 2]],
        ];
    }

    public function test_owner_saves_methods_with_a_qr_and_the_drawer_shows_them(): void
    {
        [$owner, $store] = $this->seller();

        $this->actingAs($owner)->post(route('dashboard.manual-payments.update'), [
            'yape' => ['enabled' => 1, 'phone' => '901317207', 'holder' => 'Nair Salazar Pinedo', 'qr' => UploadedFile::fake()->image('qr.png', 300, 300)],
            'bank' => ['enabled' => 1, 'bank' => 'BCP', 'account' => '191-12345678-0-12'],
        ])->assertSessionHasNoErrors();

        $yape = $store->fresh()->manual_payments['yape'];
        $this->assertTrue($yape['enabled']);
        Storage::disk('public')->assertExists($yape['qr_path']);

        $this->get(route('dashboard.gateway.edit'))->assertOk()->assertSee('Pagos por confirmación')->assertSee('Quitar QR');
        $this->get(route('dashboard.shipping.index'))->assertOk()->assertSee('Cobertura de envío');

        $this->get(route('store.show', $store->slug))->assertOk()
            ->assertSee('Nair Salazar Pinedo')->assertSee('191-12345678-0-12')->assertSee('Ya realicé el pago');
    }

    public function test_the_gateway_page_save_button_also_saves_manual_payments(): void
    {
        [$owner, $store] = $this->seller(['manual_payments' => null]);

        $this->actingAs($owner)->post(route('dashboard.gateway.update'), [
            'checkout_mode' => 'whatsapp', 'manual_payments_form' => 1,
            'yape' => ['enabled' => 1, 'phone' => '901317207'],
        ])->assertSessionHasNoErrors();

        $this->assertTrue($store->fresh()->manual_payments['yape']['enabled']);
        $this->get(route('store.show', $store->slug))->assertSee('901317207');
    }

    public function test_a_method_without_anything_to_pay_to_cannot_be_enabled(): void
    {
        [$owner] = $this->seller();

        $this->actingAs($owner)->post(route('dashboard.manual-payments.update'), ['plin' => ['enabled' => 1]])
            ->assertSessionHasErrors('plin.enabled');
    }

    public function test_paid_yape_order_waits_for_confirmation_and_the_seller_confirms_it(): void
    {
        [$owner, $store, $product] = $this->seller();

        $this->postJson(route('store.checkout', $store->slug), $this->cart($product, [
            'payment_method' => 'manual_yape', 'payment_done' => true, 'payment_reference' => 'OP-778899',
        ]))->assertOk()->assertJson(['success' => true, 'whatsapp_url' => null]);

        $order = Order::sole();
        $this->assertSame('awaiting', $order->payment_confirmation);
        $this->assertSame('pending', $order->payment_status);
        $this->assertSame('OP-778899', $order->payment_reference);
        $this->assertSame(8, $product->fresh()->stock);

        $this->getJson('/api/pedido-estado/' . $order->order_number)->assertJson(['payment_confirmation' => 'awaiting']);

        $this->actingAs($owner)->get(route('dashboard.pedidos.show', $order))->assertSee('Pedido en confirmación');
        $this->get(route('dashboard.pedidos.index', ['status' => 'por_confirmar']))
            ->assertViewHas('orders', fn ($o) => $o->count() === 1);

        $this->post(route('dashboard.pedidos.payment.confirm', $order))->assertSessionHas('success');

        $order->refresh();
        $this->assertSame('confirmed', $order->payment_confirmation);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('confirmed', $order->status);
        $this->assertEquals($order->total, $order->amount_paid);
        $this->assertSame(1, $order->payments()->count());
        Mail::assertQueued(OrderPaymentConfirmed::class, fn ($m) => $m->confirmed);

        // Confirming twice never double-credits the ledger.
        $this->post(route('dashboard.pedidos.payment.confirm', $order))->assertStatus(422);
        $this->assertSame(1, $order->payments()->count());
    }

    public function test_rejecting_marks_the_payment_failed_without_crediting_anything(): void
    {
        [$owner, $store, $product] = $this->seller();
        $this->postJson(route('store.checkout', $store->slug), $this->cart($product, ['payment_method' => 'manual_yape', 'payment_done' => true]))->assertOk();
        $order = Order::sole();

        $this->actingAs($owner)->post(route('dashboard.pedidos.payment.reject', $order), ['note' => 'No llegó'])->assertSessionHas('success');

        $order->refresh();
        $this->assertSame('rejected', $order->payment_confirmation);
        $this->assertSame('failed', $order->payment_status);
        $this->assertSame(0, $order->payments()->count());
        Mail::assertQueued(OrderPaymentConfirmed::class, fn ($m) => !$m->confirmed);
    }

    public function test_another_store_owner_cannot_confirm_the_payment(): void
    {
        [, $store, $product] = $this->seller();
        $this->postJson(route('store.checkout', $store->slug), $this->cart($product, ['payment_method' => 'manual_yape', 'payment_done' => true]))->assertOk();
        $order = Order::sole();

        $intruder = User::factory()->create(['role' => 'store_owner']);
        Store::create(['user_id' => $intruder->id, 'name' => 'Otra', 'slug' => 'otra', 'status' => 'active', 'template_name' => 'soft-market', 'checkout_mode' => 'whatsapp']);

        $this->actingAs($intruder)->post(route('dashboard.pedidos.payment.confirm', $order))->assertForbidden();
        $this->assertSame('awaiting', $order->fresh()->payment_confirmation);
    }

    public function test_cash_on_delivery_creates_a_normal_pending_order(): void
    {
        [, $store, $product] = $this->seller();

        $this->postJson(route('store.checkout', $store->slug), $this->cart($product, ['payment_method' => 'manual_cod']))->assertOk();

        $order = Order::sole();
        $this->assertNull($order->payment_confirmation);
        $this->assertSame('manual_cod', $order->payment_method);
        $this->assertSame('Pago contra entrega', $order->payment_method_label);
    }

    public function test_a_method_the_store_did_not_enable_is_refused(): void
    {
        [, $store, $product] = $this->seller();

        $this->postJson(route('store.checkout', $store->slug), $this->cart($product, ['payment_method' => 'manual_plin', 'payment_done' => true]))
            ->assertStatus(422);
        $this->assertSame(0, Order::count());
    }

    public function test_delivery_coverage_limits_orders_to_the_chosen_departments(): void
    {
        [$owner, $store, $product] = $this->seller();

        $this->actingAs($owner)->post(route('dashboard.delivery-coverage.update'), ['departments' => ['Lima'], 'exchange_hours' => 48])
            ->assertSessionHasNoErrors();
        $this->assertSame(['Lima'], $store->fresh()->delivery_coverage);
        $this->assertSame(48, $store->fresh()->exchange_hours);

        $this->postJson(route('store.checkout', $store->slug), $this->cart($product, ['payment_method' => 'manual_cod', 'customer_state' => 'Cusco']))
            ->assertStatus(422)->assertJsonFragment(['error' => 'Por ahora solo realizamos envíos a: Lima.']);
        $this->postJson(route('store.checkout', $store->slug), $this->cart($product, ['payment_method' => 'manual_cod']))->assertOk();

        $this->get(route('store.product', [$store->slug, $product->slug]))->assertOk()
            ->assertSee('solo a Lima', false)->assertSee('Cambios dentro de las 48 horas');
    }

    public function test_footer_only_advertises_the_payment_methods_the_store_accepts(): void
    {
        [, $store] = $this->seller();

        $html = $this->get(route('store.show', $store->slug))->assertOk()->getContent();
        $this->assertStringContainsString('alt="Yape"', $html);
        $this->assertStringNotContainsString('aria-label="Visa"', $html);
    }
}
