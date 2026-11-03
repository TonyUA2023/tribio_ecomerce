<?php

namespace App\Services\Pos;

use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\Register;
use App\Models\RegisterMovement;
use App\Models\RegisterSession;
use App\Models\Store;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cash registers and their shifts (ERP Fase 4a): open with a float, put cash in or take it
 * out, close with the counted cash. Expected cash = float + cash sales + cash in − cash out.
 */
class RegisterService
{
    /** The store's active registers; the first visit creates "Caja principal". */
    public function registers(Store $store): Collection
    {
        if (!Register::where('store_id', $store->id)->exists()) {
            Register::create(['store_id' => $store->id, 'name' => 'Caja principal']);
        }

        return Register::where('store_id', $store->id)->where('is_active', true)->with('openSession.opener:id,name')->orderBy('id')->get();
    }

    public function create(Store $store, string $name): Register
    {
        if (Register::where('store_id', $store->id)->where('is_active', true)->count() >= 20) {
            throw ValidationException::withMessages(['name' => 'Ya tienes 20 cajas.']);
        }

        return Register::create(['store_id' => $store->id, 'name' => trim($name)]);
    }

    public function open(Register $register, float $float, ?int $userId, ?Actor $actor = null): RegisterSession
    {
        return DB::transaction(function () use ($register, $float, $userId, $actor) {
            // The register row is the lock: two cashiers opening the same register at once get one shift.
            Register::whereKey($register->id)->lockForUpdate()->first();
            if (RegisterSession::where('register_id', $register->id)->where('status', 'open')->exists()) {
                throw ValidationException::withMessages(['opening_float' => "«{$register->name}» ya está abierta."]);
            }

            return RegisterSession::create([
                'store_id'      => $register->store_id,
                'register_id'   => $register->id,
                'status'        => 'open',
                'opened_by'     => $userId,
            ] + ($actor ? $actor->columns('opened_staff_id', null) : []) + [
                'opened_at'     => now(),
                'opening_float' => round($float, 2),
            ]);
        });
    }

    public function movement(RegisterSession $session, string $type, float $amount, string $reason, ?int $userId, ?Actor $actor = null): RegisterMovement
    {
        $this->assertOpen($session, 'amount');
        if ($type === 'out' && $amount > $this->summary($session)['expected_cash'] + 0.005) {
            throw ValidationException::withMessages(['amount' => 'No hay tanto efectivo en la caja.']);
        }

        return $session->movements()->forceCreate(['register_session_id' => $session->id, 'type' => $type, 'amount' => round($amount, 2), 'reason' => trim($reason), 'user_id' => $userId]
            + ($actor ? $actor->columns() : []));
    }

    /**
     * What the shift took: sales, money per method, cash movements and the cash the drawer
     * should hold now.
     *
     * `credit` is what the shift's sales left owed (fiado); `collected` the debts paid into this register;
     * `refunds` the money given back for returns (already subtracted in `methods`).
     *
     * @return array{sales: int, sales_total: float, cancelled: int, methods: array<string, float>, credit: float, collected: float, cash_in: float, cash_out: float, expected_cash: float}
     */
    public function summary(RegisterSession $session): array
    {
        $methods = OrderPayment::where('register_session_id', $session->id)->where('status', OrderPayment::STATUS_PAID)
            ->selectRaw('method, SUM(amount) as total')->groupBy('method')->pluck('total', 'method')
            ->map(fn ($total) => round((float) $total, 2));
        $sales = Order::where('register_session_id', $session->id)->where('status', '!=', 'cancelled');
        $movements = $session->movements()->selectRaw('type, SUM(amount) as total')->groupBy('type')->pluck('total', 'type');
        $paidAtSale = (float) OrderPayment::where('register_session_id', $session->id)->where('status', OrderPayment::STATUS_PAID)
            ->whereNotIn('kind', [OrderPayment::KIND_BALANCE, 'refund'])->whereIn('order_id', (clone $sales)->select('id'))->sum('amount');
        $refunds = (float) OrderPayment::where('register_session_id', $session->id)->where('status', OrderPayment::STATUS_PAID)
            ->where('kind', 'refund')->sum('amount');
        $collected = (float) OrderPayment::where('register_session_id', $session->id)->where('status', OrderPayment::STATUS_PAID)
            ->where('kind', OrderPayment::KIND_BALANCE)->sum('amount');
        $in = round((float) ($movements['in'] ?? 0), 2);
        $out = round((float) ($movements['out'] ?? 0), 2);

        return [
            'sales'         => (clone $sales)->count(),
            'sales_total'   => round((float) (clone $sales)->sum('total'), 2),
            'cancelled'     => Order::where('register_session_id', $session->id)->where('status', 'cancelled')->count(),
            'methods'       => collect(PosSaleService::METHODS)->keys()->mapWithKeys(fn ($m) => [$m => (float) ($methods[$m] ?? 0)])->all(),
            'credit'        => round(max(0, (float) (clone $sales)->sum('total') - $paidAtSale), 2),
            'collected'     => round($collected, 2),
            'refunds'       => round(abs($refunds), 2),
            'cash_in'       => $in,
            'cash_out'      => $out,
            'expected_cash' => round((float) $session->opening_float + (float) ($methods['cash'] ?? 0) + $in - $out, 2),
        ];
    }

    /** Freezes the count: expected vs counted cash and the money per method. */
    public function close(RegisterSession $session, float $counted, ?string $notes, ?int $userId, ?Actor $actor = null): RegisterSession
    {
        return DB::transaction(function () use ($session, $counted, $notes, $userId, $actor) {
            $locked = RegisterSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            $this->assertOpen($locked, 'counted_cash');
            $summary = $this->summary($locked);

            $locked->forceFill([
                'status'        => 'closed',
                'closed_by'     => $userId,
            ] + ($actor ? $actor->columns('closed_staff_id', null) : []) + [
                'closed_at'     => now(),
                'expected_cash' => $summary['expected_cash'],
                'counted_cash'  => round($counted, 2),
                'difference'    => round($counted - $summary['expected_cash'], 2),
                'totals'        => $summary,
                'notes'         => filled($notes) ? mb_substr(trim($notes), 0, 500) : null,
            ])->save();

            return $locked;
        });
    }

    private function assertOpen(RegisterSession $session, string $field): void
    {
        if (!$session->isOpen()) {
            throw ValidationException::withMessages([$field => 'Este turno de caja ya se cerró.']);
        }
    }
}
