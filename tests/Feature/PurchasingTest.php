<?php

namespace Tests\Feature;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockLot;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** ERP Fase 1c: suppliers and purchase orders; receiving puts stock in at landed cost. */
class PurchasingTest extends TestCase
{
    use RefreshDatabase;

    protected function migrateFreshUsing(): array
    {
        return ['--path' => array_values(array_filter(glob(database_path('migrations/*.php')),
            fn ($path) => !str_ends_with($path, '2026_09_11_182936_update_role_column_in_users_table.php'))), '--realpath' => true];
    }

    private function owner(string $slug = 'ferreteria'): array
    {
        $user = User::factory()->create(['role' => 'store_owner']);
        $store = Store::create(['user_id' => $user->id, 'name' => 'Ferretería ' . $slug, 'slug' => $slug, 'status' => 'active', 'template_name' => 'soft-market']);

        return [$user, $store];
    }

    private function product(Store $store, array $attributes = []): Product
    {
        return Product::create($attributes + [
            'store_id' => $store->id, 'name' => 'Cemento', 'slug' => 'cemento-' . uniqid(), 'price' => 35,
            'stock' => 0, 'track_stock' => true, 'is_active' => true,
        ]);
    }

    private function savePurchase(User $user, array $lines, array $extra = [], ?PurchaseOrder $order = null)
    {
        $payload = $extra + ['items_json' => json_encode($lines)];

        return $order
            ? $this->actingAs($user)->put(route('dashboard.compras.update', $order->id), $payload)
            : $this->actingAs($user)->post(route('dashboard.compras.store'), $payload);
    }

    public function test_owner_creates_a_supplier_and_a_purchase(): void
    {
        [$user, $store] = $this->owner();
        $cement = $this->product($store);

        $this->actingAs($user)->post(route('dashboard.proveedores.store'), ['name' => 'Distribuidora Andina', 'document_number' => '20123456789', 'phone' => '987 654 321'])
            ->assertRedirect(route('dashboard.proveedores.index'));
        $supplier = Supplier::sole();
        $this->assertSame('https://wa.me/51987654321', $supplier->whatsapp_url);

        $this->savePurchase($user, [['product_id' => $cement->id, 'quantity' => 100, 'unit_cost' => 24.5]],
            ['supplier_id' => $supplier->id, 'extra_costs' => 150, 'mark_ordered' => '1'])->assertRedirect();

        $order = PurchaseOrder::with('items')->sole();
        $this->assertSame(['OC-0001', 'ordered'], [$order->number, $order->status]);
        $this->assertEquals([2450.0, 150.0, 2600.0], [(float) $order->subtotal, (float) $order->extra_costs, (float) $order->total]);
        $this->assertNotNull($order->ordered_at);
        $this->assertSame(0, $cement->fresh()->stock, 'Ordering does not touch stock');
    }

    public function test_receiving_spreads_freight_by_value_and_creates_lots(): void
    {
        [$user, $store] = $this->owner();
        $cement = $this->product($store, ['name' => 'Cemento']);
        $nails = $this->product($store, ['name' => 'Clavos']);
        $this->savePurchase($user, [
            ['product_id' => $cement->id, 'quantity' => 100, 'unit_cost' => 24],   // 2400 → 80% of the value
            ['product_id' => $nails->id, 'quantity' => 50, 'unit_cost' => 12],     // 600  → 20%
        ], ['extra_costs' => 300]);
        $order = PurchaseOrder::with('items')->sole();
        [$cementLine, $nailsLine] = [$order->items->firstWhere('product_id', $cement->id), $order->items->firstWhere('product_id', $nails->id)];

        $this->actingAs($user)->post(route('dashboard.compras.receive', $order->id), ['lines' => [
            $cementLine->id => ['quantity' => 100, 'lot_code' => 'CEM-11', 'expires_at' => '2027-02-01'],
            $nailsLine->id  => ['quantity' => 50],
        ]])->assertRedirect(route('dashboard.compras.show', $order->id));

        $this->assertSame('received', $order->fresh()->status);
        // Cemento: 24 + 240/100 = 26.40 · Clavos: 12 + 60/50 = 13.20
        $this->assertEquals(26.4, (float) $cementLine->fresh()->landed_unit_cost);
        $this->assertEquals(13.2, (float) $nailsLine->fresh()->landed_unit_cost);
        $this->assertSame([100, 50], [$cement->fresh()->stock, $nails->fresh()->stock]);
        $this->assertEquals(26.4, (float) $cement->fresh()->cost_price, 'Landed cost becomes the product cost');

        $lot = StockLot::where('product_id', $cement->id)->sole();
        $this->assertSame(['CEM-11', '2027-02-01', 'receipt'], [$lot->code, $lot->expires_at->toDateString(), $lot->origin]);
        $movement = InventoryMovement::where('product_id', $cement->id)->sole();
        $this->assertSame(['in', 'OC-0001'], [$movement->type, $movement->reference]);
        $this->assertEquals(2640.0, (float) $movement->total_cost);
    }

