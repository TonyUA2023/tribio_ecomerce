<?php

namespace Tests\Feature;

use App\Mail\ProductReviewReceived;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Store;
use App\Models\User;
use App\Services\CustomerIdentityService;
use App\Services\Reviews\ProductReviewService;
use App\Services\Reviews\ReviewNotAllowedException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Concerns\BuildsReviewFixtures;
use Tests\TestCase;

/**
 * Verified-purchase product reviews: the purchase rule, the numbers derived from them,
 * the three ways a buyer can submit one (storefront form, web hub, mobile API) and the
 * owner's dashboard. The storefront rendering lives in ProductReviewsStorefrontTest.
 */
class ProductReviewsTest extends TestCase
{
    use RefreshDatabase, BuildsReviewFixtures;

    protected function migrateFreshUsing(): array
    {
        if (config('database.default') !== 'sqlite') {
            return [];
        }

        return ['--path' => array_values(array_filter(glob(database_path('migrations/*.php')),
            fn ($path) => !str_ends_with($path, '2026_09_11_182936_update_role_column_in_users_table.php'))), '--realpath' => true];
    }

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    // ─── Public name ─────────────────────────────────────────────

    public function test_reviewers_are_shown_as_first_name_and_last_initial_never_an_email(): void
    {
        $this->assertSame('María P.', ProductReview::publicNameFor('María Castillo Pérez'));
        $this->assertSame('Jorge', ProductReview::publicNameFor('Jorge'));
        $this->assertSame('Ana L.', ProductReview::publicNameFor('  ana   lópez '));
        $this->assertSame('Cliente', ProductReview::publicNameFor('maria@gmail.com'));
        $this->assertSame('Cliente', ProductReview::publicNameFor(''));
        $this->assertSame('María P.', ProductReview::publicNameFor('MARÍA PÉREZ'));       // shouting → normal case
        $this->assertSame('McDonald T.', ProductReview::publicNameFor('McDonald Torres')); // styled spelling is kept
    }

    // ─── The purchase rule ───────────────────────────────────────

    public function test_a_buyer_with_a_delivered_order_can_review_and_the_product_numbers_update(): void
    {
        $product = $this->makeProduct($this->makeStore());
        $buyer = $this->buyer();
        $order = $this->purchase($buyer, $product);

        $result = $this->service()->submit($buyer, $product, 5, '  Excelente calidad  ');

        $this->assertTrue($result['created']);
        $review = $result['review'];
        $this->assertSame('Excelente calidad', $review->comment);
        $this->assertSame('María P.', $review->reviewer_name);
        $this->assertSame($order->items()->first()->id, $review->order_item_id);
        $this->assertSame(ProductReview::STATUS_PUBLISHED, $review->status); // publishes immediately

        $product->refresh();
        $this->assertSame(1, $product->reviews_count);
        $this->assertEqualsWithDelta(5.0, (float) $product->average_rating, 0.001);
    }

    public function test_a_guest_order_placed_with_the_same_email_also_counts(): void
    {
        $product = $this->makeProduct($this->makeStore());
        $buyer = $this->buyer(['email' => 'lucia@example.test']);
        // Bought before creating the Tribio Pass account: user_id is null, only the email matches.
        $this->purchase($buyer, $product, 'delivered', ['user_id' => null, 'customer_email' => 'lucia@example.test']);

        $this->assertTrue($this->service()->submit($buyer, $product, 4, null)['created']);
    }

    public function test_reviewing_requires_the_order_to_be_delivered(): void
    {
        $product = $this->makeProduct($this->makeStore());
        $buyer = $this->buyer();
        $this->purchase($buyer, $product, 'shipped');

        $state = $this->service()->viewerState($buyer, $product)['state'];
        $this->assertSame(ProductReviewService::STATE_AWAITING_DELIVERY, $state);

        try {
            $this->service()->submit($buyer, $product, 5, 'Aún no me llega');
            $this->fail('A shipped order must not unlock reviewing.');
        } catch (ReviewNotAllowedException $e) {
            $this->assertSame(ProductReviewService::STATE_AWAITING_DELIVERY, $e->state);
        }
        $this->assertSame(0, ProductReview::count());
    }

