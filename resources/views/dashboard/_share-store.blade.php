{{-- "Comparte tu tienda": enlace listo para copiar, botones de WhatsApp y Facebook, y los tres pasos
     para aparecer en Google. El enlace compartido muestra foto y descripción gracias a las etiquetas
     de App\Services\Seo\StoreSeo. --}}
@php
    $__shareStore = $store ?? Auth::user()->currentStore();
    $__shareUrl = $__shareStore ? rtrim($__shareStore->url, '/') : null;
    $__shareText = $__shareStore ? "¡Hola! Ya puedes ver el catálogo de {$__shareStore->name} y hacer tu pedido en línea 🛍️\n{$__shareUrl}" : '';
@endphp
@if($__shareStore && $__shareStore->status === 'active')
<section class="dash-section" aria-labelledby="share-store-heading" x-data="{ copied: '', copy(text, key) { navigator.clipboard?.writeText(text).then(() => { this.copied = key; setTimeout(() => this.copied = '', 2000); }); } }">
    <div class="glass-card p-5 sm:p-6 space-y-4">
        <div>
            <h2 id="share-store-heading" class="text-base font-bold text-white">📣 Comparte tu tienda y empieza a vender</h2>
            <p class="text-xs text-white/50 mt-1">Los pedidos llegan cuando la gente conoce tu enlace. Envíalo a tus clientes, ponlo en tu Instagram o en tu estado de WhatsApp.</p>
        </div>
        <div class="flex flex-col sm:flex-row gap-2">
            <input type="text" readonly value="{{ $__shareUrl }}" class="input-field flex-1 text-sm" aria-label="Enlace de tu tienda" @focus="$event.target.select()">
            <button type="button" class="btn-secondary py-2 px-4 text-xs" @click="copy('{{ $__shareUrl }}', 'link')" x-text="copied === 'link' ? '✓ Copiado' : 'Copiar enlace'"></button>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="https://wa.me/?text={{ rawurlencode($__shareText) }}" target="_blank" rel="noopener" class="btn-primary py-2 px-4 text-xs">Compartir por WhatsApp</a>
            <a href="https://www.facebook.com/sharer/sharer.php?u={{ rawurlencode($__shareUrl) }}" target="_blank" rel="noopener" class="btn-secondary py-2 px-4 text-xs">Facebook</a>
            <a href="{{ $__shareUrl }}" target="_blank" rel="noopener" class="btn-secondary py-2 px-4 text-xs">Ver mi tienda</a>
        </div>
        <details class="text-xs text-white/60">
            <summary class="cursor-pointer font-bold text-white/80">¿Cómo aparezco en Google?</summary>
            <ol class="list-decimal pl-5 mt-2 space-y-1.5">
                <li>Entra a <strong>Google Search Console</strong> (search.google.com/search-console) con tu cuenta de Google y agrega tu tienda como propiedad.</li>
                <li>Pega este mapa del sitio en "Sitemaps": <code class="text-white/80">{{ $__shareUrl }}/sitemap.xml</code>
                    <button type="button" class="underline ml-1" @click="copy('{{ $__shareUrl }}/sitemap.xml', 'sitemap')" x-text="copied === 'sitemap' ? '✓ Copiado' : 'Copiar'"></button></li>
                <li>En "Inspección de URL" pega el enlace de tu tienda y pulsa <strong>Solicitar indexación</strong>.</li>
            </ol>
            <p class="mt-2 text-white/40">Google puede tardar de unos días a algunas semanas en mostrar tu tienda. Mientras tanto, lo que más ayuda son los enlaces desde tus redes y un título y descripción claros en <a href="{{ route('dashboard.store.edit') }}" class="underline">Mi tienda</a>.</p>
        </details>
    </div>
</section>
@endif
