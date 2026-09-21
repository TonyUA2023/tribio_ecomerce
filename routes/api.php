<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerAuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\StoreController;

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

    Route::get('/user', function (Request $request) {
        return response()->json([
            'user' => $request->user(),
            'store' => $request->user()->currentStore()
        ]);
    });

    // Tribio Pass — historial de compras del comprador autenticado
    Route::get('/customer/orders', [CustomerAuthController::class, 'orders']);

    // Dashboard
    Route::get('/dashboard/stats', [DashboardController::class, 'index']);

    // Tienda / Store settings
    Route::get('/dashboard/tienda', [StoreController::class, 'show']);
    Route::put('/dashboard/tienda', [StoreController::class, 'update']);

    // Pedidos / Orders
    Route::get('/dashboard/pedidos', [OrderController::class, 'index']);
    Route::get('/dashboard/pedidos/{id}', [OrderController::class, 'show']);
    Route::patch('/dashboard/pedidos/{id}/status', [OrderController::class, 'updateStatus']);

    // Productos / Products
    Route::post('/dashboard/productos/{id}/historial-estado', [ProductController::class, 'addStateHistory']);
    Route::apiResource('/dashboard/productos', ProductController::class)->parameters([
        'productos' => 'id'
    ]);
});
