<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Models\User;
use App\Services\Billing\MercadoPagoSubscriptions;
use App\Support\BusinessProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class SubscriptionController extends Controller
{
    public function __construct(protected MercadoPagoSubscriptions $billing)
    {
    }

    /**
     * Step 1 (pricing modal on the home page): register the account + draft store
     * for the chosen plan, then send the owner to the cart/payment page. No charge
     * happens here — Mercado Pago is only contacted once the owner confirms on `pay()`.
     */
    public function checkout(Request $request)
    {
        if (!$request->has('phone') && $request->has('whatsapp_phone')) {
            $request->merge(['phone' => $request->whatsapp_phone]);
        }

        $planKey = $request->input('plan_key');

        // Tribio Pass unification: someone already logged in — a buyer with no store
        // yet, or a store owner opening an additional store under the same account
        // (Tribio Pass now supports owning several, see the multi-store ADR) — reuses
        // their existing account instead of registering a disconnected new identity.
        $existingUser = Auth::check() && !Auth::user()->isSuperAdmin()
            ? Auth::user()
            : null;

        $rules = [
            'plan_key'       => 'required|string|in:basic,professional,enterprise',
            'store_name'     => 'required|string|max:255',
            'store_category' => ['required', 'string', Rule::in(BusinessProfile::keys())],
            'store_slug'     => 'required|string|alpha_dash|max:60|unique:stores,slug',
            'whatsapp_phone' => 'nullable|string|max:20',
        ];

        $messages = [
            'store_slug.unique' => 'Esta dirección de tienda ya está en uso.',
            'store_category.in' => 'Elige el rubro de tu negocio de la lista.',
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

            // Rubro defaults first (e.g. Textilería starts with "Ventas por encargo" on).
            return Store::create(BusinessProfile::for($request->store_category)->creationDefaults() + [
                'user_id'        => $user->id,
                'name'           => $request->store_name,
                'slug'           => $request->store_slug,
                'category'       => $request->store_category,
                'template_name'  => config('storefront.default_template', 'soft-market'),
                'whatsapp_phone' => $request->whatsapp_phone,
                'accent_color'   => '#0284c7',
                'status'         => 'draft', // draft hasta que Mercado Pago confirme la suscripción
                'plan'           => $planKey,
                'plan_expires_at'=> null,
            ]);
        });

        Auth::login($store->user, true);

        return redirect()->route('plan.pay', $store);
    }

    /**
     * The "cart": order summary for the chosen plan. Nothing is charged until the owner
     * authorizes the monthly subscription on Mercado Pago's page (see subscribe()).
     */
    public function pay(Store $store)
    {
        abort_unless(Auth::check() && Auth::id() === $store->user_id, 403);

        if ($store->status === 'active' && !$store->hasPlanExpired()) {
            return redirect()->route('dashboard.index')
                ->with('success', 'Tu tienda ya está activa. ¡Bienvenido de vuelta!');
        }

        $planConfig = config("tribio.plans.{$store->plan}");
        abort_if(!$planConfig, 404);

        return view('public.plan-pay', [
            'store'        => $store,
            'plan'         => $planConfig,
            'billingReady' => $this->billing->isConfigured(),
        ]);
    }

    /** Creates the Mercado Pago subscription and sends the owner to authorize it. */
    public function subscribe(Store $store)
    {
        abort_unless(Auth::check() && Auth::id() === $store->user_id, 403);

        $planConfig = config("tribio.plans.{$store->plan}");
        abort_if(!$planConfig, 404);

        if (!$this->billing->isConfigured()) {
            return redirect()->route('plan.pay', $store)->withErrors(['billing' => 'Los pagos todavía no están activos. Intenta más tarde.']);
        }

        try {
            $preapproval = $this->billing->create($store, $planConfig, $store->user->email, route('plan.return', $store));
        } catch (\Throwable $e) {
            Log::error('Mercado Pago: fallo al crear la suscripción del plan', ['store_id' => $store->id, 'message' => $e->getMessage()]);

            return redirect()->route('plan.pay', $store)->withErrors(['billing' => $e->getMessage()]);
        }

        $store->update(['billing_preapproval_id' => $preapproval['id']]);

        return redirect()->away($preapproval['init_point']);
    }

    /**
     * Where Mercado Pago sends the owner back. The query string is never trusted: the
     * subscription is re-fetched from Mercado Pago and the store synced from that.
     */
    public function return(Store $store)
    {
        abort_unless(Auth::check() && Auth::id() === $store->user_id, 403);

        $preapproval = $store->billing_preapproval_id ? $this->billing->get($store->billing_preapproval_id) : null;
        if ($preapproval) {
            $this->billing->sync($store, $preapproval);
        }

        if ($store->fresh()->status === 'active') {
            Auth::user()->switchToStore($store);

            return redirect()->route('dashboard.index')
                ->with('success', '¡Suscripción activada! Bienvenido al Plan ' . ucfirst(config("tribio.plans.{$store->plan}.label")) . '.');
        }

        return redirect()->route('plan.pay', $store)
            ->withErrors(['billing' => 'Aún no recibimos la autorización de Mercado Pago. Si ya la completaste, espera un minuto y recarga; si no, inténtalo de nuevo.']);
    }

    /**
     * Public Mercado Pago webhook (topics `subscription_preapproval` and
     * `subscription_authorized_payment`). The payload is only a signal: the subscription
     * is re-fetched from Mercado Pago and the store synced from that response.
     */
    public function webhook(Request $request)
    {
        $topic = $request->input('type', $request->input('topic'));
        $id = $request->input('data.id', $request->input('id'));
        Log::info('Mercado Pago (planes) webhook recibido', $request->all());

        if (!$id || !in_array($topic, ['subscription_preapproval', 'subscription_authorized_payment'], true)) {
            return response()->json(['status' => 'ignored'], 200);
        }

        $preapprovalId = (string) $id;
        if ($topic === 'subscription_authorized_payment') {
            $payment = $this->billing->authorizedPayment($preapprovalId);
            $preapprovalId = (string) ($payment['preapproval_id'] ?? '');
        }

        $store = $preapprovalId !== '' ? Store::where('billing_preapproval_id', $preapprovalId)->first() : null;
        if (!$store) {
            Log::warning('Mercado Pago (planes) webhook: no se encontró tienda.', ['id' => $id, 'topic' => $topic]);
            return response()->json(['status' => 'ok'], 200);
        }

        if ($preapproval = $this->billing->get($preapprovalId)) {
            $this->billing->sync($store, $preapproval);
        }

        return response()->json(['status' => 'ok'], 200);
    }
}
