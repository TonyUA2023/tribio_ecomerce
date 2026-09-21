<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showForm()
    {
        return view('auth.login', [
            'googleReady' => filled(config('services.google.client_id')),
        ]);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            $user = Auth::user();

            if ($user->isSuperAdmin()) {
                return redirect()->route('admin.dashboard');
            }

            // Tribio Pass: this form authenticates any account (store owner or buyer).
            // Route by capability rather than assuming everyone owns a store.
            if ($user->hasStore()) {
                return redirect()->route('dashboard.index');
            }

            return redirect()->route('tribio-pass');
        }

        $googleOnly = User::where('email', $credentials['email'])->whereNotNull('google_id')->exists();

        return back()->withErrors([
            'email' => $googleOnly
                ? 'Esta cuenta está conectada con Google. Inicia sesión con el botón "Continuar con Google".'
                : 'Las credenciales no coinciden con nuestros registros.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('home');
    }
}
