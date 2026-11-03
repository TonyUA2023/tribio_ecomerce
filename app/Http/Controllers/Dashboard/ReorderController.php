<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\ReorderPurchaseRequest;
use App\Services\Inventory\ReorderAdvisor;
use App\Services\Purchasing\PurchaseOrderService;
use App\Services\Purchasing\PurchasingSchema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Dashboard → Inventario → Reposición: what to buy now and what is not moving (ReorderAdvisor). */
class ReorderController extends Controller
{
    public function index(Request $request, ReorderAdvisor $advisor, PurchasingSchema $purchasing)
    {
        $store = Auth::user()->currentStore();
        $params = $advisor->params($request->query());
        $suggestions = $advisor->suggestions($store, $params);

        return view('dashboard.inventory.reorder', [
            'params'      => $params,
            'suggestions' => $suggestions,
            'stagnant'    => $advisor->stagnant($store, $params),
            'canPurchase' => $purchasing->ready(),
            'suppliers'   => $purchasing->ready() ? $store->suppliers()->orderBy('name')->get(['id', 'name']) : collect(),
            // The supplier the most suggested products were last bought from.
            'supplierId'  => collect($suggestions)->pluck('supplier_id')->filter()->countBy()->sortDesc()->keys()->first(),
        ]);
    }

    public function purchase(ReorderPurchaseRequest $request, ReorderAdvisor $advisor, PurchaseOrderService $orders, PurchasingSchema $purchasing): RedirectResponse
    {
        abort_unless($purchasing->ready(), 404);
        $store = $request->user()->currentStore();

        // Costs come from the server (last price paid / product cost), not from the form.
        $costs = collect($advisor->suggestions($store, $advisor->params($request->input())))->pluck('unit_cost', 'product_id');
        $fallback = $store->products()->whereIn('id', array_column($request->validated()['items'], 'product_id'))->pluck('cost_price', 'id');

        $order = $orders->save($store, [
            'supplier_id' => $request->validated()['supplier_id'] ?? null,
            'notes'       => 'Creada desde Reposición sugerida.',
            'items'       => array_map(fn ($line) => $line + [
                'unit_cost' => (float) ($costs[$line['product_id']] ?? $fallback[$line['product_id']] ?? 0),
            ], $request->validated()['items']),
        ], null, $request->user()->id);

        return redirect()->route('dashboard.compras.edit', $order->id)
            ->with('success', "Armamos la compra {$order->number} en borrador. Revisa costos y cantidades antes de pedirla.");
    }
}
