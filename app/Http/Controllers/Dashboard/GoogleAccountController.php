<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CustomerIdentityService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

/**
 * Lets an already-authenticated store owner link their Tribio Pass account to
 * their own Google account from the dashboard ("Mi Cuenta"). Distinct from the
 * public Auth\GoogleAuthController, which is the guest find-or-create door and
 * is gated by `guest` middleware — this one lives inside the dashboard's own
 * `auth` + `role:store_owner,super_admin` route group instead. Uses its own
 * redirectUrl() (a second authorized redirect URI on the same Google OAuth
 * client) so it never shares state with the guest flow.
 */
class GoogleAccountController extends Controller
{
    public function __construct(protected CustomerIdentityService $identity)
    {
    }

    public function connect()
    {
        return Socialite::driver('google')
            ->redirectUrl(route('dashboard.google.callback'))
            ->redirect();
    }

    public function callback()
    {
        try {
            $googleUser = Socialite::driver('google')
                ->redirectUrl(route('dashboard.google.callback'))
                ->user();
        } catch (\Throwable $e) {
            Log::error('Dashboard Google account link failed', ['message' => $e->getMessage()]);

            return redirect()->route('dashboard.password.edit')
                ->with('error', 'No se pudo conectar tu cuenta con Google. Intenta de nuevo.');
        }

        $user = Auth::user();

        $conflict = User::where('google_id', $googleUser->getId())
            ->where('id', '!=', $user->id)
            ->exists();

        if ($conflict) {
            return redirect()->route('dashboard.password.edit')
                ->with('error', 'Esta cuenta de Google ya está conectada a otra cuenta de Tribio Pass.');
        }

        $this->identity->linkGoogleToUser($user, $googleUser->getId());

        return redirect()->route('dashboard.password.edit')
            ->with('success', '¡Listo! Tu cuenta quedó conectada con Google. A partir de ahora, inicia sesión únicamente con el botón "Continuar con Google".');
    }
}
