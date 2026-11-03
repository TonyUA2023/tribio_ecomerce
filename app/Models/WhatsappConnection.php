<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A store's WhatsApp Cloud API number (ERP Fase 3a). Lives in its own table so the
 * secrets never travel with the Store the mobile app receives. Meta calls
 * /webhooks/whatsapp/{webhook_key}; the app secret signs every event.
 */
class WhatsappConnection extends Model
{
    public const STATUSES = [
        'pending'      => 'Pendiente',
        'connected'    => 'Conectado',
        'error'        => 'Con error',
        'disconnected' => 'Desconectado',
    ];

    protected $fillable = [
        'store_id', 'waba_id', 'phone_number_id', 'display_phone_number', 'verified_name', 'quality_rating',
        'access_token', 'app_secret', 'verify_token', 'webhook_key', 'status', 'last_error', 'connected_at', 'last_webhook_at',
    ];

    protected $hidden = ['access_token', 'app_secret', 'verify_token', 'webhook_key'];

    protected $casts = [
        'access_token'    => 'encrypted',
        'app_secret'      => 'encrypted',
        'connected_at'    => 'datetime',
        'last_webhook_at' => 'datetime',
        'monthly_budget'  => 'decimal:2',
        'rates'           => 'array',
    ];

    /** Meta billing categories priced like another one (status webhook `pricing.category`). */
    private const RATE_ALIASES = ['marketing_lite' => 'marketing', 'authentication-international' => 'authentication', 'authentication_international' => 'authentication'];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(WhatsappConversation::class, 'connection_id');
    }

    public function isConnected(): bool
    {
        return $this->status === 'connected';
    }

    public function webhookUrl(): string
    {
        return route('webhooks.whatsapp.receive', $this->webhook_key);
    }

    /**
     * Price in PEN of one delivered message of a Meta billing category: the store's own
     * rate when set (from its Meta invoice), else Tribio's reference rate.
     */
    public function rate(string $category): float
    {
        $category = self::RATE_ALIASES[strtolower($category)] ?? strtolower($category);
        if ($category === 'referral_conversion') {
            return 0.0; // free entry point (Click-to-WhatsApp ads)
        }
        $own = $this->rates[$category] ?? null;

        return (float) (is_numeric($own) ? $own : config("services.whatsapp.rates.{$category}", 0));
    }

    /** "hasta 250 clientes cada 24 h", or "sin límite" (Meta's limit to write first). */
    public function getMessagingLimitLabelAttribute(): string
    {
        if ($this->messaging_limit_tier === 'TIER_UNLIMITED') {
            return 'sin límite';
        }

        return 'hasta ' . number_format($this->messaging_limit ?? 250) . ' clientes cada 24 h' . ($this->messaging_limit_tier ? '' : ' (supuesto: Meta aún no lo informó)');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