    public function test_someone_who_never_bought_it_cannot_review(): void
    {
        $product = $this->makeProduct($this->makeStore());
        $other = $this->makeProduct($product->store, ['slug' => 'otro-producto']);
        $buyer = $this->buyer();
        $this->purchase($buyer, $other); // bought a DIFFERENT product

        $this->assertSame(ProductReviewService::STATE_NOT_PURCHASED, $this->service()->viewerState($buyer, $product)['state']);
        $this->expectException(ReviewNotAllowedException::class);
        $this->service()->submit($buyer, $product, 5, 'Nunca lo compré');
    }

    public function test_someone_elses_delivered_order_gives_no_right_to_review(): void
    {
        $product = $this->makeProduct($this->makeStore());
        $realBuyer = $this->buyer();
        $stranger = $this->buyer();
        $this->purchase($realBuyer, $product);

        $this->expectException(ReviewNotAllowedException::class);
        $this->service()->submit($stranger, $product, 1, 'Sin haber comprado');
    }

    public function test_a_store_owner_cannot_review_their_own_products(): void
    {
        $store = $this->makeStore();
        $product = $this->makeProduct($store);
        $owner = $store->user;
        $this->purchase($owner, $product); // even with a real delivered order of their own

        $this->assertSame(ProductReviewService::STATE_OWN_STORE, $this->service()->viewerState($owner, $product)['state']);
        $this->expectException(ReviewNotAllowedException::class);
        $this->service()->submit($owner, $product, 5, 'Mi propia tienda');
    }

    public function test_a_store_owner_can_review_products_of_another_store_as_a_buyer(): void
    {
        $otherStore = $this->makeStore();
        $product = $this->makeProduct($otherStore);
        $someOwner = $this->makeStore()->user;
        $this->purchase($someOwner, $product);

        $this->assertTrue($this->service()->submit($someOwner, $product, 5, 'Compré como cliente')['created']);
    }

    public function test_one_review_per_buyer_and_product_a_second_submission_edits_it(): void
    {
        $product = $this->makeProduct($this->makeStore());
        $buyer = $this->buyer();
        $this->purchase($buyer, $product);
        $this->purchase($buyer, $product); // bought it twice

        $first = $this->service()->submit($buyer, $product, 3, 'Regular');
        $this->assertNull($first['review']->edited_at);

        $second = $this->service()->submit($buyer, $product, 5, 'Después de usarlo, me encantó');

        $this->assertFalse($second['created']);
        $this->assertSame($first['review']->id, $second['review']->id);
        $this->assertSame(1, ProductReview::count());
        $this->assertSame(5, $second['review']->rating);
        $this->assertNotNull($second['review']->edited_at);
        $this->assertEqualsWithDelta(5.0, (float) $product->fresh()->average_rating, 0.001);
    }

    public function test_resubmitting_identical_content_does_not_mark_the_review_as_edited(): void
    {
        $product = $this->makeProduct($this->makeStore());
        $buyer = $this->buyer();
        $this->purchase($buyer, $product);

        $this->service()->submit($buyer, $product, 4, 'Bien');
        $again = $this->service()->submit($buyer, $product, 4, 'Bien');

        $this->assertNull($again['review']->edited_at);
    }

    public function test_the_owner_hiding_a_review_survives_the_buyer_editing_it(): void
    {
        $product = $this->makeProduct($this->makeStore());
        $buyer = $this->buyer();
        $this->purchase($buyer, $product);

        $review = $this->service()->submit($buyer, $product, 1, 'Muy malo')['review'];
        $this->service()->setVisibility($review, false);
        $this->service()->submit($buyer, $product, 2, 'Algo mejor');

        $this->assertSame(ProductReview::STATUS_HIDDEN, $review->fresh()->status);
        $this->assertSame(0, $product->fresh()->reviews_count);
    }

    public function test_comment_is_optional_capped_and_stripped_of_control_characters(): void
    {
        $service = $this->service();

        $this->assertNull($service->cleanText(null, 100));
        $this->assertNull($service->cleanText("  \n\t ", 100));
        $this->assertSame("línea 1\n\nlínea 2", $service->cleanText("línea 1\r\n\r\n\r\n\r\nlínea 2", 100));
        $this->assertSame('abc def', $service->cleanText("abc\x00\x07 def", 100));
        $this->assertSame(5, mb_strlen($service->cleanText('ñandú-largo', 5)));
        // The zero-width joiner that glues family emoji together must survive.
        $this->assertSame("👨\u{200D}👩", $service->cleanText("👨\u{200D}👩", 100));
    }

