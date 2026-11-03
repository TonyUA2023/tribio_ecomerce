<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A quote ("proforma", ERP Fase 4c): the lines and prices offered to a customer, valid until
 * a date. Charged at the register it becomes a counter sale with the quoted prices.
 */
class Quote extends Model
{
    public const STATUSES = [
        'open'      => ['Vigente', 'badge-blue'],
        'expired'   => ['Vencida', 'badge-gray'],
        'sold'      => ['Vendida', 'badge-green'],
        'cancelled' => ['Anulada', 'badge-red'],
    ];

    protected $fillable = [
        'store_id', 'number', 'status', 'contact_id', 'user_id', 'order_id', 'price_list', 'customer_name', 'customer_phone',
        'customer_document', 'items', 'subtotal', 'discount', 'total', 'valid_until', 'notes',
    ];

    protected $casts = [
        'items'       => 'array',
        'subtotal'    => 'decimal:2',
        'discount'    => 'decimal:2',
        'total'       => 'decimal:2',
        'valid_until' => 'date',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** "expired" is an open quote past its date in Peru; it can still be sold if the owner wants. */
    public function getStateAttribute(): string
    {
        return $this->status === 'open' && $this->valid_until->toDateString() < now('America/Lima')->toDateString() ? 'expired' : $this->status;
    }

    public function getStateLabelAttribute(): string
    {
        return self::STATUSES[$this->state][0];
    }

    public function getStateBadgeAttribute(): string
    {
        return self::STATUSES[$this->state][1];
    }
}
