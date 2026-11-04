@props(['href', 'icon', 'title', 'hint' => null])
{{-- Caja: tarjeta de acceso rápido a un módulo (proformas, devoluciones, turnos…). --}}
<a href="{{ $href }}" {{ $attributes->class(['pos-shortcut']) }}>
    <span class="pos-shortcut-icon"><x-dashboard-icon :name="$icon"/></span>
    <span class="pos-shortcut-text">
        <strong>{{ $title }}</strong>
        @if($hint)<small>{{ $hint }}</small>@endif
    </span>
    <x-dashboard-icon name="chevron" class="pos-shortcut-go"/>
</a>
