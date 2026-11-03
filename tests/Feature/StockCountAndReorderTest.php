<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockCount;
use App\Models\StockLot;
use App\Models\Store;
use App\Models\User;
use App\Services\Inventory\ReorderAdvisor;
use App\Services\Inventory\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** ERP Fase 1d: physical counts from the phone and reorder suggestions. */
class StockCountAndReorderTest extends TestCase
{
    use RefreshDatabase;

    protected function migrateFreshUsing(): array
    {
        return ['--path' => array_values(array_filter(glob(database_path('migrations/*.php')),
            fn ($path) => !str_ends_with($path, '2026_09_11_182936_update_role_column_in_users_table.php'))), '--realpath' => true];
    }

    private function owner(string $slug = 'bodega'): array
    {
        $user = User::factory()->create(['role' => 'store_owner']);
        $store = Store::create(['user_id' => $user->id, 'name' => 'Bodega ' . $slug, 'slug' => $slug, 'status' => 'active', 'template_name' => 'soft-market']);

        return [$user, $store];
    }

    private function product(Store $store, array $attributes = []): Product
    {
        return Product::create($attributes + [
            'store_id' => $store->id, 'name' => 'Arroz', 'slug' => 'arroz-' . uniqid(), 'price' => 5,
            'stock' => 0, 'track_stock' => true, 'is_active' => true, 'low_stock_alert' => 0,
        ]);
    }

    private function stock(): StockService
    {
        return app(StockService::class);
    }

    private function record(User $user, StockCount $count, Product $product, ?int $qty)
    {
        $item = $count->items()->where('product_id', $product->id)->sole();

        return $this->actingAs($user)->patchJson(route('dashboard.inventario.conteos.record', $count->id), ['item_id' => $item->id, 'counted_qty' => $qty]);
    }

    // ─── Counts ──────────────────────────────────────────────────

    public function test_a_count_corrects_stock_by_the_difference_and_values_it(): void
    {
        [$user, $store] = $this->owner();
        $rice = $this->product($store, ['name' => 'Arroz']);
        $sugar = $this->product($store, ['name' => 'Azúcar']);
        $this->stock()->adjust($rice, 'in', 20, null, null, ['unit_cost' => 3]);
        $this->stock()->adjust($sugar, 'in', 10, null, null, ['unit_cost' => 4]);

        $this->actingAs($user)->post(route('dashboard.inventario.conteos.store'), ['blind' => '1'])->assertRedirect();
        $count = StockCount::sole();
        $this->assertSame(['CF-0001', 'open', true, 2], [$count->number, $count->status, $count->blind, $count->items()->count()]);

        $this->record($user, $count, $rice, 17)->assertOk()->assertJson(['counted_qty' => 17, 'expected_qty' => 20, 'variance' => -3, 'variance_value' => -9]);
        $this->record($user, $count, $sugar, 10)->assertOk()->assertJson(['variance' => 0]);

        $this->actingAs($user)->post(route('dashboard.inventario.conteos.apply', $count->id))
            ->assertRedirect()->assertSessionHas('success', 'Aplicamos el conteo CF-0001: faltó mercadería por S/ 9.00.');

        $this->assertSame([17, 10], [$rice->fresh()->stock, $sugar->fresh()->stock]);
        $this->assertSame(['applied', '-9.00'], [$count->fresh()->status, $count->fresh()->variance_value]);
        $movement = InventoryMovement::where('product_id', $rice->id)->latest('id')->first();
        $this->assertSame(['adjustment', -3, 'CF-0001'], [$movement->type, $movement->quantity, $movement->reference]);
        $this->assertSame(17, (int) StockLot::where('product_id', $rice->id)->sum('quantity_remaining'));
    }

    public function test_sales_during_the_count_are_not_losses(): void
    {
        [$user, $store] = $this->owner();
        $rice = $this->product($store);
        $this->stock()->adjust($rice, 'in', 20, null, null, ['unit_cost' => 3]);
        $this->actingAs($user)->post(route('dashboard.inventario.conteos.store'), []);
        $count = StockCount::sole();

        $this->stock()->sell($rice->fresh(), null, 2, 'V-1');           // sold before counting it: shelf has 18
        $this->record($user, $count, $rice, 18)->assertJson(['expected_qty' => 18, 'variance' => 0]);
        $this->stock()->sell($rice->fresh(), null, 5, 'V-2');           // sold after counting it

        $this->actingAs($user)->post(route('dashboard.inventario.conteos.apply', $count->id))->assertRedirect();
        $this->assertSame(13, $rice->fresh()->stock, 'No correction: both sales stand');
    }

