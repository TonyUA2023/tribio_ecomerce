@extends('layouts.dashboard')
@section('title', 'Contenido con IA')
@section('page_title', '✨ Contenido con IA')
@section('content')
@php
    use App\Models\BrandKit;
    use App\Services\AiMarketing\CopyGenerator;
    $usedPercent = $allowance > 0 ? min(100, round(($allowance - $remaining) * 100 / $allowance)) : 100;
@endphp
<div class="w-full max-w-5xl mx-auto space-y-5 sm:space-y-6">

    <div class="flex items-center justify-between gap-3 flex-wrap">
        <p class="text-sm text-white/60 max-w-2xl">Textos para tus redes escritos con IA en la voz de tu marca, y flyers listos para publicar. Los precios siempre salen de tus productos, nunca los inventa la IA.</p>
        <a href="{{ route('dashboard.marketing.index') }}" class="text-xs text-tribio-cyan underline">← Volver a Marketing</a>
    </div>

    @unless($ready)
    <div class="glass-card p-4 sm:p-5">
        <p class="text-white font-bold text-sm">⏳ Estamos activando este módulo</p>
        <p class="text-xs text-white/50 mt-1">Falta una actualización en el servidor de Tribio. Apenas esté lista podrás usarlo aquí; no tienes que hacer nada.</p>
    </div>
    @else

    {{-- Créditos --}}
    <div class="glass-card p-4 sm:p-5">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <p class="text-[11px] text-white/40 uppercase tracking-wider">Créditos de IA de este mes</p>
                <p class="text-white font-bold text-lg mt-0.5"><span data-credits-remaining>{{ $remaining }}</span> <span class="text-white/40 font-normal text-sm">de {{ $allowance }} disponibles</span></p>
            </div>
            <p class="text-[11px] text-white/40">Cada texto generado usa 1 crédito · se renuevan el día 1</p>
        </div>
        <div class="h-1.5 rounded-full bg-white/10 mt-3 overflow-hidden"><div class="h-full bg-tribio-cyan" style="width: {{ 100 - $usedPercent }}%"></div></div>
        @unless($aiAvailable)
            <p class="text-xs text-white/50 mt-3">⏳ Estamos activando el asistente de IA. Mientras tanto ya puedes crear flyers y configurar tu kit de marca.</p>
        @endunless
    </div>

    {{-- Textos con IA --}}
    <div class="glass-card p-5 sm:p-6" x-data="copyStudio(@js(route('dashboard.marketing.content.copy')))">
        <h3 class="text-white font-bold text-base">✍️ Escribir textos para redes</h3>
        <p class="text-xs text-white/50 mt-1">Elige un producto y para qué lo quieres. Te damos 2 versiones por red, listas para copiar.</p>

        <form class="mt-4 space-y-4" @submit.prevent="generate">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="input-label" for="copy-product">Producto</label>
                    <select id="copy-product" class="input-field" x-model="form.product_id">
                        <option value="">Toda mi tienda (sin producto)</option>
                        @foreach($products as $product)
                            <option value="{{ $product->id }}">{{ $product->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="input-label" for="copy-objective">¿Para qué es?</label>
                    <select id="copy-objective" class="input-field" x-model="form.objective">
                        @foreach(CopyGenerator::OBJECTIVES as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <p class="input-label">¿Dónde lo vas a publicar? <span class="text-white/40 font-normal">(hasta 3)</span></p>
                <div class="flex flex-wrap gap-2 mt-1">
                    @foreach(CopyGenerator::CHANNELS as $key => $label)
                        <button type="button" @click="toggle('{{ $key }}')"
                            class="px-3 py-1.5 rounded-full text-xs border transition"
                            :class="form.channels.includes('{{ $key }}') ? 'border-tribio-cyan text-tribio-cyan bg-tribio-cyan/10' : 'border-white/15 text-white/60'"
                            :aria-pressed="form.channels.includes('{{ $key }}')">{{ $label }}</button>
                    @endforeach
                </div>
            </div>

            <div>
                <label class="input-label" for="copy-notes">Indicaciones <span class="text-white/40 font-normal">(opcional)</span></label>
                <textarea id="copy-notes" class="input-field" rows="2" maxlength="400" x-model="form.notes" placeholder="Ej.: es para el Día de la Madre, resalta que es ideal para regalar"></textarea>
            </div>

            <div class="flex items-center gap-3 flex-wrap">
                <button type="submit" class="btn-primary text-xs sm:text-sm" :disabled="loading || !{{ $aiAvailable ? 'true' : 'false' }}">
                    <span x-show="!loading">Escribir con IA · 1 crédito</span>
                    <span x-show="loading" x-cloak>Escribiendo…</span>
                </button>
                <p class="text-xs text-red-300" x-show="error" x-text="error" x-cloak role="alert"></p>
            </div>
        </form>

        <div class="mt-5 space-y-3" x-show="items.length" x-cloak>
            <template x-for="(item, index) in items" :key="index">
                <div class="rounded-xl border border-white/10 bg-white/5 p-4">
                    <div class="flex items-center justify-between gap-2">
                        <span class="badge badge-blue" x-text="channelLabel(item.channel)"></span>
                        <button type="button" class="btn-ghost text-xs" @click="copy(item, index)" x-text="copied === index ? '¡Copiado!' : 'Copiar'"></button>
                    </div>
                    <p class="text-sm text-white mt-2 whitespace-pre-line" x-text="item.text"></p>
                    <p class="text-xs text-white/60 mt-2" x-show="item.cta"><strong>Llamado:</strong> <span x-text="item.cta"></span></p>
                    <p class="text-xs text-tribio-cyan mt-1" x-show="item.hashtags.length" x-text="item.hashtags.map(tag => '#' + tag).join(' ')"></p>
                    <template x-for="warning in item.warnings">
                        <p class="text-xs mt-2"><span class="badge badge-gold">Revisa</span> <span class="text-white/70" x-text="warning"></span></p>
                    </template>
                </div>
            </template>
        </div>
    </div>

    {{-- Flyers --}}
    <div class="glass-card p-5 sm:p-6">
        <div class="flex items-start justify-between gap-3 flex-wrap">
            <div class="min-w-0">
                <h3 class="text-white font-bold text-base">🖼️ Crear un flyer</h3>
                <p class="text-xs text-white/50 mt-1">Ofertas, productos destacados y precios por mayor en formato post, vertical o historia. Con tu logo, tus colores y tu WhatsApp.</p>
            </div>
            @if($products->isNotEmpty())
                <a href="{{ route('dashboard.marketing.content.flyer') }}" class="btn-primary inline-flex text-xs sm:text-sm">Abrir el estudio de flyers</a>
            @else
                <a href="{{ route('dashboard.productos.create') }}" class="btn-secondary inline-flex text-xs sm:text-sm">Primero crea un producto</a>
            @endif
        </div>
    </div>

    {{-- Kit de marca --}}
    <div class="glass-card p-5 sm:p-6" x-data="{ open: {{ $kit->exists ? 'false' : 'true' }} }">
        <button type="button" class="w-full flex items-center justify-between gap-3 text-left" @click="open = !open" :aria-expanded="open">
            <span>
                <span class="text-white font-bold text-base block">🎨 Kit de marca</span>
                <span class="text-xs text-white/50">{{ $kit->exists ? 'Tono: ' . (BrandKit::TONES[$kit->tone] ?? $kit->tone) : 'Cuéntanos cómo habla tu marca para que los textos suenen a ti.' }}</span>
            </span>
            <span class="text-white/40 text-sm" x-text="open ? 'Cerrar' : 'Editar'"></span>
        </button>

        <form method="POST" action="{{ route('dashboard.marketing.content.brand') }}" class="mt-4 space-y-4" x-show="open" x-cloak>
            @csrf @method('PUT')
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="input-label" for="kit-tone">Tono</label>
                    <select id="kit-tone" name="tone" class="input-field">
                        @foreach(BrandKit::TONES as $key => $label)
                            <option value="{{ $key }}" @selected(old('tone', $kit->tone) === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="input-label" for="kit-slogan">Eslogan <span class="text-white/40 font-normal">(opcional)</span></label>
                    <input id="kit-slogan" name="slogan" class="input-field" maxlength="120" value="{{ old('slogan', $kit->slogan) }}" placeholder="Ej.: Calidad que se nota">
                </div>
            </div>
            <div>
                <label class="input-label" for="kit-audience">¿A quién le vendes? <span class="text-white/40 font-normal">(opcional)</span></label>
                <input id="kit-audience" name="audience" class="input-field" maxlength="200" value="{{ old('audience', $kit->audience) }}" placeholder="Ej.: mamás jóvenes de Lima que compran por WhatsApp">
            </div>
            <div>
                <label class="input-label" for="kit-banned">Palabras que nunca debe usar <span class="text-white/40 font-normal">(separadas por coma)</span></label>
                <input id="kit-banned" name="banned_words_text" class="input-field" value="{{ old('banned_words_text', implode(', ', $kit->banned_words ?? [])) }}" placeholder="Ej.: barato, milagroso, garantizado">
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 items-end">
                <div>
                    <label class="input-label" for="kit-primary">Color principal</label>
                    <input id="kit-primary" type="color" name="primary_color" class="h-10 w-full rounded-lg border border-white/10 bg-transparent" value="{{ old('primary_color', $kit->primary_color) }}">
                </div>
                <div>
                    <label class="input-label" for="kit-secondary">Color secundario</label>
                    <input id="kit-secondary" type="color" name="secondary_color" class="h-10 w-full rounded-lg border border-white/10 bg-transparent" value="{{ old('secondary_color', $kit->secondary_color) }}">
                </div>
                <label class="flex items-center gap-2 text-sm text-white/70 col-span-2 sm:col-span-1">
                    <input type="checkbox" name="show_whatsapp" value="1" @checked(old('show_whatsapp', $kit->show_whatsapp))>
                    Mostrar mi WhatsApp en los flyers
                </label>
            </div>
            <button type="submit" class="btn-primary text-xs sm:text-sm">Guardar kit de marca</button>
        </form>
    </div>

    {{-- Biblioteca --}}
    <div class="glass-card p-5 sm:p-6">
        <h3 class="text-white font-bold text-base">📚 Tu biblioteca</h3>
        @if($assets->isEmpty())
            <p class="text-sm text-white/60 mt-2">Aquí aparecerán los textos y flyers que crees.</p>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3">
                @foreach($assets as $asset)
                <div class="rounded-xl border border-white/10 bg-white/5 p-3 flex gap-3">
                    @if($asset->kind === 'flyer' && $asset->file_url)
                        <a href="{{ $asset->file_url }}" target="_blank" rel="noopener" class="shrink-0"><img src="{{ $asset->file_url }}" alt="Flyer de {{ $asset->title }}" class="w-20 h-20 object-cover rounded-lg"></a>
                    @endif
                    <div class="min-w-0 flex-1">
                        <p class="text-sm text-white font-semibold truncate">{{ $asset->kind === 'flyer' ? '🖼️' : '✍️' }} {{ $asset->title }}</p>
                        <p class="text-[11px] text-white/40">{{ $asset->created_at->locale('es')->diffForHumans() }}</p>
                        @if($asset->kind === 'copy')
                            <p class="text-xs text-white/60 mt-1 line-clamp-3">{{ $asset->content['items'][0]['text'] ?? '' }}</p>
                        @endif
                        <div class="flex items-center gap-3 mt-2">
                            @if($asset->file_url)<a href="{{ $asset->file_url }}" download class="text-xs text-tribio-cyan underline">Descargar</a>@endif
                            <form method="POST" action="{{ route('dashboard.marketing.content.assets.destroy', $asset) }}" onsubmit="return confirm('¿Quitar de tu biblioteca?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs text-white/40 hover:text-red-300">Quitar</button>
                            </form>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        @endif
    </div>
    @endunless
</div>

<script>
function copyStudio(url) {
    const labels = @js(CopyGenerator::CHANNELS);
    return {
        form: { product_id: '', objective: 'oferta', channels: ['instagram'], notes: '' },
        items: [], loading: false, error: '', copied: null,
        toggle(channel) {
            const i = this.form.channels.indexOf(channel);
            if (i >= 0) this.form.channels.splice(i, 1);
            else if (this.form.channels.length < 3) this.form.channels.push(channel);
        },
        channelLabel(channel) { return labels[channel] || channel; },
        async generate() {
            this.loading = true; this.error = '';
            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ ...this.form, product_id: this.form.product_id || null }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    this.error = data.message || 'No pudimos escribir el texto. Inténtalo de nuevo.';
                    if (data.errors) this.error = Object.values(data.errors)[0][0];
                    return;
                }
                this.items = data.items;
                document.querySelectorAll('[data-credits-remaining]').forEach(el => el.textContent = data.remaining);
            } catch (e) {
                this.error = 'Sin conexión. Revisa tu internet e inténtalo de nuevo.';
            } finally {
                this.loading = false;
            }
        },
        async copy(item, index) {
            const text = [item.text, item.hashtags.map(tag => '#' + tag).join(' ')].filter(Boolean).join('\n\n');
            await navigator.clipboard.writeText(text);
            this.copied = index;
            setTimeout(() => { if (this.copied === index) this.copied = null; }, 1800);
        },
    };
}
</script>
@endsection
