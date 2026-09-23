<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ProductReview;
use App\Models\User;
use App\Services\Reviews\ProductReviewService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Tribio Pass buyer identity: OTP registration and purchase history, shared by
 * the web session-based flow (CustomerPortalController) and the mobile
 * token-based flow (Api\CustomerAuthController) so both stay in sync instead
 * of duplicating the same rules twice.
 */
class CustomerIdentityService
{
    public function sendRegistrationOtp(string $name, string $email): void
    {
        $otp = sprintf('%06d', mt_rand(100000, 999999));

        Cache::put('otp_' . $email, $otp, now()->addMinutes(15));

        $brevoApiKey = config('services.brevo.api_key');

        if ($brevoApiKey) {
            try {
                Http::withHeaders([
                    'accept' => 'application/json',
                    'api-key' => $brevoApiKey,
                    'content-type' => 'application/json',
                ])->post('https://api.brevo.com/v3/smtp/email', [
                    'sender' => [
                        'name' => 'Tribio Pass',
                        'email' => config('mail.from.address', 'noreply@tribio.pe'),
                    ],
                    'to' => [
                        [
                            'email' => $email,
                            'name' => $name,
                        ],
                    ],
                    'subject' => 'Tu código de verificación de Tribio Pass',
                    'htmlContent' => '<html><body><h1>Verificación de Correo</h1><p>Hola ' . htmlspecialchars($name) . ',</p><p>Tu código de verificación de 6 dígitos es: <strong>' . $otp . '</strong></p><p>Este código expirará en 15 minutos.</p></body></html>',
                ]);
            } catch (\Throwable $e) {
                Log::error('Error sending Brevo OTP: ' . $e->getMessage());
            }
        }
    }

    /**
     * Verifies the cached OTP for $email. When no Brevo key is configured,
     * '000000' is accepted as a dev backdoor (matches the pre-extraction behavior).
     */
    public function verifyRegistrationOtp(string $email, string $token): bool
    {
        $cachedOtp = Cache::get('otp_' . $email);
        $backdoorAllowed = empty(config('services.brevo.api_key'));

        if ($cachedOtp !== $token && !($backdoorAllowed && $token === '000000')) {
            return false;
        }

        Cache::forget('otp_' . $email);

        return true;
    }

