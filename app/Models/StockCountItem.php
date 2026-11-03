<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockCountItem extends Model
{
    protected $fillable = ['stock_count_id', 'product_id', 'expected_qty', 'counted_qty', 'unit_cost', 'counted_at'];

    protected $casts = [
        'unit_cost'  => 'decimal:4',
        'counted_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /** Counted minus what the system said when it was counted; null while not counted. */
    public function getVarianceAttribute(): ?int
    {
        return $this->counted_qty === null ? null : (int) $this->counted_qty - (int) $this->expected_qty;
    }

    public function getVarianceValueAttribute(): ?float
    {
        return $this->variance === null || $this->unit_cost === null ? null : round($this->variance * (float) $this->unit_cost, 2);
    }
}
