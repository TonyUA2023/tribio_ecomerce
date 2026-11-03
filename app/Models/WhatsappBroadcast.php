<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One approved template sent to a segment of customers (ERP Fase 3c). Scheduled or sent at
 * once; its messages leave over time and carry broadcast_id, so delivery and cost roll up here.
 */
class WhatsappBroadcast extends Model
{
    public const STATUSES = [
        'scheduled' => ['Programada', 'badge-blue'],
        'sending'   => ['Enviando', 'badge-yellow'],
        'done'      => ['Enviada', 'badge-green'],
        'cancelled' => ['Cancelada', 'badge-gray'],
        'failed'    => ['No se envió', 'badge-red'],
    ];

    protected $fillable = [
        'store_id', 'connection_id', 'template_id', 'user_id', 'name', 'template_name', 'category', 'audience', 'variables',
        'status', 'recipients_count', 'skipped_count', 'rate', 'estimated_cost', 'error', 'scheduled_at', 'started_at', 'finished_at',
    ];

    protected $casts = [
        'audience'       => 'array',
        'variables'      => 'array',
        'rate'           => 'decimal:4',
        'estimated_cost' => 'decimal:2',
        'scheduled_at'   => 'datetime',
        'started_at'     => 'datetime',
        'finished_at'    => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(WhatsappConnection::class, 'connection_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WhatsappTemplate::class, 'template_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsappMessage::class, 'broadcast_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(WhatsappBroadcastRecipient::class, 'broadcast_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['scheduled', 'sending'], true);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    public function getStatusBadgeAttribute(): string
    {
        return self::STATUSES[$this->status][1] ?? 'badge-gray';
    }
}
