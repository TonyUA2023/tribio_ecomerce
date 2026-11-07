<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\PurchaseOrderItem;
use App\Models\StockLot;
use App\Services\Inventory\InventorySchema;
use App\Services\Inventory\ProfitReport;
use App\Services\Inventory\StockService;
use App\Services\Purchasing\PurchasingSchema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InventoryController extends Controller
{
    private function getStore() { return Auth::user()->currentStore(); }

    public function index(Request $request, StockService $stock, InventorySchema $schema)
    {
        $store = $this->getStore();

        $products = $store->products()
            ->with($schema->unitsReady() ? ['category', 'units'] : ['category'])
            ->when($request->filter === 'low', fn($q) => $q->where('track_stock', true)->whereColumn('stock', '<=', 'low_stock_alert')->where('stock', '>', 0))
            ->when($request->filter === 'out', fn($q) => $q->where('track_stock', true)->where('stock', 0))
            ->when($request->filter === 'tracked', fn($q) => $q->where('track_stock', true))
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->orderByRaw('CASE WHEN track_stock = 1 AND stock = 0 THEN 0 WHEN track_stock = 1 AND stock <= low_stock_alert THEN 1 ELSE 2 END')
            ->paginate(20);

        $stats = [
            'total_tracked'   => $store->products()->where('track_stock', true)->count(),
            'out_of_stock'    => $store->products()->where('track_stock', true)->where('stock', 0)->count(),
            'low_stock'       => $store->products()->where('track_stock', true)->whereColumn('stock', '<=', 'low_stock_alert')->where('stock', '>', 0)->count(),
            // At the cost of each lot once lots exist; before that, stock × cost price.
            'total_value'     => $stock->stockValue($store->id),
        ];
        $expiring = $schema->lotsReady()
            ? StockLot::where('store_id', $store->id)->onHand()->whereNotNull('expires_at')
                ->where('expires_at', '<=', now()->addDays(StockLot::EXPIRING_DAYS))->with('product:id,name')->consumptionOrder()->limit(10)->get()
            : collect();

        return view('dashboard.inventory.index', compact('store', 'products', 'stats', 'expiring'));
    }

    /**
     * Type-ahead for the products of a combo: tracked products that are not combos themselves, found on the server
     * (a select with every product would carry thousands of options). `?except=` leaves out the combo being edited.
     */
    public function components(Request $request, InventorySchema $schema, \App\Services\Purchasing\PurchaseProductSearch $search)
    {
        abort_unless($schema->unitsReady(), 404);
        $except = $request->integer('except');
        $found = $search->search(Auth::user()->currentStore(), mb_substr((string) $request->query('q'), 0, 80));

        return response()->json(array_values(array_filter($found, fn ($row) => $row['id'] !== $except)));
    }

    public function product(Product $product, InventorySchema $schema, PurchasingSchema $purchasing, \App\Services\Crm\CrmSchema $crm)
    {
        $store = $this->getStore();
        abort_if($product->store_id !== $store->id, 403);

        $movements = $product->inventoryMovements()
            ->with('user')
            ->latest('id')
            ->paginate(30);

        $lotsReady = $schema->lotsReady();
        $lots = $lotsReady ? $product->lots()->onHand()->consumptionOrder()->get() : collect();

        // Last purchases of this product: who sold it, when and at what landed cost.
        $purchases = $purchasing->ready()
            ? PurchaseOrderItem::where('product_id', $product->id)->whereNotNull('landed_unit_cost')
                ->with('purchaseOrder.supplier:id,name')->latest('id')->limit(5)->get()
            : collect();

        // Packagings and combo (Fase 1e).
        $unitsReady = $schema->unitsReady();
        $isKit = false;
        $components = collect();
        $inCombos = collect();
        if ($unitsReady) {
            $product->load(['units', 'components.component:id,name,stock,deleted_at']);
            $isKit = $product->components->isNotEmpty();
            $components = $product->components;
            // Combos this product is part of: it cannot be a combo itself.
            $inCombos = \App\Models\ProductComponent::where('component_product_id', $product->id)->with('kit:id,name')->get();
        }

        return view('dashboard.inventory.product', compact('store', 'product', 'movements', 'lotsReady', 'lots', 'purchases',
            'unitsReady', 'isKit', 'components', 'inCombos')
            + [
                // Recompra (Fase 2c): pending reminders of this product.
                'reorderReady'   => $crm->reorderReady(),
                'reorderPending' => $crm->reorderReady()
                    ? \App\Models\ContactTask::where('product_id', $product->id)->where('kind', 'reorder')->pending()->count()
                    : 0,
            ]);
    }

    public function profit(Request $request, ProfitReport $report)
    {
        $store = $this->getStore();

        // A hand-picked range wins over a preset; a bad one (end before start, over ~13 months) falls back to the preset.
        $custom = $request->filled('desde') ? \App\Services\Reports\ProfitAndLoss::customRange($request->query('desde'), $request->query('hasta') ?: $request->query('desde')) : null;
        $rangeError = $request->filled('desde') && !$custom;
        $preset = array_key_exists((string) $request->query('periodo'), ProfitReport::PRESETS) ? $request->query('periodo') : 'mes';
        [$from, $to] = $custom ?? ProfitReport::range($preset);

        return view('dashboard.inventory.profit', [
            'store'      => $store,
            'preset'     => $custom ? null : $preset,
            'from'       => $from,
            'to'         => $to,
            'rangeError' => $rangeError,
        ] + $report->build($store, $from, $to));
    }

    public function adjust(Request $request, Product $product, StockService $stock)
    {
        $store = $this->getStore();
        abort_if($product->store_id !== $store->id, 403);

        $request->validate([
            'type'       => 'required|in:in,out,adjustment,loss',
            'quantity'   => 'required|integer|min:1',
            'reason'     => 'nullable|string|max:255',
            'unit_cost'  => 'nullable|numeric|min:0|max:9999999',
            'lot_code'   => 'nullable|string|max:40',
            'expires_at' => 'nullable|date',
        ], [
            'unit_cost.numeric' => 'El costo debe ser un número.',
            'expires_at.date'   => 'La fecha de vencimiento no es válida.',
        ]);

        // 'adjustment': el usuario ingresa el stock absoluto (ver StockService::adjust()).
        $movement = $stock->adjust($product, $request->type, (int) $request->quantity, $request->reason, Auth::id(), [
            'unit_cost'  => $request->input('unit_cost'),
            'code'       => $request->input('lot_code'),
            'expires_at' => $request->input('expires_at'),
        ]);

        return back()->with('success', "Stock de '{$product->name}' ajustado de {$movement->stock_before} a {$movement->stock_after}.");
    }

    public function export()
    {
        $store    = $this->getStore();
        $products = $store->products()->with('category')->where('track_stock', true)->get();

        $csv  = "Nombre,SKU,Categoría,Stock,Precio,Costo,Valor Total\n";
        foreach ($products as $p) {
            $csv .= "\"{$p->name}\",\"{$p->sku}\",\"{$p->category?->name}\",{$p->stock},{$p->price},{$p->cost_price}," . ($p->stock * ($p->cost_price ?? 0)) . "\n";
        }

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', "attachment; filename=inventario-{$store->slug}.csv");
    }
}
