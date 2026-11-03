<?php

namespace App\Services\Crm;

use App\Models\Contact;
use App\Models\ContactTask;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Carbon;

/**
 * Recompra (Fase 2c): when a customer's product runs out, the owner gets a follow-up a few
 * days before, with the WhatsApp message ready.
 *
 * Runs-out date per contact + product, replaying their paid, not-cancelled orders oldest
 * first: each purchase lasts quantity × consumption_days, starting when it was bought or
 * when the previous purchase runs out, whichever is later (buying early stacks up).
 * One pending 'reorder' task per contact + product:
 *  - a purchase made after the task was created closes it ("Volvió a comprar") and a new
 *    one is scheduled from the new date;
 *  - no paid purchase left (cancelled / refunded) removes the pending task;
 *  - nothing is scheduled for products that ran out more than STALE_DAYS ago (the
 *    customer's lifecycle stage covers those).
 */
class ReorderReminders
{
    public const REMIND_DAYS_BEFORE = 5;
    public const STALE_DAYS = 30;

    public function __construct(private ContactService $contacts)
    {
    }

    /** Every product of the order that has a consumption time. */
    public function refreshForOrder(Order $order): void
    {
        if (!$order->contact_id || !($contact = Contact::find($order->contact_id))) {
            return;
        }

        $productIds = $order->items()->whereNotNull('product_id')->distinct()->pluck('product_id');
        Product::whereIn('id', $productIds)->whereNotNull('consumption_days')->get()
            ->each(fn (Product $product) => $this->refresh($contact, $product));
    }

    /** After the owner sets or changes a product's duration: its buyers of the last 6 months. */
    public function refreshForProduct(Product $product): int
    {
        $contactIds = Order::where('store_id', $product->store_id)->whereNotNull('contact_id')
            ->where('created_at', '>=', now()->subMonths(6))
            ->whereHas('items', fn ($q) => $q->where('product_id', $product->id))
            ->distinct()->pluck('contact_id');

        Contact::whereIn('id', $contactIds)->get()->each(fn (Contact $contact) => $this->refresh($contact, $product));

        return $contactIds->count();
    }

    public function refresh(Contact $contact, Product $product): void
    {
        $pending = ContactTask::where('contact_id', $contact->id)->where('product_id', $product->id)
            ->where('kind', 'reorder')->pending()->first();

        $days = (int) $product->consumption_days;
        [$runsOut, $lastOrder] = $days > 0 ? $this->runsOut($contact, $product, $days) : [null, null];

        if (!$runsOut) {
            // No duration anymore, or no paid purchase left: a scheduled reminder makes no sense.
            $pending?->delete();

            return;
        }

        if ($pending && $lastOrder->created_at->gt($pending->created_at)) {
            $this->contacts->completeTask($pending, "Volvió a comprar (pedido #{$lastOrder->order_number})", null);
            $pending = null;
        }

        if ($runsOut->lt(now()->subDays(self::STALE_DAYS))) {
            return;
        }

        $lastingDays = $lastOrder->created_at->diffInDays($runsOut);
        $remindAt = $runsOut->copy()->subDays(min(self::REMIND_DAYS_BEFORE, max(1, (int) ceil($lastingDays / 4))))->setTime(10, 0);
        $attributes = [
            'title'  => "Recompra de {$product->name} — se le acaba el "
                . $runsOut->locale('es')->isoFormat($runsOut->isCurrentYear() ? 'D [de] MMMM' : 'D [de] MMMM [de] YYYY'),
            'due_at' => $remindAt->lt(now()) ? now() : $remindAt,
        ];

        if ($pending) {
            $pending->update($attributes);
        } else {
            ContactTask::create($attributes + [
                'store_id'   => $contact->store_id,
                'contact_id' => $contact->id,
                'product_id' => $product->id,
                'kind'       => 'reorder',
            ]);
        }
    }

    /** @return array{0: ?Carbon, 1: ?Order} when the product runs out and the last order that counted */
    private function runsOut(Contact $contact, Product $product, int $days): array
    {
        $orders = Order::where('contact_id', $contact->id)
            ->where('status', '!=', 'cancelled')
            ->whereIn('payment_status', ['paid', 'partial'])
            ->whereHas('items', fn ($q) => $q->where('product_id', $product->id))
            ->with(['items' => fn ($q) => $q->where('product_id', $product->id)])
            ->orderBy('created_at')->orderBy('id')
            ->get();

        $cursor = null;
        $last = null;
        foreach ($orders as $order) {
            $units = (int) $order->items->sum('quantity');
            if ($units <= 0) {
                continue;
            }
            $start = $cursor && $cursor->gt($order->created_at) ? $cursor : $order->created_at->copy();
            $cursor = $start->copy()->addDays($units * $days);
            $last = $order;
        }

        return [$cursor, $last];
    }
}
