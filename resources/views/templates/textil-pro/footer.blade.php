{{-- Textil Pro footer. La suscripción y el Libro de Reclamaciones envían de verdad: ambos usan el
     formulario de contacto de la tienda (StoreController::submitContact), que guarda el mensaje
     para el dueño. Nada aquí simula un envío. --}}
@php
    $isEn = \App\Helpers\TranslationHelper::isEn();
    $t = $storefrontTheme;
    $footerCategories = ($categories ?? $store->categories()->whereNull('parent_id')->get())->take(6);
    $phoneClean = preg_replace('/[^0-9]/', '', $store->whatsapp_phone ?? $store->contact_phone ?? '');
    $contactUrl = route('store.contact', $store->slug);
    $socials = array_filter([
        'facebook' => $store->facebook_url,
        'instagram' => $store->instagram_url,
        'tiktok' => $store->tiktok_url,
        'whatsapp' => $phoneClean ? 'https://wa.me/' . $phoneClean : null,
    ]);
@endphp
<footer class="tx-footer">
    <div class="tx-wrap tx-footer-grid">
        <div>
            <h4>{{ $isEn ? 'Help' : 'Ayuda' }}</h4>
            <ul>
                <li><a href="{{ $contactUrl }}">{{ $isEn ? 'Help center' : 'Centro de ayuda' }}</a></li>
                <li><button type="button" @click="window.openCustomerModal ? window.openCustomerModal('orders') : $dispatch('open-customer-modal')">{{ $isEn ? 'Track my order' : 'Rastrear mi pedido' }}</button></li>
                <li><button type="button" @click="window.openCustomerModal ? window.openCustomerModal('login') : $dispatch('open-customer-modal')">{{ $isEn ? 'My account' : 'Mi cuenta' }}</button></li>
                @if($phoneClean)
                    <li><a href="https://wa.me/{{ $phoneClean }}" target="_blank" rel="noopener">{{ $isEn ? 'WhatsApp support' : 'Atención por WhatsApp' }}</a></li>
                @endif
            </ul>
            <a class="tx-libro" href="{{ route('store.contact', ['slug' => $store->slug, 'libro' => 1]) }}">
                {{ $isEn ? 'Complaints book' : 'Libro de reclamaciones' }}
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 4h11l3 3v13H5zM9 9h6M9 13h6M9 17h3"/></svg>
            </a>
        </div>
        <div>
            <h4>{{ $isEn ? 'Shop' : 'Comprar' }}</h4>
            <ul>
                @foreach($footerCategories as $cat)
                    <li><a href="{{ route('store.catalog', ['slug' => $store->slug, 'category' => $cat->slug]) }}">{{ $cat->getTranslatedName() }}</a></li>
                @endforeach
                <li><a href="{{ route('store.catalog', ['slug' => $store->slug, 'sort' => 'newest']) }}">{{ $isEn ? 'New in' : 'Novedades' }}</a></li>
                <li><a href="{{ route('store.catalog', ['slug' => $store->slug, 'on_sale' => 1]) }}">{{ $isEn ? 'Sale' : 'Ofertas' }}</a></li>
            </ul>
        </div>
        <div>
            <h4>{{ $isEn ? 'About' : 'Acerca de' }} {{ $store->name }}</h4>
            <p class="tx-footer-about" data-tpl-text="footer.about">{{ $t->text('footer.about') }}</p>
            <ul>
                <li><a href="{{ $contactUrl }}">{{ $isEn ? 'Contact' : 'Contacto' }}</a></li>
                <li><a href="{{ route('store.gallery', $store->slug) }}">{{ $isEn ? 'Lookbook' : 'Galería' }}</a></li>
            </ul>
        </div>
        <div class="tx-news" x-data="txNewsletter(@js(route('store.contact.submit', $store->slug)))">
            <h4 data-tpl-text="footer.newsletter_title">{{ $t->text('footer.newsletter_title') }}</h4>
            <p data-tpl-text="footer.newsletter_text">{{ $t->text('footer.newsletter_text') }}</p>
            <form @submit.prevent="submit()" x-show="!done" novalidate>
                <label class="tx-sr" for="tx-news-email">{{ $isEn ? 'Your email' : 'Tu correo electrónico' }}</label>
                <input id="tx-news-email" type="email" x-model="email" placeholder="{{ $isEn ? 'Enter your email' : 'Ingresa tu correo electrónico' }}" autocomplete="email" required>
                <button type="submit" :disabled="sending">{{ $isEn ? 'Subscribe' : 'Suscribirme' }}</button>
            </form>
            <p class="tx-news-ok" x-show="done" x-cloak role="status">{{ $isEn ? 'Done! You will hear from us soon.' : '¡Listo! Te escribiremos con las novedades.' }}</p>
            <p class="tx-news-ok" style="color: #FF9C9C" x-show="error" x-text="error" x-cloak role="alert"></p>
            @if($socials)
                <div class="tx-social">
                    @foreach($socials as $network => $url)
                        <a href="{{ $url }}" target="_blank" rel="noopener" aria-label="{{ ucfirst($network) }}">
                            @switch($network)
                                @case('facebook')<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-1.6 19.9v-7H7.9V12h2.5V9.8c0-2.5 1.5-3.9 3.8-3.9 1.1 0 2.2.2 2.2.2v2.5h-1.3c-1.2 0-1.6.8-1.6 1.6V12h2.8l-.4 2.9h-2.3v7A10 10 0 0 0 12 2Z"/></svg>@break
                                @case('instagram')<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor"/></svg>@break
                                @case('tiktok')<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M16.6 3c.4 2.2 1.8 3.7 4 3.9v3.2a7.4 7.4 0 0 1-4-1.2v6.5a6.1 6.1 0 1 1-6.1-6.1c.3 0 .7 0 1 .1v3.3a2.9 2.9 0 1 0 2 2.7V3h3.1Z"/></svg>@break
                                @default<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm5.3 14.1c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .3-3.4-.7-2.9-1.2-4.7-4.1-4.8-4.3-.1-.2-1.1-1.5-1.1-2.9s.7-2.1 1-2.4c.3-.3.6-.3.8-.3h.6c.2 0 .4 0 .6.5l.9 2.1c.1.2.1.4 0 .5l-.3.5-.4.5c-.1.1-.3.3-.1.6.2.3.7 1.2 1.6 2 1.1.9 2 1.2 2.3 1.4.3.1.5.1.6-.1l.9-1.1c.2-.3.4-.2.7-.1l2 1c.3.1.5.2.5.3.1.2.1.7-.1 1.3Z"/></svg>
                            @endswitch
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
    <div class="tx-footer-bottom">
        <div class="tx-wrap">
            <div style="display: grid; gap: 8px">
                <nav>
                    <a href="{{ $contactUrl }}">{{ $isEn ? 'Contact' : 'Contacto' }}</a>
                    <a href="{{ route('store.contact', ['slug' => $store->slug, 'libro' => 1]) }}">{{ $isEn ? 'Complaints book' : 'Libro de reclamaciones' }}</a>
                </nav>
                <span class="tx-footer-copy">© {{ now()->year }} {{ $store->name }}. {{ $isEn ? 'All rights reserved.' : 'Todos los derechos reservados.' }} · {{ $isEn ? 'Powered by' : 'Tienda creada con' }} Tribio</span>
            </div>
            <div class="tx-pay" aria-label="{{ $isEn ? 'Payment methods' : 'Medios de pago' }}">
                <span>VISA</span><span style="color: #EB001B">MC</span><span style="color: #2E77BB">AMEX</span><span style="color: #7B2D8E">YAPE</span><span style="color: #00A5A8">PLIN</span>
            </div>
        </div>
    </div>
