<?php

namespace App\Http\Controllers;

use App\Services\Geo\GeoCatalog;
use Illuminate\Http\JsonResponse;

/** Public geo lookups shared by the checkout, the dashboard rate form and the mobile app. */
class GeoController extends Controller
{
    public function countries(GeoCatalog $geo): JsonResponse
    {
        return response()->json(['data' => collect($geo->countries())->map(fn ($c) => $c + ['iso2' => $c['code']])->all()]);
    }

    public function states(GeoCatalog $geo, string $country): JsonResponse
    {
        $country = strtoupper($country);

        return response()->json(['country' => $country, 'data' => $geo->states($country)]);
    }
}
