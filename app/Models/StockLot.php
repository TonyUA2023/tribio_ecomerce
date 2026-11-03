<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One receipt of a product: its cost, expiry and how much of it is still on hand. */
class StockLot extends Model
{
    public const EXPIRING_DAYS = 30;

    protected $fillable = [
        'store_id', 'product_id', 'code', 'expires_at', 'unit_cost',
        'quantity_received', 'quantity_remaining', 'origin', 'received_at',
    ];

    protected $casts = [
        'expires_at'  => 'date',
        'received_at' => 'datetime',
        'unit_cost'   => 'decimal:4',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** First expiring first (FEFO); lots without a date after those, oldest first (FIFO). */
    public function scopeConsumptionOrder(Builder $query): Builder
    {
        return $query->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expires_at')->orderBy('received_at')->orderBy('id');
    }

    public function scopeOnHand(Builder $query): Builder
    {
        return $query->where('quantity_remaining', '>', 0);
    }

    public function getExpiryStateAttribute(): ?string
    {
        if (!$this->expires_at) {
            return null;
        }
        if ($this->expires_at->isPast() && !$this->expires_at->isToday()) {
            return 'expired';
        }

        return $this->expires_at->lte(now()->addDays(self::EXPIRING_DAYS)) ? 'expiring' : 'ok';
    }

    public function getOriginLabelAttribute(): string
    {
        return match ($this->origin) {
            'opening'    => 'Saldo inicial',
            'adjustment' => 'Ajuste',
            default      => 'Entrada',
        };
    }
}
