<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Register;
use App\Models\RegisterSession;
use App\Models\Store;
use App\Models\User;
use App\Services\Inventory\StockService;
use App\Services\Pos\PosSaleService;
use App\Services\Pos\RegisterService;
use App\Services\Reports\SalesProfit;
use App\Services\Reports\SalesSeries;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Ventas (caja) y Pedidos (tienda virtual) son módulos distintos; el Dashboard los grafica por separado. */
class SalesAndOrdersTest extends TestCase
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
        Carbon::setTestNow('2026-11-06 15:00:00'); // 10:00 in Lima
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return array{0: User, 1: Store, 2: Product, 3: RegisterSession} a product bought at S/ 10 and sold at S/ 25 */
    private function shop(string $mode = 'both'): array
    {
        $user = User::factory()->create(['role' => 'store_owner']);
        $store = Store::create(['user_id' => $user->id, 'name' => 'Ferretería', 'slug' => 'ferre-' . $mode, 'status' => 'active', 'template_name' => 'soft-market', 'mode' => $mode]);
        $product = Product::create(['store_id' => $store->id, 'name' => 'Cemento', 'slug' => 'cemento', 'price' => 25, 'cost_price' => 10, 'stock' => 0, 'track_stock' => true, 'is_active' => true]);
        app(StockService::class)->adjust($product, 'in', 100, 'Compra', $user->id, ['unit_cost' => 10]);
        $session = app(RegisterService::class)->open(Register::create(['store_id' => $store->id, 'name' => 'Caja 1']), 100, $user->id);

        return [$user, $store, $product, $session];
    }

    private function sell(RegisterSession $session, User $user, Product $product, int $quantity = 1, array $extra = []): Order
    {
        [$order] = app(PosSaleService::class)->sell($session, $extra + [
            'uuid' => (string) Str::uuid(), 'items' => [['product_id' => $product->id, 'quantity' => $quantity]],
            'payments' => [['method' => 'cash', 'amount' => $quantity * 25]],
        ], $user->id);

        return $order;
    }

    private function onlineOrder(Store $store, string $number, float $total = 80, string $status = 'pending'): Order
    {
        $order = new Order();
        $order->forceFill(['store_id' => $store->id, 'order_number' => $number, 'customer_name' => 'Comprador web', 'subtotal' => $total, 'total' => $total,
            'currency' => 'PEN', 'status' => $status, 'payment_status' => 'paid', 'source' => 'store'])->save();

        return $order;
    }

    public function test_pedidos_only_lists_the_online_stores_orders(): void
    {
        [$user, $store, $product, $session] = $this->shop();
        $sale = $this->sell($session, $user, $product, 2);
        $this->onlineOrder($store, 'WEB-0001');

        $this->actingAs($user)->get(route('dashboard.pedidos.index'))->assertOk()
            ->assertSee('WEB-0001')->assertDontSee($sale->order_number)->assertDontSee($sale->document_code)
            ->assertSee('tienda virtual')->assertSee('Ver ventas de caja');
    }

    public function test_a_counter_sale_opens_its_ticket_not_a_pedido(): void
    {
        [$user, , $product, $session] = $this->shop();
        $sale = $this->sell($session, $user, $product);

        $this->actingAs($user)->get(route('dashboard.pedidos.show', $sale->id))->assertRedirect(route('dashboard.pos.receipt', $sale->id));
    }

    public function test_ventas_only_lists_completed_counter_sales(): void
    {
        [$user, $store, $product, $session] = $this->shop();
        $kept = $this->sell($session, $user, $product, 1);
        $cancelled = $this->sell($session, $user, $product, 3);
        $cancelled->forceFill(['status' => 'cancelled'])->save();
        $web = $this->onlineOrder($store, 'WEB-0002');

        $page = $this->actingAs($user)->get(route('dashboard.ventas.index'))->assertOk();
        $page->assertSee($kept->document_code)->assertDontSee($cancelled->document_code)->assertDontSee($web->order_number)
            ->assertSee('<small>Ventas</small><strong>1</strong>', false)->assertDontSee('Anulada')->assertSee('Pedidos');
    }

    public function test_ventas_shows_the_profit_of_exactly_what_is_filtered(): void
    {
        [$user, , $product, $session] = $this->shop();
        $this->sell($session, $user, $product, 2, ['customer_name' => 'Ana Ruiz', 'customer_document' => '45678912']);                        // S/ 50 − 20
        $this->sell($session, $user, $product, 1, ['payments' => [['method' => 'yape', 'amount' => 25]]]);                                   // S/ 25 − 10
        $this->sell($session, $user, $product, 4, ['discount' => 10, 'payments' => [['method' => 'cash', 'amount' => 90]]]);                 // S/ 90 − 40

        // Everything today: 165 sold, 70 of cost.
        $this->actingAs($user)->get(route('dashboard.ventas.index'))->assertOk()
            ->assertSee('S/ 165.00')->assertSee('S/ 95.00')->assertSee('margen 57.6 %');
        // One customer only: the profit follows the search.
        $this->actingAs($user)->get(route('dashboard.ventas.index', ['q' => 'Ana']))->assertOk()->assertSee('S/ 50.00')->assertSee('S/ 30.00')->assertSee('margen 60.0 %')->assertDontSee('S/ 165.00');
        // One payment method only.
        $this->actingAs($user)->get(route('dashboard.ventas.index', ['medio' => 'yape']))->assertOk()->assertSee('S/ 25.00')->assertSee('S/ 15.00');
        // A day without sales shows nothing earned.
        $this->actingAs($user)->get(route('dashboard.ventas.index', ['periodo' => 'ayer']))->assertOk()->assertSee('No hay ventas')->assertSee('<small>Ventas</small><strong>0</strong>', false);
    }

    public function test_the_profit_service_works_on_any_order_query(): void
    {
        [$user, $store, $product, $session] = $this->shop();
        $this->sell($session, $user, $product, 2);
        $this->sell($session, $user, $product, 1);

        $profit = app(SalesProfit::class)->forOrders(Order::where('store_id', $store->id)->where('source', 'pos'), $store->id);
        $this->assertSame(2, $profit['count']);
        $this->assertEqualsWithDelta(75.0, $profit['income'], 0.001);
        $this->assertEqualsWithDelta(30.0, $profit['cost'], 0.001);
        $this->assertEqualsWithDelta(45.0, $profit['gross'], 0.001);
        $this->assertEqualsWithDelta(37.5, $profit['average'], 0.001);
        $this->assertFalse($profit['partial']);

        $none = app(SalesProfit::class)->forOrders(Order::where('store_id', $store->id)->where('source', 'nothing'), $store->id);
        $this->assertSame([0, 0.0, null, false], [$none['count'], $none['gross'], $none['margin'], $none['partial']]);
    }

    public function test_the_periods_cover_day_month_and_year(): void
    {
        [$user, $store, $product, $session] = $this->shop();
        $today = $this->sell($session, $user, $product);
        Carbon::setTestNow('2026-11-05 15:00:00');
        $yesterday = $this->sell($session, $user, $product);
        Carbon::setTestNow('2026-10-20 15:00:00');
        $lastMonth = $this->sell($session, $user, $product);
        Carbon::setTestNow('2026-03-10 15:00:00');
        $thisYear = $this->sell($session, $user, $product);
        Carbon::setTestNow('2026-11-06 15:00:00');

        $codes = fn (string $periodo) => collect([$today, $yesterday, $lastMonth, $thisYear])->filter(fn ($o) => str_contains($this->actingAs($user)->get(route('dashboard.ventas.index', ['periodo' => $periodo]))->getContent(), $o->document_code))->map->document_code->values()->all();

        $this->assertSame([$today->document_code], $codes('hoy'));
        $this->assertSame([$yesterday->document_code], $codes('ayer'));
        $this->assertSame([$today->document_code, $yesterday->document_code], $codes('mes'));
        $this->assertSame([$lastMonth->document_code], $codes('mes_pasado'));
        $this->assertCount(4, $codes('anio'));
        $this->assertCount(4, $codes('todas'));
        // A single day picked by hand.
        $this->actingAs($user)->get(route('dashboard.ventas.index', ['desde' => '2026-03-10', 'hasta' => '2026-03-10']))->assertOk()->assertSee($thisYear->document_code)->assertDontSee($today->document_code);
    }

    public function test_the_menu_separates_the_two_systems_when_the_account_has_both(): void
    {
        [$both] = $this->shop('both');
        $page = $this->actingAs($both)->get(route('dashboard.index'))->assertOk();
        $page->assertSeeInOrder(['Punto de venta', 'Caja', 'Ventas', 'Tienda virtual', 'Pedidos', 'Productos y stock', 'Clientes y reportes', 'Ganancias y pérdidas']);
    }

    public function test_the_dashboard_has_one_chart_per_system(): void
    {
        [$user, $store, $product, $session] = $this->shop('both');
        $this->sell($session, $user, $product, 2);
        $this->onlineOrder($store, 'WEB-0003', 120);

        $this->actingAs($user)->get(route('dashboard.index'))->assertOk()
            ->assertSee('Ventas del punto de venta')->assertSee('Pedidos de la tienda virtual')
            ->assertSee('Últimos 7 días')->assertSee('Ver tabla')->assertSee('Ventas de hoy')
            ->assertSee('S/ 50.00')->assertSee('S/ 120.00')->assertSee('Punto de venta')->assertSee('Tienda virtual');
        $this->actingAs($user)->get(route('dashboard.index', ['grafico' => '30']))->assertOk()->assertSee('Ventas del punto de venta');
    }

    public function test_each_account_type_gets_only_its_own_chart(): void
    {
        [$pos] = $this->shop('pos');
        $this->actingAs($pos)->get(route('dashboard.index'))->assertOk()->assertSee('Ventas del punto de venta')->assertDontSee('Pedidos de la tienda virtual');

        [$online, $store] = $this->shop('online');
        $this->onlineOrder($store, 'WEB-0004', 60);
        $this->actingAs($online)->get(route('dashboard.index'))->assertOk()->assertSee('Pedidos de la tienda virtual')->assertDontSee('Ventas del punto de venta');
    }

    public function test_the_dashboard_counts_orders_apart_from_sales(): void
    {
        [$user, $store, $product, $session] = $this->shop('both');
        $this->sell($session, $user, $product, 2);
        $this->onlineOrder($store, 'WEB-0005');

        $page = $this->actingAs($user)->get(route('dashboard.index'))->assertOk();
        $page->assertSee('1 pedidos de la tienda en total');
        $page->assertDontSee('2 pedidos de la tienda en total');
    }

    public function test_series_group_by_lima_day_and_origin(): void
    {
        [$user, $store, $product, $session] = $this->shop('both');
        $this->sell($session, $user, $product);                       // 10:00 Lima, Nov 6
        Carbon::setTestNow('2026-11-07 03:30:00');                    // 22:30 Lima, still Nov 6
        $this->sell($session, $user, $product);
        Carbon::setTestNow('2026-11-06 15:00:00');
        $this->onlineOrder($store, 'WEB-0006', 200);

        [$from, $to] = SalesSeries::range('7');
        $series = app(SalesSeries::class);
        $pos = $series->daily($store, 'pos', $from, $to);
        $online = $series->daily($store, 'online', $from, $to);

        $this->assertCount(7, $pos['days']);
        $this->assertSame([2, 50.0], [$pos['days']['2026-11-06']['count'], $pos['days']['2026-11-06']['total']]);
        $this->assertSame([1, 200.0], [$online['count'], $online['total']]);
        $this->assertSame(25.0, $pos['average']);
    }

    public function test_profit_page_splits_income_by_origin_only_for_both(): void
    {
        [$user, $store, $product, $session] = $this->shop('both');
        $this->sell($session, $user, $product, 2);
        $this->onlineOrder($store, 'WEB-0007', 100);

        $this->actingAs($user)->get(route('dashboard.ganancias.index', ['periodo' => 'hoy']))->assertOk()->assertSee('Ventas por origen')->assertSee('S/ 100.00')->assertSee('Ayer');
        [$pos, , $p2, $s2] = $this->shop('pos');
        $this->actingAs($pos)->get(route('dashboard.ganancias.index'))->assertOk()->assertDontSee('Ventas por origen');
    }
}
