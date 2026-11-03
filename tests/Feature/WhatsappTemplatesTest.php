<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactEvent;
use App\Models\MessageTemplate;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use App\Models\WhatsappConnection;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Models\WhatsappTemplate;
use App\Services\Whatsapp\TemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** ERP Fase 3b: Meta-approved templates (review, status, sending first) and incoming media. */
class WhatsappTemplatesTest extends TestCase
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
        config(['app.url' => 'http://localhost']);
    }

    private function owner(string $slug = 'nutri'): array
    {
        $user = User::factory()->create(['role' => 'store_owner']);
        $store = Store::create(['user_id' => $user->id, 'name' => 'Nutri Vida', 'slug' => $slug, 'status' => 'active', 'template_name' => 'soft-market']);
        $connection = WhatsappConnection::create([
            'store_id' => $store->id, 'waba_id' => '2233445566', 'phone_number_id' => $slug === 'nutri' ? '1098765432' : '5550001111',
            'access_token' => self::TOKEN, 'app_secret' => self::SECRET, 'verify_token' => str_repeat('v', 40),
            'webhook_key' => str_pad($slug, 40, 'k'), 'status' => 'connected', 'connected_at' => now(),
        ]);

        return [$user, $store, $connection];
    }

    private function rosa(Store $store, bool $consent = false): Contact
    {
        Order::create(['store_id' => $store->id, 'order_number' => 'NV-7', 'customer_name' => 'Rosa Quispe', 'customer_phone' => '987111222',
            'customer_country' => 'PE', 'subtotal' => 80, 'total' => 80, 'currency' => 'PEN', 'status' => 'delivered', 'payment_status' => 'paid', 'payment_method' => 'yape']);
        $rosa = Contact::sole();
        $rosa->forceFill(['accepts_marketing' => $consent])->save();

        return $rosa;
    }

    private function approved(WhatsappConnection $connection, string $name, string $body, ?array $params, string $category = 'UTILITY', array $extra = []): WhatsappTemplate
    {
        return WhatsappTemplate::create([
            'store_id' => $connection->store_id, 'connection_id' => $connection->id, 'meta_id' => (string) random_int(1000, 99999), 'name' => $name,
            'language' => 'es', 'category' => $category, 'status' => 'APPROVED', 'body' => $body,
            'components' => array_merge([['type' => 'BODY', 'text' => $body]], $extra), 'params' => $params,
        ]);
    }

    private function signed(WhatsappConnection $connection, array $payload)
    {
        $raw = json_encode($payload);

        return $this->call('POST', route('webhooks.whatsapp.receive', $connection->webhook_key), [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256=' . hash_hmac('sha256', $raw, self::SECRET)], $raw);
    }

    public function test_tribio_templates_become_meta_text_with_numbered_variables(): void
    {
        [, $store] = $this->owner();
        $service = app(TemplateService::class);

        [$text, $params] = $service->convert($store, 'Hola {nombre} 👋 ¿Qué tal te va con {producto}? ¿Te separo otro?');
        $this->assertSame(['Hola {{1}} 👋 ¿Qué tal te va con {{2}}? ¿Te separo otro?', ['nombre', 'producto']], [$text, $params]);

        [$text, $params] = $service->convert($store, 'Hola {nombre}, tenemos novedades en {tienda} 👉 {link_tienda}');
        $this->assertMatchesRegularExpression('#^Hola \{\{1\}\}, tenemos novedades en Nutri Vida 👉 https?://[^ ]+/tienda/nutri$#u', $text, 'Store name and link are written in');
        $this->assertSame(['nombre'], $params);

        $this->expectException(ValidationException::class);
        $service->convert($store, '{nombre}, te espera tu pedido');
    }

    public function test_a_template_is_sent_to_review_with_examples(): void
    {
        [$user, , $connection] = $this->owner();
        Http::fake(['graph.facebook.com/*/2233445566/message_templates' => Http::response(['id' => '555001', 'status' => 'PENDING', 'category' => 'UTILITY'])]);

        $this->actingAs($user)->post(route('dashboard.whatsapp.templates.submit'), ['key' => 'reorder', 'category' => 'UTILITY'])->assertSessionHas('success');

        Http::assertSent(fn (HttpRequest $request) => $request['name'] === 'tribio_reorder' && $request['language'] === 'es' && $request['category'] === 'UTILITY'
            && $request['components'][0]['example']['body_text'] === [['Rosa', 'Batido de fresa']]
            && $request->hasHeader('Authorization', 'Bearer ' . self::TOKEN));
        $template = WhatsappTemplate::sole();
        $this->assertSame(['555001', 'PENDING', ['nombre', 'producto'], 'reorder'], [$template->meta_id, $template->status, $template->params, $template->source_key]);

        $this->actingAs($user)->post(route('dashboard.whatsapp.templates.submit'), ['key' => 'reorder', 'category' => 'UTILITY'])
            ->assertSessionHasErrors(['template' => 'Esta plantilla ya está en revisión en Meta.']);
        Http::assertSentCount(1);
    }

    public function test_meta_tells_the_review_result_by_webhook_and_a_rejected_one_is_corrected_in_place(): void
    {
        [$user, , $connection] = $this->owner();
        $template = WhatsappTemplate::create(['store_id' => $connection->store_id, 'connection_id' => $connection->id, 'meta_id' => '555001', 'name' => 'tribio_follow_up',
            'language' => 'es', 'category' => 'UTILITY', 'status' => 'PENDING', 'body' => 'Hola {{1}}, ¿cómo vas?', 'params' => ['nombre'], 'source_key' => 'follow_up']);
        $update = fn (string $event, string $reason = 'NONE') => ['object' => 'whatsapp_business_account', 'entry' => [['id' => '2233445566', 'changes' => [[
            'field' => 'message_template_status_update',
            'value' => ['event' => $event, 'message_template_id' => 555001, 'message_template_name' => 'tribio_follow_up', 'message_template_language' => 'es', 'reason' => $reason],
        ]]]]];

        $this->signed($connection, $update('REJECTED', 'INCORRECT_CATEGORY'))->assertOk();
        $this->assertSame(['REJECTED', 'La categoría no corresponde: prueba como Marketing.'], [$template->fresh()->status, $template->fresh()->rejected_reason]);

        Http::fake(['graph.facebook.com/*/555001' => Http::response(['success' => true])]);
        $this->actingAs($user)->post(route('dashboard.whatsapp.templates.submit'), ['key' => 'follow_up', 'category' => 'UTILITY'])->assertSessionHas('success');
        Http::assertSent(fn (HttpRequest $request) => str_ends_with($request->url(), '/555001') && isset($request['components']) && !isset($request['name']));
        $this->assertSame(['PENDING', null], [$template->fresh()->status, $template->fresh()->rejected_reason]);

        $this->signed($connection, $update('APPROVED'))->assertOk();
        $this->assertSame('APPROVED', $template->fresh()->status);
    }

    public function test_sync_mirrors_meta_including_templates_made_there(): void
    {
        [$user, , $connection] = $this->owner();
        $gone = $this->approved($connection, 'vieja', 'Hola', null);
        Http::fake(['graph.facebook.com/*/2233445566/message_templates*' => Http::sequence()
            ->push(['data' => [['id' => '1', 'name' => 'aviso_envio', 'language' => 'es', 'status' => 'APPROVED', 'category' => 'UTILITY',
                'components' => [['type' => 'BODY', 'text' => 'Tu pedido {{1}} salió hoy.', 'example' => ['body_text' => [['#1']]]]]]],
                'paging' => ['cursors' => ['after' => 'abc'], 'next' => 'https://graph.facebook.com/next']])
            ->push(['data' => [['id' => '2', 'name' => 'promo_foto', 'language' => 'es', 'status' => 'APPROVED', 'category' => 'MARKETING',
                'components' => [['type' => 'HEADER', 'format' => 'IMAGE'], ['type' => 'BODY', 'text' => 'Mira la oferta']]]],
                'paging' => ['cursors' => ['after' => 'def']]])]);

        $this->actingAs($user)->post(route('dashboard.whatsapp.templates.sync'))->assertSessionHas('success', 'Meta tiene 2 plantillas en tu cuenta.');

        $this->assertNull(WhatsappTemplate::find($gone->id), 'Deleted in Meta, deleted here');
        $this->assertTrue(WhatsappTemplate::where('name', 'aviso_envio')->sole()->isSendable());
        $this->assertFalse(WhatsappTemplate::where('name', 'promo_foto')->sole()->isSendable(), 'Image headers are not supported yet');
        $this->actingAs($user)->get(route('dashboard.whatsapp.templates.index'))->assertOk()
            ->assertSee('aviso_envio')->assertSee('Tiene un encabezado con imagen')->assertSee('Recompra');
    }

    public function test_writing_first_to_a_customer_with_a_template_fills_their_data(): void
    {
        [$user, $store, $connection] = $this->owner();
        $rosa = $this->rosa($store);
        $template = $this->approved($connection, 'tribio_thanks', '¡Hola {{1}}! Gracias por tu compra en Nutri Vida 🙌 Tu pedido {{2}} va en camino.', ['nombre', 'pedido']);
        Http::fake(['graph.facebook.com/*/1098765432/messages' => Http::response(['messages' => [['id' => 'wamid.T1']]])]);

        $this->actingAs($user)->post(route('dashboard.whatsapp.start', $rosa->id))->assertRedirect();
        $chat = WhatsappConversation::sole();
        $this->assertSame([$rosa->id, '51987111222', false], [$chat->contact_id, $chat->wa_id, $chat->windowOpen()]);

        $page = $this->actingAs($user)->get(route('dashboard.whatsapp.show', $chat->id))->assertOk()->assertSee('aún no te escribió por este número');
        $prefilled = collect($page->viewData('templates')[0]['variables'])->pluck('value', 'key')->all();
        $this->assertSame(['1' => 'Rosa', '2' => '#NV-7'], $prefilled);

        $this->actingAs($user)->post(route('dashboard.whatsapp.template', $chat->id), ['template_id' => $template->id, 'values' => $prefilled])
            ->assertSessionHasNoErrors()->assertRedirect(route('dashboard.whatsapp.show', $chat->id));

        Http::assertSent(fn (HttpRequest $request) => $request['type'] === 'template' && $request['to'] === '51987111222'
            && $request['template']['name'] === 'tribio_thanks' && $request['template']['language'] === ['code' => 'es']
            && $request['template']['components'] === [['type' => 'body', 'parameters' => [['type' => 'text', 'text' => 'Rosa'], ['type' => 'text', 'text' => '#NV-7']]]]);
        $message = WhatsappMessage::sole();
        $this->assertSame(['template', 'sent', '¡Hola Rosa! Gracias por tu compra en Nutri Vida 🙌 Tu pedido #NV-7 va en camino.'], [$message->type, $message->status, $message->body]);
        $this->assertStringStartsWith('Plantilla «tribio_thanks» por WhatsApp', ContactEvent::where('type', 'whatsapp')->sole()->body);
    }

    public function test_marketing_templates_need_consent_and_every_variable(): void
    {
        [$user, $store, $connection] = $this->owner();
        $rosa = $this->rosa($store);
        $promo = $this->approved($connection, 'tribio_news', 'Hola {{1}}, tenemos novedades en Nutri Vida 👉 http://localhost/tienda/nutri', ['nombre'], 'MARKETING');
        $utility = $this->approved($connection, 'tribio_follow_up', 'Hola {{1}}, ¿cómo vas?', ['nombre']);
        $chat = WhatsappConversation::create(['store_id' => $store->id, 'connection_id' => $connection->id, 'contact_id' => $rosa->id, 'wa_id' => '51987111222']);

        $this->actingAs($user)->post(route('dashboard.whatsapp.template', $chat->id), ['template_id' => $promo->id, 'values' => ['1' => 'Rosa']])
            ->assertSessionHasErrors(['template_id' => 'Rosa Quispe no aceptó recibir ofertas (Ley 29733): solo puedes enviarle plantillas de utilidad.']);
        $this->actingAs($user)->post(route('dashboard.whatsapp.template', $chat->id), ['template_id' => $utility->id, 'values' => ['1' => "  \n "]])
            ->assertSessionHasErrors(['values' => 'Completa todos los datos de la plantilla.']);
        $this->assertSame(0, WhatsappMessage::count());

        $blocked = collect($this->actingAs($user)->get(route('dashboard.whatsapp.show', $chat->id))->viewData('templates'))->firstWhere('name', 'tribio_news');
        $this->assertSame('No aceptó recibir ofertas (Ley 29733).', $blocked['blocked']);

        $rosa->forceFill(['accepts_marketing' => true])->save();
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.P1']]])]);
        $this->actingAs($user)->post(route('dashboard.whatsapp.template', $chat->id), ['template_id' => $promo->id, 'values' => ['1' => 'Rosa']])->assertSessionHasNoErrors();
        $this->assertSame('sent', WhatsappMessage::sole()->status);
    }

    public function test_named_variables_of_templates_made_in_meta_are_sent_by_name(): void
    {
        [$user, $store, $connection] = $this->owner();
        $rosa = $this->rosa($store);
        $template = $this->approved($connection, 'cita', 'Hola {{nombre}}, te esperamos el {{fecha}}.', null);
        $chat = WhatsappConversation::create(['store_id' => $store->id, 'connection_id' => $connection->id, 'contact_id' => $rosa->id, 'wa_id' => '51987111222']);
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.N1']]])]);

        $variables = app(TemplateService::class)->variables($template, ['nombre' => 'Rosa']);
        $this->assertSame([['key' => 'nombre', 'label' => 'Primer nombre del cliente', 'value' => 'Rosa'], ['key' => 'fecha', 'label' => 'Dato {{fecha}}', 'value' => '']], $variables);

        $this->actingAs($user)->post(route('dashboard.whatsapp.template', $chat->id), ['template_id' => $template->id, 'values' => ['nombre' => 'Rosa', 'fecha' => 'sábado 8']]);

        Http::assertSent(fn (HttpRequest $request) => $request['template']['components'][0]['parameters'] === [
            ['type' => 'text', 'text' => 'Rosa', 'parameter_name' => 'nombre'], ['type' => 'text', 'text' => 'sábado 8', 'parameter_name' => 'fecha'],
        ]);
    }

    public function test_a_template_of_another_store_cannot_be_used(): void
    {
        [$user, $store, $connection] = $this->owner();
        [, , $otherConnection] = $this->owner('otra');
        $foreign = $this->approved($otherConnection, 'ajena', 'Hola {{1}}.', ['nombre']);
        $chat = WhatsappConversation::create(['store_id' => $store->id, 'connection_id' => $connection->id, 'wa_id' => '51987111222']);

        $this->actingAs($user)->post(route('dashboard.whatsapp.template', $chat->id), ['template_id' => $foreign->id, 'values' => ['1' => 'x']])
            ->assertSessionHasErrors(['template_id' => 'Elige una plantilla aprobada.']);
        $this->actingAs($user)->delete(route('dashboard.whatsapp.templates.destroy', $foreign->id))->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_a_customer_photo_is_downloaded_and_served_only_to_the_store(): void
    {
        Storage::fake('local');
        [$user, $store, $connection] = $this->owner();
        [$intruder] = $this->owner('otra');
        Http::fake([
            'graph.facebook.com/*/9988' => Http::response(['url' => 'https://lookaside.fbsbx.com/whatsapp_business/attachments/?mid=9988', 'mime_type' => 'image/jpeg', 'file_size' => 4]),
            'lookaside.fbsbx.com/*' => Http::response('JPEG', 200, ['Content-Type' => 'image/jpeg']),
        ]);
        $photo = ['from' => '51987111222', 'from_user_id' => 'PE.1', 'id' => 'wamid.M1', 'timestamp' => (string) now()->timestamp,
            'type' => 'image', 'image' => ['id' => '9988', 'mime_type' => 'image/jpeg', 'caption' => 'Este quiero']];

        $this->signed($connection, ['object' => 'whatsapp_business_account', 'entry' => [['id' => '2233445566', 'changes' => [['field' => 'messages', 'value' => [
            'metadata' => ['phone_number_id' => '1098765432'], 'contacts' => [['profile' => ['name' => 'Rosa'], 'wa_id' => '51987111222', 'user_id' => 'PE.1']], 'messages' => [$photo],
        ]]]]]])->assertOk();

        $message = WhatsappMessage::sole();
        $this->assertSame(['image/jpeg', 4], [$message->payload['mime_type'], $message->payload['size']]);
        Storage::disk('local')->assertExists($message->payload['path']);
        Http::assertSent(fn (HttpRequest $request) => str_starts_with($request->url(), 'https://lookaside.fbsbx.com/') && $request->hasHeader('Authorization', 'Bearer ' . self::TOKEN));

        $this->actingAs($user)->get(route('dashboard.whatsapp.media', $message->id))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->actingAs($user)->get(route('dashboard.whatsapp.show', $message->conversation_id))->assertSee(route('dashboard.whatsapp.media', $message->id));
        $this->actingAs($intruder)->get(route('dashboard.whatsapp.media', $message->id))->assertNotFound();
    }

    public function test_a_file_too_big_is_not_downloaded(): void
    {
        Storage::fake('local');
        [$user, , $connection] = $this->owner();
        Http::fake(['graph.facebook.com/*/7777' => Http::response(['url' => 'https://lookaside.fbsbx.com/x', 'mime_type' => 'video/mp4', 'file_size' => 50 * 1024 * 1024])]);
        $video = ['from' => '51987111222', 'id' => 'wamid.V1', 'timestamp' => (string) now()->timestamp, 'type' => 'video', 'video' => ['id' => '7777', 'mime_type' => 'video/mp4']];

        $this->signed($connection, ['object' => 'whatsapp_business_account', 'entry' => [['id' => '2233445566', 'changes' => [['field' => 'messages', 'value' => [
            'metadata' => ['phone_number_id' => '1098765432'], 'contacts' => [['profile' => ['name' => 'Rosa'], 'wa_id' => '51987111222']], 'messages' => [$video],
        ]]]]]]);

        $message = WhatsappMessage::sole();
        $this->assertTrue($message->payload['too_large']);
        $this->assertArrayNotHasKey('path', $message->payload);
        Http::assertNotSent(fn (HttpRequest $request) => str_starts_with($request->url(), 'https://lookaside.fbsbx.com/'));
        $this->actingAs($user)->get(route('dashboard.whatsapp.show', $message->conversation_id))->assertSee('Archivo muy grande');
    }

    public function test_an_edited_own_template_starting_with_a_variable_cannot_be_submitted(): void
    {
        [$user, $store] = $this->owner();
        $own = MessageTemplate::create(['store_id' => $store->id, 'key' => null, 'name' => 'Directo', 'body' => '{nombre}, tu pedido está listo', 'is_promo' => false]);

        $this->actingAs($user)->get(route('dashboard.whatsapp.templates.index'))->assertOk()->assertSee('Meta no acepta que el mensaje empiece o termine con un dato del cliente');
        $this->actingAs($user)->post(route('dashboard.whatsapp.templates.submit'), ['key' => 'propia-' . $own->id, 'category' => 'UTILITY'])
            ->assertSessionHasErrors('template');
        Http::assertNothingSent();
    }
}
