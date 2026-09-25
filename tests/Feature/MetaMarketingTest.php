<?php

namespace Tests\Feature;

use App\Models\MarketingEventLog;
use App\Models\Order;
use App\Models\OrderAttribution;
use App\Models\PendingCheckout;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\StoreMarketingIntegration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Dashboard → Marketing, stage 1 (manual Meta connection): catalog feed, storefront
 * Pixel config + cookie consent, Conversions API "Purchase", attribution, dashboard.
 */
class MetaMarketingTest extends TestCase
{
    use RefreshDatabase;

    private const PIXEL = '123456789012345';
    private const TOKEN = 'EAABsbCS1iHgBOZCtestTOKENvalue1234567890abcdef';

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
        // A later Http::fake() would not override this one (first match wins): tests set $metaResponse.
        Http::fake(['graph.facebook.com/*' => fn () => $this->metaResponse ?? Http::response(['events_received' => 1, 'fbtrace_id' => 'AbC123'])]);
    }

    private $metaResponse = null;

    /** "http://localhost:8000/tienda/x" → "/tienda/x" (the host follows the test environment). */
    private function path(string $url): string
    {
        return (string) preg_replace('#^https?://[^/]+#', '', $url);
    }

    // ─── Fixtures ────────────────────────────────────────────────

    private function store(array $attributes = []): Store
    {
        return Store::create($attributes + [
            'user_id' => User::factory()->create(['role' => 'store_owner'])->id,
            'name' => 'Audio Lima', 'slug' => 'audio-lima', 'status' => 'active', 'template_name' => 'soft-market',
            'checkout_mode' => 'mixed', 'whatsapp_phone' => '51 999 888 777', 'national_shipping_cost' => 10,
        ]);
    }

    private function product(Store $store, array $attributes = []): Product
    {
        return Product::create($attributes + [
            'store_id' => $store->id, 'name' => 'Audífonos Pro', 'slug' => 'audifonos-pro', 'description' => '<p>Sonido <b>limpio</b></p>',
            'price' => 50, 'stock' => 10, 'track_stock' => true, 'is_active' => true, 'image_path' => 'stores/1/products/audifonos.jpg',
        ]);
    }

    private function connect(Store $store, array $attributes = []): StoreMarketingIntegration
    {
        return StoreMarketingIntegration::create($attributes + [
            'store_id' => $store->id, 'provider' => 'meta', 'is_active' => true,
            'pixel_id' => self::PIXEL, 'capi_token' => self::TOKEN, 'default_condition' => 'new',
        ]);
    }

    private function checkout(Store $store, Product $product, array $cookies = [], array $extra = [])
    {
        // withCredentials(): JSON test requests drop cookies otherwise (a same-origin fetch sends them).
        return $this->withCredentials()->withUnencryptedCookies($cookies)
            ->withHeader('Referer', "http://localhost/tienda/{$store->slug}/producto/{$product->slug}")
            ->postJson(route('store.checkout', $store->slug), $extra + [
                'customer_name' => 'Ana María Pérez', 'customer_phone' => '987 654 321', 'customer_email' => ' Ana@Example.test ',
                'customer_country' => 'PE', 'customer_city' => 'San Juan de Lurigancho', 'customer_address' => 'Av. Siempre Viva 123',
                'customer_document_number' => '44556677',
                'items' => [['id' => $product->id, 'quantity' => 2]],
            ]);
    }

    private function consentCookies(): array
    {
        return [
            'tribio_consent' => 'granted',
            '_fbp' => 'fb.1.1712345678901.1098765432',
            'tribio_attr' => json_encode(['utm_source' => 'facebook', 'utm_campaign' => 'audifonos-oct', 'fbclid' => 'IwAR0abc', 'landing_path' => '/tienda/audio-lima', 'ts' => 1712345678]),
        ];
    }

    /** @return list<array{request: HttpRequest, body: array}> */
    private function metaCalls(): array
    {
        return collect(Http::recorded())
            ->filter(fn ($pair) => str_contains($pair[0]->url(), 'graph.facebook.com'))
            ->map(fn ($pair) => ['request' => $pair[0], 'body' => $pair[0]->data()])
            ->values()->all();
    }

    // ─── Catalog feed ────────────────────────────────────────────

    public function test_feed_is_not_published_until_meta_is_connected_and_active(): void
    {
        $store = $this->store();
        $this->product($store);

        $this->get('/tienda/audio-lima/feed/facebook.xml')->assertNotFound();

        $integration = $this->connect($store, ['is_active' => false]);
        $this->get('/tienda/audio-lima/feed/facebook.xml')->assertNotFound();

        $integration->update(['is_active' => true]);
        $this->get('/tienda/audio-lima/feed/facebook.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function test_feed_maps_prices_variants_images_and_skips_what_cannot_be_advertised(): void
    {
        $store = $this->store();
        $this->connect($store, ['default_condition' => 'refurbished']);
        $sale = $this->product($store, ['name' => 'Parlante "Boom" & Co <mini>', 'slug' => 'parlante', 'price' => 80, 'compare_price' => 100,
            'gallery_images' => ['stores/1/products/g1.webp', 'stores/1/products/g2.png'], 'is_featured' => true]);
        $shirt = $this->product($store, ['name' => 'Polo', 'slug' => 'polo', 'price' => 40, 'has_variants' => true, 'condition' => 'new']);
        $red = ProductVariant::create(['product_id' => $shirt->id, 'price' => 45, 'stock' => 3, 'attributes' => ['Color' => 'Rojo', 'Talla' => 'M'], 'is_active' => true]);
        $blue = ProductVariant::create(['product_id' => $shirt->id, 'price' => 0, 'stock' => 0, 'attributes' => ['Color' => 'Azul'], 'is_active' => true]);
        ProductVariant::create(['product_id' => $shirt->id, 'price' => 45, 'stock' => 5, 'attributes' => ['Color' => 'Verde'], 'is_active' => false]);
        $this->product($store, ['slug' => 'apagado', 'is_active' => false]);
        $this->product($store, ['slug' => 'sin-foto', 'image_path' => null]);
        $this->product($store, ['slug' => 'gratis', 'price' => 0]);
        $this->product($store, ['slug' => 'excluido', 'exclude_from_ads' => true]);
        $this->product($this->store(['slug' => 'otra-tienda', 'name' => 'Otra']), ['slug' => 'ajeno']);

        $xml = simplexml_load_string($this->get('/tienda/audio-lima/feed/facebook.xml')->assertOk()->getContent());
        $this->assertNotFalse($xml, 'The feed must be valid XML even with quotes, & and < in names');
        // iterator_to_array(…, false): every <item> shares the key "item" otherwise.
        $items = collect(iterator_to_array($xml->channel->item, false))->map(fn ($item) => $item->children('http://base.google.com/ns/1.0'))
            ->keyBy(fn ($g) => (string) $g->id);

        $this->assertEqualsCanonicalizing([(string) $sale->id, "{$shirt->id}-{$red->id}", "{$shirt->id}-{$blue->id}"], $items->keys()->all());

        $s = $items[(string) $sale->id];
        $this->assertSame('Parlante "Boom" & Co <mini>', (string) $s->title);
        $this->assertSame(['100.00 PEN', '80.00 PEN'], [(string) $s->price, (string) $s->sale_price]);
        $this->assertSame('/tienda/audio-lima/producto/parlante', $this->path((string) $s->link));
        $this->assertSame('/storage/stores/1/products/audifonos.jpg', $this->path((string) $s->image_link));
        $this->assertSame([
            '/feed-img/stores/1/products/g1.webp.jpg', // WebP → JPEG copy (Meta rejects WebP)
            '/storage/stores/1/products/g2.png',
        ], array_map(fn ($url) => $this->path((string) $url), iterator_to_array($s->additional_image_link, false)));
        $this->assertSame(['refurbished', 'in stock', 'Audio Lima', 'destacado'], [(string) $s->condition, (string) $s->availability, (string) $s->brand, (string) $s->custom_label_0]);
        $this->assertSame('Sonido limpio', (string) $items[(string) $sale->id]->description);

        $r = $items["{$shirt->id}-{$red->id}"];
        $this->assertSame([(string) $shirt->id, '45.00 PEN', 'in stock', 'Rojo', 'M', 'new'], [(string) $r->item_group_id, (string) $r->price, (string) $r->availability, (string) $r->color, (string) $r->size, (string) $r->condition]);
        $b = $items["{$shirt->id}-{$blue->id}"];
        $this->assertSame(['40.00 PEN', 'out of stock'], [(string) $b->price, (string) $b->availability], 'A variant without its own price uses the product price');
    }

    public function test_feed_on_a_custom_domain_links_to_that_domain(): void
    {
        $store = $this->store(['custom_domain' => 'audiolima.test']);
        $this->connect($store);
        $this->product($store);

        $content = $this->get('http://audiolima.test/feed/facebook.xml')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<g:link>http://audiolima\.test(:\d+)?/producto/audifonos-pro</g:link>#', $content);
    }

    public function test_webp_photos_are_served_to_meta_as_jpeg(): void
    {
        Storage::fake('public');
        $image = imagecreatetruecolor(600, 600);
        ob_start();
        imagewebp($image);
        Storage::disk('public')->put('stores/1/products/foto.webp', ob_get_clean());

        $response = $this->get('/feed-img/stores/1/products/foto.webp.jpg')->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->assertSame("\xFF\xD8", substr(file_get_contents($response->getFile()->getPathname()), 0, 2));
        $this->assertCount(1, Storage::disk('public')->files('feed-cache'));

        $this->get('/feed-img/stores/1/products/no-existe.webp.jpg')->assertNotFound();
        $this->get('/feed-img/../../.env.webp.jpg')->assertNotFound();
    }

    // ─── Storefront Pixel ────────────────────────────────────────

    public function test_storefront_prints_nothing_for_stores_without_meta(): void
    {
        $store = $this->store();
        $this->product($store);

        $this->get('/tienda/audio-lima/producto/audifonos-pro')->assertOk()->assertDontSee('__tribioMarketing', false)->assertDontSee('facebook-domain-verification', false);
        $this->connect($store, ['is_active' => false, 'domain_verification' => 'abc123abc123']);
        $this->get('/tienda/audio-lima/producto/audifonos-pro')->assertOk()->assertDontSee('__tribioMarketing', false)->assertDontSee('facebook-domain-verification', false);
    }

    public function test_storefront_gets_the_pixel_config_and_view_content_only_on_product_pages(): void
    {
        $store = $this->store();
        $this->connect($store, ['domain_verification' => 'abc123abc123']);
        $product = $this->product($store, ['has_variants' => true]);

        $page = $this->get('/tienda/audio-lima/producto/audifonos-pro')->assertOk()->getContent();
        $config = $this->marketingConfig($page);
        $this->assertSame(self::PIXEL, $config['meta']['pixelId']);
        $this->assertNull($config['google'], 'No Google tag for a Meta-only store');
        $this->assertSame('/tienda/audio-lima', $config['cookiePath']);
        $this->assertStringEndsWith('/privacidad#cookies', $config['privacyUrl']);
        $this->assertSame([(string) $product->id], $config['viewContent']['content_ids']);
        $this->assertSame('product_group', $config['viewContent']['content_type']);
        $this->assertStringContainsString('<meta name="facebook-domain-verification" content="abc123abc123">', $page);

        // The catalog loops over products: a $product leaking into the layout must not fire ViewContent.
        $this->assertNull($this->marketingConfig($this->get('/tienda/audio-lima/catalogo')->assertOk()->getContent())['viewContent']);
    }

    public function test_every_storefront_template_includes_the_marketing_head(): void
    {
        foreach (['soft-market', 'minimal-light', 'elegant-refurbished', 'industrial-light', 'elegant-dark'] as $i => $template) {
            $store = $this->store(['slug' => "tienda-{$i}", 'template_name' => $template]);
            $this->connect($store);
            $this->product($store, ['slug' => "p-{$i}"]);

            $this->get("/tienda/tienda-{$i}")->assertOk()->assertSee('__tribioMarketing', false);
            if ($template !== 'elegant-dark') { // its product page extends the platform layout
                $this->get("/tienda/tienda-{$i}/producto/p-{$i}")->assertOk()->assertSee('__tribioMarketing', false);
            }
        }
    }

    private function marketingConfig(string $html): array
    {
        $this->assertSame(1, preg_match('/window\.__tribioMarketing = (\{.*?\});<\/script>/', $html, $m));

        return json_decode($m[1], true);
    }

    // ─── Conversions API Purchase ────────────────────────────────

    public function test_a_whatsapp_order_is_reported_once_when_the_owner_records_its_payment(): void
    {
        $store = $this->store();
        $this->connect($store);
        $product = $this->product($store);

        $number = $this->checkout($store, $product, $this->consentCookies())->assertOk()->json('order_number');
        $order = Order::where('order_number', $number)->sole();
        $this->assertSame('pending', $order->payment_status);
        $this->assertSame([], $this->metaCalls(), 'An unpaid WhatsApp order is not a purchase yet');

        $tracking = PendingCheckout::where('order_id', $order->id)->sole()->payload['tracking'];
        $this->assertSame('granted', $tracking['consent']);
        $this->assertSame('fb.1.1712345678901.1098765432', $tracking['fbp']);
        $this->assertSame('fb.1.1712345678000.IwAR0abc', $tracking['fbc'], 'Built from the ad click when the Pixel had not set _fbc');

        $attribution = OrderAttribution::where('order_id', $order->id)->sole();
        $this->assertSame(['meta', 'facebook', 'audifonos-oct', 'IwAR0abc'], [$attribution->channel, $attribution->utm_source, $attribution->utm_campaign, $attribution->fbclid]);

        $this->actingAs($store->user)->post(route('dashboard.pedidos.payments.store', $order), ['amount' => $order->total, 'method' => 'yape'])->assertRedirect();
        $this->assertSame('paid', $order->fresh()->payment_status);

        $calls = $this->metaCalls();
        $this->assertCount(1, $calls);
        $this->assertSame('https://graph.facebook.com/v26.0/' . self::PIXEL . '/events', $calls[0]['request']->url(), 'The token travels in the body, never in the URL');
        $this->assertSame(self::TOKEN, $calls[0]['body']['access_token']);
        $event = $calls[0]['body']['data'][0];
        $this->assertSame(['Purchase', "purchase_{$number}", 'website'], [$event['event_name'], $event['event_id'], $event['action_source']]);
        $this->assertEquals((float) $order->total, $event['custom_data']['value']);
        $this->assertSame(['PEN', [(string) $product->id], 2], [$event['custom_data']['currency'], $event['custom_data']['content_ids'], $event['custom_data']['num_items']]);
        $this->assertSame(hash('sha256', 'ana@example.test'), $event['user_data']['em']);
        $this->assertSame(hash('sha256', '51987654321'), $event['user_data']['ph']);
        $this->assertSame(hash('sha256', 'ana'), $event['user_data']['fn']);
        $this->assertSame(hash('sha256', 'maríapérez'), $event['user_data']['ln']);
        $this->assertSame(hash('sha256', 'sanjuandelurigancho'), $event['user_data']['ct']);
        $this->assertSame('fb.1.1712345678901.1098765432', $event['user_data']['fbp']);
        $this->assertStringNotContainsString('44556677', json_encode($calls[0]['body']), 'The DNI is never sent');
        $this->assertStringNotContainsString('Siempre Viva', json_encode($calls[0]['body']), 'The address is never sent');

        $log = MarketingEventLog::sole();
        $this->assertSame(['sent', 'Purchase', $order->id], [$log->status, $log->event_name, $log->order_id]);
        $this->assertNotNull($store->metaIntegration()->first()->last_event_at);

        // Later saves of the same order never report it again.
        $order->fresh()->update(['status' => 'shipped']);
        $order->fresh()->update(['payment_status' => 'refunded']);
        $order->fresh()->update(['payment_status' => 'paid']);
        $this->assertCount(1, $this->metaCalls());
    }

    public function test_a_card_payment_approved_at_checkout_is_reported_right_away(): void
    {
        $store = $this->store(['payment_gateway' => 'mercado_pago', 'checkout_mode' => 'card', 'mp_access_token' => 'TEST-token']);
        $this->connect($store);
        $product = $this->product($store);
        $mock = new \GuzzleHttp\Handler\MockHandler([new \GuzzleHttp\Psr7\Response(201, [], json_encode(['id' => 555, 'status' => 'approved']))]);
        $this->app->bind(\GuzzleHttp\Client::class, fn () => new \GuzzleHttp\Client(['handler' => \GuzzleHttp\HandlerStack::create($mock)]));

        $this->checkout($store, $product, $this->consentCookies(), [
            'payment_method' => 'mercadopago', 'mp_form_data' => ['token' => 'card-token', 'installments' => 1, 'payment_method_id' => 'visa'],
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertSame('paid', Order::sole()->payment_status);
        $this->assertCount(1, $this->metaCalls());
        $this->assertSame('sent', MarketingEventLog::sole()->status);
    }

    public function test_buyers_who_decline_or_ignore_the_cookie_notice_are_not_reported(): void
    {
        $store = $this->store();
        $this->connect($store);
        $product = $this->product($store);

        foreach ([['tribio_consent' => 'denied'] + $this->consentCookies(), []] as $cookies) {
            $number = $this->checkout($store, $product, $cookies)->assertOk()->json('order_number');
            $order = Order::where('order_number', $number)->sole();
            $tracking = PendingCheckout::where('order_id', $order->id)->sole()->payload['tracking'];
            $this->assertArrayNotHasKey('fbp', $tracking);
            $this->assertArrayNotHasKey('client_ip_address', $tracking);
            $order->recordPayment((float) $order->total, 'full', 'manual');
        }

        $this->assertSame([], $this->metaCalls());
        $this->assertSame(['skipped', 'skipped'], MarketingEventLog::orderBy('id')->pluck('status')->all());
        $this->assertNull(OrderAttribution::first()->fbclid, 'Without consent the ad click id is not kept');
    }

    public function test_stores_without_a_conversions_api_token_send_nothing_from_the_server(): void
    {
        $store = $this->store();
        $this->connect($store, ['capi_token' => null]);
        $product = $this->product($store);

        $number = $this->checkout($store, $product, $this->consentCookies())->json('order_number');
        Order::where('order_number', $number)->sole()->recordPayment(110, 'full', 'manual');

        $this->assertSame([], $this->metaCalls());
        $this->assertSame(0, MarketingEventLog::count());
    }

    public function test_a_made_to_order_deposit_reports_the_whole_order_once(): void
    {
        $store = $this->store();
        $this->connect($store);
        $order = Order::create([
            'store_id' => $store->id, 'order_number' => 'TRB-2026-000777', 'customer_name' => 'Luis', 'customer_email' => 'luis@example.test',
            'subtotal' => 300, 'total' => 300, 'currency' => 'PEN', 'status' => 'pending', 'payment_status' => 'pending',
        ]);
        PendingCheckout::create(['store_id' => $store->id, 'reference' => $order->order_number, 'gateway' => 'whatsapp',
            'order_id' => $order->id, 'payload' => ['items' => [], 'tracking' => ['consent' => 'granted']]]);

        $order->recordPayment(150, 'deposit', 'manual');
        $this->assertSame('partial', $order->fresh()->payment_status);
        $order->fresh()->recordPayment(150, 'balance', 'manual');
        $this->assertSame('paid', $order->fresh()->payment_status);

        $calls = $this->metaCalls();
        $this->assertCount(1, $calls, 'Paying the balance is not a second purchase');
        $this->assertEquals(300, $calls[0]['body']['data'][0]['custom_data']['value'], 'The purchase is the whole order, not the deposit');
    }

    public function test_a_rejected_token_is_recorded_without_breaking_the_payment_or_leaking_the_token(): void
    {
        $this->metaResponse = Http::response(['error' => ['message' => 'Invalid OAuth access token ' . self::TOKEN, 'code' => 190]], 400);
        $store = $this->store();
        $this->connect($store);
        $product = $this->product($store);

        $number = $this->checkout($store, $product, $this->consentCookies())->json('order_number');
        $order = Order::where('order_number', $number)->sole();
        $this->actingAs($store->user)->post(route('dashboard.pedidos.payments.store', $order), ['amount' => $order->total, 'method' => 'yape'])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame('paid', $order->fresh()->payment_status);
        $log = MarketingEventLog::sole();
        $this->assertSame('failed', $log->status, 'A 4xx is permanent: no retries');
        $this->assertStringNotContainsString(self::TOKEN, $log->error);
        $this->assertStringContainsString('Invalid OAuth access token ***', $store->metaIntegration()->first()->last_error);
    }

    public function test_order_status_hands_the_browser_its_purchase_only_right_after_paying(): void
    {
        $store = $this->store();
        $this->connect($store, ['capi_token' => null]);
        $product = $this->product($store);
        $order = Order::create([
            'store_id' => $store->id, 'order_number' => 'TRB-2026-000900', 'customer_name' => 'Ana', 'customer_email' => 'ana@example.test',
            'subtotal' => 100, 'total' => 110, 'currency' => 'PEN', 'status' => 'confirmed', 'payment_status' => 'paid',
        ]);
        $order->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'price' => 50, 'quantity' => 2, 'subtotal' => 100]);

        $tracking = $this->getJson('/api/pedido-estado/TRB-2026-000900')->assertOk()->json('tracking');
        $this->assertSame('purchase_TRB-2026-000900', $tracking['event_id']);
        $this->assertEquals(110, $tracking['value']);
        $this->assertSame([(string) $product->id], $tracking['content_ids']);
        $this->assertArrayNotHasKey('em', $tracking);

        $order->forceFill(['created_at' => now()->subHours(3)])->save();
        $this->assertNull($this->getJson('/api/pedido-estado/TRB-2026-000900')->json('tracking'), 'Old orders: sequential numbers must not expose sales');

        $order->forceFill(['created_at' => now(), 'payment_status' => 'pending'])->save();
        $this->assertNull($this->getJson('/api/pedido-estado/TRB-2026-000900')->json('tracking'));
    }

    // ─── Dashboard ───────────────────────────────────────────────

    public function test_owner_connects_meta_by_pasting_what_meta_shows_them(): void
    {
        $store = $this->store(['custom_domain' => 'audiolima.test']);
        $this->product($store);
        $owner = $store->user;

        $this->actingAs($owner)->get(route('dashboard.marketing.index'))->assertOk()
            ->assertSee('Marketing')->assertSee('Conectar con Meta');
        $this->actingAs($owner)->get(route('dashboard.marketing.meta.edit'))->assertOk()
            ->assertSeeInOrder(['audiolima.test', '/feed/facebook.xml'], false)->assertSee('Verificar tu dominio');

        $this->actingAs($owner)->put(route('dashboard.marketing.meta.update'), [
            'is_active' => '1',
            'pixel_id' => "<script>fbq('init', '" . self::PIXEL . "'); fbq('track', 'PageView');</script>",
            'capi_token' => self::TOKEN,
            'test_event_code' => 'test123',
            'domain_verification' => '<meta name="facebook-domain-verification" content="k9xq2l7wz0abcd" />',
            'default_condition' => 'new',
        ])->assertRedirect(route('dashboard.marketing.meta.edit'))->assertSessionHas('success');

        $integration = $store->metaIntegration()->first();
        $this->assertSame([self::PIXEL, 'TEST123', 'k9xq2l7wz0abcd', self::TOKEN], [$integration->pixel_id, $integration->test_event_code, $integration->domain_verification, $integration->capi_token]);
        $this->assertNotSame(self::TOKEN, DB::table('store_marketing_integrations')->value('capi_token'), 'The token is stored encrypted');
        $this->assertStringNotContainsString(self::TOKEN, json_encode($store->fresh()->load('metaIntegration')->toArray()), 'Never serialized');

        $page = $this->actingAs($owner)->get(route('dashboard.marketing.meta.edit'))->assertOk()->getContent();
        $this->assertStringNotContainsString(self::TOKEN, $page, 'The saved token is never sent back to the browser');
        $this->assertStringContainsString('EAAB…cdef', $page);

        // An empty token field keeps the saved token; the remove box clears it.
        $base = ['is_active' => '1', 'pixel_id' => self::PIXEL, 'default_condition' => 'used'];
        $this->actingAs($owner)->put(route('dashboard.marketing.meta.update'), $base)->assertRedirect();
        $this->assertSame([self::TOKEN, 'used'], [$integration->fresh()->capi_token, $integration->fresh()->default_condition]);
        $this->actingAs($owner)->put(route('dashboard.marketing.meta.update'), $base + ['remove_capi_token' => '1'])->assertRedirect();
        $this->assertNull($integration->fresh()->capi_token);

        $this->actingAs($owner)->put(route('dashboard.marketing.meta.update'), ['pixel_id' => 'mi pixel', 'default_condition' => 'new'])
            ->assertSessionHasErrors('pixel_id');
    }

    public function test_owner_can_send_a_test_event_to_meta(): void
    {
        $store = $this->store();
        $this->connect($store);
        $owner = $store->user;

        $this->actingAs($owner)->post(route('dashboard.marketing.meta.test-event'))->assertSessionHas('error');
        $this->assertSame([], $this->metaCalls());

        $store->metaIntegration()->first()->update(['test_event_code' => 'TEST999']);
        $this->actingAs($owner)->post(route('dashboard.marketing.meta.test-event'))->assertSessionHas('success');

        $calls = $this->metaCalls();
        $this->assertCount(1, $calls);
        $this->assertSame('TEST999', $calls[0]['body']['test_event_code']);
        $this->assertTrue(MarketingEventLog::sole()->is_test);
        $this->actingAs($owner)->get(route('dashboard.marketing.meta.edit'))->assertOk()->assertSee('Evento de prueba');
    }

    public function test_marketing_index_shows_catalog_status_and_sales_from_meta(): void
    {
        $store = $this->store();
        $this->connect($store);
        $this->product($store);
        $this->product($store, ['slug' => 'sin-foto', 'image_path' => null]);
        $order = Order::create(['store_id' => $store->id, 'order_number' => 'TRB-2026-000321', 'customer_name' => 'Ana',
            'subtotal' => 120, 'total' => 120, 'currency' => 'PEN', 'status' => 'confirmed', 'payment_status' => 'paid']);
        OrderAttribution::create(['order_id' => $order->id, 'store_id' => $store->id, 'channel' => 'meta', 'utm_source' => 'facebook']);

        $this->actingAs($store->user)->get(route('dashboard.marketing.index'))->assertOk()
            ->assertSee('1 producto listo')->assertSee('1 fuera del catálogo')->assertSee('1 pedido pagado')->assertSee('120.00');
    }

    public function test_product_form_controls_whether_a_product_goes_to_the_catalog(): void
    {
        $store = $this->store();
        $product = $this->product($store);
        $owner = $store->user;
        $form = ['name' => 'Audífonos Pro', 'price' => 50, 'stock' => 10, 'is_active' => 1];

        $this->actingAs($owner)->get(route('dashboard.productos.edit', $product))->assertOk()->assertDontSee('📣 Publicidad');
        $this->connect($store);
        $this->actingAs($owner)->get(route('dashboard.productos.edit', $product))->assertOk()->assertSee('📣 Publicidad');

        $this->actingAs($owner)->put(route('dashboard.productos.update', $product), $form + ['ads_settings' => 1, 'include_in_ads' => 0, 'condition' => 'used'])->assertRedirect();
        $this->assertSame([true, 'used'], [$product->fresh()->exclude_from_ads, $product->fresh()->condition]);

        // A save without the section (e.g. an older form) leaves the setting alone.
        $this->actingAs($owner)->put(route('dashboard.productos.update', $product), $form)->assertRedirect();
        $this->assertTrue($product->fresh()->exclude_from_ads);
    }

    public function test_sidebar_links_to_marketing(): void
    {
        $store = $this->store();
        $this->actingAs($store->user)->get(route('dashboard.index'))->assertOk()
            ->assertSee(route('dashboard.marketing.index'), false);
    }

    // ─── Migration not run yet (the deploy never runs migrations) ─

    public function test_everything_keeps_working_before_the_marketing_migration_runs(): void
    {
        Schema::drop('order_attributions');
        Schema::drop('marketing_event_logs');
        Schema::drop('store_marketing_integrations');
        Schema::table('products', fn ($table) => $table->dropColumn(['condition', 'exclude_from_ads']));

        $store = $this->store();
        $product = Product::create(['store_id' => $store->id, 'name' => 'Polo', 'slug' => 'polo', 'price' => 50, 'stock' => 10, 'is_active' => true]);
        $owner = $store->user;

        $this->get('/tienda/audio-lima/producto/polo')->assertOk()->assertDontSee('__tribioMarketing', false);
        $this->get('/tienda/audio-lima/feed/facebook.xml')->assertNotFound();
        $number = $this->checkout($store, $product, $this->consentCookies())->assertOk()->json('order_number');
        $order = Order::where('order_number', $number)->sole();
        $this->actingAs($owner)->post(route('dashboard.pedidos.payments.store', $order), ['amount' => $order->total, 'method' => 'yape'])->assertRedirect();
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->getJson("/api/pedido-estado/{$number}")->assertOk()->assertJsonMissingPath('tracking');

        $this->actingAs($owner)->get(route('dashboard.marketing.index'))->assertOk()->assertSee('Estamos activando este módulo');
        $this->actingAs($owner)->get(route('dashboard.marketing.meta.edit'))->assertOk();
        $this->actingAs($owner)->put(route('dashboard.marketing.meta.update'), ['pixel_id' => self::PIXEL, 'default_condition' => 'new'])->assertSessionHas('error');
        $this->actingAs($owner)->put(route('dashboard.productos.update', $product), ['name' => 'Polo 2', 'price' => 50, 'stock' => 10, 'is_active' => 1, 'ads_settings' => 1])->assertRedirect();
        $this->assertSame('Polo 2', $product->fresh()->name);
        $this->assertSame([], $this->metaCalls());
    }
}
