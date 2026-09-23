<?php

namespace App\Services\Checkout;

use App\Models\Product;
use App\Models\Store;

/**
 * Prices a storefront cart: line prices (product or variant, in the visitor's currency,
 * plus the USD figure PayPal always needs), stock availability, bulk discount, shipping
 * (flat/zone rates, free-shipping thresholds, express) and the total. Extracted verbatim
 * from StoreController::performCheckout() so every gateway charges from one place —
 * behavior pinned by tests/Feature/CheckoutGatewaysTest.
 */
class CheckoutPricing
{
    public function __construct(private readonly ShippingCostResolver $shipping)
    {
    }

    /**
     * @param iterable<array{id: int, quantity: int, variant_id?: ?int, variant_title?: ?string, variant_attributes?: ?array}> $cartItems
     * @throws CheckoutPricingException
     */
    public function quote(Store $store, iterable $cartItems, string $country, ?string $state, bool $wantsExpress): CheckoutQuote
    {
        $cartItems = collect($cartItems);
        $products  = Product::whereIn('id', $cartItems->pluck('id'))
            ->where('store_id', $store->id)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        if ($products->isEmpty()) {
            throw new CheckoutPricingException('Carrito inválido');
        }

        $subtotal = 0;
        $orderItemsData = [];

        foreach ($cartItems as $item) {
            $product  = $products[$item['id']] ?? null;
            if (!$product) continue;

            $qty               = (int) $item['quantity'];
            $price             = $product->resolvePrice();
            // Resolved in parallel regardless of the customer's browsing currency: PayPal
            // never settles in PEN (confirmed against PayPal's currency-codes reference),
            // so its branch always needs a USD amount, prefering each product's own
            // explicit USD price over a live-converted one (see Product::resolvePrice()).
            $priceUsd          = $product->resolvePrice('USD');
            $sku               = $product->sku;
            $variantId         = $item['variant_id'] ?? null;
            $variantTitle      = $item['variant_title'] ?? null;
            $variantAttributes = $item['variant_attributes'] ?? null;
            $imagePath         = $product->image_path;

            // Availability is only checked here, never decremented: stock only moves once
            // a payment is actually confirmed, in PendingCheckout::materialize(). A
            // gateway checkout that's abandoned or rejected must never cost real inventory.
            if ($variantId) {
                $variant = $product->variants()->where('id', $variantId)->first();
                if ($variant) {
                    $price             = $variant->resolvePrice();
                    $priceUsd          = $variant->resolvePrice('USD');
                    if (!empty($variant->sku)) $sku = $variant->sku;
                    $variantTitle      = $variant->title;
                    $variantAttributes = $variant->attributes;
                    if (!empty($variant->image_path)) $imagePath = $variant->image_path;

                    if ($product->track_stock && empty($product->out_of_stock_message) && !$product->allow_backorder) {
                        if ($variant->stock < $qty) {
                            throw new CheckoutPricingException("Stock insuficiente para {$product->name} ({$variantTitle}). Disponibles: {$variant->stock}.");
                        }
                    }
                }
            }

            if ($product->track_stock && empty($product->out_of_stock_message) && !$product->allow_backorder) {
                if (!$variantId && $product->stock < $qty) {
                    throw new CheckoutPricingException("Stock insuficiente para {$product->name}. Disponibles: {$product->stock}.");
                }
            }

            $itemSubtotal = $price * $qty;
            $subtotal += $itemSubtotal;

            $orderItemsData[] = [
                'product_id'         => $product->id,
                'variant_id'         => $variantId,
                'variant_title'      => $variantTitle,
                'variant_attributes' => $variantAttributes,
                'product_name'       => $product->name,
                'product_sku'        => $sku,
                'product_image'      => $imagePath,
                'price'              => $price,
                'price_usd'          => $priceUsd,
                'quantity'           => $qty,
                'subtotal'           => $itemSubtotal,
            ];
        }

        // Descuento por cantidad (compra al por mayor) — se calcula sobre el subtotal
        // original, antes de envío. Ver Store::calculateBulkDiscount().
        $totalQuantity = collect($orderItemsData)->sum('quantity');
        $discount = $store->calculateBulkDiscount($subtotal, $totalQuantity);

        $shippingCost = $this->shipping->resolve($store, $country, $state);

        // Envío gratis por cantidad o monto (ver Store::qualifiesForFreeShipping) anula
        // solo la tarifa base — el envío express, si el cliente lo elige aparte, se sigue
        // cobrando normalmente.
        if ($store->qualifiesForFreeShipping($subtotal, $totalQuantity)) {
            $shippingCost = 0;
        }

        $isExpress = false;
        if ($wantsExpress && $store->is_express_shipping_enabled) {
            $isExpress = true;
            $shippingCost += $store->express_shipping_cost;
        }

        $total = $subtotal - $discount + $shippingCost;

        return new CheckoutQuote($orderItemsData, $subtotal, $discount, $shippingCost, $isExpress, $total, $totalQuantity);
    }
}
