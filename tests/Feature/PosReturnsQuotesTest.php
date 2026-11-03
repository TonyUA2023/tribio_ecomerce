<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\ProductListPrice;
use App\Models\Quote;
use App\Models\Register;
use App\Models\RegisterSession;
use App\Models\Store;
use App\Models\User;
use App\Services\Pos\ReceivableService;
use App\Services\Pos\RegisterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/** ERP Fase 4c: price lists, quotes ("proformas") and returns of counter sales. */
class PosReturnsQuotesTest extends TestCase
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
        $this->travelTo(Carbon::parse('2026-11-10 15:00:00', 'America/Lima'));
    }

    private function owner(string $slug = 'nutri'): array
    {
        $user = User::factory()->create(['role' => 'store_owner']);
        $store = Store::create(['user_id' => $user->id, 'name' => 'Nutri Vida', 'slug' => $slug, 'status' => 'active', 'template_name' => 'soft-market']);
        $batido = Product::create(['store_id' => $store->id, 'name' => 'Batido de fresa', 'slug' => 'batido-' . $slug, 'price' => 10, 'stock' => 50, 'track_stock' => true, 'is_active' => true,
            'price_tiers' => [['min_qty' => 12, 'price' => 7]]]);
        $rosa = Contact::create(['store_id' => $store->id, 'name' => 'Rosa Quispe', 'phone' => '51987111222', 'source' => 'manual']);

        return [$user, $store, $batido, $rosa];
    }

    private function shift(User $user, Store $store): RegisterSession
    {
        $this->actingAs($user)->get(route('dashboard.pos.index'))->assertOk();
        $register = Register::where('store_id', $store->id)->sole();
        $this->actingAs($user)->post(route('dashboard.pos.registers.open', $register->id), ['opening_float' => 100]);

        return RegisterSession::where('register_id', $register->id)->sole();
    }

    private function sale(User $user, RegisterSession $session, array $data)
    {
        return $this->actingAs($user)->postJson(route('dashboard.pos.sell', $session->id), $data + ['uuid' => (string) Str::uuid()]);
    }

    public function test_price_lists_follow_the_customer_and_a_cheaper_tier_wins(): void
    {
        [$user, $store, $batido, $rosa] = $this->owner();
        $rosa->forceFill(['relationship' => 'mayorista'])->save();
        $session = $this->shift($user, $store);

        $this->actingAs($user)->put(route('dashboard.price-lists.update'), ['rows' => [$batido->id => ['mayorista' => '8', 'distribuidor' => '']]])->assertSessionHas('success');
        $this->assertSame(8.0, (float) ProductListPrice::where('product_id', $batido->id)->where('list', 'mayorista')->value('price'));
        $this->actingAs($user)->post(route('dashboard.price-lists.percent'), ['list' => 'distribuidor', 'percent' => 25])->assertSessionHas('success', 'Pusimos precio a 1 producto.');
        $this->actingAs($user)->getJson(route('dashboard.pos.products', ['q' => 'batido']))->assertJsonPath('products.0.lists.mayorista', 8)->assertJsonPath('products.0.lists.distribuidor', 7.5);
        $this->actingAs($user)->get(route('dashboard.price-lists.index'))->assertOk()->assertSee('Batido de fresa')->assertSee('7.50');

        $total = fn (array $extra, int $qty = 2) => $this->sale($user, $session, ['items' => [['product_id' => $batido->id, 'quantity' => $qty]],
            'payments' => [['method' => 'cash', 'amount' => 500]]] + $extra)->assertCreated()->json('total');
        $this->assertSame(16, $total(['contact_id' => $rosa->id]), 'Her relationship picks the mayorista list');
        $this->assertSame(20, $total(['contact_id' => $rosa->id, 'price_list' => 'menor']), 'The cashier can switch back to retail');
        $this->assertSame(15, $total(['price_list' => 'distribuidor']));
        $this->assertSame(84, $total(['contact_id' => $rosa->id], 12), 'From 12 units the S/ 7 tier is cheaper than S/ 8');
    }

    public function test_a_quote_is_saved_printed_and_charged_later_at_its_prices(): void
    {
        [$user, $store, $batido, $rosa] = $this->owner();
        $session = $this->shift($user, $store);

        $this->actingAs($user)->postJson(route('dashboard.quotes.store'), ['items' => [['product_id' => $batido->id, 'quantity' => 3]], 'discount' => 2,
            'contact_id' => $rosa->id, 'valid_days' => 15])->assertCreated()
            ->assertJson(['number' => 'PRO-2026-000001', 'total' => 28, 'valid_until' => '25/11/2026'])
            ->assertJsonPath('whatsapp_url', fn ($url) => str_starts_with($url, 'https://wa.me/51987111222?text=Proforma%20PRO-2026-000001'));
        $quote = Quote::sole();
        $this->assertSame(['open', 'Rosa Quispe', 10.0], [$quote->status, $quote->customer_name, (float) $quote->items[0]['price']]);
        $this->assertSame(50, $batido->fresh()->stock, 'A quote reserves nothing');

        $this->actingAs($user)->get(route('dashboard.quotes.show', $quote->id))->assertOk()->assertSee('PROFORMA')->assertSee('25/11/2026')->assertSee('28.00');
        $this->actingAs($user)->get(route('dashboard.quotes.index'))->assertOk()->assertSee('PRO-2026-000001')->assertSee('Cobrar en caja');

        // The price goes up; the register loads the quote and charges what was offered.
        $batido->update(['price' => 12]);
        $this->actingAs($user)->get(route('dashboard.pos.index', ['proforma' => $quote->id]))->assertOk()->assertSee('PRO-2026-000001');
        $this->sale($user, $session, ['items' => [['product_id' => $batido->id, 'quantity' => 3, 'price' => 10]], 'discount' => 2, 'contact_id' => $rosa->id,
            'quote_id' => $quote->id, 'payments' => [['method' => 'yape', 'amount' => 28]]])->assertCreated()->assertJson(['total' => 28]);
        $this->assertSame(['sold', Order::sole()->id], [$quote->fresh()->status, $quote->fresh()->order_id]);

        $this->actingAs($user)->postJson(route('dashboard.quotes.store'), ['items' => [['product_id' => $batido->id, 'quantity' => 1]]])
            ->assertCreated()->assertJson(['number' => 'PRO-2026-000002', 'whatsapp_url' => null]);
        $second = Quote::latest('id')->first();
        $this->actingAs($user)->post(route('dashboard.quotes.cancel', $second->id))->assertSessionHas('success');
        $this->assertSame(['cancelled', 'Cliente varios'], [$second->fresh()->status, $second->customer_name]);

        $this->travelTo(Carbon::parse('2026-12-01 10:00:00', 'America/Lima'));
        $this->actingAs($user)->postJson(route('dashboard.quotes.store'), ['items' => [['product_id' => $batido->id, 'quantity' => 1]], 'valid_days' => 1])->assertCreated();
        $this->travelTo(Carbon::parse('2026-12-03 10:00:00', 'America/Lima'));
        $this->assertSame('expired', Quote::latest('id')->first()->state);
    }

    public function test_a_return_restocks_refunds_from_the_register_and_closes_the_sale_when_complete(): void
    {
        [$user, $store, $batido, $rosa] = $this->owner();
        $session = $this->shift($user, $store);
        $this->sale($user, $session, ['items' => [['product_id' => $batido->id, 'quantity' => 3]], 'contact_id' => $rosa->id,
            'payments' => [['method' => 'cash', 'amount' => 30]]])->assertCreated();
        $order = Order::sole();
        $item = $order->items->sole();
        $return = fn (array $items, array $extra = []) => $this->actingAs($user)->post(route('dashboard.returns.store', $order->id), [
            'items' => $items, 'reason' => 'Llegó abierto', 'method' => 'cash', 'register_session_id' => $session->id] + $extra);

        $this->actingAs($user)->get(route('dashboard.returns.create', $order->id))->assertOk()->assertSee('Batido de fresa')->assertSee('Vuelve al stock');
        $return([['order_item_id' => $item->id, 'quantity' => 1, 'restock' => 1]])->assertRedirect()->assertSessionHas('success', 'Devolución registrada: entrega S/ 10.00 al cliente.');

        $note = OrderReturn::sole();
        $this->assertSame(['DEV-2026-000001', 10.0, 10.0, 0.0], [$note->number, (float) $note->total, (float) $note->refunded, (float) $note->debt_reduced]);
        $order->refresh();
        $this->assertSame([10.0, 20.0, 0.0, 'delivered'], [(float) $order->returned_total, (float) $order->amount_paid, (float) $order->balance_due, $order->status]);
        $this->assertSame([48, 'return'], [$batido->fresh()->stock, InventoryMovement::where('reference', 'DEV-2026-000001')->sole()->type]);
        $summary = app(RegisterService::class)->summary($session);
        $this->assertSame([120.0, 10.0], [$summary['expected_cash'], $summary['refunds']], '100 float + 30 sale − 10 back');
        $this->assertSame(20.0, (float) $rosa->fresh()->total_spent);
        $this->actingAs($user)->get(route('dashboard.returns.show', $note->id))->assertOk()->assertSee('NOTA DE DEVOLUCIÓN')->assertSee('Devuelto (Efectivo)');

        $return([['order_item_id' => $item->id, 'quantity' => 3]])->assertSessionHasErrors(['items' => 'De «Batido de fresa» se pueden devolver 2 und. como máximo.']);
        $return([['order_item_id' => $item->id, 'quantity' => 2, 'restock' => 0]], ['reason' => 'Vencidos'])->assertSessionHas('success');
        $this->assertSame(['refunded', 48, 0.0], [$order->fresh()->status, $batido->fresh()->stock, (float) $order->fresh()->amount_paid]);
        $this->actingAs($user)->post(route('dashboard.returns.store', $order->id), ['items' => [['order_item_id' => $item->id, 'quantity' => 1]], 'reason' => 'x'])
            ->assertSessionHasErrors(['items' => 'Esta venta no admite devoluciones.']);
    }

    public function test_a_return_on_credit_lowers_the_debt_first_and_shares_the_discount(): void
    {
        [$user, $store, $batido, $rosa] = $this->owner();
        $session = $this->shift($user, $store);
        $this->sale($user, $session, ['items' => [['product_id' => $batido->id, 'quantity' => 4]], 'discount' => 4, 'contact_id' => $rosa->id,
            'payments' => [['method' => 'cash', 'amount' => 6]], 'credit' => ['installments' => 2, 'every_days' => 15]])->assertCreated();
        $order = Order::sole();
        $this->assertSame([36.0, 30.0], [(float) $order->total, (float) $order->balance_due]);

        // 1 of 4 units, with its share of the S/ 4 discount: S/ 9.
        $this->actingAs($user)->post(route('dashboard.returns.store', $order->id), ['items' => [['order_item_id' => $order->items->sole()->id, 'quantity' => 1]],
            'reason' => 'Sabor equivocado'])->assertSessionHas('success', 'Devolución registrada: se descontó de lo que el cliente debía.');

        $note = OrderReturn::sole();
        $this->assertSame([9.0, 9.0, 0.0, null], [(float) $note->total, (float) $note->debt_reduced, (float) $note->refunded, $note->method]);
        $this->assertSame(21.0, (float) $order->fresh()->balance_due);
        $schedule = app(ReceivableService::class)->schedule($order->fresh()->load('installments'));
        $this->assertSame([[15.0, 6.0], [15.0, 15.0]], $schedule->map(fn ($row) => [$row['amount'], $row['pending']])->all(), 'The return covers the first installment first');
        $this->assertSame(21.0, app(ReceivableService::class)->owedBy($rosa));
    }

    public function test_another_stores_quotes_returns_and_prices_are_out_of_reach(): void
    {
        [$user, $store, $batido, $rosa] = $this->owner();
        $session = $this->shift($user, $store);
        $this->sale($user, $session, ['items' => [['product_id' => $batido->id, 'quantity' => 1]], 'payments' => [['method' => 'cash', 'amount' => 10]]])->assertCreated();
        $this->actingAs($user)->postJson(route('dashboard.quotes.store'), ['items' => [['product_id' => $batido->id, 'quantity' => 1]]])->assertCreated();
        [$other, $otherStore, $otherProduct] = $this->owner('otra');
        $order = Order::sole();

        $this->actingAs($other)->get(route('dashboard.quotes.show', Quote::sole()->id))->assertNotFound();
        $this->actingAs($other)->post(route('dashboard.quotes.cancel', Quote::sole()->id))->assertNotFound();
        $this->actingAs($other)->get(route('dashboard.returns.create', $order->id))->assertNotFound();
        $this->actingAs($other)->post(route('dashboard.returns.store', $order->id), ['items' => [['order_item_id' => $order->items->sole()->id, 'quantity' => 1]], 'reason' => 'x'])->assertNotFound();
        $this->actingAs($other)->put(route('dashboard.price-lists.update'), ['rows' => [$batido->id => ['mayorista' => '1']]])->assertSessionHas('success');
        $this->assertSame(0, ProductListPrice::count(), 'Another store cannot price this product');
        $this->actingAs($other)->postJson(route('dashboard.quotes.store'), ['items' => [['product_id' => $batido->id, 'quantity' => 1]]])
            ->assertJsonValidationErrors(['items.0' => 'Ese producto ya no está a la venta.']);

        // A web order cannot be returned from the register.
        $web = Order::create(['store_id' => $store->id, 'order_number' => 'WEB-1', 'customer_name' => 'Ana', 'subtotal' => 10, 'total' => 10, 'currency' => 'PEN', 'status' => 'delivered', 'payment_status' => 'paid']);
        $this->actingAs($user)->get(route('dashboard.returns.create', $web->id))->assertNotFound();
        $this->actingAs($user)->get(route('dashboard.returns.index', ['venta' => '000001']))->assertRedirect(route('dashboard.returns.create', $order->id));
    }
}
