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
        // Cargar categorias principales con sus hijos, y 1 producto para la mega-imagen
        $categories       = $store->categories()->whereNull('parent_id')
                                ->with(['children', 'products' => function($q) { $q->latest()->limit(1); }])
                                ->withCount('activeProducts')->get();
        
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

        $categories = $store->categories()->whereNull('parent_id')
                        ->with(['children', 'products' => function($q) { $q->latest()->limit(1); }])
                        ->withCount('activeProducts')->get();
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
        $isUsd = request()->cookie('user_country') === 'US';
        $priceColumn = $isUsd ? 'price_usd' : 'price';

        $minPricePossible = floor($store->activeProducts()->min($priceColumn) ?? 0);
        $maxPricePossible = ceil($store->activeProducts()->max($priceColumn) ?? 1000);

        // Filtro por precio (min_price y max_price)
        if ($request->filled('min_price')) {
            $query->where($priceColumn, '>=', floatval($request->input('min_price')));
        }
        if ($request->filled('max_price')) {
            $query->where($priceColumn, '<=', floatval($request->input('max_price')));
        }

        // Ordenamiento
        $sort = $request->input('sort', 'position');
        if ($sort === 'price_asc') {
            $query->orderBy($priceColumn, 'asc');
        } elseif ($sort === 'price_desc') {
            $query->orderBy($priceColumn, 'desc');
        } elseif ($sort === 'oldest') {
            $query->orderBy('created_at', 'asc');
        } elseif ($sort === 'newest') {
            $query->orderBy('created_at', 'desc');
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

    public function getShippingCost(Request $request, string $slug)
    {
        $store = $this->getStore($slug);
        $country = $request->input('country', 'PE');
        $state = $request->input('state');

        // Look for exact state match first
        if ($state) {
            $rate = $store->shippingRates()->where('is_active', true)
                          ->where('country_code', $country)
                          ->where('state', $state)
                          ->first();
            if ($rate) {
                return response()->json(['cost' => $rate->cost]);
            }
        }

        // Look for country default
        $rate = $store->shippingRates()->where('is_active', true)
                      ->where('country_code', $country)
                      ->whereNull('state')
                      ->first();
        if ($rate) {
            return response()->json(['cost' => $rate->cost]);
        }

        // Look for ALL (International default)
        $rate = $store->shippingRates()->where('is_active', true)
                      ->where('country_code', 'ALL')
                      ->first();
        
        return response()->json(['cost' => $rate ? $rate->cost : 0]);
    }

    public function checkout(Request $request, string $slug)
    {
        $store = $this->getStore($slug);

        $request->validate([
            'customer_name'    => 'required|string|max:255',
            'customer_phone'   => 'required|string|max:20',
            'customer_email'   => 'required|email|max:255',
            'customer_address' => 'nullable|string|max:500',
            'customer_country' => 'nullable|string|max:2',
            'customer_state'   => 'nullable|string|max:100',
            'customer_city'    => 'nullable|string|max:100',
            'customer_zipcode' => 'nullable|string|max:20',
            'customer_notes'   => 'nullable|string|max:500',
            'items'            => 'required|array|min:1',
            'items.*.id'       => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'express_shipping' => 'nullable|boolean',
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
            $itemSubtotal = $product->resolvePrice() * $qty;
            $subtotal += $itemSubtotal;

            $orderItemsData[] = [
                'product_id'    => $product->id,
                'product_name'  => $product->name,
                'product_sku'   => $product->sku,
                'product_image' => $product->image_path,
                'price'         => $product->resolvePrice(),
                'quantity'      => $qty,
                'subtotal'      => $itemSubtotal,
            ];
        }

        // Calculate dynamic shipping cost
        $shippingCost = 0;
        $country = $request->customer_country ?? 'PE';
        $state = $request->customer_state;
        
        $rate = null;
        if ($state) {
            $rate = $store->shippingRates()->where('is_active', true)->where('country_code', $country)->where('state', $state)->first();
        }
        if (!$rate) {
            $rate = $store->shippingRates()->where('is_active', true)->where('country_code', $country)->whereNull('state')->first();
        }
        if (!$rate) {
            $rate = $store->shippingRates()->where('is_active', true)->where('country_code', 'ALL')->first();
        }
        
        if ($rate) {
            $shippingCost = $rate->cost;
        }

        $isExpress = false;
        if ($request->boolean('express_shipping') && $store->is_express_shipping_enabled) {
            $isExpress = true;
            $shippingCost += $store->express_shipping_cost;
        }

        $total = $subtotal + $shippingCost;
        $currency = request()->cookie('user_country') === 'US' ? 'USD' : 'PEN';

        $order = Order::create([
            'store_id'            => $store->id,
            'order_number'        => Order::generateOrderNumber($store->id),
            'customer_name'       => $request->customer_name,
            'customer_phone'      => $request->customer_phone,
            'customer_email'      => $request->customer_email,
            'customer_address'    => $request->customer_address,
            'customer_country'    => $country,
            'customer_state'      => $state,
            'customer_city'       => $request->customer_city,
            'customer_zipcode'    => $request->customer_zipcode,
            'customer_notes'      => $request->customer_notes,
            'subtotal'            => $subtotal,
            'shipping_cost'       => $shippingCost,
            'is_express_shipping' => $isExpress,
            'total'               => $total,
            'currency'            => $currency,
            'status'              => 'pending',
            'source'              => 'store',
        ]);

        $order->items()->createMany($orderItemsData);
        $store->increment('total_orders');

        $response = [
            'success'      => true,
            'order_number' => $order->order_number,
            'redirect_url' => route('store.order.confirmation', [$store->slug, $order->id]),
            'whatsapp_url' => $store->whatsapp_link . '?text=' . $order->buildWhatsappMessage(),
        ];

        // Mercado Pago Integration (Use mp_access_token or gateway_access_token)
        $mpToken = $store->mp_access_token ?? $store->gateway_access_token;
        if ($mpToken) {
            try {
                $client = new \GuzzleHttp\Client();
                
                $items = [];
                foreach ($order->items as $orderItem) {
                    $items[] = [
                        'title'       => $orderItem->product_name,
                        'quantity'    => $orderItem->quantity,
                        'unit_price'  => (float) $orderItem->price,
                        'currency_id' => $currency,
                    ];
                }
                
                if ($shippingCost > 0) {
                    $items[] = [
                        'title'       => 'Costo de Envío',
                        'quantity'    => 1,
                        'unit_price'  => (float) $shippingCost,
                        'currency_id' => $currency,
                    ];
                }

                $mpResponse = $client->post('https://api.mercadopago.com/checkout/preferences', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $mpToken,
                        'Content-Type'  => 'application/json',
                    ],
                    'json' => [
                        'items' => $items,
                        'payer' => [
                            'name' => $order->customer_name,
                            'email' => $order->customer_email,
                        ],
                        'back_urls' => [
                            'success' => route('store.order.confirmation', [$store->slug, $order->id]),
                            'failure' => route('store.order.confirmation', [$store->slug, $order->id]),
                            'pending' => route('store.order.confirmation', [$store->slug, $order->id]),
                        ],
                        'auto_return' => 'approved',
                        'external_reference' => $order->order_number,
                    ]
                ]);

                $preference = json_decode($mpResponse->getBody()->getContents(), true);
                if (isset($preference['init_point'])) {
                    $response['payment_url'] = $preference['init_point'];
                }
            } catch (\Exception $e) {
                \Log::error('Mercado Pago Error: ' . $e->getMessage());
            }
        }

        return response()->json($response);
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

    public function contact(string $slug)
    {
        $store = $this->getStore($slug);
        $categories = $store->categories()->whereNull('parent_id')->get();
        return view('templates.minimal-light.contact', compact('store', 'categories'));
    }

    public function submitContact(Request $request, string $slug)
    {
        $store = $this->getStore($slug);
        
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'subject' => 'nullable|string|max:255',
            'message' => 'required|string',
        ]);

        $store->contactMessages()->create($request->all());

        return back()->with('success', '¡Gracias por contactarnos! Tu mensaje ha sido enviado exitosamente.');
    }
}
