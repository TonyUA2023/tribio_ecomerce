<?php

namespace App\Http\Controllers\Api;

use App\Services\Geo\GeoCatalog;
use App\Http\Controllers\Api\Concerns\ResolvesCurrentStore;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SaveShippingRateRequest;
use App\Http\Resources\Mobile\ShippingRateResource;
use Illuminate\Http\JsonResponse;

/** Zonas de envío: cost per country (and optionally state/region). */
class ShippingRateController extends Controller
{
    use ResolvesCurrentStore;

    public function index(): JsonResponse
    {
        return response()->json([
            'data'      => ShippingRateResource::collection($this->currentStore()->shippingRates()->orderBy('country_code')->orderBy('state')->get()),
            'countries' => app(GeoCatalog::class)->countries(),
        ]);
    }

    public function store(SaveShippingRateRequest $request): JsonResponse
    {
        $rate = $this->currentStore()->shippingRates()->updateOrCreate(
            ['country_code' => $request->country_code, 'state' => $request->state],
            ['cost' => $request->cost, 'is_active' => true],
        );

        return response()->json(['message' => 'Tarifa de envío agregada correctamente.', 'rate' => new ShippingRateResource($rate)], 201);
    }

    public function update(SaveShippingRateRequest $request, int $id): JsonResponse
    {
        $rate = $this->currentStore()->shippingRates()->findOrFail($id);
        $rate->update(['country_code' => $request->country_code, 'state' => $request->state, 'cost' => $request->cost]);

        return response()->json(['message' => 'Tarifa actualizada.', 'rate' => new ShippingRateResource($rate)]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->currentStore()->shippingRates()->findOrFail($id)->delete();

        return response()->json(['message' => 'Tarifa eliminada.']);
    }
}
