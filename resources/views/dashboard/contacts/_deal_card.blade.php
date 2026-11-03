{{-- One opportunity with its actions. Needs $deal; optional $orders (the customer's orders, to link a win), $showContact and $showStage. --}}
@php use App\Models\Deal; @endphp
<div class="rounded-xl border border-white/10 bg-white/5 p-3" x-data="{ action: null }">
    <div class="flex items-start justify-between gap-2">
        <div class="min-w-0">
            <p class="text-sm text-white font-semibold">{{ $deal->title }}</p>
            <p class="text-[11px] text-white/50">
                @if($showContact ?? true)
                    <a href="{{ route('dashboard.clientes.show', $deal->contact_id) }}" class="underline">{{ $deal->contact?->name }}</a> ·
                @endif
                {{ $deal->amount !== null ? 'S/ ' . number_format((float) $deal->amount, 2) : 'sin monto' }}
                @if($deal->expected_close_at)
                    · <span class="{{ $deal->isLate() ? 'text-red-300' : '' }}">cierra {{ $deal->expected_close_at->locale('es')->isoFormat('D MMM') }}{{ $deal->isLate() ? ' (atrasada)' : '' }}</span>
                @endif
            </p>
        </div>
        @if($showStage ?? true)
            <span class="badge {{ $deal->stage_badge }} flex-shrink-0">{{ $deal->stage_label }}</span>
        @endif
    </div>

    @if($deal->isOpen())
    <div class="flex items-center gap-2 flex-wrap mt-2">
        <form method="POST" action="{{ route('dashboard.clientes.deals.move', $deal->id) }}">
            @csrf
            <label class="sr-only" for="move-{{ $deal->id }}">Mover «{{ $deal->title }}» a</label>
            <select id="move-{{ $deal->id }}" name="stage" class="input-field !py-1 !text-xs !w-auto" onchange="this.form.submit()">
                @foreach(Deal::OPEN_STAGES as $key => $label)
                    <option value="{{ $key }}" @selected($deal->stage === $key)>{{ $deal->stage === $key ? 'Etapa: ' : 'Mover a ' }}{{ $label }}</option>
                @endforeach
            </select>
        </form>
        <button type="button" class="btn-secondary !py-1 text-xs" @click="action = action === 'win' ? null : 'win'">🏆 Ganada</button>
        <button type="button" class="btn-ghost !py-1 text-xs" @click="action = action === 'lose' ? null : 'lose'">Perdida</button>
    </div>

    <form method="POST" action="{{ route('dashboard.clientes.deals.win', $deal->id) }}" class="mt-2 flex items-end gap-2 flex-wrap" x-show="action === 'win'" x-cloak>
        @csrf
        @if(!empty($orders) && $orders->isNotEmpty())
            <div class="flex-1 min-w-0">
                <label class="input-label" for="win-order-{{ $deal->id }}">¿Con qué pedido? <span class="text-white/40 font-normal">(opcional)</span></label>
                <select id="win-order-{{ $deal->id }}" name="order_id" class="input-field !py-1 !text-xs">
                    <option value="">Sin pedido</option>
                    @foreach($orders as $order)
                        <option value="{{ $order->id }}">#{{ $order->order_number }} · S/ {{ number_format((float) $order->total, 2) }} · {{ $order->created_at->locale('es')->isoFormat('D MMM') }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <button type="submit" class="btn-primary !py-1 text-xs">Confirmar</button>
    </form>

    <form method="POST" action="{{ route('dashboard.clientes.deals.lose', $deal->id) }}" class="mt-2 grid grid-cols-1 sm:grid-cols-3 gap-2 items-end" x-show="action === 'lose'" x-cloak>
        @csrf
        <div>
            <label class="input-label" for="lose-reason-{{ $deal->id }}">¿Por qué?</label>
            <select id="lose-reason-{{ $deal->id }}" name="lost_reason" class="input-field !py-1 !text-xs">
                @foreach(Deal::LOST_REASONS as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="input-label" for="lose-note-{{ $deal->id }}">Detalle <span class="text-white/40 font-normal">(opcional)</span></label>
            <input id="lose-note-{{ $deal->id }}" name="lost_note" class="input-field !py-1 !text-xs" maxlength="255">
        </div>
        <button type="submit" class="btn-secondary !py-1 text-xs">Marcar perdida</button>
    </form>
    @else
    <div class="flex items-center gap-3 mt-2 text-[11px] text-white/50">
        <span>
            {{ $deal->stage === 'ganada' ? 'Ganada' : 'Perdida' }} {{ $deal->closed_at?->locale('es')->diffForHumans() }}
            @if($deal->stage === 'perdida' && $deal->lost_reason) · {{ Deal::LOST_REASONS[$deal->lost_reason] ?? $deal->lost_reason }} @endif
            @if($deal->order) · pedido #{{ $deal->order->order_number }} @endif
        </span>
        <form method="POST" action="{{ route('dashboard.clientes.deals.reopen', $deal->id) }}">
            @csrf
            <button type="submit" class="text-tribio-cyan hover:underline">Reabrir</button>
        </form>
    </div>
    @endif
</div>
