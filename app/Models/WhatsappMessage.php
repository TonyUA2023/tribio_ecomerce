<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A message in or out. Outgoing ones walk queued → sent → delivered → read, or failed. */
class WhatsappMessage extends Model
{
    public const STATUS_ORDER = ['queued' => 0, 'sent' => 1, 'delivered' => 2, 'read' => 3];

    public const STATUS_LABELS = [
        'received'  => 'Recibido',
        'queued'    => 'Enviando…',
        'sent'      => 'Enviado',
        'delivered' => 'Entregado',
        'read'      => 'Leído',
        'failed'    => 'No se envió',
    ];

    protected $fillable = [
        'store_id', 'conversation_id', 'direction', 'wamid', 'type', 'body', 'payload', 'status', 'error',
        'user_id', 'wa_timestamp', 'sent_at', 'delivered_at', 'read_at', 'broadcast_id',
    ];

    protected $casts = [
        'payload'      => 'array',
        'wa_timestamp' => 'datetime',
        'sent_at'      => 'datetime',
        'delivered_at' => 'datetime',
        'read_at'      => 'datetime',
        'cost'         => 'decimal:4',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsappConversation::class, 'conversation_id');
    }

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(WhatsappBroadcast::class, 'broadcast_id');
    }

    public function isInbound(): bool
    {
        return $this->direction === 'in';
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }
}
