<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\RegisterSession;
use App\Models\Store;
use App\Models\StockLot;
use App\Models\User;
use App\Services\Inventory\ProfitReport;
use Database\Seeders\DemoFerreteriaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** The DEMOFERRETERIA account: a store with a week of sales, shifts and purchases that add up. */
class DemoFerreteriaSeederTest extends TestCase
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
        // Mid-afternoon, so "today" has a shift of its own.
        Carbon::setTestNow(Carbon::parse('2026-11-03 21:00:00'));
        $this->seed(DemoFerreteriaSeeder::class);
        Carbon::setTestNow(Carbon::parse('2026-11-03 21:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function store(): Store
    {
        return Store::where('slug', DemoFerreteriaSeeder::SLUG)->firstOrFail();
    }

    public function test_the_ferreteria_owner_can_sign_in_and_reach_the_dashboard(): void
    {
        $this->post('/login', ['email' => DemoFerreteriaSeeder::EMAIL, 'password' => DemoFerreteriaSeeder::DEFAULT_PASSWORD])
            ->assertRedirect(route('dashboard.index'));

        $user = User::where('email', DemoFerreteriaSeeder::EMAIL)->firstOrFail();
        $this->assertSame('store_owner', $user->role);
        $this->assertSame($this->store()->id, $user->currentStore()->id);
    }

    public function test_the_catalog_is_priced_with_a_margin_and_stocked_through_lots(): void
    {
        $store = $this->store();
        $products = Product::where('store_id', $store->id)->get();

        $this->assertGreaterThanOrEqual(40, $products->count());
        foreach ($products as $product) {
            $this->assertGreaterThan((float) $product->cost_price, (float) $product->price, $product->name);
            $this->assertGreaterThanOrEqual(0, $product->stock);
            // products.stock is the sum of its lots.
            $this->assertSame((int) $product->stock, (int) StockLot::where('product_id', $product->id)->sum('quantity_remaining'), $product->name);
        }
    }

    public function test_there_is_a_closed_shift_report_for_each_of_the_seven_days(): void
    {
        $store = $this->store();
        $sessions = RegisterSession::where('store_id', $store->id)->get();

        $this->assertCount(7, $sessions->groupBy(fn ($s) => $s->opened_at->toDateString()));
        $this->assertGreaterThan(7, $sessions->count(), 'More than one register per day.');
        foreach ($sessions as $session) {
            $this->assertSame('closed', $session->status);
            $this->assertEqualsWithDelta((float) $session->counted_cash - (float) $session->expected_cash, (float) $session->difference, 0.005);
            $this->assertEqualsWithDelta(
                (float) $session->orders()->where('status', '!=', 'cancelled')->sum('total'),
                (float) $session->totals['sales_total'],
                0.005,
            );
        }
        $this->assertTrue(Order::where('store_id', $store->id)->where('source', 'pos')->where('payment_status', 'paid')->exists());
        $this->assertSame(0, Order::where('store_id', $store->id)->where('payment_status', '!=', 'paid')->count());
    }

    public function test_the_weekly_report_adds_up_and_matches_the_apps_profitability_report(): void
    {
        $store = $this->store();
        $from = Carbon::now()->subDays(6)->startOfDay();
        $report = app(DemoFerreteriaSeeder::class)->weeklyReport($store, $from, Carbon::now()->endOfDay());

        $this->assertCount(7, $report['days']);
        foreach ($report['days'] as $date => $day) {
            $this->assertGreaterThan(0, $day['sales'], $date);
            $this->assertEqualsWithDelta($day['income'] - $day['cost'], $day['gross'], 0.005, $date);
            $this->assertEqualsWithDelta($day['gross'] - $day['expenses'], $day['net'], 0.005, $date);
            $this->assertEqualsWithDelta($day['purchases'] + $day['expenses'], $day['outflow'], 0.005, $date);
        }

        $totals = $report['totals'];
        $this->assertGreaterThan(0, $totals['gross']);
        $this->assertGreaterThan(0, $totals['net']);
        $this->assertLessThan($totals['gross'], $totals['net']);

        // Same figure the dashboard's Rentabilidad page computes for the same period.
        $profit = app(ProfitReport::class)->build($store, $from, Carbon::now()->endOfDay());
        $this->assertEqualsWithDelta($profit['totals']['revenue'], $totals['income'], 0.05);
        $this->assertEqualsWithDelta($profit['totals']['cost'], $totals['cost'], 0.05);
        $this->assertEqualsWithDelta($profit['totals']['profit'], $totals['gross'], 0.05);
        $this->assertFalse($profit['estimated']);
        $this->assertFalse($profit['missing']);
    }

    public function test_the_dashboard_screens_render_with_the_demo_data(): void
    {
        $user = User::where('email', DemoFerreteriaSeeder::EMAIL)->firstOrFail();
        $session = RegisterSession::where('store_id', $this->store()->id)->orderByDesc('opened_at')->firstOrFail();

        $this->actingAs($user)->get(route('dashboard.pos.sessions.index'))->assertOk()->assertSee('Caja 1');
        $this->actingAs($user)->get(route('dashboard.pos.sessions.show', $session->id))->assertOk();
        $this->actingAs($user)->get(route('dashboard.inventario.profit'))->assertOk();
        $this->actingAs($user)->get(route('dashboard.index'))->assertOk();
    }

    public function test_running_the_seeder_again_changes_nothing(): void
    {
        $orders = Order::count();
        $this->seed(DemoFerreteriaSeeder::class);
        Carbon::setTestNow(Carbon::parse('2026-11-03 21:00:00'));

        $this->assertSame($orders, Order::count());
        $this->assertSame(1, Store::where('slug', DemoFerreteriaSeeder::SLUG)->count());
    }
}
