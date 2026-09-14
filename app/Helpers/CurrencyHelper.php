<?php

namespace App\Helpers;

class CurrencyHelper
{
    /**
     * Catálogo completo de países soportados y su configuración de divisa.
     */
    public static function supportedCountries(): array
    {
        return [
            'PE' => [
                'code'     => 'PE',
                'name'     => 'Perú',
                'currency' => 'PEN',
                'symbol'   => 'S/',
                'flag'     => '🇵🇪',
                'flag_url' => '/images/flags/pe.svg',
                'decimals' => 2,
            ],
            'US' => [
                'code'     => 'US',
                'name'     => 'Estados Unidos',
                'currency' => 'USD',
                'symbol'   => '$',
                'flag'     => '🇺🇸',
                'flag_url' => '/images/flags/us.svg',
                'decimals' => 2,
            ],
            'ES' => [
                'code'     => 'ES',
                'name'     => 'España',
                'currency' => 'EUR',
                'symbol'   => '€',
                'flag'     => '🇪🇸',
                'flag_url' => '/images/flags/es.svg',
                'decimals' => 2,
            ],
            'MX' => [
                'code'     => 'MX',
                'name'     => 'México',
                'currency' => 'MXN',
                'symbol'   => '$',
                'flag'     => '🇲🇽',
                'flag_url' => '/images/flags/mx.svg',
                'decimals' => 2,
            ],
            'CO' => [
                'code'     => 'CO',
                'name'     => 'Colombia',
                'currency' => 'COP',
                'symbol'   => '$',
                'flag'     => '🇨🇴',
                'flag_url' => '/images/flags/co.svg',
                'decimals' => 0,
            ],
            'EC' => [
                'code'     => 'EC',
                'name'     => 'Ecuador',
                'currency' => 'USD',
                'symbol'   => '$',
                'flag'     => '🇪🇨',
                'flag_url' => '/images/flags/ec.svg',
                'decimals' => 2,
            ],
            'CL' => [
                'code'     => 'CL',
                'name'     => 'Chile',
                'currency' => 'CLP',
                'symbol'   => '$',
                'flag'     => '🇨🇱',
                'flag_url' => '/images/flags/cl.svg',
                'decimals' => 0,
            ],
            'AR' => [
                'code'     => 'AR',
                'name'     => 'Argentina',
                'currency' => 'ARS',
                'symbol'   => '$',
                'flag'     => '🇦🇷',
                'flag_url' => '/images/flags/ar.svg',
                'decimals' => 0,
            ],
        ];
    }

    /**
     * Monedas soportadas únicas.
     */
    public static function supportedCurrencies(): array
    {
        return ['PEN', 'USD', 'EUR', 'MXN', 'COP', 'CLP', 'ARS'];
    }

    /**
     * Obtener país actual desde cookie o parámetro.
     */
    public static function currentCountry(): string
    {
        if (request()->has('country')) {
            $c = strtoupper(trim((string) request()->query('country')));
            if (array_key_exists($c, static::supportedCountries())) {
                return $c;
            }
        }

        $cookieCountry = request()->cookie('user_country');
        if ($cookieCountry) {
            $c = strtoupper(trim((string) $cookieCountry));
            if (array_key_exists($c, static::supportedCountries())) {
                return $c;
            }
        }

        return 'PE';
    }

    /**
     * Retorna el código de la moneda actual ('PEN', 'USD', 'EUR', 'MXN', 'COP', 'CLP', 'ARS').
     */
    public static function currentCurrency(): string
    {
        // 1. Parámetro query explícito ?currency=usd
        if (request()->has('currency')) {
            $q = strtoupper(trim((string) request()->query('currency')));
            if (in_array($q, static::supportedCurrencies())) {
                return $q;
            }
        }

        // 2. Cookie store_currency
        $cookieCurr = request()->cookie('store_currency');
        if ($cookieCurr) {
            $cookieCurr = strtoupper(trim((string) $cookieCurr));
            if (in_array($cookieCurr, static::supportedCurrencies())) {
                return $cookieCurr;
            }
        }

        // 3. Derivar del país seleccionado
        $country = static::currentCountry();
        $supported = static::supportedCountries();
        if (isset($supported[$country]['currency'])) {
            return $supported[$country]['currency'];
        }

        return 'PEN';
    }

    /**
     * Retorna verdadero si la moneda activa es USD.
     */
    public static function isUsd(): bool
    {
        return static::currentCurrency() === 'USD';
    }

    /**
     * Retorna el símbolo de la moneda ('S/', '$', '€').
     */
    public static function symbol(?string $curr = null): string
    {
        $currency = $curr ? strtoupper(trim($curr)) : static::currentCurrency();
        return match ($currency) {
            'PEN' => 'S/',
            'EUR' => '€',
            'USD', 'MXN', 'COP', 'CLP', 'ARS' => '$',
            default => '$',
        };
    }

    /**
     * Retorna el código de moneda ('PEN', 'USD', etc.).
     */
    public static function code(): string
    {
        return static::currentCurrency();
    }

    /**
     * Obtiene los metadatos de un país.
     */
    public static function getCountryInfo(string $countryCode): ?array
    {
        $countryCode = strtoupper(trim($countryCode));
        return static::supportedCountries()[$countryCode] ?? null;
    }

    /**
     * Obtiene la divisa asociada a un país.
     */
    public static function getCurrencyForCountry(string $countryCode): string
    {
        $info = static::getCountryInfo($countryCode);
        return $info['currency'] ?? 'PEN';
    }

    /**
     * Formatea un valor monetario según la moneda.
     */
    public static function format(float $amount, ?string $curr = null): string
    {
        $currency = $curr ? strtoupper(trim($curr)) : static::currentCurrency();
        $decimals = in_array($currency, ['COP', 'CLP', 'ARS']) ? 0 : 2;
        return number_format($amount, $decimals, '.', ',');
    }

    /**
     * Retorna la URL de la bandera oficial en SVG para el país.
     */
    public static function flagUrl(?string $countryCode): string
    {
        $code = strtolower(trim((string) $countryCode));
        if (empty($code)) {
            $code = 'pe';
        }
        return asset("images/flags/{$code}.svg");
    }
}
