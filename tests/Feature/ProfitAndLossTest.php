<?php

namespace Tests\Feature;

use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\Register;
use App\Models\RegisterSession;
use App\Models\Store;
use App\Models\User;
use App\Services\Inventory\StockService;
use App\Services\Pos\PosSaleService;
use App\Services\Pos\RegisterService;
use App\Services\Reports\ProfitAndLoss;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Ganancias y pérdidas: ingresos − costo = bruta; − gastos − pérdidas = neta, por día de Lima. */
class ProfitAndLossTest extends TestCase
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

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return array{0: User, 1: Store, 2: Product, 3: RegisterSession} a product bought at S/ 10 and sold at S/ 25 */
    private function business(string $slug = 'ferre'): array
    {
        $user = User::factory()->create(['role' => 'store_owner']);
        $store = Store::create(['user_id' => $user->id, 'name' => 'Ferretería', 'slug' => $slug, 'status' => 'active', 'template_name' => 'soft-market']);
        $product = Product::create(['store_id' => $store->id, 'name' => 'Cemento', 'slug' => 'cemento', 'price' => 25, 'cost_price' => 10, 'stock' => 0, 'track_stock' => true, 'is_active' => true]);

        Carbon::setTestNow('2026-11-03 14:00:00'); // 09:00 in Lima
        app(StockService::class)->adjust($product, 'in', 100, 'Compra', $user->id, ['unit_cost' => 10]);
        $register = Register::create(['store_id' => $store->id, 'name' => 'Caja 1']);
        $session = app(RegisterService::class)->open($register, 100, $user->id);

        return [$user, $store, $product, $session];
    }

    private function sell(RegisterSession $session, User $user, Product $product, int $quantity): void
    {
        app(PosSaleService::class)->sell($session, [
            'uuid' => (string) Str::uuid(), 'items' => [['product_id' => $product->id, 'quantity' => $quantity]],
            'payments' => [['method' => 'cash', 'amount' => $quantity * 25]],
        ], $user->id);
    }

    public function test_the_statement_adds_up_sales_cost_expenses_losses_and_returns(): void
    {
        [$user, $store, $product, $session] = $this->business();

        $this->sell($session, $user, $product, 4);                                          // S/ 100 sold, S/ 40 cost
        app(RegisterService::class)->movement($session, 'out', 15, 'Flete de reparto', $user->id);   // gasto
        app(StockService::class)->adjust($product, 'loss', 2, 'Se rompió una bolsa', $user->id);     // pérdida S/ 20
        app(StockService::class)->restock($product, null, 1, 'DEV-1', $user->id);                    // one unit back at S/ 10
        $order = \App\Models\Order::where('store_id', $store->id)->sole();
        OrderReturn::create(['store_id' => $store->id, 'order_id' => $order->id, 'number' => 'DEV-2026-000001', 'reason' => 'Mala compra', 'items' => [], 'total' => 25, 'refunded' => 25]);

        $report = app(ProfitAndLoss::class)->build($store, Carbon::parse('2026-11-03 00:00', ProfitAndLoss::TZ), Carbon::parse('2026-11-03 23:59', ProfitAndLoss::TZ));
        $day = $report['days']['2026-11-03'];

        $this->assertSame(1, $day['sales']);
        $this->assertEqualsWithDelta(100.0, $day['sold'], 0.001);
        $this->assertEqualsWithDelta(25.0, $day['returns'], 0.001);
        $this->assertEqualsWithDelta(75.0, $day['income'], 0.001);       // 100 − 25 returned
        $this->assertEqualsWithDelta(30.0, $day['cost'], 0.001);         // 40 − 10 put back
        $this->assertEqualsWithDelta(45.0, $day['gross'], 0.001);
        $this->assertEqualsWithDelta(60.0, $day['margin'], 0.001);
        $this->assertEqualsWithDelta(15.0, $day['expenses'], 0.001);
        $this->assertEqualsWithDelta(20.0, $day['losses'], 0.001);
        $this->assertEqualsWithDelta(10.0, $day['net'], 0.001);          // 45 − 15 − 20
        $this->assertEquals($day['net'], $report['totals']['net']);
        $this->assertSame([['reason' => 'Flete de reparto', 'amount' => 15.0]], $report['expenses']);
        $this->assertSame('Se rompió una bolsa', $report['losses'][0]['reason']);
        $this->assertFalse($report['estimated']);
    }

    public function test_a_loss_larger_than_the_profit_is_a_negative_net(): void
    {
        [$user, $store, $product, $session] = $this->business();
        $this->sell($session, $user, $product, 2);                                          // gross S/ 30
        app(StockService::class)->adjust($product, 'loss', 10, 'Humedad', $user->id);        // S/ 100 lost

        $report = app(ProfitAndLoss::class)->build($store, Carbon::parse('2026-11-03', ProfitAndLoss::TZ)->startOfDay(), Carbon::parse('2026-11-03', ProfitAndLoss::TZ)->endOfDay());

        $this->assertEqualsWithDelta(-70.0, $report['totals']['net'], 0.001);
    }

    public function test_days_are_cut_at_midnight_in_lima_not_in_utc(): void
    {
        [$user, $store, $product, $session] = $this->business();
        $this->sell($session, $user, $product, 1);                      // 09:00 Lima, Nov 3

        Carbon::setTestNow('2026-11-04 03:00:00');                      // 22:00 Lima, still Nov 3 (already Nov 4 in UTC)
        $this->sell($session, $user, $product, 1);
        Carbon::setTestNow('2026-11-04 06:00:00');                      // 01:00 Lima, Nov 4
        $this->sell($session, $user, $product, 1);

        $report = app(ProfitAndLoss::class)->build($store, Carbon::parse('2026-11-03', ProfitAndLoss::TZ)->startOfDay(), Carbon::parse('2026-11-04', ProfitAndLoss::TZ)->endOfDay());

        $this->assertSame([2, 1], [$report['days']['2026-11-03']['sales'], $report['days']['2026-11-04']['sales']]);
    }

    public function test_it_only_counts_the_stores_own_numbers(): void
    {
        [$user, $store, $product, $session] = $this->business('uno');
        $this->sell($session, $user, $product, 2);
        $other = Store::create(['user_id' => User::factory()->create(['role' => 'store_owner'])->id, 'name' => 'Otra', 'slug' => 'otra', 'status' => 'active', 'template_name' => 'soft-market']);

        $report = app(ProfitAndLoss::class)->build($other, Carbon::parse('2026-11-03', ProfitAndLoss::TZ)->startOfDay(), Carbon::parse('2026-11-03', ProfitAndLoss::TZ)->endOfDay());

        $this->assertSame(0, $report['totals']['sales']);
        $this->assertEquals(0.0, $report['totals']['net']);
    }

    public function test_ranges_and_the_page(): void
    {
        [$user, $store, $product, $session] = $this->business();
        $this->sell($session, $user, $product, 4);
        app(RegisterService::class)->movement($session, 'out', 15, 'Flete de reparto', $user->id);
        app(StockService::class)->adjust($product, 'loss', 2, 'Se rompió una bolsa', $user->id);

        [$from, $to] = ProfitAndLoss::range('7_dias');
        $this->assertSame(['2026-10-28', '2026-11-03'], [$from->toDateString(), $to->toDateString()]);
        $this->assertNull(ProfitAndLoss::customRange('2026-11-05', '2026-11-01'), 'end before start');
        $this->assertNull(ProfitAndLoss::customRange('no', 'fecha'));
        $this->assertNotNull(ProfitAndLoss::customRange('2026-11-01', '2026-11-03'));

        $this->actingAs($user)->get(route('dashboard.ganancias.index'))->assertOk()
            ->assertSee('Ganancias y pérdidas')->assertSee('Ganancia neta')->assertSee('Flete de reparto')->assertSee('Se rompió una bolsa')->assertSee('S/ 100.00');
        $this->actingAs($user)->get(route('dashboard.ganancias.index', ['periodo' => 'mes']))->assertOk();
        $this->actingAs($user)->get(route('dashboard.ganancias.index', ['desde' => '2026-11-01', 'hasta' => '2026-11-03']))->assertOk();
        $this->actingAs($user)->get(route('dashboard.ganancias.index', ['desde' => 'x', 'hasta' => 'y', 'periodo' => 'nope']))->assertOk();
    }

    public function test_a_negative_net_is_called_a_loss_on_the_page(): void
    {
        [$user, $store, $product, $session] = $this->business();
        $this->sell($session, $user, $product, 1);
        app(StockService::class)->adjust($product, 'loss', 20, 'Inundación', $user->id);

        $this->actingAs($user)->get(route('dashboard.ganancias.index'))->assertOk()->assertSee('Pérdida neta')->assertSee('−S/');
    }
}