    public function test_partial_receipt_puts_the_freight_on_what_arrived(): void
    {
        [$user, $store] = $this->owner();
        $cement = $this->product($store);
        $nails = $this->product($store, ['name' => 'Clavos']);
        $this->savePurchase($user, [
            ['product_id' => $cement->id, 'quantity' => 10, 'unit_cost' => 20],
            ['product_id' => $nails->id, 'quantity' => 10, 'unit_cost' => 20],
        ], ['extra_costs' => 30]);
        $order = PurchaseOrder::with('items')->sole();

        $this->actingAs($user)->post(route('dashboard.compras.receive', $order->id), ['lines' => [
            $order->items[0]->id => ['quantity' => 5],
            $order->items[1]->id => ['quantity' => 0],
        ]])->assertRedirect();

        $this->assertEquals(26.0, (float) $order->items[0]->fresh()->landed_unit_cost, '20 + 30/5');
        $this->assertNull($order->items[1]->fresh()->landed_unit_cost);
        $this->assertSame([5, 0], [$cement->fresh()->stock, $nails->fresh()->stock]);
    }

    public function test_freight_shares_add_up_exactly(): void
    {
        [$user, $store] = $this->owner();
        $products = collect(range(1, 3))->map(fn ($i) => $this->product($store, ['name' => "P{$i}"]));
        $this->savePurchase($user, $products->map(fn ($p) => ['product_id' => $p->id, 'quantity' => 3, 'unit_cost' => 1])->all(), ['extra_costs' => 10]);
        $order = PurchaseOrder::with('items')->sole();

        $shares = app(\App\Services\Purchasing\PurchaseOrderService::class)
            ->extraCostShares($order->items, $order->items->mapWithKeys(fn ($i) => [$i->id => 3])->all(), 10);

        $this->assertEqualsWithDelta(10.0, array_sum($shares), 0.0001);
        $this->assertEquals([3.33, 3.33, 3.34], array_values($shares));
    }

    public function test_received_or_cancelled_orders_are_locked(): void
    {
        [$user, $store] = $this->owner();
        $cement = $this->product($store);
        $this->savePurchase($user, [['product_id' => $cement->id, 'quantity' => 1, 'unit_cost' => 5]]);
        $order = PurchaseOrder::sole();

        $this->actingAs($user)->post(route('dashboard.compras.cancel', $order->id))->assertRedirect();
        $this->assertSame('cancelled', $order->fresh()->status);

        $this->actingAs($user)->post(route('dashboard.compras.receive', $order->id), ['lines' => [$order->items()->first()->id => ['quantity' => 1]]])
            ->assertSessionHasErrors('items');
        $this->savePurchase($user, [['product_id' => $cement->id, 'quantity' => 9, 'unit_cost' => 5]], [], $order)->assertSessionHasErrors('items');
        $this->assertSame(0, $cement->fresh()->stock);
    }

    public function test_receiving_nothing_is_rejected(): void
    {
        [$user, $store] = $this->owner();
        $cement = $this->product($store);
        $this->savePurchase($user, [['product_id' => $cement->id, 'quantity' => 4, 'unit_cost' => 5]]);
        $order = PurchaseOrder::with('items')->sole();

        $this->actingAs($user)->post(route('dashboard.compras.receive', $order->id), ['lines' => [$order->items[0]->id => ['quantity' => 0]]])
            ->assertSessionHasErrors('items');
        $this->assertSame('draft', $order->fresh()->status);
    }

    public function test_only_own_tracked_products_and_suppliers(): void
    {
        [$user, $store] = $this->owner();
        [, $other] = $this->owner('otra');
        $foreign = $this->product($other);
        $untracked = $this->product($store, ['track_stock' => false]);
        $foreignSupplier = $other->suppliers()->create(['name' => 'Ajeno']);

        $this->savePurchase($user, [['product_id' => $foreign->id, 'quantity' => 1, 'unit_cost' => 1]])->assertSessionHasErrors('items');
        $this->savePurchase($user, [['product_id' => $untracked->id, 'quantity' => 1, 'unit_cost' => 1]])->assertSessionHasErrors('items');
        $this->savePurchase($user, [['product_id' => $this->product($store)->id, 'quantity' => 1, 'unit_cost' => 1]], ['supplier_id' => $foreignSupplier->id])
            ->assertSessionHasErrors('supplier_id');
        $this->assertSame(0, PurchaseOrder::count());

        $otherOrder = PurchaseOrder::create(['store_id' => $other->id, 'number' => 'OC-0001', 'status' => 'draft']);
        $this->actingAs($user)->get(route('dashboard.compras.show', $otherOrder->id))->assertNotFound();
        $this->actingAs($user)->get(route('dashboard.proveedores.edit', $foreignSupplier->id))->assertNotFound();
    }

