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
            ->with(['categories', 'category'])
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->when($request->category, function($q) use ($request) {
                $catId = $request->category;
                $q->where(function($sub) use ($catId) {
                    $sub->where('category_id', $catId)
                        ->orWhereHas('categories', fn($sq) => $sq->where('categories.id', $catId));
                });
            })
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
            'categories'        => 'nullable|array',
            'categories.*'      => 'integer|exists:categories,id',
            'brand_id'          => 'nullable|integer|exists:brands,id',
            'price'             => 'required|numeric|min:0',
            'compare_price'     => 'nullable|numeric|min:0',
            'price_usd'         => 'nullable|numeric|min:0',
            'compare_price_usd' => 'nullable|numeric|min:0',
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

        // Resolución de categorías múltiples y principal
        $categoryIds = $request->input('categories', []);
        if (is_string($categoryIds)) {
            $categoryIds = json_decode($categoryIds, true) ?? [];
        }
        if (!is_array($categoryIds)) {
            $categoryIds = [];
        }
        $categoryIds = array_values(array_filter(array_map('intval', $categoryIds)));

        if ($request->filled('category_id') && in_array((int)$request->category_id, $categoryIds)) {
            $data['category_id'] = (int)$request->category_id;
        } elseif (!empty($categoryIds)) {
            $data['category_id'] = $categoryIds[0];
        } elseif ($request->filled('category_id')) {
            $data['category_id'] = (int)$request->category_id;
            $categoryIds = [(int)$request->category_id];
        } else {
            $data['category_id'] = null;
        }

        $product = Product::create($data);

        // Sincronizar categorías en la tabla pivot
        if (!empty($categoryIds)) {
            $product->categories()->sync($categoryIds);
        }

        // Guardar variantes si tiene activado has_variants
        if ($request->boolean('has_variants')) {
            $product->has_variants = true;
            if ($request->filled('variant_options_json')) {
                $product->variant_options = json_decode($request->input('variant_options_json'), true);
            }
            $product->save();

            if ($request->filled('variants_json')) {
                $variantsData = json_decode($request->input('variants_json'), true);
                if (is_array($variantsData)) {
                    $totalVariantStock = 0;
                    foreach ($variantsData as $v) {
                        $vStock = intval($v['stock'] ?? 0);
                        $totalVariantStock += $vStock;
                        $product->variants()->create([
                            'sku'           => !empty($v['sku']) ? $v['sku'] : ($product->sku . '-' . Str::random(4)),
                            'price'         => !empty($v['price']) ? floatval($v['price']) : null,
                            'compare_price' => !empty($v['compare_price']) ? floatval($v['compare_price']) : null,
                            'price_usd'     => !empty($v['price_usd']) ? floatval($v['price_usd']) : null,
                            'stock'         => $vStock,
                            'attributes'    => $v['attributes'] ?? [],
                            'is_active'     => isset($v['is_active']) ? (bool) $v['is_active'] : true,
                        ]);
                    }
                    if ($totalVariantStock > 0 && $product->stock == 0) {
                        $product->update(['stock' => $totalVariantStock]);
                    }
                }
            }
        }

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
        $product->load(['variants', 'categories']);

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
            'categories'        => 'nullable|array',
            'categories.*'      => 'integer|exists:categories,id',
            'brand_id'          => 'nullable|integer|exists:brands,id',
            'price'             => 'required|numeric|min:0',
            'compare_price'     => 'nullable|numeric|min:0',
            'price_usd'         => 'nullable|numeric|min:0',
            'compare_price_usd' => 'nullable|numeric|min:0',
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
            'remove_gallery'    => 'nullable|array',
            'tags'              => 'nullable|string',
        ]);

        $data['track_stock']   = $request->boolean('track_stock');
        $data['allow_backorder']= $request->boolean('allow_backorder');
        $data['is_active']     = $request->boolean('is_active');
        $data['is_featured']   = $request->boolean('is_featured');
        $data['is_new']        = $request->boolean('is_new');
        $data['tags']          = $request->tags ? array_map('trim', explode(',', $request->tags)) : null;

        // Variantes
        $hasVariants = $request->boolean('has_variants');
        $data['has_variants'] = $hasVariants;
        if ($hasVariants && $request->filled('variant_options_json')) {
            $data['variant_options'] = json_decode($request->input('variant_options_json'), true);
        } else if (!$hasVariants) {
            $data['variant_options'] = null;
        }

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

        // Gestión de Galería de Imágenes Secundarias
        $currentGallery = is_array($product->gallery_images) ? $product->gallery_images : [];

        // Eliminar imágenes seleccionadas para borrar
        if ($request->filled('remove_gallery')) {
            foreach ($request->input('remove_gallery') as $pathToRemove) {
                if (in_array($pathToRemove, $currentGallery)) {
                    Storage::disk('public')->delete($pathToRemove);
                    $currentGallery = array_values(array_filter($currentGallery, fn($p) => $p !== $pathToRemove));
                }
            }
        }

        // Agregar nuevas fotos secundarias
        if ($request->hasFile('gallery')) {
            foreach ($request->file('gallery') as $img) {
                $currentGallery[] = $img->store("stores/{$store->id}/gallery-products", 'public');
            }
        }

        $data['gallery_images'] = array_values($currentGallery);

        // Resolución de categorías múltiples y principal
        $categoryIds = $request->input('categories', []);
        if (is_string($categoryIds)) {
            $categoryIds = json_decode($categoryIds, true) ?? [];
        }
        if (!is_array($categoryIds)) {
            $categoryIds = [];
        }
        $categoryIds = array_values(array_filter(array_map('intval', $categoryIds)));

        if ($request->filled('category_id') && in_array((int)$request->category_id, $categoryIds)) {
            $data['category_id'] = (int)$request->category_id;
        } elseif (!empty($categoryIds)) {
            $data['category_id'] = $categoryIds[0];
        } elseif ($request->filled('category_id')) {
            $data['category_id'] = (int)$request->category_id;
            $categoryIds = [(int)$request->category_id];
        } else {
            $data['category_id'] = null;
        }

        $product->update($data);
        $product->categories()->sync($categoryIds);

        // Sincronizar Variantes
        if ($hasVariants && $request->filled('variants_json')) {
            $variantsData = json_decode($request->input('variants_json'), true);
            if (is_array($variantsData)) {
                $keptIds = [];
                $totalVariantStock = 0;
                foreach ($variantsData as $v) {
                    $vStock = intval($v['stock'] ?? 0);
                    $totalVariantStock += $vStock;
                    $payload = [
                        'sku'           => !empty($v['sku']) ? $v['sku'] : ($product->sku . '-' . Str::random(4)),
                        'price'         => !empty($v['price']) ? floatval($v['price']) : null,
                        'compare_price' => !empty($v['compare_price']) ? floatval($v['compare_price']) : null,
                        'price_usd'     => !empty($v['price_usd']) ? floatval($v['price_usd']) : null,
                        'stock'         => $vStock,
                        'attributes'    => $v['attributes'] ?? [],
                        'is_active'     => isset($v['is_active']) ? (bool) $v['is_active'] : true,
                    ];

                    if (!empty($v['id'])) {
                        $existing = $product->variants()->find($v['id']);
                        if ($existing) {
                            $existing->update($payload);
                            $keptIds[] = $existing->id;
                            continue;
                        }
                    }

                    $created = $product->variants()->create($payload);
                    $keptIds[] = $created->id;
                }

                $product->variants()->whereNotIn('id', $keptIds)->delete();

                if ($totalVariantStock > 0 && $product->stock == 0) {
                    $product->update(['stock' => $totalVariantStock]);
                }
            }
        } elseif (!$hasVariants) {
            $product->variants()->delete();
        }

        return redirect()->route('dashboard.productos.index')
            ->with('success', "Producto '{$product->name}' actualizado correctamente.");
    }

    public function destroy(Product $product)
    {
        $store = $this->getStore();
        abort_if($product->store_id !== $store->id, 403);

        if ($product->image_path) Storage::disk('public')->delete($product->image_path);
        if (!empty($product->gallery_images) && is_array($product->gallery_images)) {
            foreach ($product->gallery_images as $img) {
                Storage::disk('public')->delete($img);
            }
        }
        $product->delete();

        return back()->with('success', 'Producto eliminado.');
    }
}
