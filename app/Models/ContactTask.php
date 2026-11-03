<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A follow-up with a date: call back, remind to reorder, collect a payment. */
class ContactTask extends Model
{
    public const KINDS = [
        'follow_up' => 'Seguimiento',
        'reorder'   => 'Recompra',
        'payment'   => 'Cobranza',
        'other'     => 'Otro',
    ];

    protected $fillable = ['store_id', 'contact_id', 'user_id', 'product_id', 'kind', 'title', 'due_at', 'done_at'];

    protected $casts = [
        'due_at'  => 'datetime',
        'done_at' => 'datetime',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('done_at');
    }

    public function isOverdue(): bool
    {
        return !$this->done_at && $this->due_at->lt(now()->startOfDay());
    }

    /**
     * WhatsApp link to the task's customer; a reorder reminder carries the message ready
     * (the owner can still edit it in WhatsApp before sending).
     */
    public function getWhatsappUrlAttribute(): ?string
    {
        $url = $this->contact?->whatsapp_url;
        if (!$url) {
            return null;
        }
        if ($this->kind !== 'reorder' || !$this->product) {
            return $url;
        }

        $templates = app(\App\Services\Crm\MessageTemplates::class);
        $template = $templates->find($this->contact->store, 'reorder');

        return $templates->whatsappUrl($this->contact, $templates->render($template['body'], $this->contact, $this->product));
    }

    /** "mié. 5 nov., 10:00" — with the year when it is not this year. */
    public function getDueLabelAttribute(): string
    {
        return $this->due_at->locale('es')->isoFormat($this->due_at->isCurrentYear() ? 'ddd D MMM, HH:mm' : 'ddd D MMM YYYY, HH:mm');
    }

    public function getKindLabelAttribute(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
    }
}
