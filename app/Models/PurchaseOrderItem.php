<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id', 'product_id', 'unit_name', 'unit_factor', 'quantity_ordered', 'unit_cost',
        'quantity_received', 'landed_unit_cost', 'lot_code', 'expires_at',
    ];

    protected $casts = [
        'unit_cost'        => 'decimal:4',
        'landed_unit_cost' => 'decimal:4',
        'expires_at'       => 'date',
    ];

    /** "3 cajas ×100" / "12 und" for screens. */
    public function getQuantityLabelAttribute(): string
    {
        $factor = (int) ($this->unit_factor ?: 1);

        return $factor > 1 && $this->unit_name
            ? "{$this->quantity_ordered} {$this->unit_name} ×{$factor}"
            : "{$this->quantity_ordered} und";
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
