<?php

namespace App\Http\Resources\Mobile;

use App\Helpers\CurrencyHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShippingRateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $country = CurrencyHelper::supportedCountries()[$this->country_code] ?? null;

        return [
            'id'           => $this->id,
            'country_code' => $this->country_code,
            'country_name' => $country['name'] ?? $this->country_code,
            'flag'         => $country['flag'] ?? null,
            'state'        => $this->state,
            'cost'         => (float) $this->cost,
            'currency'     => $country['currency'] ?? null,
            'symbol'       => $country['symbol'] ?? null,
            'is_active'    => (bool) $this->is_active,
        ];
    }
}
