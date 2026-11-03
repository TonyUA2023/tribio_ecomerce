<?php

namespace Tests\Feature;

use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\PurchaseOrder;
use App\Models\StockCount;
use App\Models\StockLot;
use App\Models\Store;
use App\Models\User;
use App\Services\Inventory\ProfitReport;
use App\Services\Inventory\ReorderAdvisor;
use App\Services\Inventory\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** ERP Fase 1e: packagings (buy by box, stock in units) and combos (derived stock, sold through components). */
class UnitsAndKitsTest extends TestCase
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

    private function owner(string $slug = 'ferreteria'): array
    {
        $user = User::factory()->create(['role' => 'store_owner']);
        $store = Store::create([
            'user_id' => $user->id, 'name' => 'Ferretería ' . $slug, 'slug' => $slug, 'status' => 'active', 'template_name' => 'soft-market',
            'checkout_mode' => 'mixed', 'whatsapp_phone' => '51 999 888 777', 'national_shipping_cost' => 10,
        ]);

        return [$user, $store];
    }

    private function product(Store $store, array $attributes = []): Product
    {
        return Product::create($attributes + [
            'store_id' => $store->id, 'name' => 'Clavos', 'slug' => 'p-' . uniqid(), 'price' => 1,
            'stock' => 0, 'track_stock' => true, 'is_active' => true, 'low_stock_alert' => 0,
        ]);
    }

    private function stock(): StockService
    {
        return app(StockService::class);
    }

    /** Batido (10 at S/ 60) + Té (30 at S/ 20) → "Pack Desayuno" = 1 batido + 2 tés. */
    private function breakfastPack(User $user, Store $store): array
    {
        $shake = $this->product($store, ['name' => 'Batido', 'price' => 100]);
        $tea = $this->product($store, ['name' => 'Té', 'price' => 30]);
        $this->stock()->adjust($shake, 'in', 10, null, null, ['unit_cost' => 60]);
        $this->stock()->adjust($tea, 'in', 30, null, null, ['unit_cost' => 20]);
        $pack = $this->product($store, ['name' => 'Pack Desayuno', 'price' => 150]);

        $this->actingAs($user)->put(route('dashboard.inventario.kit.update', $pack->id), ['components_json' => json_encode([
            ['product_id' => $shake->id, 'quantity' => 1], ['product_id' => $tea->id, 'quantity' => 2],
        ])])->assertRedirect()->assertSessionHasNoErrors();

        return [$shake, $tea, $pack];
    }

    // ─── Packagings ──────────────────────────────────────────────

    public function test_packagings_and_stock_shown_in_them(): void
    {
        [$user, $store] = $this->owner();
        $nails = $this->product($store, ['stock' => 0]);

        $this->actingAs($user)->post(route('dashboard.inventario.units.store', $nails->id), ['name' => 'Caja', 'factor' => 100])->assertRedirect();
        $this->actingAs($user)->post(route('dashboard.inventario.units.store', $nails->id), ['name' => 'caja', 'factor' => 50])->assertSessionHasErrors('name');
        $this->actingAs($user)->post(route('dashboard.inventario.units.store', $nails->id), ['name' => 'Bolsa', 'factor' => 1])->assertSessionHasErrors('factor');

        $nails->stock = 240;
        $this->assertSame('2 cajas y 40 und', $nails->load('units')->stockInUnits());
        $nails->stock = 100;
        $this->assertSame('1 caja', $nails->stockInUnits());
        $nails->stock = 40;
        $this->assertNull($nails->stockInUnits());
    }

    public function test_buying_by_box_puts_units_in_stock_at_cost_per_unit(): void
    {
        [$user, $store] = $this->owner();
        $nails = $this->product($store);
        $box = ProductUnit::create(['product_id' => $nails->id, 'name' => 'Caja', 'factor' => 100]);

        $this->actingAs($user)->post(route('dashboard.compras.store'), [
            'items_json'  => json_encode([['product_id' => $nails->id, 'unit_id' => $box->id, 'quantity' => 3, 'unit_cost' => 25]]),
            'extra_costs' => 15,
        ])->assertRedirect();
        $order = PurchaseOrder::with('items')->sole();
        $this->assertSame(['Caja', 100, '3 Caja ×100'], [$order->items[0]->unit_name, $order->items[0]->unit_factor, $order->items[0]->quantity_label]);
        $this->assertEquals(75.0, (float) $order->subtotal);

        $this->actingAs($user)->post(route('dashboard.compras.receive', $order->id), ['lines' => [$order->items[0]->id => ['quantity' => 3]]]);

        // (3 × 25 + 15) / 3 = 30 per box → 0.30 per nail; 300 nails in stock.
        $this->assertSame(300, $nails->fresh()->stock);
        $this->assertEquals(30.0, (float) $order->items[0]->fresh()->landed_unit_cost);
        $this->assertEquals(0.3, (float) StockLot::sole()->unit_cost);
        $this->assertEquals(0.3, (float) $nails->fresh()->cost_price);

        $this->actingAs($user)->get(route('dashboard.inventario.product', $nails))->assertOk()->assertSee('= 3 cajas')->assertSee('S/ 0.30 c/u');
    }

    public function test_a_packaging_of_another_product_is_rejected(): void
    {
        [$user, $store] = $this->owner();
        $nails = $this->product($store);
        $screws = $this->product($store, ['name' => 'Tornillos']);
        $box = ProductUnit::create(['product_id' => $screws->id, 'name' => 'Caja', 'factor' => 100]);

        $this->actingAs($user)->post(route('dashboard.compras.store'), [
            'items_json' => json_encode([['product_id' => $nails->id, 'unit_id' => $box->id, 'quantity' => 1, 'unit_cost' => 1]]),
        ])->assertSessionHasErrors('items');
        $this->assertSame(0, PurchaseOrder::count());
    }

    // ─── Combos ──────────────────────────────────────────────────

    public function test_a_combo_stock_is_what_its_components_allow(): void
    {
        [$user, $store] = $this->owner();
        [$shake, $tea, $pack] = $this->breakfastPack($user, $store);

        $this->assertSame(10, $pack->fresh()->stock, 'min(10 / 1, 30 / 2)');
        $this->assertTrue($pack->fresh()->track_stock);

        $this->stock()->adjust($tea->fresh(), 'loss', 26, null, null);   // 4 tés left → 2 packs
        $this->assertSame(2, $pack->fresh()->stock);
    }

    public function test_selling_a_combo_takes_its_components_at_their_cost(): void
    {
        [$user, $store] = $this->owner();
        [$shake, $tea, $pack] = $this->breakfastPack($user, $store);

        $movement = $this->stock()->sell($pack->fresh(), null, 3, 'TRB-9');

        $this->assertSame([7, 24, 7], [$shake->fresh()->stock, $tea->fresh()->stock, $pack->fresh()->stock]);
        $this->assertSame(['sale', -3, 'TRB-9'], [$movement->type, $movement->quantity, $movement->reference]);
        $this->assertEquals(3 * 60 + 6 * 20, (float) $movement->total_cost);
        $components = InventoryMovement::where('reference', 'TRB-9')->where('type', 'out')->get();
        $this->assertSame([-3, -6], $components->sortBy('product_id')->pluck('quantity')->values()->all());
        $this->assertStringContainsString('Pack Desayuno', $components->first()->reason);
    }

    public function test_checkout_of_a_combo_and_its_profit(): void
    {
        [$user, $store] = $this->owner();
        [$shake, $tea, $pack] = $this->breakfastPack($user, $store);

        $this->postJson(route('store.checkout', $store->slug), [
            'customer_name' => 'Ana', 'customer_phone' => '987654321', 'customer_email' => 'ana@example.test', 'customer_address' => 'Av. 1', 'customer_country' => 'PE', 'customer_city' => 'Lima',
            'items' => [['id' => $pack->id, 'quantity' => 2]],
        ])->assertOk();
        $order = Order::sole();
        $this->assertSame([8, 26, 8], [$shake->fresh()->stock, $tea->fresh()->stock, $pack->fresh()->stock]);

        $order->update(['payment_status' => 'paid']);
        $rows = collect(app(ProfitReport::class)->build($store, now()->startOfMonth(), now()->endOfDay())['rows'])->keyBy('name');

        $this->assertSame(['Pack Desayuno'], $rows->keys()->all(), 'Components are not sales of their own');
        $this->assertEquals([300.0, 200.0, 100.0, 'exact'], [$rows['Pack Desayuno']['revenue'], $rows['Pack Desayuno']['cost'], $rows['Pack Desayuno']['profit'], $rows['Pack Desayuno']['cost_status']]);
    }

    public function test_a_combo_cannot_be_moved_by_hand_and_ignores_the_form_stock(): void
    {
        [$user, $store] = $this->owner();
        [, , $pack] = $this->breakfastPack($user, $store);

        $this->actingAs($user)->post(route('dashboard.inventario.adjust', $pack), ['type' => 'in', 'quantity' => 5])->assertSessionHasErrors('type');

        $pack->update(['stock' => 99]);                       // the product form writes stock directly
        $this->stock()->reconcile($pack);
        $this->assertSame(10, $pack->fresh()->stock);
    }

    public function test_combo_rules(): void
    {
        [$user, $store] = $this->owner();
        [$shake, , $pack] = $this->breakfastPack($user, $store);
        $withStock = $this->product($store, ['name' => 'Con stock', 'stock' => 4]);
        $untracked = $this->product($store, ['name' => 'Servicio', 'track_stock' => false]);
        [, $other] = $this->owner('otra');
        $foreign = $this->product($other);
        $new = $this->product($store, ['name' => 'Nuevo pack']);

        $save = fn (Product $kit, array $rows) => $this->actingAs($user)->put(route('dashboard.inventario.kit.update', $kit->id), ['components_json' => json_encode($rows)]);

        $save($withStock, [['product_id' => $shake->id, 'quantity' => 1]])->assertSessionHasErrors('components');   // has its own stock
        $save($new, [['product_id' => $pack->id, 'quantity' => 1]])->assertSessionHasErrors('components');         // nested combo
        $save($new, [['product_id' => $untracked->id, 'quantity' => 1]])->assertSessionHasErrors('components');
        $save($new, [['product_id' => $foreign->id, 'quantity' => 1]])->assertSessionHasErrors('components');
        $save($new, [['product_id' => $new->id, 'quantity' => 1]])->assertSessionHasErrors('components');
        $save($shake, [['product_id' => $new->id, 'quantity' => 1]])->assertSessionHasErrors('components');        // shake is inside a combo
        $this->assertSame(0, $new->components()->count());

        $save($pack, [])->assertSessionHas('success', '«Pack Desayuno» ya no es un combo.');
        $this->assertSame(0, $pack->components()->count());
    }

    public function test_combos_stay_out_of_counts_purchases_and_reorder_but_their_sales_count_for_components(): void
    {
        [$user, $store] = $this->owner();
        [$shake, $tea, $pack] = $this->breakfastPack($user, $store);
        $this->stock()->sell($pack->fresh(), null, 9, 'TRB-1');   // 9 batidos consumed via combos → 1 left

        $this->actingAs($user)->post(route('dashboard.inventario.conteos.store'), []);
        $this->assertNotContains($pack->id, StockCount::sole()->items()->pluck('product_id')->all());

        $this->actingAs($user)->post(route('dashboard.compras.store'), [
            'items_json' => json_encode([['product_id' => $pack->id, 'quantity' => 1, 'unit_cost' => 1]]),
        ])->assertSessionHasErrors('items');

        $rows = collect(app(ReorderAdvisor::class)->suggestions($store))->keyBy('name');
        $this->assertArrayNotHasKey('Pack Desayuno', $rows->all());
        $this->assertSame(9, $rows['Batido']['sold'], 'Consumed through combos = sold');
    }

    public function test_product_page_shows_the_combo(): void
    {
        [$user, $store] = $this->owner();
        [, , $pack] = $this->breakfastPack($user, $store);

        $this->actingAs($user)->get(route('dashboard.inventario.product', $pack))->assertOk()
            ->assertSee('Es un combo')->assertDontSee('Registrar movimiento')->assertDontSee('📦 Presentaciones');
    }
}
