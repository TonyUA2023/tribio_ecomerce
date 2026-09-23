<?php

namespace App\Services\Checkout;

/**
 * What a cart costs, computed server-side — the only numbers any gateway is ever asked
 * to charge. `items` are OrderItem-ready rows (the PendingCheckout payload shape).
 *
 * `amountDueNow` is what the gateway charges today: the full total, or the merchant's
 * deposit share when the cart holds made-to-order items (stores.deposit_percent).
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
        public readonly bool $hasMadeToOrder = false,
        public readonly int $depositPercent = 100,
        public readonly ?float $amountDueNowOverride = null,
        public readonly ?string $requiredBy = null,
        public readonly ?string $estimatedReadyAt = null,
    ) {
    }

    public function amountDueNow(): float
    {
        return $this->amountDueNowOverride ?? $this->total;
    }

    public function isDeposit(): bool
    {
        return $this->depositPercent < 100 && $this->amountDueNow() < $this->total;
    }
}
