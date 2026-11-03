{{-- ERP Fase 4d: in "modo caja", a cashier needs a manager's PIN for restricted actions. Renders nothing otherwise. --}}
@props(['id' => 'x'])
@php
    $posStore = auth()->user()?->currentStore();
    $posAccess = app(\App\Services\Pos\PosAccess::class);
    $posPerson = $posStore && $posAccess->active($posStore) ? $posAccess->current($posStore) : null;
@endphp
@if($posPerson && !$posPerson->isManager())
    <div {{ $attributes->merge(['class' => 'rounded-xl border border-amber-300/30 bg-amber-300/5 p-2']) }}>
        <label class="input-label" for="approver-pin-{{ $id }}">PIN de un encargado</label>
        <input id="approver-pin-{{ $id }}" name="approver_pin" type="password" inputmode="numeric" autocomplete="off" maxlength="6" class="input-field !py-1" placeholder="Lo escribe el encargado">
    </div>
@endif
