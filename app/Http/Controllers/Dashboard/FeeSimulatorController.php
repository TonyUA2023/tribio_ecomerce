<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Payments\MercadoPagoFees;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Calcula comisiones de Mercado Pago para el simulador del formulario de productos. */
class FeeSimulatorController extends Controller
{
    public function __invoke(Request $request, MercadoPagoFees $fees): JsonResponse
    {
        $data = $request->validate([
            'mode'    => 'required|in:charge,receive',
            'amount'  => 'required|numeric|min:0|max:1000000',
            'method'  => 'required|string|max:30',
            'release' => 'required|string|max:30',
        ]);

        try {
            $result = $data['mode'] === 'receive'
                ? $fees->fromNet((float) $data['amount'], $data['method'], $data['release'])
                : $fees->fromCharge((float) $data['amount'], $data['method'], $data['release']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }
}