    // ─── Derived numbers ─────────────────────────────────────────

    public function test_average_and_count_ignore_hidden_reviews_and_follow_every_change(): void
    {
        $product = $this->makeProduct($this->makeStore());
        $a = $this->buyer(); $b = $this->buyer(); $c = $this->buyer();
        foreach ([$a, $b, $c] as $user) {
            $this->purchase($user, $product);
        }

        $ra = $this->service()->submit($a, $product, 5, null)['review'];
        $rb = $this->service()->submit($b, $product, 4, null)['review'];
        $this->service()->submit($c, $product, 1, null);

        $product->refresh();
        $this->assertSame(3, $product->reviews_count);
        $this->assertEqualsWithDelta(3.33, (float) $product->average_rating, 0.001);

        $this->service()->setVisibility(ProductReview::where('rating', 1)->first(), false);
        $product->refresh();
        $this->assertSame(2, $product->reviews_count);
        $this->assertEqualsWithDelta(4.5, (float) $product->average_rating, 0.001);

        $rb->delete();
        $this->assertEqualsWithDelta(5.0, (float) $product->fresh()->average_rating, 0.001);

        $ra->delete();
        $product->refresh();
        $this->assertSame(0, $product->reviews_count);
        $this->assertEqualsWithDelta(0.0, (float) $product->average_rating, 0.001);
    }

    public function test_recomputing_the_numbers_does_not_touch_the_product_updated_at(): void
    {
        $product = $this->makeProduct($this->makeStore());
        $product->forceFill(['updated_at' => now()->subYear()])->saveQuietly();
        $before = $product->fresh()->updated_at->timestamp;

        $buyer = $this->buyer();
        $this->purchase($buyer, $product);
        $this->service()->submit($buyer, $product, 5, null);

        $this->assertSame($before, $product->fresh()->updated_at->timestamp);
    }

    public function test_summary_distribution_comes_from_the_published_rows(): void
    {
        $product = $this->makeProduct($this->makeStore());
        foreach ([5, 5, 4, 2] as $stars) {
            $user = $this->buyer();
            $this->purchase($user, $product);
            $this->service()->submit($user, $product, $stars, null);
        }

        $summary = $this->service()->summary($product);

        $this->assertSame(4, $summary['count']);
        $this->assertSame(4.0, $summary['average']);
        $this->assertSame([5 => 2, 4 => 1, 3 => 0, 2 => 1, 1 => 0], $summary['distribution']);
    }

    // ─── Mail ────────────────────────────────────────────────────

    public function test_a_new_review_notifies_the_store_owner_once_and_edits_stay_silent(): void
    {
        $store = $this->makeStore(['contact_email' => 'duena@example.test']);
        $product = $this->makeProduct($store);
        $buyer = $this->buyer();
        $this->purchase($buyer, $product);

        $this->service()->submit($buyer, $product, 5, 'Genial');
        $this->service()->submit($buyer, $product, 4, 'Genial, pero tardó');

        Mail::assertQueued(ProductReviewReceived::class, 1);
        Mail::assertQueued(ProductReviewReceived::class, fn ($mail) => $mail->hasTo('duena@example.test'));
    }

