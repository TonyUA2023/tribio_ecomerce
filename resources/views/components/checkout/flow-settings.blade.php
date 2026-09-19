<section class="space-y-4 pt-5 border-t border-white/10" aria-labelledby="flow-settings-title">
    <div class="flex items-center justify-between gap-3">
        <div>
            <h4 id="flow-settings-title" class="text-white font-bold">Flow</h4>
            <p class="text-xs text-white/60 mt-1">Más formas de pagar, desde el mismo checkout.</p>
        </div>
        <span class="text-xs px-3 py-1 rounded-full bg-white/10 text-white">{{ $store?->flow_enabled ? ($store->flow_mode === 'live' ? 'Producción' : 'Pruebas') : 'Desactivado' }}</span>
    </div>
    <input type="hidden" name="flow_enabled" value="0">
    <label class="flex items-center gap-3 text-sm text-white cursor-pointer">
        <input type="checkbox" name="flow_enabled" value="1" @checked(old('flow_enabled', $store?->flow_enabled)) class="w-4 h-4 accent-cyan-400">
        Ofrecer Flow a mis clientes
    </label>
    @error('flow_enabled') <p class="text-sm text-red-300" role="alert">{{ $message }}</p> @enderror
    <div class="p-3 bg-white/5 rounded-xl border border-white/10 text-xs text-slate-300 space-y-2">
        <p>Obtén tus claves en <a href="https://www.flow.cl/app/web/misDatos.php" target="_blank" rel="noopener noreferrer" class="text-tribio-cyan underline">tu cuenta Flow</a>, sección Integraciones. Para probar, utiliza una cuenta de <a href="https://sandbox.flow.cl" target="_blank" rel="noopener noreferrer" class="text-tribio-cyan underline">Sandbox</a>.</p>
        <p>Activa Yape y los demás medios con Flow. Tus clientes verán únicamente los medios contratados y disponibles en tu cuenta.</p>
        <p>La confirmación se configura automáticamente. Tu tienda debe estar publicada con HTTPS para recibir los pagos.</p>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label for="flow-mode" class="input-label">Entorno</label>
            <select id="flow-mode" name="flow_mode" class="input-field">
                <option value="sandbox" @selected(old('flow_mode', $store?->flow_mode ?? 'sandbox') === 'sandbox')>Pruebas (Sandbox)</option>
                <option value="live" @selected(old('flow_mode', $store?->flow_mode) === 'live')>Producción · Cobros reales</option>
            </select>
        </div>
        <div>
            <label for="flow-currency" class="input-label">Moneda contratada con Flow</label>
            <select id="flow-currency" name="flow_currency" class="input-field">
                @foreach(['PEN' => 'Soles (PEN)', 'USD' => 'Dólares (USD)', 'CLP' => 'Pesos chilenos (CLP)', 'MXN' => 'Pesos mexicanos (MXN)'] as $code => $label)
                    <option value="{{ $code }}" @selected(old('flow_currency', $store?->flow_currency ?? 'PEN') === $code)>{{ $label }}</option>
                @endforeach
            </select>
            <p class="text-xs text-white/50 mt-1">Debe estar habilitada en tu cuenta. Flow se ofrece cuando la compra usa esta moneda.</p>
        </div>
    </div>
    @foreach(['flow_api_key' => 'API Key', 'flow_secret_key' => 'Secret Key'] as $field => $label)
        <div>
            <label for="{{ $field }}" class="input-label">{{ $label }}</label>
            <input id="{{ $field }}" type="password" name="{{ $field }}" autocomplete="new-password" class="input-field" placeholder="{{ filled($store?->$field) ? 'Guardada · Deja vacío para conservarla' : 'Pega la clave de tu cuenta Flow' }}">
            @error($field) <p class="text-sm text-red-300" role="alert">{{ $message }}</p> @enderror
        </div>
    @endforeach
    <p class="text-xs text-white/50">Las claves se guardan cifradas. Al cambiar de entorno, reemplaza ambas por las claves correspondientes.</p>
</section>
