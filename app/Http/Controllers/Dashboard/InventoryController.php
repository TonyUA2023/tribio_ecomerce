<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\InventoryMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InventoryController extends Controller
{
    private function getStore() { return Auth::user()->store; }

    public function index(Request $request)
    {
        $store = $this->getStore();

        $products = $store->products()
            ->with('category')
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
            'total_value'     => $store->products()->where('track_stock', true)->selectRaw('SUM(stock * cost_price) as val')->value('val') ?? 0,
        ];

        return view('dashboard.inventory.index', compact('store', 'products', 'stats'));
    }

    public function product(Product $product)
    {
        $store = $this->getStore();
        abort_if($product->store_id !== $store->id, 403);

        $movements = $product->inventoryMovements()
            ->with('user')
            ->latest()
            ->paginate(30);

        return view('dashboard.inventory.product', compact('store', 'product', 'movements'));
    }

    public function adjust(Request $request, Product $product)
    {
        $store = $this->getStore();
        abort_if($product->store_id !== $store->id, 403);

        $request->validate([
            'type'     => 'required|in:in,out,adjustment,loss',
            'quantity' => 'required|integer|min:1',
            'reason'   => 'nullable|string|max:255',
        ]);

        $type = $request->type;
        $qty  = (int) $request->quantity;

        $stockBefore = $product->stock;
        $stockAfter  = match ($type) {
            'in'         => $stockBefore + $qty,
            'out', 'loss'=> max(0, $stockBefore - $qty),
            'adjustment' => $qty, // El usuario ingresa el stock absoluto
            default      => $stockBefore,
        };

        $product->update(['stock' => $stockAfter]);

        InventoryMovement::create([
            'product_id'   => $product->id,
            'store_id'     => $store->id,
            'type'         => $type,
            'quantity'     => $type === 'adjustment' ? ($stockAfter - $stockBefore) : ($type === 'in' ? $qty : -$qty),
            'stock_before' => $stockBefore,
            'stock_after'  => $stockAfter,
            'reason'       => $request->reason ?? 'Ajuste manual',
            'user_id'      => Auth::id(),
        ]);

        return back()->with('success', "Stock de '{$product->name}' ajustado de {$stockBefore} a {$stockAfter}.");
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
