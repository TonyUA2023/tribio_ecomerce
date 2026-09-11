<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\StoreController;

// Autenticación pública
Route::post('/login', [AuthController::class, 'login']);

// Webhook Mercado Pago
Route::post('/mercadopago/webhook/{store}', [\App\Http\Controllers\StoreController::class, 'mercadopagoWebhook'])->name('api.mercadopago.webhook');

// Rutas protegidas
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/user', function (Request $request) {
        return response()->json([
            'user' => $request->user(),
            'store' => $request->user()->store
        ]);
    });

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
