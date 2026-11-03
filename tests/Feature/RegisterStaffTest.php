<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Order;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Register;
use App\Models\RegisterMovement;
use App\Models\RegisterSession;
use App\Models\Store;
use App\Models\StoreStaff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/** ERP Fase 4d: register staff with PIN, "modo caja", manager approvals and offline sales. */
class RegisterStaffTest extends TestCase
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

    /** Owner with an open register, a manager (PIN 9999) and a cashier (PIN 1111). */
    private function shop(string $slug = 'nutri'): array
    {
        $user = User::factory()->create(['role' => 'store_owner']);
        $store = Store::create(['user_id' => $user->id, 'name' => 'Nutri Vida', 'slug' => $slug, 'status' => 'active', 'template_name' => 'soft-market']);
        $batido = Product::create(['store_id' => $store->id, 'name' => 'Batido de fresa', 'slug' => 'batido-' . $slug, 'sku' => 'BAT-01', 'price' => 10, 'stock' => 50, 'track_stock' => true, 'is_active' => true]);
        $this->actingAs($user)->get(route('dashboard.pos.index'))->assertOk();
        $register = Register::where('store_id', $store->id)->sole();
        $this->actingAs($user)->post(route('dashboard.pos.registers.open', $register->id), ['opening_float' => 100]);
        $this->actingAs($user)->post(route('dashboard.pos.staff.store'), ['name' => 'Elena', 'role' => 'encargado', 'pin' => '9999', 'pin_confirmation' => '9999'])->assertSessionHas('success');
        $this->actingAs($user)->post(route('dashboard.pos.staff.store'), ['name' => 'Ana', 'role' => 'cajero', 'pin' => '1111', 'pin_confirmation' => '1111'])->assertSessionHas('success');

        return [$user, $store, $batido, RegisterSession::where('register_id', $register->id)->sole()];
    }

    private function kiosk(User $user, string $pin = '1111'): void
    {
        $this->actingAs($user)->post(route('dashboard.pos.kiosk.enable'))->assertRedirect(route('dashboard.pos.index'));
        $this->actingAs($user)->post(route('dashboard.pos.unlock'), ['pin' => $pin])->assertRedirect();
    }

    private function sale(User $user, RegisterSession $session, array $data)
    {
        return $this->actingAs($user)->postJson(route('dashboard.pos.sell', $session->id), $data + ['uuid' => (string) Str::uuid()]);
    }

    public function test_staff_get_hashed_unique_pins_and_the_mode_needs_a_manager(): void
    {
        $user = User::factory()->create(['role' => 'store_owner']);
        $store = Store::create(['user_id' => $user->id, 'name' => 'Nutri Vida', 'slug' => 'nutri', 'status' => 'active', 'template_name' => 'soft-market']);

        $this->actingAs($user)->post(route('dashboard.pos.staff.store'), ['name' => 'Ana', 'role' => 'cajero', 'pin' => '1111', 'pin_confirmation' => '1111'])->assertSessionHas('success');
        $this->assertNotSame('1111', StoreStaff::sole()->pin_hash);
        $this->actingAs($user)->post(route('dashboard.pos.staff.store'), ['name' => 'Luis', 'role' => 'cajero', 'pin' => '1111', 'pin_confirmation' => '1111'])
            ->assertSessionHasErrors(['pin' => 'Ese PIN ya lo usa otra persona de tu tienda. Elige otro.']);
        $this->actingAs($user)->post(route('dashboard.pos.staff.store'), ['name' => 'Luis', 'role' => 'cajero', 'pin' => '12', 'pin_confirmation' => '12'])->assertSessionHasErrors('pin');

        $this->actingAs($user)->post(route('dashboard.pos.kiosk.enable'))
            ->assertSessionHasErrors(['kiosk' => 'Primero agrega al menos un encargado: su PIN es el que permite salir del modo caja.']);
        $this->actingAs($user)->get(route('dashboard.pos.staff.index'))->assertOk()->assertSee('Ana')->assertSee('Primero agrega un encargado');
        $this->assertArrayNotHasKey('pin_hash', StoreStaff::sole()->toArray());
    }

    public function test_modo_caja_locks_the_dashboard_to_the_register_and_asks_for_a_pin(): void
    {
        [$user, , $batido, $session] = $this->shop();
        $this->actingAs($user)->post(route('dashboard.pos.kiosk.enable'))->assertRedirect(route('dashboard.pos.index'));

        $this->actingAs($user)->get(route('dashboard.pos.index'))->assertOk()->assertSee('Ingresa tu PIN para usar la caja');
        $this->actingAs($user)->get(route('dashboard.productos.index'))->assertRedirect(route('dashboard.pos.index'));
        $this->actingAs($user)->get(route('dashboard.pos.staff.index'))->assertRedirect(route('dashboard.pos.index'));
        $this->actingAs($user)->getJson(route('dashboard.pos.products', ['q' => 'bat']))->assertStatus(423)->assertJson(['locked' => true]);

        $this->actingAs($user)->post(route('dashboard.pos.unlock'), ['pin' => '0000'])->assertSessionHasErrors(['pin' => 'PIN incorrecto.']);
        $this->actingAs($user)->post(route('dashboard.pos.unlock'), ['pin' => '1111'])->assertSessionHas('success', 'Hola, Ana.');
        $this->actingAs($user)->get(route('dashboard.pos.index'))->assertOk()->assertSee('Ana')->assertSee('Busca o escanea un código');
        $this->actingAs($user)->get(route('dashboard.productos.index'))->assertRedirect(route('dashboard.pos.index'));

        // After 15 idle minutes the register locks itself.
        $this->travel(16)->minutes();
        $this->sale($user, $session, ['items' => [['product_id' => $batido->id, 'quantity' => 1]], 'payments' => [['method' => 'cash', 'amount' => 10]]])->assertStatus(423);

        // Leaving needs a manager's PIN, not a cashier's.
        $this->actingAs($user)->post(route('dashboard.pos.kiosk.disable'), ['pin' => '1111'])->assertSessionHasErrors(['pin' => 'Ese PIN no es de un encargado.']);
        $this->actingAs($user)->post(route('dashboard.pos.kiosk.disable'), ['pin' => '9999'])->assertRedirect(route('dashboard.pos.staff.index'));
        $this->actingAs($user)->get(route('dashboard.productos.index'))->assertOk();
    }

    public function test_wrong_pins_are_throttled(): void
    {
        [$user] = $this->shop();
        $this->actingAs($user)->post(route('dashboard.pos.kiosk.enable'));
        foreach (range(1, 5) as $try) {
            $this->actingAs($user)->post(route('dashboard.pos.unlock'), ['pin' => '000' . $try])->assertSessionHasErrors(['pin' => 'PIN incorrecto.']);
        }
        $this->actingAs($user)->post(route('dashboard.pos.unlock'), ['pin' => '1111'])->assertSessionHasErrors('pin');
        $this->assertStringStartsWith('Demasiados intentos.', session('errors')->first('pin'));
    }

    public function test_a_cashier_sells_at_list_price_and_needs_a_manager_for_price_discount_and_credit(): void
    {
        [$user, $store, $batido, $session] = $this->shop();
        $rosa = Contact::create(['store_id' => $store->id, 'name' => 'Rosa Quispe', 'phone' => '51987111222', 'source' => 'manual']);
        $this->kiosk($user);
        $items = ['items' => [['product_id' => $batido->id, 'quantity' => 2]]];

        $this->sale($user, $session, $items + ['payments' => [['method' => 'cash', 'amount' => 20]]])->assertCreated();
        $this->assertSame([StoreStaff::where('name', 'Ana')->value('id'), null], [Order::sole()->staff_id, Order::sole()->approved_staff_id]);
        $this->assertSame(StoreStaff::where('name', 'Ana')->value('id'), Order::sole()->payments->sole()->staff_id);

        $typed = ['items' => [['product_id' => $batido->id, 'quantity' => 2, 'price' => 8]], 'discount' => 1, 'payments' => [['method' => 'cash', 'amount' => 15]]];
        $this->sale($user, $session, $typed)->assertJsonValidationErrors(['approval' => 'Para cambiar el precio, hacer un descuento se necesita el PIN de un encargado.']);
        $this->sale($user, $session, $typed + ['approver_pin' => '1111'])->assertJsonValidationErrors(['pin' => 'Ese PIN no es de un encargado.']);
        $this->sale($user, $session, $typed + ['approver_pin' => '9999'])->assertCreated()->assertJson(['total' => 15]);
        $this->assertSame(StoreStaff::where('name', 'Elena')->value('id'), Order::latest('id')->first()->approved_staff_id);

        $this->sale($user, $session, $items + ['contact_id' => $rosa->id, 'credit' => ['installments' => 1]])
            ->assertJsonValidationErrors(['approval' => 'Para fiar se necesita el PIN de un encargado.']);
        $this->assertSame(2, Order::count());

        // A quote's agreed prices need no approval.
        $quote = Quote::create(['store_id' => $store->id, 'number' => 'PRO-2026-000001', 'status' => 'open', 'customer_name' => 'Rosa', 'items' => [[
            'product_id' => $batido->id, 'variant_id' => null, 'unit_id' => null, 'name' => 'Batido', 'unit_name' => null, 'quantity' => 2, 'price' => 9, 'subtotal' => 18]],
            'subtotal' => 18, 'discount' => 0, 'total' => 18, 'valid_until' => now()->addDays(7)]);
        $this->sale($user, $session, ['items' => [['product_id' => $batido->id, 'quantity' => 2, 'price' => 9]], 'quote_id' => $quote->id,
            'payments' => [['method' => 'cash', 'amount' => 18]]])->assertCreated();
    }

    public function test_cash_out_closing_returns_and_quote_cancel_need_a_manager(): void
    {
        [$user, $store, $batido, $session] = $this->shop();
        $this->sale($user, $session, ['items' => [['product_id' => $batido->id, 'quantity' => 2]], 'payments' => [['method' => 'cash', 'amount' => 20]]])->assertCreated();
        $order = Order::sole();
        $this->kiosk($user);

        $this->actingAs($user)->post(route('dashboard.pos.sessions.movement', $session->id), ['type' => 'in', 'amount' => 5, 'reason' => 'Sencillo'])->assertSessionHas('success');
        $this->actingAs($user)->post(route('dashboard.pos.sessions.movement', $session->id), ['type' => 'out', 'amount' => 5, 'reason' => 'Delivery'])
            ->assertSessionHasErrors(['approval' => 'Para retirar efectivo se necesita el PIN de un encargado.']);
        $this->actingAs($user)->post(route('dashboard.pos.sessions.movement', $session->id), ['type' => 'out', 'amount' => 5, 'reason' => 'Delivery', 'approver_pin' => '9999'])
            ->assertSessionHas('success');
        $this->assertSame([StoreStaff::where('name', 'Ana')->value('id'), StoreStaff::where('name', 'Elena')->value('id')],
            [RegisterMovement::latest('id')->first()->staff_id, RegisterMovement::latest('id')->first()->approved_staff_id]);

        $return = ['items' => [['order_item_id' => $order->items->sole()->id, 'quantity' => 1]], 'reason' => 'Vencido', 'method' => 'cash', 'register_session_id' => $session->id];
        $this->actingAs($user)->post(route('dashboard.returns.store', $order->id), $return)->assertSessionHasErrors('approval');
        $this->actingAs($user)->post(route('dashboard.returns.store', $order->id), $return + ['approver_pin' => '9999'])->assertSessionHas('success');

        $this->sale($user, $session, ['items' => [['product_id' => $batido->id, 'quantity' => 1]], 'payments' => [['method' => 'cash', 'amount' => 10]]])->assertCreated();
        $this->actingAs($user)->get(route('dashboard.pos.sessions.close', $session->id))->assertOk()->assertSee('PIN de un encargado');
        $this->actingAs($user)->post(route('dashboard.pos.sessions.close.store', $session->id), ['counted_cash' => 110])->assertSessionHasErrors('approval');
        $this->actingAs($user)->post(route('dashboard.pos.sessions.close.store', $session->id), ['counted_cash' => 120, 'approver_pin' => '9999'])->assertRedirect();
        $this->assertSame(['closed', StoreStaff::where('name', 'Ana')->value('id')], [$session->fresh()->status, $session->fresh()->closed_staff_id]);
        $this->actingAs($user)->get(route('dashboard.pos.sessions.show', $session->id))->assertOk()->assertSee('Por persona')->assertSee('Ana');
    }

    public function test_a_sale_rung_up_offline_keeps_its_time_and_never_sells_twice(): void
    {
        [$user, , $batido, $session] = $this->shop();
        $this->travelTo(Carbon::parse('2026-11-10 15:00:00'));
        $uuid = (string) Str::uuid();
        $body = ['uuid' => $uuid, 'items' => [['product_id' => $batido->id, 'quantity' => 1]], 'payments' => [['method' => 'cash', 'amount' => 10]],
            'offline_at' => '2026-11-10T13:20:00Z'];

        $this->actingAs($user)->postJson(route('dashboard.pos.sell', $session->id), $body)->assertCreated();
        $this->actingAs($user)->postJson(route('dashboard.pos.sell', $session->id), $body)->assertOk()->assertJson(['duplicate' => true]);
        $this->assertSame(['2026-11-10 13:20:00', 49], [Order::sole()->created_at->utc()->format('Y-m-d H:i:s'), $batido->fresh()->stock]);

        // A clock far in the past is not trusted.
        $this->actingAs($user)->postJson(route('dashboard.pos.sell', $session->id), ['offline_at' => '2026-10-01T10:00:00Z'] + array_merge($body, ['uuid' => (string) Str::uuid()]))->assertCreated();
        $this->assertSame('2026-11-10 15:00:00', Order::latest('id')->first()->created_at->utc()->format('Y-m-d H:i:s'));

        $this->actingAs($user)->getJson(route('dashboard.pos.catalog'))->assertOk()->assertJsonPath('products.0.sku', 'BAT-01');
        $this->actingAs($user)->getJson(route('dashboard.pos.token'))->assertOk()->assertJsonStructure(['token']);
        $this->assertFileExists(public_path('pos-sw.js'));
        $this->assertFileExists(public_path('pos/offline.js'));
    }

    public function test_another_stores_staff_is_out_of_reach(): void
    {
        [$user] = $this->shop();
        [$other] = $this->shop('otra');
        $ana = StoreStaff::where('name', 'Ana')->first();

        $this->actingAs($other)->put(route('dashboard.pos.staff.update', $ana->id), ['role' => 'encargado'])->assertNotFound();
        $this->actingAs($other)->post(route('dashboard.pos.kiosk.enable'));
        $this->actingAs($other)->post(route('dashboard.pos.unlock'), ['pin' => '1111'])->assertSessionHas('success');
        $this->assertSame('cajero', $ana->fresh()->role);
        $this->assertSame(2, StoreStaff::where('name', 'Ana')->count(), 'Each store has its own Ana with PIN 1111');
    }
}
