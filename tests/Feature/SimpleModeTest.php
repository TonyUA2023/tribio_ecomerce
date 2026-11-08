<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RegisterSession;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Modo simple: vender desde el celular en dos toques (bodega, puesto, ambulante). */
class SimpleModeTest extends TestCase
{
    use RefreshDatabase;

    protected function migrateFreshUsing(): array
    {
        return ['--path' => array_values(array_filter(glob(database_path('migrations/*.php')),
            fn ($path) => !str_ends_with($path, '2026_09_11_182936_update_role_column_in_users_table.php'))), '--realpath' => true];
    }

    private function owner(string $mode = Store::MODE_SIMPLE, string $slug = 'bodega'): array
    {
        $user = User::factory()->create(['role' => 'store_owner']);
        $store = Store::create(['user_id' => $user->id, 'name' => 'Bodega ' . $slug, 'slug' => $slug, 'status' => 'active', 'template_name' => 'soft-market', 'mode' => $mode]);

        return [$user, $store];
    }

    private function product(Store $store, string $name = 'Gaseosa 500 ml', float $price = 3.5, array $extra = []): Product
    {
        return Product::create($extra + [
            'store_id' => $store->id, 'name' => $name, 'slug' => str($name)->slug() . '-' . uniqid(), 'price' => $price, 'cost_price' => 2,
            'stock' => 20, 'track_stock' => true, 'is_active' => true,
        ]);
    }

    private function sell(User $user, array $items, string $method = 'cash', array $extra = [])
    {
        return $this->actingAs($user)->postJson(route('dashboard.simple.sell'), ['uuid' => (string) \Illuminate\Support\Str::uuid(), 'method' => $method, 'items' => $items] + $extra);
    }

    public function test_the_account_type_has_no_storefront_and_lands_on_the_selling_screen(): void
    {
        [$user, $store] = $this->owner();

        $this->assertTrue($store->isSimple());
        $this->assertTrue($store->isPosOnly());
        $this->assertFalse($store->hasStorefront());
        $this->assertTrue($store->hasPos());
        $this->assertFalse(Store::online()->whereKey($store->id)->exists(), 'No public page nor directory listing');
        $this->actingAs($user)->get(route('dashboard.index'))->assertRedirect(route('dashboard.simple.index'));
        $this->get(route('store.show', $store->slug))->assertNotFound();
    }

    public function test_selling_screen_lists_products_and_keeps_the_menu_short(): void
    {
        [$user, $store] = $this->owner();
        $this->product($store, 'Gaseosa 500 ml');
        $this->product($store, 'Pan francés', 0.3, ['has_variants' => true]);

        $this->actingAs($user)->get(route('dashboard.simple.index'))->assertOk()
            ->assertSee('Gaseosa 500 ml')->assertDontSee('Pan francés')->assertSee('simple_buscar', false)->assertSee('Monto')
            ->assertSee('Cómo voy hoy')->assertDontSee('Zonas de envío');
    }

    public function test_wholesale_prices_reach_the_phone_and_the_sale_charges_them(): void
    {
        [$user, $store] = $this->owner();
        $soda = $this->product($store, 'Gaseosa 500 ml', 3.5, ['price_tiers' => [['min_qty' => 6, 'price' => 3.0]]]);

        $catalog = app(\App\Services\Pos\SimpleSales::class)->catalog($store);
        $this->assertSame([['min_qty' => 6, 'price' => 3.0]], $catalog[0]['tiers']);

        $this->sell($user, [['product_id' => $soda->id, 'quantity' => 6]], 'cash', ['tendered' => 20])->assertOk()->assertJson(['total' => 18.0, 'change' => 2.0]);
    }

    public function test_one_cash_sale_moves_stock_pays_and_gives_change(): void
    {
        [$user, $store] = $this->owner();
        $soda = $this->product($store);

        $this->sell($user, [['product_id' => $soda->id, 'quantity' => 3]], 'cash', ['tendered' => 20])
            ->assertOk()->assertJson(['ok' => true, 'total' => 10.5, 'change' => 9.5, 'today' => ['count' => 1, 'total' => 10.5]]);

        $order = Order::sole();
        $this->assertSame(['pos', 'delivered', 'paid', 'cash'], [$order->source, $order->status, $order->payment_status, $order->payment_method]);
        $this->assertSame(17, $soda->fresh()->stock);
        $this->assertSame(1, RegisterSession::where('status', 'open')->count(), 'The shift opened by itself');
    }

