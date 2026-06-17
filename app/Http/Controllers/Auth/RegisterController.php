<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RegisterController extends Controller
{
    public function showForm()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $request->validate([
            'name'           => 'required|string|max:255',
            'email'          => 'required|email|unique:users',
            'password'       => 'required|min:8|confirmed',
            'phone'          => 'nullable|string|max:20',
            'store_name'     => 'required|string|max:255',
            'store_category' => 'required|string',
            'store_slug'     => 'required|string|alpha_dash|unique:stores,slug|max:60',
            'whatsapp_phone' => 'nullable|string|max:20',
            'template_name'  => 'required|string|in:elegant-dark,minimal-light,vibrant-fresh,industrial-light',
            'logo'           => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
            'accent_color'   => 'nullable|string|max:7',
        ]);

        // Crear usuario
        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'role'     => 'store_owner',
            'phone'    => $request->phone,
        ]);

        // Manejar logo
        $logoPath = null;
        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('logos', 'public');
        }

        // Crear tienda
        $store = Store::create([
            'user_id'        => $user->id,
            'name'           => $request->store_name,
            'slug'           => $request->store_slug,
            'category'       => $request->store_category,
            'template_name'  => $request->template_name,
            'whatsapp_phone' => $request->whatsapp_phone,
            'accent_color'   => $request->accent_color ?? '#8B5CF6',
            'logo_path'      => $logoPath,
            'status'         => 'active',
            'plan'           => 'basic',
            'plan_expires_at'=> now()->addDays(30), // 30 días de prueba
        ]);

        Auth::login($user);

        return redirect()->route('dashboard.index')
            ->with('success', '¡Bienvenido a Tribio! Tu tienda ha sido creada exitosamente.');
    }
}