</footer>

@once
<script>
    document.addEventListener('alpine:init', () => {
        // Suscripción real: se guarda como mensaje de contacto de la tienda.
        Alpine.data('txNewsletter', (url) => ({
            email: '', sending: false, done: false, error: '',
            async submit() {
                this.error = '';
                if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(this.email)) {
                    this.error = @js(\App\Helpers\TranslationHelper::isEn() ? 'Enter a valid email.' : 'Escribe un correo válido.');
                    return;
                }
                this.sending = true;
                try {
                    const body = new FormData();
                    body.append('_token', document.querySelector('meta[name=csrf-token]').content);
                    body.append('name', this.email.split('@')[0]);
                    body.append('email', this.email);
                    body.append('subject', 'Suscripción a novedades');
                    body.append('message', 'Quiero recibir novedades, lanzamientos y ofertas de la tienda.');
                    const res = await fetch(url, { method: 'POST', body, headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                    if (!res.ok && res.status !== 302) throw new Error(String(res.status));
                    this.done = true;
                } catch (e) {
                    this.error = @js(\App\Helpers\TranslationHelper::isEn() ? 'We could not subscribe you. Try again.' : 'No pudimos suscribirte. Inténtalo otra vez.');
                } finally {
                    this.sending = false;
                }
            },
        }));
    });
</script>
@endonce
