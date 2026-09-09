@extends('layouts.dashboard')

@section('title', 'Zonas de Envío')
@section('page_title', '🚚 Costos de Envío Internacional')

@section('content')
<div class="w-full max-w-5xl mx-auto space-y-6">

    @if(session('success'))
        <div class="p-4 rounded-xl bg-green-500/10 border border-green-500/20 text-green-400 font-medium">
            ✅ {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Formulario -->
        <div class="lg:col-span-1">
            <div class="glass-card p-6">
                <h3 class="text-white font-bold text-sm mb-4">Añadir Tarifa</h3>
                <form action="{{ route('dashboard.shipping.store') }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="input-label">País (Código 2 letras)</label>
                        <select name="country_code" class="input-field" required>
                            <option value="PE">Perú (PE)</option>
                            <option value="US">Estados Unidos (US)</option>
                            <option value="MX">México (MX)</option>
                            <option value="CO">Colombia (CO)</option>
                            <option value="ES">España (ES)</option>
                            <option value="ALL">Resto del Mundo (ALL)</option>
                        </select>
                        <p class="text-[10px] text-white/40 mt-1">Usa 'ALL' para el costo por defecto de otros países.</p>
                    </div>

                    <div>
                        <label class="input-label">Estado / Departamento (Opcional)</label>
                        <input type="text" name="state" class="input-field" placeholder="Ej: Lima, California">
                        <p class="text-[10px] text-white/40 mt-1">Déjalo en blanco para aplicar al país entero.</p>
                    </div>

                    <div>
                        <label class="input-label">Costo de Envío</label>
                        <div class="flex items-center">
                            <span class="px-3 py-2 bg-black/20 border border-white/5 border-r-0 rounded-l-lg text-xs text-white/50">$</span>
                            <input type="number" step="0.01" name="cost" class="input-field rounded-l-none" required placeholder="15.00">
                        </div>
                    </div>

                    <button type="submit" class="btn-primary w-full justify-center">Guardar Tarifa</button>
                </form>
            </div>
        </div>

        <!-- Lista de Tarifas -->
        <div class="lg:col-span-2">
            <div class="glass-card p-0 overflow-hidden">
                <div class="p-6 border-b border-white/5 flex justify-between items-center">
                    <h3 class="text-white font-bold text-sm">Zonas Configuradas</h3>
                </div>
                
                @if($rates->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-white/70">
                        <thead class="text-xs uppercase bg-black/20 text-white/40">
                            <tr>
                                <th class="px-6 py-3 font-medium">País</th>
                                <th class="px-6 py-3 font-medium">Estado / Depto</th>
                                <th class="px-6 py-3 font-medium">Costo</th>
                                <th class="px-6 py-3 font-medium text-right">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @foreach($rates as $rate)
                            <tr class="hover:bg-white/[0.02] transition-colors">
                                <td class="px-6 py-4 font-bold text-white">
                                    {{ $rate->country_code === 'ALL' ? '🌎 Resto del Mundo' : $rate->country_code }}
                                </td>
                                <td class="px-6 py-4">
                                    @if($rate->state)
                                        <span class="px-2 py-1 rounded bg-white/10 text-xs">{{ $rate->state }}</span>
                                    @else
                                        <span class="text-white/30 text-xs italic">Aplica a todo el país</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 font-bold text-tribio-cyan">
                                    {{ number_format($rate->cost, 2) }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <form action="{{ route('dashboard.shipping.destroy', $rate) }}" method="POST" class="inline-block" onsubmit="return confirm('¿Eliminar tarifa?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-red-400 hover:text-red-300">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="p-12 text-center">
                    <div class="w-16 h-16 bg-white/5 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-white/20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <p class="text-white/50 text-sm">No has configurado costos de envío.</p>
                    <p class="text-white/30 text-xs mt-1">El envío aparecerá como gratuito si no configuras zonas.</p>
                </div>
                @endif
            </div>
        </div>
        
    </div>
</div>
@endsection
