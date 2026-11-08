@extends('layouts.dashboard')
@section('title', $contact->name . ' — Clientes')
@section('page_title', '👥 Clientes')
@section('content')
@php
    use App\Models\Contact;
    use App\Models\ContactEvent;
    $money = fn ($value) => 'S/ ' . number_format((float) $value, 2);
    $phoneDigits = preg_replace('/\D+/', '', (string) $contact->phone);
    $crm = (bool) auth()->user()->currentStore()?->hasCrm();
    if (!$crm) { $chat = null; $apiReady = false; }
@endphp
<div class="cl-app cl-show">

    <a href="{{ route('dashboard.clientes.index') }}" class="cl-back" aria-label="Volver a Clientes"><x-dashboard-icon name="back"/>Clientes</a>

    @if($errors->any())
        <div class="cl-error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
    @endif

    <header class="cl-profile">
        <span class="cl-avatar is-big" aria-hidden="true">{{ mb_strtoupper(mb_substr($contact->name, 0, 1)) }}</span>
        <div class="cl-profile-main">
            <h2>{{ $contact->name }}</h2>
            <p>
                @if($contact->relationship){{ Contact::RELATIONSHIPS[$contact->relationship] ?? '' }} · @endif{{ $contact->phone_display ?? ($contact->email ?? 'Sin datos de contacto') }}
            </p>
            <div class="cl-badges">
                @if($profile && $contact->stage_label)<span class="badge {{ $contact->stage_badge }}">{{ $contact->stage_label }}</span>@endif
                @if($debt !== null && ($debt > 0 || $contact->credit_limit !== null))
                    <a href="{{ route('dashboard.receivables.show', $contact->id) }}" class="badge {{ $debt > 0 ? 'badge-gold' : 'badge-gray' }}">{{ $debt > 0 ? 'Debe S/ ' . number_format($debt, 2) : 'Sin deudas' }}</a>
                @endif
            </div>
        </div>
    </header>

    <div class="cl-actions">
        @if($phoneDigits)<a href="tel:{{ $phoneDigits }}" class="btn-secondary"><x-dashboard-icon name="phone"/>Llamar</a>@endif
        @if($chat)
            <a href="{{ route('dashboard.whatsapp.show', $chat->id) }}" class="btn-secondary">💬 Chat{{ $chat->unread_count > 0 ? ' (' . $chat->unread_count . ')' : '' }}</a>
        @elseif($apiReady && $contact->phone)
            <form method="POST" action="{{ route('dashboard.whatsapp.start', $contact->id) }}">
                @csrf
                <button type="submit" class="btn-secondary" title="Escribirle desde tu número conectado, con una plantilla aprobada">💬 Chat</button>
            </form>
        @endif
        @if($contact->whatsapp_url && $profile && $crm)
            <div class="cl-menu" x-data="{ open: false }" @keydown.escape="open = false">
                <button type="button" class="btn-primary" @click="open = !open" :aria-expanded="open" aria-haspopup="true">WhatsApp ▾</button>
                <div class="cl-menu-list is-wide" x-show="open" x-cloak @click.outside="open = false">
                    @foreach($templates as $template)
                        @if($template['promo'] && !$contact->accepts_marketing)
                            <span class="is-off" title="No aceptó recibir ofertas">{{ $template['name'] }} · sin permiso</span>
                        @elseif(str_contains($template['body'], '{saldo}') && !$hasBalance)
                            <span class="is-off" title="No tiene saldo pendiente">{{ $template['name'] }} · sin saldo</span>
                        @else
                            <a href="{{ route('dashboard.clientes.whatsapp', [$contact->id, 'plantilla' => $template['key']]) }}" target="_blank" rel="noopener" @click="open = false">{{ $template['name'] }}</a>
                        @endif
                    @endforeach
                    <a href="{{ $contact->whatsapp_url }}" target="_blank" rel="noopener" class="is-soft">Escribir sin plantilla</a>
                    <a href="{{ route('dashboard.clientes.templates.index') }}" class="is-link">Editar plantillas</a>
                </div>
            </div>
        @elseif($contact->whatsapp_url)
            <a href="{{ $contact->whatsapp_url }}" target="_blank" rel="noopener" class="btn-primary">WhatsApp</a>
        @endif
    </div>

    <div class="cl-stats">
        <div><small>Compras</small><strong>{{ $contact->orders_count }}</strong></div>
        <div><small>Total pagado</small><strong>{{ $money($contact->total_spent) }}</strong></div>
        <div><small>Última compra</small><strong class="is-small">{{ $contact->last_order_at?->locale('es')->diffForHumans() ?? '—' }}</strong></div>
    </div>

    @if($contact->notes)
        <div class="cl-note"><small>Nota fija</small><p>{{ $contact->notes }}</p></div>
    @endif

    @if($profile && $crm)
    {{-- Seguimientos --}}
    <section class="cl-section" x-data="{ open: false }" aria-labelledby="cl-tasks">
        <div class="cl-section-head">
            <h3 id="cl-tasks">Seguimientos @if($tasks->isNotEmpty())<span class="cl-count">{{ $tasks->count() }}</span>@endif</h3>
            <button type="button" class="cl-add" @click="open = !open" :aria-expanded="open" x-text="open ? 'Cerrar' : '+ Agendar'">+ Agendar</button>
        </div>
        <form method="POST" action="{{ route('dashboard.clientes.tasks.store') }}" class="cl-form" x-show="open" x-cloak>
            @csrf
            <input type="hidden" name="contact_id" value="{{ $contact->id }}">
            <div>
                <label class="input-label" for="task-title">Nuevo seguimiento</label>
                <input id="task-title" name="title" class="input-field" maxlength="160" placeholder="Ej.: llamar para ver si le gustó" required>
            </div>
            <div>
                <label class="input-label" for="task-due">Para cuándo</label>
                <input id="task-due" type="datetime-local" name="due_at" class="input-field" value="{{ now()->addDay()->setTime(10, 0)->format('Y-m-d\TH:i') }}" required>
            </div>
            <button type="submit" class="btn-primary">Agendar</button>
        </form>
        <div class="cl-items">
            @forelse($tasks as $task)
            <div class="cl-item">
                <div class="cl-item-main">
                    <strong>{{ $task->title }}</strong>
                    <small class="{{ $task->isOverdue() ? 'is-late' : '' }}">{{ $task->kind_label }} · {{ $task->due_label }}{{ $task->isOverdue() ? ' · vencido' : '' }}</small>
                </div>
                <div class="cl-item-side">
                    @if($contact->whatsapp_url)
                        <a href="{{ route('dashboard.clientes.whatsapp', [$contact->id, 'plantilla' => \App\Services\Crm\MessageTemplates::FOR_TASK_KIND[$task->kind] ?? 'follow_up', 'producto' => $task->product_id]) }}" target="_blank" rel="noopener" class="btn-ghost">WhatsApp</a>
                    @endif
                    <form method="POST" action="{{ route('dashboard.clientes.tasks.complete', $task->id) }}">
                        @csrf
                        <button type="submit" class="btn-secondary">✓ Hecho</button>
                    </form>
                </div>
            </div>
            @empty
                <p class="cl-none">Nada pendiente con {{ $contact->name }}.</p>
            @endforelse
        </div>
    </section>

    {{-- Oportunidades --}}
    @if($deals !== null)
    <section class="cl-section" x-data="{ open: false }" aria-labelledby="cl-deals">
        <div class="cl-section-head">
            <h3 id="cl-deals">Oportunidades</h3>
            <span class="cl-head-links">
                <a href="{{ route('dashboard.clientes.deals.index') }}">Ver embudo</a>
                <button type="button" class="cl-add" @click="open = !open" :aria-expanded="open" x-text="open ? 'Cerrar' : '+ Nueva'">+ Nueva</button>
            </span>
        </div>
        <form method="POST" action="{{ route('dashboard.clientes.deals.store') }}" class="cl-form is-grid" x-show="open" x-cloak>
            @csrf
            <input type="hidden" name="contact_id" value="{{ $contact->id }}">
            <div class="is-full">
                <label class="input-label" for="new-deal-title">Nueva oportunidad</label>
                <input id="new-deal-title" name="title" class="input-field" maxlength="120" placeholder="Ej.: quiere ser distribuidora" required>
            </div>
            <div>
                <label class="input-label" for="new-deal-amount">Monto (S/)</label>
                <input id="new-deal-amount" type="number" inputmode="decimal" name="amount" min="0" step="0.01" class="input-field">
            </div>
            <div>
                <label class="input-label" for="new-deal-date">Cierre</label>
                <input id="new-deal-date" type="date" name="expected_close_at" class="input-field">
            </div>
            <button type="submit" class="btn-primary is-full">Crear</button>
        </form>
        <div class="cl-items">
            @forelse($deals as $deal)
                @include('dashboard.contacts._deal_card', ['deal' => $deal, 'orders' => $orders->take(10), 'showContact' => false])
            @empty
                <p class="cl-none">Sin oportunidades con {{ $contact->name }}.</p>
            @endforelse
        </div>
    </section>
    @endif
    @endif

    {{-- Historia --}}
    <section class="cl-section" x-data="{ open: false, type: 'note' }" aria-labelledby="cl-history">
        <div class="cl-section-head">
            <h3 id="cl-history">Historia</h3>
            @if($profile)<button type="button" class="cl-add" @click="open = !open" :aria-expanded="open" x-text="open ? 'Cerrar' : '+ Anotar'">+ Anotar</button>@endif
        </div>
        @if($profile)
        <form method="POST" action="{{ route('dashboard.clientes.events.store', $contact->id) }}" class="cl-form" x-show="open" x-cloak>
            @csrf
            <div class="cl-types" role="radiogroup" aria-label="Tipo de anotación">
                @foreach(ContactEvent::MANUAL_TYPES as $type)
                    <label>
                        <input type="radio" name="type" value="{{ $type }}" class="sr-only" x-model="type" aria-label="{{ ContactEvent::TYPES[$type]['label'] }}">
                        <span class="pos-chip" :class="type === '{{ $type }}' && 'is-active'">{{ ContactEvent::TYPES[$type]['icon'] }} {{ ContactEvent::TYPES[$type]['label'] }}</span>
                    </label>
                @endforeach
            </div>
            <textarea name="body" rows="3" maxlength="2000" class="input-field" placeholder="¿Qué pasó? Ej.: le escribí por WhatsApp, quiere el pack para el lunes" aria-label="Qué pasó" required></textarea>
            <button type="submit" class="btn-primary">Guardar en su historia</button>
        </form>
        @endif
        <div class="cl-items">
            @forelse($timeline as $entry)
                @if($entry['kind'] === 'order')
                    @php $order = $entry['order']; $isSale = $order->source === 'pos'; @endphp
                    <a href="{{ $isSale ? route('dashboard.pos.receipt', $order->id) : route('dashboard.pedidos.show', $order) }}" @if($isSale) target="_blank" rel="noopener" @endif class="cl-item is-link">
                        <div class="cl-item-main">
                            <strong>{{ $isSale ? '🧾 Venta ' . ($order->document_code ?? $order->order_number) : '🛒 Pedido #' . $order->order_number }}</strong>
                            <small>{{ $order->created_at->locale('es')->isoFormat('D MMM YYYY, HH:mm') }}</small>
                        </div>
                        <div class="cl-item-side is-stack">
                            <strong>{{ \App\Helpers\CurrencyHelper::symbol($order->currency ?: 'PEN') }} {{ number_format((float) $order->total, 2) }}</strong>
                            @if($isSale)
                                @if((float) $order->balance_due > 0.004)<span class="badge badge-gold">Debe {{ $money($order->balance_due) }}</span>@endif
                            @else
                                <span><span class="badge {{ $order->status_badge }}">{{ $order->status_label }}</span> <span class="badge {{ $order->payment_status_badge }}">{{ $order->payment_status_label }}</span></span>
                            @endif
                        </div>
                    </a>
                @else
                    @php $event = $entry['event']; @endphp
                    <div class="cl-item is-event">
                        <small>{{ ContactEvent::TYPES[$event->type]['icon'] ?? '•' }} {{ ContactEvent::TYPES[$event->type]['label'] ?? $event->type }} · {{ $event->occurred_at->locale('es')->isoFormat('D MMM YYYY, HH:mm') }}{{ $event->user ? ' · ' . $event->user->name : '' }}</small>
                        <p>{{ $event->body }}</p>
                    </div>
                @endif
            @empty
                <p class="cl-none">Aún no hay nada en su historia.</p>
            @endforelse
        </div>
    </section>

    {{-- Datos --}}
    <section class="cl-section" x-data="{ open: {{ $errors->any() ? 'true' : 'false' }} }" aria-labelledby="cl-data">
        <div class="cl-section-head">
            <h3 id="cl-data">Datos</h3>
            @if($profile)<button type="button" class="cl-add" @click="open = !open" :aria-expanded="open" x-text="open ? 'Cerrar' : 'Editar'">Editar</button>@endif
        </div>
        <dl class="cl-data" x-show="!open">
            <div><dt>Celular</dt><dd>{{ $contact->phone_display ?? '—' }}</dd></div>
            <div><dt>Correo</dt><dd class="break-all">{{ $contact->email ?? '—' }}</dd></div>
            <div><dt>Documento</dt><dd>{{ $contact->document_number ? trim(($contact->document_type ? strtoupper($contact->document_type) . ' ' : '') . $contact->document_number) : '—' }}</dd></div>
            <div><dt>Ciudad</dt><dd>{{ $contact->city ?? '—' }}</dd></div>
            <div><dt>Primera compra</dt><dd>{{ $contact->first_order_at?->locale('es')->isoFormat('D MMM YYYY') ?? '—' }}</dd></div>
            @if($profile)
                <div><dt>Cumpleaños</dt><dd>{{ $contact->birthday?->locale('es')->isoFormat('D [de] MMMM') ?? '—' }}</dd></div>
                <div><dt>Etiquetas</dt><dd>
                    @forelse($contact->tags ?? [] as $tagName)<a href="{{ route('dashboard.clientes.index', ['etiqueta' => $tagName]) }}" class="badge badge-blue">{{ $tagName }}</a> @empty — @endforelse
                </dd></div>
                <div><dt>Ofertas y novedades</dt><dd>{{ $contact->accepts_marketing ? 'Aceptó recibirlas' : 'No ha dado permiso' }}</dd></div>
            @endif
        </dl>
        @if($profile)
        <form method="POST" action="{{ route('dashboard.clientes.update', $contact->id) }}" class="cl-form" x-show="open" x-cloak>
            @csrf @method('PUT')
            @include('dashboard.contacts._form')
            <button type="submit" class="btn-primary">Guardar datos</button>
        </form>
        @endif
    </section>
</div>
@endsection
