<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockLot;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Alta rápida: varios productos de una vez, con su stock inicial a costo. */
class QuickAddProductsTest extends TestCase
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

    private function owner(string $slug = 'ferre'): array
    {
        $user = User::factory()->create(['role' => 'store_owner']);
        $store = Store::create(['user_id' => $user->id, 'name' => 'Ferretería', 'slug' => $slug, 'status' => 'active', 'template_name' => 'minimal-light', 'mode' => 'pos']);

        return [$user, $store];
    }

    private function save(User $user, array $rows)
    {
        return $this->actingAs($user)->postJson(route('dashboard.productos.rapido.store'), ['rows' => $rows]);
    }

    public function test_the_page_loads_from_the_products_list_and_the_pos_home(): void
    {
        [$user, $store] = $this->owner();
        Category::create(['store_id' => $store->id, 'name' => 'Herramientas', 'slug' => 'herramientas']);

        $this->actingAs($user)->get(route('dashboard.productos.index'))->assertOk()->assertSee('Alta rápida')->assertSee(route('dashboard.productos.rapido'), false);
        $this->actingAs($user)->get(route('dashboard.productos.rapido'))->assertOk()->assertSee('Alta rápida de productos')->assertSee('Herramientas')->assertSee('Margen sugerido');
        $this->actingAs($user)->get(route('dashboard.index'))->assertOk()->assertSee(route('dashboard.productos.rapido'), false);
    }

    public function test_it_creates_products_with_their_stock_as_a_lot_at_cost(): void
    {
        [$user, $store] = $this->owner();

        $this->save($user, [
            ['sku' => '7750001112223', 'name' => 'Martillo 16 oz', 'category' => 'Herramientas', 'cost' => '17.50', 'price' => '28', 'stock' => '24', 'unit' => 'unidad'],
            ['name' => 'Clavos de 2"', 'category' => 'herramientas', 'cost' => '4,60', 'price' => 'S/ 6.80', 'stock' => '120', 'unit' => 'kg'],
            ['name' => 'Servicio de corte', 'price' => '10'],
        ])->assertCreated()->assertJsonPath('created', 3)->assertJsonPath('message', 'Guardamos 3 productos.');

        $hammer = Product::where('store_id', $store->id)->where('sku', '7750001112223')->firstOrFail();
        $nails = Product::where('store_id', $store->id)->where('name', 'Clavos de 2"')->firstOrFail();
        $service = Product::where('store_id', $store->id)->where('name', 'Servicio de corte')->firstOrFail();

        $this->assertSame([28.0, 17.5, 24, true, true], [(float) $hammer->price, (float) $hammer->cost_price, $hammer->stock, $hammer->track_stock, $hammer->is_active]);
        $this->assertSame([6.8, 4.6, 120, 'kg'], [(float) $nails->price, (float) $nails->cost_price, $nails->stock, $nails->unit]);
        $this->assertSame([0, 'unidad', null], [$service->stock, $service->unit, $service->category_id]);

        // The opening stock is a lot with the cost typed: margins are right from the first sale.
        $lot = StockLot::where('product_id', $hammer->id)->sole();
        $this->assertSame([24, 24], [$lot->quantity_received, $lot->quantity_remaining]);
        $this->assertEqualsWithDelta(17.5, (float) $lot->unit_cost, 0.0001);
        $this->assertSame(0, StockLot::where('product_id', $service->id)->count());
        $this->assertSame((int) $hammer->stock, (int) StockLot::where('product_id', $hammer->id)->sum('quantity_remaining'));

        // "Herramientas" and "herramientas" are one category, created once.
        $this->assertSame(1, Category::where('store_id', $store->id)->count());
        $this->assertSame($hammer->category_id, $nails->category_id);
    }

    public function test_it_is_all_or_nothing_and_points_at_each_bad_row(): void
    {
        [$user, $store] = $this->owner();

        $response = $this->save($user, [
            ['name' => 'Bueno', 'price' => '10'],
            ['name' => '', 'price' => '5'],
            ['name' => 'Sin precio', 'price' => '0'],
            ['name' => 'Costo raro', 'cost' => 'abc', 'price' => '9'],
            ['name' => 'Stock con decimales', 'price' => '9', 'stock' => '2.5'],
        ])->assertStatus(422);

        $response->assertJsonValidationErrors(['rows.1.name', 'rows.2.price', 'rows.3.cost', 'rows.4.stock']);
        $this->assertFalse($response->json('errors.rows\.0\.name') !== null, 'the good row has no error');
        $this->assertSame(0, Product::where('store_id', $store->id)->count(), 'one bad row saves none');
    }

    public function test_codes_cannot_repeat_in_the_store_nor_in_the_same_table(): void
    {
        [$user, $store] = $this->owner();
        Product::create(['store_id' => $store->id, 'name' => 'Existente', 'slug' => 'existente', 'sku' => 'ABC-1', 'price' => 5, 'stock' => 0, 'track_stock' => true, 'is_active' => true]);

        $this->save($user, [
            ['sku' => 'abc-1', 'name' => 'Repetido en la tienda', 'price' => '5'],
            ['sku' => 'NUEVO-1', 'name' => 'Uno', 'price' => '5'],
            ['sku' => 'nuevo-1', 'name' => 'Repetido en la tabla', 'price' => '5'],
        ])->assertStatus(422)->assertJsonValidationErrors(['rows.0.sku', 'rows.2.sku'])->assertJsonMissingValidationErrors('rows.1.sku');

        $this->assertSame(1, Product::where('store_id', $store->id)->count());
    }

    public function test_the_same_code_may_exist_in_two_stores_and_names_can_repeat(): void
    {
        [$userA, $storeA] = $this->owner('a');
        [$userB, $storeB] = $this->owner('b');

        $this->save($userA, [['sku' => 'X-1', 'name' => 'Cinta', 'price' => '3']])->assertCreated();
        $this->save($userB, [['sku' => 'X-1', 'name' => 'Cinta', 'price' => '3']])->assertCreated();
        $this->save($userA, [['name' => 'Cinta', 'price' => '4']])->assertCreated();

        $slugs = Product::where('store_id', $storeA->id)->pluck('slug')->all();
        $this->assertSame(['cinta', 'cinta-2'], $slugs, 'two products with the same name get distinct slugs');
        $this->assertSame(1, Product::where('store_id', $storeB->id)->count());
    }

    public function test_it_limits_the_batch_and_rejects_empty_ones(): void
    {
        [$user] = $this->owner();

        $this->save($user, [])->assertStatus(422)->assertJsonValidationErrors('rows');
        $rows = array_fill(0, 201, ['name' => 'X', 'price' => '1']);
        $this->save($user, $rows)->assertStatus(422)->assertJsonValidationErrors('rows');
    }

    public function test_products_go_to_the_current_store_only_and_guests_are_refused(): void
    {
        [$user, $store] = $this->owner('mio');
        [, $other] = $this->owner('ajena');

        $this->save($user, [['name' => 'Mío', 'price' => '5']])->assertCreated();
        $this->assertSame(1, Product::where('store_id', $store->id)->count());
        $this->assertSame(0, Product::where('store_id', $other->id)->count());

        auth()->logout();
        $this->postJson(route('dashboard.productos.rapido.store'), ['rows' => [['name' => 'X', 'price' => '1']]])->assertUnauthorized();
    }

    public function test_the_new_products_can_be_sold_right_away_with_the_margin_from_their_cost(): void
    {
        [$user, $store] = $this->owner();
        $this->save($user, [['name' => 'Cemento', 'cost' => '25.20', 'price' => '29.50', 'stock' => '10']])->assertCreated();
        $product = Product::where('store_id', $store->id)->sole();

        $register = \App\Models\Register::create(['store_id' => $store->id, 'name' => 'Caja 1']);
        $session = app(\App\Services\Pos\RegisterService::class)->open($register, 100, $user->id);
        app(\App\Services\Pos\PosSaleService::class)->sell($session, ['uuid' => (string) \Illuminate\Support\Str::uuid(), 'items' => [['product_id' => $product->id, 'quantity' => 2]], 'payments' => [['method' => 'cash', 'amount' => 59]]], $user->id);

        [$from, $to] = \App\Services\Reports\ProfitAndLoss::range('hoy');
        $report = app(\App\Services\Reports\ProfitAndLoss::class)->build($store, $from, $to);

        $this->assertEqualsWithDelta(59.0, $report['totals']['income'], 0.001);
        $this->assertEqualsWithDelta(50.4, $report['totals']['cost'], 0.001);   // 2 × 25.20 from the opening lot
        $this->assertEqualsWithDelta(8.6, $report['totals']['gross'], 0.001);
        $this->assertFalse($report['estimated']);
        $this->assertSame(8, $product->fresh()->stock);
    }
}
