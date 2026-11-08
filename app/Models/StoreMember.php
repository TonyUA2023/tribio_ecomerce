<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A person of the business team. The owner (stores.user_id) is the global admin; everyone else is
 * invited by e-mail and works with their own Tribio Pass. `admin` opens everything, `seller` only
 * the modules ticked in `modules`.
 */
class StoreMember extends Model
{
    public const ADMIN = 'admin';
    public const SELLER = 'seller';

    protected $fillable = ['store_id', 'user_id', 'email', 'name', 'role', 'modules', 'is_active', 'invited_by', 'accepted_at'];

    protected $casts = [
        'modules'     => 'array',
        'is_active'   => 'boolean',
        'accepted_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ADMIN;
    }
}
