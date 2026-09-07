<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class SubscriptionController extends Controller
{
    public function checkout(Request $request)
    {
        if (!$request->has('phone') && $request->has('whatsapp_phone')) {
            $request->merge(['phone' => $request->whatsapp_phone]);
        }

        $request->validate([
            'plan_key'       => 'required|string|in:basic,professional,enterprise',
            'name'           => 'required|string|max:255',
            'email'          => 'required|email|unique:users,email',
            'password'       => 'required|min:8|confirmed',
            'phone'          => 'required|string|max:20',
            'store_name'     => 'required|string|max:255',
            'store_category' => 'required|string',
            'store_slug'     => 'required|string|alpha_dash|unique:stores,slug|max:60',
            'whatsapp_phone' => 'nullable|string|max:20',
        ], [
            'store_slug.unique' => 'Esta dirección de tienda ya está en uso.',
            'email.unique'      => 'Este correo electrónico ya está registrado.',
            'password.confirmed'=> 'La confirmación de la contraseña no coincide.',
        ]);

        $planKey = $request->plan_key;
        $planConfig = config("tribio.plans.{$planKey}");
        
        if (!$planConfig) {
            return back()->withErrors(['plan_key' => 'El plan seleccionado no es válido.']);
        }

        $planPrice = (float) $planConfig['price'];
        $planName = $planConfig['label'];

        // Usamos una transacción de base de datos
        return DB::transaction(function () use ($request, $planKey, $planPrice, $planName) {
            // 1. Crear el usuario como dueño de tienda
            $user = User::create([
                'name'     => $request->name,
                'email'    => $request->email,
                'password' => Hash::make($request->password),
                'role'     => 'store_owner',
                'phone'    => $request->phone,
            ]);

            // 2. Crear la tienda en estado "draft" (inactiva hasta que pague)
            $store = Store::create([
                'user_id'        => $user->id,
                'name'           => $request->store_name,
                'slug'           => $request->store_slug,
                'category'       => $request->store_category,
                'template_name'  => 'minimal-light', // Plantilla inicial por defecto
                'whatsapp_phone' => $request->whatsapp_phone,
                'accent_color'   => '#0284c7', // Celeste por defecto
                'status'         => 'draft', // Draft hasta que el pago se confirme
                'plan'           => $planKey,
                'plan_expires_at'=> null, // Se establece al confirmar el pago
            ]);

            // 3. Crear Preferencia de Mercado Pago
            $accessToken = config('services.mercadopago.access_token');
            $notificationUrl = route('plan.webhook');

            // Mercado Pago exige HTTPS para la URL de notificación.
            // Si la URL local es HTTP, Mercado Pago podría rechazar la preferencia.
            // Evitamos enviar localhost o 127.0.0.1 a menos que sea HTTPS (por ejemplo, con ngrok)
            if (str_starts_with($notificationUrl, 'http://')) {
                if (str_contains($notificationUrl, 'localhost') || str_contains($notificationUrl, '127.0.0.1')) {
                    $notificationUrl = null;
                } else {
                    $notificationUrl = str_replace('http://', 'https://', $notificationUrl);
                }
            }

            try {
                $preferenceData = [
                    'items' => [
                        [
                            'title'       => "Suscripción Tribio - Plan {$planName}",
                            'quantity'    => 1,
                            'unit_price'  => $planPrice,
                            'currency_id' => 'PEN',
                        ]
                    ],
                    'back_urls' => [
                        'success' => route('plan.callback', ['status' => 'success', 'store_id' => $store->id]),
                        'failure' => route('plan.callback', ['status' => 'failure', 'store_id' => $store->id]),
                        'pending' => route('plan.callback', ['status' => 'pending', 'store_id' => $store->id]),
                    ],
                    'auto_return'        => 'approved',
                    'external_reference' => (string) $store->id,
                ];

                if ($notificationUrl) {
                    $preferenceData['notification_url'] = $notificationUrl;
                }

                $response = Http::withHeaders([
                    'Authorization' => "Bearer {$accessToken}",
                    'Content-Type'  => 'application/json',
                ])->post('https://api.mercadopago.com/v1/preferences', $preferenceData);

                if ($response->successful()) {
                    $preference = $response->json();
                    
                    // Redirigir al flujo de pago de Mercado Pago (preferimos sandbox_init_point en local/test o init_point)
                    $checkoutUrl = app()->environment('local') 
                        ? ($preference['sandbox_init_point'] ?? $preference['init_point'])
                        : $preference['init_point'];

                    return redirect()->away($checkoutUrl);
                }

                Log::error('Mercado Pago Preference API error', [
                    'status' => $response->status(),
                    'body'   => $response->body()
                ]);

                throw new \Exception('No se pudo crear la preferencia de pago en Mercado Pago.');

            } catch (\Exception $e) {
                Log::error('Mercado Pago checkout exception', [
                    'message' => $e->getMessage()
                ]);

                // Rollback manual de la transacción al lanzar la excepción
                throw $e;
            }
        });
    }

    public function callback(Request $request)
    {
        $status = $request->input('status');
        $storeId = $request->input('store_id');

        $store = Store::findOrFail($storeId);
        $user = $store->user;

        // Si la tienda ya está activa (por ejemplo, el webhook de Mercado Pago ya procesó el pago)
        if ($store->status === 'active') {
            Auth::login($user);
            return redirect()->route('dashboard.index')
                ->with('success', "¡Excelente! Tu suscripción al Plan " . ucfirst($store->plan) . " está activa. ¡Bienvenido de vuelta!");
        }

        if ($status === 'success' || $request->input('collection_status') === 'approved') {
            // El pago fue aprobado
            $store->update([
                'status'          => 'active',
                'plan_expires_at' => now()->addDays(30), // Suscripción mensual de 30 días
            ]);

            // Iniciar sesión del usuario automáticamente
            Auth::login($user);

            return redirect()->route('dashboard.index')
                ->with('success', "¡Excelente! Tu pago ha sido procesado con éxito. Bienvenido al Plan " . ucfirst($store->plan) . ".");
        }

        // Si falló o fue cancelado, eliminamos la tienda y usuario "draft" temporales para mantener limpia la BD
        $store->forceDelete();
        $user->forceDelete();

        return redirect()->route('home')
            ->with('error', 'El pago fue cancelado o rechazado. Por favor, intenta registrarte nuevamente.');
    }

    public function webhook(Request $request)
    {
        Log::info('Mercado Pago Webhook payload recibido:', $request->all());

        // El webhook puede venir con 'type' => 'payment' y 'data.id'
        // O como IPN con 'topic' => 'payment' e 'id'
        $paymentId = null;

        if ($request->input('type') === 'payment') {
            $paymentId = $request->input('data.id') ?? $request->input('data_id');
        } elseif ($request->input('topic') === 'payment') {
            $paymentId = $request->input('id');
        }

        // Si no se encuentra de esas formas, verificamos si 'id' viene en la raíz para Webhooks heredados
        if (!$paymentId && $request->has('id') && $request->input('type') === null) {
            $paymentId = $request->input('id');
        }

        if (!$paymentId) {
            Log::warning('Mercado Pago Webhook: No se encontró payment ID en el payload.');
            return response()->json(['message' => 'No payment ID found'], 200);
        }

        $accessToken = config('services.mercadopago.access_token');

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
            ])->get("https://api.mercadopago.com/v1/payments/{$paymentId}");

            if ($response->successful()) {
                $payment = $response->json();
                $storeId = $payment['external_reference'] ?? null;
                $status = $payment['status'] ?? '';

                Log::info("Mercado Pago Webhook pago consultado:", [
                    'payment_id' => $paymentId,
                    'store_id'   => $storeId,
                    'status'     => $status
                ]);

                if ($storeId && $status === 'approved') {
                    $store = Store::find($storeId);
                    if ($store && $store->status === 'draft') {
                        $store->update([
                            'status'          => 'active',
                            'plan_expires_at' => now()->addDays(30),
                        ]);
                        Log::info("Tienda {$storeId} activada con éxito a través del webhook (Pago: {$paymentId}).");
                    }
                }
            } else {
                Log::error('Error al consultar detalles de pago en Mercado Pago', [
                    'payment_id' => $paymentId,
                    'status'     => $response->status(),
                    'body'       => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Excepción al procesar webhook de Mercado Pago', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
        }

        // Mercado Pago requiere una respuesta 200 OK para confirmar recepción
        return response()->json(['status' => 'ok'], 200);
    }
}