    public function test_validation_messages(): void
    {
        [$user, $store] = $this->owner();
        $cement = $this->product($store);

        $this->savePurchase($user, [])->assertSessionHasErrors(['items' => 'Agrega al menos un producto a la compra.']);
        $this->savePurchase($user, [
            ['product_id' => $cement->id, 'quantity' => 1, 'unit_cost' => 1],
            ['product_id' => $cement->id, 'quantity' => 2, 'unit_cost' => 1],
        ])->assertSessionHasErrors('items.1.product_id');
    }

    public function test_numbers_are_sequential_per_store(): void
    {
        [$user, $store] = $this->owner();
        [$otherUser, $other] = $this->owner('otra');
        $this->savePurchase($user, [['product_id' => $this->product($store)->id, 'quantity' => 1, 'unit_cost' => 1]]);
        $this->savePurchase($user, [['product_id' => $this->product($store)->id, 'quantity' => 1, 'unit_cost' => 1]]);
        $this->savePurchase($otherUser, [['product_id' => $this->product($other)->id, 'quantity' => 1, 'unit_cost' => 1]]);

        $this->assertSame(['OC-0001', 'OC-0002'], $store->purchaseOrders()->orderBy('id')->pluck('number')->all());
        $this->assertSame(['OC-0001'], $other->purchaseOrders()->pluck('number')->all());
    }

    public function test_pages_render(): void
    {
        [$user, $store] = $this->owner();
        $cement = $this->product($store, ['name' => 'Cemento Sol']);
        $supplier = $store->suppliers()->create(['name' => 'Distribuidora Andina']);
        $this->savePurchase($user, [['product_id' => $cement->id, 'quantity' => 10, 'unit_cost' => 24]], ['supplier_id' => $supplier->id, 'extra_costs' => 20]);
        $order = PurchaseOrder::with('items')->sole();

        $this->actingAs($user)->get(route('dashboard.compras.index'))->assertOk()->assertSee('OC-0001')->assertSee('Distribuidora Andina');
        $this->actingAs($user)->get(route('dashboard.compras.create'))->assertOk()->assertSee('Cemento Sol');
        $this->actingAs($user)->get(route('dashboard.compras.edit', $order->id))->assertOk();
        $this->actingAs($user)->get(route('dashboard.compras.show', $order->id))->assertOk()->assertSee('¿Llegó la mercadería?');
        $this->actingAs($user)->get(route('dashboard.proveedores.index'))->assertOk()->assertSee('Distribuidora Andina');
        $this->actingAs($user)->get(route('dashboard.proveedores.edit', $supplier->id))->assertOk()->assertSee('OC-0001');

        $this->actingAs($user)->post(route('dashboard.compras.receive', $order->id), ['lines' => [$order->items[0]->id => ['quantity' => 10]]]);
        $this->actingAs($user)->get(route('dashboard.compras.show', $order->id))->assertOk()->assertSee('S/ 26.00 c/u');
        $this->actingAs($user)->get(route('dashboard.inventario.product', $cement))->assertOk()
            ->assertSee('Últimas compras')->assertSee('Distribuidora Andina')->assertSee('+ gastos');
    }

    public function test_supplier_with_purchases_cannot_be_deleted(): void
    {
        [$user, $store] = $this->owner();
        $supplier = $store->suppliers()->create(['name' => 'Andina']);
        $this->savePurchase($user, [['product_id' => $this->product($store)->id, 'quantity' => 1, 'unit_cost' => 1]], ['supplier_id' => $supplier->id]);

        $this->actingAs($user)->delete(route('dashboard.proveedores.destroy', $supplier->id))->assertSessionHas('error');
        $this->assertModelExists($supplier);

        $empty = $store->suppliers()->create(['name' => 'Vacío']);
        $this->actingAs($user)->delete(route('dashboard.proveedores.destroy', $empty->id))->assertRedirect(route('dashboard.proveedores.index'));
        $this->assertModelMissing($empty);
    }
}
