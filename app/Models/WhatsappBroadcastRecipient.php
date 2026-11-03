<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A customer the broadcast was for: queued (with its message) or skipped with the reason. */
class WhatsappBroadcastRecipient extends Model
{
    protected $fillable = ['broadcast_id', 'contact_id', 'message_id', 'status', 'reason'];

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(WhatsappBroadcast::class, 'broadcast_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(WhatsappMessage::class, 'message_id');
    }
}
