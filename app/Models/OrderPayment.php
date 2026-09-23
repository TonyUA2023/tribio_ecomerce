<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One confirmed movement of money on an order. Amounts are in the order's currency
 * (a PayPal charge settled in USD is credited here as the order-currency amount it
 * covers). Written only through Order::recordPayment().
 */
class OrderPayment extends Model
{
    public const KIND_FULL = 'full';
    public const KIND_DEPOSIT = 'deposit';
    public const KIND_BALANCE = 'balance';

    public const STATUS_PAID = 'paid';
    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'order_id', 'kind', 'amount', 'currency', 'gateway', 'gateway_ref',
        'status', 'paid_at', 'recorded_by', 'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