    public function test_extra_units_enter_as_an_adjustment_lot(): void
    {
        [$user, $store] = $this->owner();
        $rice = $this->product($store, ['cost_price' => 3]);
        $this->stock()->adjust($rice, 'in', 5, null, null);
        $this->actingAs($user)->post(route('dashboard.inventario.conteos.store'), []);
        $count = StockCount::sole();

        $this->record($user, $count, $rice, 8)->assertJson(['variance' => 3, 'variance_value' => 9]);
        $this->actingAs($user)->post(route('dashboard.inventario.conteos.apply', $count->id))
            ->assertSessionHas('success', 'Aplicamos el conteo CF-0001. El stock quedó igual a lo que contaste.');

        $this->assertSame(8, $rice->fresh()->stock);
        $this->assertSame('adjustment', StockLot::latest('id')->first()->origin);
        $this->assertEquals(9.0, (float) $count->fresh()->variance_value);
    }

    public function test_uncounted_products_are_left_alone_and_clearing_works(): void
    {
        [$user, $store] = $this->owner();
        $rice = $this->product($store);
        $sugar = $this->product($store, ['name' => 'Azúcar']);
        $this->stock()->adjust($rice, 'in', 5, null, null);
        $this->stock()->adjust($sugar, 'in', 5, null, null);
        $this->actingAs($user)->post(route('dashboard.inventario.conteos.store'), []);
        $count = StockCount::sole();

        $this->record($user, $count, $rice, 1);
        $this->record($user, $count, $sugar, 0);
        $this->record($user, $count, $sugar, null)->assertJson(['counted_qty' => null, 'variance' => null]);

        $this->actingAs($user)->post(route('dashboard.inventario.conteos.apply', $count->id));
        $this->assertSame([1, 5], [$rice->fresh()->stock, $sugar->fresh()->stock]);
    }

    public function test_a_count_by_category_and_its_rules(): void
    {
        [$user, $store] = $this->owner();
        $category = Category::create(['store_id' => $store->id, 'name' => 'Abarrotes', 'slug' => 'abarrotes']);
        $this->product($store, ['category_id' => $category->id]);
        $this->product($store, ['name' => 'Detergente']);
        $this->product($store, ['name' => 'Servicio', 'track_stock' => false]);

        $this->actingAs($user)->post(route('dashboard.inventario.conteos.store'), ['category_id' => $category->id]);
        $count = StockCount::sole();
        $this->assertSame(1, $count->items()->count());

        $this->actingAs($user)->post(route('dashboard.inventario.conteos.apply', $count->id))->assertSessionHasErrors('count');
        $this->actingAs($user)->post(route('dashboard.inventario.conteos.cancel', $count->id))->assertRedirect();
        $this->assertSame('cancelled', $count->fresh()->status);
        $this->record($user, $count, Product::where('category_id', $category->id)->sole(), 3)->assertStatus(422);

        [, $other] = $this->owner('otra');
        $foreignCategory = Category::create(['store_id' => $other->id, 'name' => 'Ajena', 'slug' => 'ajena']);
        $this->actingAs($user)->post(route('dashboard.inventario.conteos.store'), ['category_id' => $foreignCategory->id])->assertSessionHasErrors('category_id');
    }

    public function test_counts_belong_to_their_store(): void
    {
        [$user] = $this->owner();
        [$otherUser, $other] = $this->owner('otra');
        $this->product($other);
        $this->actingAs($otherUser)->post(route('dashboard.inventario.conteos.store'), []);
        $foreign = StockCount::sole();

        $this->actingAs($user)->get(route('dashboard.inventario.conteos.show', $foreign->id))->assertNotFound();
        $this->actingAs($user)->post(route('dashboard.inventario.conteos.apply', $foreign->id))->assertNotFound();
    }

    public function test_count_pages_render(): void
    {
        [$user, $store] = $this->owner();
        $this->product($store, ['name' => 'Arroz Costeño', 'sku' => 'ARR-01']);
        $this->actingAs($user)->post(route('dashboard.inventario.conteos.store'), ['blind' => '1']);

        $this->actingAs($user)->get(route('dashboard.inventario.conteos.index'))->assertOk()->assertSee('CF-0001')->assertSee('0 de 1 contados');
        // The sheet is drawn by Alpine from JSON, so the product name is checked in the view data.
        $this->actingAs($user)->get(route('dashboard.inventario.conteos.show', StockCount::sole()->id))->assertOk()
            ->assertSee('Revisar diferencias')
            ->assertViewHas('items', fn ($items) => $items->first()->product->name === 'Arroz Costeño');
    }

