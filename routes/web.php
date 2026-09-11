<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\StoreSettingsController;
use App\Http\Controllers\Dashboard\ProductController;
use App\Http\Controllers\Dashboard\CategoryController;
use App\Http\Controllers\Dashboard\BrandController;
use App\Http\Controllers\Dashboard\GalleryController;
use App\Http\Controllers\Dashboard\OrderController as DashboardOrderController;
use App\Http\Controllers\Dashboard\InventoryController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminStoreController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\Dashboard\StoreBuilderController;

// ═══════════════════════════════════════════════════════════════
//  PORTAL PÚBLICO TRIBIO
// ═══════════════════════════════════════════════════════════════
Route::get('/', [PublicController::class, 'home'])->name('home');
Route::get('/buscar', [PublicController::class, 'search'])->name('search');
Route::get('/negocios', [PublicController::class, 'directory'])->name('directory');
Route::post('/plan/checkout', [SubscriptionController::class, 'checkout'])->name('plan.checkout');
Route::get('/plan/callback', [SubscriptionController::class, 'callback'])->name('plan.callback');
Route::post('/plan/webhook', [SubscriptionController::class, 'webhook'])->name('plan.webhook');

// ═══════════════════════════════════════════════════════════════
//  AUTENTICACIÓN
// ═══════════════════════════════════════════════════════════════
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);

    // Registro multi-step
    Route::get('/registro', [RegisterController::class, 'showForm'])->name('register');
    Route::post('/registro', [RegisterController::class, 'register']);
});

Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

// ═══════════════════════════════════════════════════════════════
//  DASHBOARD - VENDEDOR
// ═══════════════════════════════════════════════════════════════
Route::middleware(['auth', 'role:store_owner,super_admin'])->prefix('dashboard')->name('dashboard.')->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('index');

    // Configuración de la tienda
    Route::get('/tienda/configuracion', [StoreSettingsController::class, 'edit'])->name('store.edit');
    Route::post('/tienda/configuracion', [StoreSettingsController::class, 'update'])->name('store.update');
    Route::post('/tienda/logo', [StoreSettingsController::class, 'uploadLogo'])->name('store.logo');
    Route::post('/tienda/portada', [StoreSettingsController::class, 'uploadCover'])->name('store.cover');
    Route::get('/tienda/plantillas', [StoreSettingsController::class, 'templates'])->name('store.templates');
    Route::post('/tienda/plantilla', [StoreSettingsController::class, 'updateTemplate'])->name('store.template');

    // Shipping
    Route::get('/tienda/envios', [\App\Http\Controllers\Dashboard\ShippingController::class, 'index'])->name('shipping.index');
    Route::post('/tienda/envios', [\App\Http\Controllers\Dashboard\ShippingController::class, 'store'])->name('shipping.store');
    Route::delete('/tienda/envios/{shipping}', [\App\Http\Controllers\Dashboard\ShippingController::class, 'destroy'])->name('shipping.destroy');

    // Constructor visual
    Route::get('/tienda/constructor', [StoreBuilderController::class, 'index'])->name('store.builder');
    Route::post('/tienda/constructor/secciones', [StoreBuilderController::class, 'store'])->name('store.builder.store');
    Route::put('/tienda/constructor/secciones/{id}', [StoreBuilderController::class, 'update'])->name('store.builder.update');
    Route::delete('/tienda/constructor/secciones/{id}', [StoreBuilderController::class, 'destroy'])->name('store.builder.destroy');
    Route::post('/tienda/constructor/publicar', [StoreBuilderController::class, 'publish'])->name('store.builder.publish');
    Route::post('/tienda/constructor/reordenar', [StoreBuilderController::class, 'reorder'])->name('store.builder.reorder');
    Route::post('/tienda/constructor/cargar-plantilla', [StoreBuilderController::class, 'loadFromTemplate'])->name('store.builder.load_template');
    Route::post('/tienda/constructor/render-preview', [StoreBuilderController::class, 'renderPreview'])->name('store.builder.render_preview');
    Route::post('/tienda/constructor/upload-image', [StoreBuilderController::class, 'uploadImage'])->name('store.builder.upload_image');

    // Productos CRUD
    Route::resource('productos', ProductController::class)->parameters(['productos' => 'product']);

    // Categorías
    Route::resource('categorias', CategoryController::class)->parameters(['categorias' => 'category']);
    Route::post('/categorias/reordenar', [CategoryController::class, 'reorder'])->name('categorias.reorder');
    Route::post('/categorias/{category}/toggle-header', [CategoryController::class, 'toggleHeader'])->name('categorias.toggle-header');
    Route::post('/categorias/{category}/toggle-featured', [CategoryController::class, 'toggleFeatured'])->name('categorias.toggle-featured');

    // Marcas
    Route::resource('marcas', BrandController::class)->parameters(['marcas' => 'brand']);

    // Galería
    Route::get('/galeria', [GalleryController::class, 'index'])->name('galeria.index');
    Route::post('/galeria', [GalleryController::class, 'store'])->name('galeria.store');
    Route::delete('/galeria/{item}', [GalleryController::class, 'destroy'])->name('galeria.destroy');
    Route::post('/galeria/reordenar', [GalleryController::class, 'reorder'])->name('galeria.reorder');

    // Pedidos
    Route::get('/pedidos', [DashboardOrderController::class, 'index'])->name('pedidos.index');
    Route::get('/pedidos/{order}', [DashboardOrderController::class, 'show'])->name('pedidos.show');
    Route::patch('/pedidos/{order}/status', [DashboardOrderController::class, 'updateStatus'])->name('pedidos.status');
    Route::get('/pedidos/{order}/whatsapp', [DashboardOrderController::class, 'whatsappRedirect'])->name('pedidos.whatsapp');

    // Inventario
    Route::get('/inventario', [InventoryController::class, 'index'])->name('inventario.index');
    Route::get('/inventario/{product}', [InventoryController::class, 'product'])->name('inventario.product');
    Route::post('/inventario/{product}/ajuste', [InventoryController::class, 'adjust'])->name('inventario.adjust');
    Route::get('/inventario/exportar', [InventoryController::class, 'export'])->name('inventario.export');
});

