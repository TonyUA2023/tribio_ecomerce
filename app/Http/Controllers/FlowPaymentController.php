<?php

namespace App\Http\Controllers;

use App\Http\Requests\FlowCallbackRequest;
use App\Models\PendingCheckout;
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
        $token = $request->validated('token');
        $unavailable = false;
        try {
            $receipt = $flow->reconcile($store, $token);
        } catch (ModelNotFoundException $e) {
            abort(404);
        } catch (Throwable $e) {
            // Network/verification hiccup: fall back to whatever is already known —
            // never invent a status Flow hasn't actually confirmed.
            $pending = PendingCheckout::where('store_id', $store->id)->where('gateway', 'flow')
                ->where('gateway_ref', $token)->firstOrFail();
            $order = $pending->order;
            $receipt = [
                'paymentStatus' => $order?->payment_status ?? 'pending',
                'orderNumber'   => $order?->order_number ?? $pending->reference,
                'currency'      => $order?->currency ?? $pending->payload['currency'],
                'total'         => (float) ($order?->total ?? $pending->payload['total']),
                'flowToken'     => $token,
            ];
            $unavailable = true;
        }
        return response()->view('payments.flow-result', array_merge($receipt, compact('store', 'unavailable')))
            ->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }
}
