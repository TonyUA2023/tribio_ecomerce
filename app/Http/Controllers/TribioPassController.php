<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Services\Team\TeamAccess;
use Illuminate\Support\Facades\Auth;

/**
 * The Tribio Pass hub: a single account that can be a buyer (purchase history
 * across any store), a business owner (their own store), or both at once. The
 * page itself stays thin — auth/orders/addresses are all driven client-side
 * through the existing /customer/* JSON endpoints in CustomerPortalController,
 * the same ones the storefront checkout drawer already uses.
 */
class TribioPassController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if ($user && !$user->canUseCustomerPortal()) {
            return redirect()->route('admin.dashboard');
        }

        $team = app(TeamAccess::class);
        if ($user) {
            $team->claimInvites($user);
        }

        return view('public.tribio-pass', [
            // Every business this account works in (its own and the ones it was let into): the first thing it sees.
            'workspaces'  => $user ? $team->workspaces($user) : collect(),
            'hasStore'    => $user?->hasStore() ?? false,
            'googleReady' => filled(config('services.google.client_id')),
        ]);
    }

    /** "Ingresar a {empresa}": opens the business dashboard (a seller lands on what they may use). */
    public function enter(Store $store)
    {
        $user = Auth::user();
        abort_unless(app(TeamAccess::class)->roleIn($user, $store) !== null, 403);

        $user->switchToStore($store);

        return redirect()->route('dashboard.index');
    }
}
