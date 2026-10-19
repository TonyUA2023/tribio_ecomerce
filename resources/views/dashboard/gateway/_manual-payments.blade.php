@php
    $mp = is_array($store?->manual_payments) ? $store->manual_payments : [];
    $mpv = fn ($key, $field) => old("$key.$field", $mp[$key][$field] ?? '');
    $cards = [
        'yape' => ['Yape', true, ['holder' => 'Titular (como sale en la app)', 'phone' => 'Número de Yape']],
        'plin' => ['Plin', true, ['holder' => 'Titular', 'phone' => 'Número de Plin']],
        'bank' => ['Transferencia / depósito', false, ['holder' => 'Titular de la cuenta', 'bank' => 'Banco', 'account' => 'N.° de cuenta', 'cci' => 'CCI (opcional)']],
        'cod'  => ['Pago contra entrega', false, []],
    ];
@endphp
<form method="POST" action="{{ route('dashboard.manual-payments.update') }}" enctype="multipart/form-data" class="glass-card p-4 sm:p-6 space-y-4">
    @csrf
    <div>
        <h2 class="text-white font-bold text-base">Pagos por confirmación</h2>
        <p class="text-xs text-white/50 mt-1">Para negocios que cobran con Yape, Plin o transferencia. El cliente paga, pulsa «Ya realicé el pago» y el pedido queda <strong>en confirmación</strong> hasta que tú lo confirmes desde Pedidos. No necesita Mercado Pago ni tarjeta.</p>
    </div>
    @if(session('success') && request()->routeIs('dashboard.gateway.edit'))
        <div class="p-3 rounded-xl bg-green-500/10 border border-green-500/20 text-green-400 text-sm">✅ {{ session('success') }}</div>
    @endif
    @foreach($cards as $key => [$label, $hasQr, $fields])
        <div class="rounded-xl border border-white/10 p-3.5 space-y-3" x-data="{ on: {{ old("$key.enabled", $mp[$key]['enabled'] ?? false) ? 'true' : 'false' }} }">
            <label class="flex items-center gap-2.5 cursor-pointer">
                <input type="checkbox" name="{{ $key }}[enabled]" value="1" x-model="on" class="w-4 h-4">
                <span class="text-white font-semibold text-sm">{{ $label }}</span>
            </label>
            @error("$key.enabled")<p class="text-xs text-red-400">{{ $message }}</p>@enderror
            <div x-show="on" x-cloak class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @foreach($fields as $field => $fieldLabel)
                    <div>
                        <label class="input-label">{{ $fieldLabel }}</label>
                        <input type="text" name="{{ $key }}[{{ $field }}]" value="{{ $mpv($key, $field) }}" class="input-field">
                    </div>
                @endforeach
                @if($hasQr)
                    <div class="sm:col-span-2">
                        <label class="input-label">Imagen del QR <span class="text-white/40 normal-case font-normal">(captura de tu app, PNG/JPG hasta 3 MB)</span></label>
                        <input type="file" name="{{ $key }}[qr]" accept="image/png,image/jpeg,image/webp" class="input-field">
                        @if(!empty($mp[$key]['qr_path']))
                            <div class="flex items-center gap-3 mt-2">
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($mp[$key]['qr_path']) }}" alt="QR actual de {{ $label }}" class="w-20 h-20 object-cover rounded-lg border border-white/10">
                                <label class="text-xs text-white/60 flex items-center gap-1.5"><input type="checkbox" name="{{ $key }}[remove_qr]" value="1"> Quitar QR</label>
                            </div>
                        @endif
                    </div>
                @endif
                <div class="sm:col-span-2">
                    <label class="input-label">Indicaciones para el cliente <span class="text-white/40 normal-case font-normal">(opcional)</span></label>
                    <textarea name="{{ $key }}[instructions]" rows="2" maxlength="500" class="input-field" placeholder="{{ $key === 'cod' ? 'Ej. Paga en efectivo al recibir. Solo Lima.' : 'Ej. Envía tu captura al WhatsApp si lo deseas.' }}">{{ $mpv($key, 'instructions') }}</textarea>
                </div>
            </div>
        </div>
    @endforeach
    <div class="flex justify-end">
        <button type="submit" class="btn-primary text-sm">Guardar pagos por confirmación</button>
    </div>
</form>
