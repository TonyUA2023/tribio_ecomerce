<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentGatewayController extends Controller
{
    private function getStore()
    {
        return Auth::user()->currentStore();
    }

    public function edit()
    {
        $store = $this->getStore();
        return view('dashboard.gateway.edit', compact('store'));
    }

    public function update(Request $request)
    {
        $store = $this->getStore();

        $request->validate([
            'flow_enabled' => 'nullable|boolean',
            'flow_api_key' => 'nullable|string|max:255',
            'flow_secret_key' => 'nullable|string|max:255',
            'flow_mode' => 'nullable|in:sandbox,live',
            'flow_currency' => 'nullable|in:PEN,USD,CLP,MXN',
            'checkout_mode'  => 'required|string|in:whatsapp,card,mixed',
            'payment_gateway'=> 'nullable|string|in:mercado_pago,paypal,flow',
            'gateway_public_key' => 'nullable|string|max:255',
            'gateway_private_key'=> 'nullable|string|max:255',
            'gateway_access_token'=> 'nullable|string',
            'mp_access_token'             => 'nullable|string|max:255',
            'mp_public_key'               => 'nullable|string|max:255',
            'paypal_client_id'            => 'nullable|string|max:255',
            'paypal_client_secret'        => 'nullable|string|max:255',
            'paypal_mode'                 => 'nullable|string|in:sandbox,live',
            'paypal_webhook_id'           => 'nullable|string|max:255',
        ]);

        $data = $request->only([
            'flow_mode', 'flow_currency',
            'checkout_mode', 'payment_gateway', 'gateway_public_key', 'gateway_private_key', 'gateway_access_token',
            'mp_access_token', 'mp_public_key',
            'paypal_client_id', 'paypal_client_secret', 'paypal_mode', 'paypal_webhook_id',
        ]);
        $data['paypal_mode'] = in_array($data['paypal_mode'] ?? null, ['sandbox', 'live'], true) ? $data['paypal_mode'] : 'sandbox';

        if (($data['payment_gateway'] ?? '') === '') {
            $data['payment_gateway'] = null;
        }

        if ($request->filled('mp_access_token')) {
            $data['gateway_access_token'] = trim($request->input('mp_access_token'));
            $data['mp_access_token'] = trim($request->input('mp_access_token'));
        }
        if ($request->filled('mp_public_key')) {
            $data['gateway_public_key'] = trim($request->input('mp_public_key'));
            $data['mp_public_key'] = trim($request->input('mp_public_key'));
        }
        if ($request->filled('gateway_access_token') && empty($data['mp_access_token'])) {
            $data['mp_access_token'] = trim($request->input('gateway_access_token'));
        }
        if ($request->filled('gateway_public_key') && empty($data['mp_public_key'])) {
            $data['mp_public_key'] = trim($request->input('gateway_public_key'));
        }

        // Blank credentials preserve stored values; secrets never return in the form.
        foreach (['flow_api_key', 'flow_secret_key'] as $field) {
            if ($request->filled($field)) {
                $data[$field] = trim($request->input($field));
            }
        }
        // flow_enabled ya no es un checkbox propio en el formulario: se deriva de
        // cuál pasarela quedó seleccionada (payment_gateway, ya normalizada arriba
        // a null cuando viene vacía — por eso NO se usa "?? $store->payment_gateway"
        // aquí: haría que limpiar la selección "resucite" el valor guardado viejo).
        // Mercado Pago y PayPal se activan igual, implícitamente, al ser la elegida.
        $data['flow_enabled'] = $data['payment_gateway'] === 'flow';
        if ($data['flow_enabled'] &&
            (empty($data['flow_api_key'] ?? $store->flow_api_key) || empty($data['flow_secret_key'] ?? $store->flow_secret_key))) {
            throw \Illuminate\Validation\ValidationException::withMessages(['payment_gateway' => 'Seleccionaste Flow: ingresa tu API Key y Secret Key para activarlo.']);
        }
        $store->update($data);

        return back()->with('success', 'Configuración de pasarela de pago actualizada correctamente.');
    }
}
