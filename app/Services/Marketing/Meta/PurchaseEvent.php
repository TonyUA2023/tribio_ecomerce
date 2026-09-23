<?php

namespace App\Services\Marketing\Meta;

use App\Models\Order;

/**
 * The "Purchase" of an order, as the browser Pixel and the Conversions API both
 * report it. Same event_id on both sides, so Meta counts the sale once.
 *
 * Value = the order total, also for a made-to-order deposit: the sale is the whole
 * order (that is what the ad produced); the deposit is only how it is paid. The event
 * is sent once, when the order first becomes 'partial' or 'paid' — paying the balance
 * later is not a second purchase.
 */
class PurchaseEvent
{
    public function __construct(private BuyerMatchData $buyer) {}

    public static function eventId(Order $order): string
    {
        return 'purchase_' . $order->order_number;
    }

    /** Conversions API event (server side). */
    public function forConversionsApi(Order $order, array $tracking): array
    {
        return array_filter([
            'event_name'       => 'Purchase',
            'event_time'       => now()->timestamp,
            'event_id'         => self::eventId($order),
            'action_source'    => 'website',
            'event_source_url' => $tracking['event_source_url'] ?? null,
            'user_data'        => $this->buyer->forOrder($order, $tracking),
            'custom_data'      => $this->customData($order) + ['order_id' => $order->order_number],
        ], fn ($v) => $v !== null);
    }

    /** What the storefront hands to fbq('track', 'Purchase', …) — no personal data. */
    public function forBrowser(Order $order): array
    {
        return ['event_id' => self::eventId($order)] + $this->customData($order);
    }

    private function customData(Order $order): array
    {
        $items = $order->items->filter(fn ($item) => $item->product_id);
        $contents = $items->map(fn ($item) => [
            'id'         => CatalogItemMapper::itemId((int) $item->product_id, $item->variant_id ? (int) $item->variant_id : null),
            'quantity'   => (int) $item->quantity,
            'item_price' => round((float) $item->price, 2),
        ])->values()->all();

        return [
            'currency'     => $order->currency ?: 'PEN',
            'value'        => round((float) $order->total, 2),
            'content_type' => 'product',
            'content_ids'  => array_values(array_unique(array_column($contents, 'id'))),
            'contents'     => $contents,
            'num_items'    => (int) $items->sum('quantity'),
        ];
    }
}
