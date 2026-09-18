<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Models\User;
use App\Services\CulqiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    public function __construct(protected CulqiService $culqi)
    {
    }

    /**
     * Step 1 (pricing modal on the home page): register the account + draft store
     * for the chosen plan, then send the owner to the cart/payment page. No charge
     * happens here — Culqi only sees a card once the owner confirms on `pay()`.
     */
    public function checkout(Request $request)
    {
        if (!$request->has('phone') && $request->has('whatsapp_phone')) {
            $request->merge(['phone' => $request->whatsapp_phone]);
        }

        $planKey = $request->input('plan_key');

        // Tribio Pass unification: someone already logged in (e.g. a customer with
        // purchase history in other stores) upgrades their own account instead of
        // being forced to register a disconnected second identity.
        $existingUser = Auth::check() && !Auth::user()->isSuperAdmin() && !Auth::user()->hasStore()
            ? Auth::user()
            : null;

        $rules = [
            'plan_key'       => 'required|string|in:basic,professional,enterprise',
            'store_name'     => 'required|string|max:255',
            'store_category' => 'required|string',
            'store_slug'     => 'required|string|alpha_dash|max:60|unique:stores,slug',
            'whatsapp_phone' => 'nullable|string|max:20',
        ];

        $messages = [
            'store_slug.unique' => 'Esta dirección de tienda ya está en uso.',
            'email.unique'      => 'Este correo electrónico ya está registrado. Inicia sesión con tu Tribio Pass para continuar.',
            'password.confirmed'=> 'La confirmación de la contraseña no coincide.',
        ];

        if ($existingUser) {
            $request->validate($rules, $messages);
        } else {
            $request->validate(array_merge($rules, [
                'name'     => 'required|string|max:255',
                'email'    => 'required|email|unique:users,email',
                'password' => 'required|min:8|confirmed',
                'phone'    => 'required|string|max:20',
            ]), $messages);
        }

        if (!config("tribio.plans.{$planKey}")) {
            return back()->withErrors(['plan_key' => 'El plan seleccionado no es válido.']);
        }

        $store = DB::transaction(function () use ($request, $planKey, $existingUser) {
            $user = $existingUser ?? User::create([
                'name'     => $request->name,
                'email'    => $request->email,
                'password' => Hash::make($request->password),
                'role'     => User::ROLE_STORE_OWNER,
                'phone'    => $request->phone,
            ]);

            return Store::create([
                'user_id'        => $user->id,
                'name'           => $request->store_name,
                'slug'           => $request->store_slug,
                'category'       => $request->store_category,
                'template_name'  => 'minimal-light',
                'whatsapp_phone' => $request->whatsapp_phone,
                'accent_color'   => '#0284c7',
                'status'         => 'draft', // draft hasta que Culqi confirme el pago
                'plan'           => $planKey,
                'plan_expires_at'=> null,
            ]);
        });

        Auth::login($store->user, true);

        return redirect()->route('plan.pay', $store);
    }

    /**
     * The "cart": order summary for the chosen plan + embedded Culqi card form.
     * Nothing is charged until the owner submits the card via charge().
     */
    public function pay(Store $store)
    {
        abort_unless(Auth::check() && Auth::id() === $store->user_id, 403);

        if ($store->status === 'active') {
            return redirect()->route('dashboard.index')
                ->with('success', 'Tu tienda ya está activa. ¡Bienvenido de vuelta!');
        }

        $planConfig = config("tribio.plans.{$store->plan}");
        abort_if(!$planConfig, 404);

        if (!$this->culqi->isConfigured()) {
            return view('public.plan-pay', [
                'store'      => $store,
                'plan'       => $planConfig,
                'culqiReady' => false,
            ]);
        }

        return view('public.plan-pay', [
            'store'         => $store,
            'plan'          => $planConfig,
            'culqiReady'    => true,
            'culqiPublicKey'=> $this->culqi->publicKey(),
        ]);
    }

    /**
     * Receives the Culqi card token from the cart page and turns it into a live
     * recurring subscription: customer -> card -> plan (cached) -> subscription.
     */
    public function charge(Request $request, Store $store)
    {
        abort_unless(Auth::check() && Auth::id() === $store->user_id, 403);

        if ($store->status === 'active') {
            return response()->json(['success' => true, 'redirect' => route('dashboard.index')]);
        }

        $request->validate([
            'culqi_token'  => 'required|string',
            'address'      => 'required|string|max:100|min:5',
            'address_city' => 'required|string|max:30|min:2',
        ]);

        $planConfig = config("tribio.plans.{$store->plan}");
        abort_if(!$planConfig, 404);

        $user = $store->user;
        [$firstName, $lastName] = $this->splitName($user->name);

        try {
            $customer = $this->culqi->createCustomer([
                'address'      => $request->address,
                'address_city' => $request->address_city,
                'country_code' => 'PE',
                'email'        => $user->email,
                'first_name'   => $firstName,
                'last_name'    => $lastName,
                'phone_number' => preg_replace('/[^0-9]/', '', $user->phone ?? '999999999') ?: '999999999',
            ]);

            $card = $this->culqi->createCard($customer['id'], $request->culqi_token);
            $plan = $this->culqi->getOrCreatePlan($store->plan);
            $subscription = $this->culqi->createSubscription($card['id'], $plan['id']);

            $store->update([
                'status'                 => 'active',
                'plan_expires_at'        => now()->addMonth(),
                'culqi_customer_id'      => $customer['id'],
                'culqi_card_id'          => $card['id'],
                'culqi_subscription_id'  => $subscription['id'],
            ]);

            Log::info('Culqi: suscripción creada', [
                'store_id'        => $store->id,
                'subscription_id' => $subscription['id'],
                'plan'            => $store->plan,
            ]);

            return response()->json([
                'success'  => true,
                'redirect' => route('dashboard.index'),
                'message'  => "¡Pago procesado con éxito! Bienvenido al Plan " . ucfirst($planConfig['label']) . ".",
            ]);
        } catch (\Throwable $e) {
            Log::error('Culqi: fallo al procesar la suscripción', [
                'store_id' => $store->id,
                'message'  => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'No se pudo procesar el pago. Verifica los datos de tu tarjeta e intenta nuevamente.',
            ], 422);
        }
    }

    /**
     * Public Culqi webhook (configured in CulqiPanel > Eventos > Webhooks). We never trust
     * the payload's amounts/state directly — only use it as a signal to re-fetch the
     * subscription from Culqi's API and sync from that authoritative response, the same
     * defensive pattern already used for the Mercado Pago storefront webhook.
     */
    public function webhook(Request $request)
    {
        $payload = $request->all();
        Log::info('Culqi Webhook recibido:', $payload);

        $type = $payload['type'] ?? null;
        $data = $payload['data'] ?? [];
        $subscriptionId = $data['subscription_id'] ?? $data['id'] ?? null;

        if (!$subscriptionId || !str_starts_with((string) $type, 'subscription.')) {
            return response()->json(['status' => 'ignored'], 200);
        }

        $store = Store::where('culqi_subscription_id', $subscriptionId)->first();
        if (!$store) {
            Log::warning('Culqi Webhook: no se encontró tienda para la suscripción.', ['subscription_id' => $subscriptionId]);
            return response()->json(['status' => 'ok'], 200);
        }

        $subscription = $this->culqi->getSubscription($subscriptionId);
        if (!$subscription) {
            return response()->json(['status' => 'ok'], 200);
        }

        match (true) {
            $type === 'subscription.charge.succeeded' => $store->update([
                'status'          => 'active',
                'plan_expires_at' => now()->addMonth(),
            ]),
            $type === 'subscription.cancel.succeeded' || ($subscription['state'] ?? null) === 'canceled' => $store->update([
                'status' => 'cancelled',
            ]),
            default => null,
        };

        return response()->json(['status' => 'ok'], 200);
    }

    /**
     * Splits a single "name" field into Culqi's required first/last name shape
     * (letters and spaces only, at least 2 characters each).
     */
    private function splitName(string $fullName): array
    {
        $clean = trim(preg_replace('/[^\p{L}\s]/u', '', $fullName));
        $parts = preg_split('/\s+/', $clean, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($parts) < 2) {
            return [$parts[0] ?? 'Cliente', 'Tribio'];
        }

        $first = array_shift($parts);
        $last = implode(' ', $parts);

        return [
            strlen($first) < 2 ? str_pad($first, 2, $first) : $first,
            strlen($last) < 2 ? str_pad($last, 2, $last) : $last,
        ];
    }
}
