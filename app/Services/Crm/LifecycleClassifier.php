<?php

namespace App\Services\Crm;

use App\Models\Contact;
use App\Models\ContactEvent;

/**
 * Where a customer stands, by rules on their orders (no AI: auditable, free, instant).
 *
 *   lead        no order yet
 *   inactivo    last order more than 90 days ago
 *   en_riesgo   bought 2+ times and is overdue: more than 1.5 × their usual gap
 *               between orders (at least 30 days) since the last one
 *   vip         5+ orders
 *   recurrente  2+ orders
 *   nuevo       1 order
 *
 * Applied whenever an order changes the contact's totals (ContactResolver) and nightly
 * (`crm:refresh-stages`), because "en riesgo" and "inactivo" happen by time passing.
 */
class LifecycleClassifier
{
    public const VIP_ORDERS = 5;
    public const RISK_FACTOR = 1.5;
    public const RISK_MIN_DAYS = 30;

    public function stageFor(Contact $contact): string
    {
        $orders = (int) $contact->orders_count;
        if ($orders === 0 || !$contact->last_order_at) {
            return 'lead';
        }

        $sinceLast = $contact->last_order_at->diffInDays(now());
        if ($sinceLast > Contact::INACTIVE_AFTER_DAYS) {
            return 'inactivo';
        }

        if ($orders >= 2 && $contact->first_order_at) {
            $usualGap = $contact->first_order_at->diffInDays($contact->last_order_at) / ($orders - 1);
            if ($sinceLast > max(self::RISK_MIN_DAYS, self::RISK_FACTOR * $usualGap)) {
                return 'en_riesgo';
            }
        }

        return match (true) {
            $orders >= self::VIP_ORDERS => 'vip',
            $orders >= 2                => 'recurrente',
            default                     => 'nuevo',
        };
    }

    /** Saves the stage if it changed; a change of an existing stage is written in the timeline. */
    public function apply(Contact $contact): void
    {
        $stage = $this->stageFor($contact);
        $previous = $contact->lifecycle_stage;
        if ($stage === $previous) {
            return;
        }

        $contact->forceFill(['lifecycle_stage' => $stage, 'stage_changed_at' => now()])->save();

        if ($previous !== null) {
            ContactEvent::create([
                'store_id'    => $contact->store_id,
                'contact_id'  => $contact->id,
                'type'        => 'stage',
                'body'        => (Contact::STAGES[$previous]['label'] ?? $previous) . ' → ' . Contact::STAGES[$stage]['label'],
                'occurred_at' => now(),
            ]);
        }
    }
}
