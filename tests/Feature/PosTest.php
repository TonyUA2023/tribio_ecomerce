<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use App\Models\Register;
use App\Models\RegisterSession;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/** ERP Fase 4a: registers, shifts and counter sales. */
class PosTest extends TestCase
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
        config(['app.url' => 'http://localhost']);
    }

    private function owner(string $slug = 'nutri'): array
    {
        $user = User::factory()->create(['role' => 'store_owner']);
        $store = Store::create(['user_id' => $user->id, 'name' => 'Nutri Vida', 'slug' => $slug, 'status' => 'active', 'template_name' => 'soft-market', 'address' => 'Av. Grau 123']);

        return [$user, $store];
    }

    /** Batido (S/ 10, stock 5) and Barra (S/ 2 each, box of 12, S/ 1.50 each from 12). */
    private function catalog(Store $store): array
    {
        $batido = Product::create(['store_id' => $store->id, 'name' => 'Batido de fresa', 'slug' => 'batido', 'sku' => 'BAT-01', 'price' => 10, 'stock' => 5, 'track_stock' => true, 'is_active' => true]);
        $barra = Product::create(['store_id' => $store->id, 'name' => 'Barra proteica', 'slug' => 'barra', 'price' => 2, 'stock' => 50, 'track_stock' => true, 'is_active' => true,
            'price_tiers' => [['min_qty' => 12, 'price' => 1.5]]]);
        $box = ProductUnit::create(['product_id' => $barra->id, 'name' => 'Caja', 'factor' => 12, 'barcode' => '7750001112223']);

        return [$batido, $barra, $box];
    }

    private function openShift(User $user, Store $store, float $float = 100): RegisterSession
    {
        $this->actingAs($user)->get(route('dashboard.pos.index'))->assertOk()->assertSee('Caja principal');
        $register = Register::where('store_id', $store->id)->sole();
        $this->actingAs($user)->post(route('dashboard.pos.registers.open', $register->id), ['opening_float' => $float])->assertRedirect();

        return RegisterSession::where('register_id', $register->id)->sole();
    }

    private function sale(User $user, RegisterSession $session, array $data)
    {
        return $this->actingAs($user)->postJson(route('dashboard.pos.sell', $session->id), $data + ['uuid' => (string) Str::uuid()]);
    }

    public function test_a_register_is_opened_once_and_shows_the_sale_screen(): void
    {
        [$user, $store] = $this->owner();
        $session = $this->openShift($user, $store, 80);

        $this->assertSame(['open', 80.0, $user->id], [$session->status, (float) $session->opening_float, $session->opened_by]);
        $this->actingAs($user)->post(route('dashboard.pos.registers.open', $session->register_id), ['opening_float' => 10])
            ->assertSessionHasErrors(['opening_float' => '«Caja principal» ya está abierta.']);
        $this->actingAs($user)->get(route('dashboard.pos.index'))->assertOk()->assertSee('Busca o escanea un código')->assertSee('Cerrar caja');
        $this->actingAs($user)->get(route('dashboard.index'))->assertSee(route('dashboard.pos.index'));
    }

    public function test_a_counter_sale_prices_packagings_and_tiers_moves_stock_and_gives_change(): void
    {
        [$user, $store] = $this->owner();
        [$batido, $barra, $box] = $this->catalog($store);
        $session = $this->openShift($user, $store);
        $uuid = (string) Str::uuid();
        $payload = ['uuid' => $uuid, 'items' => [
            ['product_id' => $batido->id, 'quantity' => 2],
            ['product_id' => $barra->id, 'unit_id' => $box->id, 'quantity' => 1],
        ], 'discount' => 1, 'payments' => [['method' => 'cash', 'amount' => 50]]];

        $this->actingAs($user)->postJson(route('dashboard.pos.sell', $session->id), $payload)->assertCreated()
            ->assertJson(['total' => 37, 'change' => 13, 'duplicate' => false, 'whatsapp_url' => null]);

        $order = Order::sole();
        $this->assertSame(['pos', 'delivered', 'paid', 'Cliente varios', $session->id, 38.0, 1.0, 37.0, 50.0, 13.0],
            [$order->source, $order->status, $order->payment_status, $order->customer_name, $order->register_session_id,
                (float) $order->subtotal, (float) $order->discount, (float) $order->total, (float) $order->cash_tendered, (float) $order->change_given]);
        $boxLine = $order->items->firstWhere('product_id', $barra->id);
        $this->assertSame([12, 'Caja', 1, 18.0], [$boxLine->quantity, $boxLine->unit_name, $boxLine->unit_quantity, (float) $boxLine->subtotal], 'Box of 12 at the S/ 1.50 tier');
        $this->assertSame(['cash', $session->id, 37.0], [$order->payments->sole()->method, $order->payments->sole()->register_session_id, (float) $order->payments->sole()->amount]);
        $this->assertSame([3, 38], [$batido->fresh()->stock, $barra->fresh()->stock]);
        $this->assertNull($order->contact_id);
        Mail::assertNothingQueued();

        // A retry with the same id returns the same sale and moves nothing again.
        $this->actingAs($user)->postJson(route('dashboard.pos.sell', $session->id), $payload)->assertOk()->assertJson(['duplicate' => true, 'order_id' => $order->id]);
        $this->assertSame([1, 3], [Order::count(), $batido->fresh()->stock]);

        $this->actingAs($user)->get(route('dashboard.pos.receipt', $order->id))->assertOk()
            ->assertSee('NOTA DE VENTA')->assertSee($order->order_number)->assertSee('1 Caja × 18.00')->assertSee('Vuelto')->assertSee('13.00');
        $this->actingAs($user)->get(route('dashboard.pedidos.index'))->assertOk()->assertSee('Caja');
    }

    public function test_split_payments_and_their_limits(): void
    {
        [$user, $store] = $this->owner();
        [$batido] = $this->catalog($store);
        $session = $this->openShift($user, $store);
        $items = ['items' => [['product_id' => $batido->id, 'quantity' => 3]]];

        $this->sale($user, $session, $items + ['payments' => [['method' => 'yape', 'amount' => 35]]])
            ->assertUnprocessable()->assertJsonValidationErrors(['payments' => 'Con Yape, Plin, tarjeta o transferencia no se da vuelto: el monto supera el total.']);
        $this->sale($user, $session, $items + ['payments' => [['method' => 'yape', 'amount' => 20], ['method' => 'cash', 'amount' => 5]]])
            ->assertUnprocessable()->assertJsonValidationErrors(['payments' => 'Falta cobrar S/ 5.00.']);
        $this->assertSame([0, 5], [Order::count(), $batido->fresh()->stock], 'A refused sale moves nothing');

        $this->sale($user, $session, $items + ['payments' => [['method' => 'yape', 'amount' => 20], ['method' => 'cash', 'amount' => 20]]])
            ->assertCreated()->assertJson(['change' => 10]);
        $order = Order::sole();
        $this->assertSame(['mixto', ['cash' => 10.0, 'yape' => 20.0]], [$order->payment_method,
            $order->payments->sortBy('method')->mapWithKeys(fn ($p) => [$p->method => (float) $p->amount])->all()]);
    }

    public function test_the_scanner_finds_packagings_variants_and_skus_and_a_variant_must_be_chosen(): void
    {
        [$user, $store] = $this->owner();
        [$batido, $barra, $box] = $this->catalog($store);
        $polo = Product::create(['store_id' => $store->id, 'name' => 'Polo', 'slug' => 'polo', 'price' => 30, 'stock' => 4, 'track_stock' => true, 'is_active' => true, 'has_variants' => true]);
        $m = ProductVariant::create(['product_id' => $polo->id, 'sku' => 'POLO-M', 'price' => 30, 'stock' => 2, 'attributes' => ['Talla' => 'M'], 'is_active' => true]);
        ProductVariant::create(['product_id' => $polo->id, 'sku' => 'POLO-L', 'price' => 32, 'stock' => 2, 'attributes' => ['Talla' => 'L'], 'is_active' => true]);
        $session = $this->openShift($user, $store);
        $search = fn (string $q) => $this->actingAs($user)->getJson(route('dashboard.pos.products', ['q' => $q]))->assertOk();

        $search('7750001112223')->assertJsonPath('exact', ['product_id' => $barra->id, 'variant_id' => null, 'unit_id' => $box->id])
            ->assertJsonPath('products.0.units.0.factor', 12);
        $search('POLO-M')->assertJsonPath('exact.variant_id', $m->id)->assertJsonCount(2, 'products.0.variants');
        $search('BAT-01')->assertJsonPath('exact.product_id', $batido->id);
        $search('barr')->assertJsonPath('exact', null)->assertJsonPath('products.0.name', 'Barra proteica')
            ->assertJsonPath('products.0.tiers', [['min_qty' => 12, 'price' => 1.5]]);

        $this->sale($user, $session, ['items' => [['product_id' => $polo->id, 'quantity' => 1]], 'payments' => [['method' => 'card', 'amount' => 30]]])
            ->assertJsonValidationErrors(['items.0' => 'Elige una variante de «Polo».']);
        $this->sale($user, $session, ['items' => [['product_id' => $polo->id, 'variant_id' => $m->id, 'quantity' => 1]], 'payments' => [['method' => 'card', 'amount' => 30]]])
            ->assertCreated();
        $this->assertSame([1, 'M'], [$m->fresh()->stock, Order::sole()->items->sole()->variant_title]);
    }

    public function test_selling_more_than_the_stock_is_allowed_and_logged(): void
    {
        [$user, $store] = $this->owner();
        [$batido] = $this->catalog($store);
        $session = $this->openShift($user, $store);

        $this->sale($user, $session, ['items' => [['product_id' => $batido->id, 'quantity' => 7, 'price' => 9]], 'payments' => [['method' => 'cash', 'amount' => 63]]])
            ->assertCreated()->assertJson(['total' => 63, 'change' => 0]);
        $this->assertSame(0, $batido->fresh()->stock);
        $this->assertStringContainsString('faltaron 2', InventoryMovement::where('product_id', $batido->id)->where('type', 'sale')->sole()->reason);
    }

    public function test_the_customer_is_linked_or_created(): void
    {
        [$user, $store] = $this->owner();
        [$batido] = $this->catalog($store);
        $session = $this->openShift($user, $store);
        $rosa = Contact::create(['store_id' => $store->id, 'name' => 'Rosa Quispe', 'source' => 'manual']);
        $pay = ['items' => [['product_id' => $batido->id, 'quantity' => 1]], 'payments' => [['method' => 'cash', 'amount' => 10]]];

        $this->actingAs($user)->getJson(route('dashboard.pos.customers', ['q' => 'ros']))->assertJsonPath('0.id', $rosa->id);
        $this->sale($user, $session, $pay + ['contact_id' => $rosa->id])->assertCreated();
        $this->assertSame([$rosa->id, 'Rosa Quispe'], [Order::latest('id')->first()->contact_id, Order::latest('id')->first()->customer_name]);
        $this->assertSame([1, 10.0], [$rosa->fresh()->orders_count, (float) $rosa->fresh()->total_spent]);

        $this->sale($user, $session, $pay + ['customer_name' => 'Luis Rojas', 'customer_phone' => '987 333 444', 'customer_document' => '44556677'])
            ->assertCreated()->assertJsonPath('whatsapp_url', fn ($url) => str_starts_with($url, 'https://wa.me/51987333444?text='));
        $luis = Contact::where('name', 'Luis Rojas')->sole();
        $this->assertSame(['pos', '51987333444', '44556677'], [$luis->source, $luis->phone, $luis->document_number]);

        $this->sale($user, $session, $pay + ['customer_document' => '123'])->assertJsonValidationErrors('customer_document');
    }

    public function test_cash_movements_and_the_closing_count(): void
    {
        [$user, $store] = $this->owner();
        [$batido] = $this->catalog($store);
        $session = $this->openShift($user, $store, 100);
        $this->sale($user, $session, ['items' => [['product_id' => $batido->id, 'quantity' => 3]], 'payments' => [['method' => 'yape', 'amount' => 10], ['method' => 'cash', 'amount' => 50]]])->assertCreated();
        $movement = fn (string $type, $amount, string $reason = 'Pago de delivery') => $this->actingAs($user)
            ->post(route('dashboard.pos.sessions.movement', $session->id), ['type' => $type, 'amount' => $amount, 'reason' => $reason]);

        $movement('out', 15)->assertSessionHas('success');
        $movement('in', 5, 'Sencillo')->assertSessionHas('success');
        $movement('out', 500)->assertSessionHasErrors(['amount' => 'No hay tanto efectivo en la caja.']);

        // 100 float + 20 cash sale − 15 + 5 = 110 expected.
        $this->actingAs($user)->get(route('dashboard.pos.sessions.close', $session->id))->assertOk()->assertSee('S/ 110.00')->assertSee('Yape');
        $this->actingAs($user)->post(route('dashboard.pos.sessions.close.store', $session->id), ['counted_cash' => 108])
            ->assertRedirect(route('dashboard.pos.sessions.show', $session->id))->assertSessionHas('error', 'Caja cerrada con S/ 2.00 de menos.');

        $session->refresh();
        $this->assertSame(['closed', 110.0, 108.0, -2.0, 10.0, 20.0], [$session->status, (float) $session->expected_cash, (float) $session->counted_cash,
            (float) $session->difference, (float) $session->totals['methods']['yape'], (float) $session->totals['methods']['cash']]);

        $this->sale($user, $session, ['items' => [['product_id' => $batido->id, 'quantity' => 1]], 'payments' => [['method' => 'cash', 'amount' => 10]]])
            ->assertJsonValidationErrors(['session' => 'Este turno de caja ya se cerró. Abre la caja otra vez.']);
        $this->actingAs($user)->get(route('dashboard.pos.sessions.show', $session->id))->assertOk()->assertSee('Faltó S/ 2.00')->assertSee('Pago de delivery');
        $this->actingAs($user)->get(route('dashboard.pos.sessions.index'))->assertOk()->assertSee('Caja principal');
        $this->actingAs($user)->get(route('dashboard.pos.index'))->assertOk()->assertSee('Abrir caja')->assertSee('Faltó S/ 2.00');
    }

    public function test_another_stores_registers_sales_and_tickets_are_out_of_reach(): void
    {
        [$user, $store] = $this->owner();
        [$batido] = $this->catalog($store);
        $session = $this->openShift($user, $store);
        $this->sale($user, $session, ['items' => [['product_id' => $batido->id, 'quantity' => 1]], 'payments' => [['method' => 'cash', 'amount' => 10]]])->assertCreated();
        [$other, $otherStore] = $this->owner('otra');
        $foreign = Product::create(['store_id' => $otherStore->id, 'name' => 'Ajeno', 'slug' => 'ajeno', 'price' => 5, 'stock' => 5, 'track_stock' => true, 'is_active' => true]);

        $this->sale($other, $session, ['items' => [['product_id' => $batido->id, 'quantity' => 1]], 'payments' => [['method' => 'cash', 'amount' => 10]]])->assertNotFound();
        $this->actingAs($other)->get(route('dashboard.pos.receipt', Order::sole()->id))->assertNotFound();
        $this->actingAs($other)->post(route('dashboard.pos.registers.open', $session->register_id), ['opening_float' => 0])->assertNotFound();
        $this->actingAs($other)->get(route('dashboard.pos.sessions.show', $session->id))->assertNotFound();
        $this->sale($user, $session, ['items' => [['product_id' => $foreign->id, 'quantity' => 1]], 'payments' => [['method' => 'cash', 'amount' => 5]]])
            ->assertJsonValidationErrors(['items.0' => 'Ese producto ya no está a la venta.']);
        $this->actingAs($other)->getJson(route('dashboard.pos.products', ['q' => 'Batido']))->assertJsonCount(0, 'products');
    }
}
