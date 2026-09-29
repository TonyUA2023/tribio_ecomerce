<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use App\Services\MadeToOrder\CustomizationSchema;
use App\Services\Pricing\WholesalePricing;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * "Por mayor": unit-price tiers by the total units of a product (all sizes/colors and
 * made-to-order size grids together) and a minimum order quantity, charged by every gateway.
 * Polo: S/30 regular, S/25 from 12, S/22 from 50.
 */
class WholesalePricingTest extends TestCase
{
    use RefreshDatabase;

    private array $mpRequests = [];

    protected function migrateFreshUsing(): array
    {
        return ['--path' => array_values(array_filter(glob(database_path('migrations/*.php')),
            fn ($path) => !str_ends_with($path, '2026_09_11_182936_update_role_column_in_users_table.php'))), '--realpath' => true];
    }

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        WholesalePricing::forgetReady();
    }

    private function store(array $attributes = []): Store
    {
        return Store::create($attributes + [
            'user_id' => User::factory()->create(['role' => 'store_owner'])->id, 'name' => 'Apachi', 'slug' => 'apachi',
            'status' => 'active', 'template_name' => 'textil-pro', 'category' => 'textileria', 'whatsapp_phone' => '51971012786',
        ]);
    }

    /** @return array{0: Product, 1: ProductVariant, 2: ProductVariant} */
    private function polo(Store $store, array $attributes = []): array
    {
        $polo = Product::create($attributes + [
            'store_id' => $store->id, 'name' => 'Polo estampado', 'slug' => 'polo', 'price' => 30, 'price_usd' => 8,
            'stock' => 500, 'track_stock' => true, 'is_active' => true, 'has_variants' => true,
            'price_tiers' => [['min_qty' => 12, 'price' => 25], ['min_qty' => 50, 'price' => 22]],
        ]);
        $s = ProductVariant::create(['product_id' => $polo->id, 'sku' => 'P-S', 'price' => 30, 'stock' => 200, 'attributes' => ['Talla' => 'S'], 'is_active' => true]);
        // XXL costs more: tiers apply proportionally (-16.67% / -26.67%).
        $xxl = ProductVariant::create(['product_id' => $polo->id, 'sku' => 'P-XXL', 'price' => 36, 'stock' => 200, 'attributes' => ['Talla' => 'XXL'], 'is_active' => true]);

        return [$polo, $s, $xxl];
    }

    private function cart(array $items, array $extra = []): array
    {
        return $extra + [
            'customer_name' => 'Rosa Quispe', 'customer_phone' => '987654321', 'customer_email' => 'rosa@example.test',
            'customer_country' => 'PE', 'items' => $items,
        ];
    }

    public function test_tiers_count_every_size_of_the_product_together(): void
    {
        $store = $this->store();
        [$polo, $s, $xxl] = $this->polo($store);

        // 8 S + 4 XXL = 12 units → the 12+ tier applies to both lines.
        $this->postJson(route('store.checkout', $store->slug), $this->cart([
            ['id' => $polo->id, 'quantity' => 8, 'variant_id' => $s->id],
            ['id' => $polo->id, 'quantity' => 4, 'variant_id' => $xxl->id],
        ]))->assertOk()->assertJson(['success' => true]);

        $order = Order::with('items')->sole();
        $this->assertEquals([25.0, 30.0], $order->items->pluck('price')->map(fn ($p) => (float) $p)->all());
        $this->assertEquals(8 * 25 + 4 * 30, (float) $order->subtotal);

        // 11 units: regular price.
        Order::query()->delete();
        $this->postJson(route('store.checkout', $store->slug), $this->cart([['id' => $polo->id, 'quantity' => 11, 'variant_id' => $s->id]]))->assertOk();
        $this->assertEquals(30.0, (float) Order::with('items')->latest('id')->first()->items->sole()->price);
    }

    public function test_the_biggest_reached_tier_wins_and_usd_follows_the_same_ratio(): void
    {
        $store = $this->store();
        [$polo, $s] = $this->polo($store);

        $this->postJson(route('store.checkout', $store->slug), $this->cart([['id' => $polo->id, 'quantity' => 60, 'variant_id' => $s->id]]))->assertOk();

        $item = Order::with('items')->sole()->items->sole();
        $this->assertEquals(22.0, (float) $item->price);
        // Same ratio on the line's own USD price (this size has no explicit USD price, so it's converted).
        $this->assertEquals(round((float) $s->resolvePrice('USD') * 22 / 30, 2), (float) $item->price_usd);
    }

    public function test_the_minimum_order_is_enforced_before_anything_is_created(): void
    {
        $store = $this->store();
        [$polo, $s] = $this->polo($store, ['min_quantity' => 6]);

        $this->postJson(route('store.checkout', $store->slug), $this->cart([['id' => $polo->id, 'quantity' => 4, 'variant_id' => $s->id]]))
            ->assertStatus(422)->assertJson(['error' => 'Polo estampado: el pedido mínimo es de 6 unidades (llevas 4).']);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('pending_checkouts', 0);
    }

    public function test_made_to_order_size_grids_reach_tiers_and_extras_are_not_discounted(): void
    {
        $store = $this->store(['made_to_order_enabled' => true, 'deposit_percent' => 50]);
        $polo = Product::create([
            'store_id' => $store->id, 'name' => 'Polo promoción', 'slug' => 'promo', 'price' => 30, 'stock' => 0,
            'track_stock' => false, 'is_active' => true, 'sale_mode' => Product::SALE_MADE_TO_ORDER, 'lead_time_days' => 5,
            'price_tiers' => [['min_qty' => 12, 'price' => 24]],
            'customization_schema' => CustomizationSchema::normalize([
                ['type' => 'text', 'label' => 'Nombre', 'required' => true, 'max' => 20, 'price' => 5],
                ['type' => 'sizes', 'label' => 'Tallas', 'required' => true, 'sizes' => ['S', 'M', 'L']],
            ]),
        ]);

        $this->postJson(route('store.checkout', $store->slug), $this->cart([[
            'id' => $polo->id, 'quantity' => 1, 'customization' => ['nombre' => 'Promo 2026', 'tallas' => ['S' => 6, 'M' => 6]],
        ]]))->assertOk();

        $order = Order::with('items')->sole();
        // 12 units: garment 30 → 24, plus the S/5 text extra (not discounted) = 29.
        $this->assertSame(12, $order->items->sole()->quantity);
        $this->assertEquals(29.0, (float) $order->items->sole()->price);
        $this->assertEquals(348.0, (float) $order->total);
        $this->assertEquals(174.0, (float) $order->deposit_amount);
    }

    public function test_mercado_pago_is_charged_the_wholesale_total(): void
    {
        $store = $this->store(['payment_gateway' => 'mercado_pago', 'checkout_mode' => 'card', 'mp_access_token' => 'TEST-token']);
        [$polo, $s] = $this->polo($store);
        $stack = HandlerStack::create(new MockHandler([new Response(201, [], json_encode(['id' => 77, 'status' => 'approved']))]));
        $stack->push(Middleware::history($this->mpRequests));
        $this->app->bind(Client::class, fn () => new Client(['handler' => $stack]));

        $this->postJson(route('store.checkout', $store->slug), $this->cart([['id' => $polo->id, 'quantity' => 12, 'variant_id' => $s->id]], [
            'payment_method' => 'mercadopago', 'mp_form_data' => ['token' => 'card', 'installments' => 1, 'payment_method_id' => 'visa'],
        ]))->assertOk()->assertJson(['success' => true]);

        $this->assertEquals(300, json_decode((string) $this->mpRequests[0]['request']->getBody(), true)['transaction_amount']);
    }

    public function test_tiers_are_validated_in_the_product_form(): void
    {
        $this->assertSame([['min_qty' => 12, 'price' => 25.0], ['min_qty' => 50, 'price' => 22.0]],
            WholesalePricing::normalize([['min_qty' => '50', 'price' => '22'], ['min_qty' => '12', 'price' => '25'], ['min_qty' => '', 'price' => '']], 30));
        $this->assertNull(WholesalePricing::normalize([], 30));

        foreach ([
            [['min_qty' => '1', 'price' => '20']],          // desde 2 unidades
            [['min_qty' => '12', 'price' => '31']],         // más caro que el precio normal
            [['min_qty' => '12', 'price' => '25'], ['min_qty' => '50', 'price' => '26']], // no baja
            [['min_qty' => '12', 'price' => '25'], ['min_qty' => '12', 'price' => '20']], // repetido
            'no-es-una-lista',
        ] as $invalid) {
            try {
                WholesalePricing::normalize($invalid, 30);
                $this->fail('Accepted invalid tiers: ' . json_encode($invalid));
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('price_tiers', $e->errors());
            }
        }
    }

    public function test_the_owner_saves_tiers_and_minimum_from_the_product_form(): void
    {
        $store = $this->store();
        $this->actingAs($store->user)->get(route('dashboard.productos.create'))->assertOk()->assertSee('Precios por mayor');

        $this->post(route('dashboard.productos.store'), [
            'name' => 'Polera con capucha', 'price' => 60, 'stock' => 30, 'is_active' => 1,
            'price_tiers_json' => json_encode([['min_qty' => 12, 'price' => 52], ['min_qty' => 50, 'price' => 48]]),
            'min_quantity' => 3,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $product = Product::where('name', 'Polera con capucha')->sole();
        $this->assertSame([['min_qty' => 12, 'price' => 52], ['min_qty' => 50, 'price' => 48]], $product->price_tiers);
        $this->assertSame(3, $product->min_quantity);

        $this->post(route('dashboard.productos.store'), [
            'name' => 'Mala', 'price' => 60, 'stock' => 30,
            'price_tiers_json' => json_encode([['min_qty' => 12, 'price' => 70]]),
        ])->assertSessionHasErrors('price_tiers');
        $this->assertSame(0, Product::where('name', 'Mala')->count());
    }

    public function test_the_product_page_shows_the_tiers_and_registers_them_for_the_cart(): void
    {
        $store = $this->store();
        [$polo] = $this->polo($store, ['min_quantity' => 6]);

        $this->get(route('store.product', [$store->slug, $polo->slug]))->assertOk()
            ->assertSee('Precio por cantidad')
            ->assertSee('6 – 11 unid.')
            ->assertSee('12 – 49 unid.')
            ->assertSee('Desde 50 unid.')
            ->assertSee('-27%')
            ->assertSee('Pedido mínimo')
            ->assertSee('window.TribioWholesale[' . $polo->id . ']', false);
    }
}