    public function test_the_notification_renders_and_customer_text_cannot_inject_links_or_html(): void
    {
        $store = $this->makeStore();
        $product = $this->makeProduct($store, ['name' => 'Polo [oferta](http://evil.test)']);
        $buyer = $this->buyer(['name' => 'Ana *Hacker* <b>Pérez</b>']);
        $this->purchase($buyer, $product);

        $review = $this->service()->submit($buyer, $product, 2, "Malo [pincha aquí](http://evil.test/phish)\n<script>alert(1)</script>\nsegunda línea")['review'];

        $html = (new ProductReviewReceived($review))->render();

        $this->assertStringContainsString('2/5', $html);
        $this->assertStringContainsString('segunda línea', $html);
        $this->assertStringNotContainsString('href="http://evil.test', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString(route('dashboard.resenas.index'), $html);
    }

    // ─── Mobile / hub JSON endpoint ──────────────────────────────

    public function test_mobile_api_creates_a_review_for_a_delivered_purchase(): void
    {
        $product = $this->makeProduct($this->makeStore());
        $buyer = $this->buyer();
        $this->purchase($buyer, $product);
        Sanctum::actingAs($buyer);

        $this->postJson('/api/customer/reviews', ['product_id' => $product->id, 'rating' => 5, 'comment' => 'Llegó perfecto'])
            ->assertCreated()
            ->assertJson([
                'success' => true, 'created' => true,
                'review'  => ['product_id' => $product->id, 'rating' => 5, 'comment' => 'Llegó perfecto', 'status' => 'published'],
                'product' => ['id' => $product->id, 'reviews_count' => 1, 'average_rating' => 5.0],
            ]);

        // Same door again = an edit, so 200 (not 201) and still one row.
        $this->postJson('/api/customer/reviews', ['product_id' => $product->id, 'rating' => 4])
            ->assertOk()->assertJson(['created' => false, 'review' => ['rating' => 4, 'comment' => null]]);
        $this->assertSame(1, ProductReview::count());
    }

    public function test_mobile_api_answers_403_with_a_reason_when_the_purchase_is_not_delivered(): void
    {
        $product = $this->makeProduct($this->makeStore());
        $buyer = $this->buyer();
        $this->purchase($buyer, $product, 'confirmed');
        Sanctum::actingAs($buyer);

        $this->postJson('/api/customer/reviews', ['product_id' => $product->id, 'rating' => 5])
            ->assertForbidden()
            ->assertJson(['success' => false, 'code' => 'awaiting_delivery']);

        $this->assertSame(0, ProductReview::count());
    }

    public function test_mobile_api_validates_the_payload_and_requires_a_token(): void
    {
        $product = $this->makeProduct($this->makeStore());

        $this->postJson('/api/customer/reviews', ['product_id' => $product->id, 'rating' => 5])->assertUnauthorized();

        $buyer = $this->buyer();
        $this->purchase($buyer, $product);
        Sanctum::actingAs($buyer);

        foreach ([['rating' => 0], ['rating' => 6], ['rating' => 'cinco'], [], ['rating' => 5, 'comment' => str_repeat('x', 1501)]] as $bad) {
            $this->postJson('/api/customer/reviews', ['product_id' => $product->id] + $bad)->assertUnprocessable();
        }
        $this->postJson('/api/customer/reviews', ['rating' => 5])->assertUnprocessable()->assertJsonValidationErrors('product_id');
        $this->postJson('/api/customer/reviews', ['product_id' => 999999, 'rating' => 5])->assertNotFound();

        $this->assertSame(0, ProductReview::count());
    }

    public function test_the_web_hub_endpoint_uses_the_session_and_the_same_rules(): void
    {
        $product = $this->makeProduct($this->makeStore());
        $buyer = $this->buyer();
        $this->purchase($buyer, $product);

        $this->postJson('/tribio-pass/resenas', ['product_id' => $product->id, 'rating' => 5])->assertUnauthorized();

        $this->actingAs($buyer)->postJson('/tribio-pass/resenas', ['product_id' => $product->id, 'rating' => 5, 'comment' => 'Todo bien'])
            ->assertCreated()->assertJson(['success' => true, 'created' => true]);
    }

    public function test_the_hub_page_wires_the_rating_modal_to_the_review_endpoint(): void
    {
        $this->get(route('tribio-pass'))->assertOk()
            ->assertSee(route('tribio-pass.reviews.store'), false)
            ->assertSee('submitReview()', false)
            ->assertSee('Califica tu compra');
    }

    public function test_the_hubs_orders_json_carries_the_review_flags(): void
    {
        $store = $this->makeStore();
        $product = $this->makeProduct($store);
        $buyer = $this->buyer();
        $this->purchase($buyer, $product);

        $this->actingAs($buyer)->getJson('/customer/orders')
            ->assertOk()
            ->assertJsonPath('orders.0.items.0.product_id', $product->id)
            ->assertJsonPath('orders.0.items.0.can_review', true)
            ->assertJsonPath('orders.0.items.0.review', null)
            ->assertJsonPath('orders.0.items.0.product_url', route('store.product', [$store->slug, $product->slug]));
    }

    public function test_platform_staff_cannot_review(): void
    {
        $product = $this->makeProduct($this->makeStore());
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)->postJson('/tribio-pass/resenas', ['product_id' => $product->id, 'rating' => 5])->assertForbidden();
    }

    // ─── Storefront form ─────────────────────────────────────────

