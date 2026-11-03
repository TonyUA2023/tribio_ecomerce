<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A template as Meta knows it (Fase 3b). Only APPROVED ones whose parts Tribio can fill
 * (text body with variables, static header/footer/buttons) can be sent.
 */
class WhatsappTemplate extends Model
{
    public const CATEGORIES = [
        'UTILITY'        => 'Utilidad',
        'MARKETING'      => 'Marketing',
        'AUTHENTICATION' => 'Autenticación',
    ];

    public const STATUSES = [
        'APPROVED'         => ['Aprobada', 'badge-green'],
        'PENDING'          => ['En revisión', 'badge-yellow'],
        'IN_APPEAL'        => ['En apelación', 'badge-yellow'],
        'REJECTED'         => ['Rechazada', 'badge-red'],
        'PAUSED'           => ['Pausada por Meta', 'badge-red'],
        'DISABLED'         => ['Desactivada por Meta', 'badge-red'],
        'LIMIT_EXCEEDED'   => ['Límite de plantillas', 'badge-red'],
        'PENDING_DELETION' => ['Eliminándose', 'badge-gray'],
        'DELETED'          => ['Eliminada', 'badge-gray'],
    ];

    protected $fillable = [
        'store_id', 'connection_id', 'meta_id', 'name', 'language', 'category', 'status', 'body',
        'components', 'params', 'source_key', 'rejected_reason', 'synced_at',
    ];

    protected $casts = [
        'components' => 'array',
        'params'     => 'array',
        'synced_at'  => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(WhatsappConnection::class, 'connection_id');
    }

    /**
     * The body's variables in order of first appearance: "1", "2"… (positional) or names.
     *
     * @return list<string>
     */
    public function variables(): array
    {
        preg_match_all('/\{\{\s*([a-z0-9_]+)\s*\}\}/', $this->body, $matches);

        return array_values(array_unique($matches[1]));
    }

    public function isNamed(): bool
    {
        return collect($this->variables())->contains(fn ($v) => !ctype_digit($v));
    }

    /** Approved, and nothing but the body needs data (media headers, dynamic buttons… are not supported yet). */
    public function isSendable(): bool
    {
        return $this->status === 'APPROVED' && $this->category !== 'AUTHENTICATION' && $this->unsupportedReason() === null;
    }

    public function unsupportedReason(): ?string
    {
        foreach ($this->components ?? [] as $component) {
            $type = strtoupper($component['type'] ?? '');
            if ($type === 'HEADER' && (strtoupper($component['format'] ?? 'TEXT') !== 'TEXT' || str_contains($component['text'] ?? '', '{{'))) {
                return 'Tiene un encabezado con imagen o datos variables.';
            }
            if ($type === 'BUTTONS') {
                foreach ($component['buttons'] ?? [] as $button) {
                    if (str_contains(($button['url'] ?? '') . json_encode($button['example'] ?? ''), '{{') || !in_array(strtoupper($button['type'] ?? ''), ['QUICK_REPLY', 'URL', 'PHONE_NUMBER'], true)) {
                        return 'Tiene botones con datos variables.';
                    }
                }
            }
            if (in_array($type, ['CAROUSEL', 'LIMITED_TIME_OFFER'], true)) {
                return 'Es un tipo de plantilla que aún no podemos enviar.';
            }
        }

        return null;
    }

    /** The text the customer receives, for the chat bubble and the preview. */
    public function render(array $values): string
    {
        return preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}/', fn ($m) => $values[$m[1]] ?? $m[0], $this->body);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    public function getStatusBadgeAttribute(): string
    {
        return self::STATUSES[$this->status][1] ?? 'badge-gray';
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }
}
