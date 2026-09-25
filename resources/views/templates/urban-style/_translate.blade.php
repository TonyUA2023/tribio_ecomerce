{{-- Traducción al inglés (Google Translate) solo si la tienda activó "multi-idioma".
     Si no, se borra cualquier cookie googtrans que otra tienda del mismo dominio haya
     dejado: sin esto la página se traducía sola al inglés y no había selector para volver. --}}
@unless($templatePreview ?? false)
<script>
    // Borra googtrans en la ruta y en cada dominio padre (Google la escribe en ambos).
    window.usClearGoogTrans = function () {
        const parts = window.location.hostname.split('.');
        const expire = '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; SameSite=Lax';
        document.cookie = 'googtrans' + expire;
        for (let i = 0; i < parts.length - 1; i++) {
            const domain = parts.slice(i).join('.');
            document.cookie = 'googtrans' + expire + '; domain=' + domain;
            document.cookie = 'googtrans' + expire + '; domain=.' + domain;
        }
    };
</script>
@if($store->is_multilanguage_enabled)
    <div id="google_translate_element" style="display:none;"></div>
    <script type="text/javascript">
        function googleTranslateElementInit() {
            new google.translate.TranslateElement({pageLanguage: 'es', includedLanguages: 'en,es', autoDisplay: false}, 'google_translate_element');
        }
        // El servidor ya decidió el idioma (cookie store_lang): en español no debe quedar
        // ninguna googtrans vieja que haga que Google vuelva a traducir la página.
        @unless(\App\Helpers\TranslationHelper::isEn()) window.usClearGoogTrans(); @endunless
    </script>
    <script type="text/javascript" src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>
@else
    <script>window.usClearGoogTrans();</script>
@endif
@endunless
