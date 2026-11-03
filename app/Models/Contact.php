<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A store's customer: one row per person per store, linked to their orders.
 * Always query through $store->contacts() — there is no global tenant scope.
 */
class Contact extends Model
{
    public const INACTIVE_AFTER_DAYS = 90;

    /** Lifecycle stages (Fase 2, computed by LifecycleClassifier) with their badge class. */
    public const STAGES = [
        'lead'       => ['label' => 'Prospecto', 'badge' => 'badge-gray'],
        'nuevo'      => ['label' => 'Cliente nuevo', 'badge' => 'badge-blue'],
        'recurrente' => ['label' => 'Recurrente', 'badge' => 'badge-green'],
        'vip'        => ['label' => 'VIP', 'badge' => 'badge-purple'],
        'en_riesgo'  => ['label' => 'En riesgo', 'badge' => 'badge-gold'],
        'inactivo'   => ['label' => 'Inactivo', 'badge' => 'badge-red'],
    ];

    public const RELATIONSHIPS = [
        'consumidor'   => 'Consumidor final',
        'mayorista'    => 'Mayorista',
        'distribuidor' => 'Distribuidor / socio',
    ];

    protected $fillable = [
        'store_id', 'name', 'phone', 'email', 'document_type', 'document_number', 'city', 'birthday',
        'source', 'relationship', 'notes', 'tags', 'accepts_marketing', 'marketing_consent_at',
    ];

    protected $casts = [
        'tags'                 => 'array',
        'birthday'             => 'date',
        'stage_changed_at'     => 'datetime',
        'accepts_marketing'    => 'boolean',
        'marketing_consent_at' => 'datetime',
        'total_spent'          => 'decimal:2',
        'credit_limit'         => 'decimal:2',
        'first_order_at'       => 'datetime',
        'last_order_at'        => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ContactEvent::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ContactTask::class);
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    public function whatsappConversations(): HasMany
    {
        return $this->hasMany(WhatsappConversation::class);
    }

    public function getStageLabelAttribute(): ?string
    {
        return self::STAGES[$this->lifecycle_stage]['label'] ?? null;
    }

    public function getStageBadgeAttribute(): string
    {
        return self::STAGES[$this->lifecycle_stage]['badge'] ?? 'badge-gray';
    }

    /** "51987654321" → "+51 987 654 321" (Peru); other numbers just get the "+". */
    public function getPhoneDisplayAttribute(): ?string
    {
        if (!$this->phone) {
            return null;
        }

        return preg_match('/^51(\d{3})(\d{3})(\d{3})$/', $this->phone, $m)
            ? "+51 {$m[1]} {$m[2]} {$m[3]}"
            : '+' . $this->phone;
    }

    public function getWhatsappUrlAttribute(): ?string
    {
        return $this->phone ? 'https://wa.me/' . $this->phone : null;
    }

    /** Purchase segments of Clientes (ContactController::SEGMENTS), also used by WhatsApp broadcasts. */
    public function scopeInSegment(Builder $query, ?string $segment): Builder
    {
        return match ($segment) {
            'recurrentes' => $query->where('orders_count', '>=', 2),
            'una_compra'  => $query->where('orders_count', 1),
            'inactivos'   => $query->where('last_order_at', '<', now()->subDays(self::INACTIVE_AFTER_DAYS)),
            default       => $query,
        };
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }
        $digits = preg_replace('/\D+/', '', $term);

        return $query->where(function (Builder $q) use ($term, $digits) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', '%' . mb_strtolower($term) . '%')
                ->orWhere('document_number', 'like', "%{$term}%");
            if (strlen($digits) >= 4) {
                $q->orWhere('phone', 'like', "%{$digits}%");
            }
        });
    }
}
