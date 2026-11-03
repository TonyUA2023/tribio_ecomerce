<?php

namespace Tests\Feature;

use App\Jobs\ProcessWhatsappWebhook;
use App\Models\Contact;
use App\Models\ContactEvent;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use App\Models\WhatsappConnection;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Models\WhatsappWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** ERP Fase 3a: WhatsApp Cloud API — signed webhooks, inbox, 24 h window, delivery statuses. */
class WhatsappInboxTest extends TestCase
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
    }

    private function owner(string $slug = 'nutri'): array
    {
        $user = User::factory()->create(['role' => 'store_owner']);
        $store = Store::create(['user_id' => $user->id, 'name' => 'Nutri Vida', 'slug' => $slug, 'status' => 'active', 'template_name' => 'soft-market']);

        return [$user, $store];
    }

    private function connection(Store $store, string $phoneNumberId = '1098765432'): WhatsappConnection
    {
        return WhatsappConnection::create([
            'store_id' => $store->id, 'waba_id' => '2233445566', 'phone_number_id' => $phoneNumberId, 'display_phone_number' => '+51 900 111 222',
            'access_token' => self::TOKEN, 'app_secret' => self::SECRET, 'verify_token' => str_repeat('v', 40),
            'webhook_key' => str_pad($phoneNumberId, 40, 'k'), 'status' => 'connected', 'connected_at' => now(),
        ]);
    }

    private function incoming(string $phoneNumberId, array $message, array $contact): array
    {
        return ['object' => 'whatsapp_business_account', 'entry' => [['id' => '2233445566', 'changes' => [['field' => 'messages', 'value' => [
            'messaging_product' => 'whatsapp',
            'metadata'          => ['display_phone_number' => '51900111222', 'phone_number_id' => $phoneNumberId],
            'contacts'          => [$contact],
            'messages'          => [$message],
        ]]]]]];
    }

    private function text(string $body, string $wamid, ?string $from = '51987111222', string $userId = 'PE.1200000000000001', ?int $at = null): array
    {
        return array_filter(['from' => $from, 'from_user_id' => $userId, 'id' => $wamid, 'timestamp' => (string) ($at ?? now()->timestamp),
            'type' => 'text', 'text' => ['body' => $body]], fn ($v) => $v !== null);
    }

    private function profile(string $name = 'Rosa Quispe', ?string $phone = '51987111222', string $userId = 'PE.1200000000000001'): array
    {
        return array_filter(['profile' => ['name' => $name], 'wa_id' => $phone, 'user_id' => $userId], fn ($v) => $v !== null);
    }

    private function deliver(WhatsappConnection $connection, array $payload, ?string $secret = self::SECRET)
    {
        $raw = json_encode($payload);
        $headers = ['CONTENT_TYPE' => 'application/json'];
        if ($secret !== null) {
            $headers['HTTP_X_HUB_SIGNATURE_256'] = 'sha256=' . hash_hmac('sha256', $raw, $secret);
        }

        return $this->call('POST', route('webhooks.whatsapp.receive', $connection->webhook_key), [], [], [], $headers, $raw);
    }

    public function test_meta_verifies_the_webhook_with_the_store_token(): void
    {
        [, $store] = $this->owner();
        $connection = $this->connection($store);
        $url = route('webhooks.whatsapp.verify', $connection->webhook_key);

        $this->get($url . '?hub.mode=subscribe&hub.verify_token=' . str_repeat('v', 40) . '&hub.challenge=1158201444')
            ->assertOk()->assertSeeText('1158201444');
        $this->get($url . '?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=1')->assertForbidden();
        $this->get(route('webhooks.whatsapp.verify', str_repeat('x', 40)) . '?hub.mode=subscribe')->assertNotFound();
    }

    public function test_unsigned_or_badly_signed_events_are_rejected(): void
    {
        [, $store] = $this->owner();
        $connection = $this->connection($store);
        $payload = $this->incoming($connection->phone_number_id, $this->text('Hola', 'wamid.A1'), $this->profile());

        $this->deliver($connection, $payload, null)->assertForbidden();
        $this->deliver($connection, $payload, 'ffffffffffffffffffffffffffffffff')->assertForbidden();
        $this->assertSame(0, WhatsappWebhookEvent::count());
        $this->assertSame(0, WhatsappMessage::count());
    }

    public function test_an_incoming_message_lands_in_the_inbox_linked_to_the_existing_customer(): void
    {
        [, $store] = $this->owner();
        $connection = $this->connection($store);
        Order::create(['store_id' => $store->id, 'order_number' => 'NV-1', 'customer_name' => 'Rosa Quispe Mamani', 'customer_phone' => '987111222',
            'customer_country' => 'PE', 'subtotal' => 80, 'total' => 80, 'currency' => 'PEN', 'status' => 'delivered', 'payment_status' => 'paid', 'payment_method' => 'yape']);
        $rosa = Contact::sole();

        $this->deliver($connection, $this->incoming($connection->phone_number_id, $this->text('¿Tienen el batido de fresa?', 'wamid.A1'), $this->profile()))
            ->assertOk()->assertSeeText('EVENT_RECEIVED');

        $chat = WhatsappConversation::sole();
        $this->assertSame([$rosa->id, '51987111222', 'PE.1200000000000001', 1], [$chat->contact_id, $chat->wa_id, $chat->wa_user_id, $chat->unread_count]);
        $this->assertTrue($chat->windowOpen());
        $this->assertSame('¿Tienen el batido de fresa?', WhatsappMessage::sole()->body);
        $this->assertSame(1, Contact::count(), 'No duplicate customer');
        $this->assertSame('Te escribió por WhatsApp: «¿Tienen el batido de fresa?»', ContactEvent::where('type', 'whatsapp')->sole()->body);
        $this->assertNotNull(WhatsappWebhookEvent::sole()->processed_at);

        // Same delivery again (Meta retries): nothing new. A second message in the same burst: no new timeline entry.
        $this->deliver($connection, $this->incoming($connection->phone_number_id, $this->text('¿Tienen el batido de fresa?', 'wamid.A1'), $this->profile()))->assertOk();
        $this->deliver($connection, $this->incoming($connection->phone_number_id, $this->text('Y de chocolate', 'wamid.A2'), $this->profile()))->assertOk();
        $this->assertSame(2, WhatsappMessage::count());
        $this->assertSame(2, $chat->fresh()->unread_count);
        $this->assertSame(1, ContactEvent::where('type', 'whatsapp')->count());
    }

    public function test_a_new_number_becomes_a_lead_and_a_username_user_without_phone_still_gets_a_chat(): void
    {
        [, $store] = $this->owner();
        $connection = $this->connection($store);

        $this->deliver($connection, $this->incoming($connection->phone_number_id, $this->text('Precio del pack', 'wamid.B1', '51955444333', 'PE.77'), $this->profile('Lucía', '51955444333', 'PE.77')));
        $lucia = Contact::where('phone', '51955444333')->sole();
        $this->assertSame(['Lucía', 'whatsapp', 'lead'], [$lucia->name, $lucia->source, $lucia->lifecycle_stage]);

        // Since 2026 Meta may omit the phone for customers with a username: matched by business-scoped ID only.
        $this->deliver($connection, $this->incoming($connection->phone_number_id, $this->text('Hola', 'wamid.C1', null, 'PE.99'), $this->profile('Mateo', null, 'PE.99')));
        $this->deliver($connection, $this->incoming($connection->phone_number_id, $this->text('¿Siguen ahí?', 'wamid.C2', null, 'PE.99'), $this->profile('Mateo', null, 'PE.99')));
        $mateo = WhatsappConversation::where('wa_user_id', 'PE.99')->sole();
        $this->assertNull($mateo->wa_id);
        $this->assertSame('Mateo', $mateo->contact->name);
        $this->assertSame(2, $mateo->messages()->count());
    }

    public function test_events_for_another_number_are_ignored(): void
    {
        [, $store] = $this->owner();
        $connection = $this->connection($store);

        $this->deliver($connection, $this->incoming('5550000000', $this->text('Hola', 'wamid.X1'), $this->profile()))->assertOk();

        $this->assertSame(0, WhatsappMessage::count());
        $this->assertSame(0, Contact::count());
    }

    public function test_media_is_shown_as_text_with_its_caption(): void
    {
        [, $store] = $this->owner();
        $connection = $this->connection($store);
        $photo = ['from' => '51987111222', 'from_user_id' => 'PE.1200000000000001', 'id' => 'wamid.M1', 'timestamp' => (string) now()->timestamp,
            'type' => 'image', 'image' => ['id' => '9988', 'mime_type' => 'image/jpeg', 'caption' => 'Este quiero']];

        $this->deliver($connection, $this->incoming($connection->phone_number_id, $photo, $this->profile()));

        $message = WhatsappMessage::sole();
        $this->assertSame(['image', '📷 Foto · Este quiero', '9988'], [$message->type, $message->body, $message->payload['media_id']]);
    }

    public function test_a_reply_inside_the_window_is_sent_and_tracked_to_read(): void
    {
        [$user, $store] = $this->owner();
        $connection = $this->connection($store);
        $this->deliver($connection, $this->incoming($connection->phone_number_id, $this->text('Hola', 'wamid.A1'), $this->profile()));
        $chat = WhatsappConversation::sole();
        Http::fake(['graph.facebook.com/*/1098765432/messages' => Http::response(['messaging_product' => 'whatsapp', 'messages' => [['id' => 'wamid.OUT1']]])]);

        $this->actingAs($user)->get(route('dashboard.whatsapp.show', $chat->id))->assertOk()->assertSee('Hola');
        $this->assertSame(0, $chat->fresh()->unread_count, 'Opening the chat marks it read');

        $this->actingAs($user)->post(route('dashboard.whatsapp.reply', $chat->id), ['body' => 'Sí, ¡hay de fresa!'])->assertRedirect(route('dashboard.whatsapp.show', $chat->id));

        $sent = WhatsappMessage::where('direction', 'out')->sole();
        $this->assertSame(['wamid.OUT1', 'sent', $user->id], [$sent->wamid, $sent->status, $sent->user_id]);
        Http::assertSent(fn (HttpRequest $request) => $request->hasHeader('Authorization', 'Bearer ' . self::TOKEN)
            && !str_contains($request->url(), self::TOKEN)
            && $request['to'] === '51987111222' && $request['text']['body'] === 'Sí, ¡hay de fresa!');

        $statuses = fn (string $state) => ['object' => 'whatsapp_business_account', 'entry' => [['id' => '2233445566', 'changes' => [['field' => 'messages', 'value' => [
            'metadata' => ['phone_number_id' => '1098765432'],
            'statuses' => [['id' => 'wamid.OUT1', 'status' => $state, 'timestamp' => (string) now()->timestamp, 'recipient_id' => '51987111222']],
        ]]]]]];
        $this->deliver($connection, $statuses('read'));
        $this->deliver($connection, $statuses('delivered')); // late, out of order
        $this->assertSame('read', $sent->fresh()->status);
        $this->assertNotNull($sent->fresh()->read_at);

        $poll = $this->actingAs($user)->getJson(route('dashboard.whatsapp.poll', [$chat->id, 'after' => 0]))->assertOk();
        $this->assertSame('Leído', $poll->json("statuses.{$sent->id}.label"));
        $this->assertTrue($poll->json('window_open'));
    }

    public function test_a_username_customer_is_answered_by_business_scoped_id(): void
    {
        [$user, $store] = $this->owner();
        $connection = $this->connection($store);
        $this->deliver($connection, $this->incoming($connection->phone_number_id, $this->text('Hola', 'wamid.C1', null, 'PE.99'), $this->profile('Mateo', null, 'PE.99')));
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.OUT2']]])]);

        $this->actingAs($user)->post(route('dashboard.whatsapp.reply', WhatsappConversation::sole()->id), ['body' => 'Hola Mateo']);

        Http::assertSent(fn (HttpRequest $request) => $request['recipient'] === 'PE.99' && !isset($request['to']));
    }

    public function test_free_text_is_refused_once_the_24_hour_window_closes(): void
    {
        [$user, $store] = $this->owner();
        $connection = $this->connection($store);
        $this->deliver($connection, $this->incoming($connection->phone_number_id, $this->text('Hola', 'wamid.A1', at: now()->subHours(25)->timestamp), $this->profile()));
        $chat = WhatsappConversation::sole();

        $this->actingAs($user)->post(route('dashboard.whatsapp.reply', $chat->id), ['body' => '¿Sigues interesada?'])
            ->assertSessionHasErrors(['body' => 'Pasaron más de 24 h desde el último mensaje del cliente: Meta solo permite escribirle con una plantilla aprobada.']);
        $this->assertSame(0, WhatsappMessage::where('direction', 'out')->count());
        $this->actingAs($user)->get(route('dashboard.whatsapp.show', $chat->id))->assertSee('Pasaron más de 24 h desde el último mensaje de Rosa Quispe');
        Http::assertNothingSent();
    }

    public function test_a_permanent_meta_error_marks_the_bubble_failed_in_spanish(): void
    {
        [$user, $store] = $this->owner();
        $connection = $this->connection($store);
        $this->deliver($connection, $this->incoming($connection->phone_number_id, $this->text('Hola', 'wamid.A1'), $this->profile()));
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['code' => 190, 'message' => 'Invalid OAuth access token ' . self::TOKEN]], 401)]);

        $this->actingAs($user)->post(route('dashboard.whatsapp.reply', WhatsappConversation::sole()->id), ['body' => 'Hola']);

        $sent = WhatsappMessage::where('direction', 'out')->sole();
        $this->assertSame('failed', $sent->status);
        $this->assertStringStartsWith('El token de acceso no es válido o venció.', $sent->error);
        $this->assertStringNotContainsString(self::TOKEN, $sent->error);
    }

    public function test_the_owner_connects_a_number_and_gets_the_webhook_data(): void
    {
        [$user, $store] = $this->owner();
        Http::fake([
            'graph.facebook.com/*/1098765432?*' => Http::response(['display_phone_number' => '+51 900 111 222', 'verified_name' => 'Nutri Vida', 'quality_rating' => 'GREEN']),
            'graph.facebook.com/*/2233445566/subscribed_apps' => Http::response(['success' => true]),
        ]);

        $this->actingAs($user)->put(route('dashboard.whatsapp.connection.update'), ['waba_id' => '2233445566', 'phone_number_id' => '1098765432'])
            ->assertSessionHasErrors('access_token');
        $this->actingAs($user)->put(route('dashboard.whatsapp.connection.update'), [
            'waba_id' => '2233445566', 'phone_number_id' => '1098765432', 'access_token' => self::TOKEN, 'app_secret' => self::SECRET,
        ])->assertSessionHas('success');

        $connection = WhatsappConnection::sole();
        $this->assertSame(['connected', 'Nutri Vida', 'GREEN'], [$connection->status, $connection->verified_name, $connection->quality_rating]);
        $this->assertNotSame(self::TOKEN, $connection->getRawOriginal('access_token'), 'Token stored encrypted');
        $this->actingAs($user)->get(route('dashboard.whatsapp.connection.edit'))->assertOk()
            ->assertSee($connection->webhookUrl())->assertSee($connection->verify_token)->assertDontSee(self::TOKEN);
        $this->assertArrayNotHasKey('whatsapp_connection', $store->fresh()->toArray());

        // Edit without retyping the secrets keeps them; disconnecting keeps the history and stops webhooks.
        $this->actingAs($user)->put(route('dashboard.whatsapp.connection.update'), ['waba_id' => '2233445566', 'phone_number_id' => '1098765432'])->assertSessionHasNoErrors();
        $this->assertSame(self::TOKEN, $connection->fresh()->access_token);
        $this->actingAs($user)->delete(route('dashboard.whatsapp.connection.destroy'))->assertSessionHas('success');
        $this->assertSame('disconnected', $connection->fresh()->status);
        $this->deliver($connection->fresh(), ['entry' => []])->assertForbidden();
    }

    public function test_meta_rejecting_the_token_leaves_the_connection_in_error(): void
    {
        [$user] = $this->owner();
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['code' => 190, 'message' => 'Error validating access token']], 400)]);

        $this->actingAs($user)->put(route('dashboard.whatsapp.connection.update'), [
            'waba_id' => '2233445566', 'phone_number_id' => '1098765432', 'access_token' => self::TOKEN, 'app_secret' => self::SECRET,
        ])->assertSessionHas('error');

        $this->assertSame('error', WhatsappConnection::sole()->status);
        $this->assertStringStartsWith('El token de acceso no es válido', WhatsappConnection::sole()->last_error);
    }

    public function test_chats_of_another_store_are_not_reachable(): void
    {
        [$user, $store] = $this->owner();
        [$intruder, $other] = $this->owner('otra');
        $connection = $this->connection($store);
        $this->deliver($connection, $this->incoming($connection->phone_number_id, $this->text('Secreto', 'wamid.A1'), $this->profile()));
        $chat = WhatsappConversation::sole();

        $this->actingAs($intruder)->get(route('dashboard.whatsapp.show', $chat->id))->assertNotFound();
        $this->actingAs($intruder)->post(route('dashboard.whatsapp.reply', $chat->id), ['body' => 'x'])->assertNotFound();
        $this->actingAs($intruder)->getJson(route('dashboard.whatsapp.poll', $chat->id))->assertNotFound();
        $this->actingAs($intruder)->get(route('dashboard.whatsapp.index'))->assertOk()->assertDontSee('Secreto');
        $this->actingAs($user)->get(route('dashboard.whatsapp.index'))->assertOk()->assertSee('Secreto')->assertSee('Rosa Quispe');

        // A number already connected elsewhere cannot be claimed.
        $this->actingAs($intruder)->put(route('dashboard.whatsapp.connection.update'), [
            'waba_id' => '1', 'phone_number_id' => '1098765432', 'access_token' => self::TOKEN, 'app_secret' => self::SECRET,
        ])->assertSessionHasErrors(['phone_number_id' => 'Ese número ya está conectado a otra tienda.']);
        $this->assertSame(1, WhatsappConnection::count());
    }

    public function test_the_webhook_answers_fast_and_processes_on_the_queue(): void
    {
        Queue::fake();
        [, $store] = $this->owner();
        $connection = $this->connection($store);

        $this->deliver($connection, $this->incoming($connection->phone_number_id, $this->text('Hola', 'wamid.A1'), $this->profile()))->assertOk();

        Queue::assertPushed(ProcessWhatsappWebhook::class, 1);
        $this->assertSame(0, WhatsappMessage::count());
        $this->assertNull(WhatsappWebhookEvent::sole()->processed_at);
    }
}
