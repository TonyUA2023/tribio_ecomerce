<section class="space-y-4 pt-5 border-t border-white/10" aria-labelledby="flow-settings-title">
    <div class="flex items-center justify-between gap-3">
        <div>
            <h4 id="flow-settings-title" class="text-white font-bold">Flow</h4>
            <p class="text-xs text-white/60 mt-1">Más formas de pagar, desde el mismo checkout.</p>
        </div>
        @if($store?->flow_api_key)
            @if(($store->flow_mode ?? 'sandbox') === 'live')
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">🟢 Modo Producción (En Vivo)</span>
            @else
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30">🧪 Modo Pruebas (Sandbox)</span>
            @endif
        @else
            <span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-white/10 text-white/50">No configurado</span>
        @endif
    </div>
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
    <div>
        <label for="flow_api_key" class="input-label">API Key</label>
        <input id="flow_api_key" type="text" name="flow_api_key" autocomplete="new-password" class="input-field font-mono text-xs" placeholder="{{ filled($store?->flow_api_key) ? 'Guardada · Deja vacío para conservarla' : 'Pega la clave de tu cuenta Flow' }}">
        @error('flow_api_key') <p class="text-sm text-red-300" role="alert">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="flow_secret_key" class="input-label">Secret Key</label>
        <div class="relative">
            <input :type="showFlowSecret ? 'text' : 'password'" id="flow_secret_key" name="flow_secret_key" autocomplete="new-password" class="input-field font-mono text-xs pr-11" placeholder="{{ filled($store?->flow_secret_key) ? 'Guardada · Deja vacío para conservarla' : 'Pega la clave de tu cuenta Flow' }}">
            <button type="button" @click="showFlowSecret = !showFlowSecret" class="absolute right-3 top-1/2 -translate-y-1/2 text-white/40 hover:text-white/80 text-sm" :aria-label="showFlowSecret ? 'Ocultar secreto' : 'Mostrar secreto'">
                <span x-show="!showFlowSecret">👁️</span><span x-show="showFlowSecret" x-cloak>🙈</span>
            </button>
        </div>
        <p class="text-[10px] text-white/40 mt-1">Este dato es secreto: no lo compartas con nadie.</p>
        @error('flow_secret_key') <p class="text-sm text-red-300" role="alert">{{ $message }}</p> @enderror
    </div>
    <p class="text-xs text-white/50">Las claves se guardan cifradas. Al cambiar de entorno, reemplaza ambas por las claves correspondientes.</p>
</section>
