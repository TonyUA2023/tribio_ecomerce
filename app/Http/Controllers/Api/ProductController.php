<?php

namespace App\Http\Controllers\Api;

use App\Actions\Products\SaveProduct;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductStateHistory;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    private function noStore(): JsonResponse
    {
        return response()->json(['message' => 'No tienes ninguna tienda configurada.'], 404);
    }

    public function index(Request $request)
    {
        $store = $request->user()->currentStore();

        if (!$store) {
            return $this->noStore();
        }

        $query = $store->products()->with(['categories', 'category', 'brand'])->withCount('variants');

        // Por defecto, no listamos subcomponentes en el catálogo general
        if (!$request->has('include_subcomponents') || $request->include_subcomponents == 'false') {
            $query->whereNull('parent_id');
        } elseif ($request->has('parent_id')) {
            $query->where('parent_id', $request->parent_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->has('category_id') && $request->category_id !== 'all') {
            $catId = $request->category_id;
            $query->where(function ($sub) use ($catId) {
                $sub->where('category_id', $catId)
                    ->orWhereHas('categories', fn ($sq) => $sq->where('categories.id', $catId));
            });
        }

        $query
            ->when($request->status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($request->status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($request->stock === 'low', fn ($q) => $q->where('track_stock', true)->whereColumn('stock', '<=', 'low_stock_alert')->where('stock', '>', 0))
            ->when($request->stock === 'out', fn ($q) => $q->where('track_stock', true)->where('stock', 0));

        $products = $query->orderBy('sort_order')->latest()->paginate(15);

        // Map Image URLs and financial indicators
        $products->getCollection()->transform(fn ($product) => $this->present($product));

        return response()->json($products);
    }

    public function show(Request $request, $id)
    {
        $store = $request->user()->currentStore();

        if (!$store) {
            return $this->noStore();
        }

        $product = $store->products()
            ->with(['category', 'categories', 'brand', 'children', 'variants', 'stateHistories.user'])
            ->find($id);

        if (!$product) {
            return response()->json(['message' => 'Producto no encontrado.'], 404);
        }

        $this->present($product);
        $product->children->transform(fn ($child) => $this->present($child));
        $product->stateHistories->transform(function ($history) {
            $history->image_url = $history->image_url;

            return $history;
        });

        return response()->json($product);
    }

    public function store(Request $request, SaveProduct $saveProduct)
    {
        $store = $request->user()->currentStore();

        if (!$store) {
            return $this->noStore();
        }

        // Same action the web form uses (see SaveProduct): one set of rules for both surfaces.
        $product = $saveProduct->execute($request, $store, $request->user());

        // Registro inicial en el historial visual si hay imagen (lotes compuestos)
        if ($product->image_path) {
            ProductStateHistory::create([
                'product_id'  => $product->id,
                'user_id'     => $request->user()->id,
                'action_type' => 'initial_registry',
                'description' => 'Registro inicial del producto con foto.',
                'image_path'  => $product->image_path,
            ]);
        }

        return response()->json([
            'message' => 'Producto creado con éxito.',
            'product' => $this->present($product->load(['variants', 'categories'])),
        ], 201);
    }

    public function update(Request $request, SaveProduct $saveProduct, $id)
    {
        $store = $request->user()->currentStore();

        if (!$store) {
            return $this->noStore();
        }

        $product = $store->products()->find($id);

        if (!$product) {
            return response()->json(['message' => 'Producto no encontrado.'], 404);
        }

        // Partial: the app may send only the fields that changed (e.g. the visibility switch).
        $product = $saveProduct->execute($request, $store, $request->user(), $product, partial: true);

        if ($request->hasFile('image')) {
            ProductStateHistory::create([
                'product_id'  => $product->id,
                'user_id'     => $request->user()->id,
                'action_type' => 'photo_updated',
                'description' => 'Actualización manual de foto de producto.',
                'image_path'  => $product->image_path,
            ]);
        }

        return response()->json([
            'message' => 'Producto actualizado con éxito.',
            'product' => $this->present($product->load(['variants', 'categories'])),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $store = $request->user()->currentStore();

        if (!$store) {
            return $this->noStore();
        }

        $product = $store->products()->find($id);

        if (!$product) {
            return response()->json(['message' => 'Producto no encontrado.'], 404);
        }

        $product->delete();

        return response()->json(['message' => 'Producto eliminado con éxito.']);
    }

    public function addStateHistory(Request $request, $id)
    {
        $store = $request->user()->currentStore();
        if (!$store) {
            return $this->noStore();
        }

        $product = $store->products()->find($id);
        if (!$product) {
            return response()->json(['message' => 'Producto no encontrado.'], 404);
        }

        $data = $request->validate([
            'action_type' => 'required|string|in:initial_registry,child_sold,child_added,manual_adjustment,photo_updated',
            'description' => 'required|string',
            'image'       => 'required|image|max:5120',
            'metadata'    => 'nullable|string',
        ]);

        $path = $request->file('image')->store("stores/{$store->id}/products/history", 'public');

        $history = ProductStateHistory::create([
            'product_id'  => $product->id,
            'user_id'     => $request->user()->id,
            'action_type' => $data['action_type'],
            'description' => $data['description'],
            'image_path'  => $path,
            'metadata'    => $data['metadata'] ? json_decode($data['metadata'], true) : null,
        ]);

        // Actualizar foto principal del producto con el último estado visual
        $product->update(['image_path' => $path]);

        $history->image_url = $history->image_url;

        return response()->json([
            'message' => 'Historial de estado registrado con éxito.',
            'history' => $history,
            'product_image_url' => $product->image_url,
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────────

    /** Adds the computed attributes the app reads (image URLs, financials). */
    private function present(Product $product): Product
    {
        $product->image_url = $product->image_url;
        $product->gallery_urls = $product->gallery_urls;
        $product->video_url = $product->video_url;
        $product->total_cost = $product->total_cost;
        $product->revenue_generated = $product->revenue_generated;
        $product->net_profit = $product->net_profit;

        return $product;
    }
}
