<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PendingCheckout;
use App\Models\Store;
use App\Services\Payments\MercadoPagoBalanceCheckout;
use App\Services\Payments\MercadoPagoPayments;
use App\Services\Storefront\ColorScale;
use App\Services\Storefront\StorefrontStoreResolver;
use App\Services\Storefront\StorefrontTheme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

/**
 * The page a made-to-order buyer opens from the store's link (Order::balancePaymentUrl())
 * to follow the workshop stage and pay what is still owed. Every entry point is a signed
 * URL, so an order number alone never exposes an order.
 */
class BalancePaymentController extends Controller
{
    public function __construct(
        private readonly StorefrontStoreResolver $stores,
        private readonly MercadoPagoBalanceCheckout $checkout,
        private readonly MercadoPagoPayments $payments,
    ) {
    }

    public function show(string $slug, string $orderNumber): View
    {
        [$store, $order] = $this->resolve($slug, $orderNumber);
        $order->load('items');
        $owes = (float) $order->balance_due > 0;

        return view('storefront.balance', [
            'store' => $store,
            'order' => $order,
            'palette' => $this->palette($store),
            'canPayOnline' => $owes && $this->checkout->supports($store, $order),
            'startUrl' => $owes ? URL::temporarySignedRoute('store.balance.start', now()->addHours(6), [$store->slug, $order->order_number]) : null,
            'whatsappUrl' => $store->whatsapp_link
                ? $store->whatsapp_link . '?text=' . rawurlencode("Hola, quiero coordinar el pago del saldo de mi pedido #{$order->order_number}.")
                : null,
            'state' => session('balance_state'),
        ]);
    }

    public function start(string $slug, string $orderNumber): RedirectResponse
    {
        [$store, $order] = $this->resolve($slug, $orderNumber);
        if ((float) $order->balance_due <= 0 || !$this->checkout->supports($store, $order)) {
            return redirect()->to($order->balancePaymentUrl());
        }

        try {
            return redirect()->away($this->checkout->start($store, $order));
        } catch (\Throwable $e) {
            Log::error('Mercado Pago: no se pudo iniciar el pago del saldo.', [
                'store_id' => $store->id, 'order_number' => $order->order_number, 'error' => $e->getMessage(),
            ]);

            return redirect()->to($order->balancePaymentUrl())->with('balance_state', 'unavailable');
        }
    }

    /**
     * Mercado Pago's back_url. The query string only tells us which payment to ask about:
     * the balance is credited after Mercado Pago's API confirms it (or by the webhook).
     */
    public function return(string $slug, string $reference, string $token): RedirectResponse
    {
        $store = $this->stores->resolve($slug);
        $pending = PendingCheckout::where('store_id', $store->id)->where('reference', $reference)->firstOrFail();
        abort_unless($pending->isBalancePayment()
            && hash_equals((string) ($pending->gateway_meta['return_token'] ?? ''), $token), 404);
        $order = Order::where('store_id', $store->id)->findOrFail($pending->payload['balance_for_order_id']);

        $paymentId = request()->query('payment_id') ?? request()->query('collection_id');
        if (!$pending->isSettled() && $paymentId) {
            $payment = $this->payments->verifiedPayment($store, (string) $paymentId, $reference);
            if ($payment) {
                $pending->settleBalance($payment);
            }
        }

        $reported = request()->query('collection_status') ?? request()->query('status');
        $state = match (true) {
            $pending->fresh()->isSettled() => 'paid',
            in_array($reported, ['rejected', 'cancelled'], true) => 'failed',
            // Includes 'approved' not yet confirmed by Mercado Pago: never claim it as paid.
            in_array($reported, ['approved', 'pending', 'in_process'], true) => 'pending',
            default => null,
        };

        return redirect()->to($order->fresh()->balancePaymentUrl())->with('balance_state', $state);
    }

    /** @return array{0: Store, 1: Order} */
    private function resolve(string $slug, string $orderNumber): array
    {
        $store = $this->stores->resolve($slug);
        $order = Order::where('store_id', $store->id)->where('order_number', $orderNumber)->firstOrFail();
        abort_unless($order->isMadeToOrder(), 404);

        return [$store, $order];
    }

    private function palette(Store $store): array
    {
        try {
            return StorefrontTheme::for($store)->palette();
        } catch (\Throwable) {
            return ColorScale::primary('#1E293B');
        }
    }
}
