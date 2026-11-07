<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Pos\PosSaleService;
use App\Services\Pos\PosSchema;
use App\Services\Pos\SalesDocuments;
use App\Services\Reports\ProfitAndLoss;
use App\Services\Reports\SalesProfit;
use Illuminate\Http\Request;

/**
 * Dashboard → Ventas: the counter sales that went through (cancelled ones are not sales), by day,
 * month or year, searchable by customer, comprobante or document — with what they earned for
 * exactly the filters in use. The online store's orders are a different module (Pedidos).
 */
class SalesController extends Controller
{
    public const PERIODS = ['hoy' => 'Hoy', 'ayer' => 'Ayer', '7_dias' => '7 días', 'mes' => 'Este mes', 'mes_pasado' => 'Mes pasado', 'anio' => 'Este año', 'todas' => 'Todas'];

    public function index(Request $request, PosSchema $schema, SalesDocuments $documents, SalesProfit $profit)
    {
        abort_unless($schema->ready(), 404);
        $store = $request->user()->currentStore();
        abort_unless($store, 404);

        $custom = $request->filled('desde') ? ProfitAndLoss::customRange($request->query('desde'), $request->query('hasta') ?: $request->query('desde')) : null;
        $period = array_key_exists((string) $request->query('periodo'), self::PERIODS) ? $request->query('periodo') : 'hoy';
        $range = $custom ?? ($period === 'todas' ? null : ProfitAndLoss::range($period));
        $docsReady = $documents->ready();
        $type = $docsReady && isset(SalesDocuments::TYPES[(string) $request->query('tipo')]) ? $request->query('tipo') : null;
        $method = array_key_exists((string) $request->query('medio'), PosSaleService::METHODS) ? $request->query('medio') : null;
        $term = trim((string) $request->query('q'));

        $sales = Order::query()->where('store_id', $store->id)->where('source', 'pos')->where('status', '!=', 'cancelled')
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

        // Where the earnings detail lives, for the same period.
        $detail = $custom ? ['desde' => $range[0]->toDateString(), 'hasta' => $range[1]->toDateString()] : ['periodo' => $period === 'todas' ? 'anio' : $period];

        $sunat = app(\App\Services\Sunat\SunatSchema::class)->ready();
        $orders = (clone $sales)->with('payments')
            ->when($sunat, fn ($q) => $q->with('electronicDocument:id,order_id,store_id,type,series,number,environment,status,sunat_message,attempts,emailed_at'))
            ->latest('id')->paginate(25)->withQueryString();
        // Boleta/factura: waiting for the «Enviar a SUNAT» button, sent, or internal.
        $electronic = $sunat ? $orders->getCollection()->filter(fn ($o) => in_array($o->document_type, ['boleta', 'factura'], true))
            ->mapWithKeys(fn ($o) => [$o->id => app(\App\Services\Sunat\ElectronicDocuments::class)->state($o)]) : collect();

        return view('dashboard.sales.index', [
            'store'     => $store,
            'orders'    => $orders,
            'electronic' => $electronic,
            'summary'   => $profit->forOrders($sales, $store->id),
            'periods'   => self::PERIODS,
            'period'    => $custom ? null : $period,
            'from'      => $range[0] ?? null,
            'to'        => $range[1] ?? null,
            'type'      => $type,
            'method'    => $method,
            'term'      => $term,
            'docsReady' => $docsReady,
            'methods'   => PosSaleService::METHODS,
            'extras'    => $schema->extrasReady(),
            'detailUrl' => route('dashboard.ganancias.index', $detail),
            'hasStorefront' => $store->hasStorefront(),
        ]);
    }
}
