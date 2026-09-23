<?php

namespace Tests\Feature;

use App\Models\ProductReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Concerns\BuildsReviewFixtures;
use Tests\TestCase;

/**
 * The reviews section as customers see it: rendered through the real HTTP kernel on every
 * template (a Blade slip such as an unclosed @if only shows up when the page is served —
 * `view:cache` compiles it happily), for every kind of visitor, on standard and custom domains.
 */
class ProductReviewsStorefrontTest extends TestCase
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
        $this->withoutVite();
    }

    /** Every storefront design that has a product page (vibrant-fresh reuses elegant-dark's). */
    public static function templates(): array
    {
        return [
            'minimal-light (Maetek)' => ['minimal-light'],
            'soft-market'            => ['soft-market'],
            'elegant-dark'           => ['elegant-dark'],
            'vibrant-fresh'          => ['vibrant-fresh'],
            'industrial-light'       => ['industrial-light'],
            'elegant-refurbished'    => ['elegant-refurbished'],
        ];
    }

    private function pageUrl($store, $product): string
    {
        return route('store.product', [$store->slug, $product->slug]);
    }

    // ─── What the page shows ─────────────────────────────────────

    #[DataProvider('templates')]
    public function test_every_template_shows_real_reviews_instead_of_the_old_mockup(string $template): void
    {
        $store = $this->makeStore(['template_name' => $template, 'name' => 'Casa Verde']);
        $product = $this->makeProduct($store, ['name' => 'Polo bordado']);
        $this->seedReview($product, 5, 'Excelente calidad, llegó rápido', ['name' => 'Lucía Fernández']);
        $answered = $this->seedReview($product, 4, 'Buen acabado', ['name' => 'Pedro Ramos']);
        $hidden = $this->seedReview($product, 1, 'Comentario oculto por spam', ['name' => 'Spammer Malo']);
        $this->service()->setVisibility($hidden, false);
        $this->service()->reply($answered, 'Gracias Pedro, te esperamos pronto');

        $html = $this->get($this->pageUrl($store, $product))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'id="resenas"'), "$template: exactly one reviews section");
        foreach (['Lucía F.', 'Pedro R.', 'Excelente calidad, llegó rápido', 'Buen acabado', 'Respuesta de Casa Verde',
                     'Gracias Pedro, te esperamos pronto', '4.5', '2 reseñas', 'Compra verificada', 'Opiniones de clientes'] as $expected) {
            $this->assertStringContainsString($expected, $html, "$template should show '$expected'");
        }
        foreach (['Comentario oculto por spam', 'Spammer M.', 'Opiniones de Clientes (28)', 'Hace 2 días',
                     'Llegó rapidísimo y la calidad es tal cual', 'Customer Reviews (28)'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $html, "$template must not show '$forbidden'");
        }
        $this->assertStringContainsString('class="tr-rv__btn" data-rv-login', $html, "$template: a guest is invited to sign in");
    }

    #[DataProvider('templates')]
    public function test_every_template_renders_the_empty_state_for_a_product_without_reviews(string $template): void
    {
        $store = $this->makeStore(['template_name' => $template]);
        $product = $this->makeProduct($store);

        $this->get($this->pageUrl($store, $product))
            ->assertOk()
            ->assertSee('id="resenas"', false)
            ->assertSee('Todavía no hay reseñas de este producto')
            ->assertDontSee('class="tr-rv__summary"', false);
    }

    public function test_the_section_sits_after_the_product_details_and_before_related_products_on_the_maetek_family(): void
    {
        foreach (['minimal-light', 'soft-market'] as $template) {
            $store = $this->makeStore(['template_name' => $template]);
            $product = $this->makeProduct($store);
            $this->makeProduct($store, ['name' => 'Relacionado']); // so "Related Products" renders

            $html = $this->get($this->pageUrl($store, $product))->assertOk()->getContent();

            $tabs = strpos($html, 'id="detalles-producto"');
            $reviews = strpos($html, 'id="resenas"');
            $related = strpos($html, 'Recomendados para ti');
            $this->assertNotFalse($tabs);
            $this->assertNotFalse($related, "$template: related products expected on the page");
            $this->assertTrue($tabs < $reviews && $reviews < $related, "$template: order must be details → reviews → related");
        }
    }

    public function test_the_rating_line_under_the_title_is_real_or_absent_on_the_maetek_family(): void
    {
        foreach (['minimal-light', 'soft-market'] as $template) {
            $store = $this->makeStore(['template_name' => $template]);
            $product = $this->makeProduct($store);

            // No reviews: the old mockup ("4.9 · 28 valoraciones") is gone and nothing takes its place.
            $none = $this->get($this->pageUrl($store, $product))->assertOk()->getContent();
            $this->assertStringNotContainsString('28 valoraciones', $none, $template);
            $this->assertStringNotContainsString('href="#resenas"', $none, "$template shows no header rating without reviews");

            // One review, then three: real average and count, linking to the section.
            $this->seedReview($product, 5, 'Uno');
            $one = $this->get($this->pageUrl($store, $product))->assertOk()->getContent();
            $this->assertStringContainsString('>5.0</span>', $one, $template);
            $this->assertStringContainsString('(1 reseña)', $one, $template);

            $this->seedReview($product, 4, 'Dos');
            $this->seedReview($product, 4, 'Tres');
            $three = $this->get($this->pageUrl($store, $product))->assertOk()->getContent();
            $this->assertStringContainsString('href="#resenas" class="flex items-center gap-1 hover:opacity-80 transition"', $three, $template);
            $this->assertStringContainsString('>4.3</span>', $three, $template);
            $this->assertStringContainsString('(3 reseñas)', $three, $template);
            $this->assertStringNotContainsString('28 valoraciones', $three, $template);

            // SKU and the guarantee keep their separators whether or not the rating is there.
            $product->forceFill(['sku' => 'POLO-01'])->saveQuietly();
            $withSku = $this->get($this->pageUrl($store, $product))->assertOk()->getContent();
            $this->assertMatchesRegularExpression('#\(3 reseñas\)</span>\s*</a>\s*<span class="text-stone-300">•</span>\s*<span>SKU:#', $withSku, $template);
        }
    }

    public function test_review_text_is_escaped_and_line_breaks_survive(): void
    {
        $store = $this->makeStore();
        $product = $this->makeProduct($store);
        $review = $this->seedReview($product, 3, "Primera línea\nSegunda <script>alert(\"x\")</script> línea");
        $this->service()->reply($review, '<b>Gracias</b> & saludos');

        $html = $this->get($this->pageUrl($store, $product))->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert("x")</script>', $html);
        $this->assertStringNotContainsString('<b>Gracias</b>', $html);
        $this->assertStringContainsString("Primera línea\nSegunda &lt;script&gt;", $html);
        $this->assertStringContainsString('&lt;b&gt;Gracias&lt;/b&gt; &amp; saludos', $html);
    }

    public function test_the_section_follows_the_stores_language(): void
    {
        $store = $this->makeStore();
        $product = $this->makeProduct($store);
        $review = $this->seedReview($product, 5, 'Great!');
        $this->service()->reply($review, 'Thanks!');

        $this->get($this->pageUrl($store, $product) . '?lang=en')
            ->assertOk()
            ->assertSee('Customer reviews')->assertSee('Verified purchase')->assertSee('Reply from')->assertSee('1 review')
            ->assertDontSee('Opiniones de clientes');
    }

    public function test_reviews_are_paginated_newest_first_with_plain_links(): void
    {
        $store = $this->makeStore();
        $product = $this->makeProduct($store);
        foreach (range(1, 10) as $n) {
            $this->seedReview($product, 5, "Comentario número $n.");
        }

        $first = $this->get($this->pageUrl($store, $product))->assertOk()->getContent();
        $this->assertStringContainsString('Comentario número 10.', $first);
        $this->assertStringContainsString('Comentario número 3.', $first);
        $this->assertStringNotContainsString('Comentario número 2.', $first);
        $this->assertStringContainsString('resenas=2#resenas', $first);
        $this->assertStringContainsString('10 reseñas', $first); // the summary counts all of them, not just the page

        $second = $this->get($this->pageUrl($store, $product) . '?resenas=2')->assertOk()->getContent();
        $this->assertStringContainsString('Comentario número 2.', $second);
        $this->assertStringContainsString('Comentario número 1.', $second);
        $this->assertStringNotContainsString('Comentario número 3.', $second);
        $this->assertStringContainsString('rel="prev"', $second);
    }

    // ─── Stars on the product cards ──────────────────────────────

    /** Which listing pages each design has (the two placeholder designs only ship a home). */
    public static function cardPages(): array
    {
        return [
            'minimal-light (Maetek)' => ['minimal-light', ['store.catalog', 'store.show']],
            'soft-market'            => ['soft-market', ['store.catalog', 'store.show']],
            'industrial-light'       => ['industrial-light', ['store.catalog', 'store.show']],
            // iStack's home reads $featured_products while the data provider passes featuredProducts, so it
            // has always shown its hardcoded sample cards: only its catalog lists real products.
            'elegant-refurbished'    => ['elegant-refurbished', ['store.catalog']],
            'elegant-dark'           => ['elegant-dark', ['store.show']],
            'vibrant-fresh'          => ['vibrant-fresh', ['store.show']],
        ];
    }

    #[DataProvider('cardPages')]
    public function test_product_cards_show_the_rating_of_reviewed_products(string $template, array $routes): void
    {
        $store = $this->makeStore(['template_name' => $template]);
        $rated = $this->makeProduct($store, ['name' => 'Producto Calificado', 'is_featured' => true]);
        $this->makeProduct($store, ['name' => 'Producto Sin Resenas', 'is_featured' => true]);
        $rated->forceFill(['reviews_count' => 12, 'average_rating' => 4.75])->saveQuietly();

        foreach ($routes as $routeName) {
            $html = $this->get(route($routeName, $store->slug))->assertOk()->getContent();
            $this->assertStringContainsString('Producto Calificado', $html, "$template $routeName lists the product");
            $this->assertGreaterThanOrEqual(1, substr_count($html, 'class="tr-rating-badge"'), "$template $routeName shows the stars");
            $this->assertStringContainsString('<strong style="font-weight:700">4.8</strong><span>(12)</span>', $html);
        }
    }

    #[DataProvider('cardPages')]
    public function test_a_store_without_reviews_renders_no_star_markup_on_its_cards(string $template, array $routes): void
    {
        $store = $this->makeStore(['template_name' => $template]);
        $this->makeProduct($store, ['name' => 'Producto Corriente', 'is_featured' => true]);

        foreach ($routes as $routeName) {
            $html = $this->get(route($routeName, $store->slug))->assertOk()->getContent();
            $this->assertStringContainsString('Producto Corriente', $html);
            $this->assertStringNotContainsString('tr-rating-badge', $html, "$template $routeName must look exactly as before");
        }
    }

    public function test_the_builders_product_carousel_shows_stars_only_for_reviewed_products(): void
    {
        $store = $this->makeStore();
        $rated = $this->makeProduct($store, ['name' => 'Con estrellas']);
        $plain = $this->makeProduct($store, ['name' => 'Sin estrellas', 'slug' => 'sin-estrellas']);
        $rated->forceFill(['reviews_count' => 5, 'average_rating' => 4.6])->saveQuietly();

        $html = view('components.store-sections.products_carousel', [
            'data' => [], 'store' => $store, 'featuredProducts' => collect([$rated->fresh(), $plain->fresh()]),
        ])->render();

        $this->assertStringContainsString('Con estrellas', $html);
        $this->assertStringContainsString('Sin estrellas', $html);
        $this->assertSame(1, substr_count($html, 'class="tr-rating-badge"'));
        $this->assertStringContainsString('<strong style="font-weight:700">4.6</strong><span>(5)</span>', $html);
    }

    // ─── What each kind of visitor can do ────────────────────────

    public function test_an_eligible_buyer_sees_the_form_pointing_at_the_store_route(): void
    {
        $store = $this->makeStore();
        $product = $this->makeProduct($store);
        $buyer = $this->buyer();
        $this->purchase($buyer, $product);

        $response = $this->actingAs($buyer)->get($this->pageUrl($store, $product))->assertOk();

        $response->assertSee('class="tr-rv__form" data-rv-form', false)
            ->assertSee('action="' . route('store.product.review', [$store->slug, $product->slug]) . '"', false)
            ->assertSee('Publicar reseña')
            ->assertDontSee('class="tr-rv__btn" data-rv-login', false);
        $this->assertSame(5, substr_count($response->getContent(), '<input type="radio" id="tr-rv-star-'));
        // Never `required` inside the review form: server-side validation owns that (a required field
        // in a hidden/collapsed block blocks submission silently).
        $content = $response->getContent();
        $form = substr($content, $start = strpos($content, 'class="tr-rv__form" data-rv-form'), strpos($content, '</form>', $start) - $start);
        $this->assertStringNotContainsString(' required', $form);
    }

    public function test_a_buyer_who_already_reviewed_sees_their_review_ready_to_edit(): void
    {
        $store = $this->makeStore();
        $product = $this->makeProduct($store);
        $buyer = $this->buyer();
        $this->purchase($buyer, $product);
        $this->service()->submit($buyer, $product, 4, 'Me gustó bastante');

        $this->actingAs($buyer)->get($this->pageUrl($store, $product))->assertOk()
            ->assertSee('Tu reseña')->assertSee('Editar mi reseña')->assertSee('Guardar cambios')
            ->assertSee('Me gustó bastante');
    }

    public function test_a_buyer_whose_review_was_hidden_is_told_only_they_can_see_it(): void
    {
        $store = $this->makeStore();
        $product = $this->makeProduct($store);
        $buyer = $this->buyer();
        $this->purchase($buyer, $product);
        $review = $this->service()->submit($buyer, $product, 2, 'Texto ofensivo')['review'];
        $this->service()->setVisibility($review, false);

        $html = $this->actingAs($buyer)->get($this->pageUrl($store, $product))->assertOk()->getContent();

        $this->assertStringContainsString('ocultó tu reseña', $html);
        $this->assertSame(1, substr_count($html, 'Texto ofensivo'), 'only inside their own edit box, never in the public list');
        $this->assertStringNotContainsString('class="tr-rv__summary"', $html);
    }

    public function test_visitors_that_cannot_review_get_the_reason(): void
    {
        $store = $this->makeStore();
        $product = $this->makeProduct($store);

        $waiting = $this->buyer();
        $this->purchase($waiting, $product, 'shipped');
        $this->actingAs($waiting)->get($this->pageUrl($store, $product))->assertOk()
            ->assertSee('aún no llega a tus manos')->assertDontSee('class="tr-rv__form" data-rv-form', false);

        $stranger = $this->buyer();
        $this->actingAs($stranger)->get($this->pageUrl($store, $product))->assertOk()
            ->assertSee('Solo los clientes que compraron este producto')->assertDontSee('class="tr-rv__form" data-rv-form', false);

        $this->actingAs($store->user)->get($this->pageUrl($store, $product))->assertOk()
            ->assertSee('Ir a Reseñas')->assertSee(route('dashboard.resenas.index'))->assertDontSee('class="tr-rv__form" data-rv-form', false);
    }

    // ─── The form round trip ─────────────────────────────────────

    public function test_submitting_from_the_page_shows_the_thank_you_and_the_new_review(): void
    {
        $store = $this->makeStore();
        $product = $this->makeProduct($store);
        $buyer = $this->buyer();
        $this->purchase($buyer, $product);
        $page = $this->pageUrl($store, $product);

        $this->actingAs($buyer)->from($page)->followingRedirects()
            ->post(route('store.product.review', [$store->slug, $product->slug]), ['rating' => 5, 'comment' => 'Superó mis expectativas'])
            ->assertOk()
            ->assertSee('Tu reseña ya está publicada')
            ->assertSee('Superó mis expectativas')
            ->assertSee('María P.');

        $this->assertSame(1, ProductReview::count());
    }

    public function test_a_failed_submit_explains_itself_next_to_the_form_and_keeps_what_was_typed(): void
    {
        $store = $this->makeStore();
        $product = $this->makeProduct($store);
        $buyer = $this->buyer();
        $this->purchase($buyer, $product);
        $page = $this->pageUrl($store, $product);

        $this->actingAs($buyer)->from($page)->followingRedirects()
            ->post(route('store.product.review', [$store->slug, $product->slug]), ['rating' => 9, 'comment' => 'Texto que no debe perderse'])
            ->assertOk()
            ->assertSee('La calificación debe estar entre 1 y 5 estrellas.')
            ->assertSee('Texto que no debe perderse');
        $this->assertSame(0, ProductReview::count());

        // A rule failure (not delivered yet) lands in the banner at the top of the section.
        $waiting = $this->buyer();
        $this->purchase($waiting, $product, 'confirmed');
        $this->actingAs($waiting)->from($page)->followingRedirects()
            ->post(route('store.product.review', [$store->slug, $product->slug]), ['rating' => 5])
            ->assertOk()->assertSee('class="tr-rv__flash tr-rv__flash--err"', false)->assertSee('cuando tu pedido sea entregado');
    }

    // ─── Code running ahead of its migration ─────────────────────

    /**
     * The deploy entrypoint does not run `php artisan migrate`, and this project's local .env points
     * at the production database — so the code can go live before the table exists. Reviews are a
     * complement: they must never take a product page (every store's), the buyers' purchase
     * history or the dashboard down with them.
     */
    public function test_a_missing_reviews_table_never_takes_the_rest_of_the_site_down(): void
    {
        $store = $this->makeStore(['template_name' => 'minimal-light']);
        $product = $this->makeProduct($store, ['name' => 'Polo sin reseñas aún']);
        $buyer = $this->buyer();
        $this->purchase($buyer, $product);
        Schema::drop('product_reviews');

        $this->get($this->pageUrl($store, $product))
            ->assertOk()->assertSee('Polo sin reseñas aún')->assertDontSee('id="resenas"', false);

        $this->actingAs($buyer)->getJson('/customer/orders')
            ->assertOk()->assertJsonPath('orders.0.items.0.can_review', false)->assertJsonPath('orders.0.items.0.review', null);

        $this->actingAs($store->user)->get(route('dashboard.resenas.index'))
            ->assertRedirect(route('dashboard.index'))->assertSessionHas('info');
    }

    // ─── Custom domains ──────────────────────────────────────────

    public function test_reviews_work_end_to_end_on_a_custom_domain(): void
    {
        $store = $this->makeStore(['custom_domain' => 'mitienda.test', 'slug' => 'mi-tienda', 'template_name' => 'minimal-light']);
        $product = $this->makeProduct($store, ['slug' => 'polo-dominio']);
        $this->seedReview($product, 5, 'Reseña visible en el dominio propio', ['name' => 'Rosa Vega']);
        $buyer = $this->buyer();
        $this->purchase($buyer, $product);

        $page = 'http://mitienda.test/producto/polo-dominio';
        $html = $this->actingAs($buyer)->get($page)->assertOk()->getContent();

        $this->assertStringContainsString('Reseña visible en el dominio propio', $html);
        // The custom-domain middleware strips /tienda/{slug}: the form must post to a route that exists there.
        $this->assertStringContainsString('action="http://mitienda.test/producto/polo-dominio/resena"', $html);

        $this->actingAs($buyer)->from($page)->post('http://mitienda.test/producto/polo-dominio/resena', ['rating' => 4, 'comment' => 'Desde mi dominio'])
            ->assertRedirect($page . '#resenas');
        $this->assertDatabaseHas('product_reviews', ['user_id' => $buyer->id, 'product_id' => $product->id, 'rating' => 4]);
    }
}
