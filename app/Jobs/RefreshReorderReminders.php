<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\Crm\CrmSchema;
use App\Services\Crm\ReorderReminders;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Recompra (Fase 2c) for one order. Dispatched after the checkout transaction commits:
 * the order observer fires before the order's items exist, and a reminder must never
 * slow down or break a sale.
 */
class RefreshReorderReminders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public function __construct(public int $orderId)
    {
    }

    public function handle(CrmSchema $schema, ReorderReminders $reminders): void
    {
        if (!$schema->reorderReady() || !($order = Order::find($this->orderId))) {
            return;
        }

        $reminders->refreshForOrder($order);
    }
}
