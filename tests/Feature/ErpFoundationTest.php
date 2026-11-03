<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\Crm\ContactResolver;
use App\Services\Crm\CrmSchema;
use App\Services\Inventory\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** ERP Fase 0: one locked, logged path for stock, and contacts built from orders (Dashboard → Clientes). */
class ErpFoundationTest extends TestCase
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
        config(['app.url' => 'http://localhost']);
    }

    private function owner(array $store = []): array
    {
        $user = User::factory()->create(['role' => 'store_owner']);
        $store = Store::create($store + [
            'user_id' => $user->id, 'name' => 'Ferretería Lima', 'slug' => 'ferreteria-lima', 'status' => 'active',
            'template_name' => 'soft-market', 'checkout_mode' => 'mixed', 'whatsapp_phone' => '51 999 888 777', 'national_shipping_cost' => 10,
        ]);

        return [$user, $store];
    }

    private function product(Store $store, array $attributes = []): Product
    {
        return Product::create($attributes + [
            'store_id' => $store->id, 'name' => 'Martillo', 'slug' => 'martillo-' . uniqid(), 'price' => 40,
            'stock' => 10, 'track_stock' => true, 'is_active' => true,
        ]);
    }

    private function order(Store $store, array $attributes = []): Order
    {
        return Order::create($attributes + [
            'store_id' => $store->id, 'order_number' => 'TST-' . (++$this->orderSeq), 'customer_name' => 'Ana Pérez',
            'customer_phone' => '987 654 321', 'customer_country' => 'PE', 'subtotal' => 100, 'total' => 100,
            'currency' => 'PEN', 'status' => 'pending', 'payment_status' => 'pending', 'payment_method' => 'whatsapp',
        ]);
    }

    // ─── Stock ───────────────────────────────────────────────────

    public function test_a_sale_is_locked_clamped_and_logged(): void
    {
        [, $store] = $this->owner();
        $product = $this->product($store, ['stock' => 3]);

        $movement = app(StockService::class)->sell($product, null, 5, 'TRB-1');

        $this->assertSame(0, $product->fresh()->stock);
        $this->assertSame(['sale', -3, 3, 0, 'TRB-1'], [$movement->type, $movement->quantity, $movement->stock_before, $movement->stock_after, $movement->reference]);
        $this->assertStringContainsString('faltaron 2', $movement->reason);
    }

    public function test_untracked_products_are_not_touched(): void
    {
        [, $store] = $this->owner();
        $product = $this->product($store, ['track_stock' => false, 'stock' => 3]);

        $this->assertNull(app(StockService::class)->sell($product, null, 2, 'TRB-1'));
        $this->assertSame(3, $product->fresh()->stock);
        $this->assertSame(0, InventoryMovement::count());
    }

    public function test_web_adjustment_records_the_real_change(): void
    {
        [$user, $store] = $this->owner();
        $product = $this->product($store, ['stock' => 4]);

        $this->actingAs($user)->post(route('dashboard.inventario.adjust', $product), ['type' => 'out', 'quantity' => 10, 'reason' => 'Rotura'])
            ->assertRedirect()->assertSessionHas('success', "Stock de 'Martillo' ajustado de 4 a 0.");

        $movement = InventoryMovement::sole();
        $this->assertSame([-4, 4, 0, 'Rotura', $user->id], [$movement->quantity, $movement->stock_before, $movement->stock_after, $movement->reason, $movement->user_id]);
    }

    public function test_api_adjustment_keeps_its_response(): void
    {
        [$user, $store] = $this->owner();
        $product = $this->product($store, ['stock' => 4]);

        $this->actingAs($user, 'sanctum')->postJson("/api/dashboard/inventario/{$product->id}/ajuste", ['type' => 'adjustment', 'quantity' => 12])
            ->assertOk()->assertJsonPath('product.stock', 12)->assertJsonPath('message', 'Stock de «Martillo» ajustado de 4 a 12.');
        $this->assertSame(8, InventoryMovement::sole()->quantity);
    }

    public function test_checkout_logs_the_sale_and_links_the_buyer(): void
    {
        [, $store] = $this->owner();
        $product = $this->product($store, ['stock' => 10]);

        $this->postJson(route('store.checkout', $store->slug), [
            'customer_name' => 'Ana Pérez', 'customer_phone' => '987654321', 'customer_email' => 'Ana@Example.test',
            'customer_address' => 'Av. Siempre Viva 123', 'customer_country' => 'PE', 'customer_city' => 'Lima',
            'items' => [['id' => $product->id, 'quantity' => 2]],
        ])->assertOk();

        $order = Order::sole();
        $this->assertSame(8, $product->fresh()->stock);
        $movement = InventoryMovement::sole();
        $this->assertSame(['sale', -2, $order->order_number], [$movement->type, $movement->quantity, $movement->reference]);

        $contact = Contact::sole();
        $this->assertSame($contact->id, $order->contact_id);
        $this->assertSame(['Ana Pérez', '51987654321', 'ana@example.test', 'Lima', 1], [$contact->name, $contact->phone, $contact->email, $contact->city, $contact->orders_count]);
    }

    // ─── Contacts ────────────────────────────────────────────────

    public function test_same_phone_typed_differently_is_one_contact(): void
    {
        [, $store] = $this->owner();
        $this->order($store, ['customer_phone' => '987 654 321']);
        $this->order($store, ['customer_phone' => '+51 987-654-321', 'customer_name' => 'Ana P.']);

        $contact = Contact::sole();
        $this->assertSame('Ana Pérez', $contact->name, 'The first name is kept');
        $this->assertSame(2, $contact->orders_count);
        $this->assertSame('+51 987 654 321', $contact->phone_display);
        $this->assertSame('https://wa.me/51987654321', $contact->whatsapp_url);
    }

    public function test_matches_by_email_and_fills_the_missing_phone(): void
    {
        [, $store] = $this->owner();
        $this->order($store, ['customer_phone' => null, 'customer_email' => 'luis@example.test', 'customer_name' => 'Luis']);
        $this->order($store, ['customer_phone' => '912345678', 'customer_email' => 'LUIS@example.test ', 'customer_name' => 'Luis']);

        $contact = Contact::sole();
        $this->assertSame('51912345678', $contact->phone);
        $this->assertSame(2, $contact->orders_count);
    }

    public function test_totals_follow_payments_and_cancellations(): void
    {
        [, $store] = $this->owner();
        $first = $this->order($store, ['total' => 100]);
        $second = $this->order($store, ['total' => 60]);
        $this->order($store, ['total' => 999, 'payment_status' => 'failed']);

        $contact = Contact::sole();
        $this->assertSame(2, $contact->orders_count, 'Failed payments are not orders');
        $this->assertEquals(0, $contact->total_spent, 'Nothing paid yet');

        $first->update(['payment_status' => 'paid']);
        $this->assertEquals(100, $contact->fresh()->total_spent);

        $second->update(['status' => 'cancelled']);
        $this->assertSame(1, $contact->fresh()->orders_count);
    }

    public function test_orders_without_phone_email_or_document_get_no_contact(): void
    {
        [, $store] = $this->owner();
        $order = $this->order($store, ['customer_phone' => 'sin número']);

        $this->assertNull($order->fresh()->contact_id);
        $this->assertSame(0, Contact::count());
    }

    public function test_contacts_are_per_store(): void
    {
        [$user, $store] = $this->owner();
        [, $other] = $this->owner(['slug' => 'otra', 'name' => 'Otra tienda']);
        $this->order($store);
        $this->order($other, ['customer_name' => 'Ana en otra tienda']);

        $this->assertSame(2, Contact::count());
        $foreign = $other->contacts()->sole();

        $this->actingAs($user)->get(route('dashboard.clientes.index'))->assertOk()
            ->assertSee('Ana Pérez')->assertDontSee('Ana en otra tienda');
        $this->actingAs($user)->get(route('dashboard.clientes.show', $foreign->id))->assertNotFound();
    }

    public function test_clients_page_filters_and_shows_a_contact(): void
    {
        [$user, $store] = $this->owner();
        $this->order($store, ['payment_status' => 'paid']);
        $this->order($store, ['payment_status' => 'paid']);
        $this->order($store, ['customer_name' => 'Pedro Una Vez', 'customer_phone' => '955555555']);

        $this->actingAs($user)->get(route('dashboard.clientes.index', ['segmento' => 'recurrentes']))
            ->assertOk()->assertSee('Ana Pérez')->assertDontSee('Pedro Una Vez');
        $this->actingAs($user)->get(route('dashboard.clientes.index', ['buscar' => '955 555']))
            ->assertOk()->assertSee('Pedro Una Vez')->assertDontSee('Ana Pérez');

        $ana = $store->contacts()->where('name', 'Ana Pérez')->sole();
        $this->actingAs($user)->get(route('dashboard.clientes.show', $ana->id))
            ->assertOk()->assertSee('S/ 200.00')->assertSee('#TST-1')->assertSee('#TST-2');
    }

    public function test_backfill_links_orders_placed_before_contacts_existed(): void
    {
        [, $store] = $this->owner();
        Order::withoutEvents(function () use ($store) {
            $this->order($store);
            $this->order($store, ['customer_phone' => null]);
        });
        $this->assertSame(0, Contact::count());

        $this->artisan('crm:backfill-contacts')->expectsOutputToContain('Pedidos enlazados: 1')->assertSuccessful();
        $this->artisan('crm:backfill-contacts')->expectsOutputToContain('Pedidos enlazados: 0')->assertSuccessful();
        $this->assertSame(1, Contact::sole()->orders_count);
    }

    public function test_checkout_still_works_before_the_migration_runs(): void
    {
        [, $store] = $this->owner();
        $this->app->instance(CrmSchema::class, new class extends CrmSchema {
            public function ready(): bool { return false; }
        });

        $order = $this->order($store);

        $this->assertNull($order->fresh()->contact_id);
        $this->assertSame(0, Contact::count());
    }

    public function test_phone_normalization(): void
    {
        $this->assertSame('51987654321', ContactResolver::normalizePhone('987 654 321'));
        $this->assertSame('51987654321', ContactResolver::normalizePhone('+51 (987) 654-321'));
        $this->assertSame('5491112345678', ContactResolver::normalizePhone('+54 9 11 1234-5678', 'AR'));
        $this->assertSame('014445555', ContactResolver::normalizePhone('01 444 5555'), 'Landlines are kept as typed');
        $this->assertNull(ContactResolver::normalizePhone('123'));
    }
}