    // ─── Reorder ─────────────────────────────────────────────────

    public function test_suggests_what_will_run_out_before_a_purchase_arrives(): void
    {
        [, $store] = $this->owner();
        $fast = $this->product($store, ['name' => 'Rápido']);
        $slow = $this->product($store, ['name' => 'Lento']);
        $alerted = $this->product($store, ['name' => 'Sin ventas en alerta', 'low_stock_alert' => 5]);
        $quiet = $this->product($store, ['name' => 'Sin ventas tranquilo', 'low_stock_alert' => 2]);
        $this->stock()->adjust($fast, 'in', 70, null, null, ['unit_cost' => 2]);
        $this->stock()->adjust($slow, 'in', 100, null, null);
        $this->stock()->adjust($alerted, 'in', 3, null, null);
        $this->stock()->adjust($quiet, 'in', 10, null, null);
        $this->stock()->sell($fast->fresh(), null, 60, 'V-1');      // 2/day → 10 left = 5 days
        $this->stock()->sell($slow->fresh(), null, 30, 'V-2');      // 1/day → 70 left, plenty

        $rows = collect(app(ReorderAdvisor::class)->suggestions($store));

        $this->assertSame(['Rápido', 'Sin ventas en alerta'], $rows->pluck('name')->all());
        $first = $rows->first();
        $this->assertSame([10, 60, 2.0, 5], [$first['stock'], $first['sold'], $first['daily'], $first['days_left']]);
        $this->assertSame(2 * (7 + 30) - 10, $first['suggested']);
        $this->assertEquals(2.0, $first['unit_cost']);
        $this->assertSame(5 * 2 - 3, $rows[1]['suggested']);
    }

    public function test_stagnant_stock_and_its_value(): void
    {
        [, $store] = $this->owner();
        $old = $this->product($store, ['name' => 'Viejo']);
        $this->stock()->adjust($old, 'in', 4, null, null, ['unit_cost' => 10]);
        $old->forceFill(['created_at' => now()->subDays(200)])->save();
        $selling = $this->product($store, ['name' => 'Vendido']);
        $this->stock()->adjust($selling, 'in', 4, null, null, ['unit_cost' => 10]);
        $selling->forceFill(['created_at' => now()->subDays(200)])->save();
        $this->stock()->sell($selling->fresh(), null, 1, 'V-1');
        $this->product($store, ['name' => 'Nuevo', 'stock' => 3]);   // too new to judge

        $rows = app(ReorderAdvisor::class)->stagnant($store);

        $this->assertSame([['product_id' => $old->id, 'name' => 'Viejo', 'stock' => 4, 'value' => 40.0]], $rows);
    }

    public function test_reorder_builds_a_draft_purchase(): void
    {
        [$user, $store] = $this->owner();
        $fast = $this->product($store, ['name' => 'Rápido']);
        $this->stock()->adjust($fast, 'in', 70, null, null, ['unit_cost' => 2.5]);
        $this->stock()->sell($fast->fresh(), null, 60, 'V-1');
        $supplier = $store->suppliers()->create(['name' => 'Mayorista Lima']);

        $this->actingAs($user)->get(route('dashboard.inventario.reorder'))->assertOk()->assertSee('Rápido')->assertSee('alcanza para 5 días');

        $this->actingAs($user)->post(route('dashboard.inventario.reorder.purchase'), [
            'supplier_id' => $supplier->id,
            'lines'       => [$fast->id => ['selected' => '1', 'quantity' => 50]],
        ])->assertRedirect();

        $order = PurchaseOrder::with('items')->sole();
        $this->assertSame(['draft', $supplier->id], [$order->status, $order->supplier_id]);
        $this->assertSame(50, $order->items[0]->quantity_ordered);
        $this->assertEquals(2.5, (float) $order->items[0]->unit_cost, 'Cost comes from the server, not the form');

        $this->actingAs($user)->post(route('dashboard.inventario.reorder.purchase'), ['lines' => [$fast->id => ['quantity' => 5]]])
            ->assertSessionHasErrors(['items' => 'Marca al menos un producto para comprar.']);
    }
}
