<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Register;
use App\Models\RegisterSession;
use App\Models\Store;
use App\Models\User;
use App\Services\Inventory\StockService;
use App\Services\Pos\PosSaleService;
use App\Services\Pos\RegisterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/** La pantalla de venta (sin autocompletado del navegador, menú «Más») y la ganancia de cada turno. */
class PosScreenTest extends TestCase
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
        $this->withoutVite();
    }

    /** @return array{0: User, 1: Store, 2: Product, 3: RegisterSession} a product bought at S/ 25.20 and sold at S/ 29.50 */
    private function shop(): array
    {
        $user = User::factory()->create(['role' => 'store_owner']);
        $store = Store::create(['user_id' => $user->id, 'name' => 'Ferretería', 'slug' => 'ferre', 'status' => 'active', 'template_name' => 'soft-market']);
        $product = Product::create(['store_id' => $store->id, 'name' => 'Cemento', 'slug' => 'cemento', 'price' => 29.5, 'cost_price' => 25.2, 'stock' => 0, 'track_stock' => true, 'is_active' => true]);
        app(StockService::class)->adjust($product, 'in', 20, 'Compra', $user->id, ['unit_cost' => 25.2]);
        $session = app(RegisterService::class)->open(Register::create(['store_id' => $store->id, 'name' => 'Caja 1']), 100, $user->id);

        return [$user, $store, $product, $session];
    }

    private function sell(RegisterSession $session, User $user, Product $product, int $quantity, ?float $discount = null): void
    {
        app(PosSaleService::class)->sell($session, [
            'uuid' => (string) Str::uuid(), 'items' => [['product_id' => $product->id, 'quantity' => $quantity]], 'discount' => $discount,
            'payments' => [['method' => 'cash', 'amount' => $quantity * 29.5 - ($discount ?? 0)]],
        ], $user->id);
    }

    public function test_the_search_box_cannot_be_autofilled_with_a_saved_email(): void
    {
        [$user] = $this->shop();

        $page = $this->actingAs($user)->get(route('dashboard.pos.index'))->assertOk();
        $html = $page->getContent();

        // The search field is a search box with a non-credential name and every manager hint off…
        $this->assertSame(1, preg_match('/<input\b[^>]*\bid="pos-search"[^>]*>/', $html, $search));
        foreach (['type="search"', 'name="pos_buscar"', 'autocomplete="off"', 'data-1p-ignore', 'data-lpignore', 'data-form-type="other"', 'spellcheck="false"'] as $attribute) {
            $this->assertStringContainsString($attribute, $search[0]);
        }
        // …and no password field on the screen invites the browser to fill "username" + "password" (the cause of the e-mail).
        preg_match_all('/<input[^>]*type="password"[^>]*>/', $html, $passwords);
        $this->assertNotEmpty($passwords[0]);
        foreach ($passwords[0] as $input) {
            $this->assertStringContainsString('autocomplete="new-password"', $input);
        }
    }

    public function test_secondary_actions_live_in_the_more_menu_and_the_extras_are_folded(): void
    {
        [$user] = $this->shop();

        $this->actingAs($user)->get(route('dashboard.pos.index'))->assertOk()
            ->assertSee('Caja 1')->assertSee('Abierta ·')->assertSee('Ventas del turno')->assertSee('Cerrar caja')
            ->assertSee('Más')->assertSee('Ingreso / retiro de efectivo')->assertSee('Proformas')->assertSee('Devoluciones')->assertSee('Ganancias de hoy')
            ->assertSee('Cliente y descuento')->assertSee('Vista de productos')
            ->assertSee(route('dashboard.ganancias.index', ['periodo' => 'hoy']), false);
    }

    public function test_the_shift_shows_what_it_earned(): void
    {
        [$user, , $product, $session] = $this->shop();
        $this->sell($session, $user, $product, 2);              // S/ 59.00 sold, 2 × 25.20 = S/ 50.40 of cost
        $this->sell($session, $user, $product, 1, 4.5);         // S/ 25.00 after discount, S/ 25.20 of cost

        $profit = app(RegisterService::class)->profit($session);
        $this->assertEqualsWithDelta(84.0, $profit['income'], 0.001);
        $this->assertEqualsWithDelta(75.6, $profit['cost'], 0.001);
        $this->assertEqualsWithDelta(8.4, $profit['gross'], 0.001);
        $this->assertEqualsWithDelta(10.0, $profit['margin'], 0.001);
        $this->assertFalse($profit['partial']);

        $this->actingAs($user)->get(route('dashboard.pos.sessions.show', $session->id))->assertOk()->assertSee('Ganancia del turno')->assertSee('S/ 8.40')->assertSee('margen 10.0 %');
        $this->actingAs($user)->get(route('dashboard.pos.sessions.close', $session->id))->assertOk()->assertSee('Ganancia del turno')->assertSee('Vendido S/ 84.00 − costo S/ 75.60');
    }

    public function test_cancelled_sales_and_other_shifts_do_not_count(): void
    {
        [$user, $store, $product, $session] = $this->shop();
        $this->sell($session, $user, $product, 1);
        $other = app(RegisterService::class)->open(Register::create(['store_id' => $store->id, 'name' => 'Caja 2']), 50, $user->id);
        $this->sell($other, $user, $product, 3);
        \App\Models\Order::where('register_session_id', $other->id)->update(['status' => 'cancelled']);

        $this->assertEqualsWithDelta(4.3, app(RegisterService::class)->profit($session)['gross'], 0.001);
        $this->assertSame(0.0, app(RegisterService::class)->profit($other)['income']);
        $this->assertNull(app(RegisterService::class)->profit($other)['margin']);
    }

    public function test_units_sold_without_a_recorded_cost_are_flagged(): void
    {
        [$user, $store, , $session] = $this->shop();
        $free = Product::create(['store_id' => $store->id, 'name' => 'Sin costo', 'slug' => 'sin-costo', 'price' => 10, 'stock' => 5, 'track_stock' => true, 'is_active' => true]);
        app(StockService::class)->adjust($free, 'in', 5, 'Compra', $user->id);   // lot with no cost
        $this->sell($session, $user, $free, 1);

        $this->assertTrue(app(RegisterService::class)->profit($session)['partial']);
        $this->actingAs($user)->get(route('dashboard.pos.sessions.show', $session->id))->assertOk()->assertSee('sin costo registrado');
    }

    public function test_a_cashier_in_kiosk_mode_never_sees_costs_or_profit(): void
    {
        [$user, , $product, $session] = $this->shop();
        $this->sell($session, $user, $product, 2);
        $this->actingAs($user)->post(route('dashboard.pos.staff.store'), ['name' => 'Elena', 'role' => 'encargado', 'pin' => '9999', 'pin_confirmation' => '9999']);
        $this->actingAs($user)->post(route('dashboard.pos.staff.store'), ['name' => 'Ana', 'role' => 'cajero', 'pin' => '1111', 'pin_confirmation' => '1111']);
        $this->actingAs($user)->post(route('dashboard.pos.kiosk.enable'));
        $this->actingAs($user)->post(route('dashboard.pos.unlock'), ['pin' => '1111']);

        $this->actingAs($user)->get(route('dashboard.pos.sessions.show', $session->id))->assertOk()->assertDontSee('Ganancia del turno')->assertDontSee('Ver ganancias');
        $this->actingAs($user)->get(route('dashboard.pos.sessions.close', $session->id))->assertOk()->assertDontSee('Ganancia del turno');
        $this->actingAs($user)->get(route('dashboard.pos.index'))->assertOk()->assertDontSee('Ganancias de hoy')->assertSee('Bloquear caja');
        $this->actingAs($user)->get(route('dashboard.ganancias.index'))->assertRedirect(route('dashboard.pos.index'));
    }
}
