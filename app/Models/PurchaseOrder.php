<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A purchase from a supplier: draft → ordered → received (or cancelled). See PurchaseOrderService. */
class PurchaseOrder extends Model
{
    public const STATUSES = [
        'draft'     => 'Borrador',
        'ordered'   => 'Pedida',
        'received'  => 'Recibida',
        'cancelled' => 'Cancelada',
    ];

    protected $fillable = [
        'store_id', 'supplier_id', 'user_id', 'number', 'status', 'expected_at', 'subtotal', 'extra_costs', 'total',
        'notes', 'ordered_at', 'received_at', 'cancelled_at',
    ];

    protected $casts = [
        'expected_at'  => 'date',
        'ordered_at'   => 'datetime',
        'received_at'  => 'datetime',
        'cancelled_at' => 'datetime',
        'subtotal'     => 'decimal:2',
        'extra_costs'  => 'decimal:2',
        'total'        => 'decimal:2',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'ordered'], true);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'ordered'   => 'badge-blue',
            'received'  => 'badge-green',
            'cancelled' => 'badge-red',
            default     => 'badge-gray',
        };
    }
}
