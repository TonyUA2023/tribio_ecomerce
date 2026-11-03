<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Something that happened with a customer: a note, a call, a WhatsApp, a stage change. */
class ContactEvent extends Model
{
    public const TYPES = [
        'note'     => ['label' => 'Nota', 'icon' => '📝'],
        'call'     => ['label' => 'Llamada', 'icon' => '📞'],
        'whatsapp' => ['label' => 'WhatsApp', 'icon' => '💬'],
        'visit'    => ['label' => 'Visita', 'icon' => '🤝'],
        'stage'    => ['label' => 'Cambio de etapa', 'icon' => '🏷️'],
        'task'     => ['label' => 'Seguimiento hecho', 'icon' => '✅'],
        'deal'     => ['label' => 'Oportunidad', 'icon' => '💼'],
        'credit'   => ['label' => 'Fiado y pagos', 'icon' => '🧾'],
    ];

    /** What the owner can log by hand (the others are written by the system). */
    public const MANUAL_TYPES = ['note', 'call', 'whatsapp', 'visit'];

    protected $fillable = ['store_id', 'contact_id', 'user_id', 'type', 'body', 'occurred_at'];

    protected $casts = ['occurred_at' => 'datetime'];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
