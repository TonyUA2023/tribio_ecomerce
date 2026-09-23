<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The store owner must be able to ship an order from the dashboard alone: the list
 * shows every order, and the detail shows everything a courier asks for — full name,
 * identity document, phone, email, full address — plus how the buyer paid.
 */
class DashboardOrderDetailsTest extends TestCase
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
    }

    private function store(User $owner, array $attributes = []): Store
    {
        return Store::create($attributes + [
            'user_id' => $owner->id, 'name' => 'Bordados Lima', 'slug' => 'bordados-lima',
            'status' => 'active', 'template_name' => 'soft-market', 'checkout_mode' => 'mixed',
            'whatsapp_phone' => '51 999 888 777', 'national_shipping_cost' => 10,
        ]);
    }

    private function cart(Product $product, array $extra = []): array
    {
        return $extra + [
            'customer_name' => 'Ana Pérez Quispe', 'customer_phone' => '987654321', 'customer_email' => 'ana@example.test',
            'customer_address' => 'Av. Giráldez 245, Dpto 302', 'customer_country' => 'PE',
            'customer_state' => 'Junín', 'customer_city' => 'Huancayo', 'customer_zipcode' => '12001',
            'customer_notes' => 'Frente al parque',
            'items' => [['id' => $product->id, 'quantity' => 2]],
        ];
    }

    public function test_checkout_stores_the_buyer_document_and_the_dashboard_shows_every_shipping_detail(): void
    {
        $owner = User::factory()->create(['role' => 'store_owner']);
        $store = $this->store($owner);
        $product = Product::create(['store_id' => $store->id, 'name' => 'Polo bordado', 'slug' => 'polo', 'price' => 50, 'stock' => 10, 'is_active' => true]);

        $this->postJson(route('store.checkout', $store->slug), $this->cart($product, [
            'customer_document_type' => 'DNI', 'customer_document_number' => '71234567',
        ]))->assertOk()->assertJson(['success' => true]);

        $order = Order::sole();
        $this->assertSame(['DNI', '71234567'], [$order->customer_document_type, $order->customer_document_number]);
        $this->assertStringContainsString(urlencode('71234567'), $order->buildWhatsappMessage());

        $this->actingAs($owner)->get(route('dashboard.pedidos.index'))
            ->assertOk()->assertSee($order->order_number)->assertSee('Ana Pérez Quispe')
            ->assertDontSee('se completará en la próxima fase');

        $this->get(route('dashboard.pedidos.show', $order))->assertOk()
            ->assertSee('Ana Pérez Quispe')->assertSee('DNI 71234567')->assertSee('987654321')
            ->assertSee('ana@example.test')->assertSee('Av. Giráldez 245, Dpto 302')
            ->assertSee('Huancayo')->assertSee('Junín')->assertSee('12001')->assertSee('Frente al parque')
            ->assertSee('WhatsApp / Pago directo')->assertSee('Polo bordado')
            ->assertSee('https://wa.me/51987654321', false);

        $this->get(route('dashboard.pedidos.index', ['search' => '71234567']))
            ->assertOk()->assertViewHas('orders', fn ($orders) => $orders->count() === 1);
    }

    public function test_mercado_pago_card_payer_document_is_used_when_the_form_sends_none(): void
    {
        $owner = User::factory()->create(['role' => 'store_owner']);
        $store = $this->store($owner, ['payment_gateway' => 'mercado_pago', 'checkout_mode' => 'card', 'mp_access_token' => 'TEST-token']);
        $product = Product::create(['store_id' => $store->id, 'name' => 'Polo bordado', 'slug' => 'polo', 'price' => 50, 'stock' => 10, 'is_active' => true]);
        $this->app->bind(Client::class, fn () => new Client(['handler' => HandlerStack::create(new MockHandler([
            new Response(201, [], json_encode(['id' => 987654, 'status' => 'approved'])),
        ]))]));

        $this->postJson(route('store.checkout', $store->slug), $this->cart($product, [
            'payment_method' => 'mercadopago',
            'mp_form_data' => ['token' => 'card-token', 'installments' => 1, 'payment_method_id' => 'visa',
                'payer' => ['email' => 'ana@example.test', 'identification' => ['type' => 'CE', 'number' => '001234567']]],
        ]))->assertOk()->assertJson(['success' => true]);

        $order = Order::sole();
        $this->assertSame(['CE', '001234567'], [$order->customer_document_type, $order->customer_document_number]);

        $this->actingAs($owner)->get(route('dashboard.pedidos.show', $order))->assertOk()
            ->assertSee('CE 001234567')->assertSee('Mercado Pago · Tarjeta')->assertSee('Pagado')->assertSee('987654');
    }

    public function test_an_unknown_document_type_is_rejected(): void
    {
        $store = $this->store(User::factory()->create(['role' => 'store_owner']));
        $product = Product::create(['store_id' => $store->id, 'name' => 'Polo', 'slug' => 'polo', 'price' => 50, 'stock' => 10, 'is_active' => true]);

        $this->postJson(route('store.checkout', $store->slug), $this->cart($product, [
            'customer_document_type' => 'XYZ', 'customer_document_number' => '123',
        ]))->assertStatus(422)->assertJsonValidationErrors('customer_document_type');

        $this->assertSame(0, Order::count());
    }
}
