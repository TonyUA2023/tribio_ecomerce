<?php

namespace App\Http\Middleware;

use App\Models\Store;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dashboard screens that do not belong to the account type: a POS-only business does not see
 * (nor reach by URL) the online store's tools, and an online-only business has no register.
 * Pedidos stays reachable in both: a counter sale is an order, and its detail links there.
 */
class StoreModuleGuard
{
    /** Tools of the online store. */
    public const STOREFRONT_ONLY = [
        'dashboard.galeria.*', 'dashboard.resenas.*', 'dashboard.mensajes.*', 'dashboard.shipping.*', 'dashboard.gateway.*',
        'dashboard.manual-payments.*', 'dashboard.delivery-coverage.*', 'dashboard.marketing.*', 'dashboard.plantillas.*',
        'dashboard.store.builder.*', 'dashboard.templates.*',
    ];

    /** Tools of the point-of-sale system. */
    public const POS_ONLY = [
        'dashboard.pos.*', 'dashboard.quotes.*', 'dashboard.returns.*', 'dashboard.receivables.*', 'dashboard.price-lists.*', 'dashboard.ventas.*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $store = $request->user()?->currentStore();
        if (!$store || !Store::modesReady()) {
            return $next($request);
        }

        $blocked = match (true) {
            $store->isPosOnly() => $request->routeIs(...self::STOREFRONT_ONLY),
            !$store->hasPos()   => $request->routeIs(...self::POS_ONLY),
            default             => false,
        };
        if (!$blocked) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Esta función no está incluida en tu plan.'], 403);
        }

        return redirect()->route('dashboard.index')->with('info', 'Esa función no está incluida en el tipo de cuenta de tu negocio.');
    }
}
