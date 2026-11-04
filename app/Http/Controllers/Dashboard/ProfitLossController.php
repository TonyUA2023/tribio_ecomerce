<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Reports\ProfitAndLoss;
use Illuminate\Http\Request;

/** Dashboard → Ganancias y pérdidas: ingresos, costo, ganancia bruta, gastos, pérdidas y ganancia neta, por día. */
class ProfitLossController extends Controller
{
    public function index(Request $request, ProfitAndLoss $report)
    {
        $store = $request->user()->currentStore();
        abort_unless($store, 404);

        $custom = $request->filled('desde') ? ProfitAndLoss::customRange($request->query('desde'), $request->query('hasta')) : null;
        $preset = array_key_exists((string) $request->query('periodo'), ProfitAndLoss::PRESETS) ? $request->query('periodo') : '7_dias';
        [$from, $to] = $custom ?? ProfitAndLoss::range($preset);

        return view('dashboard.profit-loss', [
            'store'   => $store,
            'preset'  => $custom ? null : $preset,
            'from'    => $from,
            'to'      => $to,
            'presets' => ProfitAndLoss::PRESETS,
        ] + $report->build($store, $from, $to));
    }
}
