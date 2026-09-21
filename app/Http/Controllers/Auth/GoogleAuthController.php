<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Store;
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
     * Optionally carries where to send the visitor back to across the redirect to
     * Google: a pending plan selection (home pricing modal), the Tribio Pass hub,
     * or a specific store's checkout drawer — so the callback can land them back
     * exactly where they started instead of somewhere generic.
     */
    public function redirect(Request $request)
    {
        if ($request->filled('plan_key')) {
            session(['google_auth_plan_key' => $request->query('plan_key')]);
        }

        if ($request->query('from') === 'tribio-pass') {
            session(['google_auth_from' => 'tribio-pass']);
        }

        if ($request->filled('store')) {
            session(['google_auth_store' => $request->query('store')]);
        }

        return Socialite::driver('google')->redirect();
    }

    public function callback()
    {
        $planKey = session()->pull('google_auth_plan_key');
        $from = session()->pull('google_auth_from');
        $storeSlug = session()->pull('google_auth_store');

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            Log::error('Google OAuth callback failed', ['message' => $e->getMessage()]);

            return $this->redirectBack($planKey, $from, $storeSlug)
                ->withErrors(['google' => 'No se pudo completar el inicio de sesión con Google. Intenta de nuevo.']);
        }

        $user = $this->identity->findOrCreateFromGoogle(
            $googleUser->getId(),
            $googleUser->getEmail(),
            $googleUser->getName() ?: $googleUser->getNickname() ?: 'Usuario Tribio',
            $googleUser->getAvatar(),
        );

        if ($user->isSuperAdmin()) {
            return $this->redirectBack($planKey, $from, $storeSlug)
                ->withErrors(['google' => 'Esta cuenta no puede usar este acceso.']);
        }

        Auth::login($user, true);
        request()->session()->regenerate();

        if ($planKey) {
            return redirect($this->homeUrl($planKey));
        }

        if ($storeSlug && ($store = Store::where('slug', $storeSlug)->first())) {
            // ?tribio_pass_login=1 tells the storefront's shared customer-modal
            // component (any of the 5 templates) to reopen itself on load.
            return redirect($store->url . '?tribio_pass_login=1');
        }

        // Same capability-based routing as LoginController::login().
        return $user->hasStore()
            ? redirect()->route('dashboard.index')
            : redirect()->route('tribio-pass');
    }

    private function redirectBack(?string $planKey, ?string $from, ?string $storeSlug)
    {
        if ($planKey) {
            return redirect($this->homeUrl($planKey));
        }

        if ($storeSlug && ($store = Store::where('slug', $storeSlug)->first())) {
            return redirect($store->url);
        }

        return redirect($from === 'tribio-pass' ? route('tribio-pass') : route('home'));
    }

    private function homeUrl(string $planKey): string
    {
        return route('home', ['plan_key' => $planKey]) . '#precios';
    }
}
