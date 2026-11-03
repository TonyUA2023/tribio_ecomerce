<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Goods a customer brought back from a counter sale, with its internal credit note number
 * (ERP Fase 4c). What it was worth first lowers the customer's debt; the rest is money given back.
 */
class OrderReturn extends Model
{
    protected $fillable = [
        'store_id', 'order_id', 'number', 'user_id', 'register_session_id', 'reason', 'items', 'total', 'debt_reduced', 'refunded', 'method', 'staff_id', 'approved_staff_id',
    ];

    protected $casts = [
        'items'        => 'array',
        'total'        => 'decimal:2',
        'debt_reduced' => 'decimal:2',
        'refunded'     => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function registerSession(): BelongsTo
    {
        return $this->belongsTo(RegisterSession::class);
    }
}
