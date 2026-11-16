<?php

namespace App\Http\Middleware;

use App\Services\Team\AccessLog;
use App\Services\Team\TeamAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The mobile seller API for team members: a seller reaches only the areas their modules open
 * (`TeamAccess::API_SEGMENTS`); owners and admins reach everything. Also records the visit in the
 * access log (one row every 30 minutes per person), like the web dashboard.
 */
class ApiTeamAccess
{
    public function __construct(private TeamAccess $team)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $store = $user?->currentStore();
        if (!$user || !$store) {
            return $next($request);
        }

        if ($request->isMethod('GET')) {
            try {
                app(AccessLog::class)->visit($user, $store, $request);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // /api/dashboard/{segment}/…
        if ($this->team->allowsApi($user, $store, (string) $request->segment(3))) {
            return $next($request);
        }

        return response()->json(['message' => 'Esa sección no está incluida en tu acceso. Pídele al administrador de ' . $store->name . ' que te la active.'], 403);
    }
}
