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
    private ?array $memo = null;

    private const PERU = [
        'Amazonas', 'Áncash', 'Apurímac', 'Arequipa', 'Ayacucho', 'Cajamarca', 'Callao', 'Cusco',
        'Huancavelica', 'Huánuco', 'Ica', 'Junín', 'La Libertad', 'Lambayeque', 'Lima', 'Loreto',
        'Madre de Dios', 'Moquegua', 'Pasco', 'Piura', 'Puno', 'San Martín', 'Tacna', 'Tumbes', 'Ucayali',
    ];

    private const WORLD_KEY = 'geo.world.v2';

    /**
     * Every country in the world (the same countriesnow.space dataset the buyer's registration
     * form used to read directly), supported-currency countries first, then A–Z.
     *
     * @return list<array{code: string, name: string, flag: string, currency: ?string, supported: bool}>
     */
    public function countries(): array
    {
        $supported = CurrencyHelper::supportedCountries();

        return collect($this->world())
            ->map(fn (array $c, string $code) => [
                'code'      => $code,
                'name'      => $c['name'],
                'flag'      => $this->flag($code),
                'currency'  => $supported[$code]['currency'] ?? null,
                'supported' => isset($supported[$code]),
            ])
            ->sortBy(fn (array $c) => ($c['supported'] ? '0' : '1') . Str::ascii($c['name']))
            ->values()->all();
    }

    public function countryName(string $country): ?string
    {
        return $this->world()[strtoupper($country)]['name'] ?? null;
    }

    public function isValidCountry(string $country): bool
    {
        return isset($this->world()[strtoupper(trim($country))]);
    }

    /** @return list<string> sorted state names; empty when the country has no known subdivisions */
    public function states(string $country): array
    {
        return $this->world()[strtoupper(trim($country))]['states'] ?? [];
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
        $key = preg_replace('/\s+(metropolitana|province|department|state|region)$/', '', $key);

        return trim($key);
    }

    /** @return array<string, array{name: string, states: list<string>}> keyed by ISO alpha-2 */
    private function world(): array
    {
        if ($this->memo !== null) {
            return $this->memo;
        }
        $world = Cache::get(self::WORLD_KEY);
        if (!is_array($world)) {
            $world = $this->fetchWorld();
            // A failed fetch only sticks briefly so the API gets retried soon.
            Cache::put(self::WORLD_KEY, $world['data'], $world['live'] ? now()->addDays(30) : now()->addMinutes(10));
            $world = $world['data'];
        }

        return $this->memo = $world;
    }

    /** @return array{live: bool, data: array<string, array{name: string, states: list<string>}>} */
    private function fetchWorld(): array
    {
        $names = require config_path('geo_countries.php');
        $data = collect($names)->map(fn ($name) => ['name' => $name, 'states' => []])->all();
        $data['PE']['states'] = self::PERU;

        try {
            $res = Http::timeout(15)->acceptJson()->get('https://countriesnow.space/api/v0.1/countries/states');
            if (!$res->ok() || $res->json('error') || !is_array($res->json('data'))) {
                return ['live' => false, 'data' => $data];
            }
            foreach ($res->json('data') as $row) {
                $code = strtoupper((string) ($row['iso2'] ?? ''));
                if (!preg_match('/^[A-Z]{2}$/', $code)) {
                    continue;
                }
                $states = collect($row['states'] ?? [])->pluck('name')
                    ->map(fn ($n) => trim(preg_replace('/\s+(Department|Province|State|Region)$/i', '', (string) $n)))
                    ->filter()->unique()->sort(SORT_LOCALE_STRING)->values()->all();
                $data[$code] = [
                    'name'   => $data[$code]['name'] ?? (string) ($row['name'] ?? $code),
                    // Keep our own list when the API has too little to be useful (e.g. a lone "Peru").
                    'states' => count($states) >= 2 ? $states : ($data[$code]['states'] ?? []),
                ];
            }

            return ['live' => true, 'data' => $data];
        } catch (\Throwable) {
            return ['live' => false, 'data' => $data];
        }
    }

    private function flag(string $code): string
    {
        return collect(str_split($code))->map(fn ($ch) => mb_chr(0x1F1E6 + ord($ch) - 65))->implode('');
    }
}
