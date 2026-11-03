<?php

namespace App\Services\Crm;

use App\Models\Contact;
use App\Models\Deal;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Oportunidades (Fase 2e): open stages move freely; closing is explicit — won (optionally
 * with the real order, whose total becomes the amount when none was estimated) or lost
 * (with a reason). Every change is written in the customer's timeline.
 */
class DealService
{
    public function __construct(private ContactService $contacts)
    {
    }

    public function create(Contact $contact, array $data, ?int $userId): Deal
    {
        return DB::transaction(function () use ($contact, $data, $userId) {
            $deal = Deal::create([
                'store_id'          => $contact->store_id,
                'contact_id'        => $contact->id,
                'user_id'           => $userId,
                'title'             => trim($data['title']),
                'stage'             => 'nuevo',
                'amount'            => $data['amount'] ?? null,
                'expected_close_at' => $data['expected_close_at'] ?? null,
                'notes'             => $data['notes'] ?? null,
            ]);
            $this->log($deal, 'Nueva oportunidad: ' . $deal->title . $this->amountText($deal), $userId);

            return $deal;
        });
    }

    public function update(Deal $deal, array $data): Deal
    {
        $deal->update([
            'title'             => trim($data['title']),
            'amount'            => $data['amount'] ?? null,
            'expected_close_at' => $data['expected_close_at'] ?? null,
            'notes'             => $data['notes'] ?? null,
        ]);

        return $deal;
    }

    public function move(Deal $deal, string $stage, ?int $userId): void
    {
        if (!$deal->isOpen()) {
            throw ValidationException::withMessages(['stage' => 'Esta oportunidad ya se cerró. Vuelve a abrirla para moverla.']);
        }
        if (!isset(Deal::OPEN_STAGES[$stage]) || $stage === $deal->stage) {
            return;
        }

        $from = $deal->stage_label;
        $deal->update(['stage' => $stage]);
        $this->log($deal, "«{$deal->title}»: {$from} → {$deal->stage_label}", $userId);
    }

    public function win(Deal $deal, ?int $orderId, ?int $userId): void
    {
        if (!$deal->isOpen()) {
            throw ValidationException::withMessages(['stage' => 'Esta oportunidad ya se cerró.']);
        }

        $order = null;
        if ($orderId) {
            // Only an order of this same customer can close it.
            $order = $deal->contact->orders()->find($orderId);
            if (!$order) {
                throw ValidationException::withMessages(['order_id' => 'Ese pedido no es de este cliente.']);
            }
        }

        DB::transaction(function () use ($deal, $order, $userId) {
            $deal->update([
                'stage'     => 'ganada',
                'closed_at' => now(),
                'order_id'  => $order?->id,
                'amount'    => $deal->amount ?? $order?->total,
            ]);
            $this->log($deal, "🏆 Ganada: «{$deal->title}»" . $this->amountText($deal) . ($order ? " · pedido #{$order->order_number}" : ''), $userId);
        });
    }

    public function lose(Deal $deal, string $reason, ?string $note, ?int $userId): void
    {
        if (!$deal->isOpen()) {
            throw ValidationException::withMessages(['stage' => 'Esta oportunidad ya se cerró.']);
        }

        DB::transaction(function () use ($deal, $reason, $note, $userId) {
            $deal->update(['stage' => 'perdida', 'closed_at' => now(), 'lost_reason' => $reason, 'lost_note' => $note ?: null]);
            $this->log($deal, "Perdida: «{$deal->title}» — " . (Deal::LOST_REASONS[$reason] ?? $reason) . ($note ? " ({$note})" : ''), $userId);
        });
    }

    public function reopen(Deal $deal, ?int $userId): void
    {
        if ($deal->isOpen()) {
            return;
        }

        $deal->update(['stage' => 'negociando', 'closed_at' => null, 'lost_reason' => null, 'lost_note' => null, 'order_id' => null]);
        $this->log($deal, "Reabierta: «{$deal->title}»", $userId);
    }

    /**
     * Board numbers: open value and count per stage, and the last 90 days' results.
     *
     * @return array{stages: array<string, array{count: int, amount: float}>, open_amount: float, won_amount: float, won: int, lost: int, win_rate: ?float}
     */
    public function summary(Store $store): array
    {
        $open = Deal::where('store_id', $store->id)->open()
            ->selectRaw('stage, COUNT(*) as total, COALESCE(SUM(amount), 0) as value')->groupBy('stage')->get()->keyBy('stage');
        $closed = Deal::where('store_id', $store->id)->whereIn('stage', ['ganada', 'perdida'])
            ->where('closed_at', '>=', now()->subDays(90))
            ->selectRaw('stage, COUNT(*) as total, COALESCE(SUM(amount), 0) as value')->groupBy('stage')->get()->keyBy('stage');

        $won = (int) ($closed['ganada']->total ?? 0);
        $lost = (int) ($closed['perdida']->total ?? 0);

        return [
            'stages'      => collect(Deal::OPEN_STAGES)->map(fn ($label, $stage) => [
                'count'  => (int) ($open[$stage]->total ?? 0),
                'amount' => (float) ($open[$stage]->value ?? 0),
            ])->all(),
            'open_amount' => (float) $open->sum('value'),
            'won_amount'  => (float) ($closed['ganada']->value ?? 0),
            'won'         => $won,
            'lost'        => $lost,
            'win_rate'    => $won + $lost > 0 ? round($won * 100 / ($won + $lost), 1) : null,
        ];
    }

    private function log(Deal $deal, string $text, ?int $userId): void
    {
        $this->contacts->log($deal->contact, 'deal', $text, $userId);
    }

    private function amountText(Deal $deal): string
    {
        return $deal->amount !== null ? ' (S/ ' . number_format((float) $deal->amount, 2) . ')' : '';
    }
}
