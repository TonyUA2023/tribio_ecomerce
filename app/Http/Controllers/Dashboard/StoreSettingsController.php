<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class StoreSettingsController extends Controller
{
    private function getStore()
    {
        return Auth::user()->store;
    }

    public function edit()
    {
        $store = $this->getStore();
        $templates = config('tribio.templates');
        return view('dashboard.store.edit', compact('store', 'templates'));
    }

    public function templates()
    {
        $store = $this->getStore();
        $templates = config('tribio.templates');
        return view('dashboard.store.templates', compact('store', 'templates'));
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
            'checkout_mode'  => 'required|string|in:whatsapp,card',
            'payment_gateway'=> 'nullable|string|in:culqi,mercado_pago',
            'gateway_public_key' => 'nullable|string|max:255',
            'gateway_private_key'=> 'nullable|string|max:255',
            'gateway_access_token'=> 'nullable|string',
            'custom_domain'  => [
                'nullable',
                'string',
                'max:255',
                'unique:stores,custom_domain,' . $store->id,
                'regex:/^[a-zA-Z0-9][-a-zA-Z0-9]{0,62}(\.[a-zA-Z0-9][-a-zA-Z0-9]{0,62})+(:[0-9]{1,5})?$/'
            ],
            'is_express_shipping_enabled' => 'nullable|boolean',
            'express_shipping_cost'       => 'nullable|numeric|min:0',
            'is_multilanguage_enabled'    => 'nullable|boolean',
            'hero_title'                  => 'nullable|string|max:100',
            'hero_subtitle'               => 'nullable|string|max:200',
            'hero_badge'                  => 'nullable|string|max:50',
            'mp_access_token'             => 'nullable|string|max:255',
            'mp_public_key'               => 'nullable|string|max:255',
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
            'checkout_mode', 'payment_gateway', 'gateway_public_key', 'gateway_private_key', 'gateway_access_token',
            'mp_access_token', 'mp_public_key', 'contact_email', 'contact_phone',
            'express_shipping_cost',
            'hero_title', 'hero_subtitle', 'hero_badge'
        ]);
        $data['is_express_shipping_enabled'] = $request->has('is_express_shipping_enabled');
        $data['is_multilanguage_enabled']    = $request->has('is_multilanguage_enabled');

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

    public function uploadLogo(Request $request)
    {
        $request->validate([
            'logo' => 'required|image|mimes:png,jpg,jpeg,webp|max:2048',
        ]);

        $store = $this->getStore();

        if ($store->logo_path) {
            Storage::disk('public')->delete($store->logo_path);
        }

        $path = $request->file('logo')->store('logos', 'public');
        $store->update(['logo_path' => $path]);

        return back()->with('success', 'Logotipo actualizado correctamente.');
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

    public function updateTemplate(Request $request)
    {
        $request->validate([
            'template_name'    => 'required|string|in:elegant-dark,minimal-light,vibrant-fresh,industrial-light,elegant-refurbished',
            'accent_color'     => 'nullable|string|max:7',
            'secondary_color'  => 'nullable|string|max:7',
            'hero_carousel'    => 'nullable|boolean',
            'hero_style'       => 'nullable|string|in:full,split,minimal',
        ]);

        $store = $this->getStore();
        $store->update([
            'template_name'  => $request->template_name,
            'accent_color'   => $request->accent_color ?? $store->accent_color,
            'secondary_color'=> $request->secondary_color ?? $store->secondary_color,
            'hero_carousel'  => $request->boolean('hero_carousel'),
            'hero_style'     => $request->hero_style ?? $store->hero_style,
        ]);

        return back()->with('success', 'Diseño de tu tienda actualizado.');
    }
}
