<?php

namespace App\Http\Controllers;

use App\Http\Requests\FlowCallbackRequest;
use App\Models\Store;
use App\Services\FlowService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Throwable;

class FlowPaymentController extends Controller
{
    public function confirmation(FlowCallbackRequest $request, Store $store, FlowService $flow)
    {
        try {
            $flow->reconcile($store, $request->validated('token'));
            return response()->json(['received' => true]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['received' => false], 404);
        } catch (Throwable $e) {
            Log::warning('Flow confirmation could not be verified.', ['store_id' => $store->id]);
            return response()->json(['received' => false], 503);
        }
    }

    public function returned(FlowCallbackRequest $request, Store $store, FlowService $flow)
    {
        $unavailable = false;
        try {
            $order = $flow->reconcile($store, $request->validated('token'));
        } catch (ModelNotFoundException $e) {
            abort(404);
        } catch (Throwable $e) {
            $order = $store->orders()->where('payment_method', 'flow')
                ->where('flow_token', $request->validated('token'))->firstOrFail();
            $unavailable = true;
        }
        return response()->view('payments.flow-result', compact('store', 'order', 'unavailable'))
            ->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }
}
