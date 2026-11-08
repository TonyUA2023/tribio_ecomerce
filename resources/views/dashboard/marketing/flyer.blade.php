@extends('layouts.dashboard')
@section('title', 'Estudio de flyers')
@section('page_title', '🖼️ Estudio de flyers')
@section('content')
<style>
    /* El flyer se dibuja a tamaño real (1080 px de ancho) y se muestra escalado. */
    .flyer-stage { position: relative; width: 100%; overflow: hidden; border-radius: 16px; background: #0b0f14; }
    .flyer { position: absolute; top: 0; left: 0; transform-origin: 0 0; display: flex; flex-direction: column; overflow: hidden;
        font-family: "Arial Black", "Helvetica Neue", Arial, sans-serif; color: #fff; --p: #0f766e; --s: #111827; }
    .flyer * { box-sizing: border-box; }
    .flyer-head { display: flex; align-items: center; gap: 24px; padding: 56px 64px 0; }
    .flyer-logo { width: 112px; height: 112px; border-radius: 28px; object-fit: cover; background: #fff; }
    .flyer-store { font-size: 40px; line-height: 1.1; font-family: Arial, sans-serif; font-weight: 700; }
    .flyer-photo-wrap { flex: 1; min-height: 0; display: flex; align-items: center; justify-content: center; padding: 40px 64px; position: relative; }
    .flyer-photo { max-width: 100%; max-height: 100%; object-fit: contain; border-radius: 36px; background: #fff; padding: 24px; box-shadow: 0 30px 60px rgba(0,0,0,.25); }
    .flyer-name { font-size: 64px; line-height: 1.05; padding: 0 64px; }
    .flyer-tagline { font-family: Arial, sans-serif; font-size: 36px; padding: 16px 64px 0; opacity: .85; }
    .flyer-prices { display: flex; align-items: flex-end; gap: 28px; padding: 28px 64px 0; flex-wrap: wrap; }
    .flyer-price { font-size: 124px; line-height: .95; }
    .flyer-compare { font-family: Arial, sans-serif; font-size: 48px; text-decoration: line-through; opacity: .65; padding-bottom: 12px; }
    .flyer-badge { position: absolute; top: 28px; right: 72px; width: 230px; height: 230px; border-radius: 50%; display: flex; flex-direction: column;
        align-items: center; justify-content: center; font-size: 76px; line-height: 1; transform: rotate(-8deg); box-shadow: 0 20px 40px rgba(0,0,0,.25); }
    .flyer-badge small { font-family: Arial, sans-serif; font-size: 30px; font-weight: 700; margin-top: 6px; }
    .flyer-foot { display: flex; align-items: center; justify-content: space-between; gap: 24px; padding: 32px 64px 52px; font-family: Arial, sans-serif;
        font-size: 34px; font-weight: 700; margin-top: 36px; }
    .flyer-tiers { padding: 24px 64px 0; display: grid; gap: 16px; }
    .flyer-tier { display: flex; justify-content: space-between; align-items: center; border-radius: 24px; padding: 22px 36px; font-size: 48px; }
    .flyer-tier span:first-child { font-family: Arial, sans-serif; font-weight: 700; font-size: 38px; }
</style>

<div class="w-full max-w-[1600px] mx-auto" x-data="flyerStudio(@js($products), @js($brand), @js($selected), @js($formats), @js(route('dashboard.marketing.content.flyer.store')))">

    {{-- Barra de acciones fija --}}
    <div class="sticky top-0 z-20 -mx-1 sm:mx-0 mb-4">
        <div class="glass-card px-3.5 sm:px-5 py-2.5 sm:py-3 flex items-center gap-3">
            <a href="{{ route('dashboard.marketing.content.index') }}" class="btn-ghost !px-2.5 !py-2 flex-shrink-0" aria-label="Volver a Contenido con IA" title="Volver a Contenido con IA">←</a>
            <div class="min-w-0 flex-1">
                <p class="text-[10px] text-white/40 uppercase tracking-wider leading-none mb-0.5">Marketing · Contenido</p>
                <p class="text-white font-bold text-sm truncate" x-text="product.name"></p>
            </div>
            <button type="button" class="btn-secondary flex-shrink-0 text-xs sm:text-sm" @click="download" :disabled="busy">⬇️ <span class="hidden sm:inline">Descargar</span></button>
            <button type="button" class="btn-primary flex-shrink-0 !px-4 sm:!px-6 text-xs sm:text-sm" @click="save" :disabled="busy" x-text="busy ? 'Preparando…' : '💾 Guardar'"></button>
        </div>
    </div>
    <p class="text-xs mb-3" :class="error ? 'text-red-300' : 'text-tribio-cyan'" x-show="message" x-text="message" x-cloak role="status"></p>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-5">
        {{-- Vista previa primero en el DOM: en el celular es lo que importa --}}
        <div class="lg:col-span-3 lg:order-2">
            <div class="flyer-stage" x-ref="stage" :style="`aspect-ratio: ${size.width} / ${size.height}`">
                <div class="flyer" x-ref="flyer" :style="flyerStyle()">

                    {{-- Cabecera: logo y nombre de la tienda --}}
                    <div class="flyer-head">
                        <template x-if="brand.logo && showLogo"><img class="flyer-logo" :src="brand.logo" alt=""></template>
                        <div>
                            <div class="flyer-store" x-text="brand.name"></div>
                            <div x-show="brand.slogan" style="font-family: Arial, sans-serif; font-size: 28px; opacity: .75; margin-top: 6px" x-text="brand.slogan"></div>
                        </div>
                    </div>

                    {{-- Foto del producto --}}
                    <div class="flyer-photo-wrap">
                        <img class="flyer-photo" :src="product.image" alt="" crossorigin="anonymous">
                        <template x-if="template === 'oferta' && product.discount">
                            <div class="flyer-badge" :style="`background: ${brand.primary}; color: ${textOn(brand.primary)}`">
                                <span x-text="'-' + product.discount"></span><small>DESCUENTO</small>
                            </div>
                        </template>
                    </div>

                    <div class="flyer-name" x-text="product.name"></div>
                    <div class="flyer-tagline" x-show="tagline" x-text="tagline"></div>

                    {{-- Precios: siempre los del producto --}}
                    <template x-if="template !== 'mayorista'">
                        <div class="flyer-prices">
                            <div class="flyer-price" :style="`color: ${template === 'destacado' ? brand.primary : '#fff'}`" x-text="product.price"></div>
                            <div class="flyer-compare" x-show="template === 'oferta' && product.compare" x-text="'Antes ' + product.compare"></div>
                        </div>
                    </template>
                    <template x-if="template === 'mayorista'">
                        <div class="flyer-tiers">
                            <div class="flyer-tier" style="background: rgba(255,255,255,.14)"><span>1 unidad</span><span x-text="product.price"></span></div>
                            <template x-for="tier in product.tiers">
                                <div class="flyer-tier" :style="`background: #fff; color: ${brand.secondary}`"><span x-text="tier.label"></span><span x-text="tier.price"></span></div>
                            </template>
                        </div>
                    </template>

                    {{-- Pie: WhatsApp y web --}}
                    <div class="flyer-foot" :style="footStyle()">
                        <span x-text="brand.whatsapp && showWhatsapp ? '📲 ' + brand.whatsapp : 'Pide ya'"></span>
                        <span style="font-weight: 400; opacity: .85" x-text="brand.domain"></span>
                    </div>
                </div>
            </div>
            <p class="text-[11px] text-white/40 mt-2" x-text="`${size.width} × ${size.height} px · listo para ${formats[format].label.toLowerCase()}`"></p>
        </div>

        {{-- Controles --}}
        <div class="lg:col-span-2 lg:order-1 space-y-4">
            <div class="glass-card p-4 sm:p-5 space-y-4">
                <div>
                    <label class="input-label" for="flyer-product">Producto</label>
                    <select id="flyer-product" class="input-field" x-model.number="productId" @change="fitTemplate()">
                        <template x-for="item in products" :key="item.id">
                            <option :value="item.id" x-text="item.name" :selected="item.id === productId"></option>
                        </template>
                    </select>
                </div>

                <div>
                    <p class="input-label">Plantilla</p>
                    <div class="flex flex-wrap gap-2 mt-1">
                        @foreach($templates as $key => $label)
                            <button type="button" @click="template = '{{ $key }}'" x-show="product.templates.includes('{{ $key }}')"
                                class="px-3 py-1.5 rounded-full text-xs border transition"
                                :class="template === '{{ $key }}' ? 'border-tribio-cyan text-tribio-cyan bg-tribio-cyan/10' : 'border-white/15 text-white/60'">{{ $label }}</button>
                        @endforeach
                    </div>
                    <p class="text-[11px] text-white/40 mt-1" x-show="!product.templates.includes('oferta')">“Oferta” aparece cuando el producto tiene precio anterior; “Precio por mayor”, cuando tiene precios por mayor.</p>
                </div>

                <div>
                    <p class="input-label">Formato</p>
                    <div class="flex flex-wrap gap-2 mt-1">
                        @foreach($formats as $key => $format)
                            <button type="button" @click="format = '{{ $key }}'; $nextTick(() => fit())"
                                class="px-3 py-1.5 rounded-full text-xs border transition"
                                :class="format === '{{ $key }}' ? 'border-tribio-cyan text-tribio-cyan bg-tribio-cyan/10' : 'border-white/15 text-white/60'">{{ $format['label'] }}</button>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label class="input-label" for="flyer-tagline">Frase corta <span class="text-white/40 font-normal">(opcional)</span></label>
                    <input id="flyer-tagline" class="input-field" maxlength="60" x-model="tagline" placeholder="Ej.: Ideal para regalar">
                    <p class="text-[11px] text-white/40 mt-1">Los precios no se escriben aquí: salen de tu producto. Para cambiarlos, edita el producto.</p>
                </div>

                <div class="flex flex-col gap-2">
                    <label class="flex items-center gap-2 text-sm text-white/70" x-show="brand.logo"><input type="checkbox" x-model="showLogo"> Mostrar mi logo</label>
                    <label class="flex items-center gap-2 text-sm text-white/70" x-show="brand.whatsapp"><input type="checkbox" x-model="showWhatsapp"> Mostrar mi WhatsApp</label>
                </div>
            </div>
            <p class="text-[11px] text-white/40">Colores, eslogan y WhatsApp vienen de tu <a href="{{ route('dashboard.marketing.content.index') }}" class="underline">kit de marca</a>.</p>
        </div>
    </div>
</div>

<script>
function flyerStudio(products, brand, selected, formats, saveUrl) {
    return {
        products, brand, formats,
        productId: selected, template: 'destacado', format: 'square', tagline: '',
        showLogo: true, showWhatsapp: true, busy: false, message: '', error: false, scale: 0.5,
        get product() { return this.products.find(item => item.id === this.productId) || this.products[0]; },
        get size() { return this.formats[this.format]; },
        init() {
            this.fitTemplate();
            this.$nextTick(() => this.fit());
            new ResizeObserver(() => this.fit()).observe(this.$refs.stage);
        },
        fitTemplate() {
            const available = this.product.templates;
            if (!available.includes(this.template)) this.template = available.includes('oferta') ? 'oferta' : 'destacado';
        },
        fit() { this.scale = this.$refs.stage.clientWidth / this.size.width; },
        flyerStyle() {
            const light = this.template === 'destacado';
            const background = this.template === 'mayorista'
                ? `linear-gradient(160deg, ${this.brand.primary} 0%, ${this.brand.secondary} 100%)`
                : light ? `linear-gradient(180deg, #ffffff 0%, #f3f4f6 100%)` : `radial-gradient(circle at 30% 20%, ${this.brand.primary} 0%, ${this.brand.secondary} 70%)`;
            return `width:${this.size.width}px;height:${this.size.height}px;transform:scale(${this.scale});background:${background};color:${light ? this.brand.secondary : '#fff'}`;
        },
        footStyle() {
            return `background:${this.template === 'destacado' ? this.brand.primary : 'rgba(0,0,0,.28)'};color:${this.template === 'destacado' ? this.textOn(this.brand.primary) : '#fff'}`;
        },
        // Texto blanco u oscuro según la luminosidad del color de fondo.
        textOn(hex) {
            const [r, g, b] = [1, 3, 5].map(i => parseInt(hex.slice(i, i + 2), 16));
            return (0.299 * r + 0.587 * g + 0.114 * b) > 165 ? '#111827' : '#ffffff';
        },
        async render() {
            this.busy = true; this.message = ''; this.error = false;
            try {
                return await window.tribioExportFlyer(this.$refs.flyer, this.size.width, this.size.height);
            } catch (e) {
                this.error = true;
                this.message = 'No pudimos preparar la imagen. Recarga la página e inténtalo de nuevo.';
                return null;
            } finally {
                this.busy = false;
            }
        },
        fileName() { return `flyer-${this.product.name}`.toLowerCase().normalize('NFD').replace(/[^a-z0-9]+/g, '-').replace(/-+$/, '') + '.png'; },
        async download() {
            const blob = await this.render();
            if (!blob) return;
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = this.fileName();
            link.click();
            setTimeout(() => URL.revokeObjectURL(link.href), 4000);
        },
        async save() {
            const blob = await this.render();
            if (!blob) return;
            this.busy = true;
            const body = new FormData();
            body.append('image', blob, this.fileName());
            body.append('product_id', this.product.id);
            body.append('template', this.template);
            body.append('format', this.format);
            try {
                const res = await fetch(saveUrl, { method: 'POST', body, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content } });
                const data = await res.json().catch(() => ({}));
                this.error = !res.ok;
                this.message = res.ok ? 'Guardado en tu biblioteca.' : (data.message || 'No pudimos guardarlo. Inténtalo de nuevo.');
            } catch (e) {
                this.error = true;
                this.message = 'Sin conexión. Revisa tu internet e inténtalo de nuevo.';
            } finally {
                this.busy = false;
            }
        },
    };
}
</script>
@endsection
