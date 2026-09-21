<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
     * Purchase history for the Tribio Pass hub / mobile "Mis Compras": orders
     * matched by user_id or customer_email, across every store.
     */
    public function ordersFor(User $user): Collection
    {
        return Order::where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhere('customer_email', $user->email);
            })
            ->with(['items', 'store:id,name,slug,logo_path'])
            ->latest()
            ->get()
            ->map(function ($order) {
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
                    'items'            => $order->items->map(function ($i) {
                        return [
                            'name'     => $i->product_name,
                            'price'    => (float) $i->price,
                            'quantity' => $i->quantity,
                            'subtotal' => (float) $i->subtotal,
                        ];
                    }),
                    'shipping_address' => $order->customer_address ? "{$order->customer_address}, {$order->customer_city} ({$order->customer_state})" : '',
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
