<?php

namespace App\Http\Middleware;

use App\Services\Team\AccessLog;
use App\Services\Team\TeamAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A team member only opens what the owner let them: sellers get the modules ticked for them, admins
 * everything. Owners are never touched. A refused screen sends the person to where they can work.
 */
class StoreMemberGuard
{
    public function __construct(private TeamAccess $team)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $store = $user?->currentStore();
        $route = $request->route()?->getName();
        if ($user && $store && $request->isMethod('GET') && !$request->expectsJson() && !$user->isSuperAdmin()) {
            try {
                app(AccessLog::class)->visit($user, $store, $request);
            } catch (\Throwable $e) {
                report($e);
            }
        }
        if (!$user || !$store || !$route || (int) $store->user_id === (int) $user->id || $user->isSuperAdmin()) {
            return $next($request);
        }

        if ($this->team->allows($user, $store, $route)) {
            return $next($request);
        }

        $message = 'Esa sección no está incluida en tu acceso. Pídele al administrador de ' . $store->name . ' que te la active.';
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }
        $landing = $this->team->landing($user, $store);

        return $request->routeIs($landing)
            ? abort(403, $message)
            : redirect()->route($landing)->with('info', $message);
    }
}
