<?php

namespace App\Services\Team;

use App\Models\MemberAccessLog;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * The access record of a business: signing in (login), choosing the business in Tribio Pass (enter)
 * and using the dashboard (visit, at most once every 30 minutes per person so it stays light).
 * Inert until the table exists (a deploy never migrates).
 */
class AccessLog
{
    public const TZ = 'America/Lima';
    private const VISIT_EVERY_MINUTES = 30;

    public static function ready(): bool
    {
        static $ready = null;

        return $ready ??= Schema::hasTable('member_access_logs');
    }

    /** @param 'login'|'enter'|'visit' $type */
    public function record(User $user, Store $store, string $type, ?Request $request = null): void
    {
        if (!self::ready()) {
            return;
        }
        $request ??= request();

        MemberAccessLog::create([
            'store_id' => $store->id,
            'user_id'  => $user->id,
            'type'     => $type,
            'ip'       => $request?->ip(),
            'device'   => $this->device((string) $request?->userAgent()),
        ]);
    }

    /** Dashboard use: one row per person every half hour, however many pages they open. */
    public function visit(User $user, Store $store, ?Request $request = null): void
    {
        if (!self::ready()) {
            return;
        }
        if (!Cache::add("access-visit:{$store->id}:{$user->id}", 1, now()->addMinutes(self::VISIT_EVERY_MINUTES))) {
            return;
        }
        $this->record($user, $store, 'visit', $request);
    }

    /**
     * Per person: last time seen, last sign-in, and on how many of the last 7 days they used it.
     *
     * @param  list<int>  $userIds
     * @return array<int, array{last_at: ?Carbon, last_login_at: ?Carbon, days_7d: int, events_30d: int}>
     */
    public function summary(Store $store, array $userIds): array
    {
        $stats = array_fill_keys($userIds, ['last_at' => null, 'last_login_at' => null, 'days_7d' => 0, 'events_30d' => 0]);
        if (!self::ready() || !$userIds) {
            return $stats;
        }

        $since = now()->subDays(30);
        $weekAgo = now()->subDays(7);
        $days = [];
        MemberAccessLog::where('store_id', $store->id)->whereIn('user_id', $userIds)->where('created_at', '>=', $since)
            ->orderBy('created_at')->get(['user_id', 'type', 'created_at'])
            ->each(function (MemberAccessLog $row) use (&$stats, &$days, $weekAgo) {
                $s = &$stats[$row->user_id];
                $s['last_at'] = $row->created_at;
                $s['events_30d']++;
                if ($row->type === 'login') {
                    $s['last_login_at'] = $row->created_at;
                }
                if ($row->created_at >= $weekAgo) {
                    $days[$row->user_id][$row->created_at->copy()->setTimezone(self::TZ)->toDateString()] = true;
                }
            });
        foreach ($days as $userId => $set) {
            $stats[$userId]['days_7d'] = count($set);
        }

        // People whose last record is older than 30 days still deserve "hace N días".
        $missing = array_keys(array_filter($stats, fn ($s) => $s['last_at'] === null));
        if ($missing) {
            foreach (MemberAccessLog::where('store_id', $store->id)->whereIn('user_id', $missing)->selectRaw('user_id, MAX(created_at) as last_at')->groupBy('user_id')->get() as $row) {
                $stats[$row->user_id]['last_at'] = Carbon::parse($row->last_at);
            }
        }

        return $stats;
    }

    /** @return Collection<int, MemberAccessLog> */
    public function recent(Store $store, ?int $userId = null, int $limit = 30): Collection
    {
        if (!self::ready()) {
            return collect();
        }

        return MemberAccessLog::where('store_id', $store->id)->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->with('user:id,name')->orderByDesc('id')->limit($limit)->get();
    }

    /** "Hoy 14:20", "Ayer 09:05", "Hace 4 días", "Nunca". */
    public static function when(?Carbon $at): string
    {
        if (!$at) {
            return 'Nunca';
        }
        $local = $at->copy()->setTimezone(self::TZ);
        $today = Carbon::now(self::TZ)->startOfDay();
        if ($local >= $today) {
            return 'Hoy ' . $local->format('H:i');
        }
        if ($local >= $today->copy()->subDay()) {
            return 'Ayer ' . $local->format('H:i');
        }
        $days = (int) $local->copy()->startOfDay()->diffInDays($today);

        return 'Hace ' . $days . ($days === 1 ? ' día' : ' días');
    }

    /** today | recent (≤ 7 days) | stale | never */
    public static function health(?Carbon $at): string
    {
        if (!$at) {
            return 'never';
        }
        $local = $at->copy()->setTimezone(self::TZ);
        $today = Carbon::now(self::TZ)->startOfDay();

        return $local >= $today ? 'today' : ($local >= $today->copy()->subDays(7) ? 'recent' : 'stale');
    }

    private function device(string $agent): ?string
    {
        if ($agent === '') {
            return null;
        }
        $kind = preg_match('/Mobile|Android|iPhone|iPad/i', $agent) ? 'Celular' : 'Computadora';
        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') || str_contains($agent, 'Opera') => 'Opera',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Safari/') => 'Safari',
            default => null,
        };

        return $browser ? "{$kind} · {$browser}" : $kind;
    }
}
