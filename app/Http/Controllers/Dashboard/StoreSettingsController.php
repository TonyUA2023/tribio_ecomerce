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

    public function update(Request $request)
    {
        $store = $this->getStore();

        $request->validate([
            'name'           => 'required|string|max:255',
            'tagline'        => 'nullable|string|max:150',
            'description'    => 'nullable|string|max:1000',
            'category'       => 'required|string',
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
        ]);

        $store->update($request->only([
            'name', 'tagline', 'description', 'category',
            'whatsapp_phone', 'phone', 'email', 'address', 'city',
            'facebook_url', 'instagram_url', 'tiktok_url',
            'meta_title', 'meta_description', 'distributors',
        ]));

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
            'template_name'    => 'required|string|in:elegant-dark,minimal-light,vibrant-fresh,industrial-light',
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
