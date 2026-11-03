<?php

namespace App\Services\Pos;

use App\Models\Contact;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\RegisterSession;
use App\Models\Store;
use App\Services\Crm\ContactService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Credit sales ("fiado") and collections (ERP Fase 4b). A receivable is an order with a
 * due_date and balance_due > 0. Its installments are covered in order by what was paid
 * after the down payment, so nothing has to be kept in sync: the payments ledger stays the
 * only source of truth for money.
 */
class ReceivableService
{
    public const FREQUENCIES = [7 => 'Semanal', 15 => 'Quincenal', 30 => 'Mensual'];

    public const MAX_INSTALLMENTS = 12;

    public const DEFAULT_DAYS = 15;

    /** Days past due that split the aging report. */
    public const AGING = ['al_dia' => 'Al día', '1_30' => '1–30 días', '31_60' => '31–60 días', '61_90' => '61–90 días', '90_mas' => 'Más de 90 días'];

    public function __construct(private ContactService $contacts)
    {
    }

    /** Dates are business days in Peru, the app clock runs in UTC. */
    public function today(): Carbon
    {
        return now('America/Lima')->startOfDay();
    }

    /**
     * Even installments (the last one absorbs the cents) starting at $firstDue.
     *
     * @return list<array{number: int, due_date: string, amount: float}>
     */
    public function plan(float $amount, Carbon $firstDue, int $count, int $everyDays): array
    {
        $count = max(1, min(self::MAX_INSTALLMENTS, $count));
        $each = floor($amount / $count * 100) / 100;

        return array_map(fn ($i) => [
            'number'   => $i + 1,
            'due_date' => $firstDue->copy()->addDays($i * $everyDays)->toDateString(),
            'amount'   => round($i === $count - 1 ? $amount - $each * ($count - 1) : $each, 2),
        ], range(0, $count - 1));
    }

    /** Credit orders still owed, oldest first. */
    public function openOrders(int $storeId, ?int $contactId = null)
    {
        return Order::where('store_id', $storeId)->whereNotNull('due_date')->where('balance_due', '>', 0)
            ->whereNotIn('status', ['cancelled', 'refunded'])->whereNotIn('payment_status', ['refunded', 'failed'])
            ->when($contactId, fn ($q) => $q->where('contact_id', $contactId))
            ->orderBy('created_at')->orderBy('id');
    }

    /**
     * Each installment with what was paid of it and its state.
     *
     * @return Collection<int, array{number: int, due_date: Carbon, amount: float, paid: float, pending: float, status: string, days_late: int}>
     */
    public function schedule(Order $order): Collection
    {
        $installments = $order->installments;
        $financed = round((float) $installments->sum('amount'), 2);
        // What is no longer owed of the financed part: payments and returns both count (Fase 4c).
        $covered = max(0.0, round($financed - (float) $order->balance_due, 2));
        $today = $this->today();

        return $installments->map(function ($installment) use (&$covered, $today) {
            $paid = round(min((float) $installment->amount, $covered), 2);
            $covered = round($covered - $paid, 2);
            $pending = round((float) $installment->amount - $paid, 2);
            $due = Carbon::parse($installment->due_date->toDateString(), 'America/Lima');
            $late = $pending > 0 && $due->lt($today) ? (int) $due->diffInDays($today) : 0;

            return [
                'number'    => (int) $installment->number,
                'due_date'  => $due,
                'amount'    => (float) $installment->amount,
                'paid'      => $paid,
                'pending'   => $pending,
                'status'    => $pending <= 0 ? 'paid' : ($late > 0 ? 'overdue' : ($due->equalTo($today) ? 'due' : 'upcoming')),
                'days_late' => $late,
            ];
        });
    }

    /**
     * What each customer owes: total, overdue, next due date, worst delay.
     *
     * @return Collection<int, array{contact: Contact, owed: float, overdue: float, next_due: ?Carbon, days_late: int, orders: int}>
     */
    public function customers(Store $store): Collection
    {
        $orders = $this->openOrders($store->id)->whereNotNull('contact_id')->with(['installments', 'contact'])->get();

        return $orders->groupBy('contact_id')->map(function (Collection $orders) {
            $schedules = $orders->flatMap(fn (Order $order) => $this->schedule($order));
            $pending = $schedules->where('pending', '>', 0);

            return [
                'contact'   => $orders->first()->contact,
                'owed'      => round((float) $orders->sum('balance_due'), 2),
                'overdue'   => round((float) $pending->where('status', 'overdue')->sum('pending'), 2),
                'next_due'  => $pending->sortBy('due_date')->first()['due_date'] ?? null,
                'days_late' => (int) $pending->max('days_late'),
                'orders'    => $orders->count(),
            ];
        })->sortByDesc(fn ($row) => [$row['days_late'], $row['owed']])->values();
    }

