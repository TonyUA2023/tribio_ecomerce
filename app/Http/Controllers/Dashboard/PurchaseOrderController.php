<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\ReceivePurchaseOrderRequest;
use App\Http\Requests\Dashboard\SavePurchaseOrderRequest;
use App\Models\PurchaseOrder;
use App\Services\Inventory\InventorySchema;
use App\Services\Purchasing\PurchaseOrderService;
use App\Services\Purchasing\PurchaseProductSearch;
use App\Services\Purchasing\PurchasingSchema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Dashboard → Compras: purchase orders to suppliers; receiving puts the stock in (PurchaseOrderService). */
class PurchaseOrderController extends Controller
{
    public function __construct(private PurchasingSchema $schema, private InventorySchema $inventory)
    {
    }

    public function index(Request $request)
    {
        $store = Auth::user()->currentStore();
        if (!$this->schema->ready()) {
            return view('dashboard.purchases.index', ['store' => $store, 'ready' => false]);
        }

        $status = array_key_exists($request->query('estado'), PurchaseOrder::STATUSES) ? $request->query('estado') : null;

        return view('dashboard.purchases.index', [
            'store'  => $store,
            'ready'  => true,
            'status' => $status,
            'orders' => $store->purchaseOrders()->with('supplier:id,name')->withCount('items')
                ->when($status, fn ($q) => $q->where('status', $status))
                ->latest('id')->paginate(20)->withQueryString(),
            'counts' => $store->purchaseOrders()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function create()
    {
        abort_unless($this->schema->ready(), 404);

        return $this->form(new PurchaseOrder(['status' => 'draft']));
    }

    /** JSON for the form's product type-ahead: ?q=words → up to 20 buyable products (empty q → what to restock). */
    public function products(Request $request, PurchaseProductSearch $search)
    {
        abort_unless($this->schema->ready(), 404);

        return response()->json($search->search($request->user()->currentStore(), mb_substr((string) $request->query('q'), 0, 80)));
    }

    public function store(SavePurchaseOrderRequest $request, PurchaseOrderService $service): RedirectResponse
    {
        abort_unless($this->schema->ready(), 404);
        $order = $service->save($request->user()->currentStore(), $request->payload(), null, $request->user()->id);

        return redirect()->route('dashboard.compras.show', $order->id)->with('success', "Guardamos la compra {$order->number}.");
    }

    public function show(int $order)
    {
        $order = $this->owned($order);

        return view('dashboard.purchases.show', ['order' => $order->load(['supplier', 'items.product'])]);
    }

    public function edit(int $order)
    {
        $order = $this->owned($order);
        if (!$order->isEditable()) {
            return redirect()->route('dashboard.compras.show', $order->id)->with('error', 'Esta compra ya no se puede editar.');
        }

        return $this->form($order->load('items'));
    }

    public function update(SavePurchaseOrderRequest $request, PurchaseOrderService $service, int $order): RedirectResponse
    {
        $order = $service->save($request->user()->currentStore(), $request->payload(), $this->owned($order), $request->user()->id);

        return redirect()->route('dashboard.compras.show', $order->id)->with('success', "Actualizamos la compra {$order->number}.");
    }

    public function receive(ReceivePurchaseOrderRequest $request, PurchaseOrderService $service, int $order): RedirectResponse
    {
        $order = $service->receive($this->owned($order), $request->validated()['lines'], $request->user()->id);

        return redirect()->route('dashboard.compras.show', $order->id)
            ->with('success', "Recibiste la compra {$order->number}: el stock y los costos de tus productos ya están actualizados.");
    }

    public function cancel(PurchaseOrderService $service, int $order): RedirectResponse
    {
        $order = $this->owned($order);
        $service->cancel($order);

        return redirect()->route('dashboard.compras.show', $order->id)->with('success', "Cancelaste la compra {$order->number}.");
    }

    private function owned(int $id): PurchaseOrder
    {
        abort_unless($this->schema->ready(), 404);

        return Auth::user()->currentStore()->purchaseOrders()->findOrFail($id);
    }

    private function form(PurchaseOrder $order)
    {
        $store = Auth::user()->currentStore();
        $units = $this->inventory->unitsReady();
        // The catalog is searched on demand (products route): the page only carries the products already in this order.
        // After a validation error the lines come back from the old input instead.
        $old = collect(json_decode((string) old('items_json'), true) ?: [])->pluck('product_id');
        $products = app(PurchaseProductSearch::class)->byIds($store, $order->items->pluck('product_id')->merge($old)->filter()->unique()->all());

        return view('dashboard.purchases.form', [
            'order'     => $order,
            'suppliers' => $store->suppliers()->orderBy('name')->get(['id', 'name']),
            'products'  => app(PurchaseProductSearch::class)->present($products),
            'lines'     => $order->items->map(fn ($item) => [
                'product_id' => $item->product_id, 'quantity' => $item->quantity_ordered, 'unit_cost' => (float) $item->unit_cost,
                'unit_id'    => $units && $item->unit_name
                    ? optional($products->firstWhere('id', $item->product_id)?->units->firstWhere('name', $item->unit_name))->id
                    : null,
            ])->values(),
            'hasProducts' => $store->products()->where('track_stock', true)->exists(),
        ]);
    }
}
