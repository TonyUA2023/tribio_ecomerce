<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A store's WhatsApp message: its edit of a Tribio template (`key`) or its own (no key). */
class MessageTemplate extends Model
{
    protected $fillable = ['store_id', 'key', 'name', 'body', 'is_promo'];

    protected $casts = ['is_promo' => 'boolean'];
}
