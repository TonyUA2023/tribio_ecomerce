<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Products\QuickAddProducts;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Dashboard → Productos → Alta rápida: a table to register many products in a minute. */
class QuickProductController extends Controller
{
    public function create(Request $request)
    {
        $store = $request->user()->currentStore();
        abort_unless($store, 404);

        return view('dashboard.products.quick', [
            'store'      => $store,
            'categories' => $store->categories()->orderBy('name')->pluck('name'),
            'maxRows'    => QuickAddProducts::MAX_ROWS,
        ]);
    }

    public function store(Request $request, QuickAddProducts $quick): JsonResponse
    {
        $store = $request->user()->currentStore();
        abort_unless($store, 404);

        $products = $quick->handle($store, (array) $request->input('rows', []), $request->user()->id);

        return response()->json([
            'created' => count($products),
            'message' => count($products) === 1 ? 'Guardamos 1 producto.' : 'Guardamos ' . count($products) . ' productos.',
            'list_url' => route('dashboard.productos.index'),
        ], 201);
    }
}
