{{-- Publicidad: solo para tiendas que ya conectaron Meta (Dashboard → Marketing). --}}
@php($metaIntegration = app(\App\Services\Marketing\Meta\MetaIntegrationService::class)->find($store))
@if($metaIntegration)
@php($includeInAds = (bool) old('include_in_ads', $product ? !$product->exclude_from_ads : true))
@php($condition = old('condition', $product?->condition))
<details class="glass-card p-5 sm:p-6" @if(!$includeInAds || $condition) open @endif>
    <summary class="cursor-pointer text-white font-bold text-sm uppercase tracking-wider opacity-60">📣 Publicidad</summary>
    <input type="hidden" name="ads_settings" value="1">
    <div class="space-y-4 mt-4">
        <label class="flex items-center gap-3 cursor-pointer">
            <input type="hidden" name="include_in_ads" value="0">
            <input type="checkbox" name="include_in_ads" value="1" class="w-4 h-4 accent-tribio-cyan" @checked($includeInAds)>
            <span class="text-white/70 text-sm select-none">Mostrar en mis anuncios de Facebook e Instagram</span>
        </label>
        <div>
            <label class="input-label" for="ads_condition">Condición</label>
            <select id="ads_condition" name="condition" class="input-field">
                <option value="">Igual que la tienda ({{ \App\Models\StoreMarketingIntegration::CONDITIONS[$metaIntegration->default_condition] ?? 'Nuevo' }})</option>
                @foreach(\App\Models\StoreMarketingIntegration::CONDITIONS as $value => $label)
                    <option value="{{ $value }}" @selected($condition === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>
</details>
@endif
