<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderAttribution;
use App\Models\PendingCheckout;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\StoreMarketingIntegration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Dashboard → Marketing → Google: Merchant Center feed, site verification, product
 * structured data, GA4 / Google Ads tags behind the same cookie notice as Meta.
 */
class GoogleMarketingTest extends TestCase
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
        Http::fake();
        config(['app.url' => 'http://localhost']);
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
            'store_id' => $store->id, 'name' => 'Audífonos Pro', 'slug' => 'audifonos-pro', 'description' => 'Sonido limpio',
            'price' => 50, 'stock' => 10, 'track_stock' => true, 'is_active' => true, 'image_path' => 'stores/1/products/audifonos.webp',
        ]);
    }

    private function google(Store $store, array $attributes = []): StoreMarketingIntegration
    {
        return StoreMarketingIntegration::create($attributes + [
            'store_id' => $store->id, 'provider' => 'google', 'is_active' => true, 'default_condition' => 'new',
            'measurement_id' => 'G-AB12CD34EF', 'ads_conversion_id' => 'AW-123456789', 'ads_conversion_label' => 'AbC-D_efG',
        ]);
    }

    /** @return \Illuminate\Support\Collection<string, \SimpleXMLElement> keyed by g:id */
    private function feedItems(string $url)
    {
        $xml = simplexml_load_string($this->get($url)->assertOk()->getContent());
        $this->assertNotFalse($xml);

        return collect(iterator_to_array($xml->channel->item, false))
            ->map(fn ($item) => $item->children('http://base.google.com/ns/1.0'))
            ->keyBy(fn ($g) => (string) $g->id);
    }

    private function path(string $url): string
    {
        return (string) preg_replace('#^https?://[^/]+#', '', $url);
    }

    private function marketingHead(string $html): array
    {
        $config = preg_match('/window\.__tribioMarketing = (\{.*?\});<\/script>/', $html, $m) ? json_decode($m[1], true) : null;
        $jsonLd = preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m) ? json_decode($m[1], true) : null;

        return [$config, $jsonLd];
    }

    // ─── Merchant Center feed ────────────────────────────────────

    public function test_google_feed_is_published_only_while_google_is_connected_and_active(): void
    {
        $store = $this->store();
        $this->product($store);

        $this->get('/tienda/audio-lima/feed/google.xml')->assertNotFound();
        $integration = $this->google($store, ['is_active' => false]);
        $this->get('/tienda/audio-lima/feed/google.xml')->assertNotFound();
        $integration->update(['is_active' => true]);
        $this->get('/tienda/audio-lima/feed/google.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $this->get('/tienda/audio-lima/feed/facebook.xml')->assertNotFound('Connecting Google does not publish the Meta feed');
    }

    public function test_google_feed_uses_google_values_while_the_meta_feed_keeps_its_own(): void
    {
        $store = $this->store(['made_to_order_enabled' => true]);
        $this->google($store, ['default_condition' => 'refurbished']);
        StoreMarketingIntegration::create(['store_id' => $store->id, 'provider' => 'meta', 'is_active' => true, 'pixel_id' => '123456789012345']);
        $sale = $this->product($store, ['slug' => 'parlante', 'price' => 80, 'compare_price' => 100]);
        $backorder = $this->product($store, ['slug' => 'por-pedido', 'stock' => 0, 'allow_backorder' => true]);
        $soldOut = $this->product($store, ['slug' => 'agotado', 'stock' => 0]);
        $madeToOrder = $this->product($store, ['slug' => 'bordado', 'stock' => 0, 'sale_mode' => Product::SALE_MADE_TO_ORDER, 'lead_time_days' => 7]);
        $shirt = $this->product($store, ['slug' => 'polo', 'has_variants' => true]);
        $red = ProductVariant::create(['product_id' => $shirt->id, 'price' => 45, 'stock' => 3, 'attributes' => ['Color' => 'Rojo'], 'is_active' => true]);

        $google = $this->feedItems('/tienda/audio-lima/feed/google.xml');
        $s = $google[(string) $sale->id];
        $this->assertSame(['100.00 PEN', '80.00 PEN', 'in_stock', 'no', 'refurbished'], [(string) $s->price, (string) $s->sale_price, (string) $s->availability, (string) $s->identifier_exists, (string) $s->condition]);
        $this->assertSame('/storage/stores/1/products/audifonos.webp', $this->path((string) $s->image_link), 'Google accepts WebP: no JPEG copy');
        $this->assertSame('in_stock', (string) $google[(string) $backorder->id]->availability, 'Backorder needs a date Google requires; the store takes the order now');
        $this->assertSame('out_of_stock', (string) $google[(string) $soldOut->id]->availability);
        $m = $google[(string) $madeToOrder->id];
        $this->assertSame(['in_stock', '7'], [(string) $m->availability, (string) $m->max_handling_time]);
        $this->assertSame((string) $shirt->id, (string) $google["{$shirt->id}-{$red->id}"]->item_group_id);

        // Same store, Meta flavor: untouched by the Google changes (and not served from Google's cache).
        $meta = $this->feedItems('/tienda/audio-lima/feed/facebook.xml');
        $this->assertSame(['in stock', 'available for order', 'available for order', 'new'], [
            (string) $meta[(string) $sale->id]->availability, (string) $meta[(string) $backorder->id]->availability,
            (string) $meta[(string) $madeToOrder->id]->availability, (string) $meta[(string) $sale->id]->condition,
        ]);
        $this->assertSame('/feed-img/stores/1/products/audifonos.webp.jpg', $this->path((string) $meta[(string) $sale->id]->image_link));
        $this->assertSame('', (string) $meta[(string) $sale->id]->identifier_exists);
    }

    public function test_google_feed_on_a_custom_domain(): void
    {
        $store = $this->store(['custom_domain' => 'audiolima.test']);
        $this->google($store);
        $this->product($store);

        $this->assertMatchesRegularExpression('#<g:link>http://audiolima\.test(:\d+)?/producto/audifonos-pro</g:link>#',
            $this->get('http://audiolima.test/feed/google.xml')->assertOk()->getContent());
    }

    // ─── Storefront ──────────────────────────────────────────────

    public function test_product_page_gets_google_tags_verification_and_structured_data(): void
    {
        $store = $this->store(['custom_domain' => 'audiolima.test']);
        $this->google($store, ['domain_verification' => 'AbCdEf_1234567890-xyzXYZ']);
        $product = $this->product($store, ['name' => 'Audífonos </script><script>alert(1)</script>', 'price' => 189, 'compare_price' => 229, 'sku' => 'AUD-PRO']);

        $html = $this->get('/tienda/audio-lima/producto/audifonos-pro')->assertOk()->getContent();
        $this->assertStringContainsString('<meta name="google-site-verification" content="AbCdEf_1234567890-xyzXYZ">', $html);
        $this->assertStringNotContainsString('<script>alert(1)', $html, 'The product name cannot break out of the JSON-LD script');

        [$config, $jsonLd] = $this->marketingHead($html);
        $this->assertNull($config['meta']);
        $this->assertSame(['measurementId' => 'G-AB12CD34EF', 'adsId' => 'AW-123456789', 'adsLabel' => 'AbC-D_efG'], $config['google']);
        $this->assertSame([(string) $product->id], $config['viewContent']['content_ids']);

        $this->assertSame(['Product', 'AUD-PRO', 'Audio Lima'], [$jsonLd['@type'], $jsonLd['sku'], $jsonLd['brand']['name']]);
        $this->assertSame(['Offer', 'PEN', '189.00', 'https://schema.org/InStock', 'https://schema.org/NewCondition'], [
            $jsonLd['offers']['@type'], $jsonLd['offers']['priceCurrency'], $jsonLd['offers']['price'],
            $jsonLd['offers']['availability'], $jsonLd['offers']['itemCondition'],
        ]);
        $this->assertMatchesRegularExpression('#^http://audiolima\.test(:\d+)?/producto/audifonos-pro$#', $jsonLd['offers']['url']);
        $this->assertArrayNotHasKey('aggregateRating', $jsonLd, 'No reviews, no stars');

        [, $catalogJsonLd] = $this->marketingHead($this->get('/tienda/audio-lima/catalogo')->assertOk()->getContent());
        $this->assertNull($catalogJsonLd, 'Structured data only on the product page');
    }

    public function test_structured_data_for_variants_and_verified_reviews(): void
    {
        $store = $this->store();
        $this->google($store, ['measurement_id' => null, 'ads_conversion_id' => null, 'ads_conversion_label' => null]);
        $product = $this->product($store, ['has_variants' => true, 'track_stock' => true]);
        ProductVariant::create(['product_id' => $product->id, 'price' => 40, 'stock' => 0, 'attributes' => ['Talla' => 'S'], 'is_active' => true]);
        ProductVariant::create(['product_id' => $product->id, 'price' => 60, 'stock' => 2, 'attributes' => ['Talla' => 'M'], 'is_active' => true]);
        $product->forceFill(['average_rating' => 4.667, 'reviews_count' => 3])->save();

        $html = $this->get('/tienda/audio-lima/producto/audifonos-pro')->assertOk()->getContent();
        [$config, $jsonLd] = $this->marketingHead($html);
        $this->assertNull($config, 'Merchant Center only (no Analytics/Ads): no tags and no cookie notice');
        $this->assertSame(['AggregateOffer', '40.00', '60.00', 2, 'https://schema.org/InStock'], [
            $jsonLd['offers']['@type'], $jsonLd['offers']['lowPrice'], $jsonLd['offers']['highPrice'],
            $jsonLd['offers']['offerCount'], $jsonLd['offers']['availability'],
        ]);
        $this->assertEquals(['@type' => 'AggregateRating', 'ratingValue' => 4.7, 'reviewCount' => 3], $jsonLd['aggregateRating']);
    }

    public function test_meta_and_google_share_one_config_and_nothing_prints_when_paused(): void
    {
        $store = $this->store();
        $this->product($store);
        StoreMarketingIntegration::create(['store_id' => $store->id, 'provider' => 'meta', 'is_active' => true, 'pixel_id' => '123456789012345']);
        $google = $this->google($store);

        $html = $this->get('/tienda/audio-lima/producto/audifonos-pro')->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'window.__tribioMarketing'));
        [$config] = $this->marketingHead($html);
        $this->assertSame('123456789012345', $config['meta']['pixelId']);
        $this->assertSame('G-AB12CD34EF', $config['google']['measurementId']);

        $google->update(['is_active' => false]);
        StoreMarketingIntegration::where('provider', 'meta')->update(['is_active' => false]);
        $this->get('/tienda/audio-lima/producto/audifonos-pro')->assertOk()
            ->assertDontSee('__tribioMarketing', false)->assertDontSee('application/ld+json', false)->assertDontSee('google-site-verification', false);
    }

    // ─── Purchase + attribution ──────────────────────────────────

    public function test_a_google_only_store_gets_the_browser_purchase_with_its_order_number(): void
    {
        $store = $this->store();
        $this->google($store);
        $product = $this->product($store);
        $order = Order::create(['store_id' => $store->id, 'order_number' => 'TRB-2026-000500', 'customer_name' => 'Ana', 'customer_email' => 'ana@example.test',
            'subtotal' => 100, 'total' => 110, 'currency' => 'PEN', 'status' => 'confirmed', 'payment_status' => 'paid']);
        $order->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'price' => 50, 'quantity' => 2, 'subtotal' => 100]);

        $tracking = $this->getJson('/api/pedido-estado/TRB-2026-000500')->assertOk()->json('tracking');
        $this->assertSame(['purchase_TRB-2026-000500', 'TRB-2026-000500'], [$tracking['event_id'], $tracking['order_id']]);
        $this->assertEquals(110, $tracking['value']);
    }

    public function test_sales_from_google_ads_are_attributed_to_google(): void
    {
        $store = $this->store();
        $this->google($store);
        $product = $this->product($store);
        $attr = json_encode(['utm_source' => 'google', 'utm_medium' => 'cpc', 'gclid' => 'Cj0KCQabc', 'landing_path' => '/tienda/audio-lima', 'ts' => 1712345678]);

        foreach (['granted', 'denied'] as $consent) {
            $number = $this->withCredentials()->withUnencryptedCookies(['tribio_consent' => $consent, 'tribio_attr' => $attr])
                ->postJson(route('store.checkout', $store->slug), [
                    'customer_name' => 'Ana Pérez', 'customer_phone' => '987654321', 'customer_email' => 'ana@example.test',
                    'customer_country' => 'PE', 'items' => [['id' => $product->id, 'quantity' => 1]],
                ])->assertOk()->json('order_number');
            $order = Order::where('order_number', $number)->sole();
            $attribution = OrderAttribution::where('order_id', $order->id)->sole();
            $this->assertSame(['google', 'google', 'cpc'], [$attribution->channel, $attribution->utm_source, $attribution->utm_medium]);

            $tracking = PendingCheckout::where('order_id', $order->id)->sole()->payload['tracking'];
            $this->assertArrayNotHasKey('fbp', $tracking, 'Meta browser ids are never captured for a Google-only store');
            if ($consent === 'denied') {
                $this->assertArrayNotHasKey('gclid', $tracking['attribution'], 'Without consent the click id is dropped');
            }
        }
    }

    // ─── Dashboard ───────────────────────────────────────────────

    public function test_owner_connects_google_by_pasting_what_google_shows_them(): void
    {
        $store = $this->store(['custom_domain' => 'audiolima.test']);
        $this->product($store);
        $owner = $store->user;

        $this->actingAs($owner)->get(route('dashboard.marketing.index'))->assertOk()->assertSee('Conectar con Google')->assertDontSee('Google Shopping</p>', false);
        $this->actingAs($owner)->get(route('dashboard.marketing.google.edit'))->assertOk()
            ->assertSee('Código de verificación de Google')->assertDontSee('Necesitas un dominio propio')
            ->assertSeeInOrder(['audiolima.test', '/feed/google.xml'], false);

        $this->actingAs($owner)->put(route('dashboard.marketing.google.update'), [
            'is_active' => '1',
            'measurement_id' => "<script async src=\"https://www.googletagmanager.com/gtag/js?id=G-ab12cd34ef\"></script><script>gtag('config', 'G-AB12CD34EF');</script>",
            'ads_conversion_id' => "gtag('event', 'conversion', {'send_to': 'AW-123456789/AbC-D_efG', 'value': 1.0});",
            'ads_conversion_label' => '',
            'domain_verification' => '<meta name="google-site-verification" content="AbCdEf_1234567890-xyzXYZ" />',
            'default_condition' => 'used',
        ])->assertRedirect(route('dashboard.marketing.google.edit'))->assertSessionHas('success');

        $google = $store->googleIntegration()->first();
        $this->assertSame(['G-AB12CD34EF', 'AW-123456789', 'AbC-D_efG', 'AbCdEf_1234567890-xyzXYZ', 'used'], [
            $google->measurement_id, $google->ads_conversion_id, $google->ads_conversion_label, $google->domain_verification, $google->default_condition,
        ]);
        $this->actingAs($owner)->get(route('dashboard.marketing.index'))->assertOk()->assertSee('Midiendo compras');

        $this->actingAs($owner)->put(route('dashboard.marketing.google.update'), [
            'measurement_id' => 'UA-12345-1', 'ads_conversion_label' => 'AbC-D_efG', 'default_condition' => 'new',
        ])->assertSessionHasErrors(['measurement_id', 'ads_conversion_id']);
    }

    public function test_stores_without_their_own_domain_are_told_what_google_shopping_needs(): void
    {
        $store = $this->store();
        $owner = $store->user;

        $this->actingAs($owner)->get(route('dashboard.marketing.google.edit'))->assertOk()
            ->assertSee('Necesitas un dominio propio')->assertDontSee('Código de verificación de Google');

        $this->google($store);
        $this->product($store, ['slug' => 'sin-foto', 'image_path' => null]);
        $this->actingAs($owner)->get(route('dashboard.marketing.index'))->assertOk()->assertSee('Necesita dominio propio');
        $this->actingAs($owner)->get(route('dashboard.marketing.google.edit'))->assertOk()->assertSee('0 de 1 productos van a Google')->assertSee('No tiene foto');
    }

    public function test_product_form_shows_the_ads_section_for_google_only_stores(): void
    {
        $store = $this->store();
        $product = $this->product($store);
        $this->google($store);

        $this->actingAs($store->user)->get(route('dashboard.productos.edit', $product))->assertOk()
            ->assertSee('📣 Publicidad')->assertSee('Facebook, Instagram y Google');
    }

    // ─── Google migration not run yet ────────────────────────────

    public function test_meta_keeps_working_before_the_google_migration_runs(): void
    {
        Schema::table('store_marketing_integrations', fn ($table) => $table->dropColumn(['measurement_id', 'ads_conversion_id', 'ads_conversion_label']));
        $store = $this->store();
        $this->product($store);
        StoreMarketingIntegration::create(['store_id' => $store->id, 'provider' => 'meta', 'is_active' => true, 'pixel_id' => '123456789012345']);
        $owner = $store->user;

        [$config] = $this->marketingHead($this->get('/tienda/audio-lima/producto/audifonos-pro')->assertOk()->getContent());
        $this->assertSame('123456789012345', $config['meta']['pixelId']);
        $this->assertNull($config['google']);
        $this->get('/tienda/audio-lima/feed/facebook.xml')->assertOk();
        $this->get('/tienda/audio-lima/feed/google.xml')->assertNotFound();

        $this->actingAs($owner)->get(route('dashboard.marketing.index'))->assertOk()->assertSee('Estamos activando la conexión con Google');
        $this->actingAs($owner)->get(route('dashboard.marketing.google.edit'))->assertOk()->assertSee('Estamos activando este módulo');
        $this->actingAs($owner)->put(route('dashboard.marketing.google.update'), ['measurement_id' => 'G-AB12CD34EF', 'default_condition' => 'new'])
            ->assertSessionHas('error');
    }
}
