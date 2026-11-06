<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SUNAT configuration of a business. The SOL password, SOL user and the certificate (with its private
 * key) are encrypted with the app key and never leave the server: screens only learn whether they exist.
 */
class SunatSetting extends Model
{
    public const BETA = 'beta';
    public const PRODUCTION = 'production';

    protected $fillable = [
        'store_id', 'enabled', 'environment', 'factura_mode', 'boleta_mode', 'sol_user', 'sol_password', 'certificate_pem', 'certificate_subject',
        'certificate_valid_to', 'endpoint', 'email_company', 'email_to', 'email_customer', 'max_attempts', 'verified_at',
    ];

    protected $hidden = ['sol_user', 'sol_password', 'certificate_pem'];

    protected $casts = [
        'enabled'              => 'boolean',
        'email_company'        => 'boolean',
        'email_customer'       => 'boolean',
        'sol_user'             => 'encrypted',
        'sol_password'         => 'encrypted',
        'certificate_pem'      => 'encrypted',
        'certificate_valid_to' => 'datetime',
        'verified_at'          => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function isProduction(): bool
    {
        return $this->environment === self::PRODUCTION;
    }

    public function hasCertificate(): bool
    {
        return filled($this->getRawOriginal('certificate_pem'));
    }

    public function hasSolPassword(): bool
    {
        return filled($this->getRawOriginal('sol_password'));
    }

    public function certificateExpired(): bool
    {
        return $this->certificate_valid_to !== null && $this->certificate_valid_to->isPast();
    }
}
