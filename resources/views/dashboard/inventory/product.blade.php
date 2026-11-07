@extends('layouts.dashboard')
@section('title', 'Inventario: ' . $product->name)
@section('page_title', '📦 Inventario')
@section('content')
<div class="w-full max-w-5xl mx-auto space-y-5">

    <div class="glass-card px-3.5 sm:px-5 py-2.5 sm:py-3 flex items-center gap-3">
        <a href="{{ route('dashboard.inventario.index') }}" class="btn-ghost !px-2.5 !py-2 flex-shrink-0" aria-label="Volver a Inventario" title="Volver a Inventario">←</a>
        <div class="min-w-0 flex-1">
            <p class="text-[10px] text-white/40 uppercase tracking-wider leading-none mb-0.5">Inventario</p>
            <p class="text-white font-bold text-sm truncate">{{ $product->name }}</p>
            @if($unitsReady && $product->stockInUnits())
                <p class="text-[11px] text-white/50">= {{ $product->stockInUnits() }}</p>
            @endif
        </div>
        @if($isKit)<span class="badge badge-purple flex-shrink-0">Combo</span>@endif
        @if($product->track_stock)
            <span class="badge {{ $product->stock === 0 ? 'badge-red' : ($product->isLowStock() ? 'badge-gold' : 'badge-green') }} flex-shrink-0">{{ $product->stock }} {{ in_array($product->unit, [null, '', 'unidad', 'unidades'], true) ? 'und.' : $product->unit }}</span>
        @else
            <span class="badge badge-gray flex-shrink-0">Sin control de stock</span>
        @endif
    </div>

    @if($errors->any())
        <div class="glass-card p-4" role="alert">@foreach($errors->all() as $error)<p class="text-xs text-red-300">{{ $error }}</p>@endforeach</div>
    @endif

    @if($isKit)
    <div class="glass-card p-4 sm:p-6">
        <h3 class="text-white font-bold text-base">Es un combo</h3>
        <p class="text-xs text-white/60 mt-1">Su stock ({{ $product->stock }}) es cuántos combos alcanzan con lo que tienes de cada producto. Al venderlo se descuentan sus productos, y su costo es lo que costaron.</p>
    </div>
    @else
    {{-- Registrar movimiento --}}
    <div class="glass-card p-4 sm:p-6" x-data="{ type: '{{ old('type', 'in') }}' }">
        <h3 class="text-white font-bold text-base">Registrar movimiento</h3>
        <p class="text-xs text-white/50 mt-1" x-show="type === 'in'">Llegó mercadería: anota cuánto te costó cada unidad y, si tiene, su lote y vencimiento. Así sabrás cuánto ganas en cada venta.</p>
        <p class="text-xs text-white/50 mt-1" x-show="type === 'adjustment'" x-cloak>Escribe el stock real que contaste; ajustamos la diferencia.</p>
        <p class="text-xs text-white/50 mt-1" x-show="type === 'out' || type === 'loss'" x-cloak>Sale primero lo que vence antes y, si no tiene fecha, lo más antiguo.</p>

        <form method="POST" action="{{ route('dashboard.inventario.adjust', $product) }}" class="mt-4 space-y-4">@csrf
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="col-span-2 sm:col-span-1">
                    <label class="input-label" for="mov-type">Tipo</label>
                    <select id="mov-type" name="type" class="input-field" x-model="type">
                        <option value="in">Entrada</option>
                        <option value="out">Salida</option>
                        <option value="loss">Pérdida / merma</option>
                        <option value="adjustment">Conteo (stock real)</option>
                    </select>
                </div>
                <div>
                    <label class="input-label" for="mov-qty" x-text="type === 'adjustment' ? 'Stock contado' : 'Cantidad'">Cantidad</label>
                    <input id="mov-qty" type="number" name="quantity" class="input-field" min="1" value="{{ old('quantity', 1) }}" required>
                </div>
                <div x-show="type === 'in'">
                    <label class="input-label" for="mov-cost">Costo por unidad (S/)</label>
                    <input id="mov-cost" type="number" name="unit_cost" class="input-field" min="0" step="0.01" value="{{ old('unit_cost') }}" placeholder="{{ $product->cost_price ? number_format((float) $product->cost_price, 2, '.', '') : '0.00' }}">
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" x-show="type === 'in'">
                <div>
                    <label class="input-label" for="mov-lot">Lote <span class="text-white/40 font-normal">(opcional)</span></label>
                    <input id="mov-lot" type="text" name="lot_code" class="input-field" maxlength="40" value="{{ old('lot_code') }}" placeholder="Ej.: L2311A">
                </div>
                <div>
                    <label class="input-label" for="mov-exp">Vence <span class="text-white/40 font-normal">(opcional)</span></label>
                    <input id="mov-exp" type="date" name="expires_at" class="input-field" value="{{ old('expires_at') }}">
                </div>
            </div>
            <div>
                <label class="input-label" for="mov-reason">Motivo <span class="text-white/40 font-normal">(opcional)</span></label>
                <input id="mov-reason" type="text" name="reason" class="input-field" maxlength="255" value="{{ old('reason') }}" placeholder="Compra a proveedor, rotura, conteo…">
            </div>
            <button type="submit" class="btn-primary text-xs sm:text-sm">Guardar movimiento</button>
        </form>
    </div>
    @endif

    @if($reorderReady)
    {{-- Recompra --}}
    <form method="POST" action="{{ route('dashboard.inventario.reorder-days.update', $product->id) }}" class="glass-card p-4 sm:p-6">
        @csrf @method('PUT')
        <h3 class="text-white font-bold text-base">🔁 Recompra</h3>
        <p class="text-xs text-white/50 mt-1">¿Cuántos días le dura una unidad a un cliente? Con eso te avisamos antes de que se le acabe, con el WhatsApp ya escrito. Déjalo vacío si este producto no se recompra.</p>
        <div class="flex items-end gap-3 flex-wrap mt-3">
            <div>
                <label class="input-label" for="consumption-days">Días que dura 1 unidad</label>
                <input id="consumption-days" type="number" name="consumption_days" min="1" max="365" class="input-field !w-32" value="{{ old('consumption_days', $product->consumption_days) }}" placeholder="Ej.: 30">
            </div>
            <button type="submit" class="btn-secondary text-xs sm:text-sm">Guardar</button>
        </div>
        @if($product->consumption_days && auth()->user()->currentStore()?->hasCrm())
            <p class="text-[11px] text-white/50 mt-2">{{ $reorderPending }} {{ $reorderPending === 1 ? 'recompra agendada' : 'recompras agendadas' }} en <a href="{{ route('dashboard.clientes.tasks.index') }}" class="underline">Seguimientos</a>.</p>
        @endif
    </form>
    @endif

    @if($unitsReady && $inCombos->isNotEmpty())
    <div class="glass-card p-4 sm:p-6">
        <h3 class="text-white font-bold text-base">🎁 Parte de {{ $inCombos->count() === 1 ? 'un combo' : $inCombos->count() . ' combos' }}</h3>
        <p class="text-xs text-white/60 mt-1">
            @foreach($inCombos as $link)
                <a href="{{ route('dashboard.inventario.product', $link->kit_product_id) }}" class="underline">{{ $link->kit?->name }}</a> ({{ $link->quantity }} und por combo){{ $loop->last ? '.' : ' · ' }}
            @endforeach
            Cuando mueves su stock, el de esos combos se recalcula solo.
        </p>
    </div>
    @endif

    @if($unitsReady && $inCombos->isEmpty())
    {{-- Combo --}}
    <div class="glass-card p-4 sm:p-6" x-data="{ open: {{ $isKit ? 'true' : 'false' }}, rows: @js($components->map(fn ($c) => ['product_id' => $c->component_product_id, 'quantity' => $c->quantity])->values()) }">
        <button type="button" class="w-full flex items-center justify-between gap-3 text-left" @click="open = !open" :aria-expanded="open">
            <span>
                <span class="text-white font-bold text-base block">🎁 Combo</span>
                <span class="text-xs text-white/50">{{ $isKit ? $components->count() . ' productos · el stock se calcula solo' : 'Arma este producto con otros (un pack, un kit, una canasta).' }}</span>
            </span>
            <span class="text-white/40 text-sm" x-text="open ? 'Cerrar' : 'Configurar'"></span>
        </button>
        <form method="POST" action="{{ route('dashboard.inventario.kit.update', $product->id) }}" class="mt-4 space-y-3" x-show="open" x-cloak
              @submit="$refs.componentsJson.value = JSON.stringify(rows.filter(r => r.product_id))">
            @csrf @method('PUT')
            <input type="hidden" name="components_json" x-ref="componentsJson">
            <template x-for="(row, index) in rows" :key="index">
                <div class="flex items-end gap-2">
                    <div class="flex-1 min-w-0">
                        <label class="input-label" :for="'kc-' + index">Producto</label>
                        <select class="input-field" :id="'kc-' + index" x-model.number="row.product_id">
                            <option value="">Elige…</option>
                            @foreach($componentOptions as $option)
                                <option value="{{ $option->id }}">{{ $option->name }} ({{ $option->stock }} en stock)</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="w-24">
                        <label class="input-label" :for="'kq-' + index">Cantidad</label>
                        <input type="number" min="1" class="input-field" :id="'kq-' + index" x-model.number="row.quantity">
                    </div>
                    <button type="button" class="text-white/40 hover:text-red-300 text-lg leading-none pb-3" @click="rows.splice(index, 1)" aria-label="Quitar">×</button>
                </div>
            </template>
            <button type="button" class="btn-ghost text-xs" @click="rows.push({ product_id: '', quantity: 1 })">+ Agregar producto al combo</button>
            <p class="text-[11px] text-white/40">Solo productos con control de stock que no sean combos. Para dejar de ser combo, quita todos y guarda.</p>
            <button type="submit" class="btn-primary text-xs sm:text-sm">Guardar combo</button>
        </form>
    </div>
    @endif

    @if($unitsReady)

    {{-- Presentaciones --}}
    @unless($isKit)
    <div class="glass-card p-4 sm:p-6">
        <h3 class="text-white font-bold text-base">📦 Presentaciones</h3>
        <p class="text-xs text-white/50 mt-1">Cómo te lo vende tu proveedor: caja, saco, docena, rollo. El stock se sigue llevando en unidades; en Compras eliges la presentación y calculamos las unidades y su costo.</p>
        @if($product->units->isNotEmpty())
        <div class="space-y-2 mt-3">
            @foreach($product->units as $unit)
            <div class="rounded-xl border border-white/10 bg-white/5 p-3 flex items-center justify-between gap-3">
                <span class="text-sm text-white">{{ $unit->name }} <span class="text-white/50">= {{ $unit->factor }} und</span>@if($unit->barcode)<span class="text-white/40 text-xs"> · {{ $unit->barcode }}</span>@endif</span>
                <form method="POST" action="{{ route('dashboard.inventario.units.destroy', [$product->id, $unit->id]) }}">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-xs text-white/40 hover:text-red-300">Quitar</button>
                </form>
            </div>
            @endforeach
        </div>
        @endif
        <form method="POST" action="{{ route('dashboard.inventario.units.store', $product->id) }}" class="grid grid-cols-2 sm:grid-cols-4 gap-3 items-end mt-4">
            @csrf
            <div>
                <label class="input-label" for="unit-name">Nombre</label>
                <input id="unit-name" name="name" class="input-field" maxlength="40" placeholder="Caja" value="{{ old('name') }}" required>
            </div>
            <div>
                <label class="input-label" for="unit-factor">Trae (und)</label>
                <input id="unit-factor" type="number" name="factor" min="2" class="input-field" placeholder="100" value="{{ old('factor') }}" required>
            </div>
            <div>
                <label class="input-label" for="unit-barcode">Código <span class="text-white/40 font-normal">(opcional)</span></label>
                <input id="unit-barcode" name="barcode" class="input-field" maxlength="60" value="{{ old('barcode') }}">
            </div>
            <button type="submit" class="btn-secondary text-xs sm:text-sm">Agregar</button>
        </form>
    </div>
    @endunless
    @endif

    {{-- Lotes --}}
    @if($lotsReady && $product->track_stock)
    <div class="glass-card p-4 sm:p-6">
        <div class="flex items-center justify-between gap-3">
            <h3 class="text-white font-bold text-base">Lotes en stock</h3>
            <p class="text-xs text-white/50">Valor: S/ {{ number_format($lots->sum(fn ($lot) => $lot->quantity_remaining * (float) $lot->unit_cost), 2) }}</p>
        </div>
        @if($lots->isEmpty())
            <p class="text-sm text-white/60 mt-2">No hay unidades en stock.</p>
        @else
        <div class="space-y-2 mt-3">
            @foreach($lots as $lot)
            <div class="rounded-xl border border-white/10 bg-white/5 p-3 flex items-center justify-between gap-3 flex-wrap">
                <div class="min-w-0">
                    <p class="text-sm text-white font-semibold">{{ $lot->code ?: 'Sin código' }} <span class="text-white/40 font-normal text-xs">· {{ $lot->origin_label }} · {{ $lot->received_at->locale('es')->isoFormat('D MMM YYYY') }}</span></p>
                    <p class="text-xs mt-0.5">
                        @if($lot->expiry_state === 'expired')
                            <span class="badge badge-red">Vencido el {{ $lot->expires_at->locale('es')->isoFormat('D MMM YYYY') }}</span>
                        @elseif($lot->expiry_state === 'expiring')
                            <span class="badge badge-gold">Vence {{ $lot->expires_at->locale('es')->diffForHumans() }}</span>
                        @elseif($lot->expires_at)
                            <span class="text-white/50">Vence el {{ $lot->expires_at->locale('es')->isoFormat('D MMM YYYY') }}</span>
                        @else
                            <span class="text-white/40">Sin vencimiento</span>
                        @endif
                    </p>
                </div>
                <div class="text-right">
                    <p class="text-sm text-white font-semibold">{{ $lot->quantity_remaining }} <span class="text-white/40 font-normal">de {{ $lot->quantity_received }}</span></p>
                    <p class="text-[11px] text-white/50">{{ $lot->unit_cost !== null ? 'S/ ' . number_format((float) $lot->unit_cost, 2) . ' c/u' : 'Sin costo' }}</p>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>
    @endif

    {{-- Últimas compras --}}
    @if($purchases->isNotEmpty())
    <div class="glass-card p-4 sm:p-6">
        <h3 class="text-white font-bold text-base mb-3">Últimas compras</h3>
        <div class="space-y-2">
            @foreach($purchases as $item)
            <a href="{{ route('dashboard.compras.show', $item->purchase_order_id) }}" class="rounded-xl border border-white/10 bg-white/5 p-3 flex items-center justify-between gap-3 hover:border-tribio-cyan/40 transition">
                <span class="text-sm text-white min-w-0 truncate">{{ $item->purchaseOrder->supplier?->name ?? 'Sin proveedor' }} <span class="text-white/40 text-xs">· {{ $item->purchaseOrder->number }} · {{ $item->purchaseOrder->received_at?->locale('es')->isoFormat('D MMM YYYY') }}</span></span>
                <span class="text-right flex-shrink-0">
                    <span class="block text-sm text-white font-semibold whitespace-nowrap">S/ {{ number_format((float) $item->landed_unit_cost / max(1, (int) ($item->unit_factor ?? 1)), 2) }} c/u</span>
                    <span class="block text-[11px] text-white/40">
                        {{ $item->quantity_received * max(1, (int) ($item->unit_factor ?? 1)) }} und.
                        @if((int) ($item->unit_factor ?? 1) > 1) ({{ $item->quantity_received }} {{ $item->unit_name }}) @endif
                        @if((float) $item->landed_unit_cost > (float) $item->unit_cost)
                            · pagaste S/ {{ number_format((float) $item->unit_cost, 2) }} + gastos
                        @endif
                    </span>
                </span>
            </a>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Historial --}}
    <div class="glass-card p-4 sm:p-6">
        <h3 class="text-white font-bold text-base mb-3">Historial de movimientos</h3>
        <div class="space-y-2">
            @forelse($movements as $m)
            <div class="rounded-xl border border-white/10 bg-white/5 p-3 flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-sm text-white">
                        <span class="badge {{ in_array($m->type, ['in', 'return']) ? 'badge-green' : (in_array($m->type, ['out', 'sale', 'loss']) ? 'badge-red' : 'badge-gold') }}">{{ $m->type_label }}</span>
                        <span class="font-bold ml-1">{{ $m->quantity > 0 ? '+' : '' }}{{ $m->quantity }}</span>
                        <span class="text-white/40 text-xs ml-1">{{ $m->stock_before }} → {{ $m->stock_after }}</span>
                    </p>
                    <p class="text-xs text-white/60 mt-1">{{ $m->reason ?? '—' }}@if($m->reference && $m->type === 'sale') · Pedido {{ $m->reference }}@endif</p>
                </div>
                <div class="text-right flex-shrink-0">
                    @if($m->total_cost !== null)<p class="text-xs text-white font-semibold">S/ {{ number_format((float) $m->total_cost, 2) }}</p>@endif
                    <p class="text-[11px] text-white/40">{{ $m->user?->name ?? 'Sistema' }} · {{ $m->created_at->format('d/m/Y H:i') }}</p>
                </div>
            </div>
            @empty
                <p class="text-sm text-white/60">Aún no hay movimientos.</p>
            @endforelse
        </div>
        <div class="mt-4">{{ $movements->links() }}</div>
    </div>
</div>
@endsection
