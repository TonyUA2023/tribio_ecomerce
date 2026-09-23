<?php

namespace App\Services\Marketing\Meta;

use App\Models\Order;

/**
 * The Conversions API "user_data" for an order: how Meta matches a sale to the person
 * who saw the ad. Personal fields are normalized the way Meta expects and SHA-256
 * hashed here; they never leave Tribio in clear text. The DNI and street address are
 * deliberately never sent.
 */
class BuyerMatchData
{
    /**
     * @param  array<string, mixed>  $tracking  PendingCheckout payload 'tracking' (see TrackingContext)
     * @return array<string, string>
     */
    public function forOrder(Order $order, array $tracking): array
    {
        [$firstName, $lastName] = $this->splitName((string) $order->customer_name);
        $country = strtolower((string) ($order->customer_country ?: 'PE'));

        $hashed = array_filter([
            'em'          => $this->email($order->customer_email),
            'ph'          => $this->phone($order->customer_phone, $country),
            'fn'          => $this->compact($firstName),
            'ln'          => $this->compact($lastName),
            'ct'          => $this->compact($order->customer_city),
            'st'          => $this->compact($order->customer_state),
            'zp'          => $this->compact($order->customer_zipcode),
            'country'     => preg_match('/^[a-z]{2}$/', $country) ? $country : null,
            'external_id' => $order->user_id ? 'tribio-user-' . $order->user_id : $this->email($order->customer_email),
        ], fn ($v) => $v !== null && $v !== '');

        $data = array_map(fn ($v) => hash('sha256', $v), $hashed);

        // Browser identifiers are sent as-is (Meta's own cookies / the request).
        foreach (['fbp', 'fbc', 'client_ip_address', 'client_user_agent'] as $key) {
            if (!empty($tracking[$key]) && is_string($tracking[$key])) {
                $data[$key] = $tracking[$key];
            }
        }

        return $data;
    }

    private function email(?string $email): ?string
    {
        $email = mb_strtolower(trim((string) $email));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    /** Digits only, with the country code (Meta: "16505551234"). */
    private function phone(?string $phone, string $country): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        $digits = preg_replace('/^00/', '', $digits);
        if ($digits === '') {
            return null;
        }
        // A Peruvian mobile typed without the prefix: 9 digits starting with 9.
        if ($country === 'pe' && strlen($digits) === 9 && $digits[0] === '9') {
            $digits = '51' . $digits;
        }

        return strlen($digits) >= 8 ? $digits : null;
    }

    /** Lowercase letters and digits only ("San Juan de Lurigancho" → "sanjuandelurigancho"). */
    private function compact(?string $value): ?string
    {
        $value = preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower(trim((string) $value)));

        return $value !== '' ? $value : null;
    }

    /** @return array{0: ?string, 1: ?string} */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY);
        if (!$parts) {
            return [null, null];
        }
        $first = array_shift($parts);

        return [$first, $parts ? implode(' ', $parts) : null];
    }
}
