<?php

namespace App\Services\Checkout;

/**
 * What a cart costs, computed server-side — the only numbers any gateway is ever asked
 * to charge. `items` are OrderItem-ready rows (the PendingCheckout payload shape).
 */
final class CheckoutQuote
{
    public function __construct(
        public readonly array $items,
        public readonly float $subtotal,
        public readonly float $discount,
        public readonly float $shippingCost,
        public readonly bool $isExpress,
        public readonly float $total,
        public readonly int $totalQuantity,
    ) {
    }
}
