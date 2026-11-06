<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A boleta or factura sent to SUNAT: its signed XML, the receipt (CDR) and where it stands. */
class ElectronicDocument extends Model
{
    public const PENDING = 'pending';
    public const ACCEPTED = 'accepted';
    public const OBSERVED = 'observed';
    public const REJECTED = 'rejected';
    public const ERROR = 'error';

    public const STATUSES = [
        self::PENDING  => ['label' => 'Por enviar',  'badge' => 'badge-gold'],
        self::ACCEPTED => ['label' => 'Aceptado',    'badge' => 'badge-green'],
        self::OBSERVED => ['label' => 'Con observaciones', 'badge' => 'badge-gold'],
        self::REJECTED => ['label' => 'Rechazado',   'badge' => 'badge-red'],
        self::ERROR    => ['label' => 'Sin enviar',  'badge' => 'badge-red'],
    ];

    protected $fillable = [
        'store_id', 'order_id', 'type', 'series', 'number', 'environment', 'status', 'sunat_code', 'sunat_message', 'hash', 'xml', 'cdr_zip',
        'attempts', 'sent_at', 'last_attempt_at', 'emailed_at',
    ];

    protected $hidden = ['xml', 'cdr_zip'];

    protected $casts = ['sent_at' => 'datetime', 'last_attempt_at' => 'datetime', 'emailed_at' => 'datetime', 'number' => 'integer', 'attempts' => 'integer'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function code(): string
    {
        return sprintf('%s-%08d', $this->series, $this->number);
    }

    public function label(): string
    {
        return self::STATUSES[$this->status]['label'] ?? $this->status;
    }

    public function badge(): string
    {
        return self::STATUSES[$this->status]['badge'] ?? 'badge-gold';
    }

    /** Sent and received by SUNAT (accepted, possibly with observations). */
    public function isDelivered(): bool
    {
        return in_array($this->status, [self::ACCEPTED, self::OBSERVED], true);
    }

    /** Can be tried again: it never reached SUNAT. A rejection needs a corrected document, not a retry. */
    public function canRetry(): bool
    {
        return in_array($this->status, [self::PENDING, self::ERROR], true);
    }
}
