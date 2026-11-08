@extends('layouts.dashboard')
@section('title', 'Nueva difusión')
@section('page_title', '📣 Nueva difusión')
@section('content')
<div class="w-full max-w-[1600px] mx-auto space-y-5">
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <p class="text-sm text-white/60 max-w-2xl">Elige la plantilla y a quién enviarla. El costo se calcula mientras eliges; nada sale hasta que pulses «Enviar».</p>
        <a href="{{ route('dashboard.whatsapp.broadcasts.index') }}" class="text-xs text-tribio-cyan underline">← Difusiones</a>
    </div>

    @if($errors->any())
        <div class="glass-card p-4" role="alert">@foreach($errors->all() as $error)<p class="text-xs text-red-300">{{ $error }}</p>@endforeach</div>
    @endif

    @if($templates->isEmpty())
        <div class="glass-card p-5 flex items-center justify-between gap-3 flex-wrap" role="status">
            <p class="text-sm text-white">Necesitas al menos una plantilla aprobada por Meta.</p>
            <a href="{{ route('dashboard.whatsapp.templates.index') }}" class="btn-primary text-xs sm:text-sm">Ver plantillas</a>
        </div>
    @else
        <form method="POST" action="{{ route('dashboard.whatsapp.broadcasts.store') }}" x-ref="form"
              x-data="broadcastForm(@js($templates), @js($sources), @js(route('dashboard.whatsapp.broadcasts.estimate')), @js(old('template_id', $preselect)))"
              @input.debounce.500ms="refresh()" @change.debounce.300ms="refresh()" @submit="confirmSend($event)"
              class="grid grid-cols-1 lg:grid-cols-3 gap-5 items-start">
            @csrf
            <div class="lg:col-span-2 space-y-5">
                <div class="glass-card p-4 sm:p-5 space-y-3">
                    <div>
                        <label class="input-label" for="broadcast-name">Nombre (solo lo ves tú)</label>
                        <input id="broadcast-name" name="name" class="input-field" maxlength="120" value="{{ old('name') }}" placeholder="Promo de batidos · noviembre" required>
                    </div>
                    <div>
                        <label class="input-label" for="broadcast-template">Plantilla aprobada</label>
                        <select id="broadcast-template" name="template_id" class="input-field" x-model="templateId" required>
                            <template x-for="template in templates" :key="template.id">
                                <option :value="String(template.id)" x-text="template.name + ' · ' + template.label"></option>
                            </template>
                        </select>
                        <p class="text-[11px] text-white/50 mt-1" x-show="selected" x-text="selected ? (selected.category === 'MARKETING' ? 'Marketing: solo llega a clientes que aceptaron recibir ofertas.' : 'Utilidad: para pedidos, pagos y seguimiento; si Meta ve que es una oferta, la cobra como marketing.') : ''"></p>
                    </div>

                    <template x-if="selected && selected.variables.length">
                        <div class="space-y-2">
                            <p class="text-xs text-white/70 font-semibold">Datos de la plantilla</p>
                            <template x-for="variable in selected.variables" :key="selected.id + '-' + variable.key">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 items-end rounded-xl border border-white/10 bg-white/5 p-2">
                                    <div>
                                        <label class="input-label" :for="'source-' + variable.key" x-text="'\x7B\x7B' + variable.key + '\x7D\x7D · ' + variable.label"></label>
                                        <select class="input-field !py-1 !text-xs" :id="'source-' + variable.key" :name="'variables[' + variable.key + '][source]'" x-model="mapping[variable.key].source">
                                            <template x-for="(label, key) in sources" :key="key"><option :value="key" x-text="label"></option></template>
                                        </select>
                                    </div>
                                    <div x-show="mapping[variable.key].source === 'fixed'">
                                        <label class="input-label" :for="'text-' + variable.key">Texto</label>
                                        <input class="input-field !py-1 !text-xs" :id="'text-' + variable.key" :name="'variables[' + variable.key + '][text]'" maxlength="1000" x-model="mapping[variable.key].text" placeholder="Ej.: Batido de fresa">
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                <div class="glass-card p-4 sm:p-5 space-y-3">
                    <h3 class="text-white font-bold text-base">¿A quién?</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="input-label" for="broadcast-stage">Etapa</label>
                            <select id="broadcast-stage" name="stage" class="input-field">
                                <option value="">Todas</option>
                                @foreach($stages as $key => $label)<option value="{{ $key }}" @selected(old('stage') === $key)>{{ $label }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="input-label" for="broadcast-segment">Compras</label>
                            <select id="broadcast-segment" name="segment" class="input-field">
                                <option value="">Todos</option>
                                @foreach($segments as $key => $label)<option value="{{ $key }}" @selected(old('segment') === $key)>{{ $label }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="input-label" for="broadcast-tag">Etiqueta</label>
                            <input id="broadcast-tag" name="tag" class="input-field" list="broadcast-tags" maxlength="40" value="{{ old('tag') }}" placeholder="Cualquiera">
                            <datalist id="broadcast-tags">@foreach($tags as $tag)<option value="{{ $tag }}"></option>@endforeach</datalist>
                        </div>
                    </div>
                    <label class="flex items-start gap-2 text-xs text-white/70">
                        <input type="checkbox" name="skip_recent" value="1" class="mt-0.5" @checked(old('skip_recent', true))>
                        <span>No enviar a quien recibió otra difusión en los últimos {{ \App\Services\Whatsapp\BroadcastService::RECENT_DAYS }} días (evita que te bloqueen o reporten).</span>
                    </label>
                </div>

                <div class="glass-card p-4 sm:p-5 space-y-3">
                    <h3 class="text-white font-bold text-base">¿Cuándo?</h3>
                    <div class="flex flex-wrap gap-4 text-sm text-white/80">
                        <label class="flex items-center gap-2"><input type="radio" name="send" value="now" x-model="send"> Ahora</label>
                        <label class="flex items-center gap-2"><input type="radio" name="send" value="later" x-model="send"> Programar</label>
                    </div>
                    <div x-show="send === 'later'" x-cloak>
                        <label class="input-label" for="broadcast-at">Día y hora (hora de Perú)</label>
                        <input id="broadcast-at" type="datetime-local" name="scheduled_at" class="input-field sm:!w-auto" value="{{ old('scheduled_at') }}">
                    </div>
                    <p class="text-[11px] text-white/50">Los mensajes salen de a poco ({{ config('services.whatsapp.broadcast_per_minute') }} por minuto) para cuidar la calidad de tu número.</p>
                </div>
            </div>

            <aside class="glass-card p-4 sm:p-5 space-y-3 lg:sticky lg:top-4" aria-live="polite">
                <h3 class="text-white font-bold text-base">Resumen</h3>
                <p class="text-xs text-white/50" x-show="loading">Calculando…</p>
                <template x-if="estimate">
                    <div class="space-y-3">
                        <div>
                            <p class="text-3xl font-black text-white" x-text="estimate.recipients.toLocaleString('es-PE')"></p>
                            <p class="text-xs text-white/60">clientes recibirán el mensaje</p>
                        </div>
                        <ul class="text-[11px] text-white/50 space-y-0.5">
                            <li x-show="estimate.no_phone" x-text="estimate.no_phone + ' sin celular'"></li>
                            <li x-show="estimate.no_consent" x-text="estimate.no_consent + ' no aceptaron ofertas (Ley 29733)'"></li>
                            <li x-show="estimate.recent" x-text="estimate.recent + ' recibieron una difusión hace poco'"></li>
                        </ul>
                        <div class="rounded-xl border border-white/10 bg-white/5 p-3">
                            <p class="text-xs text-white/60">Costo estimado</p>
                            <p class="text-xl font-black text-white" x-text="money(estimate.cost)"></p>
                            <p class="text-[11px] text-white/50" x-text="estimate.category + ' · ' + money(estimate.rate, 4) + ' por mensaje entregado'"></p>
                            <p class="text-[11px] text-white/50 mt-1" x-show="estimate.budget.available !== null" x-text="'Te quedan ' + money(estimate.budget.available) + ' de ' + money(estimate.budget.budget) + ' este mes'"></p>
                        </div>
                        <p class="text-[11px] text-white/50" x-show="estimate.limit !== null" x-text="'Límite de Meta: ' + estimate.limit_used + ' de ' + estimate.limit + ' clientes en las últimas 24 h'"></p>
                        <template x-if="estimate.sample">
                            <div>
                                <p class="text-xs text-white/60 mb-1" x-text="'Así le llega a ' + estimate.sample.name + ':'"></p>
                                <p class="text-xs text-white whitespace-pre-line rounded-xl bg-emerald-900/40 border border-emerald-400/20 p-3" x-text="estimate.sample.text"></p>
                            </div>
                        </template>
                        <template x-for="problem in estimate.problems" :key="problem"><p class="text-xs text-red-300" role="alert" x-text="problem"></p></template>
                    </div>
                </template>
                <p class="text-xs text-red-300" x-show="error" x-text="error" role="alert"></p>
                <button type="submit" class="btn-primary w-full" :disabled="!estimate || estimate.problems.length > 0 || loading" :class="(!estimate || estimate.problems.length > 0 || loading) && 'opacity-50 cursor-not-allowed'"
                        x-text="send === 'later' ? 'Programar difusión' : 'Enviar difusión'"></button>
                @if($budget['budget'] === null)<p class="text-[11px] text-white/50">Primero define tu <a href="{{ route('dashboard.whatsapp.broadcasts.index') }}" class="text-tribio-cyan underline">tope mensual</a>.</p>@endif
            </aside>
        </form>
    @endif
</div>
@endsection

@push('scripts')
<script>
    function broadcastForm(templates, sources, estimateUrl, preselect) {
        const first = templates.find(t => String(t.id) === String(preselect)) || templates[0];
        return {
            templates, sources, send: @js(old('send', 'now')),
            templateId: first ? String(first.id) : '',
            mapping: {}, estimate: null, loading: false, error: '', request: 0,
            get selected() { return this.templates.find(t => String(t.id) === String(this.templateId)) || null; },
            init() {
                this.fill();
                this.$watch('templateId', () => { this.fill(); this.$nextTick(() => this.refresh()); });
                this.$nextTick(() => this.refresh());
            },
            fill() {
                const mapping = {};
                (this.selected ? this.selected.variables : []).forEach(v => { mapping[v.key] = { source: v.source, text: v.text }; });
                this.mapping = mapping;
            },
            money(value, digits = 2) { return 'S/ ' + Number(value || 0).toFixed(digits); },
            async refresh() {
                const ticket = ++this.request;
                this.loading = true;
                try {
                    const response = await fetch(estimateUrl, { method: 'POST', body: new FormData(this.$refs.form),
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                    const data = await response.json();
                    if (ticket !== this.request) return;
                    if (response.ok) { this.estimate = data; this.error = ''; }
                    else { this.estimate = null; this.error = data.errors ? Object.values(data.errors)[0][0] : 'No pudimos calcular el costo.'; }
                } catch (e) {
                    if (ticket === this.request) { this.estimate = null; this.error = 'No pudimos calcular el costo. Revisa tu conexión.'; }
                } finally {
                    if (ticket === this.request) this.loading = false;
                }
            },
            confirmSend(event) {
                if (!this.estimate || this.estimate.problems.length) { event.preventDefault(); return; }
                const text = (this.send === 'later' ? 'Se programará para ' : 'Se enviará ahora a ') + this.estimate.recipients + ' clientes por unos ' + this.money(this.estimate.cost) + '. ¿Continuar?';
                if (!confirm(text)) event.preventDefault();
            },
        };
    }
</script>
@endpush