    /** Store-wide totals and the aging buckets of what is pending. */
    public function summary(Store $store, ?Collection $customers = null): array
    {
        $customers ??= $this->customers($store);
        $aging = array_fill_keys(array_keys(self::AGING), 0.0);
        foreach ($this->openOrders($store->id)->with('installments')->get() as $order) {
            foreach ($this->schedule($order)->where('pending', '>', 0) as $row) {
                $bucket = match (true) {
                    $row['days_late'] === 0 => 'al_dia',
                    $row['days_late'] <= 30 => '1_30',
                    $row['days_late'] <= 60 => '31_60',
                    $row['days_late'] <= 90 => '61_90',
                    default                 => '90_mas',
                };
                $aging[$bucket] = round($aging[$bucket] + $row['pending'], 2);
            }
        }

        return [
            'owed'      => round((float) $customers->sum('owed'), 2),
            'overdue'   => round((float) $customers->sum('overdue'), 2),
            'customers' => $customers->count(),
            'late'      => $customers->where('overdue', '>', 0)->count(),
            'collected' => round((float) OrderPayment::where('kind', OrderPayment::KIND_BALANCE)->where('status', OrderPayment::STATUS_PAID)
                ->where('paid_at', '>=', now('America/Lima')->startOfMonth()->utc())
                ->whereHas('order', fn ($q) => $q->where('store_id', $store->id)->whereNotNull('due_date'))->sum('amount'), 2),
            'aging'     => $aging,
        ];
    }

    public function owedBy(Contact $contact): float
    {
        return round((float) $this->openOrders($contact->store_id, $contact->id)->sum('balance_due'), 2);
    }

    /** A credit limit, when set, caps what the customer may owe after this sale. */
    public function assertWithinLimit(Contact $contact, float $newDebt): void
    {
        if ($contact->credit_limit === null) {
            return;
        }
        $owed = $this->owedBy($contact);
        if ($owed + $newDebt > (float) $contact->credit_limit + 0.005) {
            throw ValidationException::withMessages(['credit' => sprintf('%s ya debe S/ %s y su límite de crédito es S/ %s: puede quedar debiendo hasta S/ %s.',
                $contact->name, number_format($owed, 2), number_format((float) $contact->credit_limit, 2), number_format(max(0, (float) $contact->credit_limit - $owed), 2))]);
        }
    }

    /**
     * A payment of the customer's debt, applied to the oldest credit sale first (or to one
     * sale). With an open shift the money counts in that register's cash. The same `uuid`
     * twice (double submit) records nothing new.
     *
     * @return array{applied: list<array{order: Order, amount: float}>, owed: float, duplicate: bool}
     */
    public function collect(Contact $contact, float $amount, string $method, ?RegisterSession $session, ?int $userId, string $uuid, ?int $orderId = null, ?Actor $actor = null): array
    {
        if (!isset(PosSaleService::METHODS[$method])) {
            throw ValidationException::withMessages(['method' => 'Elige cómo te pagó.']);
        }
        if ($session && (!$session->isOpen() || (int) $session->store_id !== (int) $contact->store_id)) {
            throw ValidationException::withMessages(['register_session_id' => 'Esa caja ya se cerró.']);
        }
        if (OrderPayment::where('gateway', 'pos')->where('gateway_ref', 'like', "cobro:{$uuid}:%")->exists()) {
            return ['applied' => [], 'owed' => $this->owedBy($contact), 'duplicate' => true];
        }

        $amount = round($amount, 2);
        $applied = DB::transaction(function () use ($contact, $amount, $method, $session, $userId, $uuid, $orderId, $actor) {
            $orders = $this->openOrders($contact->store_id, $contact->id)->when($orderId, fn ($q) => $q->whereKey($orderId))->lockForUpdate()->get();
            $owed = round((float) $orders->sum('balance_due'), 2);
            if ($owed <= 0) {
                throw ValidationException::withMessages(['amount' => "{$contact->name} no tiene deudas pendientes."]);
            }
            if ($amount > $owed + 0.005) {
                throw ValidationException::withMessages(['amount' => 'Debe S/ ' . number_format($owed, 2) . ': no se puede cobrar más que eso.']);
            }

            $left = $amount;
            $applied = [];
            foreach ($orders as $order) {
                if ($left <= 0) {
                    break;
                }
                $take = round(min($left, (float) $order->balance_due), 2);
                $order->recordPayment($take, OrderPayment::KIND_BALANCE, 'pos', "cobro:{$uuid}:{$order->id}", $userId, 'Cobro de deuda')
                    ->forceFill(['method' => $method, 'register_session_id' => $session?->id] + ($actor ? $actor->columns('staff_id', null) : []))->save();
                $applied[] = ['order' => $order->fresh(), 'amount' => $take];
                $left = round($left - $take, 2);
            }

            return $applied;
        });

        $owed = $this->owedBy($contact);
        $this->contacts->log($contact, 'credit', sprintf('Pagó S/ %s de su deuda (%s). %s', number_format($amount, 2), PosSaleService::METHODS[$method],
            $owed > 0 ? 'Aún debe S/ ' . number_format($owed, 2) . '.' : 'Ya no debe nada.'), $userId);

        return ['applied' => $applied, 'owed' => $owed, 'duplicate' => false];
    }
}
