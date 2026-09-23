<?php

namespace App\Jobs\Marketing;

use App\Models\MarketingEventLog;
use App\Models\Order;
use App\Models\PendingCheckout;
use App\Models\StoreMarketingIntegration;
use App\Services\Marketing\Meta\ConversionsApi;
use App\Services\Marketing\Meta\ConversionsApiException;
use App\Services\Marketing\Meta\MetaIntegrationService;
use App\Services\Marketing\Meta\PurchaseEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Reports one paid order to Meta's Conversions API. Idempotent through the unique
 * marketing_event_logs row: however many times it is dispatched or retried, a sale
 * that was sent is never sent again. Only orders whose buyer accepted cookies at
 * checkout are reported (the choice is stored in the PendingCheckout payload).
 *
 * It never throws. It is dispatched after commit from inside checkout transactions, and
 * on a `sync` queue an exception would surface in the very checkout that paid — the
 * shape of the Mercado Pago charge-loss bug. Retries use release() instead.
 */
class SendMetaPurchaseEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    /** Seconds before the 2nd and 3rd attempts. */
    private const RETRY_DELAYS = [60, 300];

    public function __construct(public int $orderId) {}

    public function handle(MetaIntegrationService $meta, PurchaseEvent $purchase, ConversionsApi $api): void
    {
        try {
            $this->send($meta, $purchase, $api);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function send(MetaIntegrationService $meta, PurchaseEvent $purchase, ConversionsApi $api): void
    {
        $order = Order::with(['store', 'items'])->find($this->orderId);
        if (!$order || !$order->store || !in_array($order->payment_status, ['paid', 'partial'], true)) {
            return;
        }
        $integration = $meta->active($order->store);
        if (!$integration?->hasConversionsApi()) {
            return;
        }

        $log = MarketingEventLog::createOrFirst([
            'store_id'   => $order->store_id,
            'provider'   => StoreMarketingIntegration::PROVIDER_META,
            'event_name' => 'Purchase',
            'event_id'   => PurchaseEvent::eventId($order),
        ], [
            'order_id' => $order->id,
            'status'   => MarketingEventLog::STATUS_PENDING,
        ]);
        if (in_array($log->status, [MarketingEventLog::STATUS_SENT, MarketingEventLog::STATUS_SKIPPED], true)) {
            return;
        }

        $tracking = $this->trackingFor($order);
        if (($tracking['consent'] ?? null) !== 'granted') {
            $log->update([
                'status' => MarketingEventLog::STATUS_SKIPPED,
                'error'  => 'El comprador no aceptó las cookies de publicidad.',
            ]);
            return;
        }

        $log->increment('attempts');
        try {
            $response = $api->send($integration, [$purchase->forConversionsApi($order, $tracking)]);
        } catch (\Throwable $e) {
            $message = ConversionsApi::describe($e, $integration);
            $integration->recordError($message);
            // A 4xx (bad token, wrong Pixel) fails identically on every retry.
            $retry = !($e instanceof ConversionsApiException && $e->isPermanent()) && $this->attempts() < $this->tries;
            $log->update([
                'status' => $retry ? MarketingEventLog::STATUS_PENDING : MarketingEventLog::STATUS_FAILED,
                'error'  => $message,
            ]);
            if ($retry) {
                $this->release(self::RETRY_DELAYS[$this->attempts() - 1] ?? 300);
            }
            return;
        }

        $log->update([
            'status'   => MarketingEventLog::STATUS_SENT,
            'sent_at'  => now(),
            'response' => ConversionsApi::summary($response),
            'error'    => null,
        ]);
        $integration->recordSuccess();
    }

    /** @return array<string, mixed> */
    private function trackingFor(Order $order): array
    {
        $pending = PendingCheckout::where('order_id', $order->id)->latest('id')->first();
        $tracking = $pending?->payload['tracking'] ?? null;

        return is_array($tracking) ? $tracking : [];
    }
}
