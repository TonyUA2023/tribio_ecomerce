<?php

namespace App\Http\Controllers;

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

        return view('public.tribio-pass', [
            'hasStore' => $user?->hasStore() ?? false,
        ]);
    }
}
