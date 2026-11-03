<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One installment of a credit sale (ERP Fase 4b). Whether it is paid is derived from the order's ledger. */
class OrderInstallment extends Model
{
    protected $fillable = ['order_id', 'number', 'due_date', 'amount'];

    protected $casts = [
        'due_date' => 'date',
        'amount'   => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
