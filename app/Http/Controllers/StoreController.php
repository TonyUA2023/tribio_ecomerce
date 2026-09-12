<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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

        $featuredProducts = $store->featuredProducts()->with(['categories', 'category'])->limit(8)->get();
        // Cargar categorias principales con sus hijos, y 1 producto para la mega-imagen
        $categories       = $store->categories()->whereNull('parent_id')
                                ->with(['children', 'products' => function($q) { $q->latest()->limit(1); }])
                                ->withCount('activeProducts')->get();
        
        $galleryItems     = $store->galleryItems()->where('is_active', true)->limit(12)->get();
        $allProducts      = $store->activeProducts()->with(['categories', 'category'])
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
                        ->withCount('activeProducts')
                        ->orderBy('name')
                        ->get();
        $brands = $store->brands()
                        ->withCount('products')
                        ->orderBy('name')
                        ->get();
        $featuredProducts = $store->featuredProducts()->limit(3)->get();

        // Query para productos activos
        $query = $store->activeProducts()->with(['categories', 'category', 'brand']);

        // Filtros
        if ($request->filled('q')) {
            $search = $request->input('q');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filtro por Categoría (singular o array)
        if ($request->filled('category')) {
            $catVal = $request->input('category');
            if ($catVal === 'destacados' || $catVal === 'featured') {
                $query->where('is_featured', true);
            } else {
                $categoryObj = $store->categories()->where(function($q) use ($catVal) {
                    $q->where('slug', $catVal)->orWhere('id', $catVal);
                })->first();

                if ($categoryObj) {
                    $childIds = $categoryObj->children()->pluck('id')->toArray();
                    $catIds = array_merge([$categoryObj->id], $childIds);
                    $query->where(function($sub) use ($catIds) {
                        $sub->whereIn('category_id', $catIds)
                            ->orWhereHas('categories', fn($sq) => $sq->whereIn('categories.id', $catIds));
                    });
                } else {
                    $query->where(function($sub) use ($catVal) {
                        $sub->whereHas('category', function($q) use ($catVal) {
                            $q->where('slug', $catVal)->orWhere('id', $catVal);
                        })->orWhereHas('categories', function($q) use ($catVal) {
                            $q->where('categories.slug', $catVal)->orWhere('categories.id', $catVal);
                        });
                    });
                }
            }
        } elseif ($request->filled('categories') && is_array($request->input('categories'))) {
            $catSlugs = $request->input('categories');
            $catIds = $store->categories()->whereIn('slug', $catSlugs)->orWhereIn('id', $catSlugs)->pluck('id')->toArray();
            if (!empty($catIds)) {
                $query->where(function($sub) use ($catIds) {
                    $sub->whereIn('category_id', $catIds)
                        ->orWhereHas('categories', fn($sq) => $sq->whereIn('categories.id', $catIds));
                });
            }
        }

        // Filtro por Marca (singular o array)
        if ($request->filled('brand')) {
            $brandVal = $request->input('brand');
            $query->whereHas('brand', function($q) use ($brandVal) {
                $q->where('slug', $brandVal)->orWhere('id', $brandVal);
            });
        } elseif ($request->filled('brands') && is_array($request->input('brands'))) {
            $brandVals = $request->input('brands');
            $query->whereHas('brand', function($q) use ($brandVals) {
                $q->whereIn('slug', $brandVals)->orWhereIn('id', $brandVals);
            });
        }

        // Precios límite para el slider y moneda
        $isUsd = request()->cookie('user_country') === 'US';
        $priceColumn = $isUsd ? 'price_usd' : 'price';
        $currencySymbol = $isUsd ? '$' : 'S/';

        $minPricePossible = floor($store->activeProducts()->min($priceColumn) ?? 0);
        $maxPricePossible = ceil($store->activeProducts()->max($priceColumn) ?? 100);
        if ($maxPricePossible <= $minPricePossible) {
            $maxPricePossible = $minPricePossible + 100;
        }

        // Filtro por precio (min_price y max_price)
        if ($request->filled('min_price') && is_numeric($request->input('min_price'))) {
            $query->where($priceColumn, '>=', floatval($request->input('min_price')));
        }
        if ($request->filled('max_price') && is_numeric($request->input('max_price'))) {
            $query->where($priceColumn, '<=', floatval($request->input('max_price')));
        }

        // Filtro: Solo en oferta
        if ($request->boolean('on_sale')) {
            $query->whereNotNull('compare_price')->whereColumn('compare_price', '>', 'price');
        }

        // Filtro: Solo con stock
        if ($request->boolean('in_stock')) {
            $query->where(function($q) {
                $q->where('stock', '>', 0)->orWhere('manage_stock', false);
            });
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
        } elseif ($sort === 'name_asc') {
            $query->orderBy('name', 'asc');
        } else {
            $query->orderByDesc('is_featured')->orderBy('sort_order');
        }

        $perPage = $request->integer('per_page', 16);
        $allProducts = $query->paginate($perPage)->withQueryString();

        $products = $allProducts;

        // Si está en modo de código a medida
        if ($store->build_mode === 'custom_code') {
            $customView = "clientes_custom.{$store->slug}.catalog";
            if (\Illuminate\Support\Facades\View::exists($customView)) {
                return view($customView, compact(
                    'store', 'categories', 'brands', 'featuredProducts', 'allProducts', 'products', 'minPricePossible', 'maxPricePossible', 'currencySymbol'
                ));
            }
        }

        $template = $store->template_name;

        return view("templates.{$template}.catalog", compact(
            'store', 'categories', 'brands', 'featuredProducts', 'allProducts', 'products', 'minPricePossible', 'maxPricePossible', 'currencySymbol'
        ));
    }

    public function product(string $slug, string $product)
    {
        $store = $this->getStore($slug);
        
        $productModel = Product::with(['categories', 'category'])
            ->where('slug', $product)
            ->where('store_id', $store->id)
            ->firstOrFail();

        $productModel->increment('views');
        
        $catIds = $productModel->categories->pluck('id')->toArray();
        if ($productModel->category_id && !in_array($productModel->category_id, $catIds)) {
            $catIds[] = $productModel->category_id;
        }

        $relatedProducts = collect();
        if (!empty($catIds)) {
            $relatedProducts = $store->activeProducts()->with(['categories', 'category'])
                ->where(function($sub) use ($catIds) {
                    $sub->whereIn('category_id', $catIds)
                        ->orWhereHas('categories', fn($sq) => $sq->whereIn('categories.id', $catIds));
                })
                ->where('id', '!=', $productModel->id)
                ->limit(4)
                ->get();
        }

        if ($relatedProducts->count() < 4) {
            $needed = 4 - $relatedProducts->count();
            $fillers = $store->activeProducts()
                ->where('id', '!=', $productModel->id)
                ->whereNotIn('id', $relatedProducts->pluck('id'))
                ->limit($needed)
                ->get();
            $relatedProducts = $relatedProducts->concat($fillers);
        }
            
        // Rename for view compatibility
        $product = $productModel;

        $categories = $store->categories()->whereNull('parent_id')->with('children')->get();

        if ($store->build_mode === 'custom_code') {
            $customView = "clientes_custom.{$store->slug}.product";
            if (\Illuminate\Support\Facades\View::exists($customView)) {
                return view($customView, compact('store', 'product', 'relatedProducts', 'categories'));
            }
        }

        $template = $store->template_name;
        return view("templates.{$template}.product", compact('store', 'product', 'relatedProducts', 'categories'));
    }

    public function gallery(string $slug)
    {
        $store       = $this->getStore($slug);
        $categories  = $store->categories()->whereNull('parent_id')->get();
        $galleryItems = $store->galleryItems()->where('is_active', true)->paginate(24);

        if ($store->build_mode === 'custom_code') {
            $customView = "clientes_custom.{$store->slug}.gallery";
            if (\Illuminate\Support\Facades\View::exists($customView)) {
                return view($customView, compact('store', 'galleryItems', 'categories'));
            }
        }

        $template    = $store->template_name;
        return view("templates.{$template}.gallery", compact('store', 'galleryItems', 'categories'));
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

        // Normalizar campos en caso lleguen sin el prefijo customer_
        $input = $request->all();
        $fields = ['name', 'phone', 'email', 'address', 'country', 'state', 'city', 'zipcode', 'notes'];
        foreach ($fields as $field) {
            if (!isset($input['customer_' . $field]) && isset($input[$field])) {
                $input['customer_' . $field] = $input[$field];
            }
        }
        $request->merge($input);

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

            $qty               = (int) $item['quantity'];
            $price             = $product->resolvePrice();
            $sku               = $product->sku;
            $variantId         = $item['variant_id'] ?? null;
            $variantTitle      = $item['variant_title'] ?? null;
            $variantAttributes = $item['variant_attributes'] ?? null;
            $imagePath         = $product->image_path;

            if ($variantId) {
                $variant = $product->variants()->where('id', $variantId)->first();
                if ($variant) {
                    $price             = $variant->resolvePrice();
                    if (!empty($variant->sku)) $sku = $variant->sku;
                    $variantTitle      = $variant->title;
                    $variantAttributes = $variant->attributes;
                    if (!empty($variant->image_path)) $imagePath = $variant->image_path;

                    if ($product->track_stock) {
                        if ($variant->stock < $qty) {
                            return response()->json([
                                'error' => "Stock insuficiente para {$product->name} ({$variantTitle}). Disponibles: {$variant->stock}."
                            ], 422);
                        }
                        $variant->decrement('stock', $qty);
                    }
                }
            }

            if ($product->track_stock) {
                if (!$variantId && $product->stock < $qty) {
                    return response()->json([
                        'error' => "Stock insuficiente para {$product->name}. Disponibles: {$product->stock}."
                    ], 422);
                }
                $product->decrement('stock', $qty);
            }

            $itemSubtotal = $price * $qty;
            $subtotal += $itemSubtotal;

            $orderItemsData[] = [
                'product_id'         => $product->id,
                'variant_id'         => $variantId,
                'variant_title'      => $variantTitle,
                'variant_attributes' => $variantAttributes,
                'product_name'       => $product->name,
                'product_sku'        => $sku,
                'product_image'      => $imagePath,
                'price'              => $price,
                'quantity'           => $qty,
                'subtotal'           => $itemSubtotal,
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

        // Gestión de cuenta de cliente universal Tribio
        $userId = Auth::check() ? Auth::id() : null;
        $accountCreated = false;

        if (!$userId && $request->boolean('create_account') && $request->filled('password')) {
            $customerEmail = trim(strtolower($request->customer_email));
            $user = User::where('email', $customerEmail)->first();
            if (!$user) {
                $user = User::create([
                    'name'     => $request->customer_name,
                    'email'    => $customerEmail,
                    'phone'    => $request->customer_phone,
                    'password' => Hash::make($request->password),
                    'role'     => User::ROLE_CLIENTE,
                ]);
                Auth::login($user, true);
                $accountCreated = true;
            }
            $userId = $user->id;
        }

        // Si hay usuario (o recién creado), registrar su dirección si es nueva
        if ($userId && !empty($request->customer_address)) {
            $customerUser = User::find($userId);
            if ($customerUser && $customerUser->isCliente()) {
                $addressType = $request->input('address_type', 'casa');
                $alreadyHasAddress = $customerUser->customerAddresses()
                    ->where('address', $request->customer_address)
                    ->exists();

                if (!$alreadyHasAddress) {
                    $customerUser->customerAddresses()->create([
                        'type'       => in_array($addressType, ['casa', 'trabajo', 'otro']) ? $addressType : 'casa',
                        'title'      => ucfirst($addressType),
                        'address'    => $request->customer_address,
                        'city'       => $request->customer_city,
                        'state'      => $state,
                        'country'    => $country,
                        'zipcode'    => $request->customer_zipcode,
                        'is_default' => $customerUser->customerAddresses()->count() === 0,
                    ]);
                }
            }
        }

        $paymentMethod = $request->input('payment_method', 'whatsapp');

        $order = Order::create([
            'store_id'            => $store->id,
            'user_id'             => $userId,
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
            'payment_status'      => 'pending',
            'payment_method'      => $paymentMethod,
            'source'              => 'store',
        ]);

        $order->items()->createMany($orderItemsData);
        $store->increment('total_orders');

        $whatsappNumber = preg_replace('/[^0-9]/', '', $store->whatsapp_phone ?? '');
        $whatsappUrl = $whatsappNumber ? ("https://wa.me/{$whatsappNumber}?text=" . $order->buildWhatsappMessage()) : null;

        $response = [
            'success'      => true,
            'order_number' => $order->order_number,
            'redirect_url' => route('store.order.confirmation', [$store->slug, $order->id]),
            'whatsapp_url' => $whatsappUrl,
        ];

        // Integración de Mercado Pago (Tarjetas Débito / Crédito 100% Seguras)
        $mpToken = $store->mp_access_token ?? $store->gateway_access_token;
        if (($paymentMethod === 'mercadopago' || $store->checkout_mode === 'card') && $mpToken) {
            try {
                $client = new \GuzzleHttp\Client(['timeout' => 15]);
                
                $items = [];
                foreach ($order->items as $orderItem) {
                    $itemTitle = $orderItem->product_name;
                    if (!empty($orderItem->variant_title)) {
                        $itemTitle .= " ({$orderItem->variant_title})";
                    }
                    $items[] = [
                        'title'       => Str::limit($itemTitle, 250),
                        'quantity'    => (int) $orderItem->quantity,
                        'unit_price'  => round((float) $orderItem->price, 2),
                        'currency_id' => $currency === 'USD' ? 'USD' : 'PEN',
                    ];
                }
                
                if ($shippingCost > 0) {
                    $items[] = [
                        'title'       => 'Costo de Envío',
                        'quantity'    => 1,
                        'unit_price'  => round((float) $shippingCost, 2),
                        'currency_id' => $currency === 'USD' ? 'USD' : 'PEN',
                    ];
                }

                $backUrl = route('store.order.confirmation', [$store->slug, $order->id]);
                $webhookUrl = route('api.mercadopago.webhook', [$store->id]);

                $preferenceBody = [
                    'items' => $items,
                    'payer' => [
                        'name'    => $order->customer_name,
                        'email'   => $order->customer_email,
                        'phone'   => [
                            'number' => preg_replace('/[^0-9]/', '', $order->customer_phone ?? '')
                        ],
                        'address' => [
                            'street_name' => $order->customer_address ?? ''
                        ]
                    ],
                    'back_urls' => [
                        'success' => $backUrl,
                        'failure' => $backUrl,
                        'pending' => $backUrl,
                    ],
                    'auto_return'         => 'approved',
                    'external_reference'  => (string) $order->order_number,
                    'statement_descriptor'=> Str::limit(preg_replace('/[^A-Za-z0-9 ]/', '', $store->name), 22),
                ];

                if (!str_contains($webhookUrl, 'localhost') && !str_contains($webhookUrl, '127.0.0.1')) {
                    $preferenceBody['notification_url'] = $webhookUrl;
                }

                $mpResponse = $client->post('https://api.mercadopago.com/checkout/preferences', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . trim($mpToken),
                        'Content-Type'  => 'application/json',
                    ],
                    'json' => $preferenceBody
                ]);

                $preference = json_decode($mpResponse->getBody()->getContents(), true);
                $isSandbox = str_starts_with(trim($mpToken), 'TEST-');
                $paymentUrl = $isSandbox ? ($preference['sandbox_init_point'] ?? $preference['init_point'] ?? null) : ($preference['init_point'] ?? null);

                if ($paymentUrl) {
                    $response['payment_url'] = $paymentUrl;
                    $order->update([
                        'payment_method' => 'mercadopago',
                        'internal_notes' => 'Mercado Pago Preference ID: ' . ($preference['id'] ?? 'N/A') . ' (' . ($isSandbox ? 'Sandbox/Test' : 'Producción') . ')'
                    ]);
                }
            } catch (\Exception $e) {
                \Log::error('Mercado Pago Error: ' . $e->getMessage());
            }
        }

        return response()->json($response);
    }

    public function orderConfirmation(string $slug, Order $order)
    {
        $store = $this->getStore($slug);

        // Procesar retorno de Mercado Pago si aplica
        $collectionStatus = request()->query('collection_status') ?? request()->query('status');
        $paymentId        = request()->query('payment_id') ?? request()->query('collection_id');

        if ($collectionStatus === 'approved') {
            $order->update([
                'status'         => 'confirmed',
                'payment_status' => 'paid',
                'payment_method' => 'mercadopago',
                'internal_notes' => trim(($order->internal_notes ?? '') . "\nMercado Pago ID: " . $paymentId . " (Pago Aprobado)")
            ]);
        } elseif (in_array($collectionStatus, ['rejected', 'cancelled'])) {
            $order->update([
                'payment_status' => 'failed',
                'internal_notes' => trim(($order->internal_notes ?? '') . "\nMercado Pago Estado: " . $collectionStatus)
            ]);
        }

        if ($store->build_mode === 'custom_code') {
            $customView = "clientes_custom.{$store->slug}.confirmation";
            if (\Illuminate\Support\Facades\View::exists($customView)) {
                return view($customView, compact('store', 'order'));
            }
        }

        $template = $store->template_name;
        return view("templates.{$template}.confirmation", compact('store', 'order'));
    }

    public function mercadopagoWebhook(Request $request, Store $store)
    {
        $type = $request->query('type') ?? $request->input('type') ?? $request->input('topic');
        $id   = $request->query('data_id') ?? $request->input('data.id') ?? $request->input('id');

        if (($type === 'payment' || $type === 'payment.created' || $type === 'payment.updated') && $id) {
            $mpToken = $store->mp_access_token ?? $store->gateway_access_token;
            if ($mpToken) {
                try {
                    $client = new \GuzzleHttp\Client(['timeout' => 10]);
                    $mpRes = $client->get("https://api.mercadopago.com/v1/payments/{$id}", [
                        'headers' => ['Authorization' => 'Bearer ' . trim($mpToken)]
                    ]);
                    $paymentData = json_decode($mpRes->getBody()->getContents(), true);
                    
                    if (!empty($paymentData['external_reference'])) {
                        $order = Order::where('order_number', $paymentData['external_reference'])
                            ->where('store_id', $store->id)
                            ->first();

                        if ($order) {
                            $status = $paymentData['status'] ?? '';
                            if ($status === 'approved') {
                                $order->update([
                                    'status'         => 'confirmed',
                                    'payment_status' => 'paid',
                                    'payment_method' => 'mercadopago',
                                    'internal_notes' => trim(($order->internal_notes ?? '') . "\nIPN Webhook: Aprobado #{$id}")
                                ]);
                            } elseif (in_array($status, ['rejected', 'cancelled'])) {
                                $order->update([
                                    'payment_status' => 'failed',
                                    'internal_notes' => trim(($order->internal_notes ?? '') . "\nIPN Webhook: {$status} #{$id}")
                                ]);
                            }
                        }
                    }
                } catch (\Exception $e) {
                    \Log::error("Mercado Pago Webhook Error ({$store->slug}): " . $e->getMessage());
                }
            }
        }

        return response()->json(['status' => 'ok'], 200);
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
