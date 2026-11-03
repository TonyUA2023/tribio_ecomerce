<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A business in progress with a customer or prospect. See DealService. */
class Deal extends Model
{
    /** Open stages, in pipeline order. */
    public const OPEN_STAGES = [
        'nuevo'      => 'Nuevo',
        'contactado' => 'Contactado',
        'cotizado'   => 'Cotizado',
        'negociando' => 'Negociando',
    ];

    public const CLOSED_STAGES = [
        'ganada'  => 'Ganada',
        'perdida' => 'Perdida',
    ];

    public const LOST_REASONS = [
        'precio'       => 'Le pareció caro',
        'otra_opcion'  => 'Eligió otra opción',
        'no_respondio' => 'Dejó de responder',
        'no_momento'   => 'No era el momento',
        'sin_stock'    => 'No teníamos lo que buscaba',
        'otro'         => 'Otro motivo',
    ];

    protected $fillable = [
        'store_id', 'contact_id', 'user_id', 'title', 'stage', 'amount', 'expected_close_at', 'notes',
        'lost_reason', 'lost_note', 'order_id', 'closed_at',
    ];

    protected $casts = [
        'amount'            => 'decimal:2',
        'expected_close_at' => 'date',
        'closed_at'         => 'datetime',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('stage', array_keys(self::OPEN_STAGES));
    }

    public function isOpen(): bool
    {
        return isset(self::OPEN_STAGES[$this->stage]);
    }

    public function getStageLabelAttribute(): string
    {
        return self::OPEN_STAGES[$this->stage] ?? self::CLOSED_STAGES[$this->stage] ?? $this->stage;
    }

    public function getStageBadgeAttribute(): string
    {
        return match ($this->stage) {
            'ganada'  => 'badge-green',
            'perdida' => 'badge-red',
            'cotizado', 'negociando' => 'badge-gold',
            default   => 'badge-blue',
        };
    }

    /** An open deal past its expected close date. */
    public function isLate(): bool
    {
        return $this->isOpen() && $this->expected_close_at && $this->expected_close_at->lt(now()->startOfDay());
    }
}
