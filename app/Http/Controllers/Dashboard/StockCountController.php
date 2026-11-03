<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\RecordStockCountRequest;
use App\Http\Requests\Dashboard\StartStockCountRequest;
use App\Models\StockCount;
use App\Services\Inventory\InventorySchema;
use App\Services\Inventory\StockCountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/** Dashboard → Inventario → Conteos: physical counts from the phone (StockCountService). */
class StockCountController extends Controller
{
    public function __construct(private InventorySchema $schema)
    {
    }

    public function index()
    {
        $store = Auth::user()->currentStore();
        if (!$this->schema->countsReady()) {
            return view('dashboard.inventory.counts.index', ['ready' => false]);
        }

        return view('dashboard.inventory.counts.index', [
            'ready'      => true,
            'counts'     => StockCount::where('store_id', $store->id)->with('category:id,name')->withCount([
                'items', 'items as counted_count' => fn ($q) => $q->whereNotNull('counted_qty'),
            ])->latest('id')->paginate(20),
            'categories' => $store->categories()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StartStockCountRequest $request, StockCountService $service): RedirectResponse
    {
        abort_unless($this->schema->countsReady(), 404);
        $count = $service->start($request->user()->currentStore(), $request->filled('category_id') ? $request->integer('category_id') : null,
            $request->boolean('blind'), $request->input('notes'), $request->user()->id);

        return redirect()->route('dashboard.inventario.conteos.show', $count->id);
    }

    public function show(int $count)
    {
        $count = $this->owned($count);
        $items = $count->items()->with('product:id,name,sku,stock,unit')->get()
            ->sortBy(fn ($item) => mb_strtolower($item->product?->name ?? ''))->values();

        return view('dashboard.inventory.counts.show', [
            'count' => $count,
            'items' => $items,
        ]);
    }

    public function record(RecordStockCountRequest $request, StockCountService $service, int $count): JsonResponse
    {
        $item = $service->record($this->owned($count), $request->integer('item_id'),
            $request->input('counted_qty') === null || $request->input('counted_qty') === '' ? null : $request->integer('counted_qty'));

        return response()->json([
            'counted_qty'    => $item->counted_qty,
            'expected_qty'   => $item->expected_qty,
            'variance'       => $item->variance,
            'variance_value' => $item->variance_value,
        ]);
    }

    public function apply(StockCountService $service, int $count): RedirectResponse
    {
        $count = $service->apply($this->owned($count), Auth::id());
        $value = (float) $count->variance_value;

        return redirect()->route('dashboard.inventario.conteos.show', $count->id)->with('success', $value < 0
            ? "Aplicamos el conteo {$count->number}: faltó mercadería por S/ " . number_format(-$value, 2) . '.'
            : "Aplicamos el conteo {$count->number}. El stock quedó igual a lo que contaste.");
    }

    public function cancel(StockCountService $service, int $count): RedirectResponse
    {
        $count = $this->owned($count);
        $service->cancel($count);

        return redirect()->route('dashboard.inventario.conteos.index')->with('success', "Cancelaste el conteo {$count->number}. El stock no cambió.");
    }

    private function owned(int $id): StockCount
    {
        abort_unless($this->schema->countsReady(), 404);

        return StockCount::where('store_id', Auth::user()->currentStore()->id)->findOrFail($id);
    }
}
