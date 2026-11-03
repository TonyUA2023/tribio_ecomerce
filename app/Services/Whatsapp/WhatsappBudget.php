<?php

namespace App\Services\Whatsapp;

use App\Models\WhatsappBroadcast;
use App\Models\WhatsappConnection;
use App\Models\WhatsappMessage;
use Illuminate\Support\Carbon;

/**
 * What WhatsApp costs the store this month and how much room is left (ERP Fase 3c).
 * Spent = cost Meta billed (from status webhooks); reserved = broadcast messages still in
 * the queue plus scheduled broadcasts. Also Meta's limit of customers the business may
 * write first to in a moving 24 h window.
 */
class WhatsappBudget
{
    /** Meta bills by the business's timezone; Tribio's stores are in Peru. */
    public const TIMEZONE = 'America/Lima';

    /** What Meta starts every new business portfolio at. */
    public const DEFAULT_LIMIT = 250;

    public function monthStart(): Carbon
    {
        return now(self::TIMEZONE)->startOfMonth()->utc();
    }

    public function spent(WhatsappConnection $connection): float
    {
        return (float) WhatsappMessage::where('store_id', $connection->store_id)
            ->where('created_at', '>=', $this->monthStart())->whereNotNull('cost')->sum('cost');
    }

    public function reserved(WhatsappConnection $connection, ?int $except = null): float
    {
        $queued = (float) WhatsappMessage::query()
            ->join('whatsapp_broadcasts', 'whatsapp_broadcasts.id', '=', 'whatsapp_messages.broadcast_id')
            ->where('whatsapp_messages.store_id', $connection->store_id)->where('whatsapp_messages.status', 'queued')
            ->when($except, fn ($q) => $q->where('whatsapp_broadcasts.id', '!=', $except))
            ->sum('whatsapp_broadcasts.rate');
        $scheduled = (float) WhatsappBroadcast::where('connection_id', $connection->id)->where('status', 'scheduled')
            ->when($except, fn ($q) => $q->whereKeyNot($except))->sum('estimated_cost');

        return $queued + $scheduled;
    }

    /** @return array{budget: ?float, spent: float, reserved: float, available: ?float} */
    public function summary(WhatsappConnection $connection, ?int $except = null): array
    {
        $budget = $connection->monthly_budget !== null ? (float) $connection->monthly_budget : null;
        $spent = $this->spent($connection);
        $reserved = $this->reserved($connection, $except);

        return [
            'budget'    => $budget,
            'spent'     => round($spent, 2),
            'reserved'  => round($reserved, 2),
            'available' => $budget !== null ? round(max(0, $budget - $spent - $reserved), 2) : null,
        ];
    }

    /** Broadcast messages stop leaving once Meta's bills reach the cap. */
    public function exceeded(WhatsappConnection $connection): bool
    {
        return $connection->monthly_budget !== null && $this->spent($connection) >= (float) $connection->monthly_budget;
    }

    /** Customers the store may still write first to in the next 24 h; null = no limit. */
    public function limit(WhatsappConnection $connection): ?int
    {
        return $connection->messaging_limit_tier === 'TIER_UNLIMITED' ? null : ($connection->messaging_limit ?? self::DEFAULT_LIMIT);
    }

    /** Distinct customers who got a template in the last 24 h (conservative: Meta skips open windows). */
    public function limitUsed(WhatsappConnection $connection): int
    {
        return WhatsappMessage::query()
            ->join('whatsapp_conversations', 'whatsapp_conversations.id', '=', 'whatsapp_messages.conversation_id')
            ->where('whatsapp_conversations.connection_id', $connection->id)
            ->where('whatsapp_messages.direction', 'out')->where('whatsapp_messages.type', 'template')
            ->where('whatsapp_messages.status', '!=', 'failed')
            ->where('whatsapp_messages.created_at', '>=', now()->subDay())
            ->distinct()->count('whatsapp_messages.conversation_id');
    }

    /**
     * "TIER_250" → 250, "TIER_2K" → 2000, "TIER_UNLIMITED" → unlimited. Meta's answer is read
     * loosely (a string or an object holding the tier).
     *
     * @return array{0: ?string, 1: ?int} tier, limit (null limit = unlimited or unknown)
     */
    public static function parseTier(mixed $value): array
    {
        if (!preg_match('/TIER_(UNLIMITED|\d+K?)/i', is_string($value) ? $value : (string) json_encode($value), $m)) {
            return [null, null];
        }
        $tier = strtoupper($m[1]);
        if ($tier === 'UNLIMITED') {
            return ['TIER_UNLIMITED', null];
        }

        return ['TIER_' . $tier, str_ends_with($tier, 'K') ? (int) $tier * 1000 : (int) $tier];
    }
}
