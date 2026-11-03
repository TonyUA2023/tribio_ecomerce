<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One shift of a register: opened with a cash float, closed with the counted cash. At close
 * the expected cash, the difference and the money per payment method are frozen.
 */
class RegisterSession extends Model
{
    protected $fillable = [
        'store_id', 'register_id', 'status', 'opened_by', 'closed_by', 'opened_staff_id', 'closed_staff_id', 'opened_at', 'closed_at',
        'opening_float', 'expected_cash', 'counted_cash', 'difference', 'totals', 'notes',
    ];

    protected $casts = [
        'opened_at'     => 'datetime',
        'closed_at'     => 'datetime',
        'opening_float' => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'counted_cash'  => 'decimal:2',
        'difference'    => 'decimal:2',
        'totals'        => 'array',
    ];

    public function register(): BelongsTo
    {
        return $this->belongsTo(Register::class);
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function openerStaff(): BelongsTo
    {
        return $this->belongsTo(StoreStaff::class, 'opened_staff_id');
    }

    public function closerStaff(): BelongsTo
    {
        return $this->belongsTo(StoreStaff::class, 'closed_staff_id');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(OrderPayment::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(RegisterMovement::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
