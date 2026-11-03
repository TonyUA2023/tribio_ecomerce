<?php

namespace App\Services\Crm;

use App\Models\Contact;
use App\Models\Order;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Turns the customer data copied into each order into one contact per person per store,
 * matched by phone first, then e-mail, then document number. Called for every new order
 * (OrderObserver) and by `crm:backfill-contacts` for the orders placed before.
 */
class ContactResolver
{
    /** Order sources that are also valid contact sources; anything else is "store". */
    private const SOURCES = ['store', 'whatsapp', 'manual', 'pos'];

    public static function normalizePhone(?string $phone, ?string $country = 'PE'): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (strlen($digits) < 6 || strlen($digits) > 15) {
            return null;
        }
        // Peruvian mobiles are typed without the country code: 987654321 → 51987654321.
        if (($country ?: 'PE') === 'PE' && strlen($digits) === 9 && $digits[0] === '9') {
            return '51' . $digits;
        }

        return $digits;
    }

    public static function normalizeEmail(?string $email): ?string
    {
        $email = mb_strtolower(trim((string) $email));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    /** Links the order to its contact (creating it) and refreshes the contact's totals. */
    public function linkOrder(Order $order): ?Contact
    {
        $phone = self::normalizePhone($order->customer_phone, $order->customer_country);
        $email = self::normalizeEmail($order->customer_email);
        $document = trim((string) $order->customer_document_number) ?: null;
        if (!$phone && !$email && !$document) {
            return null;
        }

        try {
            $contact = $this->upsert($order, $phone, $email, $document);
        } catch (UniqueConstraintViolationException) {
            // Two orders of the same new phone at once: the other one created it first.
            $contact = $this->upsert($order, $phone, $email, $document);
        }

        // Query-builder update: no model events, so the order observer is not re-entered.
        Order::whereKey($order->id)->update(['contact_id' => $contact->id]);
        $order->setAttribute('contact_id', $contact->id);
        $order->syncOriginalAttribute('contact_id');

        $this->refreshTotals($contact);

        return $contact;
    }

    public function refreshTotals(Contact $contact): void
    {
        $valid = Order::where('contact_id', $contact->id)
            ->where('status', '!=', 'cancelled')
            ->where('payment_status', '!=', 'failed');

        $contact->forceFill([
            'orders_count'   => (clone $valid)->count(),
            'total_spent'    => round((float) (clone $valid)->where('currency', 'PEN')->whereIn('payment_status', ['paid', 'partial'])
                ->sum(DB::raw("CASE WHEN payment_status = 'partial' THEN COALESCE(amount_paid, 0) ELSE total" . $this->returned() . ' END')), 2),
            'first_order_at' => (clone $valid)->min('created_at'),
            'last_order_at'  => (clone $valid)->max('created_at'),
        ])->save();

        // Fase 2: the stage follows the orders (only once the profile columns exist).
        if (app(CrmSchema::class)->profileReady()) {
            app(LifecycleClassifier::class)->apply($contact);
        }
    }

    /** Returns (ERP Fase 4c) lower what a customer spent. */
    private function returned(): string
    {
        return app(\App\Services\Pos\PosSchema::class)->extrasReady() ? ' - COALESCE(returned_total, 0)' : '';
    }

    private function upsert(Order $order, ?string $phone, ?string $email, ?string $document): Contact
    {
        return DB::transaction(function () use ($order, $phone, $email, $document) {
            $contact = $this->find($order->store_id, $phone, $email, $document);

            $details = array_filter([
                'phone'           => $phone,
                'email'           => $email,
                'document_type'   => $document ? $order->customer_document_type : null,
                'document_number' => $document,
                'city'            => $order->customer_city,
            ]);

            if (!$contact) {
                return Contact::create($details + [
                    'store_id' => $order->store_id,
                    'name'     => trim((string) $order->customer_name) ?: 'Cliente',
                    'source'   => in_array($order->source, self::SOURCES, true) ? $order->source : 'store',
                ]);
            }

            // Fill what the contact is missing; never overwrite what the owner already has.
            $missing = array_filter($details, fn ($value, $key) => blank($contact->{$key}), ARRAY_FILTER_USE_BOTH);
            // A phone that already belongs to another contact of this store stays there.
            if (isset($missing['phone']) && Contact::where('store_id', $order->store_id)->where('phone', $missing['phone'])->exists()) {
                unset($missing['phone']);
            }
            if ($missing) {
                $contact->update($missing);
            }

            return $contact;
        });
    }

    private function find(int $storeId, ?string $phone, ?string $email, ?string $document): ?Contact
    {
        foreach (['phone' => $phone, 'email' => $email, 'document_number' => $document] as $column => $value) {
            if ($value) {
                $contact = Contact::where('store_id', $storeId)->where($column, $value)->lockForUpdate()->first();
                if ($contact) {
                    return $contact;
                }
            }
        }

        return null;
    }
}
