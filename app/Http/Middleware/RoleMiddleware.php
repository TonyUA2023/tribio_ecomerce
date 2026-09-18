<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        if (in_array($user->role, $roles, true)) {
            return $next($request);
        }

        // Tribio Pass unification: any account that owns a store reaches the business
        // dashboard even if its legacy `role` is still `cliente` (e.g. a buyer who just
        // subscribed to a plan without going through a separate store_owner signup).
        if (in_array('store_owner', $roles, true) && $user->hasStore()) {
            return $next($request);
        }

        abort(403, 'No tienes permisos para acceder a esta sección.');
    }
}
