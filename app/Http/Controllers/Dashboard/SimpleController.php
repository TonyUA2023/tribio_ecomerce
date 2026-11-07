<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Pos\PosSchema;
use App\Services\Pos\SimpleSales;
use App\Services\Reports\ProfitAndLoss;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Dashboard → Vender / Hoy of the "modo simple": one phone screen to sell, one to see the day. */
class SimpleController extends Controller
{
    public function __construct(private PosSchema $schema, private SimpleSales $simple)
    {
    }

    public function index(Request $request)
    {
        $store = $request->user()->currentStore();
        abort_unless($store, 404);
        if (!$this->schema->ready()) {
            return view('dashboard.simple.index', ['store' => $store, 'ready' => false]);
        }

        return view('dashboard.simple.index', [
            'store'    => $store,
            'ready'    => true,
            'products' => $this->simple->catalog($store),
            'today'    => $this->simple->today($store),
            'methods'  => SimpleSales::METHODS,
            'more'     => $this->simple->hasMore($store),
        ]);
    }

    /** The search box of a business with more products than the screen carries. */
    public function search(Request $request): JsonResponse
    {
        abort_unless($this->schema->ready(), 404);
        $store = $request->user()->currentStore();
        abort_unless($store, 404);

        return response()->json($this->simple->search($store, mb_substr((string) $request->query('q'), 0, 80)));
    }

    /** One tap = one sale. The uuid comes from the phone, so a double tap or a retry never sells twice. */
    public function sell(Request $request): JsonResponse
    {
        abort_unless($this->schema->ready(), 404);
        $store = $request->user()->currentStore();
        abort_unless($store, 404);

        $data = $request->validate([
            'uuid'               => ['required', 'string', 'max:64'],
            'method'             => ['required', 'string', 'in:' . implode(',', array_keys(SimpleSales::METHODS))],
            'tendered'           => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'offline_at'         => ['nullable', 'date'],
            'items'              => ['required', 'array', 'min:1', 'max:60'],
            'items.*.free'       => ['nullable', 'boolean'],
            'items.*.product_id' => ['required_without:items.*.free', 'nullable', 'integer'],
            'items.*.name'       => ['nullable', 'string', 'max:120'],
            'items.*.price'      => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'items.*.quantity'   => ['required', 'integer', 'min:1', 'max:9999'],
        ]);

        $result = $this->simple->sell($store, $request->user()->id, $data);

        return response()->json([
            'ok'       => true,
            'number'   => $result['order']->order_number,
            'total'    => round((float) $result['order']->total, 2),
            'change'   => $result['change'],
            'repeated' => $result['repeated'],
            'today'    => $this->simple->today($store),
        ]);
    }

    /** The day in three numbers: what came in, what it cost, what is left. */
    public function today(Request $request, ProfitAndLoss $report)
    {
        $store = $request->user()->currentStore();
        abort_unless($store, 404);
        if (!$this->schema->ready()) {
            return view('dashboard.simple.today', ['store' => $store, 'ready' => false]);
        }

        [$from, $to] = ProfitAndLoss::range('hoy');
        $summary = $this->simple->summary($store, $report->build($store, $from, $to));

        return view('dashboard.simple.today', [
            'store'   => $store,
            'ready'   => true,
            'day'     => $summary,
            'recent'  => Order::where('store_id', $store->id)->where('source', 'pos')->where('status', '!=', 'cancelled')->where('created_at', '>=', $from->copy()->setTimezone(config('app.timezone')))
                ->with('items:id,order_id,product_name,quantity')->latest('id')->limit(15)->get(),
        ]);
    }
}
