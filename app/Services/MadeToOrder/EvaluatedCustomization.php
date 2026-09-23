<?php

namespace App\Services\MadeToOrder;

/** A buyer's answers for one cart line, validated against the product's schema. */
final class EvaluatedCustomization
{
    /**
     * @param array<int, array> $rows            snapshot rows stored in order_items.customization
     * @param float             $extraPerUnit    sum of the chosen extras, in the store's base currency (PEN)
     * @param int               $quantity        units to produce (a size grid overrides the cart quantity)
     * @param array<int,string> $attachmentTokens uploads to link to the order item once it exists
     */
    public function __construct(
        public readonly array $rows,
        public readonly float $extraPerUnit,
        public readonly int $quantity,
        public readonly array $attachmentTokens,
        public readonly ?string $requiredBy,
    ) {
    }
}
