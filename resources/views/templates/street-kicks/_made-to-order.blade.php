{{-- Buyer customization for a made-to-order product. Lives inside the product page's
     x-data (reads currentPrice / currentVariant from it). Everything here is re-validated
     and re-priced server-side by CustomizationSchema::evaluate() at checkout. --}}
@php
    $mtoCurrency = \App\Helpers\CurrencyHelper::currentCurrency();
    $mtoConfig = [
        'productId' => $product->id,
        'name' => $product->name,
        'image' => $product->image_url,
        'basePrice' => (float) $product->resolvePrice(),
        'schema' => $product->customization_schema ?? [],
        'rate' => $mtoCurrency === 'PEN' ? 1 : app(\App\Services\ExchangeRateService::class)->getRate($mtoCurrency),
        'wholeUnits' => in_array($mtoCurrency, ['COP', 'CLP', 'ARS'], true),
        'symbol' => \App\Helpers\CurrencyHelper::symbol(),
        'leadTime' => (int) $product->lead_time_days,
        'minDate' => today()->addDays((int) $product->lead_time_days)->toDateString(),
        'depositPercent' => $store->depositPercent(),
        'uploadUrl' => route('store.attachments.upload', $store->slug),
    ];
@endphp
<script>
document.addEventListener('alpine:init', () => {
    if (Alpine.__tribioMadeToOrderForm) return;
    Alpine.__tribioMadeToOrderForm = true;
    Alpine.data('madeToOrderForm', (config) => ({
        schema: config.schema,
        answers: {},
        files: {},
        uploading: {},
        errors: {},
        quantity: 1,
        init() {
            this.schema.forEach((field) => {
                if (field.type === 'sizes') {
                    this.answers[field.key] = Object.fromEntries(field.sizes.map((size) => [size, 0]));
                } else {
                    this.answers[field.key] = '';
                }
            });
        },
        get sizeField() { return this.schema.find((field) => field.type === 'sizes') || null; },
        get units() {
            if (!this.sizeField) return Math.max(1, parseInt(this.quantity, 10) || 1);
            return Object.values(this.answers[this.sizeField.key] || {}).reduce((sum, n) => sum + (parseInt(n, 10) || 0), 0);
        },
        money(amount) {
            const value = config.wholeUnits ? Math.round(amount).toLocaleString('en-US') : Number(amount).toFixed(2);
            return config.symbol + ' ' + value;
        },
        convert(pen) {
            const converted = pen * config.rate;
            return config.wholeUnits ? Math.round(converted) : Math.round(converted * 100) / 100;
        },
        optionPrice(field) {
            const option = (field.options || []).find((o) => o.label === this.answers[field.key]);
            return option ? option.price : 0;
        },
        get extraPen() {
            return this.schema.reduce((sum, field) => {
                const value = this.answers[field.key];
                if (field.type === 'choice') return sum + this.optionPrice(field);
                if (['text', 'textarea', 'file'].includes(field.type) && String(value || '').trim() !== '') return sum + (field.price || 0);
                return sum;
            }, 0);
        },
        get base() { return typeof this.currentPrice === 'number' ? this.currentPrice : config.basePrice; },
        get unitPrice() { return this.base + (this.extraPen > 0 ? this.convert(this.extraPen) : 0); },
        get total() { return this.unitPrice * this.units; },
        extraLabel(pen) { return pen > 0 ? '+ ' + this.money(this.convert(pen)) : ''; },
        async upload(field, event) {
            const file = event.target.files && event.target.files[0];
            event.target.value = '';
            if (!file) return;
            delete this.errors[field.key];
            if (file.size > 10 * 1024 * 1024) { this.errors[field.key] = 'El archivo pesa más de 10 MB.'; return; }
            this.uploading[field.key] = true;
            try {
                const body = new FormData();
                body.append('file', file);
                const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const res = await fetch(config.uploadUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' }, body });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    this.errors[field.key] = (data.errors && data.errors.file && data.errors.file[0]) || data.message || 'No se pudo subir el archivo. Intenta de nuevo.';
                    return;
                }
                this.answers[field.key] = data.token;
                this.files[field.key] = { name: data.name, preview: String(data.mime || '').startsWith('image/') ? data.preview_url : null };
            } catch (e) {
                this.errors[field.key] = 'No se pudo subir el archivo. Revisa tu conexión.';
            } finally {
                this.uploading[field.key] = false;
            }
        },
        removeFile(field) {
            this.answers[field.key] = '';
            delete this.files[field.key];
        },
        validate() {
            this.errors = {};
            this.schema.forEach((field) => {
                const value = this.answers[field.key];
                if (field.type === 'sizes') {
                    if (field.required && this.units < 1) this.errors[field.key] = 'Indica cuántas unidades quieres por talla.';
                    return;
                }
                const text = String(value || '').trim();
                if (field.required && text === '') {
                    this.errors[field.key] = field.type === 'file' ? 'Sube tu archivo.' : (field.type === 'choice' ? 'Elige una opción.' : 'Completa este dato.');
                } else if (field.max && text.length > field.max) {
                    this.errors[field.key] = `Máximo ${field.max} caracteres.`;
                } else if (field.type === 'date' && text !== '' && text < config.minDate) {
                    this.errors[field.key] = 'Elige una fecha a partir del ' + config.minDate.split('-').reverse().join('/') + '.';
                }
            });
            if (this.units < 1) this.errors._units = 'Elige al menos una unidad.';
            return Object.keys(this.errors).length === 0;
        },
        summary() {
            return this.schema.map((field) => {
                const value = this.answers[field.key];
                if (field.type === 'sizes') {
                    const rows = Object.entries(value || {}).filter(([, n]) => (parseInt(n, 10) || 0) > 0).map(([size, n]) => `${size} × ${n}`);
                    return rows.length ? { label: field.label, value: rows.join(', ') } : null;
                }
                if (field.type === 'file') return this.files[field.key] ? { label: field.label, value: this.files[field.key].name } : null;
                if (field.type === 'date') return value ? { label: field.label, value: value.split('-').reverse().join('/') } : null;
                return String(value || '').trim() ? { label: field.label, value: String(value).trim() } : null;
            }).filter(Boolean);
        },
        addCustomToCart(event = null) {
            if (!this.validate()) {
                this.$nextTick(() => [...document.querySelectorAll('[data-mto-error]')].find((el) => el.offsetParent !== null)
                    ?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
                return;
            }
            const variant = this.currentVariant ? {
                id: this.currentVariant.id, title: this.currentVariant.title, attributes: this.currentVariant.attributes,
            } : null;
            window.TribioCart.addCustom({
                id: config.productId, name: config.name, image: config.image, variant,
                price: this.unitPrice, quantity: this.units,
                customization: JSON.parse(JSON.stringify(this.answers)),
                summary: this.summary(),
                fixedQuantity: !!this.sizeField,
            }, event?.currentTarget || undefined);
            window.dispatchEvent(new CustomEvent('open-cart-drawer'));
        },
    }));
});
</script>

<div x-data="madeToOrderForm(@js($mtoConfig))" class="mb-8 space-y-4">
    @if(!empty($product->customization_schema))
    <div class="p-5 sm:p-6 rounded-3xl bg-white border border-stone-200/80 shadow-xs space-y-5">
        <div>
            <p class="text-[11px] font-black uppercase tracking-wider text-[var(--t-primary-dark)]">✂️ Personaliza tu pedido</p>
            <p class="text-xs text-stone-500 mt-1">Lo hacemos especialmente para ti con estos datos.</p>
        </div>

        <template x-for="field in schema" :key="field.key">
            <div>
                <div class="flex items-baseline justify-between gap-2 mb-1.5">
                    <label :for="'mto-' + field.key" class="text-sm font-bold text-stone-800">
                        <span x-text="field.label"></span><span x-show="field.required" class="text-rose-500"> *</span>
                    </label>
                    <span x-show="['text', 'textarea', 'file'].includes(field.type) && field.price > 0" class="text-[11px] font-bold text-[var(--t-primary-dark)]" x-text="extraLabel(field.price)"></span>
                </div>

                <template x-if="field.type === 'text'">
                    <div>
                        <input type="text" :id="'mto-' + field.key" x-model="answers[field.key]" :maxlength="field.max"
                               class="w-full px-4 py-3 rounded-xl bg-stone-50 border border-stone-200 text-sm focus:border-[var(--t-primary)] focus:ring-2 focus:ring-[var(--t-primary)]/20 outline-none transition">
                        <p class="text-[11px] text-stone-400 text-right mt-1" x-text="String(answers[field.key] || '').length + ' / ' + field.max"></p>
                    </div>
                </template>

                <template x-if="field.type === 'textarea'">
                    <textarea :id="'mto-' + field.key" x-model="answers[field.key]" :maxlength="field.max" rows="3"
                              class="w-full px-4 py-3 rounded-xl bg-stone-50 border border-stone-200 text-sm focus:border-[var(--t-primary)] focus:ring-2 focus:ring-[var(--t-primary)]/20 outline-none transition"></textarea>
                </template>

                <template x-if="field.type === 'choice'">
                    <div class="flex flex-wrap gap-2" role="radiogroup" :aria-label="field.label">
                        <template x-for="option in field.options" :key="option.label">
                            <button type="button" @click="answers[field.key] = option.label; delete errors[field.key]"
                                    :aria-pressed="answers[field.key] === option.label"
                                    class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold border transition cursor-pointer"
                                    :class="answers[field.key] === option.label ? 'bg-[var(--t-primary-50)] border-[var(--t-primary)] text-[var(--t-primary-deep)] shadow-xs' : 'bg-stone-50 border-stone-200 text-stone-700 hover:border-stone-400'">
                                <span x-show="option.color" class="w-4 h-4 rounded-full border border-stone-300" :style="'background:' + option.color"></span>
                                <span x-text="option.label"></span>
                                <span x-show="option.price > 0" class="opacity-70" x-text="extraLabel(option.price)"></span>
                            </button>
                        </template>
                    </div>
                </template>

                <template x-if="field.type === 'file'">
                    <div>
                        <template x-if="!files[field.key]">
                            <label :for="'mto-' + field.key" class="flex flex-col items-center justify-center gap-1 p-5 rounded-2xl border-2 border-dashed border-stone-300 bg-stone-50 hover:border-[var(--t-primary)] hover:bg-[var(--t-primary-50)] transition cursor-pointer text-center">
                                <span class="text-2xl" aria-hidden="true" x-text="uploading[field.key] ? '⏳' : '⬆️'"></span>
                                <span class="text-sm font-bold text-stone-700" x-text="uploading[field.key] ? 'Subiendo…' : 'Subir archivo'"></span>
                                <span class="text-[11px] text-stone-500">PNG, JPG, WEBP o PDF · máx. 10 MB</span>
                                <input type="file" :id="'mto-' + field.key" accept="image/png,image/jpeg,image/webp,application/pdf" class="sr-only" @change="upload(field, $event)" :disabled="uploading[field.key]">
                            </label>
                        </template>
                        <template x-if="files[field.key]">
                            <div class="flex items-center gap-3 p-3 rounded-2xl border border-stone-200 bg-stone-50">
                                <template x-if="files[field.key].preview"><img :src="files[field.key].preview" alt="" class="w-14 h-14 rounded-xl object-contain bg-white border border-stone-200"></template>
                                <template x-if="!files[field.key].preview"><span class="w-14 h-14 rounded-xl bg-white border border-stone-200 flex items-center justify-center text-xl">📄</span></template>
                                <span class="flex-1 min-w-0 text-sm font-semibold text-stone-700 truncate" x-text="files[field.key].name"></span>
                                <button type="button" @click="removeFile(field)" class="text-xs font-bold text-stone-500 hover:text-rose-600 px-2 py-1">Cambiar</button>
                            </div>
                        </template>
                    </div>
                </template>

                <template x-if="field.type === 'sizes'">
                    <div>
                        <div class="grid grid-cols-3 sm:grid-cols-4 gap-2">
                            <template x-for="size in field.sizes" :key="size">
                                <div class="p-2 rounded-xl border border-stone-200 bg-stone-50 text-center">
                                    <p class="text-xs font-black text-stone-700 mb-1" x-text="size"></p>
                                    <div class="flex items-center justify-center gap-1">
                                        <button type="button" class="w-7 h-7 rounded-lg bg-white border border-stone-200 font-bold cursor-pointer" @click="answers[field.key][size] = Math.max(0, (parseInt(answers[field.key][size], 10) || 0) - 1)" :aria-label="'Menos talla ' + size">−</button>
                                        <input type="number" min="0" max="9999" x-model.number="answers[field.key][size]" class="w-10 h-7 text-center text-sm font-bold bg-transparent border-none p-0" :aria-label="'Unidades talla ' + size">
                                        <button type="button" class="w-7 h-7 rounded-lg bg-white border border-stone-200 font-bold cursor-pointer" @click="answers[field.key][size] = (parseInt(answers[field.key][size], 10) || 0) + 1; delete errors[field.key]" :aria-label="'Más talla ' + size">+</button>
                                    </div>
                                </div>
                            </template>
                        </div>
                        <p class="text-xs text-stone-500 mt-2">Total: <strong x-text="units + (units === 1 ? ' unidad' : ' unidades')"></strong></p>
                    </div>
                </template>

                <template x-if="field.type === 'date'">
                    <div>
                        <input type="date" :id="'mto-' + field.key" x-model="answers[field.key]" min="{{ $mtoConfig['minDate'] }}"
                               class="w-full sm:w-auto px-4 py-3 rounded-xl bg-stone-50 border border-stone-200 text-sm focus:border-[var(--t-primary)] outline-none">
                        <p class="text-[11px] text-stone-500 mt-1">Lo más pronto posible: {{ today()->addDays((int) $product->lead_time_days)->format('d/m/Y') }}</p>
                    </div>
                </template>

                <p x-show="errors[field.key]" data-mto-error class="text-xs font-semibold text-rose-600 mt-1.5" x-text="errors[field.key]"></p>
            </div>
        </template>
    </div>
    @endif

    {{-- Precio en vivo y compra --}}
    <div class="p-5 rounded-3xl bg-[var(--t-primary-50)] border border-[var(--t-primary-200)] space-y-3">
        <div class="flex flex-wrap items-end justify-between gap-2">
            <div>
                <p class="text-xs text-stone-600">Precio por unidad <span x-show="extraPen > 0">(con personalización)</span></p>
                <p class="text-xl font-black text-stone-900" x-text="money(unitPrice)"></p>
            </div>
            <div class="text-right">
                <p class="text-xs text-stone-600" x-text="units + (units === 1 ? ' unidad' : ' unidades')"></p>
                <p class="text-2xl font-black text-stone-900" x-text="money(total)"></p>
            </div>
        </div>
        @if($store->depositPercent() < 100)
        <p class="text-xs text-[var(--t-primary-deep)] font-semibold">💳 Hoy pagas el {{ $store->depositPercent() }}% como adelanto y el saldo antes de la entrega.</p>
        @endif

        <div class="flex items-center gap-3">
            <template x-if="!sizeField">
                <div class="flex items-center bg-white border-2 border-stone-300/80 rounded-2xl p-1">
                    <button type="button" @click="if (quantity > 1) quantity--" class="w-10 h-11 flex items-center justify-center text-stone-800 hover:bg-stone-100 rounded-xl text-xl font-bold cursor-pointer" aria-label="Menos">−</button>
                    <input type="number" x-model.number="quantity" min="1" class="w-12 h-11 text-center bg-transparent border-none focus:ring-0 text-lg font-black" aria-label="Cantidad">
                    <button type="button" @click="quantity++" class="w-10 h-11 flex items-center justify-center text-stone-800 hover:bg-stone-100 rounded-xl text-xl font-bold cursor-pointer" aria-label="Más">+</button>
                </div>
            </template>
            <button type="button" @click="addCustomToCart($event)"
                    class="flex-1 bg-[#1A1A1A] hover:bg-stone-800 text-white font-black text-base rounded-2xl transition-all shadow-xl flex items-center justify-center gap-2 py-4 px-6 cursor-pointer active:scale-98">
                <span>Añadir pedido al carrito</span>
            </button>
        </div>
        <p x-show="errors._units" data-mto-error class="text-xs font-semibold text-rose-600" x-text="errors._units"></p>
    </div>
</div>
