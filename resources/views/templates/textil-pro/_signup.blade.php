{{-- Pestaña flotante "Regístrate": abre el registro de Tribio Pass. Se puede cerrar y no vuelve
     a aparecer en esa visita. --}}
@if(($templatePreview ?? false) || $storefrontTheme->enabled('signup.enabled'))
<div class="tx-signup" data-tpl-show="signup.enabled" @unless($storefrontTheme->enabled('signup.enabled')) hidden @endunless
     x-data="{ closed: false, init() { try { this.closed = sessionStorage.getItem('tx-signup-{{ $store->id }}') === '1'; } catch (e) {} } }"
     x-show="!closed" x-cloak>
    <button type="button" class="tx-signup-main" @click="window.openCustomerModal ? window.openCustomerModal('register') : $dispatch('open-customer-modal')">
        <span data-tpl-text="signup.text">{{ $storefrontTheme->text('signup.text') }}</span>
    </button>
    <button type="button" class="tx-signup-x" @click="closed = true; try { sessionStorage.setItem('tx-signup-{{ $store->id }}', '1'); } catch (e) {}" aria-label="{{ \App\Helpers\TranslationHelper::isEn() ? 'Close' : 'Cerrar' }}">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
    </button>
</div>
@endif
