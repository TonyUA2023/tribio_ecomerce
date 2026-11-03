<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsappMessage;
use App\Jobs\StartWhatsappBroadcast;
use App\Models\Contact;
use App\Models\ContactEvent;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use App\Models\WhatsappBroadcast;
use App\Models\WhatsappConnection;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Models\WhatsappTemplate;
use App\Services\Whatsapp\BroadcastService;
use App\Services\Whatsapp\InboxService;
use App\Services\Whatsapp\WhatsappBudget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/** ERP Fase 3c: WhatsApp broadcasts with cost estimate, monthly cap, Meta's limit and opt-out. */
class WhatsappBroadcastsTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = '0123456789abcdef0123456789abcdef';
    private const TOKEN = 'EAAG-test-token-for-the-sandbox-only';

    protected function migrateFreshUsing(): array
    {
        return ['--path' => array_values(array_filter(glob(database_path('migrations/*.php')),
            fn ($path) => !str_ends_with($path, '2026_09_11_182936_update_role_column_in_users_table.php'))), '--realpath' => true];
    }

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Http::preventStrayRequests();
        config(['app.url' => 'http://localhost', 'services.whatsapp.rates.marketing' => 0.25, 'services.whatsapp.rates.utility' => 0.10]);
    }

    private function owner(string $slug = 'nutri', ?float $budget = 20): array
    {
        $user = User::factory()->create(['role' => 'store_owner']);
        $store = Store::create(['user_id' => $user->id, 'name' => 'Nutri Vida', 'slug' => $slug, 'status' => 'active', 'template_name' => 'soft-market']);
        $connection = WhatsappConnection::create([
            'store_id' => $store->id, 'waba_id' => '2233445566', 'phone_number_id' => $slug === 'nutri' ? '1098765432' : '5550001111',
            'access_token' => self::TOKEN, 'app_secret' => self::SECRET, 'verify_token' => str_repeat('v', 40),
            'webhook_key' => str_pad($slug, 40, 'k'), 'status' => 'connected', 'connected_at' => now(),
        ]);
        $connection->forceFill(['monthly_budget' => $budget])->save();

        return [$user, $store, $connection];
    }

    /** Rosa (bought, consented), Luis (no orders, no consent), Ana (no phone). */
    private function customers(Store $store): array
    {
        Order::create(['store_id' => $store->id, 'order_number' => 'NV-7', 'customer_name' => 'Rosa Quispe', 'customer_phone' => '987111222',
            'customer_country' => 'PE', 'subtotal' => 80, 'total' => 80, 'currency' => 'PEN', 'status' => 'delivered', 'payment_status' => 'paid', 'payment_method' => 'yape']);
        $rosa = Contact::where('name', 'Rosa Quispe')->sole();
        $rosa->forceFill(['accepts_marketing' => true, 'lifecycle_stage' => 'nuevo'])->save();
        $luis = Contact::create(['store_id' => $store->id, 'name' => 'Luis Rojas', 'phone' => '51987333444', 'source' => 'manual']);
        $luis->forceFill(['lifecycle_stage' => 'lead'])->save();
        $ana = Contact::create(['store_id' => $store->id, 'name' => 'Ana Díaz', 'source' => 'manual']);

        return [$rosa, $luis, $ana];
    }

    private function approved(WhatsappConnection $connection, string $name, string $body, ?array $params, string $category = 'UTILITY'): WhatsappTemplate
    {
        return WhatsappTemplate::create([
            'store_id' => $connection->store_id, 'connection_id' => $connection->id, 'meta_id' => (string) random_int(1000, 99999), 'name' => $name,
            'language' => 'es', 'category' => $category, 'status' => 'APPROVED', 'body' => $body,
            'components' => [['type' => 'BODY', 'text' => $body]], 'params' => $params,
        ]);
    }

    private function fakeMeta(): void
    {
        Http::fake(['graph.facebook.com/*/1098765432/messages' => fn () => Http::response(['messages' => [['id' => 'wamid.' . Str::random(12)]]])]);
    }

    private function signed(WhatsappConnection $connection, array $value)
    {
        $raw = json_encode(['object' => 'whatsapp_business_account', 'entry' => [['id' => '2233445566', 'changes' => [['field' => 'messages',
            'value' => ['metadata' => ['display_phone_number' => '51900111222', 'phone_number_id' => '1098765432']] + $value]]]]]);

        return $this->call('POST', route('webhooks.whatsapp.receive', $connection->webhook_key), [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256=' . hash_hmac('sha256', $raw, self::SECRET)], $raw);
    }

    public function test_the_estimate_shows_reach_exclusions_cost_and_a_sample(): void
    {
        [$user, $store, $connection] = $this->owner();
        $this->customers($store);
        $utility = $this->approved($connection, 'tribio_thanks', 'Hola {{1}}, gracias por tu pedido {{2}} en Nutri Vida.', ['nombre', 'pedido']);
        $promo = $this->approved($connection, 'tribio_news', 'Hola {{1}}, tenemos novedades en Nutri Vida.', ['nombre'], 'MARKETING');

        $this->actingAs($user)->postJson(route('dashboard.whatsapp.broadcasts.estimate'), ['template_id' => $utility->id])
            ->assertOk()->assertJson(['matched' => 3, 'no_phone' => 1, 'no_consent' => 0, 'recipients' => 2, 'rate' => 0.1, 'cost' => 0.2,
                'budget' => ['budget' => 20, 'spent' => 0, 'available' => 20], 'limit' => 250, 'problems' => []])
            ->assertJsonPath('sample.text', 'Hola Rosa, gracias por tu pedido #NV-7 en Nutri Vida.');

        // Marketing only reaches customers who accepted offers; filters narrow the group.
        $this->actingAs($user)->postJson(route('dashboard.whatsapp.broadcasts.estimate'), ['template_id' => $promo->id])
            ->assertJson(['recipients' => 1, 'no_consent' => 1, 'cost' => 0.25]);
        $this->actingAs($user)->postJson(route('dashboard.whatsapp.broadcasts.estimate'), ['template_id' => $promo->id, 'stage' => 'lead'])
            ->assertJsonPath('problems.0', 'Ningún cliente de ese grupo aceptó recibir ofertas: con una plantilla de marketing no se le puede escribir.');

        // A Meta-made template asks for the text of each variable.
        $meta = $this->approved($connection, 'oferta_semana', 'Hola {{1}}, esta semana {{2}} con descuento.', null, 'MARKETING');
        $this->actingAs($user)->postJson(route('dashboard.whatsapp.broadcasts.estimate'), ['template_id' => $meta->id])
            ->assertJsonPath('problems.0', 'Escribe el texto del dato {{1}}.');
        $this->actingAs($user)->postJson(route('dashboard.whatsapp.broadcasts.estimate'), ['template_id' => $meta->id, 'variables' => [
            '1' => ['source' => 'nombre'], '2' => ['source' => 'fixed', 'text' => "Batido\nde fresa"]]])
            ->assertJson(['problems' => []])->assertJsonPath('sample.text', 'Hola Rosa, esta semana Batido de fresa con descuento.');
    }

    public function test_a_broadcast_needs_a_cap_and_stays_within_it_and_metas_limit(): void
    {
        [$user, $store, $connection] = $this->owner(budget: null);
        $this->customers($store);
        $template = $this->approved($connection, 'tribio_follow_up', 'Hola {{1}}, ¿cómo vas?', ['nombre']);
        $estimate = fn () => $this->actingAs($user)->postJson(route('dashboard.whatsapp.broadcasts.estimate'), ['template_id' => $template->id])->json('problems');

        $this->assertSame(['Define tu tope mensual de gasto en WhatsApp antes de enviar difusiones.'], $estimate());

        $this->actingAs($user)->put(route('dashboard.whatsapp.broadcasts.budget'), ['monthly_budget' => '1', 'rates' => ['utility' => '0.40', 'marketing' => '']])
            ->assertSessionHas('success');
        $this->assertSame([0.4, 0.25], [$connection->fresh()->rate('utility'), $connection->fresh()->rate('marketing_lite')]);
        $this->assertSame([], $estimate(), '2 × S/ 0.40 fits in S/ 1');

        // Already billed this month: S/ 0.40 → only S/ 0.60 left.
        $chat = WhatsappConversation::create(['store_id' => $store->id, 'connection_id' => $connection->id, 'wa_id' => '51911000000']);
        WhatsappMessage::create(['store_id' => $store->id, 'conversation_id' => $chat->id, 'direction' => 'out', 'type' => 'template', 'body' => 'x', 'status' => 'delivered'])
            ->forceFill(['cost' => 0.40])->save();
        $this->assertSame(['Costaría ~S/ 0.80 y te quedan S/ 0.60 de tu tope mensual (S/ 1.00). Reduce el público o sube el tope.'], $estimate());

        $this->actingAs($user)->post(route('dashboard.whatsapp.broadcasts.store'), ['name' => 'Seguimiento', 'template_id' => $template->id, 'send' => 'now'])
            ->assertSessionHasErrors('broadcast');
        $this->assertSame(0, WhatsappBroadcast::count());

        // Meta's 24 h limit: the template above already counts as one customer written first.
        $connection->forceFill(['monthly_budget' => 50, 'messaging_limit_tier' => 'TIER_2', 'messaging_limit' => 2])->save();
        $this->assertSame(['Meta te deja escribir primero a 2 clientes cada 24 h y ya usaste 1. Reduce el público a 1 o prográmala para mañana.'], $estimate());
        $this->assertSame([['TIER_2K', 2000], ['TIER_UNLIMITED', null], [null, null]],
            [WhatsappBudget::parseTier('TIER_2K'), WhatsappBudget::parseTier(['current_limit' => 'TIER_UNLIMITED']), WhatsappBudget::parseTier(null)]);
    }

    public function test_sending_now_queues_one_message_per_customer_and_skips_missing_data(): void
    {
        [$user, $store, $connection] = $this->owner();
        [$rosa, $luis] = $this->customers($store);
        $template = $this->approved($connection, 'tribio_thanks', 'Hola {{1}}, gracias por tu pedido {{2}} en Nutri Vida.', ['nombre', 'pedido']);
        $this->fakeMeta();

        $this->actingAs($user)->post(route('dashboard.whatsapp.broadcasts.store'), ['name' => 'Gracias', 'template_id' => $template->id, 'send' => 'now', 'skip_recent' => '1'])
            ->assertRedirect()->assertSessionHas('success');

        $broadcast = WhatsappBroadcast::sole();
        $this->assertSame(['done', 1, 1, 0.1], [$broadcast->status, $broadcast->recipients_count, $broadcast->skipped_count, (float) $broadcast->estimated_cost]);
        Http::assertSentCount(1);
        Http::assertSent(fn (HttpRequest $request) => $request['to'] === '51987111222' && $request['template']['name'] === 'tribio_thanks'
            && $request['template']['components'][0]['parameters'][1]['text'] === '#NV-7' && $request->hasHeader('Authorization', 'Bearer ' . self::TOKEN));

        $sent = WhatsappMessage::sole();
        $this->assertSame([$broadcast->id, 'sent', 'Hola Rosa, gracias por tu pedido #NV-7 en Nutri Vida.'], [$sent->broadcast_id, $sent->status, $sent->body]);
        $this->assertSame($rosa->id, $sent->conversation->contact_id);
        $this->assertSame('Sin dato: Último pedido (#NV-123)', $broadcast->recipients()->where('contact_id', $luis->id)->value('reason'));
        $this->assertTrue(ContactEvent::where('contact_id', $rosa->id)->where('body', 'like', 'Difusión «Gracias» por WhatsApp:%')->exists());

        // A customer who just got one is left out of the next broadcast.
        $this->actingAs($user)->postJson(route('dashboard.whatsapp.broadcasts.estimate'), ['template_id' => $template->id, 'skip_recent' => '1'])
            ->assertJson(['recent' => 1, 'recipients' => 1]);

        $this->actingAs($user)->get(route('dashboard.whatsapp.broadcasts.show', $broadcast->id))->assertOk()
            ->assertSee('Gracias')->assertSee('Rosa Quispe')->assertSee('Sin dato: Último pedido (#NV-123)');
        $this->actingAs($user)->get(route('dashboard.whatsapp.broadcasts.index'))->assertOk()->assertSee('Gracias')->assertSee('S/ 20.00');
        $this->actingAs($user)->get(route('dashboard.whatsapp.broadcasts.create'))->assertOk()->assertSee('tribio_thanks');
    }

    public function test_a_scheduled_broadcast_waits_reserves_budget_and_can_be_cancelled(): void
    {
        [$user, $store, $connection] = $this->owner();
        $this->customers($store);
        $template = $this->approved($connection, 'tribio_follow_up', 'Hola {{1}}, ¿cómo vas?', ['nombre']);
        $this->fakeMeta();
        $at = now(WhatsappBudget::TIMEZONE)->addDay()->setTime(10, 0);

        $this->actingAs($user)->post(route('dashboard.whatsapp.broadcasts.store'), [
            'name' => 'Mañana', 'template_id' => $template->id, 'send' => 'later', 'scheduled_at' => $at->format('Y-m-d\TH:i'),
        ])->assertSessionHas('success');
        $first = WhatsappBroadcast::sole();
        $this->assertSame(['scheduled', 0.2], [$first->status, (float) $first->estimated_cost]);
        $this->assertTrue($first->scheduled_at->eq($at));
        $this->assertSame(0.2, app(WhatsappBudget::class)->summary($connection)['reserved']);
        Http::assertNothingSent();

        $this->actingAs($user)->post(route('dashboard.whatsapp.broadcasts.store'), [
            'name' => 'Otra', 'template_id' => $template->id, 'send' => 'later', 'scheduled_at' => $at->format('Y-m-d\TH:i'),
        ]);
        $second = WhatsappBroadcast::latest('id')->first();
        $this->actingAs($user)->post(route('dashboard.whatsapp.broadcasts.cancel', $second->id))->assertSessionHas('success');
        $this->assertSame('cancelled', $second->fresh()->status);

        $this->artisan('whatsapp:broadcasts')->assertSuccessful();
        Http::assertNothingSent();

        $this->travelTo($at->copy()->addMinute());
        $this->artisan('whatsapp:broadcasts')->assertSuccessful();
        $this->assertSame(['done', 2], [$first->fresh()->status, $first->fresh()->recipients_count]);
        $this->assertSame('cancelled', $second->fresh()->status);
        Http::assertSentCount(2);
    }

    public function test_cancelling_stops_the_messages_still_in_the_queue(): void
    {
        [, $store, $connection] = $this->owner();
        $this->customers($store);
        $template = $this->approved($connection, 'tribio_follow_up', 'Hola {{1}}, ¿cómo vas?', ['nombre']);
        Queue::fake();

        $broadcasts = app(BroadcastService::class);
        $broadcast = $broadcasts->create($store, $connection, $template, ['name' => 'Seguimiento'], null);
        Queue::assertPushed(StartWhatsappBroadcast::class);
        $broadcasts->start($broadcast->fresh());
        Queue::assertPushed(SendWhatsappMessage::class, 2);
        $this->assertSame(['sending', 2], [$broadcast->fresh()->status, WhatsappMessage::where('status', 'queued')->count()]);

        $broadcasts->cancel($broadcast->fresh());
        $message = WhatsappMessage::first();
        app(InboxService::class)->deliver($message->fresh());
        Http::assertNothingSent();
        $this->assertSame(['failed', 'Cancelaste la difusión antes de que saliera este mensaje.'], [$message->fresh()->status, $message->fresh()->error]);
        $this->assertSame('cancelled', $broadcast->fresh()->status);
    }

    public function test_meta_pricing_records_the_cost_and_the_cap_stops_broadcast_messages(): void
    {
        [, $store, $connection] = $this->owner(budget: 0.30);
        $this->customers($store);
        $template = $this->approved($connection, 'tribio_follow_up', 'Hola {{1}}, ¿cómo vas?', ['nombre']);
        $this->fakeMeta();
        $broadcast = app(BroadcastService::class)->create($store, $connection, $template, ['name' => 'Seguimiento'], null);
        [$first, $second] = WhatsappMessage::orderBy('id')->get()->all();

        $status = fn (string $wamid, string $type) => ['statuses' => [['id' => $wamid, 'status' => 'delivered', 'timestamp' => (string) now()->timestamp,
            'recipient_id' => '51987111222', 'pricing' => ['billable' => $type === 'regular', 'pricing_model' => 'PMP', 'category' => 'utility', 'type' => $type]]]];
        $this->signed($connection, $status($first->wamid, 'regular'))->assertOk();
        $this->signed($connection, $status($second->wamid, 'free_customer_service'))->assertOk();
        $this->signed($connection, $status($first->wamid, 'regular'))->assertOk();

        $this->assertSame([0.1, 'utility', 0.0], [(float) $first->fresh()->cost, $first->fresh()->billing_category, (float) $second->fresh()->cost]);
        $this->assertSame(0.1, app(WhatsappBudget::class)->summary($connection)['spent']);

        // Bills reached the cap: a broadcast message still in the queue does not leave.
        $first->fresh()->forceFill(['cost' => 0.30])->save();
        $chat = WhatsappConversation::create(['store_id' => $store->id, 'connection_id' => $connection->id, 'wa_id' => '51911000000']);
        $queued = WhatsappMessage::create(['store_id' => $store->id, 'conversation_id' => $chat->id, 'broadcast_id' => $broadcast->id, 'direction' => 'out',
            'type' => 'template', 'body' => 'x', 'payload' => ['template_id' => $template->id, 'values' => ['1' => 'Eva']], 'status' => 'queued']);
        app(InboxService::class)->deliver($queued);
        Http::assertSentCount(2);
        $this->assertSame('Se alcanzó tu tope mensual de gasto en WhatsApp.', $queued->fresh()->error);
    }

    public function test_a_customer_who_writes_baja_stops_getting_marketing(): void
    {
        [, $store, $connection] = $this->owner();
        [$rosa] = $this->customers($store);
        $message = fn (string $id, string $text) => ['contacts' => [['profile' => ['name' => 'Rosa'], 'wa_id' => '51987111222']],
            'messages' => [['from' => '51987111222', 'id' => $id, 'timestamp' => (string) now()->timestamp, 'type' => 'text', 'text' => ['body' => $text]]]];

        $this->signed($connection, $message('wamid.IN1', 'No más de 2 por favor'))->assertOk();
        $this->assertTrue($rosa->fresh()->accepts_marketing, 'Only a whole "stop" message counts');

        $this->signed($connection, $message('wamid.IN2', ' ¡BAJA! '))->assertOk();
        $this->assertFalse($rosa->fresh()->accepts_marketing);
        $this->assertTrue(ContactEvent::where('contact_id', $rosa->id)->where('body', 'like', 'Pidió por WhatsApp no recibir más ofertas%')->exists());

        // Meta's own opt-out button arrives as a quick reply.
        $rosa->forceFill(['accepts_marketing' => true])->save();
        $this->signed($connection, ['contacts' => [['profile' => ['name' => 'Rosa'], 'wa_id' => '51987111222']], 'messages' => [[
            'from' => '51987111222', 'id' => 'wamid.IN3', 'timestamp' => (string) now()->timestamp, 'type' => 'button', 'button' => ['text' => 'Detener promociones', 'payload' => 'STOP']]]])->assertOk();
        $this->assertFalse($rosa->fresh()->accepts_marketing);
    }

    public function test_results_count_replies_and_orders_after_the_broadcast(): void
    {
        [$user, $store, $connection] = $this->owner();
        [$rosa] = $this->customers($store);
        $template = $this->approved($connection, 'tribio_follow_up', 'Hola {{1}}, ¿cómo vas?', ['nombre']);
        $this->fakeMeta();
        $broadcast = app(BroadcastService::class)->create($store, $connection, $template, ['name' => 'Seguimiento'], $user->id);
        $sent = WhatsappMessage::where('conversation_id', WhatsappConversation::where('contact_id', $rosa->id)->value('id'))->sole();

        $this->travel(2)->hours();
        $this->signed($connection, ['contacts' => [['profile' => ['name' => 'Rosa'], 'wa_id' => '51987111222']], 'messages' => [[
            'from' => '51987111222', 'id' => 'wamid.R1', 'timestamp' => (string) now()->timestamp, 'type' => 'text', 'text' => ['body' => 'Quiero 2 más']]]])->assertOk();
        Order::create(['store_id' => $store->id, 'order_number' => 'NV-8', 'customer_name' => 'Rosa Quispe', 'customer_phone' => '987111222',
            'customer_country' => 'PE', 'subtotal' => 160, 'total' => 160, 'currency' => 'PEN', 'status' => 'pending', 'payment_status' => 'pending', 'payment_method' => 'yape']);

        $stats = app(BroadcastService::class)->stats($broadcast->fresh());
        $this->assertSame([2, 1, 1, 160.0], [$stats['sent'], $stats['replied'], $stats['orders'], $stats['revenue']]);
        $this->assertSame($broadcast->id, $sent->broadcast_id);
        $this->actingAs($user)->get(route('dashboard.whatsapp.broadcasts.show', $broadcast->id))->assertOk()->assertSee('S/ 160.00');
    }

    public function test_connecting_reads_metas_messaging_limit(): void
    {
        [$user, , $connection] = $this->owner();
        Http::fake(fn (HttpRequest $request) => match (true) {
            str_contains($request->url(), 'subscribed_apps') => Http::response(['success' => true]),
            str_contains($request->url(), 'whatsapp_business_manager_messaging_limit') => Http::response(['whatsapp_business_manager_messaging_limit' => 'TIER_2K', 'id' => '1098765432']),
            default => Http::response(['display_phone_number' => '+51 900 111 222', 'verified_name' => 'Nutri Vida']),
        });

        $this->actingAs($user)->post(route('dashboard.whatsapp.connection.test'))->assertRedirect();
        $this->assertSame(['TIER_2K', 2000], [$connection->fresh()->messaging_limit_tier, $connection->fresh()->messaging_limit]);
        $this->actingAs($user)->get(route('dashboard.whatsapp.connection.edit'))->assertSee('hasta 2,000 clientes cada 24 h');
    }

    public function test_another_stores_broadcasts_and_templates_are_out_of_reach(): void
    {
        [$user, $store, $connection] = $this->owner();
        $this->customers($store);
        [$other, , $otherConnection] = $this->owner('otra');
        $foreign = $this->approved($otherConnection, 'tribio_follow_up', 'Hola {{1}}', ['nombre']);
        $mine = $this->approved($connection, 'tribio_follow_up', 'Hola {{1}}, ¿cómo vas?', ['nombre']);
        $this->fakeMeta();
        $broadcast = app(BroadcastService::class)->create($store, $connection, $mine, ['name' => 'Mía'], $user->id);

        $this->actingAs($other)->get(route('dashboard.whatsapp.broadcasts.show', $broadcast->id))->assertNotFound();
        $this->actingAs($other)->post(route('dashboard.whatsapp.broadcasts.cancel', $broadcast->id))->assertNotFound();
        $this->actingAs($user)->postJson(route('dashboard.whatsapp.broadcasts.estimate'), ['template_id' => $foreign->id])->assertJsonValidationErrors('template_id');
        $this->actingAs($other)->postJson(route('dashboard.whatsapp.broadcasts.estimate'), ['template_id' => $foreign->id])->assertJson(['recipients' => 0]);
        $this->actingAs($other)->get(route('dashboard.whatsapp.broadcasts.index'))->assertOk()->assertDontSee('Mía');
    }
}
