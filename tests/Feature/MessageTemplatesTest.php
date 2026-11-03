<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactEvent;
use App\Models\ContactTask;
use App\Models\MessageTemplate;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\Crm\MessageTemplates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** ERP Fase 2d: WhatsApp templates filled from the database, logged in the timeline, consent-aware. */
class MessageTemplatesTest extends TestCase
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

        return [$user, $store];
    }

    /** Rosa with a paid order and one with S/ 70 still to pay. */
    private function rosa(Store $store, array $contact = []): Contact
    {
        Order::create(['store_id' => $store->id, 'order_number' => 'NV-1', 'customer_name' => 'Rosa Quispe Mamani', 'customer_phone' => '987111222',
            'customer_country' => 'PE', 'subtotal' => 100, 'total' => 100, 'currency' => 'PEN', 'status' => 'delivered', 'payment_status' => 'paid', 'payment_method' => 'yape']);
        $partial = Order::create(['store_id' => $store->id, 'order_number' => 'NV-2', 'customer_name' => 'Rosa Quispe Mamani', 'customer_phone' => '987111222',
            'customer_country' => 'PE', 'subtotal' => 100, 'total' => 100, 'currency' => 'PEN', 'status' => 'confirmed', 'payment_status' => 'pending', 'payment_method' => 'yape']);
        $partial->recordPayment(30, 'deposit', 'yape', null);

        $rosa = Contact::sole();
        $rosa->forceFill($contact)->save();

        return $rosa;
    }

    private function messageOf(string $location): string
    {
        $this->assertStringStartsWith('https://wa.me/51987111222?text=', $location);

        return rawurldecode(substr($location, strlen('https://wa.me/51987111222?text=')));
    }

    public function test_placeholders_are_filled_from_the_database(): void
    {
        [, $store] = $this->owner();
        $rosa = $this->rosa($store);
        $templates = app(MessageTemplates::class);

        $this->assertSame('Hola Rosa, te escribo de Nutri Vida por tu pedido #NV-2: queda un saldo de S/ 70.00. ¿Te paso los datos para pagar?',
            $templates->render($templates->find($store, 'payment')['body'], $rosa));
        $this->assertSame('Hola Rosa, ¿cómo vas? Te escribo de Nutri Vida para saber si necesitas algo 😊',
            $templates->render($templates->find($store, 'follow_up')['body'], $rosa));
        $this->assertMatchesRegularExpression('#Mira lo nuevo en Nutri Vida: https?://[^ ]+/tienda/nutri$#u',
            $templates->render($templates->find($store, 'reactivation')['body'], $rosa));
        $this->assertSame('Hola Rosa', $templates->render('Hola {nombre} {producto}', $rosa), 'A placeholder without a value disappears');
        $this->assertSame('Hola {desconocido}', $templates->render('Hola {desconocido}', $rosa), 'Unknown words in braces stay as typed');
    }

    public function test_opening_whatsapp_logs_the_message_in_the_timeline(): void
    {
        [$user, $store] = $this->owner();
        $rosa = $this->rosa($store);
        $shake = Product::create(['store_id' => $store->id, 'name' => 'Batido Vainilla', 'slug' => 'batido', 'price' => 160, 'is_active' => true]);

        $response = $this->actingAs($user)->get(route('dashboard.clientes.whatsapp', [$rosa->id, 'plantilla' => 'reorder', 'producto' => $shake->id]));

        $response->assertRedirect();
        $this->assertSame('Hola Rosa 👋 ¿Qué tal te va con Batido Vainilla? Por mis cuentas ya se te debe estar acabando. ¿Te separo otro?', $this->messageOf($response->headers->get('Location')));
        $event = ContactEvent::where('type', 'whatsapp')->sole();
        $this->assertStringStartsWith('Mensaje «Recompra»: Hola Rosa', $event->body);
        $this->assertSame($user->id, $event->user_id);
    }

    public function test_promotional_templates_need_consent(): void
    {
        [$user, $store] = $this->owner();
        $rosa = $this->rosa($store);

        $this->actingAs($user)->get(route('dashboard.clientes.whatsapp', [$rosa->id, 'plantilla' => 'news']))
            ->assertRedirect()->assertSessionHas('error');
        $this->assertSame(0, ContactEvent::where('type', 'whatsapp')->count());

        $rosa->forceFill(['accepts_marketing' => true, 'marketing_consent_at' => now()])->save();
        $this->actingAs($user)->get(route('dashboard.clientes.whatsapp', [$rosa->id, 'plantilla' => 'news']))->assertRedirect();
        $this->assertSame(1, ContactEvent::where('type', 'whatsapp')->count());

        $this->actingAs($user)->get(route('dashboard.clientes.show', $rosa->id))->assertOk()->assertSee('Novedades');
    }

    public function test_owner_edits_resets_and_creates_templates(): void
    {
        [$user, $store] = $this->owner();
        $rosa = $this->rosa($store);

        $this->actingAs($user)->put(route('dashboard.clientes.templates.update', 'reorder'), ['body' => '{nombre}, ¿repetimos {producto}?'])->assertRedirect();
        $this->assertSame('{nombre}, ¿repetimos {producto}?', app(MessageTemplates::class)->find($store, 'reorder')['body']);

        // The reorder follow-up button uses the edited text.
        $shake = Product::create(['store_id' => $store->id, 'name' => 'Batido', 'slug' => 'batido', 'price' => 160, 'is_active' => true]);
        $task = ContactTask::create(['store_id' => $store->id, 'contact_id' => $rosa->id, 'product_id' => $shake->id, 'kind' => 'reorder', 'title' => 'x', 'due_at' => now()]);
        $this->assertSame('Rosa, ¿repetimos Batido?', $this->messageOf($task->whatsapp_url));

        $this->actingAs($user)->delete(route('dashboard.clientes.templates.destroy', 'reorder'))->assertSessionHas('success', 'Volvimos al mensaje original de Tribio.');
        $this->assertSame(MessageTemplates::BUILTIN['reorder']['body'], app(MessageTemplates::class)->find($store, 'reorder')['body']);

        $this->actingAs($user)->post(route('dashboard.clientes.templates.store'), ['name' => 'Confirmar entrega', 'body' => 'Hola {nombre}, tu pedido {pedido} sale hoy 🚚'])->assertRedirect();
        $own = MessageTemplate::whereNull('key')->sole();
        $this->actingAs($user)->get(route('dashboard.clientes.whatsapp', [$rosa->id, 'plantilla' => 'propia-' . $own->id]))->assertRedirect();
        $this->assertStringContainsString('Confirmar entrega', ContactEvent::where('type', 'whatsapp')->sole()->body);

        $this->actingAs($user)->delete(route('dashboard.clientes.templates.destroy', 'propia-' . $own->id))->assertRedirect();
        $this->assertModelMissing($own);
    }

    public function test_templates_page_shows_a_preview(): void
    {
        [$user, $store] = $this->owner();

        $this->actingAs($user)->get(route('dashboard.clientes.templates.index'))->assertOk()
            ->assertSee('Cobranza amable')->assertSee('queda un saldo de S/ 120.00')->assertSee('{nombre}', false);
    }

    public function test_templates_and_contacts_stay_in_their_store(): void
    {
        [$user, $store] = $this->owner();
        [$otherUser, $other] = $this->owner('otra');
        $this->rosa($other);
        $foreign = Contact::sole();
        $foreignTemplate = MessageTemplate::create(['store_id' => $other->id, 'name' => 'Ajena', 'body' => 'x']);

        $this->actingAs($user)->get(route('dashboard.clientes.whatsapp', [$foreign->id, 'plantilla' => 'follow_up']))->assertNotFound();
        $this->actingAs($user)->put(route('dashboard.clientes.templates.update', 'propia-' . $foreignTemplate->id), ['body' => 'hack'])->assertNotFound();
        $this->actingAs($user)->delete(route('dashboard.clientes.templates.destroy', 'propia-' . $foreignTemplate->id))->assertNotFound();
        $this->assertSame('x', $foreignTemplate->fresh()->body);
    }

    public function test_a_balance_template_needs_something_owed(): void
    {
        [$user, $store] = $this->owner();
        $rosa = $this->rosa($store);
        Order::where('order_number', 'NV-2')->sole()->recordPayment(70, 'balance', 'yape', null);   // paid off

        $this->actingAs($user)->get(route('dashboard.clientes.whatsapp', [$rosa->id, 'plantilla' => 'payment']))
            ->assertRedirect()->assertSessionHas('error', 'Rosa Quispe Mamani no tiene saldo pendiente: «Cobranza amable» saldría sin monto.');
        $this->actingAs($user)->get(route('dashboard.clientes.show', $rosa->id))->assertSee('Cobranza amable · sin saldo');
        $this->assertSame(0, ContactEvent::where('type', 'whatsapp')->count());
    }

    public function test_no_phone_no_whatsapp(): void
    {
        [$user, $store] = $this->owner();
        $contact = $store->contacts()->create(['name' => 'Sin Celular', 'email' => 'sin@correo.test', 'source' => 'manual']);

        $this->actingAs($user)->get(route('dashboard.clientes.whatsapp', [$contact->id, 'plantilla' => 'follow_up']))
            ->assertRedirect()->assertSessionHas('error', 'Sin Celular no tiene celular registrado.');
    }
}
