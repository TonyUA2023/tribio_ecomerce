<?php

namespace App\Services\Sunat;

/** "CIENTO DIECIOCHO CON 50/100 SOLES": the legend 1000 every comprobante must carry. */
class NumberToWords
{
    private const UNITS = ['', 'UN', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE', 'DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE',
        'DIECISÉIS', 'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE', 'VEINTE', 'VEINTIÚN', 'VEINTIDÓS', 'VEINTITRÉS', 'VEINTICUATRO', 'VEINTICINCO', 'VEINTISÉIS', 'VEINTISIETE', 'VEINTIOCHO', 'VEINTINUEVE'];
    private const TENS = [3 => 'TREINTA', 4 => 'CUARENTA', 5 => 'CINCUENTA', 6 => 'SESENTA', 7 => 'SETENTA', 8 => 'OCHENTA', 9 => 'NOVENTA'];
    private const HUNDREDS = [1 => 'CIENTO', 2 => 'DOSCIENTOS', 3 => 'TRESCIENTOS', 4 => 'CUATROCIENTOS', 5 => 'QUINIENTOS', 6 => 'SEISCIENTOS', 7 => 'SETECIENTOS', 8 => 'OCHOCIENTOS', 9 => 'NOVECIENTOS'];

    public static function soles(float $amount): string
    {
        $amount = round($amount, 2);
        $whole = (int) floor($amount);
        $cents = (int) round(($amount - $whole) * 100);

        return sprintf('%s CON %02d/100 SOLES', self::integer($whole), $cents);
    }

    public static function integer(int $n): string
    {
        if ($n === 0) {
            return 'CERO';
        }
        if ($n >= 1_000_000_000) {
            return (string) $n; // out of range for a sale
        }
        $parts = [];
        if ($millions = intdiv($n, 1_000_000)) {
            $parts[] = $millions === 1 ? 'UN MILLÓN' : self::below1000($millions, false) . ' MILLONES';
        }
        $thousands = intdiv($n % 1_000_000, 1000);
        if ($thousands) {
            $parts[] = $thousands === 1 ? 'MIL' : self::below1000($thousands, true) . ' MIL';
        }
        if ($rest = $n % 1000) {
            $parts[] = self::below1000($rest, false);
        }

        return implode(' ', $parts);
    }

    /** @param bool $beforeMil "veintiún mil", not "veintiuno mil": the apocopated form */
    private static function below1000(int $n, bool $beforeMil): string
    {
        if ($n === 100) {
            return 'CIEN';
        }
        $words = [];
        if ($hundreds = intdiv($n, 100)) {
            $words[] = self::HUNDREDS[$hundreds];
        }
        $rest = $n % 100;
        if ($rest > 0) {
            if ($rest < 30) {
                $words[] = self::UNITS[$rest];
            } else {
                $unit = $rest % 10;
                $words[] = self::TENS[intdiv($rest, 10)] . ($unit ? ' Y ' . self::UNITS[$unit] : '');
            }
        }

        return implode(' ', $words);
    }
}
