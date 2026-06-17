@extends('layouts.dashboard')
@section('title','Historial: ' . $product->name) @section('page_title','📦 Historial: ' . $product->name)
@section('content')
<div class="glass-card p-6 mb-6">
    <h3 class="text-white font-bold mb-4">Ajuste de stock</h3>
    <form method="POST" action="{{ route('dashboard.inventario.adjust', $product) }}" class="flex flex-wrap gap-4 items-end">@csrf
        <div><label class="input-label">Tipo</label>
            <select name="type" class="input-field w-40">
                <option value="in">Entrada</option>
                <option value="out">Salida</option>
                <option value="adjustment">Ajuste absoluto</option>
                <option value="loss">Pérdida</option>
            </select></div>
        <div><label class="input-label">Cantidad</label><input type="number" name="quantity" class="input-field w-32" min="1" value="1" required></div>
        <div class="flex-1"><label class="input-label">Motivo</label><input type="text" name="reason" class="input-field" placeholder="Compra, ajuste, etc."></div>
        <button type="submit" class="btn-primary">Aplicar</button>
    </form>
</div>
<div class="glass-card overflow-hidden">
    <h3 class="text-white font-bold p-5 border-b" style="border-color:rgba(255,255,255,0.08);">Movimientos de inventario</h3>
    <table class="w-full text-sm">
        <thead><tr class="border-b" style="border-color:rgba(255,255,255,0.08);">
            <th class="text-left py-3 px-4 text-white/50">Tipo</th>
            <th class="text-left py-3 px-4 text-white/50">Cantidad</th>
            <th class="text-left py-3 px-4 text-white/50">Antes → Después</th>
            <th class="text-left py-3 px-4 text-white/50">Motivo</th>
            <th class="text-left py-3 px-4 text-white/50">Por</th>
            <th class="text-left py-3 px-4 text-white/50">Fecha</th>
        </tr></thead>
        <tbody>@foreach($movements as $m)
        <tr class="border-b hover:bg-white/3" style="border-color:rgba(255,255,255,0.04);">
            <td class="py-3 px-4"><span class="badge {{ in_array($m->type,['in','return']) ? 'badge-green' : (in_array($m->type,['out','sale','loss']) ? 'badge-red' : 'badge-yellow') }}">{{ $m->type_label }}</span></td>
            <td class="py-3 px-4 text-white font-bold {{ $m->quantity > 0 ? 'text-green-400' : 'text-red-400' }}">{{ $m->quantity > 0 ? '+' : '' }}{{ $m->quantity }}</td>
            <td class="py-3 px-4 text-white/60">{{ $m->stock_before }} → {{ $m->stock_after }}</td>
            <td class="py-3 px-4 text-white/60 text-xs">{{ $m->reason ?? '—' }}</td>
            <td class="py-3 px-4 text-white/40 text-xs">{{ $m->user?->name ?? 'Sistema' }}</td>
            <td class="py-3 px-4 text-white/40 text-xs">{{ $m->created_at->format('d/m/Y H:i') }}</td>
        </tr>@endforeach</tbody>
    </table>
    <div class="p-4">{{ $movements->links() }}</div>
</div>
@endsection
