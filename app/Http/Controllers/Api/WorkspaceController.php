<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\StoreSummaryResource;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The account's stores and switching between them (Tribio Pass multi-store, ADR 0002). */
class WorkspaceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $current = $user->currentStore();

        return response()->json([
            'current_store_id' => $current?->id,
            'stores'           => StoreSummaryResource::collection($user->stores()->oldest()->get()),
        ]);
    }

    public function switch(Request $request, int $store): JsonResponse
    {
        $user = $request->user();
        // Only the account's own stores: any other id answers 404, never confirming it exists.
        $target = $user->stores()->find($store);
        abort_unless($target instanceof Store, 404, 'Tienda no encontrada.');

        $user->switchToStore($target);

        return response()->json([
            'message' => "Ahora administras {$target->name}.",
            'store'   => new StoreSummaryResource($target),
        ]);
    }
}
