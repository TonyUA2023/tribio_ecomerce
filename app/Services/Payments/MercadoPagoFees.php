<?php

namespace App\Services\Payments;

/**
 * "Simulador de comisiones" de Mercado Pago: dado lo que se cobra calcula lo que se recibe, y a la
 * inversa. Las tarifas salen de config('payment_fees.mercado_pago') — ver ese archivo. Es solo
 * una ayuda para fijar precios: ningún cobro real usa estos cálculos.
 */
class MercadoPagoFees
{
    /** @return array<string, mixed>|null configuración del país o null si no hay tarifas */
    public function market(string $country = 'PE'): ?array
    {
        $market = config("payment_fees.mercado_pago.{$country}");

        return is_array($market) && !empty($market['methods']) ? $market : null;
    }

    /** Medios y plazos disponibles, para armar los selectores. */
    public function options(string $country = 'PE'): array
    {
        $market = $this->market($country);
        if (!$market) {
            return [];
        }

        return collect($market['methods'])->map(fn ($method, $key) => [
            'key' => $key,
            'label' => $method['label'],
            'release' => collect($method['release'] ?? [])->map(fn ($r, $rKey) => ['key' => $rKey, 'label' => $r['label']])->values()->all(),
        ])->values()->all();
    }

    /**
     * @return array{charge: float, fee: float, net: float, percent: float, fixed: float, igv: float, symbol: string, summary: string}
     */
    public function fromCharge(float $charge, string $method, string $release, string $country = 'PE'): array
    {
        $rate = $this->rate($method, $release, $country);
        $charge = round(max($charge, 0), 2);
        $fee = $charge > 0 ? $this->fee($charge, $rate) : 0.0;

        return $this->result($charge, $fee, $rate);
    }

    /** El cobro más bajo (al centavo) con el que, tras la comisión, se recibe al menos $net. */
    public function fromNet(float $net, string $method, string $release, string $country = 'PE'): array
    {
        $rate = $this->rate($method, $release, $country);
        $net = round(max($net, 0), 2);
        if ($net <= 0) {
            return $this->result(0.0, 0.0, $rate);
        }

        $factor = 1 - ($rate['percent'] / 100) * (1 + $rate['igv'] / 100);
        $charge = round(($net + $rate['fixed'] * (1 + $rate['igv'] / 100)) / $factor, 2);
        // Los redondeos de la comisión pueden dejar el neto 1 centavo corto: se sube hasta cubrirlo.
        while (round($charge - $this->fee($charge, $rate), 2) < $net) {
            $charge = round($charge + 0.01, 2);
        }

        return $this->result($charge, $this->fee($charge, $rate), $rate);
    }

    /** @return array{percent: float, fixed: float, igv: float, symbol: string} */
    private function rate(string $method, string $release, string $country): array
    {
        $market = $this->market($country);
        $row = $market['methods'][$method]['release'][$release] ?? null;
        if (!$market || !$row) {
            throw new \InvalidArgumentException('Combinación de comisión no disponible.');
        }

        return ['percent' => (float) $row['percent'], 'fixed' => (float) $row['fixed'], 'igv' => (float) $market['igv'], 'symbol' => (string) $market['symbol']];
    }

    private function fee(float $charge, array $rate): float
    {
        return round(($charge * $rate['percent'] / 100 + $rate['fixed']) * (1 + $rate['igv'] / 100), 2);
    }

    private function result(float $charge, float $fee, array $rate): array
    {
        $summary = rtrim(rtrim(number_format($rate['percent'], 2, ',', ''), '0'), ',') . '% + ' . $rate['symbol'] . number_format($rate['fixed'], 2, ',', '') . ' + IGV';

        return [
            'charge' => $charge, 'fee' => $fee, 'net' => round($charge - $fee, 2),
            'percent' => $rate['percent'], 'fixed' => $rate['fixed'], 'igv' => $rate['igv'],
            'symbol' => $rate['symbol'], 'summary' => $summary,
        ];
    }
}
