<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactEvent;
use App\Models\Order;
use App\Models\Product;
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

/** ERP Fase 4b: credit sales ("fiado"), installments, limits and collections. */
class CreditSalesTest extends TestCase
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
        $batido = Product::create(['store_id' => $store->id, 'name' => 'Batido de fresa', 'slug' => 'batido-' . $slug, 'price' => 10, 'stock' => 50, 'track_stock' => true, 'is_active' => true]);
        $rosa = Contact::create(['store_id' => $store->id, 'name' => 'Rosa Quispe', 'phone' => '51987111222', 'source' => 'manual']);

        return [$user, $store, $batido, $rosa];
    }

    private function shift(User $user, Store $store): RegisterSession
    {
        $this->actingAs($user)->get(route('dashboard.pos.index'))->assertOk();
        $register = Register::where('store_id', $store->id)->sole();
        $this->actingAs($user)->post(route('dashboard.pos.registers.open', $register->id), ['opening_float' => 50]);

        return RegisterSession::where('register_id', $register->id)->sole();
    }

    private function sale(User $user, RegisterSession $session, array $data)
    {
        return $this->actingAs($user)->postJson(route('dashboard.pos.sell', $session->id), $data + ['uuid' => (string) Str::uuid()]);
    }

    private function collect(User $user, Contact $contact, array $data)
    {
        return $this->actingAs($user)->post(route('dashboard.receivables.collect', $contact->id), $data + ['uuid' => (string) Str::uuid(), 'method' => 'cash']);
    }

    public function test_installments_split_evenly_and_the_last_takes_the_cents(): void
    {
        $plan = app(ReceivableService::class)->plan(100, Carbon::parse('2026-11-20'), 3, 15);

        $this->assertSame([[1, '2026-11-20', 33.33], [2, '2026-12-05', 33.33], [3, '2026-12-20', 33.34]],
            array_map(fn ($i) => [$i['number'], $i['due_date'], $i['amount']], $plan));
    }

    public function test_a_credit_sale_leaves_the_rest_owed_in_installments(): void
    {
        [$user, $store, $batido, $rosa] = $this->owner();
        $session = $this->shift($user, $store);

        $this->sale($user, $session, ['items' => [['product_id' => $batido->id, 'quantity' => 3]], 'contact_id' => $rosa->id,
            'payments' => [['method' => 'cash', 'amount' => 10]], 'credit' => ['installments' => 2, 'every_days' => 15, 'first_due' => '2026-11-17']])
            ->assertCreated()->assertJson(['total' => 30, 'owed' => 20, 'change' => 0]);

        $order = Order::sole();
        $this->assertSame(['partial', 20.0, '2026-12-02', $rosa->id], [$order->payment_status, (float) $order->balance_due, $order->due_date->toDateString(), $order->contact_id]);
        $this->assertSame([['2026-11-17', 10.0], ['2026-12-02', 10.0]], $order->installments->map(fn ($i) => [$i->due_date->toDateString(), (float) $i->amount])->all());
        $this->assertTrue(ContactEvent::where('contact_id', $rosa->id)->where('type', 'credit')->where('body', 'like', 'Compró al crédito%quedó debiendo S/ 20.00 en 2 cuotas, desde el 17/11/2026.')->exists());
        $this->assertSame([20.0, 10.0], [app(RegisterService::class)->summary($session)['credit'], app(RegisterService::class)->summary($session)['methods']['cash']]);

        $this->actingAs($user)->get(route('dashboard.pos.receipt', $order->id))->assertOk()->assertSee('AL CRÉDITO')->assertSee('Debe 20.00')->assertSee('Cuota 2 · 02/12/2026');
        $this->actingAs($user)->get(route('dashboard.clientes.show', $rosa->id))->assertOk()->assertSee('Debe S/ 20.00');
    }

    public function test_credit_needs_a_known_customer_and_respects_the_limit(): void
    {
        [$user, $store, $batido, $rosa] = $this->owner();
        $session = $this->shift($user, $store);
        $items = ['items' => [['product_id' => $batido->id, 'quantity' => 2]]];

        $this->sale($user, $session, $items + ['payments' => [], 'credit' => ['installments' => 1]])
            ->assertJsonValidationErrors(['credit' => 'Para fiar, elige al cliente o anota su nombre y su celular o DNI.']);
        $this->sale($user, $session, $items + ['contact_id' => $rosa->id, 'payments' => [['method' => 'cash', 'amount' => 5]]])
            ->assertJsonValidationErrors(['payments' => 'Falta cobrar S/ 15.00.']);
        $this->sale($user, $session, $items + ['contact_id' => $rosa->id, 'credit' => ['first_due' => '2026-11-01']])
            ->assertJsonValidationErrors(['credit' => 'La fecha de pago no puede ser anterior a hoy.']);

        $this->actingAs($user)->put(route('dashboard.receivables.limit', $rosa->id), ['credit_limit' => 25])->assertSessionHas('success');
        $this->sale($user, $session, $items + ['contact_id' => $rosa->id, 'credit' => ['installments' => 1]])->assertCreated();
        $this->sale($user, $session, $items + ['contact_id' => $rosa->id, 'credit' => ['installments' => 1]])
            ->assertJsonValidationErrors(['credit' => 'Rosa Quispe ya debe S/ 20.00 y su límite de crédito es S/ 25.00: puede quedar debiendo hasta S/ 5.00.']);
        $this->assertSame(1, Order::count());

        // A new customer at the counter, nothing paid: the resolver creates the contact.
        $this->sale($user, $session, $items + ['customer_name' => 'Luis Rojas', 'customer_phone' => '987333444', 'credit' => ['installments' => 1]])->assertCreated();
        $luis = Contact::where('name', 'Luis Rojas')->sole();
        $this->assertSame(['pending', 20.0, '2026-11-25'], [Order::where('contact_id', $luis->id)->sole()->payment_status,
            (float) Order::where('contact_id', $luis->id)->sole()->balance_due, Order::where('contact_id', $luis->id)->sole()->due_date->toDateString()]);
    }

    public function test_receivables_show_aging_and_overdue_customers(): void
    {
        [$user, $store, $batido, $rosa] = $this->owner();
        $session = $this->shift($user, $store);
        $this->sale($user, $session, ['items' => [['product_id' => $batido->id, 'quantity' => 3]], 'contact_id' => $rosa->id,
            'credit' => ['installments' => 3, 'every_days' => 30, 'first_due' => '2026-11-15']])->assertCreated();

        $this->actingAs($user)->get(route('dashboard.receivables.index'))->assertOk()->assertSee('Rosa Quispe')->assertSee('Al día')->assertSee('S/ 30.00');

        $this->travelTo(Carbon::parse('2026-12-20 10:00:00', 'America/Lima'));
        $summary = app(ReceivableService::class)->summary($store);
        $this->assertSame([30.0, 20.0, 1, ['al_dia' => 10.0, '1_30' => 10.0, '31_60' => 10.0, '61_90' => 0.0, '90_mas' => 0.0]],
            [$summary['owed'], $summary['overdue'], $summary['late'], $summary['aging']]);
        $this->actingAs($user)->get(route('dashboard.receivables.index', ['ver' => 'vencidos']))->assertOk()->assertSee('Vencido S/ 20.00 · 35 días');
        $this->actingAs($user)->get(route('dashboard.receivables.show', $rosa->id))->assertOk()
            ->assertSee('Vencida · 35 d')->assertSee('Recordar por WhatsApp')->assertSee('https://wa.me/51987111222?text=', false);
    }

    public function test_a_collection_pays_the_oldest_debt_first_and_counts_in_the_register(): void
    {
        [$user, $store, $batido, $rosa] = $this->owner();
        $session = $this->shift($user, $store);
        foreach ([3, 2] as $quantity) {
            $this->sale($user, $session, ['items' => [['product_id' => $batido->id, 'quantity' => $quantity]], 'contact_id' => $rosa->id, 'credit' => ['installments' => 1]])->assertCreated();
        }
        [$first, $second] = Order::orderBy('id')->get()->all();

        $this->collect($user, $rosa, ['amount' => 60])->assertSessionHasErrors(['amount' => 'Debe S/ 50.00: no se puede cobrar más que eso.']);
        $uuid = (string) Str::uuid();
        $this->collect($user, $rosa, ['amount' => 35, 'register_session_id' => $session->id, 'uuid' => $uuid])
            ->assertSessionHas('success', 'Cobraste S/ 35.00. Aún debe S/ 15.00.');
        $this->collect($user, $rosa, ['amount' => 35, 'register_session_id' => $session->id, 'uuid' => $uuid])->assertSessionHas('success', 'Ese cobro ya estaba registrado.');

        $this->assertSame([['paid', 0.0], ['partial', 15.0]], [[$first->fresh()->payment_status, (float) $first->fresh()->balance_due],
            [$second->fresh()->payment_status, (float) $second->fresh()->balance_due]]);
        $summary = app(RegisterService::class)->summary($session);
        $this->assertSame([35.0, 85.0, 50.0], [$summary['collected'], $summary['expected_cash'], $summary['credit']]);
        $this->assertTrue(ContactEvent::where('contact_id', $rosa->id)->where('body', 'Pagó S/ 35.00 de su deuda (Efectivo). Aún debe S/ 15.00.')->exists());

        // Paid outside the register by Yape: nothing changes in the drawer.
        $this->collect($user, $rosa, ['amount' => 15, 'method' => 'yape'])->assertSessionHas('success', 'Cobraste S/ 15.00. Ya no debe nada. 🎉');
        $this->assertSame([0.0, 85.0], [app(ReceivableService::class)->owedBy($rosa), app(RegisterService::class)->summary($session)['expected_cash']]);
        $this->collect($user, $rosa, ['amount' => 1])->assertSessionHasErrors(['amount' => 'Rosa Quispe no tiene deudas pendientes.']);

        // A closed register no longer takes money.
        app(RegisterService::class)->close($session->fresh(), 85, null, $user->id);
        $this->sale($user, RegisterSession::find($session->id), ['items' => [['product_id' => $batido->id, 'quantity' => 1]], 'contact_id' => $rosa->id, 'credit' => []])
            ->assertJsonValidationErrors('session');
    }

    public function test_another_stores_debts_are_out_of_reach(): void
    {
        [$user, $store, $batido, $rosa] = $this->owner();
        $session = $this->shift($user, $store);
        $this->sale($user, $session, ['items' => [['product_id' => $batido->id, 'quantity' => 1]], 'contact_id' => $rosa->id, 'credit' => ['installments' => 1]])->assertCreated();
        [$other] = $this->owner('otra');

        $this->actingAs($other)->get(route('dashboard.receivables.show', $rosa->id))->assertNotFound();
        $this->collect($other, $rosa, ['amount' => 5])->assertNotFound();
        $this->actingAs($other)->put(route('dashboard.receivables.limit', $rosa->id), ['credit_limit' => 1])->assertNotFound();
        $this->actingAs($other)->get(route('dashboard.receivables.index'))->assertOk()->assertDontSee('Rosa Quispe');
        $this->collect($user, $rosa, ['amount' => 5, 'register_session_id' => 999])->assertNotFound();
    }
}
