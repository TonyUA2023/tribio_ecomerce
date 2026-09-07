<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\InventoryMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    private function getStore()
    {
        return Auth::user()->store;
    }

    public function index(Request $request)
    {
        $store = $this->getStore();

        $products = $store->products()
            ->with('category')
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->when($request->category, fn($q) => $q->where('category_id', $request->category))
            ->when($request->status === 'active', fn($q) => $q->where('is_active', true))
            ->when($request->status === 'inactive', fn($q) => $q->where('is_active', false))
            ->when($request->stock === 'low', fn($q) => $q->where('track_stock', true)->whereColumn('stock', '<=', 'low_stock_alert')->where('stock', '>', 0))
            ->when($request->stock === 'out', fn($q) => $q->where('track_stock', true)->where('stock', 0))
            ->orderBy('sort_order')
            ->paginate(20);

        $categories = $store->categories;

        return view('dashboard.products.index', compact('store', 'products', 'categories'));
    }

    public function create()
    {
        $store      = $this->getStore();
        $categories = $store->categories;
        $brands     = $store->brands;
        return view('dashboard.products.create', compact('store', 'categories', 'brands'));
    }

    public function store(Request $request)
    {
        $store = $this->getStore();

        $data = $request->validate([
            'name'              => 'required|string|max:255',
            'description'       => 'nullable|string',
            'short_description' => 'nullable|string|max:200',
            'sku'               => 'nullable|string|max:50',
            'origin_code'       => 'nullable|string|max:100',
            'category_id'       => 'nullable|integer|exists:categories,id',
            'brand_id'          => 'nullable|integer|exists:brands,id',
            'price'             => 'required|numeric|min:0',
            'compare_price'     => 'nullable|numeric|min:0',
            'cost_price'        => 'nullable|numeric|min:0',
            'stock'             => 'required|integer|min:0',
            'track_stock'       => 'nullable|boolean',
            'allow_backorder'   => 'nullable|boolean',
            'low_stock_alert'   => 'nullable|integer|min:0',
            'unit'              => 'nullable|string|max:20',
            'is_active'         => 'nullable|boolean',
            'is_featured'       => 'nullable|boolean',
            'is_new'            => 'nullable|boolean',
            'image'             => 'nullable|image|mimes:png,jpg,jpeg,webp|max:3072',
            'gallery.*'         => 'nullable|image|mimes:png,jpg,jpeg,webp|max:3072',
            'tags'              => 'nullable|string',
        ]);

        $data['store_id'] = $store->id;
        $data['slug']     = Str::slug($data['name']) . '-' . Str::random(4);

        // Generar SKU único si no se ingresa
        if (empty($data['sku'])) {
            $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $store->slug), 0, 3));
            if (empty($prefix)) {
                $prefix = 'PROD';
            }
            $num = $store->products()->count() + 1;
            do {
                $sku = $prefix . '-' . str_pad($num, 4, '0', STR_PAD_LEFT);
                $exists = Product::where('store_id', $store->id)->where('sku', $sku)->exists();
                $num++;
            } while ($exists);
            $data['sku'] = $sku;
        }

        $data['track_stock']   = $request->boolean('track_stock');
        $data['allow_backorder']= $request->boolean('allow_backorder');
        $data['is_active']     = $request->boolean('is_active', true);
        $data['is_featured']   = $request->boolean('is_featured');
        $data['is_new']        = $request->boolean('is_new');
        $data['tags']          = $request->tags ? array_map('trim', explode(',', $request->tags)) : null;

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store("stores/{$store->id}/products", 'public');
        }

        if ($request->hasFile('gallery')) {
            $galleryPaths = [];
            foreach ($request->file('gallery') as $img) {
                $galleryPaths[] = $img->store("stores/{$store->id}/gallery-products", 'public');
            }
            $data['gallery_images'] = $galleryPaths;
        }

        $product = Product::create($data);

        // Registro de movimiento de inventario inicial
        if ($data['track_stock'] && $data['stock'] > 0) {
            InventoryMovement::create([
                'product_id'  => $product->id,
                'store_id'    => $store->id,
                'type'        => 'in',
                'quantity'    => $data['stock'],
                'stock_before'=> 0,
                'stock_after' => $data['stock'],
                'reason'      => 'Stock inicial al crear producto',
                'user_id'     => Auth::id(),
            ]);
        }

        return redirect()->route('dashboard.productos.index')
            ->with('success', "Producto '{$product->name}' creado correctamente.");
    }

    public function edit(Product $product)
    {
        $store = $this->getStore();
        abort_if($product->store_id !== $store->id, 403);
        $categories = $store->categories;
        $brands     = $store->brands;

        return view('dashboard.products.edit', compact('store', 'product', 'categories', 'brands'));
    }

    public function update(Request $request, Product $product)
    {
        $store = $this->getStore();
        abort_if($product->store_id !== $store->id, 403);

        $data = $request->validate([
            'name'              => 'required|string|max:255',
            'description'       => 'nullable|string',
            'short_description' => 'nullable|string|max:200',
            'sku'               => 'nullable|string|max:50',
            'origin_code'       => 'nullable|string|max:100',
            'category_id'       => 'nullable|integer|exists:categories,id',
            'brand_id'          => 'nullable|integer|exists:brands,id',
            'price'             => 'required|numeric|min:0',
            'compare_price'     => 'nullable|numeric|min:0',
            'cost_price'        => 'nullable|numeric|min:0',
            'stock'             => 'required|integer|min:0',
            'track_stock'       => 'nullable|boolean',
            'allow_backorder'   => 'nullable|boolean',
            'low_stock_alert'   => 'nullable|integer|min:0',
            'unit'              => 'nullable|string|max:20',
            'is_active'         => 'nullable|boolean',
            'is_featured'       => 'nullable|boolean',
            'is_new'            => 'nullable|boolean',
            'image'             => 'nullable|image|mimes:png,jpg,jpeg,webp|max:3072',
            'tags'              => 'nullable|string',
        ]);

        $data['track_stock']   = $request->boolean('track_stock');
        $data['allow_backorder']= $request->boolean('allow_backorder');
        $data['is_active']     = $request->boolean('is_active');
        $data['is_featured']   = $request->boolean('is_featured');
        $data['is_new']        = $request->boolean('is_new');
        $data['tags']          = $request->tags ? array_map('trim', explode(',', $request->tags)) : null;

        // Registrar movimiento si cambió el stock
        if ($data['track_stock'] && (int)$data['stock'] !== $product->stock) {
            $diff = (int)$data['stock'] - $product->stock;
            InventoryMovement::create([
                'product_id'   => $product->id,
                'store_id'     => $store->id,
                'type'         => 'adjustment',
                'quantity'     => $diff,
                'stock_before' => $product->stock,
                'stock_after'  => (int)$data['stock'],
                'reason'       => 'Ajuste manual desde edición de producto',
                'user_id'      => Auth::id(),
            ]);
        }

        if ($request->hasFile('image')) {
            if ($product->image_path) Storage::disk('public')->delete($product->image_path);
            $data['image_path'] = $request->file('image')->store("stores/{$store->id}/products", 'public');
        }

        $product->update($data);

        return redirect()->route('dashboard.productos.index')
            ->with('success', "Producto '{$product->name}' actualizado correctamente.");
    }

    public function destroy(Product $product)
    {
        $store = $this->getStore();
        abort_if($product->store_id !== $store->id, 403);

        if ($product->image_path) Storage::disk('public')->delete($product->image_path);
        $product->delete();

        return back()->with('success', 'Producto eliminado.');
    }
}
