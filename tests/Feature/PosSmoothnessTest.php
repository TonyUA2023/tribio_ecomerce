<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\DocumentSeries;
use App\Models\Order;
use App\Models\Product;
use App\Models\Register;
use App\Models\RegisterSession;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Caja y sedes: numeración que nunca se repite, ventas livianas y una sede siempre a la vista. */
class PosSmoothnessTest extends TestCase
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
        $this->withoutVite();
    }

    private function owner(): array
    {
        $user = User::factory()->create(['role' => 'store_owner']);
        $store = Store::create(['user_id' => $user->id, 'name' => 'Ferretería Norte', 'slug' => 'ferre', 'status' => 'active', 'template_name' => 'soft-market',
            'address' => 'Av. Grau 123', 'ruc' => '20100070970', 'legal_name' => 'Ferretería Norte S.A.C.']);

        return [$user, $store];
    }

    private function products(Store $store, int $count): \Illuminate\Support\Collection
    {
        return collect(range(1, $count))->map(fn ($i) => Product::create(['store_id' => $store->id, 'name' => "Producto {$i}", 'slug' => "producto-{$i}", 'price' => 10 + $i, 'stock' => 100, 'track_stock' => true, 'is_active' => true]));
    }

    private function shift(User $user, Store $store, ?Register $register = null): RegisterSession
    {
        $this->actingAs($user)->get(route('dashboard.pos.index'))->assertOk();
        $register ??= Register::where('store_id', $store->id)->orderBy('id')->firstOrFail();
        $this->actingAs($user)->post(route('dashboard.pos.registers.open', $register->id), ['opening_float' => 50]);

        return RegisterSession::where('register_id', $register->id)->where('status', 'open')->firstOrFail();
    }

    private function sell(User $user, RegisterSession $session, array $items, array $extra = [])
    {
        $total = collect($items)->sum(fn ($i) => $i['price'] * $i['quantity']);

        return $this->actingAs($user)->postJson(route('dashboard.pos.sell', $session->id), $extra + [
            'uuid' => (string) Str::uuid(), 'items' => array_map(fn ($i) => ['product_id' => $i['id'], 'quantity' => $i['quantity']], $items), 'payments' => [['method' => 'cash', 'amount' => $total + 100]],
        ]);
    }

    public function test_numbering_goes_on_after_the_highest_number_issued_even_if_the_counter_fell_behind(): void
    {
        [$user, $store] = $this->owner();
        $cement = $this->products($store, 1)->first();
        $session = $this->shift($user, $store);

        $this->sell($user, $session, [['id' => $cement->id, 'price' => $cement->price, 'quantity' => 1]], ['document_type' => 'boleta'])->assertCreated();
        $this->sell($user, $session, [['id' => $cement->id, 'price' => $cement->price, 'quantity' => 1]], ['document_type' => 'boleta'])->assertCreated();
        // A restored backup / a series switched back: the counter says 0 but B001-1 and B001-2 exist.
        DocumentSeries::where('series', 'B001')->update(['last_number' => 0]);

        $this->sell($user, $session, [['id' => $cement->id, 'price' => $cement->price, 'quantity' => 1]], ['document_type' => 'boleta'])->assertCreated();
        $this->assertSame([1, 2, 3], Order::where('document_series', 'B001')->orderBy('document_number')->pluck('document_number')->all());
    }

    public function test_switching_a_series_to_one_used_before_continues_its_numbering_and_a_taken_code_is_refused(): void
    {
        [$user, $store] = $this->owner();
        $cement = $this->products($store, 1)->first();
        $session = $this->shift($user, $store);
        $this->sell($user, $session, [['id' => $cement->id, 'price' => $cement->price, 'quantity' => 1]], ['document_type' => 'boleta'])->assertCreated();   // B001-1

        $form = fn (string $boleta) => ['ruc' => '20100070970', 'series' => ['nota_venta' => 'NV01', 'boleta' => $boleta, 'factura' => 'F001']];
        $this->actingAs($user)->post(route('dashboard.pos.documents.update'), $form('B002'))->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('dashboard.pos.documents.update'), $form('B001'))->assertSessionHasNoErrors();
        $this->assertSame(1, DocumentSeries::where('series', 'B001')->value('last_number'), 'the old series resumes where it was, not at 0');

        $this->sell($user, $session, [['id' => $cement->id, 'price' => $cement->price, 'quantity' => 1]], ['document_type' => 'boleta'])->assertCreated();
        $this->assertSame([1, 2], Order::where('document_series', 'B001')->orderBy('document_number')->pluck('document_number')->all());

        // A code that a caja or a sede already uses cannot be taken by the business series.
        $this->actingAs($user)->get(route('dashboard.pos.documents.branches'))->assertOk();
        $main = Branch::where('store_id', $store->id)->firstOrFail();
        $this->actingAs($user)->post(route('dashboard.pos.documents.series.store'), ['type' => 'boleta', 'series' => 'B777', 'scope' => 'branch', 'branch_id' => $main->id])->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('dashboard.pos.documents.update'), $form('B777'))->assertSessionHasErrors('series.boleta');
    }

    public function test_a_sale_of_many_lines_does_not_repeat_work_per_line_and_stays_within_a_budget(): void
    {
        [$user, $store] = $this->owner();
        $all = $this->products($store, 12)->map(fn ($p) => ['id' => $p->id, 'price' => $p->price, 'quantity' => 2]);
        $session = $this->shift($user, $store);
        $this->sell($user, $session, $all->all())->assertCreated(); // warms up the first-sale work (legacy stock lots)

        $measure = function (array $items) use ($user, $session) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->sell($user, $session, $items)->assertCreated();
            $queries = collect(DB::getQueryLog());
            DB::disableQueryLog();

            return ['stores' => $queries->filter(fn ($q) => str_starts_with($q['query'], 'select * from "stores"'))->count(), 'total' => $queries->count()];
        };
        $few = $measure($all->take(2)->all());
        $many = $measure($all->all());

        $this->assertSame($few['stores'], $many['stores'], 'the store is loaded a fixed number of times, not once per line');
        $this->assertLessThan(200, $many['total'], 'twelve lines, one sale: about 14 queries per line (stock, lots, movements), not more');
    }

    public function test_every_caja_says_which_sede_it_sells_from_when_there_are_several(): void
    {
        [$user, $store] = $this->owner();
        $cement = $this->products($store, 1)->first();

        // One sede: nothing extra on screen.
        $session = $this->shift($user, $store);
        $this->actingAs($user)->get(route('dashboard.pos.index'))->assertOk()->assertDontSee('pos-sede', false);
        $this->actingAs($user)->get(route('dashboard.pos.index', ['cajas' => 1]))->assertOk()->assertDontSee('📍');

        $this->actingAs($user)->post(route('dashboard.pos.documents.branches.store'), ['name' => 'Sucursal Pimentel', 'address' => 'Calle Sol 45, Pimentel', 'ubigeo' => '140105', 'district' => 'Pimentel']);
        $pimentel = Branch::where('name', 'Sucursal Pimentel')->firstOrFail();
        $this->actingAs($user)->post(route('dashboard.pos.registers.store'), ['name' => 'Caja Pimentel']);
        $other = Register::where('name', 'Caja Pimentel')->firstOrFail();
        $this->actingAs($user)->put(route('dashboard.pos.documents.registers.branch', $other->id), ['branch_id' => $pimentel->id]);

        $this->actingAs($user)->get(route('dashboard.pos.index'))->assertOk()->assertSee('pos-sede', false)->assertSee('Sede principal');
        $this->actingAs($user)->get(route('dashboard.pos.index', ['cajas' => 1]))->assertOk()->assertSee('📍 Sede principal')->assertSee('📍 Sucursal Pimentel');

        // Its comprobante carries the address of the sede where it was rung up.
        $second = $this->shift($user, $store, $other);
        $this->sell($user, $second, [['id' => $cement->id, 'price' => $cement->price, 'quantity' => 1]], ['document_type' => 'boleta'])->assertCreated();
        $order = Order::latest('id')->first();
        $this->actingAs($user)->get(route('dashboard.pos.receipt', $order->id))->assertOk()->assertSee('Calle Sol 45, Pimentel')->assertDontSee('Av. Grau 123');
    }

    public function test_sales_can_be_filtered_by_sede_only_when_there_are_several(): void
    {
        [$user, $store] = $this->owner();
        $cement = $this->products($store, 1)->first();
        $first = $this->shift($user, $store);
        $this->actingAs($user)->get(route('dashboard.ventas.index'))->assertOk()->assertDontSee('>Sede<', false);

        $this->actingAs($user)->post(route('dashboard.pos.documents.branches.store'), ['name' => 'Sucursal Pimentel', 'address' => 'Calle Sol 45', 'ubigeo' => '140105']);
        $pimentel = Branch::where('name', 'Sucursal Pimentel')->firstOrFail();
        $this->actingAs($user)->post(route('dashboard.pos.registers.store'), ['name' => 'Caja Pimentel']);
        $other = Register::where('name', 'Caja Pimentel')->firstOrFail();
        $this->actingAs($user)->put(route('dashboard.pos.documents.registers.branch', $other->id), ['branch_id' => $pimentel->id]);
        $second = $this->shift($user, $store, $other);

        $this->sell($user, $first, [['id' => $cement->id, 'price' => $cement->price, 'quantity' => 1]], ['customer_name' => 'Cliente Centro'])->assertCreated();
        $this->sell($user, $second, [['id' => $cement->id, 'price' => $cement->price, 'quantity' => 1]], ['customer_name' => 'Cliente Pimentel'])->assertCreated();

        $page = $this->actingAs($user)->get(route('dashboard.ventas.index'))->assertOk()->assertSee('Cliente Centro')->assertSee('Cliente Pimentel');
        $this->assertStringContainsString('Sucursal Pimentel', $page->getContent());
        $this->actingAs($user)->get(route('dashboard.ventas.index', ['sede' => $pimentel->id]))->assertOk()->assertSee('Cliente Pimentel')->assertDontSee('Cliente Centro');
        $main = Branch::where('store_id', $store->id)->where('is_main', true)->firstOrFail();
        $this->actingAs($user)->get(route('dashboard.ventas.index', ['sede' => $main->id]))->assertOk()->assertSee('Cliente Centro')->assertDontSee('Cliente Pimentel');
    }
}
