<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A person who works the register (ERP Fase 4d): identified by a PIN on a device in
 * "modo caja", never a login of their own. Managers ("encargado") may approve what a
 * cashier cannot do alone.
 */
class StoreStaff extends Model
{
    protected $table = 'store_staff';

    protected $fillable = ['store_id', 'name', 'role', 'pin_hash', 'is_active', 'last_used_at'];

    protected $hidden = ['pin_hash'];

    protected $casts = [
        'is_active'    => 'boolean',
        'last_used_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function isManager(): bool
    {
        return $this->role === 'encargado';
    }
}
