@extends('layouts.dashboard')
@section('title', 'WhatsApp')
@section('page_title', '💬 ' . $conversation->display_name)
@section('content')
@php $closesAt = $conversation->windowClosesAt(); @endphp
<div class="w-full max-w-[1600px] mx-auto space-y-4"
     x-data="chatPoll(@js(route('dashboard.whatsapp.poll', $conversation->id)), {{ (int) $messages->last()?->id }}, @js($conversation->windowOpen()))">

    <div class="flex items-center justify-between gap-3 flex-wrap">
        <a href="{{ route('dashboard.whatsapp.index') }}" class="text-xs text-tribio-cyan underline">← Chats</a>
        <div class="flex items-center gap-3 text-xs">
            @if($conversation->wa_id)<span class="text-white/50">+{{ $conversation->wa_id }}</span>@endif
            @if($conversation->contact)
                <a href="{{ route('dashboard.clientes.show', $conversation->contact_id) }}" class="text-tribio-cyan underline">Ver ficha del cliente</a>
            @endif
        </div>
    </div>

    @if($errors->any())
        <div class="glass-card p-3" role="alert">@foreach($errors->all() as $error)<p class="text-xs text-red-300">{{ $error }}</p>@endforeach</div>
    @endif

    <div class="glass-card p-3 sm:p-4 space-y-2 max-h-[60vh] overflow-y-auto" x-ref="thread" aria-live="polite" aria-label="Mensajes">
        @forelse($messages as $message)
            @include('dashboard.whatsapp._message', ['message' => $message])
        @empty
            <p class="text-sm text-white/50 text-center py-6">Sin mensajes todavía.</p>
        @endforelse
    </div>

    <form method="POST" action="{{ route('dashboard.whatsapp.reply', $conversation->id) }}" class="glass-card p-3 sm:p-4" x-show="windowOpen">
        @csrf
        <label for="reply-body" class="sr-only">Tu respuesta</label>
        <div class="flex items-end gap-2">
            <textarea id="reply-body" name="body" rows="2" maxlength="4096" class="input-field flex-1" placeholder="Escribe tu respuesta…" required
                      @keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); $el.form.requestSubmit(); }">{{ old('body') }}</textarea>
            <button type="submit" class="btn-primary text-xs sm:text-sm">Enviar</button>
        </div>
        <p class="text-[11px] text-white/40 mt-2">
            Enter envía, Shift+Enter hace un salto de línea.
            @if($closesAt) Puedes responder libremente hasta las {{ $closesAt->locale('es')->isoFormat('HH:mm [del] D MMM') }}. @endif
            @if($templates !== null)<button type="button" class="text-tribio-cyan underline" @click="useTemplate = !useTemplate" x-text="useTemplate ? 'Ocultar plantillas' : 'Usar una plantilla'">Usar una plantilla</button>@endif
        </p>
    </form>

    <div class="glass-card p-4 space-y-3" x-show="!windowOpen || useTemplate" @if($conversation->windowOpen()) x-cloak @endif>
        <div x-show="!windowOpen" role="status">
            <p class="text-sm text-white font-semibold">{{ $conversation->last_inbound_at ? 'Pasaron más de 24 h desde el último mensaje de ' . $conversation->display_name : $conversation->display_name . ' aún no te escribió por este número' }}</p>
            <p class="text-xs text-white/60 mt-1">WhatsApp solo deja escribir primero con una plantilla aprobada por Meta (Meta cobra cada una). Cuando responda, podrás escribirle libremente por 24 h.</p>
        </div>

        @if($templates === null)
            <p class="text-xs text-white/50">Las plantillas de Meta se están activando en tu cuenta.</p>
        @elseif($templates->isEmpty())
            <p class="text-xs text-white/60">Aún no tienes plantillas aprobadas. <a href="{{ route('dashboard.whatsapp.templates.index') }}" class="text-tribio-cyan underline">Envía tus plantillas a revisión</a>.</p>
        @else
            <form method="POST" action="{{ route('dashboard.whatsapp.template', $conversation->id) }}" class="space-y-3" x-data="templatePicker(@js($templates))">
                @csrf
                <div>
                    <label class="input-label" for="tpl-select">Plantilla aprobada</label>
                    <select id="tpl-select" name="template_id" class="input-field" x-model="selectedId">
                        <template x-for="tpl in templates" :key="tpl.id">
                            <option :value="tpl.id" :disabled="!!tpl.blocked" x-text="tpl.name + ' · ' + tpl.category + (tpl.blocked ? ' · sin permiso' : '')"></option>
                        </template>
                    </select>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2" x-show="selected && selected.variables.length">
                    <template x-for="variable in (selected ? selected.variables : [])" :key="selectedId + '-' + variable.key">
                        <div>
                            <label class="input-label" :for="'tpl-var-' + variable.key" x-text="variable.label"></label>
                            <input class="input-field" :id="'tpl-var-' + variable.key" :name="'values[' + variable.key + ']'" x-model="values[variable.key]" maxlength="1000" required>
                        </div>
                    </template>
                </div>
                <div class="rounded-xl bg-white/5 border border-white/10 p-3" x-show="selected">
                    <p class="text-[10px] text-white/40 mb-1">Así lo recibirá</p>
                    <p class="text-sm text-white whitespace-pre-line" x-text="preview()"></p>
                </div>
                <div class="flex items-center gap-3 flex-wrap">
                    <button type="submit" class="btn-primary text-xs sm:text-sm" :disabled="!selected || !!selected.blocked">Enviar plantilla</button>
                    <a href="{{ route('dashboard.whatsapp.templates.index') }}" class="text-xs text-tribio-cyan underline">Gestionar plantillas</a>
                </div>
            </form>
        @endif

        @if($conversation->contact?->whatsapp_url)
            <a href="{{ $conversation->contact->whatsapp_url }}" target="_blank" rel="noopener" class="text-xs text-white/50 underline inline-block" x-show="!windowOpen">O escríbele desde el WhatsApp de tu celular</a>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
    function chatPoll(url, lastId, windowOpen) {
        return {
            lastId, windowOpen, useTemplate: false, timer: null,
            init() {
                this.scrollDown();
                this.timer = setInterval(() => this.poll(), 8000);
            },
            scrollDown() { this.$nextTick(() => { this.$refs.thread.scrollTop = this.$refs.thread.scrollHeight; }); },
            async poll() {
                if (document.hidden) return;
                try {
                    const response = await fetch(url + '?after=' + this.lastId, { headers: { 'Accept': 'application/json' } });
                    if (!response.ok) return;
                    const data = await response.json();
                    if (data.html) {
                        this.$refs.thread.querySelector('p.text-center')?.remove();
                        this.$refs.thread.insertAdjacentHTML('beforeend', data.html);
                        this.scrollDown();
                    }
                    this.lastId = data.last_id;
                    this.windowOpen = data.window_open;
                    Object.entries(data.statuses || {}).forEach(([id, status]) => {
                        const label = this.$refs.thread.querySelector(`[data-status-id="${id}"]`);
                        if (label) { label.textContent = status.label; label.classList.toggle('text-red-300', status.failed); }
                    });
                } catch (e) { /* offline: try again on the next tick */ }
            },
        };
    }

    function templatePicker(templates) {
        const first = templates.find(t => !t.blocked);
        return {
            templates,
            selectedId: first ? String(first.id) : '',
            values: {},
            get selected() { return this.templates.find(t => String(t.id) === String(this.selectedId)) || null; },
            init() {
                this.fill();
                this.$watch('selectedId', () => this.fill());
            },
            fill() {
                this.values = {};
                (this.selected ? this.selected.variables : []).forEach(v => { this.values[v.key] = v.value; });
            },
            preview() {
                if (!this.selected) return '';
                return this.selected.body.replace(/\{\{\s*([a-z0-9_]+)\s*\}\}/g, (match, key) => {
                    const variable = this.selected.variables.find(v => v.key === key);
                    return this.values[key] || '[' + (variable ? variable.label : key) + ']';
                });
            },
        };
    }
</script>
@endpush
