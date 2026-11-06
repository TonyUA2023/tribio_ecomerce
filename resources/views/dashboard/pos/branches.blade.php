@extends('layouts.dashboard')
@section('title', 'Sedes y cajas')
@section('page_title', '🏬 Sedes y cajas')
@section('content')
<div class="w-full max-w-4xl mx-auto space-y-5">
    @include('dashboard.pos._documents-tabs', ['current' => 'branches'])

    <p class="text-sm text-white/60 max-w-3xl">Una <strong>sede</strong> es un local con dirección fiscal (la principal lleva el código 0000). Cada <strong>caja</strong> vende desde una sede y el comprobante sale con esa dirección. El stock y los clientes siguen siendo uno solo para todo el negocio.</p>

    @if($errors->any())
        <div class="glass-card p-4" role="alert">@foreach($errors->all() as $error)<p class="text-xs text-red-300">{{ $error }}</p>@endforeach</div>
    @endif

    {{-- Sedes --}}
    <section class="space-y-3" aria-labelledby="br-title">
        <h2 id="br-title" class="text-white font-bold text-base">Sedes</h2>
        @foreach($branches as $branch)
            @php $missing = $branch->fiscalProblems(); @endphp
            <details class="glass-card p-4" @if($missing) open @endif>
                <summary class="flex items-center justify-between gap-3 cursor-pointer min-h-[44px]">
                    <span class="min-w-0"><strong class="text-white text-sm">{{ $branch->name }}</strong> <span class="text-white/50 text-xs">· código {{ $branch->sunat_code }}</span>
                        @if($branch->is_main)<span class="badge badge-blue">Principal</span>@endif
                        @unless($branch->is_active)<span class="badge badge-gold">Desactivada</span>@endunless</span>
                    <span class="text-[11px] {{ $missing ? 'text-amber-300' : 'text-emerald-300' }}">{{ $missing ? 'Falta ' . implode(' y ', $missing) : '✓ Lista para SUNAT' }}</span>
                </summary>
                <form method="POST" action="{{ route('dashboard.pos.documents.branches.update', $branch->id) }}" class="grid grid-cols-1 sm:grid-cols-6 gap-3 mt-3">
                    @csrf @method('PUT')
                    <div class="sm:col-span-3"><label class="input-label">Nombre</label><input name="name" class="input-field" maxlength="80" value="{{ $branch->name }}" required></div>
                    <div class="sm:col-span-3"><label class="input-label">Dirección fiscal</label><input name="address" class="input-field" maxlength="200" value="{{ $branch->address }}" placeholder="Av. Los Constructores 123"></div>
                    <div><label class="input-label">Ubigeo</label><input name="ubigeo" class="input-field" inputmode="numeric" maxlength="6" value="{{ $branch->ubigeo }}" placeholder="140101"></div>
                    <div class="sm:col-span-1"><label class="input-label">Departamento</label><input name="department" class="input-field" maxlength="60" value="{{ $branch->department }}" placeholder="Lambayeque"></div>
                    <div class="sm:col-span-2"><label class="input-label">Provincia</label><input name="province" class="input-field" maxlength="60" value="{{ $branch->province }}" placeholder="Chiclayo"></div>
                    <div class="sm:col-span-2"><label class="input-label">Distrito</label><input name="district" class="input-field" maxlength="60" value="{{ $branch->district }}" placeholder="Chiclayo"></div>
                    @unless($branch->is_main)
                        <div><label class="input-label">Código SUNAT</label><input name="sunat_code" class="input-field" inputmode="numeric" maxlength="4" value="{{ $branch->sunat_code }}"></div>
                    @endunless
                    <div class="sm:col-span-2"><label class="input-label">Teléfono</label><input name="phone" class="input-field" maxlength="30" value="{{ $branch->phone }}"></div>
                    @unless($branch->is_main)
                        <label class="flex items-center gap-2 text-sm text-white/80 min-h-[44px] sm:col-span-2 self-end"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" class="w-5 h-5" @checked($branch->is_active)> Activa</label>
                    @endunless
                    <div class="sm:col-span-6 flex justify-end gap-2"><button type="submit" class="btn-primary text-sm">Guardar sede</button></div>
                </form>
                @unless($branch->is_main)
                    <form method="POST" action="{{ route('dashboard.pos.documents.branches.destroy', $branch->id) }}" class="mt-2" onsubmit="return confirm('¿Eliminar esta sede?')">@csrf @method('DELETE')<button type="submit" class="text-xs text-red-300 underline min-h-[44px]">Eliminar sede</button></form>
                @endunless
            </details>
        @endforeach

        <details class="glass-card p-4">
            <summary class="cursor-pointer text-sm font-bold text-white min-h-[44px] flex items-center">+ Agregar sede</summary>
            <form method="POST" action="{{ route('dashboard.pos.documents.branches.store') }}" class="grid grid-cols-1 sm:grid-cols-6 gap-3 mt-3">
                @csrf
                <div class="sm:col-span-3"><label class="input-label">Nombre</label><input name="name" class="input-field" maxlength="80" required placeholder="Sucursal Pimentel"></div>
                <div class="sm:col-span-3"><label class="input-label">Dirección fiscal</label><input name="address" class="input-field" maxlength="200"></div>
                <div><label class="input-label">Ubigeo</label><input name="ubigeo" class="input-field" inputmode="numeric" maxlength="6" placeholder="140101"></div>
                <div class="sm:col-span-1"><label class="input-label">Departamento</label><input name="department" class="input-field" maxlength="60"></div>
                <div class="sm:col-span-2"><label class="input-label">Provincia</label><input name="province" class="input-field" maxlength="60"></div>
                <div class="sm:col-span-2"><label class="input-label">Distrito</label><input name="district" class="input-field" maxlength="60"></div>
                <div><label class="input-label">Código SUNAT</label><input name="sunat_code" class="input-field" inputmode="numeric" maxlength="4" placeholder="auto"></div>
                <div class="sm:col-span-2"><label class="input-label">Teléfono</label><input name="phone" class="input-field" maxlength="30"></div>
                <div class="sm:col-span-6 flex justify-end"><button type="submit" class="btn-primary text-sm">Agregar</button></div>
            </form>
        </details>
    </section>

    {{-- Cajas --}}
    <section class="glass-card p-4 sm:p-5 space-y-3" aria-labelledby="rg-title">
        <h2 id="rg-title" class="text-white font-bold text-base">Cajas</h2>
        <p class="text-xs text-white/60">Elige desde qué sede vende cada caja. Las cajas nuevas se crean en <a href="{{ route('dashboard.pos.index', ['cajas' => 1]) }}" class="underline">Caja</a>.</p>
        @foreach($registers as $register)
            <form method="POST" action="{{ route('dashboard.pos.documents.registers.branch', $register->id) }}" class="flex items-end gap-2 flex-wrap">
                @csrf @method('PUT')
                <div class="flex-1 min-w-[160px]"><label class="input-label" for="rb-{{ $register->id }}">{{ $register->name }}</label>
                    <select id="rb-{{ $register->id }}" name="branch_id" class="input-field">
                        <option value="">Sede principal</option>
                        @foreach($branches->where('is_active', true)->where('is_main', false) as $branch)<option value="{{ $branch->id }}" @selected($register->branch_id === $branch->id)>{{ $branch->name }} ({{ $branch->sunat_code }})</option>@endforeach
                    </select></div>
                <button type="submit" class="btn-secondary text-xs">Guardar</button>
            </form>
        @endforeach
    </section>

    {{-- Series --}}
    <section class="glass-card p-4 sm:p-5 space-y-3" aria-labelledby="se-title">
        <h2 id="se-title" class="text-white font-bold text-base">Series por sede o por caja</h2>
        <p class="text-xs text-white/60">Una caja usa, en este orden, la serie de su caja, la de su sede o la del negocio (en Datos y series). Así cada local puede llevar su propio B002, F002… como SUNAT permite.</p>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead><tr class="text-left text-white/50"><th class="py-2 pr-3">Documento</th><th class="pr-3">Serie</th><th class="pr-3">Corresponde a</th><th class="pr-3">Último</th><th></th></tr></thead>
                <tbody>
                @foreach($series as $row)
                    <tr class="border-t border-white/10">
                        <td class="py-2 pr-3 text-white">{{ $types[$row->type]['label'] ?? $row->type }}</td>
                        <td class="pr-3 font-mono text-white">{{ $row->series }}</td>
                        <td class="pr-3 text-white/70">{{ $row->register ? 'Caja: ' . $row->register->name : ($row->branch ? 'Sede: ' . $row->branch->name : 'Todo el negocio') }}</td>
                        <td class="pr-3 text-white/70">{{ $row->last_number ? \App\Services\Pos\SalesDocuments::code($row->series, $row->last_number) : '—' }}</td>
                        <td class="text-right">@if(($row->branch_id || $row->register_id) && !$row->last_number)<form method="POST" action="{{ route('dashboard.pos.documents.series.destroy', $row->id) }}">@csrf @method('DELETE')<button class="text-red-300 underline min-h-[36px]">Quitar</button></form>@endif</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <form method="POST" action="{{ route('dashboard.pos.documents.series.store') }}" class="grid grid-cols-2 sm:grid-cols-6 gap-3 items-end border-t border-white/10 pt-3" x-data="{ scope: 'branch' }">
            @csrf
            <div><label class="input-label" for="ns-type">Documento</label>
                <select id="ns-type" name="type" class="input-field">@foreach($types as $type => $meta)<option value="{{ $type }}">{{ $meta['short'] }}</option>@endforeach</select></div>
            <div><label class="input-label" for="ns-series">Serie</label><input id="ns-series" name="series" class="input-field uppercase" maxlength="4" placeholder="B002" required></div>
            <div><label class="input-label" for="ns-scope">Para</label>
                <select id="ns-scope" name="scope" class="input-field" x-model="scope"><option value="branch">Una sede</option><option value="register">Una caja</option></select></div>
            <div x-show="scope === 'branch'"><label class="input-label" for="ns-branch">Sede</label>
                <select id="ns-branch" name="branch_id" class="input-field">@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></div>
            <div x-show="scope === 'register'" x-cloak><label class="input-label" for="ns-reg">Caja</label>
                <select id="ns-reg" name="register_id" class="input-field">@foreach($registers as $register)<option value="{{ $register->id }}">{{ $register->name }}</option>@endforeach</select></div>
            <div><label class="input-label" for="ns-start">Empieza en</label><input id="ns-start" name="start" type="number" min="1" class="input-field" value="1"></div>
            <button type="submit" class="btn-primary text-sm">Crear serie</button>
        </form>
    </section>
</div>
@endsection
