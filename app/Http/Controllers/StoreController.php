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

        // Pasar la vista correcta según la plantilla elegida
        $template = $store->template_name;

        return view("templates.{$template}.store", compact(
            'store', 'featuredProducts', 'categories', 'galleryItems', 'allProducts'
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

        $template = $store->template_name;

        return view("templates.{$template}.catalog", compact(
            'store', 'categories', 'featuredProducts', 'allProducts', 'minPricePossible', 'maxPricePossible'
        ));
    }

    public function product(string $slug, Product $product)
    {
        $store = $this->getStore($slug);
        abort_if($product->store_id !== $store->id, 404);

        $product->increment('views');
        $relatedProducts = $store->activeProducts()
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->limit(4)->get();

        $template = $store->template_name;
        return view("templates.{$template}.product", compact('store', 'product', 'relatedProducts'));
    }

    public function gallery(string $slug)
    {
        $store       = $this->getStore($slug);
        $galleryItems = $store->galleryItems()->where('is_active', true)->paginate(24);
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
        $template = $store->template_name;

        return view("templates.{$template}.confirmation", compact('store', 'order'));
    }
}
