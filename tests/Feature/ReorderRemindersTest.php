<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactEvent;
use App\Models\ContactTask;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\Crm\ReorderReminders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** ERP Fase 2c: reorder reminders before a customer's product runs out. */
class ReorderRemindersTest extends TestCase
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
        $this->travelTo(now()->setDate(2026, 11, 3)->setTime(12, 0));
    }

    private function owner(): array
    {
        $user = User::factory()->create(['role' => 'store_owner']);
        $store = Store::create(['user_id' => $user->id, 'name' => 'Nutri', 'slug' => 'nutri', 'status' => 'active', 'template_name' => 'soft-market']);

        return [$user, $store];
    }

    private function shake(Store $store, ?int $days = 30): Product
    {
        return Product::create([
            'store_id' => $store->id, 'name' => 'Batido Vainilla', 'slug' => 'batido-' . uniqid(), 'price' => 160,
            'stock' => 0, 'track_stock' => false, 'is_active' => true, 'consumption_days' => $days,
        ]);
    }

    /**
     * Order + items in one transaction, like checkout; the reminder runs after commit. An
     * older purchase is backdated and recomputed, as if it had been bought back then.
     */
    private function buy(Store $store, Product $product, int $quantity, int $daysAgo = 0, string $payment = 'paid'): Order
    {
        $order = DB::transaction(function () use ($store, $product, $quantity, $payment) {
            $order = Order::create([
                'store_id' => $store->id, 'order_number' => 'NV-' . (++$this->orderSeq), 'customer_name' => 'Rosa Quispe',
                'customer_phone' => '987111222', 'customer_country' => 'PE', 'subtotal' => 160 * $quantity, 'total' => 160 * $quantity,
                'currency' => 'PEN', 'status' => 'confirmed', 'payment_status' => $payment, 'payment_method' => 'yape',
            ]);
            $order->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'price' => 160, 'quantity' => $quantity, 'subtotal' => 160 * $quantity]);

            return $order;
        });
        if ($daysAgo > 0) {
            Order::whereKey($order->id)->update(['created_at' => now()->subDays($daysAgo)]);
            ContactTask::where('kind', 'reorder')->update(['created_at' => now()->subDays($daysAgo)]);
            app(ReorderReminders::class)->refreshForOrder($order->fresh());
        }

        return $order->fresh();
    }

    private function pending(): ?ContactTask
    {
        return ContactTask::where('kind', 'reorder')->whereNull('done_at')->first();
    }

    public function test_a_paid_purchase_schedules_the_reminder_before_it_runs_out(): void
    {
        [, $store] = $this->owner();
        $shake = $this->shake($store);

        $this->buy($store, $shake, 2);   // 2 × 30 days → runs out 2 Jan 2027

        $task = $this->pending();
        $this->assertSame(Contact::sole()->id, $task->contact_id);
        $this->assertSame($shake->id, $task->product_id);
        $this->assertSame('2026-12-28 10:00', $task->due_at->format('Y-m-d H:i'), '5 days before');
        $this->assertSame('Recompra de Batido Vainilla — se le acaba el 2 de enero de 2027', $task->title);
        $this->assertStringContainsString('?text=' . rawurlencode('Hola Rosa 👋 ¿Qué tal te va con Batido Vainilla?'), $task->whatsapp_url);
    }

    public function test_buying_again_early_stacks_up_and_closes_the_old_reminder(): void
    {
        [, $store] = $this->owner();
        $shake = $this->shake($store);
        $this->buy($store, $shake, 1, daysAgo: 20);   // runs out in 10 days
        $old = $this->pending();

        $this->buy($store, $shake, 1);                // +30 days from then → 40 days from today

        $this->assertNotNull($old->fresh()->done_at);
        $this->assertStringEndsWith('— Volvió a comprar (pedido #NV-2)', ContactEvent::where('type', 'task')->sole()->body);
        $this->assertSame(now()->addDays(40)->subDays(5)->format('Y-m-d'), $this->pending()->due_at->format('Y-m-d'));
        $this->assertSame(1, ContactTask::where('kind', 'reorder')->whereNull('done_at')->count());
    }

    public function test_only_paid_orders_count_and_a_cancellation_removes_the_reminder(): void
    {
        [, $store] = $this->owner();
        $shake = $this->shake($store);
        $order = $this->buy($store, $shake, 1, payment: 'pending');
        $this->assertNull($this->pending(), 'Not paid yet');

        $order->update(['payment_status' => 'paid']);
        $this->assertNotNull($this->pending());

        $order->update(['status' => 'cancelled']);
        $this->assertNull($this->pending());
    }

    public function test_short_durations_and_stale_purchases(): void
    {
        [, $store] = $this->owner();
        $tea = $this->shake($store, days: 8);
        $this->buy($store, $tea, 1);                  // lasts 8 days → remind 2 days before
        $this->assertSame(now()->addDays(6)->format('Y-m-d'), $this->pending()->due_at->format('Y-m-d'));
    }

    public function test_a_reminder_that_is_already_due_shows_as_today(): void
    {
        [, $store] = $this->owner();
        $shake = $this->shake($store);
        $this->buy($store, $shake, 1, daysAgo: 28);   // runs out in 2 days: the reminder date has passed

        $this->assertSame(now()->format('Y-m-d'), $this->pending()->due_at->format('Y-m-d'));
    }

    public function test_nothing_for_products_without_duration_or_long_gone(): void
    {
        [, $store] = $this->owner();
        $plain = $this->shake($store, days: null);
        $this->buy($store, $plain, 3);
        $this->assertNull($this->pending());

        // Bought 90 days ago, duration set only now: it ran out 60 days ago, too late to remind.
        $shake = $this->shake($store, days: null);
        $this->buy($store, $shake, 1, daysAgo: 90);
        $shake->update(['consumption_days' => 30]);
        app(ReorderReminders::class)->refreshForProduct($shake);
        $this->assertNull($this->pending(), 'Too old: the lifecycle stage covers it');
    }

    public function test_setting_the_duration_reschedules_past_buyers(): void
    {
        [$user, $store] = $this->owner();
        $shake = $this->shake($store, days: null);
        $this->buy($store, $shake, 1, daysAgo: 10);
        $this->assertNull($this->pending());

        $this->actingAs($user)->put(route('dashboard.inventario.reorder-days.update', $shake->id), ['consumption_days' => 30])
            ->assertRedirect()->assertSessionHas('success', 'Listo: una unidad dura 30 días. Revisamos las recompras de 1 cliente que lo compraron en los últimos 6 meses.');
        $this->assertSame(now()->addDays(15)->format('Y-m-d'), $this->pending()->due_at->format('Y-m-d'));

        $this->actingAs($user)->put(route('dashboard.inventario.reorder-days.update', $shake->id), ['consumption_days' => ''])->assertRedirect();
        $this->assertNull($this->pending());
        $this->actingAs($user)->put(route('dashboard.inventario.reorder-days.update', $shake->id), ['consumption_days' => 0])->assertSessionHasErrors('consumption_days');
    }

    public function test_reminders_show_in_follow_ups_and_product_page(): void
    {
        [$user, $store] = $this->owner();
        $shake = $this->shake($store);
        $this->buy($store, $shake, 1);

        $this->actingAs($user)->get(route('dashboard.clientes.tasks.index'))->assertOk()
            ->assertSee('Recompra de Batido Vainilla')->assertSee('plantilla=reorder', false);
        $this->actingAs($user)->get(route('dashboard.inventario.product', $shake))->assertOk()
            ->assertSee('🔁 Recompra')->assertSee('1 recompra agendada');
    }

    public function test_another_stores_product_cannot_be_changed(): void
    {
        [$user] = $this->owner();
        $otherOwner = User::factory()->create(['role' => 'store_owner']);
        $other = Store::create(['user_id' => $otherOwner->id, 'name' => 'Otra', 'slug' => 'otra', 'status' => 'active', 'template_name' => 'soft-market']);
        $foreign = $this->shake($other);

        $this->actingAs($user)->put(route('dashboard.inventario.reorder-days.update', $foreign->id), ['consumption_days' => 5])->assertNotFound();
        $this->assertSame(30, $foreign->fresh()->consumption_days);
    }
}
