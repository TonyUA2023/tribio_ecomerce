<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\StoreSettingsController;
use App\Http\Controllers\Dashboard\ProductController;
use App\Http\Controllers\Dashboard\CategoryController;
use App\Http\Controllers\Dashboard\GalleryController;
use App\Http\Controllers\Dashboard\OrderController as DashboardOrderController;
use App\Http\Controllers\Dashboard\InventoryController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminStoreController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\StoreController;

// ═══════════════════════════════════════════════════════════════
//  PORTAL PÚBLICO TRIBIO
// ═══════════════════════════════════════════════════════════════
Route::get('/', [PublicController::class, 'home'])->name('home');
Route::get('/buscar', [PublicController::class, 'search'])->name('search');
Route::get('/negocios', [PublicController::class, 'directory'])->name('directory');

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
    Route::get('/tienda', [StoreSettingsController::class, 'edit'])->name('store.edit');
    Route::post('/tienda', [StoreSettingsController::class, 'update'])->name('store.update');
    Route::post('/tienda/logo', [StoreSettingsController::class, 'uploadLogo'])->name('store.logo');
    Route::post('/tienda/portada', [StoreSettingsController::class, 'uploadCover'])->name('store.cover');
    Route::post('/tienda/plantilla', [StoreSettingsController::class, 'updateTemplate'])->name('store.template');

    // Productos CRUD
    Route::resource('productos', ProductController::class)->parameters(['productos' => 'product']);

    // Categorías
    Route::resource('categorias', CategoryController::class)->parameters(['categorias' => 'category']);
    Route::post('/categorias/reordenar', [CategoryController::class, 'reorder'])->name('categorias.reorder');

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
//  TIENDAS PÚBLICAS
// ═══════════════════════════════════════════════════════════════
Route::prefix('tienda')->name('store.')->group(function () {
    Route::get('/{slug}', [StoreController::class, 'show'])->name('show');
    Route::get('/{slug}/catalogo', [StoreController::class, 'catalog'])->name('catalog');
    Route::get('/{slug}/producto/{product:slug}', [StoreController::class, 'product'])->name('product');
    Route::get('/{slug}/galeria', [StoreController::class, 'gallery'])->name('gallery');
    Route::post('/{slug}/checkout', [StoreController::class, 'checkout'])->name('checkout');
    Route::get('/{slug}/pedido/{order}/confirmacion', [StoreController::class, 'orderConfirmation'])->name('order.confirmation');
});
