<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Cash put into or taken out of the drawer outside a sale (change float, a supplier paid in cash…). */
class RegisterMovement extends Model
{
    protected $fillable = ['register_session_id', 'type', 'amount', 'reason', 'user_id', 'staff_id', 'approved_staff_id'];

    protected $casts = ['amount' => 'decimal:2'];

    public function session(): BelongsTo
    {
        return $this->belongsTo(RegisterSession::class, 'register_session_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(StoreStaff::class, 'staff_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