    public function test_the_storefront_form_publishes_the_review_and_returns_to_the_reviews_section(): void
    {
        $store = $this->makeStore();
        $product = $this->makeProduct($store);
        $buyer = $this->buyer();
        $this->purchase($buyer, $product);
        $url = route('store.product.review', [$store->slug, $product->slug]);
        $page = route('store.product', [$store->slug, $product->slug]);

        $this->actingAs($buyer)->from($page)->post($url, ['rating' => 4, 'comment' => 'Buena relación precio-calidad'])
            ->assertRedirect($page . '#resenas')
            ->assertSessionHas('review_status');

        $this->assertDatabaseHas('product_reviews', ['product_id' => $product->id, 'user_id' => $buyer->id, 'rating' => 4]);
    }

    public function test_the_storefront_form_reports_problems_in_its_own_error_bag(): void
    {
        $store = $this->makeStore();
        $product = $this->makeProduct($store);
        $buyer = $this->buyer();
        $url = route('store.product.review', [$store->slug, $product->slug]);
        $page = route('store.product', [$store->slug, $product->slug]);

        // Never bought it.
        $this->actingAs($buyer)->from($page)->post($url, ['rating' => 5])
            ->assertRedirect($page . '#resenas')
            ->assertSessionHasErrors('review', null, 'review');

        // Bad rating.
        $this->purchase($buyer, $product);
        $this->from($page)->post($url, ['rating' => 9])
            ->assertRedirect($page . '#resenas')
            ->assertSessionHasErrors('rating', null, 'review');

        $this->assertSame(0, ProductReview::count());
    }

    public function test_a_guest_posting_the_storefront_form_is_sent_back_with_a_message(): void
    {
        $store = $this->makeStore();
        $product = $this->makeProduct($store);
        $page = route('store.product', [$store->slug, $product->slug]);

        $this->from($page)->post(route('store.product.review', [$store->slug, $product->slug]), ['rating' => 5])
            ->assertRedirect($page . '#resenas')
            ->assertSessionHasErrors('review', null, 'review');
        $this->assertSame(0, ProductReview::count());
    }

    public function test_the_form_cannot_review_a_product_through_another_stores_slug(): void
    {
        $storeA = $this->makeStore();
        $storeB = $this->makeStore();
        $productOfB = $this->makeProduct($storeB);
        $buyer = $this->buyer();
        $this->purchase($buyer, $productOfB);

        $this->actingAs($buyer)->post(route('store.product.review', [$storeA->slug, $productOfB->slug]), ['rating' => 5])
            ->assertNotFound();
        $this->assertSame(0, ProductReview::count());
    }

    // ─── Purchase history (hub + mobile "Mis Compras") ───────────

    public function test_purchase_history_tells_the_client_which_items_can_be_rated(): void
    {
        $store = $this->makeStore();
        $delivered = $this->makeProduct($store, ['name' => 'Entregado']);
        $shipped = $this->makeProduct($store, ['name' => 'En camino']);
        $rated = $this->makeProduct($store, ['name' => 'Ya calificado']);
        $buyer = $this->buyer();

        $this->purchase($buyer, $delivered);
        $this->purchase($buyer, $shipped, 'shipped');
        $this->purchase($buyer, $rated);
        $this->service()->submit($buyer, $rated, 4, 'Bien');

        $items = app(CustomerIdentityService::class)->ordersFor($buyer)
            ->flatMap(fn ($order) => $order['items'])->keyBy('name');

        $this->assertTrue($items['Entregado']['can_review']);
        $this->assertNull($items['Entregado']['review']);
        $this->assertSame($delivered->id, $items['Entregado']['product_id']);

        $this->assertFalse($items['En camino']['can_review']);
        $this->assertNull($items['En camino']['review']);

        $this->assertSame(4, $items['Ya calificado']['review']['rating']);
        $this->assertFalse($items['Ya calificado']['can_review']); // it's an edit now, not a new review
    }

    public function test_history_flags_agree_with_what_the_service_would_accept(): void
    {
        $store = $this->makeStore();
        $product = $this->makeProduct($store);
        $ownProduct = $this->makeProduct($store, ['slug' => 'propio']);
        $owner = $store->user;
        $this->purchase($owner, $ownProduct);

        $item = app(CustomerIdentityService::class)->ordersFor($owner)->flatMap(fn ($o) => $o['items'])->first();

        $this->assertFalse($item['can_review']); // owner buying from own store: the service refuses too
        $this->assertSame(ProductReviewService::STATE_OWN_STORE, $this->service()->viewerState($owner, $ownProduct)['state']);
    }