    public function test_yape_pays_the_exact_total_and_a_short_cash_tender_counts_as_exact(): void
    {
        [$user, $store] = $this->owner();
        $soda = $this->product($store);

        $this->sell($user, [['product_id' => $soda->id, 'quantity' => 2]], 'yape')->assertOk()->assertJson(['total' => 7.0, 'change' => 0.0]);
        $this->sell($user, [['product_id' => $soda->id, 'quantity' => 1]], 'cash', ['tendered' => 1])->assertOk()->assertJson(['total' => 3.5, 'change' => 0.0]);
        $this->assertSame(['yape', 'cash'], Order::orderBy('id')->pluck('payment_method')->all());
        $this->sell($user, [['product_id' => $soda->id, 'quantity' => 1]], 'card')->assertUnprocessable();
    }

    public function test_a_sale_by_amount_needs_no_product_and_touches_no_stock(): void
    {
        [$user, $store] = $this->owner();
        $soda = $this->product($store);

        $this->sell($user, [['free' => true, 'name' => 'Arroz 1/2 kg', 'price' => 2.5, 'quantity' => 2], ['product_id' => $soda->id, 'quantity' => 1]], 'plin')
            ->assertOk()->assertJson(['total' => 8.5]);

        $free = OrderItem::whereNull('product_id')->sole();
        $this->assertSame(['Arroz 1/2 kg', 5.0], [$free->product_name, (float) $free->subtotal]);
        $this->assertSame(19, $soda->fresh()->stock);
        $this->sell($user, [['free' => true, 'name' => 'x', 'price' => 0, 'quantity' => 1]])->assertUnprocessable();
    }

    public function test_a_retried_sale_never_sells_twice(): void
    {
        [$user, $store] = $this->owner();
        $soda = $this->product($store);
        $body = ['uuid' => 'abc-123', 'method' => 'yape', 'items' => [['product_id' => $soda->id, 'quantity' => 1]]];

        $this->actingAs($user)->postJson(route('dashboard.simple.sell'), $body)->assertOk()->assertJson(['repeated' => false]);
        $this->actingAs($user)->postJson(route('dashboard.simple.sell'), $body)->assertOk()->assertJson(['repeated' => true, 'today' => ['count' => 1]]);

        $this->assertSame(1, Order::count());
        $this->assertSame(19, $soda->fresh()->stock);
    }

    public function test_yesterdays_shift_is_closed_by_itself_and_today_gets_a_new_one(): void
    {
        [$user, $store] = $this->owner();
        $soda = $this->product($store);

        $this->travelTo(now('America/Lima')->subDay()->setTime(10, 0));
        $this->sell($user, [['product_id' => $soda->id, 'quantity' => 1]], 'cash')->assertOk();
        $this->travelBack();
        $this->sell($user, [['product_id' => $soda->id, 'quantity' => 1]], 'cash')->assertOk();

        $this->assertSame(['closed', 'open'], RegisterSession::orderBy('id')->pluck('status')->all());
        $this->assertSame('Cierre automático (modo simple)', RegisterSession::orderBy('id')->first()->notes);
        $this->sell($user, [['product_id' => $soda->id, 'quantity' => 1]], 'cash')->assertOk();
        $this->assertSame(2, RegisterSession::count(), 'Same day, same shift');
    }

    public function test_today_page_shows_what_came_in_and_what_is_left(): void
    {
        [$user, $store] = $this->owner();
        $soda = $this->product($store);
        $this->sell($user, [['product_id' => $soda->id, 'quantity' => 4]], 'yape')->assertOk();

        $this->actingAs($user)->get(route('dashboard.simple.today'))->assertOk()
            ->assertSee('Cómo voy hoy')->assertSee('S/ 14.00')->assertSee('Te quedó')->assertSee('S/ 6.00')->assertSee('Yape')->assertSee('4× Gaseosa 500 ml');
    }

    public function test_another_store_cannot_be_sold_from_and_online_only_stores_have_no_simple_mode(): void
    {
        [$user, $store] = $this->owner();
        [, $other] = $this->owner(Store::MODE_SIMPLE, 'otra');
        $foreign = $this->product($other, 'Ajeno');

        $this->sell($user, [['product_id' => $foreign->id, 'quantity' => 1]])->assertUnprocessable();
        $this->assertSame(0, Order::count());

        [$online] = $this->owner(Store::MODE_ONLINE, 'tienda');
        $this->actingAs($online)->get(route('dashboard.simple.index'))->assertRedirect(route('dashboard.index'));
    }
}
