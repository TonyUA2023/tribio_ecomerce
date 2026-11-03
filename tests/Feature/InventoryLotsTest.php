<?php

namespace Tests\Feature;

use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockLot;
use App\Models\Store;
use App\Models\User;
use App\Services\Inventory\InventorySchema;
use App\Services\Inventory\ProfitReport;
use App\Services\Inventory\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** ERP Fase 1 (1a + 1b): lots with cost and expiry, FEFO/FIFO consumption, profit per product. */
class InventoryLotsTest extends TestCase
{
    use RefreshDatabase;

    private int $orderSeq = 0;

    protected function migrateFreshUsing(): array
    {
        return ['--path' => array_values(array_filter(glob(database_path('migrations/*.php')),
            fn ($path) => !str_ends_with($path, '2026_09_11_182936_update_role_column_in_users_table.php'))), '--realpath' => true];
    }

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function owner(): array
    {
        $user = User::factory()->create(['role' => 'store_owner']);
        $store = Store::create([
            'user_id' => $user->id, 'name' => 'Botica Sur', 'slug' => 'botica-sur', 'status' => 'active', 'template_name' => 'soft-market',
        ]);

        return [$user, $store];
    }

    private function product(Store $store, array $attributes = []): Product
    {
        return Product::create($attributes + [
            'store_id' => $store->id, 'name' => 'Proteína', 'slug' => 'proteina-' . uniqid(), 'price' => 100,
            'stock' => 0, 'track_stock' => true, 'is_active' => true,
        ]);
    }

    private function stock(): StockService
    {
        return app(StockService::class);
    }

