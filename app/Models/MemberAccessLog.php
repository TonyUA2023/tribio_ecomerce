<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One sign-in, entry or visit of a person to a business. */
class MemberAccessLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['store_id', 'user_id', 'type', 'ip', 'device'];

    protected $casts = ['created_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
