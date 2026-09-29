<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CustomerAuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\GalleryController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\MarketingController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentGatewayController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\ShippingRateController;
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\Api\WorkspaceController;
use App\Http\Resources\Mobile\StoreSummaryResource;

// Autenticación pública
Route::post('/login', [AuthController::class, 'login']);

// Tribio Pass — identidad de comprador (mobile). Puerta separada de /login:
// funciona para cualquier cuenta canUseCustomerPortal() (cliente o store_owner),
// no solo store_owner/super_admin.
Route::prefix('customer')->group(function () {
    Route::post('/register/send-otp', [CustomerAuthController::class, 'sendOtp']);
    Route::post('/register/verify', [CustomerAuthController::class, 'verifyOtp']);
    Route::post('/login', [CustomerAuthController::class, 'login']);
});

// Webhook Mercado Pago
Route::post('/mercadopago/webhook/{store}', [\App\Http\Controllers\StoreController::class, 'mercadopagoWebhook'])->name('api.mercadopago.webhook');

// Rutas protegidas
Route::post('/flow/{store}/confirmation', [\App\Http\Controllers\FlowPaymentController::class, 'confirmation'])->name('flow.confirmation');
Route::match(['get', 'post'], '/flow/{store}/return', [\App\Http\Controllers\FlowPaymentController::class, 'returned'])->name('flow.return');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // Solo la identidad ligera de la tienda: la fila completa trae credenciales de pago.
    Route::get('/user', function (Request $request) {
        $store = $request->user()->currentStore();

        return response()->json([
            'user' => $request->user(),
            'store' => $store ? new StoreSummaryResource($store) : null,
        ]);
    });

    // Tribio Pass — historial de compras del comprador autenticado
    Route::get('/customer/orders', [CustomerAuthController::class, 'orders']);
    // Tribio Pass — calificar un producto comprado (misma regla que la web: pedido entregado)
    Route::post('/customer/reviews', [\App\Http\Controllers\CustomerReviewController::class, 'store'])->middleware('throttle:20,1');

    // Dashboard del vendedor (paridad con el dashboard web)
    Route::prefix('dashboard')->group(function () {
        Route::get('/stats', [DashboardController::class, 'index']);

        // Tiendas de la cuenta (Tribio Pass multi-tienda)
        Route::get('/tiendas', [WorkspaceController::class, 'index']);
        Route::post('/tiendas/{store}/cambiar', [WorkspaceController::class, 'switch'])->whereNumber('store');

        // Mi tienda
        Route::get('/tienda', [StoreController::class, 'show']);
        Route::put('/tienda', [StoreController::class, 'update']);
        Route::post('/tienda/logo', [StoreController::class, 'uploadLogo']);
        Route::post('/tienda/portada', [StoreController::class, 'uploadCover']);

        // Pedidos
        Route::get('/pedidos', [OrderController::class, 'index']);
        Route::get('/pedidos/{id}', [OrderController::class, 'show'])->whereNumber('id');
        Route::patch('/pedidos/{id}/status', [OrderController::class, 'updateStatus'])->whereNumber('id');
        Route::patch('/pedidos/{order}/produccion', [OrderController::class, 'updateProduction'])->whereNumber('order');
        Route::post('/pedidos/{order}/pagos', [OrderController::class, 'recordPayment'])->whereNumber('order');

        // Productos
        Route::post('/productos/{id}/historial-estado', [ProductController::class, 'addStateHistory']);
        Route::apiResource('/productos', ProductController::class)->parameters(['productos' => 'id']);

        // Categorías
        Route::post('/categorias/reordenar', [CategoryController::class, 'reorder']);
        Route::post('/categorias/{id}/encabezado', [CategoryController::class, 'toggleHeader'])->whereNumber('id');
        Route::post('/categorias/{id}/destacada', [CategoryController::class, 'toggleFeatured'])->whereNumber('id');
        Route::apiResource('/categorias', CategoryController::class)->parameters(['categorias' => 'id'])->except('show');

        // Marcas
        Route::apiResource('/marcas', BrandController::class)->parameters(['marcas' => 'id'])->except('show');

        // Galería
        Route::get('/galeria', [GalleryController::class, 'index']);
        Route::post('/galeria', [GalleryController::class, 'store']);
        Route::post('/galeria/reordenar', [GalleryController::class, 'reorder']);
        Route::post('/galeria/{id}/hero', [GalleryController::class, 'toggleHero'])->whereNumber('id');
        Route::delete('/galeria/{id}', [GalleryController::class, 'destroy'])->whereNumber('id');

        // Reseñas
        Route::get('/resenas', [ReviewController::class, 'index']);
        Route::patch('/resenas/{id}/visibilidad', [ReviewController::class, 'toggleVisibility'])->whereNumber('id');
        Route::put('/resenas/{id}/respuesta', [ReviewController::class, 'reply'])->whereNumber('id');
        Route::delete('/resenas/{id}/respuesta', [ReviewController::class, 'destroyReply'])->whereNumber('id');

        // Inventario
        Route::get('/inventario', [InventoryController::class, 'index']);
        Route::get('/inventario/{id}', [InventoryController::class, 'product'])->whereNumber('id');
        Route::post('/inventario/{id}/ajuste', [InventoryController::class, 'adjust'])->whereNumber('id');

        // Mensajes (contacto, Libro de Reclamaciones, suscripciones)
        Route::get('/mensajes', [MessageController::class, 'index']);
        Route::get('/mensajes/{id}', [MessageController::class, 'show'])->whereNumber('id');
        Route::patch('/mensajes/{id}/no-leido', [MessageController::class, 'markUnread'])->whereNumber('id');

        // Zonas de envío
        Route::apiResource('/envios', ShippingRateController::class)->parameters(['envios' => 'id'])->except('show');

        // Pasarela de pago
        Route::get('/pasarela', [PaymentGatewayController::class, 'show']);
        Route::put('/pasarela', [PaymentGatewayController::class, 'update']);

        // Marketing (Meta y Google)
        Route::get('/marketing', [MarketingController::class, 'overview']);
        Route::get('/marketing/meta', [MarketingController::class, 'showMeta']);
        Route::put('/marketing/meta', [MarketingController::class, 'updateMeta']);
        Route::post('/marketing/meta/evento-prueba', [MarketingController::class, 'testMetaEvent'])->middleware('throttle:10,1');
        Route::get('/marketing/google', [MarketingController::class, 'showGoogle']);
        Route::put('/marketing/google', [MarketingController::class, 'updateGoogle']);
        Route::post('/marketing/google/publicar', [MarketingController::class, 'publishGoogle']);

        // Mi cuenta
        Route::get('/cuenta', [AccountController::class, 'show']);
        Route::put('/cuenta/contrasena', [AccountController::class, 'updatePassword']);
        Route::put('/cuenta/correo', [AccountController::class, 'updateEmail'])->middleware('throttle:6,1');
    });
});