    /** A paid order with one line per [product, quantity, unit price]; stock taken through StockService. */
    private function sale(Store $store, array $lines, array $order = []): Order
    {
        $subtotal = array_sum(array_map(fn ($l) => $l[1] * $l[2], $lines));
        $model = Order::create($order + [
            'store_id' => $store->id, 'order_number' => 'BS-' . (++$this->orderSeq), 'customer_name' => 'Cliente',
            'subtotal' => $subtotal, 'discount' => 0, 'total' => $subtotal, 'currency' => 'PEN',
            'status' => 'delivered', 'payment_status' => 'paid', 'payment_method' => 'yape',
        ]);
        foreach ($lines as [$product, $qty, $price, $fromStock]) {
            $model->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'price' => $price, 'quantity' => $qty, 'subtotal' => $qty * $price]);
            if ($fromStock ?? true) {
                $this->stock()->sell($product->fresh(), null, $qty, $model->order_number);
            }
        }

        return $model;
    }

    // ─── Lots ────────────────────────────────────────────────────

    public function test_an_entry_creates_a_lot_with_cost_and_expiry(): void
    {
        [$user, $store] = $this->owner();
        $product = $this->product($store);

        $this->actingAs($user)->post(route('dashboard.inventario.adjust', $product), [
            'type' => 'in', 'quantity' => 12, 'unit_cost' => '45.50', 'lot_code' => 'L2311', 'expires_at' => '2027-03-31', 'reason' => 'Compra',
        ])->assertRedirect();

        $lot = StockLot::sole();
        $this->assertSame(['L2311', '2027-03-31', 12, 12, 'receipt'], [$lot->code, $lot->expires_at->toDateString(), $lot->quantity_received, $lot->quantity_remaining, $lot->origin]);
        $this->assertEquals(45.5, (float) $lot->unit_cost);
        $this->assertEquals(546.0, (float) InventoryMovement::sole()->total_cost);
        $this->assertEquals(45.5, (float) $product->fresh()->cost_price, 'The last purchase cost becomes the product cost');
        $this->assertSame(12, $product->fresh()->stock);
    }

    public function test_stock_from_before_lots_becomes_an_opening_lot_at_cost_price(): void
    {
        [, $store] = $this->owner();
        $product = $this->product($store, ['stock' => 10, 'cost_price' => 30]);

        $movement = $this->stock()->sell($product, null, 4, 'BS-X');

        $opening = StockLot::sole();
        $this->assertSame(['opening', 10, 6], [$opening->origin, $opening->quantity_received, $opening->quantity_remaining]);
        $this->assertEquals([30.0, 120.0], [(float) $movement->unit_cost, (float) $movement->total_cost]);
    }

    public function test_sales_take_what_expires_first_then_the_oldest(): void
    {
        [, $store] = $this->owner();
        $product = $this->product($store);
        $this->stock()->adjust($product, 'in', 5, null, null, ['unit_cost' => 10]);                                     // no date, oldest
        $this->travel(1)->days();
        $this->stock()->adjust($product, 'in', 5, null, null, ['unit_cost' => 20, 'expires_at' => now()->addMonths(2)->toDateString()]);
        $this->travel(1)->days();
        $this->stock()->adjust($product, 'in', 5, null, null, ['unit_cost' => 30, 'expires_at' => now()->addMonth()->toDateString()]);

        $movement = $this->stock()->sell($product->fresh(), null, 8, 'BS-1');

        // 5 from the lot expiring in a month (30) + 3 from the one expiring in two (20).
        $this->assertEquals(5 * 30 + 3 * 20, (float) $movement->total_cost);
        $this->assertEquals([5, 2, 0], StockLot::orderBy('id')->pluck('quantity_remaining')->all());
        $this->assertSame(7, $product->fresh()->stock);
        $this->assertSame(2, \DB::table('stock_lot_movements')->where('inventory_movement_id', $movement->id)->count(), 'Each lot it came from is traceable');
    }

    public function test_losses_and_counts_move_the_lots_too(): void
    {
        [, $store] = $this->owner();
        $product = $this->product($store, ['cost_price' => 8]);
        $this->stock()->adjust($product, 'in', 10, null, null, ['unit_cost' => 8]);

        $loss = $this->stock()->adjust($product->fresh(), 'loss', 3, 'Rotura', null);
        $this->assertEquals(24.0, (float) $loss->total_cost);

        $count = $this->stock()->adjust($product->fresh(), 'adjustment', 9, 'Conteo', null);
        $this->assertSame(2, $count->quantity);
        $this->assertSame('adjustment', StockLot::latest('id')->first()->origin);
        $this->assertSame(9, (int) StockLot::sum('quantity_remaining'));
    }

    public function test_product_form_changes_are_reconciled(): void
    {
        [, $store] = $this->owner();
        $product = $this->product($store, ['stock' => 0, 'cost_price' => 5]);
        $this->stock()->adjust($product, 'in', 10, null, null);

        $product->update(['stock' => 4]);
        $this->stock()->reconcile($product);
        $this->assertSame(4, (int) StockLot::sum('quantity_remaining'));

        $product->update(['stock' => 7]);
        $this->stock()->reconcile($product);
        $this->assertSame(7, (int) StockLot::sum('quantity_remaining'));
    }

    public function test_a_lot_without_cost_leaves_the_sale_cost_unknown(): void
    {
        [, $store] = $this->owner();
        $product = $this->product($store);
        $this->stock()->adjust($product, 'in', 5, null, null);

        $movement = $this->stock()->sell($product->fresh(), null, 2, 'BS-1');

        $this->assertNull($movement->total_cost);
        $this->assertNull(StockLot::sole()->unit_cost);
    }

    public function test_without_the_migration_stock_moves_as_before(): void
    {
        [, $store] = $this->owner();
        $product = $this->product($store, ['stock' => 5, 'cost_price' => 3]);
        $this->app->instance(InventorySchema::class, new class extends InventorySchema {
            public function lotsReady(): bool { return false; }
        });

        $movement = $this->stock()->sell($product, null, 2, 'BS-1');

        $this->assertSame(3, $product->fresh()->stock);
        $this->assertNull($movement->total_cost);
        $this->assertSame(0, StockLot::count());
    }

    public function test_api_entries_accept_lot_data(): void
    {
        [$user, $store] = $this->owner();
        $product = $this->product($store);

        $this->actingAs($user, 'sanctum')->postJson("/api/dashboard/inventario/{$product->id}/ajuste", [
            'type' => 'in', 'quantity' => 6, 'unit_cost' => 12, 'lot_code' => 'A1', 'expires_at' => '2027-01-15',
        ])->assertOk()->assertJsonPath('product.stock', 6);

        $this->assertSame('A1', StockLot::sole()->code);
    }

    // ─── Screens ─────────────────────────────────────────────────

    public function test_inventory_pages_show_value_lots_and_expiring_stock(): void
    {
        [$user, $store] = $this->owner();
        $product = $this->product($store, ['name' => 'Jarabe']);
        $this->stock()->adjust($product, 'in', 4, null, null, ['unit_cost' => 12.5, 'code' => 'J-9', 'expires_at' => now()->addDays(10)->toDateString()]);

        $this->actingAs($user)->get(route('dashboard.inventario.index'))->assertOk()
            ->assertSee('S/ 50.00')->assertSee('Por vencer')->assertSee('J-9');
        $this->actingAs($user)->get(route('dashboard.inventario.product', $product))->assertOk()
            ->assertSee('Lotes en stock')->assertSee('S/ 12.50 c/u');
    }

    public function test_csv_export_is_reachable(): void
    {
        [$user, $store] = $this->owner();
        $this->product($store, ['name' => 'Jarabe', 'stock' => 2]);

        $this->actingAs($user)->get(route('dashboard.inventario.export'))->assertOk()->assertSee('Jarabe');
    }

    // ─── Profit ──────────────────────────────────────────────────

    public function test_profit_uses_the_real_cost_of_what_was_sold(): void
    {
        [$user, $store] = $this->owner();
        $shake = $this->product($store, ['name' => 'Batido']);
        $this->stock()->adjust($shake, 'in', 10, null, null, ['unit_cost' => 60]);
        $tea = $this->product($store, ['name' => 'Té', 'cost_price' => 20]);   // made to order: never from stock
        $gift = $this->product($store, ['name' => 'Regalo']);                   // no cost anywhere
        $this->stock()->adjust($gift, 'in', 5, null, null);

        // 2 batidos at 100 with a 10% order discount; 1 té at 50 (no stock movement); 1 regalo at 30.
        $this->sale($store, [[$shake, 2, 100, true]], ['discount' => 20, 'total' => 180]);
        $this->sale($store, [[$tea, 1, 50, false], [$gift, 1, 30, true]]);
        // Not counted: unpaid, cancelled, failed.
        $this->sale($store, [[$shake, 1, 100, true]], ['payment_status' => 'pending']);
        $this->sale($store, [[$shake, 1, 100, true]], ['status' => 'cancelled']);

        $report = app(ProfitReport::class)->build($store, now()->startOfMonth(), now()->endOfDay());
        $rows = collect($report['rows'])->keyBy('name');

        $batido = $rows['Batido'];
        $this->assertEquals([2, 180.0, 120.0, 60.0, 33.3, 'exact'],
            [$batido['units'], $batido['revenue'], $batido['cost'], $batido['profit'], $batido['margin'], $batido['cost_status']]);
        $this->assertEquals([20.0, 'estimated'], [$rows['Té']['cost'], $rows['Té']['cost_status']]);
        $this->assertEquals([0.0, 'missing'], [$rows['Regalo']['cost'], $rows['Regalo']['cost_status']]);
        $this->assertEquals([260.0, 140.0, 120.0], [$report['totals']['revenue'], $report['totals']['cost'], $report['totals']['profit']]);
        $this->assertTrue($report['estimated']);
        $this->assertTrue($report['missing']);

        $this->actingAs($user)->get(route('dashboard.inventario.profit'))->assertOk()
            ->assertSee('S/ 260.00')->assertSee('Batido')->assertSee('46.2%');
    }

    public function test_profit_periods(): void
    {
        [$user] = $this->owner();
        [$from, $to] = ProfitReport::range('mes_pasado');
        $this->assertTrue($from->lt(now()->startOfMonth()) && $to->lt(now()->startOfMonth()));

        $this->actingAs($user)->get(route('dashboard.inventario.profit', ['periodo' => 'anio']))->assertOk()
            ->assertSee('Aún no hay ventas pagadas en este periodo.');
    }
}
