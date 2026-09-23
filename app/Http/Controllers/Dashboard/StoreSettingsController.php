<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Services\LogoPaletteService;

class StoreSettingsController extends Controller
{
    private function getStore()
    {
        return Auth::user()->currentStore();
    }

    public function edit()
    {
        $store = $this->getStore();
        // Stores on a customizable template edit their hero copy in the Plantillas module.
        $heroManagedByTemplate = app(\App\Services\Storefront\TemplateRegistry::class)->isCustomizable($store?->template_name)
            && !$store->template_locked;
        return view('dashboard.store.edit', compact('store', 'heroManagedByTemplate'));
    }

    public function update(Request $request)
    {
        $store = $this->getStore();

        // Sanitizar el dominio antes de la validación
        if ($request->filled('custom_domain')) {
            $domain = $request->input('custom_domain');
            $domain = preg_replace('#^https?://#i', '', $domain);
            $domain = explode('/', $domain)[0];
            $domain = strtolower(trim($domain));
            $request->merge(['custom_domain' => $domain]);
        }

        $request->validate([
            'name'           => 'required|string|max:255',
            'slug'           => 'nullable|string|max:150|alpha_dash|unique:stores,slug,' . $store->id,
            'tagline'        => 'nullable|string|max:150',
            'description'    => 'nullable|string|max:1000',
            'category'       => 'required|string|in:moda,calzado,tecnologia,alimentos,joyeria,hogar,deporte,salud,servicios,otros',
            'build_mode'     => 'required|string|in:builder,custom_code',
            'whatsapp_phone' => 'nullable|string|max:20',
            'phone'          => 'nullable|string|max:20',
            'email'          => 'nullable|email',
            'address'        => 'nullable|string|max:255',
            'city'           => 'nullable|string|max:100',
            'facebook_url'   => 'nullable|url',
            'instagram_url'  => 'nullable|url',
            'tiktok_url'     => 'nullable|url',
            'meta_title'     => 'nullable|string|max:70',
            'meta_description'=> 'nullable|string|max:160',
            'distributors'   => 'nullable|array',
            'custom_domain'  => [
                'nullable',
                'string',
                'max:255',
                'unique:stores,custom_domain,' . $store->id,
                'regex:/^[a-zA-Z0-9][-a-zA-Z0-9]{0,62}(\.[a-zA-Z0-9][-a-zA-Z0-9]{0,62})+(:[0-9]{1,5})?$/'
            ],
            'is_express_shipping_enabled' => 'nullable|boolean',
            'express_shipping_cost'       => 'nullable|numeric|min:0',
            'national_shipping_cost'      => 'nullable|numeric|min:0',
            'free_shipping_min_quantity'  => 'nullable|integer|min:1',
            'free_shipping_min_amount'    => 'nullable|numeric|min:0',
            'bulk_discount_min_quantity'  => 'nullable|integer|min:1',
            'bulk_discount_type'          => 'nullable|string|in:percentage,fixed',
            'bulk_discount_value'         => 'nullable|numeric|min:0',
            'enabled_countries'           => 'nullable|array',
            'enabled_countries.*'         => 'string|in:PE,US,ES,MX,CO,EC,CL,AR',
            'country_shipping_costs'      => 'nullable|array',
            'is_multilanguage_enabled'    => 'nullable|boolean',
            'made_to_order_enabled'       => 'nullable|boolean',
            'deposit_percent'             => 'nullable|integer|min:1|max:100',
            'hero_title'                  => 'nullable|string|max:100',
            'hero_subtitle'               => 'nullable|string|max:200',
            'hero_badge'                  => 'nullable|string|max:50',
            'contact_email'               => 'nullable|email|max:255',
            'contact_phone'               => 'nullable|string|max:20',
        ], [
            'custom_domain.regex' => 'El formato del dominio no es válido. Debe ser similar a "mitienda.com" (sin http:// ni / al final).',
            'custom_domain.unique' => 'Este dominio ya está configurado en otra tienda.',
        ]);

        $data = $request->only([
            'name', 'tagline', 'description', 'category', 'build_mode',
            'whatsapp_phone', 'phone', 'email', 'address', 'city',
            'facebook_url', 'instagram_url', 'tiktok_url',
            'meta_title', 'meta_description', 'distributors',
            'custom_domain',
            'contact_email', 'contact_phone',
            'express_shipping_cost', 'national_shipping_cost',
            'free_shipping_min_quantity', 'free_shipping_min_amount',
            'bulk_discount_min_quantity', 'bulk_discount_type', 'bulk_discount_value',
            'hero_title', 'hero_subtitle', 'hero_badge'
        ]);
        $data['is_express_shipping_enabled'] = $request->has('is_express_shipping_enabled');
        $data['is_multilanguage_enabled']    = $request->has('is_multilanguage_enabled');
        $data['made_to_order_enabled']       = $request->has('made_to_order_enabled');
        if ($request->filled('deposit_percent')) {
            $data['deposit_percent'] = (int) $request->input('deposit_percent');
        }

        // Campos numéricos opcionales: un input vacío llega como '' y no como null,
        // lo cual rompería las columnas integer/decimal si se guarda tal cual.
        // national_shipping_cost debe poder quedar NULL (no 0.00) cuando se deja vacío:
        // 0.00 significa "Perú siempre gratis" a propósito, mientras que NULL significa
        // "no configurado aquí, usar la tarifa de Zonas de Envío" — ver Store::resolveShippingCostForStore().
        foreach (['free_shipping_min_quantity', 'free_shipping_min_amount', 'bulk_discount_min_quantity', 'bulk_discount_value', 'national_shipping_cost'] as $numericField) {
            if (($data[$numericField] ?? '') === '') {
                $data[$numericField] = null;
            }
        }
        if (empty($data['bulk_discount_type'])) {
            $data['bulk_discount_type'] = null;
        }

        // Países habilitados
        $enabledCountries = $request->input('enabled_countries', ['PE', 'US']);
        if (!is_array($enabledCountries)) {
            $enabledCountries = ['PE', 'US'];
        }
        if (!in_array('PE', $enabledCountries)) {
            $enabledCountries[] = 'PE';
        }
        $data['enabled_countries'] = array_values(array_unique($enabledCountries));

        // Costos de envío internacionales por país
        $rawCountryCosts = $request->input('country_shipping_costs', []);
        $cleanCountryCosts = [];
        if (is_array($rawCountryCosts)) {
            foreach ($rawCountryCosts as $cCode => $cost) {
                if ($cost !== null && $cost !== '' && is_numeric($cost)) {
                    $cleanCountryCosts[strtoupper($cCode)] = round((float) $cost, 2);
                }
            }
        }
        $data['country_shipping_costs'] = $cleanCountryCosts;

        if ($request->filled('slug')) {
            $data['slug'] = \Illuminate\Support\Str::slug($request->slug);
        } elseif ($request->name !== $store->name) {
            $slug = \Illuminate\Support\Str::slug($request->name);
            $originalSlug = $slug;
            $counter = 1;
            while (\App\Models\Store::where('slug', $slug)->where('id', '!=', $store->id)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }
            $data['slug'] = $slug;
        }

        $store->update($data);

        return back()->with('success', 'Información de la tienda actualizada correctamente.');
    }