    /**
     * Creates the Tribio Pass buyer account. Also reassociates any prior guest
     * orders placed with this email (Order::user_id was null) to the new account.
     */
    public function registerCustomer(array $data): User
    {
        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'phone'    => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'role'     => User::ROLE_CLIENTE,
        ]);

        if (!empty($data['address'])) {
            $user->customerAddresses()->create([
                'type'       => $data['type'] ?? 'casa',
                'title'      => ucfirst($data['type'] ?? 'casa'),
                'address'    => $data['address'],
                'city'       => $data['city'] ?? null,
                'state'      => $data['state'] ?? null,
                'country'    => $data['country'] ?? 'PE',
                'zipcode'    => $data['zipcode'] ?? null,
                'is_default' => true,
            ]);
        }

        Order::whereNull('user_id')
            ->where('customer_email', $user->email)
            ->update(['user_id' => $user->id]);

        return $user;
    }

    /**
     * Finds the Tribio Pass account for a Google sign-in, auto-linking by email when
     * an account with that (Google-verified) email already exists — whether buyer or
     * store owner — so the same person never ends up with two disconnected accounts.
     * Creates a fresh `cliente` account (matching the OTP registration default) only
     * when neither the Google id nor the email match anything on file. The random
     * password keeps `users.password` (not nullable) satisfied; the account can still
     * set a real one later via the normal password-reset flow.
     */
    public function findOrCreateFromGoogle(string $googleId, string $email, string $name, ?string $avatar): User
    {
        if ($user = User::where('google_id', $googleId)->first()) {
            return $user;
        }

        if ($user = User::where('email', $email)->first()) {
            $user->update(['google_id' => $googleId]);
            return $user;
        }

        $user = User::create([
            'name'      => $name,
            'email'     => $email,
            'google_id' => $googleId,
            'password'  => Hash::make(Str::random(40)),
            'role'      => User::ROLE_CLIENTE,
            'avatar'    => $avatar,
        ]);

        // email_verified_at isn't mass-assignable (by design, elsewhere in the app) —
        // force it here specifically, since Google's own "email" scope already verified it.
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    /**
     * Links an ALREADY-AUTHENTICATED account to a Google identity (dashboard
     * "Conectar con Google" — distinct from findOrCreateFromGoogle(), which is the
     * guest door). Also wipes the password to the same random-unusable-hash
     * mechanism a fresh Google signup gets, so password login stops working for
     * this account from this point on, by explicit design — the account can only
     * regain a real password via the normal "cambiar contraseña" flow.
     */
    public function linkGoogleToUser(User $user, string $googleId): void
    {
        $user->update([
            'google_id' => $googleId,
            'password'  => Hash::make(Str::random(40)),
        ]);
    }

    /**
     * Purchase history for the Tribio Pass hub / mobile "Mis Compras": orders
     * matched by user_id or customer_email, across every store.
     */
    public function ordersFor(User $user): Collection
    {
        // What the buyer already reviewed and which stores are theirs, loaded once so that
        // flagging every order line as "can be rated" costs two queries, not two per line.
        $reviewService = app(ProductReviewService::class);
        $reviewsReady = $reviewService->isAvailable();
        $reviews = $reviewsReady ? ProductReview::where('user_id', $user->id)->get()->keyBy('product_id') : collect();
        $ownStoreIds = $user->stores()->pluck('id')->map(fn ($id) => (int) $id)->all();

        return Order::boughtBy($user)
            ->with(['items.product:id,slug,store_id', 'store:id,name,slug,logo_path'])
            ->latest()
            ->get()
            ->map(function ($order) use ($reviews, $ownStoreIds, $reviewService, $reviewsReady) {
                return [
                    'id'               => $order->id,
                    'order_number'     => $order->order_number,
                    'store_name'       => $order->store?->name ?? 'Tienda Tribio',
                    'store_slug'       => $order->store?->slug,
                    'total'            => (float) $order->total,
                    'currency'         => $order->currency ?? 'PEN',
                    'currency_symbol'  => $order->currency === 'USD' ? '$' : 'S/',
                    'status'           => $order->status,
                    'status_label'     => $order->status_label,
                    'status_color'     => $order->status_color,
                    'status_step'      => $this->resolveStatusStep($order->status),
                    'created_at'       => $order->created_at ? $order->created_at->format('d/m/Y H:i') : '',
                    'created_diff'     => $order->created_at ? $order->created_at->diffForHumans() : '',
                    'items_count'      => $order->items->sum('quantity'),
                    'items'            => $order->items->map(function ($i) use ($order, $reviews, $ownStoreIds, $reviewService, $reviewsReady) {
                        $product = $i->product; // null once the owner deleted the product
                        $review = $i->product_id ? $reviews->get($i->product_id) : null;

                        return [
                            'name'         => $i->product_name,
                            'price'        => (float) $i->price,
                            'quantity'     => $i->quantity,
                            'subtotal'     => (float) $i->subtotal,
                            // Reviews ("Calificar mi compra"): same rule as ProductReviewService —
                            // delivered order, product still on sale, not your own store, not rated yet.
                            'product_id'   => $i->product_id,
                            'product_url'  => $product && $order->store
                                ? route('store.product', ['slug' => $order->store->slug, 'product' => $product->slug])
                                : null,
                            'review'       => $review ? $reviewService->transform($review) : null,
                            'can_review'   => $reviewsReady
                                && $order->status === 'delivered'
                                && $product !== null
                                && $review === null
                                && !in_array((int) $product->store_id, $ownStoreIds, true),
                        ];
                    }),
                    'shipping_address' => $order->customer_address ? "{$order->customer_address}, {$order->customer_city} ({$order->customer_state})" : '',
                    // Additive (made-to-order): workshop stage and what is still owed.
                    'payment_status'         => $order->payment_status,
                    'amount_paid'            => (float) $order->amount_paid,
                    'balance_due'            => (float) $order->balance_due,
                    'production_stage'       => $order->production_stage,
                    'production_stage_label' => $order->production_stage ? $order->production_stage_label : null,
                    'estimated_ready_at'     => $order->estimated_ready_at?->format('d/m/Y'),
                    'balance_url'            => $order->isMadeToOrder() && (float) $order->balance_due > 0 && $order->store
                        ? $order->balancePaymentUrl() : null,
                ];
            });
    }

    /**
     * Maps order status to progress step index (1-5). Public: also used by
     * CustomerPortalController::trackOrder(), the one caller outside this service.
     */
    public function resolveStatusStep(?string $status): int
    {
        return match ($status) {
            'pending'    => 1, // 1. Pedido Recibido
            'confirmed'  => 2, // 2. Confirmado
            'processing' => 3, // 3. En Preparación
            'shipped'    => 4, // 4. En Camino / Enviado
            'delivered'  => 5, // 5. Entregado
            default      => 1,
        };
    }
}
