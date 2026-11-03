@extends('layouts.dashboard')
@section('title', $contact->name . ' — Clientes')
@section('page_title', '👥 Clientes')
@section('content')
@php
    use App\Models\Contact;
    use App\Models\ContactEvent;
    use App\Models\ContactTask;
@endphp
<div class="w-full max-w-4xl mx-auto space-y-5">

    <div class="glass-card px-3.5 sm:px-5 py-2.5 sm:py-3 flex items-center gap-3">
        <a href="{{ route('dashboard.clientes.index') }}" class="btn-ghost !px-2.5 !py-2 flex-shrink-0" aria-label="Volver a Clientes" title="Volver a Clientes">←</a>
        <div class="min-w-0 flex-1">
            <p class="text-[10px] text-white/40 uppercase tracking-wider leading-none mb-0.5">Cliente{{ $contact->relationship ? ' · ' . (Contact::RELATIONSHIPS[$contact->relationship] ?? '') : '' }}</p>
            <p class="text-white font-bold text-sm truncate">{{ $contact->name }}</p>
        </div>
        @if($profile && $contact->stage_label)<span class="badge {{ $contact->stage_badge }} flex-shrink-0">{{ $contact->stage_label }}</span>@endif
        @if($debt !== null && ($debt > 0 || $contact->credit_limit !== null))
            <a href="{{ route('dashboard.receivables.show', $contact->id) }}" class="badge {{ $debt > 0 ? 'badge-gold' : 'badge-gray' }} flex-shrink-0">{{ $debt > 0 ? 'Debe S/ ' . number_format($debt, 2) : 'Sin deudas' }}</a>
        @endif
        @if($chat)
            <a href="{{ route('dashboard.whatsapp.show', $chat->id) }}" class="btn-secondary flex-shrink-0 text-xs sm:text-sm">💬 Chat{{ $chat->unread_count > 0 ? ' (' . $chat->unread_count . ')' : '' }}</a>
        @elseif($apiReady && $contact->phone)
            <form method="POST" action="{{ route('dashboard.whatsapp.start', $contact->id) }}" class="flex-shrink-0">
                @csrf
                <button type="submit" class="btn-secondary text-xs sm:text-sm" title="Escribirle desde tu número conectado, con una plantilla aprobada">💬 Chat</button>
            </form>
        @endif
        @if($contact->whatsapp_url && $profile)
            <div class="relative flex-shrink-0" x-data="{ open: false }" @keydown.escape="open = false">
                <button type="button" class="btn-primary text-xs sm:text-sm" @click="open = !open" :aria-expanded="open" aria-haspopup="true">WhatsApp ▾</button>
                <div class="absolute right-0 mt-2 w-64 glass-card p-2 z-30 space-y-1" x-show="open" x-cloak @click.outside="open = false">
                    @foreach($templates as $template)
                        @if($template['promo'] && !$contact->accepts_marketing)
                            <span class="block px-3 py-2 text-xs text-white/30 rounded-lg" title="No aceptó recibir ofertas">{{ $template['name'] }} · sin permiso</span>
                        @elseif(str_contains($template['body'], '{saldo}') && !$hasBalance)
                            <span class="block px-3 py-2 text-xs text-white/30 rounded-lg" title="No tiene saldo pendiente">{{ $template['name'] }} · sin saldo</span>
                        @else
                            <a href="{{ route('dashboard.clientes.whatsapp', [$contact->id, 'plantilla' => $template['key']]) }}" target="_blank" rel="noopener"
                               class="block px-3 py-2 text-sm text-white rounded-lg hover:bg-white/10" @click="open = false">{{ $template['name'] }}</a>
                        @endif
                    @endforeach
                    <a href="{{ $contact->whatsapp_url }}" target="_blank" rel="noopener" class="block px-3 py-2 text-xs text-white/50 rounded-lg hover:bg-white/10">Escribir sin plantilla</a>
                    <a href="{{ route('dashboard.clientes.templates.index') }}" class="block px-3 py-2 text-xs text-tribio-cyan rounded-lg hover:bg-white/10">Editar plantillas</a>
                </div>
            </div>
        @elseif($contact->whatsapp_url)
            <a href="{{ $contact->whatsapp_url }}" target="_blank" rel="noopener" class="btn-primary flex-shrink-0 text-xs sm:text-sm">WhatsApp</a>
        @endif
    </div>

    @if($errors->any())
        <div class="glass-card p-4" role="alert">@foreach($errors->all() as $error)<p class="text-xs text-red-300">{{ $error }}</p>@endforeach</div>
    @endif

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="glass-card p-3">
            <p class="text-[11px] text-white/40 uppercase tracking-wider">Pedidos</p>
            <p class="text-lg text-white font-bold mt-0.5">{{ $contact->orders_count }}</p>
        </div>
        <div class="glass-card p-3">
            <p class="text-[11px] text-white/40 uppercase tracking-wider">Total pagado</p>
            <p class="text-lg text-white font-bold mt-0.5 whitespace-nowrap">S/ {{ number_format((float) $contact->total_spent, 2) }}</p>
        </div>
        <div class="glass-card p-3">
            <p class="text-[11px] text-white/40 uppercase tracking-wider">Primera compra</p>
            <p class="text-sm text-white font-semibold mt-1">{{ $contact->first_order_at?->locale('es')->isoFormat('D MMM YYYY') ?? '—' }}</p>
        </div>
        <div class="glass-card p-3">
            <p class="text-[11px] text-white/40 uppercase tracking-wider">Última compra</p>
            <p class="text-sm text-white font-semibold mt-1">{{ $contact->last_order_at?->locale('es')->diffForHumans() ?? '—' }}</p>
        </div>
    </div>

    @if($contact->notes)
        <div class="glass-card p-4"><p class="text-[11px] text-white/40 uppercase tracking-wider">Nota fija</p><p class="text-sm text-white/80 mt-1 whitespace-pre-line">{{ $contact->notes }}</p></div>
    @endif

    @if($profile)
    {{-- Seguimientos --}}
    <div class="glass-card p-4 sm:p-6">
        <h3 class="text-white font-bold text-base">Seguimientos</h3>
        <div class="space-y-2 mt-3">
            @forelse($tasks as $task)
            <div class="rounded-xl border border-white/10 bg-white/5 p-3 flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-sm text-white font-semibold">{{ $task->title }}</p>
                    <p class="text-[11px] {{ $task->isOverdue() ? 'text-red-300' : 'text-white/50' }}">{{ $task->kind_label }} · {{ $task->due_label }}{{ $task->isOverdue() ? ' · vencido' : '' }}</p>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    @if($contact->whatsapp_url)
                        <a href="{{ route('dashboard.clientes.whatsapp', [$contact->id, 'plantilla' => \App\Services\Crm\MessageTemplates::FOR_TASK_KIND[$task->kind] ?? 'follow_up', 'producto' => $task->product_id]) }}" target="_blank" rel="noopener" class="btn-ghost text-xs">WhatsApp</a>
                    @endif
                    <form method="POST" action="{{ route('dashboard.clientes.tasks.complete', $task->id) }}">
                        @csrf
                        <button type="submit" class="btn-secondary text-xs">✓ Hecho</button>
                    </form>
                </div>
            </div>
            @empty
                <p class="text-sm text-white/60">Nada pendiente con {{ $contact->name }}.</p>
            @endforelse
        </div>
        <form method="POST" action="{{ route('dashboard.clientes.tasks.store') }}" class="grid grid-cols-1 sm:grid-cols-6 gap-2 items-end mt-4">
            @csrf
            <input type="hidden" name="contact_id" value="{{ $contact->id }}">
            <div class="sm:col-span-3">
                <label class="input-label" for="task-title">Nuevo seguimiento</label>
                <input id="task-title" name="title" class="input-field" maxlength="160" placeholder="Ej.: llamar para ver si le gustó" required>
            </div>
            <div class="sm:col-span-2">
                <label class="input-label" for="task-due">Para cuándo</label>
                <input id="task-due" type="datetime-local" name="due_at" class="input-field" value="{{ now()->addDay()->setTime(10, 0)->format('Y-m-d\TH:i') }}" required>
            </div>
            <button type="submit" class="btn-primary text-xs sm:text-sm">Agendar</button>
        </form>
    </div>

    {{-- Oportunidades --}}
    @if($deals !== null)
    <div class="glass-card p-4 sm:p-6">
        <div class="flex items-center justify-between gap-3">
            <h3 class="text-white font-bold text-base">Oportunidades</h3>
            <a href="{{ route('dashboard.clientes.deals.index') }}" class="text-xs text-tribio-cyan underline">Ver embudo</a>
        </div>
        <div class="space-y-2 mt-3">
            @forelse($deals as $deal)
                @include('dashboard.contacts._deal_card', ['deal' => $deal, 'orders' => $orders->take(10), 'showContact' => false])
            @empty
                <p class="text-sm text-white/60">Sin oportunidades con {{ $contact->name }}.</p>
            @endforelse
        </div>
        <form method="POST" action="{{ route('dashboard.clientes.deals.store') }}" class="grid grid-cols-1 sm:grid-cols-6 gap-2 items-end mt-4">
            @csrf
            <input type="hidden" name="contact_id" value="{{ $contact->id }}">
            <div class="sm:col-span-3">
                <label class="input-label" for="new-deal-title">Nueva oportunidad</label>
                <input id="new-deal-title" name="title" class="input-field" maxlength="120" placeholder="Ej.: quiere ser distribuidora" required>
            </div>
            <div>
                <label class="input-label" for="new-deal-amount">Monto (S/)</label>
                <input id="new-deal-amount" type="number" name="amount" min="0" step="0.01" class="input-field">
            </div>
            <div>
                <label class="input-label" for="new-deal-date">Cierre</label>
                <input id="new-deal-date" type="date" name="expected_close_at" class="input-field">
            </div>
            <button type="submit" class="btn-primary text-xs sm:text-sm">Crear</button>
        </form>
    </div>
    @endif

    {{-- Anotar --}}
    <form method="POST" action="{{ route('dashboard.clientes.events.store', $contact->id) }}" class="glass-card p-4 sm:p-6 space-y-3" x-data="{ type: 'note' }">
        @csrf
        <h3 class="text-white font-bold text-base">Anotar</h3>
        <div class="flex flex-wrap gap-2">
            @foreach(ContactEvent::MANUAL_TYPES as $type)
                <label class="cursor-pointer">
                    <input type="radio" name="type" value="{{ $type }}" class="sr-only" x-model="type" aria-label="{{ ContactEvent::TYPES[$type]['label'] }}">
                    <span class="inline-flex px-3 py-1.5 rounded-full border text-xs transition" :class="type === '{{ $type }}' ? 'border-tribio-cyan text-tribio-cyan bg-tribio-cyan/10' : 'border-white/15 text-white/60'">{{ ContactEvent::TYPES[$type]['icon'] }} {{ ContactEvent::TYPES[$type]['label'] }}</span>
                </label>
            @endforeach
        </div>
        <textarea name="body" rows="2" maxlength="2000" class="input-field" placeholder="¿Qué pasó? Ej.: le escribí por WhatsApp, quiere el pack para el lunes" aria-label="Qué pasó" required></textarea>
        <button type="submit" class="btn-primary text-xs sm:text-sm">Guardar en su historia</button>
    </form>
    @endif

    {{-- Historia --}}
    <div class="glass-card p-4 sm:p-6">
        <h3 class="text-white font-bold text-base mb-3">Historia</h3>
        <div class="space-y-2">
            @forelse($timeline as $entry)
                @if($entry['kind'] === 'order')
                    @php $order = $entry['order']; @endphp
                    <a href="{{ route('dashboard.pedidos.show', $order) }}" class="rounded-xl border border-white/10 bg-white/5 p-3 flex items-center justify-between gap-3 hover:border-tribio-cyan/40 transition">
                        <div class="min-w-0">
                            <p class="text-sm text-white font-semibold">🛒 Pedido #{{ $order->order_number }}</p>
                            <p class="text-[11px] text-white/40">{{ $order->created_at->locale('es')->isoFormat('D MMM YYYY, HH:mm') }}</p>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <p class="text-sm text-white font-semibold whitespace-nowrap">{{ \App\Helpers\CurrencyHelper::symbol($order->currency ?: 'PEN') }} {{ number_format((float) $order->total, 2) }}</p>
                            <span class="badge {{ $order->status_badge }}">{{ $order->status_label }}</span>
                            <span class="badge {{ $order->payment_status_badge }}">{{ $order->payment_status_label }}</span>
                        </div>
                    </a>
                @else
                    @php $event = $entry['event']; @endphp
                    <div class="rounded-xl border border-white/10 bg-white/5 p-3">
                        <p class="text-[11px] text-white/40">{{ ContactEvent::TYPES[$event->type]['icon'] ?? '•' }} {{ ContactEvent::TYPES[$event->type]['label'] ?? $event->type }} · {{ $event->occurred_at->locale('es')->isoFormat('D MMM YYYY, HH:mm') }}{{ $event->user ? ' · ' . $event->user->name : '' }}</p>
                        <p class="text-sm text-white/80 mt-1 whitespace-pre-line">{{ $event->body }}</p>
                    </div>
                @endif
            @empty
                <p class="text-sm text-white/60">Aún no hay nada en su historia.</p>
            @endforelse
        </div>
    </div>

    {{-- Datos --}}
    <div class="glass-card p-4 sm:p-6" x-data="{ open: {{ $errors->any() ? 'true' : 'false' }} }">
        <div class="flex items-center justify-between gap-3">
            <h3 class="text-white font-bold text-base">Datos</h3>
            @if($profile)<button type="button" class="btn-ghost text-xs" @click="open = !open" x-text="open ? 'Cerrar' : 'Editar'"></button>@endif
        </div>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-sm mt-3" x-show="!open">
            <div><dt class="text-[11px] text-white/40">Celular</dt><dd class="text-white">{{ $contact->phone_display ?? '—' }}</dd></div>
            <div><dt class="text-[11px] text-white/40">Correo</dt><dd class="text-white break-all">{{ $contact->email ?? '—' }}</dd></div>
            <div><dt class="text-[11px] text-white/40">Documento</dt><dd class="text-white">{{ $contact->document_number ? trim(($contact->document_type ? strtoupper($contact->document_type) . ' ' : '') . $contact->document_number) : '—' }}</dd></div>
            <div><dt class="text-[11px] text-white/40">Ciudad</dt><dd class="text-white">{{ $contact->city ?? '—' }}</dd></div>
            @if($profile)
                <div><dt class="text-[11px] text-white/40">Cumpleaños</dt><dd class="text-white">{{ $contact->birthday?->locale('es')->isoFormat('D [de] MMMM') ?? '—' }}</dd></div>
                <div><dt class="text-[11px] text-white/40">Etiquetas</dt><dd class="text-white">
                    @forelse($contact->tags ?? [] as $tagName)
                        <a href="{{ route('dashboard.clientes.index', ['etiqueta' => $tagName]) }}" class="badge badge-blue">{{ $tagName }}</a>
                    @empty — @endforelse
                </dd></div>
                <div><dt class="text-[11px] text-white/40">Ofertas y novedades</dt><dd class="text-white">{{ $contact->accepts_marketing ? 'Aceptó recibirlas' : 'No ha dado permiso' }}</dd></div>
            @endif
        </dl>
        @if($profile)
        <form method="POST" action="{{ route('dashboard.clientes.update', $contact->id) }}" class="mt-4 space-y-4" x-show="open" x-cloak>
            @csrf @method('PUT')
            @include('dashboard.contacts._form')
            <button type="submit" class="btn-primary text-xs sm:text-sm">Guardar datos</button>
        </form>
        @endif
    </div>
</div>
@endsection
