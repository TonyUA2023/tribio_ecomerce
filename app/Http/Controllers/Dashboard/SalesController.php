<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Pos\PosSaleService;
use App\Services\Pos\PosSchema;
use App\Services\Pos\SalesDocuments;
use App\Services\Reports\ProfitAndLoss;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Dashboard → Ventas: every counter sale with its comprobante, searchable by number, customer or document. */
class SalesController extends Controller
{
    public const PERIODS = ['hoy' => 'Hoy', '7_dias' => 'Últimos 7 días', 'mes' => 'Este mes', 'todas' => 'Todas'];

    public function index(Request $request, PosSchema $schema, SalesDocuments $documents)
    {
        abort_unless($schema->ready(), 404);
        $store = $request->user()->currentStore();
        abort_unless($store, 404);

        $custom = $request->filled('desde') ? ProfitAndLoss::customRange($request->query('desde'), $request->query('hasta') ?: $request->query('desde')) : null;
        $period = array_key_exists((string) $request->query('periodo'), self::PERIODS) ? $request->query('periodo') : '7_dias';
        $range = $custom ?? ($period === 'todas' ? null : ProfitAndLoss::range($period));
        $docsReady = $documents->ready();
        $type = $docsReady && isset(SalesDocuments::TYPES[(string) $request->query('tipo')]) ? $request->query('tipo') : null;
        $method = array_key_exists((string) $request->query('medio'), PosSaleService::METHODS) ? $request->query('medio') : null;
        $term = trim((string) $request->query('q'));

        $query = Order::query()->where('store_id', $store->id)->where('source', 'pos')
            ->when($range, fn ($q) => $q->whereBetween('created_at', [
                $range[0]->copy()->setTimezone(config('app.timezone')), $range[1]->copy()->setTimezone(config('app.timezone')),
            ]))
            ->when($type === SalesDocuments::NOTA, fn ($q) => $q->where(fn ($n) => $n->where('document_type', SalesDocuments::NOTA)->orWhereNull('document_type')))
            ->when($type && $type !== SalesDocuments::NOTA, fn ($q) => $q->where('document_type', $type))
            ->when($method, fn ($q) => $q->whereHas('payments', fn ($p) => $p->where('method', $method)->where('status', 'paid')))
            ->when($term !== '', function ($q) use ($term, $docsReady) {
                $q->where(function ($w) use ($term, $docsReady) {
                    $w->where('order_number', 'like', "%{$term}%")->orWhere('customer_name', 'like', "%{$term}%")->orWhere('customer_document_number', 'like', "%{$term}%");
                    // "B001-12", "B00112" or "b001 12": a comprobante by its series and number.
                    if ($docsReady && preg_match('/^([A-Za-z0-9]{4})[\s-]?0*(\d+)$/', $term, $m)) {
                        $w->orWhere(fn ($d) => $d->where('document_series', strtoupper($m[1]))->where('document_number', (int) $m[2]));
                    }
                });
            });

        $live = (clone $query)->where('status', '!=', 'cancelled');
        $summary = ['count' => (clone $live)->count(), 'total' => round((float) (clone $live)->sum('total'), 2), 'cancelled' => (clone $query)->where('status', 'cancelled')->count()];

        return view('dashboard.sales.index', [
            'orders'   => $query->with('payments')->latest('id')->paginate(25)->withQueryString(),
            'summary'  => $summary,
            'periods'  => self::PERIODS,
            'period'   => $custom ? null : $period,
            'from'     => $range[0] ?? null,
            'to'       => $range[1] ?? null,
            'type'     => $type,
            'method'   => $method,
            'term'     => $term,
            'docsReady' => $docsReady,
            'methods'  => PosSaleService::METHODS,
            'extras'   => $schema->extrasReady(),
        ]);
    }
}
