<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    protected $fillable = [
        'store_id',
        'name',
        'email',
        'phone',
        'subject',
        'message',
        'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    public const KIND_COMPLAINT = 'libro';
    public const KIND_SUBSCRIPTION = 'suscripcion';
    public const KIND_MESSAGE = 'mensaje';

    // Subjects the storefront forms send for the virtual Libro de Reclamaciones and the
    // newsletter box (templates' contact page and footer).
    public const COMPLAINT_SUBJECT_PREFIX = 'Libro de Reclamaciones';
    public const SUBSCRIPTION_SUBJECT = 'Suscripción a novedades';

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function getKindAttribute(): string
    {
        $subject = (string) $this->subject;

        return match (true) {
            str_starts_with($subject, self::COMPLAINT_SUBJECT_PREFIX) => self::KIND_COMPLAINT,
            $subject === self::SUBSCRIPTION_SUBJECT => self::KIND_SUBSCRIPTION,
            default => self::KIND_MESSAGE,
        };
    }

    public function scopeOfKind($query, ?string $kind)
    {
        return match ($kind) {
            self::KIND_COMPLAINT => $query->where('subject', 'like', self::COMPLAINT_SUBJECT_PREFIX . '%'),
            self::KIND_SUBSCRIPTION => $query->where('subject', self::SUBSCRIPTION_SUBJECT),
            self::KIND_MESSAGE => $query->where(fn ($q) => $q->whereNull('subject')
                ->orWhere(fn ($q) => $q->where('subject', 'not like', self::COMPLAINT_SUBJECT_PREFIX . '%')->where('subject', '!=', self::SUBSCRIPTION_SUBJECT))),
            default => $query,
        };
    }
}
