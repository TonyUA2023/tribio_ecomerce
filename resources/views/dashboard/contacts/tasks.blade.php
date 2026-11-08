@extends('layouts.dashboard')
@section('title', 'Seguimientos')
@section('page_title', '👥 Seguimientos')
@section('content')
<div class="w-full max-w-[1600px] mx-auto space-y-5">

    <div class="flex items-center justify-between gap-3 flex-wrap">
        <p class="text-sm text-white/60">A quién escribir o llamar, y cuándo. Lo vencido primero.</p>
        <a href="{{ route('dashboard.clientes.index') }}" class="text-xs text-tribio-cyan underline">← Clientes</a>
    </div>

    @if($errors->any())
        <div class="glass-card p-4" role="alert">@foreach($errors->all() as $error)<p class="text-xs text-red-300">{{ $error }}</p>@endforeach</div>
    @endif

    <form method="POST" action="{{ route('dashboard.clientes.tasks.store') }}" class="glass-card p-4 sm:p-5 grid grid-cols-1 sm:grid-cols-6 gap-2 items-end">
        @csrf
        <div class="sm:col-span-2">
            <label class="input-label" for="t-contact">Cliente</label>
            <select id="t-contact" name="contact_id" class="input-field">
                <option value="">Sin cliente</option>
                @foreach($contacts as $contact)<option value="{{ $contact->id }}" @selected((int) old('contact_id') === $contact->id)>{{ $contact->name }}</option>@endforeach
            </select>
        </div>
        <div class="sm:col-span-2">
            <label class="input-label" for="t-title">Qué hay que hacer</label>
            <input id="t-title" name="title" class="input-field" maxlength="160" value="{{ old('title') }}" required>
        </div>
        <div>
            <label class="input-label" for="t-due">Cuándo</label>
            <input id="t-due" type="datetime-local" name="due_at" class="input-field" value="{{ old('due_at', now()->addDay()->setTime(10, 0)->format('Y-m-d\TH:i')) }}" required>
        </div>
        <button type="submit" class="btn-primary text-xs sm:text-sm">Agendar</button>
    </form>

    @foreach(['overdue' => ['⏰ Vencidos', 'text-red-300'], 'today' => ['📅 Para hoy', 'text-white'], 'upcoming' => ['🗓️ Próximos', 'text-white']] as $group => [$heading, $color])
        @php $list = $$group; @endphp
        @if($list->isNotEmpty())
        <div class="glass-card p-4 sm:p-5">
            <h3 class="font-bold text-base {{ $color }}">{{ $heading }} <span class="text-white/40 font-normal text-sm">{{ $list->count() }}</span></h3>
            <div class="space-y-2 mt-3">
                @foreach($list as $task)
                <div class="rounded-xl border border-white/10 bg-white/5 p-3 flex items-center gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm text-white font-semibold">{{ $task->title }}</p>
                        <p class="text-[11px] text-white/50">
                            {{ $task->kind_label }} · {{ $task->due_label }}
                            @if($task->contact)
                                · <a href="{{ route('dashboard.clientes.show', $task->contact_id) }}" class="underline">{{ $task->contact->name }}</a>
                            @endif
                        </p>
                    </div>
                    @if($task->contact?->whatsapp_url)
                        <a href="{{ route('dashboard.clientes.whatsapp', [$task->contact_id, 'plantilla' => \App\Services\Crm\MessageTemplates::FOR_TASK_KIND[$task->kind] ?? 'follow_up', 'producto' => $task->product_id]) }}"
                           target="_blank" rel="noopener" class="btn-ghost text-xs flex-shrink-0" aria-label="WhatsApp a {{ $task->contact->name }}">WhatsApp</a>
                    @endif
                    <form method="POST" action="{{ route('dashboard.clientes.tasks.complete', $task->id) }}" class="flex-shrink-0">
                        @csrf
                        <button type="submit" class="btn-secondary text-xs">✓ Hecho</button>
                    </form>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    @endforeach

    @if($overdue->isEmpty() && $today->isEmpty() && $upcoming->isEmpty())
        <div class="glass-card p-6 text-center">
            <p class="text-white font-semibold">No tienes seguimientos pendientes. 👌</p>
            <p class="text-xs text-white/50 mt-1">Agenda uno arriba o desde la ficha de un cliente.</p>
        </div>
    @endif

    @if($done->isNotEmpty())
    <div class="glass-card p-4 sm:p-5">
        <h3 class="text-white font-bold text-base">Hechos recientemente</h3>
        <div class="space-y-1 mt-2">
            @foreach($done as $task)
            <div class="flex items-center justify-between gap-3 text-sm">
                <span class="text-white/60 line-through truncate">{{ $task->title }}{{ $task->contact ? ' · ' . $task->contact->name : '' }}</span>
                <form method="POST" action="{{ route('dashboard.clientes.tasks.complete', $task->id) }}" class="flex-shrink-0">
                    @csrf
                    <button type="submit" class="text-xs text-white/40 hover:text-tribio-cyan">Reabrir</button>
                </form>
            </div>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection
