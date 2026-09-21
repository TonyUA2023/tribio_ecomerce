<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\CustomerIdentityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

/**
 * "Continuar con Google" for Tribio Pass — a single button that finds-or-creates
 * a User (see CustomerIdentityService::findOrCreateFromGoogle) rather than making
 * the visitor choose between "sign up" and "log in". Web-only: see the vault ADR
 * on why mobile stayed on the existing email/OTP door for now.
 */
class GoogleAuthController extends Controller
{
    public function __construct(protected CustomerIdentityService $identity)
    {
    }

    /**
     * Optionally carries a pending plan selection (from the home page pricing modal)
     * across the redirect to Google and back, so the callback can reopen the modal
     * in the right state instead of losing the visitor's plan choice.
     */
    public function redirect(Request $request)
    {
        if ($request->filled('plan_key')) {
            session(['google_auth_plan_key' => $request->query('plan_key')]);
        }

        if ($request->query('from') === 'tribio-pass') {
            session(['google_auth_from' => 'tribio-pass']);
        }

        return Socialite::driver('google')->redirect();
    }

    public function callback()
    {
        $planKey = session()->pull('google_auth_plan_key');
        $from = session()->pull('google_auth_from');

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            Log::error('Google OAuth callback failed', ['message' => $e->getMessage()]);

            return $this->redirectBack($planKey, $from)
                ->withErrors(['google' => 'No se pudo completar el inicio de sesión con Google. Intenta de nuevo.']);
        }

        $user = $this->identity->findOrCreateFromGoogle(
            $googleUser->getId(),
            $googleUser->getEmail(),
            $googleUser->getName() ?: $googleUser->getNickname() ?: 'Usuario Tribio',
            $googleUser->getAvatar(),
        );

        if ($user->isSuperAdmin()) {
            return $this->redirectBack($planKey, $from)
                ->withErrors(['google' => 'Esta cuenta no puede usar este acceso.']);
        }

        Auth::login($user, true);
        request()->session()->regenerate();

        if ($planKey) {
            return redirect($this->homeUrl($planKey));
        }

        // Same capability-based routing as LoginController::login().
        return $user->hasStore()
            ? redirect()->route('dashboard.index')
            : redirect()->route('tribio-pass');
    }

    private function redirectBack(?string $planKey, ?string $from)
    {
        if ($planKey) {
            return redirect($this->homeUrl($planKey));
        }

        return redirect($from === 'tribio-pass' ? route('tribio-pass') : route('home'));
    }

    private function homeUrl(string $planKey): string
    {
        return route('home', ['plan_key' => $planKey]) . '#precios';
    }
}
