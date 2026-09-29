<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesCurrentStore;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AdjustStockRequest;
use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    use ResolvesCurrentStore;

    private function ownedProduct(int $id): Product
    {
        return $this->currentStore()->products()->findOrFail($id);
    }

    public function index(Request $request): JsonResponse
    {
        $store = $this->currentStore();
        $filter = $request->query('filter');
        $search = trim((string) $request->query('search'));

        $products = $store->products()
            ->with('category:id,name')
            ->whereNull('parent_id')
            ->when($filter === 'low', fn ($q) => $q->where('track_stock', true)->whereColumn('stock', '<=', 'low_stock_alert')->where('stock', '>', 0))
            ->when($filter === 'out', fn ($q) => $q->where('track_stock', true)->where('stock', 0))
            ->when($filter === 'tracked', fn ($q) => $q->where('track_stock', true))
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderByRaw('CASE WHEN track_stock = 1 AND stock = 0 THEN 0 WHEN track_stock = 1 AND stock <= low_stock_alert THEN 1 ELSE 2 END')
            ->paginate(20);

        // One aggregate query for the summary cards.
        $t = $store->products()->where('track_stock', true)
            ->selectRaw('COUNT(*) as tracked,
                SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END) as out_of_stock,
                SUM(CASE WHEN stock > 0 AND stock <= low_stock_alert THEN 1 ELSE 0 END) as low_stock,
                SUM(stock * COALESCE(cost_price, 0)) as total_value')
            ->first();

        return response()->json([
            'data' => $products->getCollection()->map(fn (Product $p) => [
                'id'              => $p->id,
                'name'            => $p->name,
                'sku'             => $p->sku,
                'image_url'       => $p->image_url,
                'category'        => $p->category?->name,
                'stock'           => (int) $p->stock,
                'low_stock_alert' => (int) $p->low_stock_alert,
                'track_stock'     => (bool) $p->track_stock,
                'price'           => (float) $p->price,
                'cost_price'      => $p->cost_price !== null ? (float) $p->cost_price : null,
            ])->values(),
            'meta'  => ['current_page' => $products->currentPage(), 'last_page' => $products->lastPage(), 'total' => $products->total()],
            'stats' => [
                'total_tracked' => (int) $t->tracked,
                'out_of_stock'  => (int) $t->out_of_stock,
                'low_stock'     => (int) $t->low_stock,
                'total_value'   => round((float) $t->total_value, 2),
            ],
        ]);
    }

    public function product(int $id): JsonResponse
    {
        $product = $this->ownedProduct($id);
        $movements = $product->inventoryMovements()->with('user:id,name')->latest()->paginate(30);

        return response()->json([
            'product' => ['id' => $product->id, 'name' => $product->name, 'sku' => $product->sku, 'stock' => (int) $product->stock, 'image_url' => $product->image_url],
            'data'    => $movements->getCollection()->map(fn (InventoryMovement $m) => [
                'id'           => $m->id,
                'type'         => $m->type,
                'type_label'   => $m->type_label,
                'quantity'     => (int) $m->quantity,
                'stock_before' => (int) $m->stock_before,
                'stock_after'  => (int) $m->stock_after,
                'reason'       => $m->reason,
                'user'         => $m->user?->name,
                'created_at'   => $m->created_at?->toISOString(),
            ])->values(),
            'meta' => ['current_page' => $movements->currentPage(), 'last_page' => $movements->lastPage()],
        ]);
    }

    public function adjust(AdjustStockRequest $request, int $id): JsonResponse
    {
        $store = $this->currentStore();
        $product = $this->ownedProduct($id);
        $qty = (int) $request->quantity;
        $type = $request->type;

        $before = (int) $product->stock;
        $after = match ($type) {
            'in'          => $before + $qty,
            'out', 'loss' => max(0, $before - $qty),
            default       => $qty, // adjustment: the owner types the absolute stock
        };

        $product->update(['stock' => $after]);

        InventoryMovement::create([
            'product_id'   => $product->id,
            'store_id'     => $store->id,
            'type'         => $type,
            'quantity'     => $type === 'adjustment' ? ($after - $before) : ($type === 'in' ? $qty : -$qty),
            'stock_before' => $before,
            'stock_after'  => $after,
            'reason'       => $request->input('reason') ?: 'Ajuste manual',
            'user_id'      => $request->user()->id,
        ]);

        return response()->json([
            'message' => "Stock de «{$product->name}» ajustado de {$before} a {$after}.",
            'product' => ['id' => $product->id, 'stock' => $after],
        ]);
    }
}
