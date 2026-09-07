<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Store;

class StoreController extends Controller
{
    public function show(Request $request)
    {
        $store = $request->user()->store;

        if (!$store) {
            return response()->json(['message' => 'No tienes ninguna tienda configurada.'], 404);
        }

        $store->logo_url = $store->logo_url;
        $store->cover_url = $store->cover_url;

        // Fetch categories and brands of this store
        $categories = $store->categories;
        $brands = $store->brands;

        return response()->json([
            'store' => $store,
            'categories' => $categories,
            'brands' => $brands,
        ]);
    }

    public function update(Request $request)
    {
        $store = $request->user()->store;

        if (!$store) {
            return response()->json(['message' => 'No tienes ninguna tienda configurada.'], 404);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'tagline' => 'nullable|string|max:255',
            'whatsapp_phone' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'accent_color' => 'nullable|string|max:50',
            'secondary_color' => 'nullable|string|max:50',
        ]);

        $store->update($data);
        
        $store->logo_url = $store->logo_url;
        $store->cover_url = $store->cover_url;

        return response()->json([
            'message' => 'Configuración de la tienda actualizada con éxito.',
            'store' => $store
        ]);
    }
}