    // ─── Dashboard ───────────────────────────────────────────────

    public function test_dashboard_lists_only_the_current_stores_reviews_and_filters_them(): void
    {
        $store = $this->makeStore();
        $product = $this->makeProduct($store, ['name' => 'Polo azul']);
        $other = $this->makeProduct($store, ['name' => 'Gorra roja']);
        $this->seedReview($product, 5, 'Comentario cinco estrellas');
        $this->seedReview($other, 2, 'Comentario dos estrellas');

        $foreign = $this->makeProduct($this->makeStore(), ['name' => 'Producto ajeno']);
        $this->seedReview($foreign, 1, 'Reseña de otra tienda');

        $this->actingAs($store->user)->get(route('dashboard.resenas.index'))
            ->assertOk()
            ->assertSee('Comentario cinco estrellas')->assertSee('Comentario dos estrellas')
            ->assertDontSee('Reseña de otra tienda');

        $this->get(route('dashboard.resenas.index', ['calificacion' => 2]))
            ->assertOk()->assertSee('Comentario dos estrellas')->assertDontSee('Comentario cinco estrellas');

        $this->get(route('dashboard.resenas.index', ['producto' => $product->id]))
            ->assertOk()->assertSee('Comentario cinco estrellas')->assertDontSee('Comentario dos estrellas');
    }

    public function test_dashboard_requires_a_store_owner_session(): void
    {
        $this->get(route('dashboard.resenas.index'))->assertRedirect('/login');
        $this->actingAs($this->buyer())->get(route('dashboard.resenas.index'))->assertStatus(403);
    }

    public function test_owner_hides_and_unhides_a_review_and_the_rating_follows(): void
    {
        $store = $this->makeStore();
        $product = $this->makeProduct($store);
        $review = $this->seedReview($product, 1, 'Spam');

        $this->actingAs($store->user)->patch(route('dashboard.resenas.visibility', $review))->assertSessionHas('success');
        $this->assertSame(ProductReview::STATUS_HIDDEN, $review->fresh()->status);
        $this->assertSame(0, $product->fresh()->reviews_count);

        $this->patch(route('dashboard.resenas.visibility', $review));
        $this->assertSame(ProductReview::STATUS_PUBLISHED, $review->fresh()->status);
        $this->assertSame(1, $product->fresh()->reviews_count);
    }

    public function test_owner_answers_publicly_edits_and_removes_the_reply(): void
    {
        $store = $this->makeStore();
        $review = $this->seedReview($this->makeProduct($store), 2, 'Llegó tarde');

        $this->actingAs($store->user)->put(route('dashboard.resenas.reply', $review), ['reply' => '  Lamentamos la demora, ya lo mejoramos.  '])
            ->assertSessionHas('success');
        $review->refresh();
        $this->assertSame('Lamentamos la demora, ya lo mejoramos.', $review->store_reply);
        $this->assertNotNull($review->store_replied_at);

        $this->put(route('dashboard.resenas.reply', $review), ['reply' => 'Corrección: te compensamos con un cupón.']);
        $this->assertSame('Corrección: te compensamos con un cupón.', $review->fresh()->store_reply);

        $this->put(route('dashboard.resenas.reply', $review), ['reply' => ''])->assertSessionHasErrors('reply');
        $this->put(route('dashboard.resenas.reply', $review), ['reply' => str_repeat('x', 1001)])->assertSessionHasErrors('reply');

        $this->delete(route('dashboard.resenas.reply.destroy', $review))->assertSessionHas('success');
        $review->refresh();
        $this->assertNull($review->store_reply);
        $this->assertNull($review->store_replied_at);
    }

    public function test_an_owner_cannot_touch_another_stores_reviews(): void
    {
        $intruder = $this->makeStore();
        $review = $this->seedReview($this->makeProduct($this->makeStore()), 3, 'De otra tienda');

        $this->actingAs($intruder->user);
        $this->patch(route('dashboard.resenas.visibility', $review))->assertNotFound();
        $this->put(route('dashboard.resenas.reply', $review), ['reply' => 'Intruso'])->assertNotFound();
        $this->delete(route('dashboard.resenas.reply.destroy', $review))->assertNotFound();

        $review->refresh();
        $this->assertSame(ProductReview::STATUS_PUBLISHED, $review->status);
        $this->assertNull($review->store_reply);
    }
}
