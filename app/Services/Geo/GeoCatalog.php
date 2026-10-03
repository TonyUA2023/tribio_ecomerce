<?php

namespace App\Services\Geo;

use App\Helpers\CurrencyHelper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Single source of truth for "where does this order ship": countries and their
 * departments/states. States come from the countriesnow.space API (cached), with a
 * built-in list for Peru so the checkout, the dashboard rate form and the shipping
 * resolver keep working when the API is down. Everything that stores or compares a
 * state goes through canonical()/key() so "LIMA", "Lima " and "Departamento de Lima"
 * all hit the same shipping rate.
 */
class GeoCatalog
{
    private const PERU = [
        'Amazonas', 'Áncash', 'Apurímac', 'Arequipa', 'Ayacucho', 'Cajamarca', 'Callao', 'Cusco',
        'Huancavelica', 'Huánuco', 'Ica', 'Junín', 'La Libertad', 'Lambayeque', 'Lima', 'Loreto',
        'Madre de Dios', 'Moquegua', 'Pasco', 'Piura', 'Puno', 'San Martín', 'Tacna', 'Tumbes', 'Ucayali',
    ];

    /** countriesnow.space names that differ from CurrencyHelper's Spanish names. */
    private const API_NAMES = [
        'PE' => 'Peru', 'US' => 'United States', 'ES' => 'Spain', 'MX' => 'Mexico',
        'CO' => 'Colombia', 'EC' => 'Ecuador', 'CL' => 'Chile', 'AR' => 'Argentina',
    ];

    /** @return list<array{code: string, name: string, flag: ?string, currency: string}> */
    public function countries(): array
    {
        return collect(CurrencyHelper::supportedCountries())
            ->map(fn (array $c) => ['code' => $c['code'], 'name' => $c['name'], 'flag' => $c['flag'] ?? null, 'currency' => $c['currency']])
            ->values()->all();
    }

    /** @return list<string> sorted state names; empty when the country has no known subdivisions */
    public function states(string $country): array
    {
        $country = strtoupper(trim($country));
        if (!isset(CurrencyHelper::supportedCountries()[$country])) {
            return [];
        }

        return Cache::remember("geo.states.v1.{$country}", now()->addDays(30), function () use ($country) {
            $fromApi = $this->fetchFromApi($country);

            return $fromApi ?: ($country === 'PE' ? self::PERU : []);
        });
    }

    /** Canonical spelling of a state for a country, or the trimmed input when unknown. */
    public function canonical(string $country, ?string $state): ?string
    {
        $state = trim((string) $state);
        if ($state === '') {
            return null;
        }
        $key = $this->key($state);
        foreach ($this->states($country) as $known) {
            if ($this->key($known) === $key) {
                return $known;
            }
        }

        return $state;
    }

    /** True when $state is a real subdivision of $country (or the country has no list to check against). */
    public function isValidState(string $country, string $state): bool
    {
        $states = $this->states($country);

        return $states === [] || in_array($this->key($state), array_map([$this, 'key'], $states), true);
    }

    /** Accent/case/prefix-insensitive comparison key. */
    public function key(?string $state): string
    {
        $key = Str::of(Str::ascii((string) $state))->lower()->squish()->toString();
        $key = preg_replace('/^(departamento|provincia|estado|region|state|province|prov\.?|dpto\.?)\s+(de(l)?\s+)?/', '', $key);
        $key = preg_replace('/\s+(metropolitana|province|department|state)$/', '', $key);

        return trim($key);
    }

    /** @return list<string> */
    private function fetchFromApi(string $country): array
    {
        try {
            $res = Http::timeout(6)->acceptJson()->post('https://countriesnow.space/api/v0.1/countries/states', ['country' => self::API_NAMES[$country] ?? $country]);
            if (!$res->ok() || $res->json('error')) {
                return [];
            }
            $names = collect($res->json('data.states', []))->pluck('name')
                ->map(fn ($n) => trim(preg_replace('/\s+(Department|Province|State|Region)$/i', '', (string) $n)))
                ->filter()->unique()->sort(SORT_LOCALE_STRING)->values()->all();

            return count($names) >= 2 ? $names : [];
        } catch (\Throwable) {
            return [];
        }
    }
}
