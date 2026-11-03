<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** A cash register of the store ("Caja principal", "Caja 2"). At most one open session at a time. */
class Register extends Model
{
    protected $fillable = ['store_id', 'name', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(RegisterSession::class);
    }

    public function openSession(): HasOne
    {
        return $this->hasOne(RegisterSession::class)->where('status', 'open')->latestOfMany('opened_at');
    }
}
