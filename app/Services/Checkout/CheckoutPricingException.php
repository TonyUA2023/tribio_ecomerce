<?php

namespace App\Services\Checkout;

/** A cart that can't be priced (empty/foreign products, not enough stock). Message is buyer-facing. */
class CheckoutPricingException extends \RuntimeException
{
}
