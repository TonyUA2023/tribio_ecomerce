<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A physical stock count: open → applied (or cancelled). See StockCountService. */
class StockCount extends Model
{
    public const STATUSES = [
        'open'      => 'En curso',
        'applied'   => 'Aplicado',
        'cancelled' => 'Cancelado',
    ];

    protected $fillable = ['store_id', 'user_id', 'number', 'status', 'category_id', 'blind', 'notes', 'applied_at', 'variance_value'];

    protected $casts = [
        'blind'          => 'boolean',
        'applied_at'     => 'datetime',
        'variance_value' => 'decimal:2',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(StockCountItem::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'applied'   => 'badge-green',
            'cancelled' => 'badge-red',
            default     => 'badge-blue',
        };
    }
}
