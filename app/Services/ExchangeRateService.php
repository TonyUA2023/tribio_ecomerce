<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExchangeRateService
{
    /**
     * Cache key for exchange rates.
     */
    const CACHE_KEY = 'tribio_exchange_rates_pen';

    /**
     * Cache duration in seconds (24 hours).
     */
    const CACHE_TTL = 86400;

    /**
     * Fallback rates based on 1 PEN in case external API fails or is unreachable.
     */
    protected array $fallbackRates = [
        'PEN' => 1.0,
        'USD' => 0.268,   // ~3.73 PEN per USD
        'EUR' => 0.252,   // ~3.97 PEN per EUR
        'MXN' => 5.35,    // ~0.187 PEN per MXN
        'COP' => 1085.0,  // ~0.00092 PEN per COP
        'CLP' => 255.0,   // ~0.0039 PEN per CLP
        'ARS' => 365.0,   // ~0.0027 PEN per ARS
    ];

    /**
     * Get all active exchange rates with base PEN.
     *
     * @return array<string, float>
     */
    public function getRates(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return $this->fetchRatesFromApi();
        });
    }

    /**
     * Fetch latest rates from open.er-api.com based on PEN.
     */
    public function fetchRatesFromApi(): array
    {
        try {
            $response = Http::timeout(6)->get('https://open.er-api.com/v6/latest/PEN');
            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['result']) && $data['result'] === 'success' && isset($data['rates'])) {
                    $apiRates = $data['rates'];
                    $merged = $this->fallbackRates;
                    foreach (array_keys($this->fallbackRates) as $cur) {
                        if (isset($apiRates[$cur]) && is_numeric($apiRates[$cur]) && $apiRates[$cur] > 0) {
                            $merged[$cur] = (float) $apiRates[$cur];
                        }
                    }
                    return $merged;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('ExchangeRateService failed to fetch live rates: ' . $e->getMessage());
        }

        return $this->fallbackRates;
    }

    /**
     * Force refresh the cached exchange rates.
     */
    public function refreshRates(): array
    {
        Cache::forget(self::CACHE_KEY);
        $fresh = $this->fetchRatesFromApi();
        Cache::put(self::CACHE_KEY, $fresh, self::CACHE_TTL);
        return $fresh;
    }

    /**
     * Get rate for a specific currency relative to 1 PEN.
     */
    public function getRate(string $currency): float
    {
        $currency = strtoupper(trim($currency));
        $rates = $this->getRates();
        return $rates[$currency] ?? ($this->fallbackRates[$currency] ?? 1.0);
    }

    /**
     * Convert an amount from one currency to another.
     */
    public function convert(float $amount, string $from = 'PEN', string $to = 'USD'): float
    {
        $from = strtoupper(trim($from));
        $to   = strtoupper(trim($to));

        if ($from === $to || $amount <= 0) {
            return $amount;
        }

        $rates = $this->getRates();
        $rateFrom = $rates[$from] ?? ($this->fallbackRates[$from] ?? 1.0);
        $rateTo   = $rates[$to] ?? ($this->fallbackRates[$to] ?? 1.0);

        // Convert $from to PEN base first
        $amountInPen = $from === 'PEN' ? $amount : ($amount / $rateFrom);

        // Convert PEN to $to
        $converted = $to === 'PEN' ? $amountInPen : ($amountInPen * $rateTo);

        // High-denomination Latin American currencies: round to neat clean whole number
        if (in_array($to, ['COP', 'CLP', 'ARS'])) {
            return (float) round($converted);
        }

        return (float) round($converted, 2);
    }

    /**
     * Formats an amount according to currency rules.
     */
    public function format(float $amount, string $currency): string
    {
        $currency = strtoupper(trim($currency));
        if (in_array($currency, ['COP', 'CLP', 'ARS'])) {
            return number_format(round($amount), 0, '', ',');
        }
        return number_format($amount, 2, '.', ',');
    }
}
