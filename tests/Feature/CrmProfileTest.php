<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactEvent;
use App\Models\ContactTask;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use App\Services\Crm\LifecycleClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** ERP Fase 2 (2a + 2b): customer profile, lifecycle stages, timeline and follow-ups. */
class CrmProfileTest extends TestCase
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

    private function owner(string $slug = 'nutri'): array
    {
        $user = User::factory()->create(['role' => 'store_owner']);
        $store = Store::create(['user_id' => $user->id, 'name' => 'Nutri ' . $slug, 'slug' => $slug, 'status' => 'active', 'template_name' => 'soft-market']);

        return [$user, $store];
    }

    /** A paid order placed $daysAgo days ago by the phone 987 654 321. */
    private function order(Store $store, int $daysAgo, array $attributes = []): Order
    {
        $this->travelTo(now()->subDays($daysAgo));
        $order = Order::create($attributes + [
            'store_id' => $store->id, 'order_number' => 'NV-' . (++$this->orderSeq), 'customer_name' => 'Rosa Quispe',
            'customer_phone' => '987654321', 'customer_country' => 'PE', 'subtotal' => 100, 'total' => 100, 'currency' => 'PEN',
            'status' => 'delivered', 'payment_status' => 'paid', 'payment_method' => 'yape',
        ]);
        $this->travelBack();

        return $order;
    }

    private function create(User $user, array $data)
    {
        return $this->actingAs($user)->post(route('dashboard.clientes.store'), $data + ['name' => 'Lucía Torres']);
    }

    // ─── Stages ──────────────────────────────────────────────────

    public function test_stages_follow_the_orders(): void
    {
        [, $store] = $this->owner();
        $this->order($store, 10);
        $contact = Contact::sole();
        $this->assertSame('nuevo', $contact->lifecycle_stage);

        $this->order($store, 5);
        $this->assertSame('recurrente', $contact->fresh()->lifecycle_stage);
        foreach ([4, 3, 2] as $days) {
            $this->order($store, $days);
        }
        $this->assertSame('vip', $contact->fresh()->lifecycle_stage);

        $stageEvents = ContactEvent::where('type', 'stage')->pluck('body')->all();
        $this->assertSame(['Cliente nuevo → Recurrente', 'Recurrente → VIP'], $stageEvents);
    }

    public function test_risk_and_inactive_come_with_time(): void
    {
        [, $store] = $this->owner();
        $this->order($store, 70);
        $this->order($store, 60);                  // usual gap 10 days → overdue after 30 (minimum)
        $contact = Contact::sole();
        $classifier = app(LifecycleClassifier::class);

        $this->assertSame('en_riesgo', $classifier->stageFor($contact->fresh()));

        $this->travel(40)->days();                 // 100 days since the last order
        $this->artisan('crm:refresh-stages')->assertSuccessful();
        $this->assertSame('inactivo', $contact->fresh()->lifecycle_stage);
    }

    public function test_a_prospect_without_orders_is_a_lead(): void
    {
        [$user] = $this->owner();
        $this->create($user, ['phone' => '955 444 333', 'tags_text' => 'Feria, gimnasio, feria', 'first_note' => 'Preguntó por el batido'])->assertRedirect();

        $contact = Contact::sole();
        $this->assertSame(['lead', 'manual', '51955444333', ['feria', 'gimnasio']], [$contact->lifecycle_stage, $contact->source, $contact->phone, $contact->tags]);
        $this->assertSame('Preguntó por el batido', ContactEvent::where('type', 'note')->sole()->body);
    }

    public function test_a_prospect_who_buys_joins_the_same_profile(): void
    {
        [$user, $store] = $this->owner();
        $this->create($user, ['phone' => '987 654 321']);
        $this->order($store, 1);

        $contact = Contact::sole();
        $this->assertSame(['Lucía Torres', 1, 'nuevo'], [$contact->name, $contact->orders_count, $contact->lifecycle_stage]);
    }

    // ─── Profile ─────────────────────────────────────────────────

    public function test_duplicates_are_refused(): void
    {
        [$user, $store] = $this->owner();
        $this->order($store, 1);

        $this->create($user, ['phone' => '+51 987-654-321'])->assertSessionHasErrors(['phone' => 'Ya tienes a Rosa Quispe con ese dato.']);
        $this->create($user, ['phone' => 'abc'])->assertSessionHasErrors('phone');
        $this->assertSame(1, Contact::count());
    }

    public function test_edit_profile_and_consent(): void
    {
        [$user, $store] = $this->owner();
        $this->order($store, 1);
        $contact = Contact::sole();

        $this->actingAs($user)->put(route('dashboard.clientes.update', $contact->id), [
            'name' => 'Rosa Quispe Mamani', 'phone' => '987654321', 'birthday' => '1990-05-12', 'relationship' => 'distribuidor',
            'tags_text' => 'VIP, Huancayo', 'accepts_marketing' => '1',
        ])->assertRedirect(route('dashboard.clientes.show', $contact->id));

        $contact->refresh();
        $this->assertSame(['Rosa Quispe Mamani', '1990-05-12', 'distribuidor', ['vip', 'huancayo'], true],
            [$contact->name, $contact->birthday->toDateString(), $contact->relationship, $contact->tags, $contact->accepts_marketing]);
        $consentAt = $contact->marketing_consent_at;
        $this->assertNotNull($consentAt);

        $this->travel(2)->days();
        $this->actingAs($user)->put(route('dashboard.clientes.update', $contact->id), ['name' => 'Rosa', 'phone' => '987654321', 'accepts_marketing' => '1']);
        $this->assertEquals($consentAt, $contact->fresh()->marketing_consent_at, 'Consent date is kept while it stands');
        $this->actingAs($user)->put(route('dashboard.clientes.update', $contact->id), ['name' => 'Rosa', 'phone' => '987654321']);
        $this->assertNull($contact->fresh()->marketing_consent_at);
    }

    public function test_timeline_mixes_orders_and_notes(): void
    {
        [$user, $store] = $this->owner();
        $this->order($store, 3);
        $contact = Contact::sole();

        $this->actingAs($user)->post(route('dashboard.clientes.events.store', $contact->id), ['type' => 'call', 'body' => 'Le gustó, pide otro el lunes'])->assertRedirect();
        $this->actingAs($user)->post(route('dashboard.clientes.events.store', $contact->id), ['type' => 'stage', 'body' => 'x'])->assertSessionHasErrors('type');

        $this->actingAs($user)->get(route('dashboard.clientes.show', $contact->id))->assertOk()
            ->assertSee('Le gustó, pide otro el lunes')->assertSee('Pedido #NV-1')->assertSee('Cliente nuevo');
    }

    public function test_filters_by_stage_and_tag(): void
    {
        [$user, $store] = $this->owner();
        $this->order($store, 1);
        $this->create($user, ['phone' => '955444333', 'tags_text' => 'mayorista']);

        $this->actingAs($user)->get(route('dashboard.clientes.index', ['etapa' => 'lead']))->assertOk()
            ->assertSee('Lucía Torres')->assertDontSee('Rosa Quispe');
        $this->actingAs($user)->get(route('dashboard.clientes.index', ['etiqueta' => 'Mayorista']))->assertOk()
            ->assertSee('Lucía Torres')->assertDontSee('Rosa Quispe');
    }

    // ─── Follow-ups ──────────────────────────────────────────────

    public function test_follow_ups_from_due_to_done(): void
    {
        [$user, $store] = $this->owner();
        $this->order($store, 1);
        $contact = Contact::sole();

        $this->actingAs($user)->post(route('dashboard.clientes.tasks.store'), ['contact_id' => $contact->id, 'title' => 'Llamar por el pack', 'due_at' => now()->subDay()->toDateTimeString()])->assertRedirect();
        $this->actingAs($user)->post(route('dashboard.clientes.tasks.store'), ['title' => 'Pedir catálogo nuevo', 'due_at' => now()->addDays(3)->toDateTimeString()]);
        $task = ContactTask::where('title', 'Llamar por el pack')->sole();

        $this->actingAs($user)->get(route('dashboard.clientes.index'))->assertSee('Seguimientos');
        $this->actingAs($user)->get(route('dashboard.clientes.tasks.index'))->assertOk()
            ->assertSee('Vencidos')->assertSee('Llamar por el pack')->assertSee('Próximos')->assertSee('Pedir catálogo nuevo');

        $this->actingAs($user)->post(route('dashboard.clientes.tasks.complete', $task->id), ['outcome' => 'Pidió 2'])->assertRedirect();
        $this->assertNotNull($task->fresh()->done_at);
        $this->assertSame('Llamar por el pack — Pidió 2', ContactEvent::where('type', 'task')->sole()->body);

        $this->actingAs($user)->post(route('dashboard.clientes.tasks.complete', $task->id));
        $this->assertNull($task->fresh()->done_at, 'A second click reopens it');
    }

    public function test_everything_stays_in_its_store(): void
    {
        [$user] = $this->owner();
        [$otherUser, $other] = $this->owner('otra');
        $this->order($other, 1);
        $foreign = Contact::sole();
        $task = ContactTask::create(['store_id' => $other->id, 'contact_id' => $foreign->id, 'title' => 'x', 'due_at' => now()]);

        $this->actingAs($user)->put(route('dashboard.clientes.update', $foreign->id), ['name' => 'Hack'])->assertNotFound();
        $this->actingAs($user)->post(route('dashboard.clientes.events.store', $foreign->id), ['type' => 'note', 'body' => 'x'])->assertNotFound();
        $this->actingAs($user)->post(route('dashboard.clientes.tasks.store'), ['contact_id' => $foreign->id, 'title' => 'x', 'due_at' => now()->toDateTimeString()])->assertSessionHasErrors('contact_id');
        $this->actingAs($user)->post(route('dashboard.clientes.tasks.complete', $task->id))->assertNotFound();
        $this->assertSame('Rosa Quispe', $foreign->fresh()->name);

        // Same phone in another store is another person: no clash.
        $this->create($user, ['phone' => '987654321'])->assertRedirect();
    }
}
