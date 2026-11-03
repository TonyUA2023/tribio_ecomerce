<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One customer's chat with the store's number. Free-form replies are only allowed while
 * the customer service window is open: 24 h since the customer's last message.
 */
class WhatsappConversation extends Model
{
    public const WINDOW_HOURS = 24;

    protected $fillable = [
        'store_id', 'connection_id', 'contact_id', 'wa_user_id', 'wa_id', 'profile_name',
        'last_inbound_at', 'last_message_at', 'last_message_preview', 'unread_count',
    ];

    protected $casts = [
        'last_inbound_at' => 'datetime',
        'last_message_at' => 'datetime',
        'unread_count'    => 'integer',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(WhatsappConnection::class, 'connection_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsappMessage::class, 'conversation_id');
    }

    public function windowClosesAt(): ?Carbon
    {
        return $this->last_inbound_at?->copy()->addHours(self::WINDOW_HOURS);
    }

    public function windowOpen(): bool
    {
        return $this->windowClosesAt()?->isFuture() ?? false;
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->contact?->name ?: $this->profile_name ?: ($this->wa_id ? '+' . $this->wa_id : 'Cliente de WhatsApp');
    }
}