// ═══════════════════════════════════════════════════════════════
//  SUPER ADMIN
// ═══════════════════════════════════════════════════════════════
Route::middleware(['auth', 'role:super_admin'])->prefix('admin')->name('admin.')->group(function () {

    Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');

    // Tiendas
    Route::get('/tiendas', [AdminStoreController::class, 'index'])->name('tiendas.index');
    Route::get('/tiendas/{store}', [AdminStoreController::class, 'show'])->name('tiendas.show');
    Route::patch('/tiendas/{store}/status', [AdminStoreController::class, 'updateStatus'])->name('tiendas.status');
    Route::patch('/tiendas/{store}/featured', [AdminStoreController::class, 'toggleFeatured'])->name('tiendas.featured');
    Route::delete('/tiendas/{store}', [AdminStoreController::class, 'destroy'])->name('tiendas.destroy');

    // Usuarios
    Route::get('/usuarios', [AdminUserController::class, 'index'])->name('usuarios.index');
    Route::get('/usuarios/{user}', [AdminUserController::class, 'show'])->name('usuarios.show');

    // Analytics globales (API)
    Route::get('/analytics', [AdminController::class, 'analytics'])->name('analytics');
    Route::get('/analytics/data', [AdminController::class, 'analyticsData'])->name('analytics.data');
});

// ═══════════════════════════════════════════════════════════════
//  TIENDAS PÚBLICAS EN DOMINIOS PROPIOS
// ═══════════════════════════════════════════════════════════════
$mainDomain = parse_url(config('app.url'), PHP_URL_HOST) ?? 'localhost';
$excludedDomains = ['localhost', '127.0.0.1', '::1'];
if (!in_array($mainDomain, $excludedDomains)) {
    $excludedDomains[] = $mainDomain;
}
$excludedPattern = '^(?!(' . implode('|', array_map(function($d) {
    return preg_quote($d, '#') . '|.*?\.' . preg_quote($d, '#');
}, $excludedDomains)) . ')$).*';

Route::domain('{custom_domain}')
    ->where(['custom_domain' => $excludedPattern])
    ->group(function () {
        Route::get('/', [StoreController::class, 'show']);
        Route::get('/catalogo', [StoreController::class, 'catalog']);
        Route::get('/producto/{product}', [StoreController::class, 'product']);
        Route::get('/galeria', [StoreController::class, 'gallery']);
        Route::post('/checkout', [StoreController::class, 'checkout']);
        Route::get('/pedido/{order}/confirmacion', [StoreController::class, 'orderConfirmation']);
        Route::get('/contacto', [StoreController::class, 'contact']);
        Route::post('/contacto', [StoreController::class, 'submitContact']);
    });

// ────────── TIENDAS PÚBLICAS ESTÁNDAR ──────────
Route::prefix('tienda')->name('store.')->group(function () {
    Route::get('/{slug}', [StoreController::class, 'show'])->name('show');
    Route::get('/{slug}/catalogo', [StoreController::class, 'catalog'])->name('catalog');
    Route::get('/{slug}/producto/{product}', [StoreController::class, 'product'])->name('product');
    Route::get('/{slug}/galeria', [StoreController::class, 'gallery'])->name('gallery');
    Route::post('/{slug}/checkout', [StoreController::class, 'checkout'])->name('checkout');
    Route::get('/{slug}/pedido/{order}/confirmacion', [StoreController::class, 'orderConfirmation'])->name('order.confirmation');
    Route::get('/{slug}/contacto', [StoreController::class, 'contact'])->name('contact');
    Route::post('/{slug}/contacto', [StoreController::class, 'submitContact'])->name('contact.submit');
});

// API para costos de envío
Route::get('/api/shipping-cost/{slug}', [\App\Http\Controllers\StoreController::class, 'getShippingCost']);

// ────────── PORTAL DEL CLIENTE / COMPRADOR UNIVERSAL ──────────
Route::prefix('customer')->name('customer.')->group(function () {
    Route::post('/check-email', [\App\Http\Controllers\CustomerPortalController::class, 'checkEmail'])->name('check-email');
    Route::get('/current', [\App\Http\Controllers\CustomerPortalController::class, 'current'])->name('current');
    Route::post('/login', [\App\Http\Controllers\CustomerPortalController::class, 'login'])->name('login');
    Route::post('/register', [\App\Http\Controllers\CustomerPortalController::class, 'register'])->name('register');
    Route::post('/logout', [\App\Http\Controllers\CustomerPortalController::class, 'logout'])->name('logout');
    Route::get('/orders', [\App\Http\Controllers\CustomerPortalController::class, 'orders'])->name('orders');
    Route::match(['get', 'post'], '/track-order', [\App\Http\Controllers\CustomerPortalController::class, 'trackOrder'])->name('track-order');
    Route::get('/addresses', [\App\Http\Controllers\CustomerPortalController::class, 'addresses'])->name('addresses');
    Route::post('/addresses', [\App\Http\Controllers\CustomerPortalController::class, 'saveAddress'])->name('addresses.save');
    Route::delete('/addresses/{id}', [\App\Http\Controllers\CustomerPortalController::class, 'deleteAddress'])->name('addresses.delete');
});
