<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A store's connection to an ad platform: one row per provider (Meta, Google). Lives in
 * its own table on purpose: Api\StoreController returns the whole Store to the mobile
 * app, so a secret like the Conversions API token must never be a `stores` column.
 *
 * Columns shared by both providers: is_active, domain_verification (Meta's
 * facebook-domain-verification / Google's google-site-verification code) and
 * default_condition. Meta-only: pixel_id, capi_token, test_event_code. Google-only:
 * measurement_id, ads_conversion_id, ads_conversion_label.
 */
class StoreMarketingIntegration extends Model
{
    public const PROVIDER_META = 'meta';
    public const PROVIDER_GOOGLE = 'google';

    /** Google Shopping: Tribio lists the store in its marketplace account (automatic, no domain needed). */
    public const SHOPPING_VIA_TRIBIO = 'tribio';
    /** Google Shopping: the owner's own Merchant Center account (needs a custom domain). */
    public const SHOPPING_OWN_ACCOUNT = 'own';

    public const CONDITIONS = [
        'new'         => 'Nuevo',
        'refurbished' => 'Reacondicionado',
        'used'        => 'Usado',
    ];

    protected $fillable = [
        'store_id', 'provider', 'is_active', 'pixel_id', 'capi_token', 'test_event_code',
        'domain_verification', 'default_condition',
        'measurement_id', 'ads_conversion_id', 'ads_conversion_label', 'shopping_mode',
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

    /** Google: a GA4 property and/or a Google Ads account to load gtag.js for. */
    public function hasGoogleTag(): bool
    {
        return $this->is_active && (filled($this->measurement_id) || filled($this->ads_conversion_id));
    }

    /** Google Ads "Purchase" conversion: needs both the account id and the conversion label. */
    public function hasAdsConversion(): bool
    {
        return $this->is_active && filled($this->ads_conversion_id) && filled($this->ads_conversion_label);
    }

    public function publishesViaTribio(): bool
    {
        return $this->is_active && $this->shopping_mode === self::SHOPPING_VIA_TRIBIO;
    }

    public function usesOwnMerchantCenter(): bool
    {
        return $this->is_active && $this->shopping_mode === self::SHOPPING_OWN_ACCOUNT;
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
