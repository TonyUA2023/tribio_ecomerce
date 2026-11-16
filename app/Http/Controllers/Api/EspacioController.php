<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\StoreSummaryResource;
use App\Services\Team\TeamAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** What the phone needs to draw the right menu for this person in the current business. */
class EspacioController extends Controller
{
    public function show(Request $request, TeamAccess $team): JsonResponse
    {
        $user = $request->user();
        $store = $user->currentStore();
        abort_unless($store, 404, 'No tienes un negocio activo.');

        return response()->json(['store' => new StoreSummaryResource($store)] + $team->profile($user, $store));
    }
}
