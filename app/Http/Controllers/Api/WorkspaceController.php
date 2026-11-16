<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\StoreSummaryResource;
use App\Models\Store;
use App\Services\Team\TeamAccess;
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
            // Owned businesses and the ones the person was let into, each with their role there.
            'stores'           => app(TeamAccess::class)->workspaces($user)
                ->map(fn ($w) => (new StoreSummaryResource($w['store']))->resolve() + ['role' => $w['role']])->values(),
        ]);
    }

    public function switch(Request $request, int $store): JsonResponse
    {
        $user = $request->user();
        // Only businesses the account works in (own or team): any other id answers 404, never confirming it exists.
        $target = Store::find($store);
        abort_unless($target instanceof Store && app(TeamAccess::class)->roleIn($user, $target) !== null, 404, 'Tienda no encontrada.');

        $user->switchToStore($target);

        return response()->json([
            'message' => "Ahora administras {$target->name}.",
            'store'   => new StoreSummaryResource($target),
        ]);
    }
}
