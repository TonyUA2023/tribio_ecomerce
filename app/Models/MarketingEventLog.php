<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One server-side event sent (or attempted) to an ad platform. The unique
 * (store, provider, event_name, event_id) index is what guarantees a purchase is
 * reported once, however many times the order is saved or a job is retried.
 */
class MarketingEventLog extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    /** Not sent on purpose (the buyer declined cookies) — never retried. */
    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'store_id', 'provider', 'event_name', 'event_id', 'order_id', 'status',
        'attempts', 'is_test', 'response', 'error', 'sent_at',
    ];

    protected $casts = [
        'is_test'  => 'boolean',
        'response' => 'array',
        'attempts' => 'integer',
        'sent_at'  => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_SENT    => 'badge-green',
            self::STATUS_FAILED  => 'badge-red',
            self::STATUS_SKIPPED => 'badge-gray',
            default              => 'badge-gold',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_SENT    => 'Enviado',
            self::STATUS_FAILED  => 'Falló',
            self::STATUS_SKIPPED => 'Sin permiso de cookies',
            default              => 'En cola',
        };
    }
}
