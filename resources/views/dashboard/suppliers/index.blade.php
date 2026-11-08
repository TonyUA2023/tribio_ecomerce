@extends('layouts.dashboard')
@section('title', 'Proveedores')
@section('page_title', '🧾 Proveedores')
@section('content')
<div class="w-full max-w-[1600px] mx-auto space-y-5">

    <div class="flex items-center justify-between gap-3 flex-wrap">
        <p class="text-sm text-white/60">A quién le compras tu mercadería.</p>
        <a href="{{ route('dashboard.compras.index') }}" class="text-xs text-tribio-cyan underline">← Compras</a>
    </div>

    <form method="POST" action="{{ route('dashboard.proveedores.store') }}" id="nuevo" class="glass-card p-4 sm:p-6 space-y-4">
        @csrf
        <h3 class="text-white font-bold text-base">Agregar proveedor</h3>
        @include('dashboard.suppliers._fields', ['supplier' => new \App\Models\Supplier()])
        <button type="submit" class="btn-primary text-xs sm:text-sm">Agregar</button>
    </form>

    <form method="GET" action="{{ route('dashboard.proveedores.index') }}" class="flex gap-2">
        <input type="search" name="buscar" value="{{ $search }}" class="input-field flex-1 min-w-0" placeholder="Nombre o RUC">
        <button type="submit" class="btn-secondary flex-shrink-0">Buscar</button>
    </form>

    @if($suppliers->isEmpty())
        <div class="glass-card p-6 text-center"><p class="text-white font-semibold">{{ $search ? 'Ningún proveedor coincide.' : 'Aún no tienes proveedores.' }}</p></div>
    @else
        <div class="space-y-2">
            @foreach($suppliers as $supplier)
            <a href="{{ route('dashboard.proveedores.edit', $supplier->id) }}" class="glass-card p-3.5 sm:p-4 flex items-center justify-between gap-3 hover:border-tribio-cyan/40 transition">
                <div class="min-w-0">
                    <p class="text-sm text-white font-semibold truncate">{{ $supplier->name }}</p>
                    <p class="text-[11px] text-white/50 truncate">{{ collect([$supplier->document_number ? 'RUC ' . $supplier->document_number : null, $supplier->contact_name, $supplier->phone])->filter()->implode(' · ') ?: 'Sin datos de contacto' }}</p>
                </div>
                <div class="text-right flex-shrink-0">
                    <p class="text-sm text-white font-semibold whitespace-nowrap">S/ {{ number_format((float) $supplier->received_total, 2) }}</p>
                    <p class="text-[11px] text-white/40">{{ $supplier->purchase_orders_count }} {{ $supplier->purchase_orders_count === 1 ? 'compra' : 'compras' }}</p>
                </div>
            </a>
            @endforeach
        </div>
        <div>{{ $suppliers->links() }}</div>
    @endif
</div>
@endsection
