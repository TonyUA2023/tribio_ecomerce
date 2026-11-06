<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Register;
use App\Models\RegisterSession;
use App\Models\Store;
use App\Models\User;
use App\Services\Inventory\ProfitReport;
use App\Services\Inventory\StockService;
use App\Services\Pos\PosSaleService;
use App\Services\Pos\RegisterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Inventario → Rentabilidad: hoy, ayer, 7 días, el resto de períodos y fechas manuales (en días de Lima). */
class ProfitabilityFiltersTest extends TestCase
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
        Carbon::setTestNow('2026-11-06 15:00:00'); // Friday 10:00 in Lima
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** One product bought at S/ 10 and sold at S/ 25: every unit earns S/ 15. */
    private function shop(): array
    {
        $user = User::factory()->create(['role' => 'store_owner']);
        $store = Store::create(['user_id' => $user->id, 'name' => 'Ferretería', 'slug' => 'ferre', 'status' => 'active', 'template_name' => 'soft-market']);
        $product = Product::create(['store_id' => $store->id, 'name' => 'Cemento', 'slug' => 'cemento', 'price' => 25, 'cost_price' => 10, 'stock' => 0, 'track_stock' => true, 'is_active' => true]);
        app(StockService::class)->adjust($product, 'in', 100, 'Compra', $user->id, ['unit_cost' => 10]);
        $session = app(RegisterService::class)->open(Register::create(['store_id' => $store->id, 'name' => 'Caja 1']), 100, $user->id);

        return [$user, $store, $product, $session];
    }

    private function sellAt(string $utc, RegisterSession $session, User $user, Product $product, int $units): void
    {
        Carbon::setTestNow($utc);
        app(PosSaleService::class)->sell($session, [
            'uuid' => (string) Str::uuid(), 'items' => [['product_id' => $product->id, 'quantity' => $units]],
            'payments' => [['method' => 'cash', 'amount' => $units * 25]],
        ], $user->id);
        Carbon::setTestNow('2026-11-06 15:00:00');
    }

    private function data(): array
    {
        [$user, $store, $product, $session] = $this->shop();
        $this->sellAt('2026-11-06 15:00:00', $session, $user, $product, 2);   // today 10:00 Lima        → S/ 50, earns 30
        $this->sellAt('2026-11-06 03:30:00', $session, $user, $product, 1);   // Nov 5, 22:30 Lima        → S/ 25, earns 15 (already Nov 6 in UTC)
        $this->sellAt('2026-11-01 15:00:00', $session, $user, $product, 3);   // Sunday Nov 1             → S/ 75, earns 45
        $this->sellAt('2026-10-12 15:00:00', $session, $user, $product, 4);   // last month               → S/ 100, earns 60

        return [$user, $store];
    }

    private function totals(User $user, array $query): array
    {
        $html = $this->actingAs($user)->get(route('dashboard.inventario.profit', $query))->assertOk()->getContent();
        preg_match_all('/class="stat-card"><p[^>]*>([^<]+)<\/p><p[^>]*>([^<]+)<\/p>/', $html, $m);

        return array_combine($m[1], array_map('trim', $m[2]));
    }

    public function test_the_new_presets_are_offered(): void
    {
        [$user] = $this->data();

        $this->actingAs($user)->get(route('dashboard.inventario.profit'))->assertOk()
            ->assertSee('Hoy')->assertSee('Ayer')->assertSee('Últimos 7 días')->assertSee('Este mes')->assertSee('Mes pasado')->assertSee('Últimos 30 días')->assertSee('Este año')
            ->assertSee('Desde')->assertSee('Hasta')->assertSee('Ver ganancia');
        $this->assertSame(['hoy', 'ayer', '7_dias', 'mes', 'mes_pasado', '30_dias', 'anio'], array_keys(ProfitReport::PRESETS));
    }

    public function test_today_shows_only_todays_earnings(): void
    {
        [$user] = $this->data();

        $totals = $this->totals($user, ['periodo' => 'hoy']);
        $this->assertSame('S/ 50.00', $totals['Ventas']);
        $this->assertSame('S/ 20.00', $totals['Costo de lo vendido']);
        $this->assertSame('S/ 30.00', $totals['Ganancia bruta']);
        $this->actingAs($user)->get(route('dashboard.inventario.profit', ['periodo' => 'hoy']))->assertSee('el viernes 6 de noviembre 2026')->assertSee('Cemento')->assertSee('2 und.');
    }

    public function test_a_sale_at_night_in_lima_belongs_to_its_peruvian_day(): void
    {
        [$user] = $this->data();

        // 22:30 of Nov 5 in Lima is already Nov 6 in UTC: it is yesterday's sale, not today's.
        $yesterday = $this->totals($user, ['periodo' => 'ayer']);
        $this->assertSame(['S/ 25.00', 'S/ 15.00'], [$yesterday['Ventas'], $yesterday['Ganancia bruta']]);
    }

    public function test_seven_days_month_last_month_and_year(): void
    {
        [$user] = $this->data();

        $this->assertSame(['S/ 150.00', 'S/ 90.00'], array_values(array_intersect_key($this->totals($user, ['periodo' => '7_dias']), array_flip(['Ventas', 'Ganancia bruta']))));
        $this->assertSame('S/ 150.00', $this->totals($user, ['periodo' => 'mes'])['Ventas']);
        $this->assertSame('S/ 100.00', $this->totals($user, ['periodo' => 'mes_pasado'])['Ventas']);
        $this->assertSame('S/ 250.00', $this->totals($user, ['periodo' => 'anio'])['Ventas']);
        $this->assertSame('S/ 150.00', $this->totals($user, [])['Ventas'], 'the default stays "Este mes"');
    }

    public function test_a_hand_picked_range_and_a_single_day(): void
    {
        [$user] = $this->data();

        $range = $this->totals($user, ['desde' => '2026-10-01', 'hasta' => '2026-11-01']);
        $this->assertSame(['S/ 175.00', 'S/ 105.00'], [$range['Ventas'], $range['Ganancia bruta']]);   // Oct 12 + Nov 1

        $day = $this->totals($user, ['desde' => '2026-11-01', 'hasta' => '2026-11-01']);
        $this->assertSame(['S/ 75.00', 'S/ 45.00'], [$day['Ventas'], $day['Ganancia bruta']]);
        $this->actingAs($user)->get(route('dashboard.inventario.profit', ['desde' => '2026-11-01', 'hasta' => '2026-11-01']))->assertOk()
            ->assertSee('el domingo 1 de noviembre 2026')->assertSee('value="2026-11-01"', false);

        // Only "desde": that one day.
        $this->assertSame('S/ 75.00', $this->totals($user, ['desde' => '2026-11-01'])['Ventas']);
    }

    public function test_a_bad_range_falls_back_to_the_month_and_says_so(): void
    {
        [$user] = $this->data();

        $this->actingAs($user)->get(route('dashboard.inventario.profit', ['desde' => '2026-11-05', 'hasta' => '2026-11-01']))->assertOk()
            ->assertSee('Revisa las fechas')->assertSee('Este mes');
        $this->actingAs($user)->get(route('dashboard.inventario.profit', ['desde' => 'no-es-fecha', 'hasta' => 'x']))->assertOk()->assertSee('Revisa las fechas');
        $this->actingAs($user)->get(route('dashboard.inventario.profit', ['desde' => '2024-01-01', 'hasta' => '2026-11-01']))->assertOk()->assertSee('Revisa las fechas');
        $this->assertSame('S/ 150.00', $this->totals($user, ['desde' => '2026-11-05', 'hasta' => '2026-11-01'])['Ventas']);
    }

    public function test_another_store_never_adds_to_the_numbers(): void
    {
        [$user] = $this->data();
        [$otherUser, $otherStore, $otherProduct, $otherSession] = (function () {
            $u = User::factory()->create(['role' => 'store_owner']);
            $s = Store::create(['user_id' => $u->id, 'name' => 'Otra', 'slug' => 'otra', 'status' => 'active', 'template_name' => 'soft-market']);
            $p = Product::create(['store_id' => $s->id, 'name' => 'Pintura', 'slug' => 'pintura', 'price' => 25, 'cost_price' => 10, 'stock' => 0, 'track_stock' => true, 'is_active' => true]);
            app(StockService::class)->adjust($p, 'in', 50, 'Compra', $u->id, ['unit_cost' => 10]);

            return [$u, $s, $p, app(RegisterService::class)->open(Register::create(['store_id' => $s->id, 'name' => 'Caja']), 10, $u->id)];
        })();
        $this->sellAt('2026-11-06 15:00:00', $otherSession, $otherUser, $otherProduct, 5);

        $this->assertSame('S/ 50.00', $this->totals($user, ['periodo' => 'hoy'])['Ventas']);
        $this->assertSame('S/ 125.00', $this->totals($otherUser, ['periodo' => 'hoy'])['Ventas']);
    }
}
