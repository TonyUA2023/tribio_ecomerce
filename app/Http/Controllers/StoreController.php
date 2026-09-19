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

        $featuredProducts = $store->featuredProducts()
            ->where(fn($q) => $q->where('price', '>', 0)->orWhere('price_usd', '>', 0))
            ->with(['categories', 'category'])
            ->limit(8)
            ->get();
        // Cargar categorias principales con sus hijos, y 1 producto para la mega-imagen
        $categories       = $store->categories()->whereNull('parent_id')
                                ->with([
                                    'children', 
                                    'products' => function($q) { 
                                        $q->where(fn($sq) => $sq->where('price', '>', 0)->orWhere('price_usd', '>', 0))->latest()->limit(1); 
                                    }
                                ])
                                ->withCount([
                                    'activeProducts' => function($q) {
                                        $q->where(fn($sq) => $sq->where('price', '>', 0)->orWhere('price_usd', '>', 0));
                                    }
                                ])
                                ->get();
        
        $galleryItems     = $store->galleryItems()->where('is_active', true)->limit(12)->get();
        $allProducts      = $store->activeProducts()
            ->where(fn($q) => $q->where('price', '>', 0)->orWhere('price_usd', '>', 0))
            ->with(['categories', 'category'])
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

        // Videos destacados para la portada (después del Hero)
        // 1. Primero los seleccionados manualmente con show_video_on_home = true (máximo 3)
        $homeVideoProducts = $store->activeProducts()
            ->whereNotNull('video_path')
            ->where('show_video_on_home', true)
            ->where(fn($q) => $q->where('price', '>', 0)->orWhere('price_usd', '>', 0))
            ->with(['categories', 'category'])
            ->limit(3)
            ->get();

        // 2. Si hay menos de 3 fijados, completar aleatoriamente con otros productos con video
        if ($homeVideoProducts->count() < 3) {
            $needed = 3 - $homeVideoProducts->count();
            $additional = $store->activeProducts()
                ->whereNotNull('video_path')
                ->whereNotIn('id', $homeVideoProducts->pluck('id'))
                ->where(fn($q) => $q->where('price', '>', 0)->orWhere('price_usd', '>', 0))
                ->with(['categories', 'category'])
                ->inRandomOrder()
                ->limit($needed)
                ->get();
            $homeVideoProducts = $homeVideoProducts->concat($additional);
        }

        // Si está en modo de código a medida, buscar la vista del cliente
        if ($store->build_mode === 'custom_code') {
            $customView = "clientes_custom.{$store->slug}.index";
            if (\Illuminate\Support\Facades\View::exists($customView)) {
                return view($customView, compact('store', 'featuredProducts', 'categories', 'galleryItems', 'allProducts', 'homeVideoProducts'));
            }
            abort(404, 'La vista personalizada para esta tienda aún no ha sido creada.');
        }

        // Pasar la vista correcta según la plantilla elegida
        $template = $store->template_name;

        return view("templates.{$template}.store", compact(
            'store', 'featuredProducts', 'categories', 'galleryItems', 'allProducts', 'sections', 'homeVideoProducts'
        ));
    }

    public function catalog(Request $request, string $slug)
    {
        $store = $this->getStore($slug);
        $store->increment('total_views');

        $categories = $store->categories()->whereNull('parent_id')
                        ->with([
                            'children', 
                            'products' => function($q) { 
                                $q->where(fn($sq) => $sq->where('price', '>', 0)->orWhere('price_usd', '>', 0))->latest()->limit(1); 
                            }
                        ])
                        ->withCount([
                            'activeProducts' => function($q) {
                                $q->where(fn($sq) => $sq->where('price', '>', 0)->orWhere('price_usd', '>', 0));
                            }
                        ])
                        ->orderBy('name')
                        ->get();
        $brands = $store->brands()
                        ->withCount([
                            'products' => function($q) {
                                $q->where('is_active', true)->where(fn($sq) => $sq->where('price', '>', 0)->orWhere('price_usd', '>', 0));
                            }
                        ])
                        ->orderBy('name')
                        ->get();
        $featuredProducts = $store->featuredProducts()
                        ->where(fn($q) => $q->where('price', '>', 0)->orWhere('price_usd', '>', 0))
                        ->limit(3)
                        ->get();

        // Query para productos activos
        $query = $store->activeProducts()
                    ->where(fn($q) => $q->where('price', '>', 0)->orWhere('price_usd', '>', 0))
                    ->with(['categories', 'category', 'brand']);

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
        $isUsd = \App\Helpers\CurrencyHelper::currentCurrency() === 'USD';
        $priceColumn = $isUsd ? 'price_usd' : 'price';
        $currencySymbol = \App\Helpers\CurrencyHelper::symbol();

        $minPricePossible = floor($store->activeProducts()->when($isUsd, fn($q) => $q->where('price_usd', '>', 0))->min($priceColumn) ?? 0);
        $maxPricePossible = ceil($store->activeProducts()->when($isUsd, fn($q) => $q->where('price_usd', '>', 0))->max($priceColumn) ?? 100);
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
        $isUsd = \App\Helpers\CurrencyHelper::isUsd();
        
        $productModel = Product::with(['categories', 'category'])
            ->where('slug', $product)
            ->where('store_id', $store->id)
            ->firstOrFail();

        // Si la tienda se está navegando en USD y este producto NO tiene precio en USD, redirigir al catálogo
        if ($isUsd && (!$productModel->price_usd || $productModel->price_usd <= 0)) {
            return redirect()->route('store.catalog', $store->slug);
        }

        $productModel->increment('views');
        
        $catIds = $productModel->categories->pluck('id')->toArray();
        if ($productModel->category_id && !in_array($productModel->category_id, $catIds)) {
            $catIds[] = $productModel->category_id;
        }

        $relatedProducts = collect();
        if (!empty($catIds)) {
            $relatedProducts = $store->activeProducts()
                ->when($isUsd, fn($q) => $q->where('price_usd', '>', 0))
                ->with(['categories', 'category'])
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
                ->when($isUsd, fn($q) => $q->where('price_usd', '>', 0))
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

    public function resolveShippingCostForStore(Store $store, string $country = 'PE', ?string $state = null): float
    {
        $country = strtoupper(trim($country ?: 'PE'));

        // 1. Envío Nacional Plano para Perú (Tarifa Única para todo el país)
        if ($country === 'PE') {
            if ($state) {
                $rate = $store->shippingRates()->where('is_active', true)
                              ->where('country_code', 'PE')
                              ->where('state', $state)
                              ->first();
                if ($rate) {
                    return (float) $rate->cost;
                }
            }
            if ($store->national_shipping_cost !== null && (float) $store->national_shipping_cost >= 0) {
                return (float) $store->national_shipping_cost;
            }
        }

        // 2. Tarifa específica configurada por país en country_shipping_costs
        $countryCosts = is_array($store->country_shipping_costs) ? $store->country_shipping_costs : json_decode($store->country_shipping_costs ?? '[]', true);
        if (!empty($countryCosts) && isset($countryCosts[$country]) && is_numeric($countryCosts[$country])) {
            return (float) $countryCosts[$country];
        }

        // 3. Consulta por departamento / estado en shipping_rates
        if ($state) {
            $rate = $store->shippingRates()->where('is_active', true)
                          ->where('country_code', $country)
                          ->where('state', $state)
                          ->first();
            if ($rate) {
                return (float) $rate->cost;
            }
        }

        // 4. Consulta por país predeterminado en shipping_rates
        $rate = $store->shippingRates()->where('is_active', true)
                      ->where('country_code', $country)
                      ->whereNull('state')
                      ->first();
        if ($rate) {
            return (float) $rate->cost;
        }

        // 5. Fallback Internacional General (ALL)
        $rate = $store->shippingRates()->where('is_active', true)
                      ->where('country_code', 'ALL')
                      ->first();
        
        return $rate ? (float) $rate->cost : 0.0;
    }

    public function getShippingCost(Request $request, string $slug)
    {
        $store = $this->getStore($slug);
        $country = $request->input('country', 'PE');
        $state = $request->input('state');
        $quantity = (int) $request->input('quantity', 0);
        $subtotal = (float) $request->input('subtotal', 0);

        $cost = $this->resolveShippingCostForStore($store, $country, $state);
        $freeShipping = $store->qualifiesForFreeShipping($subtotal, $quantity);
        if ($freeShipping) {
            $cost = 0;
        }

        return response()->json([
            'cost'          => $cost,
            'free_shipping' => $freeShipping,
            'discount'      => $store->calculateBulkDiscount($subtotal, $quantity),
        ]);
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
            // Resolved in parallel regardless of the customer's browsing currency: PayPal
            // never settles in PEN (confirmed against PayPal's currency-codes reference),
            // so its branch below always needs a USD amount, prefering each product's own
            // explicit USD price over a live-converted one (see Product::resolvePrice()).
            $priceUsd          = $product->resolvePrice('USD');
            $sku               = $product->sku;
            $variantId         = $item['variant_id'] ?? null;
            $variantTitle      = $item['variant_title'] ?? null;
            $variantAttributes = $item['variant_attributes'] ?? null;
            $imagePath         = $product->image_path;

            if ($variantId) {
                $variant = $product->variants()->where('id', $variantId)->first();
                if ($variant) {
                    $price             = $variant->resolvePrice();
                    $priceUsd          = $variant->resolvePrice('USD');
                    if (!empty($variant->sku)) $sku = $variant->sku;
                    $variantTitle      = $variant->title;
                    $variantAttributes = $variant->attributes;
                    if (!empty($variant->image_path)) $imagePath = $variant->image_path;

                    if ($product->track_stock && empty($product->out_of_stock_message) && !$product->allow_backorder) {
                        if ($variant->stock < $qty) {
                            return response()->json([
                                'error' => "Stock insuficiente para {$product->name} ({$variantTitle}). Disponibles: {$variant->stock}."
                            ], 422);
                        }
                    }
                    if ($product->track_stock) {
                        $variant->decrement('stock', $qty);
                    }
                }
            }

            if ($product->track_stock && empty($product->out_of_stock_message) && !$product->allow_backorder) {
                if (!$variantId && $product->stock < $qty) {
                    return response()->json([
                        'error' => "Stock insuficiente para {$product->name}. Disponibles: {$product->stock}."
                    ], 422);
                }
            }
            if ($product->track_stock) {
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
                'price_usd'          => $priceUsd,
                'quantity'           => $qty,
                'subtotal'           => $itemSubtotal,
            ];
        }

        // Descuento por cantidad (compra al por mayor) — se calcula sobre el subtotal
        // original, antes de envío. Ver Store::calculateBulkDiscount().
        $totalQuantity = collect($orderItemsData)->sum('quantity');
        $discount = $store->calculateBulkDiscount($subtotal, $totalQuantity);

        // Calculate dynamic shipping cost
        $country = $request->customer_country ?? 'PE';
        $state   = $request->customer_state;
        $shippingCost = $this->resolveShippingCostForStore($store, $country, $state);

        // Envío gratis por cantidad o monto (ver Store::qualifiesForFreeShipping) anula
        // solo la tarifa base — el envío express, si el cliente lo elige aparte, se sigue
        // cobrando normalmente.
        if ($store->qualifiesForFreeShipping($subtotal, $totalQuantity)) {
            $shippingCost = 0;
        }

        $isExpress = false;
        if ($request->boolean('express_shipping') && $store->is_express_shipping_enabled) {
            $isExpress = true;
            $shippingCost += $store->express_shipping_cost;
        }

        $total = $subtotal - $discount + $shippingCost;
        $currency = \App\Helpers\CurrencyHelper::currentCurrency();

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
            'discount'            => $discount,
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

        // Integración de Mercado Pago — dos caminos distintos según lo que el cliente
        // eligió en el drawer (ver Mercado-Pago-Checkout-Flow en la bóveda del proyecto):
        //  1) Tarjeta: se tokeniza en el navegador con MercadoPago.js (nunca toca nuestro
        //     servidor en texto plano) y llega aquí como `mp_form_data.token` — se cobra
        //     directo vía /v1/payments, sin salir de la tienda.
        //  2) "Otros medios" (Yape, PagoEfectivo, banca, etc.): no se pueden representar
        //     como campos de formulario, así que usan Checkout Pro (redirección a la
        //     página alojada por Mercado Pago) — llegan aquí sin `mp_form_data`.
        $mpToken = $store->mp_access_token ?? $store->gateway_access_token;
        if ($paymentMethod !== 'paypal' && ($paymentMethod === 'mercadopago' || $store->checkout_mode === 'card') && $mpToken) {
            $mpFormData = $request->input('mp_form_data');
            try {
                $client = new \GuzzleHttp\Client(['timeout' => 15]);

                if ($mpFormData && !empty($mpFormData['token'])) {
                    // --- TARJETA EMBEBIDA (sin redirección) ---
                    $paymentBody = [
                        'transaction_amount' => round((float) $total, 2),
                        'token'              => $mpFormData['token'],
                        'description'        => 'Pedido en ' . $store->name . ' - ' . $order->order_number,
                        'installments'       => (int) ($mpFormData['installments'] ?? 1),
                        'payment_method_id'  => $mpFormData['payment_method_id'] ?? null,
                        'issuer_id'          => $mpFormData['issuer_id'] ?? null,
                        'external_reference' => (string) $order->order_number,
                        'payer'              => array_merge([
                            'email' => $order->customer_email,
                        ], $mpFormData['payer'] ?? []),
                    ];

                    $mpResponse = $client->post('https://api.mercadopago.com/v1/payments', [
                        'headers' => [
                            'Authorization'      => 'Bearer ' . trim($mpToken),
                            'Content-Type'       => 'application/json',
                            'X-Idempotency-Key'  => (string) $order->order_number . '_' . time(),
                        ],
                        'json' => $paymentBody,
                    ]);

                    $paymentData = json_decode($mpResponse->getBody()->getContents(), true);
                    $status = $paymentData['status'] ?? '';

                    if ($status === 'approved') {
                        $order->update([
                            'status'          => 'confirmed',
                            'payment_status'  => 'paid',
                            'payment_method'  => 'mercadopago',
                            'internal_notes'  => trim(($order->internal_notes ?? '') . "\nMercado Pago Payment ID: " . ($paymentData['id'] ?? 'N/A') . ' (Aprobado — tarjeta embebida)'),
                        ]);
                    } elseif (in_array($status, ['in_process', 'pending'], true)) {
                        $order->update([
                            'payment_method' => 'mercadopago',
                            'internal_notes' => trim(($order->internal_notes ?? '') . "\nMercado Pago Payment ID: " . ($paymentData['id'] ?? 'N/A') . " (En proceso: {$status})"),
                        ]);
                    } else {
                        $statusDetail = $paymentData['status_detail'] ?? 'desconocido';
                        \Log::error('Mercado Pago: pago con tarjeta rechazado.', [
                            'order_number' => $order->order_number,
                            'response'     => $paymentData,
                        ]);
                        $order->update([
                            'payment_status' => 'failed',
                            'internal_notes' => trim(($order->internal_notes ?? '') . "\nPago con tarjeta rechazado: {$statusDetail}"),
                        ]);
                        return response()->json([
                            'success' => false,
                            'error'   => 'Tu tarjeta fue rechazada (' . $statusDetail . '). Verifica los datos o intenta con otro método de pago.',
                        ]);
                    }
                    // Aprobado o pendiente: continúa hacia $response normal más abajo —
                    // el cliente se queda en la tienda, ve la confirmación sin salir.
                } else {
                    // --- OTROS MEDIOS DE MERCADO PAGO (Checkout Pro, con redirección) ---
                    // Se arma desde $orderItemsData (ya en memoria, recién usado para crear
                    // el pedido) y no desde $order->items: el observer de Order ya dejó esa
                    // relación cacheada como vacía (ver el comentario en OrderObserver::created),
                    // así que leerla aquí de nuevo mandaría un carrito vacío a Mercado Pago.
                    $items = [];
                    foreach ($orderItemsData as $orderItem) {
                        $itemTitle = $orderItem['product_name'];
                        if (!empty($orderItem['variant_title'])) {
                            $itemTitle .= " ({$orderItem['variant_title']})";
                        }
                        $items[] = [
                            'title'       => Str::limit($itemTitle, 250),
                            'quantity'    => (int) $orderItem['quantity'],
                            'unit_price'  => round((float) $orderItem['price'], 2),
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

                    // Descuento por cantidad (Store::calculateBulkDiscount): Mercado Pago no
                    // tiene un campo dedicado para descuentos en la preference, así que se
                    // representa como una línea más con precio negativo — sin esto, la suma
                    // de $items no coincidiría con $total y el cliente pagaría de más.
                    if ($discount > 0) {
                        $items[] = [
                            'title'       => 'Descuento por cantidad',
                            'quantity'    => 1,
                            'unit_price'  => round((float) $discount, 2) * -1,
                            'currency_id' => $currency === 'USD' ? 'USD' : 'PEN',
                        ];
                    }

                    $backUrl = route('store.order.confirmation', [$store->slug, $order->id]);
                    $webhookUrl = route('api.mercadopago.webhook', [$store->id]);
                    // Mercado Pago no acepta `auto_return`/`notification_url` apuntando a una URL
                    // no pública (ej. localhost/127.0.0.1 en desarrollo local) — rechaza la creación
                    // de la preference entera con "auto_return invalid" si se envía de todas formas.
                    $isPubliclyReachable = !str_contains($backUrl, 'localhost') && !str_contains($backUrl, '127.0.0.1');

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
                        'external_reference'  => (string) $order->order_number,
                        'statement_descriptor'=> Str::limit(preg_replace('/[^A-Za-z0-9 ]/', '', $store->name), 22),
                    ];

                    if ($isPubliclyReachable) {
                        $preferenceBody['auto_return'] = 'approved';
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
                    } else {
                        \Log::error('Mercado Pago: la preference se creó pero no devolvió una URL de pago.', [
                            'order_number' => $order->order_number,
                            'preference_response' => $preference,
                        ]);
                        $order->update([
                            'payment_status' => 'failed',
                            'internal_notes' => trim(($order->internal_notes ?? '') . "\nMercado Pago no devolvió una URL de pago: " . json_encode($preference)),
                        ]);
                        return response()->json([
                            'success' => false,
                            'error' => 'No se pudo iniciar el pago con Mercado Pago. Intenta de nuevo o elige otro método.',
                        ]);
                    }
                }
            } catch (\GuzzleHttp\Exception\RequestException $e) {
                $mpErrorBody = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
                \Log::error('Mercado Pago Error: ' . $mpErrorBody, ['order_number' => $order->order_number]);
                $order->update([
                    'payment_status' => 'failed',
                    'internal_notes' => trim(($order->internal_notes ?? '') . "\nError Mercado Pago: " . $mpErrorBody),
                ]);
                return response()->json([
                    'success' => false,
                    'error' => 'No se pudo conectar con Mercado Pago. Intenta de nuevo o elige otro método de pago.',
                ]);
            } catch (\Exception $e) {
                \Log::error('Mercado Pago Error: ' . $e->getMessage(), ['order_number' => $order->order_number]);
                $order->update([
                    'payment_status' => 'failed',
                    'internal_notes' => trim(($order->internal_notes ?? '') . "\nError Mercado Pago: " . $e->getMessage()),
                ]);
                return response()->json([
                    'success' => false,
                    'error' => 'No se pudo iniciar el pago con Mercado Pago. Intenta de nuevo o elige otro método de pago.',
                ]);
            }
        }

        // Integración de PayPal — cada tienda usa sus propias credenciales (App\Services\
        // PayPalService, ver PayPal-Checkout-Flow en la bóveda). PayPal no liquida en PEN
        // (confirmado contra la referencia oficial de monedas de PayPal antes de construir
        // esto), así que cobra siempre en USD usando price_usd, ya resuelto por artículo
        // más arriba vía Product::resolvePrice('USD'). El flujo es de dos pasos: aquí solo
        // se crea la orden en PayPal (el cliente todavía no aprobó nada); capturePaypalOrder()
        // la cobra de verdad una vez el popup de PayPal confirma la aprobación.
        if ($paymentMethod === 'paypal') {
            $paypalService = app(\App\Services\PayPalService::class);

            if (!$paypalService->isConfigured($store)) {
                $order->update(['payment_status' => 'failed']);
                return response()->json([
                    'success' => false,
                    'error'   => 'Esta tienda no tiene PayPal configurado. Elige otro método de pago.',
                ]);
            }

            try {
                $shippingUsd = $shippingCost > 0
                    ? app(\App\Services\ExchangeRateService::class)->convert((float) $shippingCost, $currency, 'USD')
                    : 0.0;

                // El descuento por cantidad (Store::calculateBulkDiscount) ya se calculó
                // en soles/moneda de la tienda más arriba — se convierte a USD para que la
                // orden de PayPal cobre lo mismo, en vez de el monto completo sin descuento.
                $discountUsd = $discount > 0
                    ? app(\App\Services\ExchangeRateService::class)->convert((float) $discount, $currency, 'USD')
                    : 0.0;

                $paypalItems = array_map(fn ($item) => [
                    'name'        => $item['product_name'] . (!empty($item['variant_title']) ? " ({$item['variant_title']})" : ''),
                    'unit_amount' => (float) $item['price_usd'],
                    'quantity'    => (int) $item['quantity'],
                ], $orderItemsData);

                $paypalOrder = $paypalService->createOrder($store, $order->order_number, $paypalItems, $shippingUsd, $discountUsd);

                $order->update([
                    'payment_method'  => 'paypal',
                    'paypal_order_id' => $paypalOrder['id'],
                ]);

                // El frontend usa este id para abrir el popup de PayPal (paypalPaymentSession
                // .start()); la orden Tribio queda 'pending' hasta que capturePaypalOrder()
                // confirme el pago — el cliente nunca sale de la tienda mientras tanto.
                $response['paypal_order_id'] = $paypalOrder['id'];
            } catch (\Throwable $e) {
                \Log::error('PayPal: error al crear la orden', [
                    'order_number' => $order->order_number,
                    'message'      => $e->getMessage(),
                ]);
                $order->update([
                    'payment_status' => 'failed',
                    'internal_notes' => trim(($order->internal_notes ?? '') . "\nError PayPal: " . $e->getMessage()),
                ]);
                return response()->json([
                    'success' => false,
                    'error'   => 'No se pudo iniciar el pago con PayPal. Intenta de nuevo o elige otro método.',
                ]);
            }
        }

        return response()->json($response);
    }

    /**
     * Captures a previously-created PayPal order once the customer approves it in the
     * popup. Synchronous — the customer sees the result immediately, same as the
     * embedded Mercado Pago card path.
     */
    public function capturePaypalOrder(Request $request, string $slug)
    {
        $store = $this->getStore($slug);

        $request->validate([
            'order_number'    => 'required|string',
            'paypal_order_id' => 'required|string',
        ]);

        $order = Order::where('store_id', $store->id)
            ->where('order_number', $request->order_number)
            ->where('paypal_order_id', $request->paypal_order_id)
            ->firstOrFail();

        if ($order->payment_status === 'paid') {
            return response()->json(['success' => true, 'order_number' => $order->order_number]);
        }

        $paypalService = app(\App\Services\PayPalService::class);

        try {
            $capture = $paypalService->captureOrder($store, $order->paypal_order_id);
            $status  = $capture['status'] ?? '';

            if ($status === 'COMPLETED') {
                $captureId = $capture['purchase_units'][0]['payments']['captures'][0]['id'] ?? null;
                $order->update([
                    'status'         => 'confirmed',
                    'payment_status' => 'paid',
                    'internal_notes' => trim(($order->internal_notes ?? '') . "\nPayPal Capture ID: " . ($captureId ?? 'N/A')),
                ]);

                return response()->json([
                    'success'      => true,
                    'order_number' => $order->order_number,
                    'redirect_url' => route('store.order.confirmation', [$store->slug, $order->id]),
                ]);
            }

            \Log::error('PayPal: captura no completada.', ['order_number' => $order->order_number, 'response' => $capture]);
            $order->update([
                'payment_status' => 'failed',
                'internal_notes' => trim(($order->internal_notes ?? '') . "\nCaptura PayPal no completada: {$status}"),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'PayPal no pudo completar el pago. Intenta de nuevo o elige otro método.',
            ]);
        } catch (\Throwable $e) {
            \Log::error('PayPal: error al capturar la orden', [
                'order_number' => $order->order_number,
                'message'      => $e->getMessage(),
            ]);
            $order->update([
                'payment_status' => 'failed',
                'internal_notes' => trim(($order->internal_notes ?? '') . "\nError al capturar PayPal: " . $e->getMessage()),
            ]);

            return response()->json([
                'success' => false,
                'error'   => $e->getMessage() ?: 'No se pudo completar el pago con PayPal.',
            ]);
        }
    }

    /**
     * Optional reconciliation webhook — the core create+capture flow above already
     * confirms payment synchronously. Only does anything once the store has configured
     * a Webhook ID in their own PayPal app dashboard (verifyWebhookSignature() returns
     * false otherwise, and the event is ignored rather than trusted blindly).
     */
    public function paypalWebhook(Request $request, string $slug)
    {
        $store = $this->getStore($slug);
        $paypalService = app(\App\Services\PayPalService::class);

        \Log::info('PayPal Webhook recibido:', ['store_id' => $store->id, 'event_type' => $request->input('event_type')]);

        if (!$paypalService->verifyWebhookSignature($store, $request)) {
            \Log::warning('PayPal Webhook: firma inválida o webhook_id no configurado.', ['store_id' => $store->id]);
            return response()->json(['status' => 'ignored'], 200);
        }

        $eventType = $request->input('event_type');
        $paypalOrderId = $request->input('resource.supplementary_data.related_ids.order_id')
            ?? $request->input('resource.id');

        if (!$paypalOrderId || !in_array($eventType, ['PAYMENT.CAPTURE.COMPLETED', 'CHECKOUT.ORDER.APPROVED'], true)) {
            return response()->json(['status' => 'ignored'], 200);
        }

        $order = Order::where('store_id', $store->id)->where('paypal_order_id', $paypalOrderId)->first();
        if ($order && $order->payment_status !== 'paid') {
            $paypalOrder = $paypalService->getOrder($store, $paypalOrderId);
            if (($paypalOrder['status'] ?? null) === 'COMPLETED') {
                $order->update(['status' => 'confirmed', 'payment_status' => 'paid']);
            }
        }

        return response()->json(['status' => 'ok'], 200);
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
