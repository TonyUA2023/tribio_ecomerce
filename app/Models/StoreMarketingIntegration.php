<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A store's connection to an ad platform (today only Meta). Lives in its own table on
 * purpose: Api\StoreController returns the whole Store to the mobile app, so a secret
 * like the Conversions API token must never be a `stores` column.
 */
class StoreMarketingIntegration extends Model
{
    public const PROVIDER_META = 'meta';

    public const CONDITIONS = [
        'new'         => 'Nuevo',
        'refurbished' => 'Reacondicionado',
        'used'        => 'Usado',
    ];

    protected $fillable = [
        'store_id', 'provider', 'is_active', 'pixel_id', 'capi_token', 'test_event_code',
        'domain_verification', 'default_condition',
    ];

    protected $hidden = ['capi_token'];

    protected $casts = [
        'is_active'     => 'boolean',
        'capi_token'    => 'encrypted',
        'last_event_at' => 'datetime',
        'last_error_at' => 'datetime',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function hasPixel(): bool
    {
        return $this->is_active && filled($this->pixel_id);
    }

    public function hasConversionsApi(): bool
    {
        return $this->hasPixel() && filled($this->capi_token);
    }

    /** "EAAB…x9Qz": enough for the owner to recognize which token is saved. */
    public function maskedToken(): ?string
    {
        $token = (string) $this->capi_token;

        return $token === '' ? null : substr($token, 0, 4) . '…' . substr($token, -4);
    }

    public function recordError(string $message): void
    {
        $this->forceFill(['last_error' => mb_substr($message, 0, 1000), 'last_error_at' => now()])->save();
    }

    public function recordSuccess(): void
    {
        $this->forceFill(['last_event_at' => now(), 'last_error' => null, 'last_error_at' => null])->save();
    }
}
