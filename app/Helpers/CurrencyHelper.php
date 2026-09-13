<?php

namespace App\Helpers;

class CurrencyHelper
{
    /**
     * Retorna el código de la moneda actual ('PEN' o 'USD').
     */
    public static function currentCurrency(): string
    {
        // 1. Parámetro query explícito ?currency=usd o ?currency=pen
        if (request()->has('currency')) {
            $q = strtoupper(trim((string) request()->query('currency')));
            if (in_array($q, ['USD', 'PEN'])) {
                return $q;
            }
        }

        // 2. Cookie store_currency
        $cookieCurr = request()->cookie('store_currency');
        if ($cookieCurr) {
            $cookieCurr = strtoupper(trim((string) $cookieCurr));
            if (in_array($cookieCurr, ['USD', 'PEN'])) {
                return $cookieCurr;
            }
        }

        // 3. Fallback compatible con cookie user_country ('US' => 'USD', resto => 'PEN')
        $country = request()->cookie('user_country');
        if ($country === 'US') {
            return 'USD';
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
     * Retorna el símbolo de la moneda ('$' o 'S/').
     */
    public static function symbol(): string
    {
        return static::isUsd() ? '$' : 'S/';
    }

    /**
     * Retorna el código de moneda ('USD' o 'PEN').
     */
    public static function code(): string
    {
        return static::currentCurrency();
    }
}
