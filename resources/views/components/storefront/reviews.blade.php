{{--
    Sección de reseñas de un producto (compras verificadas) — compartida por TODAS las
    plantillas de la tienda. Las plantillas solo la incluyen bajo el detalle del producto:

        @include('components.storefront.reviews', ['variant' => 'stone'])

    Datos (heredados de la vista que la incluye, los arma StoreController::product()):
        $reviewSection  → ProductReviewService::storefrontData()
        $product, $store

    $variant elige la paleta: stone (Maetek/minimal-light) · soft (soft-market) ·
    industrial · refurbished · public. Todo el CSS vive aquí, con variables --rv-*, así que
    ninguna plantilla necesita clases Tailwind nuevas ni recompilar `npm run build`.
    Sin JS el formulario funciona igual (POST + redirect a #resenas); el <script> del final
    solo mejora dos cosas: el botón de "Iniciar sesión" y el aviso de "falta calificación".
--}}
@php
    $rvData = $reviewSection ?? null;
@endphp
@if($rvData && isset($product, $store))
@php
    $rvEn = \App\Helpers\TranslationHelper::isEn();
    $rvVariant = $variant ?? 'stone';
    $rvT = fn (string $es, string $en) => $rvEn ? $en : $es;
    // "hace 0 segundos" reads badly right after posting: under a minute is simply "justo ahora".
    $rvAgo = fn ($date) => abs(now()->timestamp - $date->timestamp) < 60
        ? ($rvEn ? 'just now' : 'justo ahora')
        : $date->copy()->locale($rvEn ? 'en' : 'es')->diffForHumans();
    $rvSummary = $rvData['summary'];
    $rvReviews = $rvData['reviews'];
    $rvViewer = $rvData['viewer'];
    $rvState = $rvViewer['state'];
    $rvMine = $rvViewer['review'];
    $rvErrors = isset($errors) ? $errors->getBag('review') : new \Illuminate\Support\MessageBag();
    $rvStars = fn (float $value, string $extra = '') => '<span class="tr-rv__stars ' . $extra . '" role="img" aria-label="'
        . e(number_format($value, 1) . ($rvEn ? ' out of 5 stars' : ' de 5 estrellas'))
        . '" style="--rv-fill:' . round(max(0, min(5, $value)) / 5 * 100, 1) . '%">★★★★★</span>';
    $rvFormAction = route('store.product.review', ['slug' => $store->slug, 'product' => $product->slug]);
    $rvCanWrite = in_array($rvState, ['can_review', 'reviewed'], true);
@endphp
<section id="resenas" class="tr-rv tr-rv--{{ $rvVariant }}" aria-labelledby="tr-rv-title" data-reviews-section>
<style>
.tr-rv{--rv-text:#1a1a1a;--rv-muted:#78716c;--rv-card:#fff;--rv-soft:#fafaf9;--rv-border:#e7e5e4;--rv-track:#e7e5e4;--rv-accent:#7da268;--rv-on-accent:#fff;--rv-star:#f59e0b;--rv-star-off:#d6d3d1;--rv-ok:#047857;--rv-radius:1.5rem;--rv-radius-sm:.9rem;--rv-title-case:none;--rv-title-track:0;font-family:inherit;color:var(--rv-text);scroll-margin-top:5.5rem;text-align:left}
.tr-rv--soft{--rv-accent:var(--t-primary,#7da268);--rv-on-accent:var(--t-on-primary,#fff)}
.tr-rv--industrial{--rv-text:#111827;--rv-muted:#6b7280;--rv-soft:#f9fafb;--rv-border:#e5e7eb;--rv-track:#e5e7eb;--rv-accent:var(--accent,#e50914);--rv-title-case:uppercase;--rv-title-track:.04em}
.tr-rv--refurbished{--rv-text:var(--text-main,#1d1d1f);--rv-muted:var(--text-muted,#86868b);--rv-card:var(--bg-secondary,#fff);--rv-soft:var(--bg-primary,#f5f5f7);--rv-border:var(--border-color,rgba(0,0,0,.1));--rv-track:rgba(0,0,0,.09);--rv-star-off:rgba(0,0,0,.16);--rv-accent:var(--er-accent,#0066cc);--rv-radius:var(--radius-lg,32px);--rv-radius-sm:var(--radius-sm,12px)}
.tr-rv--public{--rv-text:#0f172a;--rv-muted:#64748b;--rv-soft:#f8fafc;--rv-border:#e2e8f0;--rv-track:#e2e8f0;--rv-accent:#2563eb;--rv-star-off:#cbd5e1}
.tr-rv *,.tr-rv *::before,.tr-rv *::after{box-sizing:border-box}
/* zero-specificity reset: a plain class rule below must always be able to override it */
:where(.tr-rv p,.tr-rv h2,.tr-rv ol,.tr-rv ul,.tr-rv li,.tr-rv fieldset,.tr-rv legend){margin:0;padding:0}
.tr-rv__card{background:var(--rv-card);border:1px solid var(--rv-border);border-radius:var(--rv-radius);padding:clamp(1.25rem,3vw,2.5rem);box-shadow:0 1px 2px rgb(0 0 0 / .04)}
.tr-rv__eyebrow{font-size:.7rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:var(--rv-accent);margin-bottom:.35rem}
.tr-rv__title{font-size:clamp(1.4rem,3vw,1.9rem);font-weight:900;line-height:1.15;text-transform:var(--rv-title-case);letter-spacing:var(--rv-title-track);color:var(--rv-text)}
.tr-rv__flash{margin-top:1.25rem;padding:.8rem 1rem;border-radius:var(--rv-radius-sm);font-size:.88rem;font-weight:600;line-height:1.45}
.tr-rv__flash--ok{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
.tr-rv__flash--err{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
.tr-rv__stars{display:inline-block;font-size:1rem;letter-spacing:.08em;line-height:1;white-space:nowrap;color:var(--rv-star-off);background:linear-gradient(90deg,var(--rv-star) var(--rv-fill,100%),var(--rv-star-off) var(--rv-fill,100%));-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}
.tr-rv__stars--lg{font-size:1.3rem}
.tr-rv__summary{display:grid;gap:1.25rem;margin-top:1.5rem;padding:1.25rem;background:var(--rv-soft);border:1px solid var(--rv-border);border-radius:var(--rv-radius-sm)}
@media(min-width:640px){.tr-rv__summary{grid-template-columns:auto 1fr;align-items:center;gap:2.25rem;padding:1.5rem 2rem}}
.tr-rv__score{text-align:center}
.tr-rv__avg{font-size:3.1rem;font-weight:900;line-height:1;color:var(--rv-text)}
.tr-rv__count{font-size:.78rem;color:var(--rv-muted);margin-top:.35rem}
.tr-rv__bars{list-style:none;display:grid;gap:.42rem}
.tr-rv__bars li{display:grid;grid-template-columns:2.4rem 1fr 2rem;align-items:center;gap:.6rem;font-size:.78rem;color:var(--rv-muted)}
.tr-rv__bars li span:last-child{text-align:right}
.tr-rv__bar{height:.5rem;border-radius:99px;background:var(--rv-track);overflow:hidden}
.tr-rv__bar i{display:block;height:100%;border-radius:inherit;background:var(--rv-star)}
.tr-rv__empty{margin-top:1.5rem;padding:1.5rem 1.25rem;text-align:center;color:var(--rv-muted);font-size:.9rem;line-height:1.55;background:var(--rv-soft);border:1px solid var(--rv-border);border-radius:var(--rv-radius-sm)}
.tr-rv__empty strong{display:block;color:var(--rv-text);font-size:1rem;margin-bottom:.2rem}
.tr-rv__box{margin-top:1.25rem;padding:1.15rem 1.25rem;border:1px dashed var(--rv-border);border-radius:var(--rv-radius-sm);background:var(--rv-soft);font-size:.88rem;line-height:1.55;color:var(--rv-muted)}
.tr-rv__box strong{color:var(--rv-text)}
.tr-rv__box a{color:var(--rv-accent);font-weight:700}
.tr-rv__box--action{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.9rem}
.tr-rv__btn{appearance:none;border:0;cursor:pointer;background:var(--rv-accent);color:var(--rv-on-accent);font:inherit;font-size:.85rem;font-weight:800;line-height:1.2;padding:.75rem 1.3rem;border-radius:.9rem;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;transition:filter .15s,transform .15s}
.tr-rv__btn:hover{filter:brightness(.92)}
.tr-rv__btn:active{transform:scale(.98)}
.tr-rv__btn--ghost{background:transparent;color:var(--rv-text);border:1px solid var(--rv-border)}
.tr-rv__btn--ghost:hover{filter:none;border-color:var(--rv-accent);color:var(--rv-accent)}
.tr-rv__form{display:grid;gap:.9rem;margin-top:.9rem}
.tr-rv__label{font-size:.82rem;font-weight:700;color:var(--rv-text)}
.tr-rv__label small{font-weight:500;color:var(--rv-muted)}
.tr-rv__pick{display:inline-flex;flex-direction:row-reverse;justify-content:flex-end;gap:.1rem;position:relative}
.tr-rv__pick input{position:absolute;opacity:0;width:1px;height:1px;pointer-events:none}
.tr-rv__pick label{font-size:2.1rem;line-height:1;color:var(--rv-star-off);cursor:pointer;padding:0 .08rem;transition:transform .1s,color .1s}
.tr-rv__pick label:hover,.tr-rv__pick label:hover ~ label,.tr-rv__pick input:checked ~ label{color:var(--rv-star)}
.tr-rv__pick label:active{transform:scale(1.15)}
.tr-rv__pick input:focus-visible + label{outline:2px solid var(--rv-accent);outline-offset:2px;border-radius:6px}
.tr-rv__field-error{font-size:.8rem;font-weight:600;color:#b91c1c}
.tr-rv__textarea{width:100%;min-height:6.5rem;padding:.8rem 1rem;border:1px solid var(--rv-border);border-radius:var(--rv-radius-sm);background:var(--rv-card);color:var(--rv-text);font:inherit;font-size:.9rem;line-height:1.5;resize:vertical}
.tr-rv__textarea:focus{outline:2px solid var(--rv-accent);outline-offset:1px}
.tr-rv__fine{font-size:.75rem;color:var(--rv-muted)}
.tr-rv__edit>summary{cursor:pointer;list-style:none;font-weight:800;font-size:.85rem;color:var(--rv-accent)}
.tr-rv__edit>summary::-webkit-details-marker{display:none}
.tr-rv__mine{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.6rem 1rem}
.tr-rv__list{list-style:none;margin-top:1.75rem;display:grid;gap:1rem}
.tr-rv__item{padding:1.15rem 1.25rem;border:1px solid var(--rv-border);border-radius:var(--rv-radius-sm);background:var(--rv-card)}
.tr-rv__top{display:flex;align-items:flex-start;justify-content:space-between;gap:.75rem}
.tr-rv__who{display:flex;align-items:center;gap:.75rem;min-width:0}
.tr-rv__avatar{flex:none;width:2.4rem;height:2.4rem;border-radius:50%;display:grid;place-items:center;font-weight:800;font-size:.95rem;color:var(--rv-on-accent);background:var(--rv-accent)}
.tr-rv__name{font-weight:800;font-size:.92rem;color:var(--rv-text);overflow-wrap:anywhere}
.tr-rv__meta{display:flex;flex-wrap:wrap;align-items:center;gap:.15rem .55rem;font-size:.72rem;color:var(--rv-muted);margin-top:.15rem}
.tr-rv__verified{color:var(--rv-ok);font-weight:700}
@media(max-width:520px){.tr-rv__dot{display:none}.tr-rv__meta{column-gap:.7rem}}
.tr-rv__text{margin-top:.75rem;font-size:.92rem;line-height:1.6;white-space:pre-line;overflow-wrap:anywhere;color:var(--rv-text)}
.tr-rv__reply{margin-top:.9rem;padding:.85rem 1rem;border-left:3px solid var(--rv-accent);background:var(--rv-soft);border-radius:0 var(--rv-radius-sm) var(--rv-radius-sm) 0;font-size:.86rem;line-height:1.55}
.tr-rv__reply-head{font-size:.75rem;color:var(--rv-muted);margin-bottom:.25rem}
.tr-rv__reply-head strong{color:var(--rv-text);font-size:.82rem}
.tr-rv__reply-text{white-space:pre-line;overflow-wrap:anywhere;color:var(--rv-text)}
.tr-rv__pager{display:flex;flex-wrap:wrap;justify-content:center;gap:.75rem;margin-top:1.5rem}
</style>
<div class="tr-rv__card">
    <header>
        <p class="tr-rv__eyebrow">{{ $rvT('Compras verificadas', 'Verified purchases') }}</p>
        <h2 id="tr-rv-title" class="tr-rv__title">{{ $rvT('Opiniones de clientes', 'Customer reviews') }}</h2>
    </header>

    @if(session('review_status'))
        <div class="tr-rv__flash tr-rv__flash--ok" role="status">{{ session('review_status') }}</div>
    @endif
    @if($rvErrors->has('review'))
        <div class="tr-rv__flash tr-rv__flash--err" role="alert">{{ $rvErrors->first('review') }}</div>
    @endif

    @if($rvSummary['count'] > 0)
        <div class="tr-rv__summary">
            <div class="tr-rv__score">
                <p class="tr-rv__avg">{{ number_format($rvSummary['average'], 1) }}</p>
                {!! $rvStars((float) $rvSummary['average'], 'tr-rv__stars--lg') !!}
                <p class="tr-rv__count">{{ $rvSummary['count'] }} {{ $rvT($rvSummary['count'] === 1 ? 'reseña' : 'reseñas', $rvSummary['count'] === 1 ? 'review' : 'reviews') }}</p>
            </div>
            <ul class="tr-rv__bars" aria-label="{{ $rvT('Distribución de calificaciones', 'Rating distribution') }}">
                @foreach($rvSummary['distribution'] as $star => $total)
                    <li>
                        <span>{{ $star }} ★</span>
                        <div class="tr-rv__bar"><i style="width:{{ round($total / $rvSummary['count'] * 100) }}%"></i></div>
                        <span>{{ $total }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @else
        <div class="tr-rv__empty">
            <strong>{{ $rvT('Todavía no hay reseñas de este producto', 'No reviews for this product yet') }}</strong>
            {{ $rvT('Las reseñas las escriben clientes que ya recibieron su pedido.', 'Reviews are written by customers who have already received their order.') }}
        </div>
    @endif

    {{-- Lo que puede hacer quien mira la página --}}
    @if($rvState === 'guest')
        <div class="tr-rv__box tr-rv__box--action">
            <span><strong>{{ $rvT('¿Ya compraste este producto?', 'Already bought this product?') }}</strong><br>{{ $rvT('Inicia sesión con tu Tribio Pass para compartir tu opinión.', 'Sign in with your Tribio Pass to share your opinion.') }}</span>
            <button type="button" class="tr-rv__btn" data-rv-login data-fallback="{{ route('tribio-pass') }}">{{ $rvT('Iniciar sesión', 'Sign in') }}</button>
        </div>
    @elseif($rvState === 'own_store')
        <div class="tr-rv__box tr-rv__box--action">
            <span>{{ $rvT('Esta es tu tienda: no puedes reseñar tus propios productos, pero sí responder a tus clientes.', "This is your store: you can't review your own products, but you can reply to your customers.") }}</span>
            <a class="tr-rv__btn tr-rv__btn--ghost" href="{{ route('dashboard.resenas.index') }}">{{ $rvT('Ir a Reseñas', 'Go to Reviews') }}</a>
        </div>
    @elseif($rvState === 'awaiting_delivery')
        <div class="tr-rv__box">{{ $rvT('Tu pedido de este producto aún no llega a tus manos. Podrás calificarlo en cuanto sea entregado.', "Your order for this product hasn't been delivered yet. You'll be able to rate it as soon as it arrives.") }}</div>
    @elseif($rvState === 'not_purchased')
        <div class="tr-rv__box">{{ $rvT('Solo los clientes que compraron este producto y ya lo recibieron pueden dejar una reseña.', 'Only customers who bought this product and have received it can leave a review.') }}</div>
    @endif

    @if($rvCanWrite)
        @php
            $rvPickedRating = (int) old('rating', $rvMine?->rating ?? 0);
            $rvOpen = $rvState === 'can_review' || $rvErrors->any();
        @endphp
        <div class="tr-rv__box" @if($rvState === 'reviewed') style="border-style:solid" @endif>
            @if($rvState === 'reviewed')
                <details class="tr-rv__edit" @if($rvErrors->any()) open @endif>
                    <summary>
                        <span class="tr-rv__mine">
                            <span><strong>{{ $rvT('Tu reseña', 'Your review') }}</strong> {!! $rvStars((float) $rvMine->rating) !!}</span>
                            <span>{{ $rvT('Editar mi reseña', 'Edit my review') }}</span>
                        </span>
                    </summary>
                    @if(!$rvMine->isPublished())
                        <p class="tr-rv__fine" style="margin-top:.6rem">{{ $rvT('La tienda ocultó tu reseña: por ahora solo tú puedes verla.', 'The store hid your review: for now only you can see it.') }}</p>
                    @endif
            @else
                <strong>{{ $rvT('Cuéntanos qué te pareció', 'Tell us what you thought') }}</strong>
            @endif

            <form method="POST" action="{{ $rvFormAction }}" class="tr-rv__form" data-rv-form novalidate>
                @csrf
                <fieldset>
                    <legend class="tr-rv__label">{{ $rvT('Tu calificación', 'Your rating') }}</legend>
                    <div class="tr-rv__pick" style="margin-top:.35rem">
                        @for($i = 5; $i >= 1; $i--)
                            <input type="radio" id="tr-rv-star-{{ $i }}" name="rating" value="{{ $i }}" @checked($rvPickedRating === $i)>
                            <label for="tr-rv-star-{{ $i }}" title="{{ $i }} {{ $rvT($i === 1 ? 'estrella' : 'estrellas', $i === 1 ? 'star' : 'stars') }}"><span aria-hidden="true">★</span><span class="sr-only" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)">{{ $i }} {{ $rvT($i === 1 ? 'estrella' : 'estrellas', $i === 1 ? 'star' : 'stars') }}</span></label>
                        @endfor
                    </div>
                    <p class="tr-rv__field-error" data-rv-rating-error @if(!$rvErrors->has('rating')) hidden @endif>{{ $rvErrors->first('rating') ?: $rvT('Elige una calificación de 1 a 5 estrellas.', 'Choose a rating from 1 to 5 stars.') }}</p>
                </fieldset>
                <div>
                    <label class="tr-rv__label" for="tr-rv-comment">{{ $rvT('Tu opinión', 'Your review') }} <small>({{ $rvT('opcional', 'optional') }})</small></label>
                    <textarea id="tr-rv-comment" name="comment" class="tr-rv__textarea" rows="4" maxlength="{{ \App\Models\ProductReview::MAX_COMMENT }}" placeholder="{{ $rvT('¿Qué tal la calidad, el envío, el tamaño…?', 'How was the quality, the shipping, the size…?') }}" style="margin-top:.35rem">{{ old('comment', $rvMine?->comment) }}</textarea>
                    @if($rvErrors->has('comment'))<p class="tr-rv__field-error">{{ $rvErrors->first('comment') }}</p>@endif
                </div>
                <div style="display:flex;flex-wrap:wrap;align-items:center;gap:.75rem 1rem">
                    <button type="submit" class="tr-rv__btn">{{ $rvState === 'reviewed' ? $rvT('Guardar cambios', 'Save changes') : $rvT('Publicar reseña', 'Post review') }}</button>
                    <span class="tr-rv__fine">{{ $rvT('Se publica al instante con tu nombre y la inicial de tu apellido.', 'Published instantly with your first name and last initial.') }}</span>
                </div>
            </form>
            @if($rvState === 'reviewed')
                </details>
            @endif
        </div>
    @endif

    @if($rvReviews->count() > 0)
        <ol class="tr-rv__list">
            @foreach($rvReviews as $rvReview)
                <li class="tr-rv__item" id="resena-{{ $rvReview->id }}">
                    <div class="tr-rv__top">
                        <div class="tr-rv__who">
                            <span class="tr-rv__avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($rvReview->reviewer_name, 0, 1)) }}</span>
                            <div style="min-width:0">
                                <p class="tr-rv__name">{{ $rvReview->reviewer_name }}</p>
                                <p class="tr-rv__meta">
                                    <span class="tr-rv__verified">✓ {{ $rvT('Compra verificada', 'Verified purchase') }}</span>
                                    <span class="tr-rv__dot" aria-hidden="true">·</span>
                                    <time datetime="{{ $rvReview->created_at->toIso8601String() }}" title="{{ $rvReview->created_at->format('d/m/Y') }}">{{ $rvAgo($rvReview->created_at) }}</time>
                                    @if($rvReview->edited_at)<span class="tr-rv__dot" aria-hidden="true">·</span><span>{{ $rvT('editada', 'edited') }}</span>@endif
                                </p>
                            </div>
                        </div>
                        {!! $rvStars((float) $rvReview->rating) !!}
                    </div>
                    @if($rvReview->comment)
                        <p class="tr-rv__text">{{ $rvReview->comment }}</p>
                    @endif
                    @if($rvReview->hasReply())
                        <div class="tr-rv__reply">
                            <p class="tr-rv__reply-head"><strong>{{ $rvT('Respuesta de', 'Reply from') }} {{ $store->name }}</strong>@if($rvReview->store_replied_at) · {{ $rvAgo($rvReview->store_replied_at) }}@endif</p>
                            <p class="tr-rv__reply-text">{{ $rvReview->store_reply }}</p>
                        </div>
                    @endif
                </li>
            @endforeach
        </ol>

        @if($rvReviews->hasPages())
            <nav class="tr-rv__pager" aria-label="{{ $rvT('Páginas de reseñas', 'Review pages') }}">
                @if($rvReviews->previousPageUrl())
                    <a class="tr-rv__btn tr-rv__btn--ghost" href="{{ $rvReviews->previousPageUrl() }}" rel="prev">← {{ $rvT('Reseñas más recientes', 'Newer reviews') }}</a>
                @endif
                @if($rvReviews->hasMorePages())
                    <a class="tr-rv__btn tr-rv__btn--ghost" href="{{ $rvReviews->nextPageUrl() }}" rel="next">{{ $rvT('Ver más reseñas', 'See more reviews') }} →</a>
                @endif
            </nav>
        @endif
    @endif
</div>
<script>
(function () {
    var section = document.getElementById('resenas');
    if (!section) return;

    // "Iniciar sesión": opens the store's Tribio Pass modal; once the buyer is in, reload
    // right here so the server can show the form (or why they can't review yet).
    var login = section.querySelector('[data-rv-login]');
    if (login) {
        login.addEventListener('click', function () {
            window.__rvLoginPending = true;
            if (typeof window.openCustomerModal === 'function') { window.openCustomerModal(); }
            else { window.location.href = login.getAttribute('data-fallback'); }
        });
        // Only when THIS button started the login — never interrupt a checkout login.
        window.addEventListener('customer-authenticated', function () {
            if (window.__rvLoginPending) { window.location.hash = 'resenas'; window.location.reload(); }
        });
    }

    // Friendly guard: no rating chosen → say so without a round trip (the server checks too).
    var form = section.querySelector('[data-rv-form]');
    if (form) {
        var error = form.querySelector('[data-rv-rating-error]');
        form.addEventListener('submit', function (event) {
            if (!form.querySelector('input[name="rating"]:checked')) {
                event.preventDefault();
                if (error) { error.hidden = false; }
                var first = form.querySelector('input[name="rating"]');
                if (first) { first.focus(); }
            }
        });
        form.addEventListener('change', function (event) {
            if (event.target.name === 'rating' && error) { error.hidden = true; }
        });
    }
})();
</script>
</section>
@endif
