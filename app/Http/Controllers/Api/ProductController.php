<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $store = $request->user()->store;

        if (!$store) {
            return response()->json(['message' => 'No tienes ninguna tienda configurada.'], 404);
        }

        $query = $store->products()->with(['category', 'brand']);

        // Por defecto, no listamos subcomponentes en el catálogo general
        if (!$request->has('include_subcomponents') || $request->include_subcomponents == 'false') {
            $query->whereNull('parent_id');
        } else if ($request->has('parent_id')) {
            $query->where('parent_id', $request->parent_id);
        }

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->has('category_id') && $request->category_id !== 'all') {
            $query->where('category_id', $request->category_id);
        }

        $products = $query->orderBy('sort_order')->latest()->paginate(15);

        // Map Image URLs and financial indicators
        $products->getCollection()->transform(function ($product) {
            $product->image_url = $product->image_url;
            $product->total_cost = $product->total_cost;
            $product->revenue_generated = $product->revenue_generated;
            $product->net_profit = $product->net_profit;
            return $product;
        });

        return response()->json($products);
    }

    public function show(Request $request, $id)
    {
        $store = $request->user()->store;

        if (!$store) {
            return response()->json(['message' => 'No tienes ninguna tienda configurada.'], 404);
        }

        $product = $store->products()
            ->with(['category', 'brand', 'children', 'stateHistories.user'])
            ->find($id);

        if (!$product) {
            return response()->json(['message' => 'Producto no encontrado.'], 404);
        }

        $product->image_url = $product->image_url;
        $product->total_cost = $product->total_cost;
        $product->revenue_generated = $product->revenue_generated;
        $product->net_profit = $product->net_profit;

        $product->children->transform(function ($child) {
            $child->image_url = $child->image_url;
            $child->total_cost = $child->total_cost;
            $child->revenue_generated = $child->revenue_generated;
            $child->net_profit = $child->net_profit;
            return $child;
        });

        $product->stateHistories->transform(function ($history) {
            $history->image_url = $history->image_url;
            return $history;
        });

        return response()->json($product);
    }

    public function store(Request $request)
    {
        $store = $request->user()->store;

        if (!$store) {
            return response()->json(['message' => 'No tienes ninguna tienda configurada.'], 404);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
            'category_id' => 'nullable|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'parent_id' => 'nullable|exists:products,id',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string',
            'sku' => 'nullable|string',
            'track_stock' => 'boolean',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'is_composite' => 'boolean',
            'composite_type' => 'nullable|string|in:assembly,bucket,unit',
            'is_sold' => 'boolean',
            'image' => 'nullable|image|max:5120',
        ]);

        $data['store_id'] = $store->id;
        $data['slug'] = Str::slug($data['name']);
        
        // Ensure slug is unique for store
        $originalSlug = $data['slug'];
        $count = 1;
        while ($store->products()->where('slug', $data['slug'])->exists()) {
            $data['slug'] = $originalSlug . '-' . $count++;
        }

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store("stores/{$store->id}/products", 'public');
        }

        if (isset($data['is_sold']) && $data['is_sold']) {
            $data['sold_at'] = now();
        }

        unset($data['image']);

        $product = Product::create($data);

        // Crear historial de registro inicial si hay imagen
        if ($product->image_path) {
            \App\Models\ProductStateHistory::create([
                'product_id' => $product->id,
                'user_id' => $request->user()->id,
                'action_type' => 'initial_registry',
                'description' => 'Registro inicial del producto con foto.',
                'image_path' => $product->image_path,
            ]);
        }

        $product->image_url = $product->image_url;
        $product->total_cost = $product->total_cost;
        $product->revenue_generated = $product->revenue_generated;
        $product->net_profit = $product->net_profit;

        return response()->json([
            'message' => 'Producto creado con éxito.',
            'product' => $product
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $store = $request->user()->store;

        if (!$store) {
            return response()->json(['message' => 'No tienes ninguna tienda configurada.'], 404);
        }

        $product = $store->products()->find($id);

        if (!$product) {
            return response()->json(['message' => 'Producto no encontrado.'], 404);
        }

        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'price' => 'sometimes|required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
            'category_id' => 'nullable|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'parent_id' => 'nullable|exists:products,id',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string',
            'sku' => 'nullable|string',
            'track_stock' => 'boolean',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'is_composite' => 'boolean',
            'composite_type' => 'nullable|string|in:assembly,bucket,unit',
            'is_sold' => 'boolean',
            'image' => 'nullable|image|max:5120',
        ]);

        if (isset($data['name']) && $data['name'] !== $product->name) {
            $data['slug'] = Str::slug($data['name']);
            $originalSlug = $data['slug'];
            $count = 1;
            while ($store->products()->where('slug', $data['slug'])->where('id', '!=', $id)->exists()) {
                $data['slug'] = $originalSlug . '-' . $count++;
            }
        }

        if ($request->hasFile('image')) {
            if ($product->image_path) {
                // Opcional: borrar imagen anterior si no está en historial (de momento la dejamos por seguridad)
            }
            $data['image_path'] = $request->file('image')->store("stores/{$store->id}/products", 'public');
        }

        if (isset($data['is_sold'])) {
            if ($data['is_sold'] && !$product->is_sold) {
                $data['sold_at'] = now();
            } else if (!$data['is_sold']) {
                $data['sold_at'] = null;
            }
        }

        unset($data['image']);

        $product->update($data);

        // Si se subió nueva imagen y no existía historial previo, podemos crearlo
        if ($request->hasFile('image')) {
            \App\Models\ProductStateHistory::create([
                'product_id' => $product->id,
                'user_id' => $request->user()->id,
                'action_type' => 'photo_updated',
                'description' => 'Actualización manual de foto de producto.',
                'image_path' => $product->image_path,
            ]);
        }

        $product->image_url = $product->image_url;
        $product->total_cost = $product->total_cost;
        $product->revenue_generated = $product->revenue_generated;
        $product->net_profit = $product->net_profit;

        return response()->json([
            'message' => 'Producto actualizado con éxito.',
            'product' => $product
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $store = $request->user()->store;

        if (!$store) {
            return response()->json(['message' => 'No tienes ninguna tienda configurada.'], 404);
        }

        $product = $store->products()->find($id);

        if (!$product) {
            return response()->json(['message' => 'Producto no encontrado.'], 404);
        }

        $product->delete();

        return response()->json([
            'message' => 'Producto eliminado con éxito.'
        ]);
    }

    public function addStateHistory(Request $request, $id)
    {
        $store = $request->user()->store;
        if (!$store) {
            return response()->json(['message' => 'No tienes ninguna tienda configurada.'], 404);
        }

        $product = $store->products()->find($id);
        if (!$product) {
            return response()->json(['message' => 'Producto no encontrado.'], 404);
        }

        $data = $request->validate([
            'action_type' => 'required|string|in:initial_registry,child_sold,child_added,manual_adjustment,photo_updated',
            'description' => 'required|string',
            'image' => 'required|image|max:5120',
            'metadata' => 'nullable|string'
        ]);

        $path = $request->file('image')->store("stores/{$store->id}/products/history", 'public');

        $history = \App\Models\ProductStateHistory::create([
            'product_id' => $product->id,
            'user_id' => $request->user()->id,
            'action_type' => $data['action_type'],
            'description' => $data['description'],
            'image_path' => $path,
            'metadata' => $data['metadata'] ? json_decode($data['metadata'], true) : null,
        ]);

        // Actualizar foto principal del producto con el último estado visual
        $product->update([
            'image_path' => $path
        ]);

        $history->image_url = $history->image_url;

        return response()->json([
            'message' => 'Historial de estado registrado con éxito.',
            'history' => $history,
            'product_image_url' => $product->image_url
        ]);
    }
}
