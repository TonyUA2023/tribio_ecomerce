<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Register;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** ERP Fase 4e: the mobile quick sale API (same register, shift and sale rules as the web). */
class MobilePosApiTest extends TestCase
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

    private function owner(string $slug = 'nutri'): array
    {
        $user = User::factory()->create(['role' => 'store_owner']);
        $store = Store::create(['user_id' => $user->id, 'name' => 'Nutri Vida', 'slug' => $slug, 'status' => 'active', 'template_name' => 'soft-market']);
        $batido = Product::create(['store_id' => $store->id, 'name' => 'Batido de fresa', 'slug' => 'batido-' . $slug, 'sku' => 'BAT-01', 'price' => 10, 'stock' => 30, 'track_stock' => true, 'is_active' => true]);
        $box = ProductUnit::create(['product_id' => $batido->id, 'name' => 'Caja', 'factor' => 6, 'barcode' => '7750000000062']);

        return [$user, $store, $batido, $box];
    }

    public function test_the_app_opens_a_register_sells_and_closes_it(): void
    {
        [$user, $store, $batido, $box] = $this->owner();
        $rosa = Contact::create(['store_id' => $store->id, 'name' => 'Rosa Quispe', 'phone' => '51987111222', 'source' => 'manual']);
        Sanctum::actingAs($user);

        $overview = $this->getJson('/api/dashboard/caja')->assertOk()
            ->assertJsonPath('registers.0.name', 'Caja principal')->assertJsonPath('registers.0.session', null)
            ->assertJsonPath('methods.0', ['key' => 'cash', 'label' => 'Efectivo'])->assertJsonPath('credit', true);
        $registerId = $overview->json('registers.0.id');

        $session = $this->postJson("/api/dashboard/caja/cajas/{$registerId}/abrir", ['opening_float' => 50])->assertCreated()
            ->assertJsonPath('session.opening_float', 50)->json('session.id');
        $this->postJson("/api/dashboard/caja/cajas/{$registerId}/abrir", ['opening_float' => 50])->assertUnprocessable()->assertJsonValidationErrors('opening_float');

        $this->getJson('/api/dashboard/caja/productos?q=7750000000062')->assertOk()->assertJsonPath('exact.unit_id', $box->id);
        $this->getJson('/api/dashboard/caja/clientes?q=rosa')->assertOk()->assertJsonPath('data.0.id', $rosa->id);

        $uuid = (string) Str::uuid();
        $sale = ['uuid' => $uuid, 'items' => [['product_id' => $batido->id, 'quantity' => 2], ['product_id' => $batido->id, 'unit_id' => $box->id, 'quantity' => 1]],
            'contact_id' => $rosa->id, 'payments' => [['method' => 'yape', 'amount' => 30], ['method' => 'cash', 'amount' => 50]]];
        $this->postJson("/api/dashboard/caja/turnos/{$session}/ventas", $sale)->assertCreated()
            ->assertJsonPath('duplicate', false)->assertJsonPath('receipt.total', 80)->assertJsonPath('receipt.change', 0)
            ->assertJsonPath('receipt.lines.1', ['name' => 'Batido de fresa', 'quantity' => 1, 'unit' => 'Caja', 'subtotal' => 60])
            ->assertJsonPath('receipt.whatsapp_url', fn ($url) => str_starts_with($url, 'https://wa.me/51987111222?text='));
        $this->postJson("/api/dashboard/caja/turnos/{$session}/ventas", $sale)->assertOk()->assertJsonPath('duplicate', true);
        $order = Order::sole();
        $this->assertSame(['pos', 22, $rosa->id], [$order->source, $batido->fresh()->stock, $order->contact_id]);

        $this->postJson("/api/dashboard/caja/turnos/{$session}/ventas", ['uuid' => (string) Str::uuid(), 'items' => [['product_id' => $batido->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 5]]])->assertUnprocessable()->assertJsonValidationErrors(['payments' => 'Falta cobrar S/ 5.00.']);

        $this->postJson("/api/dashboard/caja/turnos/{$session}/movimientos", ['type' => 'out', 'amount' => 10, 'reason' => 'Pasaje'])->assertCreated()
            ->assertJsonPath('session.summary.expected_cash', 90);
        $this->getJson("/api/dashboard/caja/turnos/{$session}")->assertOk()
            ->assertJsonPath('session.summary.sales', 1)->assertJsonPath('session.summary.methods.yape', 30)
            ->assertJsonPath('sales.0.order_number', $order->order_number)->assertJsonPath('sales.0.methods', fn ($m) => collect($m)->sort()->values()->all() === ['Efectivo', 'Yape']);
        $this->getJson("/api/dashboard/caja/ventas/{$order->id}")->assertOk()->assertJsonCount(2, 'receipt.payments')->assertJsonPath('receipt.payments.0.label', 'Efectivo');

        $this->postJson("/api/dashboard/caja/turnos/{$session}/cerrar", ['counted_cash' => 88])->assertOk()
            ->assertJson(['message' => 'Caja cerrada con S/ 2.00 de menos.', 'expected' => 90, 'difference' => -2]);
        $this->getJson('/api/dashboard/caja')->assertJsonPath('registers.0.session', null);
    }

    public function test_credit_sales_and_a_receipt_with_installments_from_the_app(): void
    {
        [$user, $store, $batido] = $this->owner();
        $rosa = Contact::create(['store_id' => $store->id, 'name' => 'Rosa Quispe', 'phone' => '51987111222', 'source' => 'manual']);
        Sanctum::actingAs($user);
        $register = $this->getJson('/api/dashboard/caja')->json('registers.0.id');
        $session = $this->postJson("/api/dashboard/caja/cajas/{$register}/abrir", ['opening_float' => 0])->json('session.id');

        $this->postJson("/api/dashboard/caja/turnos/{$session}/ventas", ['uuid' => (string) Str::uuid(), 'items' => [['product_id' => $batido->id, 'quantity' => 3]],
            'contact_id' => $rosa->id, 'payments' => [['method' => 'cash', 'amount' => 10]], 'credit' => ['installments' => 2, 'every_days' => 15]])
            ->assertCreated()->assertJsonPath('receipt.owed', 20)->assertJsonCount(2, 'receipt.installments');
    }

    public function test_another_stores_registers_and_sales_are_out_of_reach(): void
    {
        [$user, , $batido] = $this->owner();
        Sanctum::actingAs($user);
        $register = $this->getJson('/api/dashboard/caja')->json('registers.0.id');
        $session = $this->postJson("/api/dashboard/caja/cajas/{$register}/abrir", ['opening_float' => 0])->json('session.id');
        $this->postJson("/api/dashboard/caja/turnos/{$session}/ventas", ['uuid' => (string) Str::uuid(), 'items' => [['product_id' => $batido->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 10]]])->assertCreated();

        [$other] = $this->owner('otra');
        Sanctum::actingAs($other);
        $this->postJson("/api/dashboard/caja/cajas/{$register}/abrir", ['opening_float' => 0])->assertNotFound();
        $this->getJson("/api/dashboard/caja/turnos/{$session}")->assertNotFound();
        $this->postJson("/api/dashboard/caja/turnos/{$session}/ventas", ['uuid' => (string) Str::uuid(), 'items' => [['product_id' => $batido->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 10]]])->assertNotFound();
        $this->getJson('/api/dashboard/caja/ventas/' . Order::sole()->id)->assertNotFound();
        $this->getJson('/api/dashboard/caja/productos?q=Batido')->assertJsonCount(1, 'products')->assertJsonPath('products.0.id', fn ($id) => $id !== $batido->id);
        $this->assertSame(1, Register::count(), 'The other store opened nothing');
    }
}
