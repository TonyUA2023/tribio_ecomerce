<?php

namespace App\Http\Middleware;

use App\Models\Store;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A business that only contracted the POS system has no public page: every /tienda/{slug}/…
 * URL of it answers 404, as if the store did not exist.
 */
class StorefrontEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('slug');
        if (is_string($slug) && Store::modesReady() && Store::where('slug', $slug)->whereIn('mode', [Store::MODE_POS, Store::MODE_SIMPLE])->exists()) {
            abort(404);
        }

        return $next($request);
    }
}
