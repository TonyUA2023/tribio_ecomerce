<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One signed POST from Meta, kept raw; payload_hash makes retries of the same body a no-op. */
class WhatsappWebhookEvent extends Model
{
    protected $fillable = ['connection_id', 'payload_hash', 'payload', 'attempts', 'error', 'processed_at'];

    protected $casts = [
        'processed_at' => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(WhatsappConnection::class, 'connection_id');
    }
}