    public function uploadLogo(Request $request, LogoPaletteService $paletteService)
    {
        $request->validate([
            'logo' => 'required|image|mimes:png,jpg,jpeg,webp|max:2048',
        ]);

        $store = $this->getStore();
        $previousPath = $store->logo_path;
        $palette = $paletteService->extract($request->file('logo')->getRealPath());
        $path = $request->file('logo')->store('logos', 'public');
        $store->update(['logo_path' => $path]);

        if ($palette) {
            $this->applyLogoPalette($store, $palette, $paletteService);
        } else {
            $store->update(['logo_palette' => null]);
        }

        if ($previousPath) {
            Storage::disk('public')->delete($previousPath);
        }

        return back()->with('success', $palette
            ? 'Logo guardado. Preparamos una paleta para tu hero; revísala y publica los cambios desde el constructor.'
            : 'Logo guardado. No pudimos identificar colores visibles; puedes configurar el hero manualmente.');
    }

    public function refreshLogoPalette(LogoPaletteService $paletteService)
    {
        $store = $this->getStore();
        if (!$store->logo_path || !Storage::disk('public')->exists($store->logo_path)) {
            return back()->withErrors(['logo' => 'Sube primero un logo para crear la paleta.']);
        }

        $palette = $paletteService->extract(Storage::disk('public')->path($store->logo_path));
        if (!$palette) {
            return back()->withErrors(['logo' => 'No encontramos colores visibles en el logo. Prueba con otra imagen.']);
        }

        $this->applyLogoPalette($store, $palette, $paletteService);

        return back()->with('success', 'Paleta generada desde tu logo. Revisa el hero y publica los cambios cuando esté listo.');
    }

    private function applyLogoPalette(\App\Models\Store $store, array $palette, LogoPaletteService $paletteService): void
    {
        $previousPalette = $store->logo_palette;
        $changes = ['logo_palette' => $palette];
        if (!$previousPalette || strcasecmp($store->accent_color ?? '', $previousPalette['primary']) === 0) {
            $changes['accent_color'] = $palette['primary'];
        }
        if (!$previousPalette || strcasecmp($store->secondary_color ?? '', $previousPalette['secondary']) === 0) {
            $changes['secondary_color'] = $palette['secondary'];
        }
        $store->update($changes);

        foreach ($store->sections()->where('type', 'hero')->get() as $hero) {
            $data = $hero->data ?? [];
            $mode = $data['palette_mode'] ?? null;
            $untouched = ($data['background_color'] ?? '#f3f4f6') === '#f3f4f6'
                && ($data['text_color'] ?? '#111827') === '#111827';
            if ($mode === 'auto' || ($mode === null && $untouched)) {
                $hero->update(['data' => $paletteService->applyToHero($data, $palette)]);
            }
        }
    }

    public function uploadCover(Request $request)
    {
        $request->validate([
            'cover' => 'required|image|mimes:png,jpg,jpeg,webp|max:5120',
        ]);

        $store = $this->getStore();

        if ($store->cover_path) {
            Storage::disk('public')->delete($store->cover_path);
        }

        $path = $request->file('cover')->store('covers', 'public');
        $store->update(['cover_path' => $path]);

        return back()->with('success', 'Portada actualizada correctamente.');
    }
}
