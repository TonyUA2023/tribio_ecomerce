<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StoreController extends Controller
{
    private function getStore(string $slug): Store
    {
        if (request()->attributes->has('store')) {
            return request()->attributes->get('store');
        }

        // Si el slug tiene formato de dominio, buscar por dominio propio
        if (str_contains($slug, '.')) {
            return Store::where('custom_domain', $slug)
                ->active()
                ->firstOrFail();
        }

        return Store::where('slug', $slug)
            ->active()
            ->firstOrFail();
    }

    public function show(string $slug)
    {
        $store = $this->getStore($slug);
        $store->increment('total_views');

        $featuredProducts = $store->featuredProducts()->with('category')->limit(8)->get();
        $categories       = $store->categories()->withCount('activeProducts')->get();
        $galleryItems     = $store->galleryItems()->where('is_active', true)->limit(12)->get();
        $allProducts      = $store->activeProducts()->with('category')
            ->orderByDesc('is_featured')->orderBy('sort_order')->paginate(12);

        $isEditor = request()->query('editor') == 1 || request()->query('preview') == 1;

        // Usar el layout publicado si existe y no estamos en el editor, sino usar las secciones del borrador
        if (!$isEditor && is_array($store->published_layout)) {
            $sections = collect($store->published_layout)->map(function($section) {
                return (object) $section;
            })->filter(function($section) {
                return $section->is_active ?? true;
            });
        } else {
            // Si es editor o no hay publicado, mostramos el borrador
            $query = $store->sections()->orderBy('order');
            if (!$isEditor) {
                $query->where('is_active', true);
            }
            $sections = $query->get();
        }

        // Si está en modo de código a medida, buscar la vista del cliente
        if ($store->build_mode === 'custom_code') {
            $customView = "clientes_custom.{$store->slug}.index";
            if (\Illuminate\Support\Facades\View::exists($customView)) {
                return view($customView, compact('store', 'featuredProducts', 'categories', 'galleryItems', 'allProducts'));
            }
            abort(404, 'La vista personalizada para esta tienda aún no ha sido creada.');
        }

        // Pasar la vista correcta según la plantilla elegida
        $template = $store->template_name;

        return view("templates.{$template}.store", compact(
            'store', 'featuredProducts', 'categories', 'galleryItems', 'allProducts', 'sections'
        ));
    }

    public function catalog(Request $request, string $slug)
    {
        $store = $this->getStore($slug);
        $store->increment('total_views');

        $categories = $store->categories()->withCount('activeProducts')->get();
        $featuredProducts = $store->featuredProducts()->limit(3)->get();

        // Query para productos activos
        $query = $store->activeProducts()->with('category');

        // Filtros
        if ($request->filled('q')) {
            $search = $request->input('q');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $catSlug = $request->input('category');
            $query->whereHas('category', function($q) use ($catSlug) {
                $q->where('slug', $catSlug);
            });
        }

        if ($request->filled('brand')) {
            $brand = $request->input('brand');
            $query->where(function($q) use ($brand) {
                $q->where('name', 'like', "%{$brand}%")
                  ->orWhere('description', 'like', "%{$brand}%")
                  ->orWhere('sku', 'like', "%{$brand}%");
            });
        }

        // Precios límite para el slider
        $minPricePossible = floor($store->activeProducts()->min('price') ?? 0);
        $maxPricePossible = ceil($store->activeProducts()->max('price') ?? 1000);

        // Filtro por precio (min_price y max_price)
        if ($request->filled('min_price')) {
            $query->where('price', '>=', floatval($request->input('min_price')));
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', floatval($request->input('max_price')));
        }

        // Ordenamiento
        $sort = $request->input('sort', 'position');
        if ($sort === 'price_asc') {
            $query->orderBy('price', 'asc');
        } elseif ($sort === 'price_desc') {
            $query->orderBy('price', 'desc');
        } else {
            $query->orderByDesc('is_featured')->orderBy('sort_order');
        }

        $perPage = $request->integer('per_page', 16);
        $allProducts = $query->paginate($perPage)->withQueryString();

        // Si está en modo de código a medida
        if ($store->build_mode === 'custom_code') {
            $customView = "clientes_custom.{$store->slug}.catalog";
            if (\Illuminate\Support\Facades\View::exists($customView)) {
                return view($customView, compact(
                    'store', 'categories', 'featuredProducts', 'allProducts', 'minPricePossible', 'maxPricePossible'
                ));
            }
        }

        $template = $store->template_name;

        return view("templates.{$template}.catalog", compact(
            'store', 'categories', 'featuredProducts', 'allProducts', 'minPricePossible', 'maxPricePossible'
        ));
    }

    public function product(string $slug, string $product)
    {
        $store = $this->getStore($slug);
        
        $productModel = Product::where('slug', $product)
            ->where('store_id', $store->id)
            ->firstOrFail();

        $productModel->increment('views');
        $relatedProducts = $store->activeProducts()
            ->where('category_id', $productModel->category_id)
            ->where('id', '!=', $productModel->id)
            ->limit(4)->get();
            
        // Rename for view compatibility
        $product = $productModel;

        if ($store->build_mode === 'custom_code') {
            $customView = "clientes_custom.{$store->slug}.product";
            if (\Illuminate\Support\Facades\View::exists($customView)) {
                return view($customView, compact('store', 'product', 'relatedProducts'));
            }
        }

        $template = $store->template_name;
        return view("templates.{$template}.product", compact('store', 'product', 'relatedProducts'));
    }

    public function gallery(string $slug)
    {
        $store       = $this->getStore($slug);
        $galleryItems = $store->galleryItems()->where('is_active', true)->paginate(24);

        if ($store->build_mode === 'custom_code') {
            $customView = "clientes_custom.{$store->slug}.gallery";
            if (\Illuminate\Support\Facades\View::exists($customView)) {
                return view($customView, compact('store', 'galleryItems'));
            }
        }

        $template    = $store->template_name;
        return view("templates.{$template}.gallery", compact('store', 'galleryItems'));
    }

    public function checkout(Request $request, string $slug)
    {
        $store = $this->getStore($slug);

        $request->validate([
            'customer_name'    => 'required|string|max:255',
            'customer_phone'   => 'required|string|max:20',
            'customer_address' => 'nullable|string|max:500',
            'customer_notes'   => 'nullable|string|max:500',
            'items'            => 'required|array|min:1',
            'items.*.id'       => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $cartItems = collect($request->items);
        $products  = Product::whereIn('id', $cartItems->pluck('id'))
            ->where('store_id', $store->id)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        if ($products->isEmpty()) {
            return response()->json(['error' => 'Carrito inválido'], 422);
        }

        $subtotal = 0;
        $orderItemsData = [];

        foreach ($cartItems as $item) {
            $product  = $products[$item['id']] ?? null;
            if (!$product) continue;

            $qty      = (int) $item['quantity'];
            $itemSubtotal = $product->price * $qty;
            $subtotal += $itemSubtotal;

            $orderItemsData[] = [
                'product_id'    => $product->id,
                'product_name'  => $product->name,
                'product_sku'   => $product->sku,
                'product_image' => $product->image_path,
                'price'         => $product->price,
                'quantity'      => $qty,
                'subtotal'      => $itemSubtotal,
            ];
        }

        $order = Order::create([
            'store_id'         => $store->id,
            'order_number'     => Order::generateOrderNumber($store->id),
            'customer_name'    => $request->customer_name,
            'customer_phone'   => $request->customer_phone,
            'customer_address' => $request->customer_address,
            'customer_notes'   => $request->customer_notes,
            'subtotal'         => $subtotal,
            'total'            => $subtotal,
            'status'           => 'pending',
            'source'           => 'store',
        ]);

        $order->items()->createMany($orderItemsData);
        $store->increment('total_orders');

        return response()->json([
            'success'      => true,
            'order_number' => $order->order_number,
            'redirect_url' => route('store.order.confirmation', [$slug, $order]),
            'whatsapp_url' => $store->whatsapp_link . '?text=' . $order->buildWhatsappMessage(),
        ]);
    }

    public function orderConfirmation(string $slug, Order $order)
    {
        $store    = $this->getStore($slug);

        if ($store->build_mode === 'custom_code') {
            $customView = "clientes_custom.{$store->slug}.confirmation";
            if (\Illuminate\Support\Facades\View::exists($customView)) {
                return view($customView, compact('store', 'order'));
            }
        }

        $template = $store->template_name;
        return view("templates.{$template}.confirmation", compact('store', 'order'));
    }
}
