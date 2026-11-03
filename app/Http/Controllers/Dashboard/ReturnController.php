<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\SaveReturnRequest;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\RegisterSession;
use App\Services\Pos\PosSaleService;
use App\Services\Pos\PosSchema;
use App\Services\Pos\ReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Dashboard → Caja → Devoluciones: returns of counter sales with their credit note (ERP Fase 4c). */
class ReturnController extends Controller
{
    public function __construct(private PosSchema $schema)
    {
    }

    /** Recent returns, and a search by sale number to start one. */
    public function index(Request $request)
    {
        abort_unless($this->schema->extrasReady(), 404);
        $store = $request->user()->currentStore();
        $number = trim((string) $request->query('venta'));
        if ($number !== '') {
            $order = Order::where('store_id', $store->id)->where('source', 'pos')
                ->where('order_number', 'like', '%' . ltrim($number, '#'))->latest('id')->first();
            if ($order) {
                return redirect()->route('dashboard.returns.create', $order->id);
            }
        }

        return view('dashboard.returns.index', [
            'returns'  => OrderReturn::where('store_id', $store->id)->with('order:id,order_number,customer_name')->latest('id')->paginate(25),
            'notFound' => $number !== '',
            'recent'   => Order::where('store_id', $store->id)->where('source', 'pos')->whereNotIn('status', ['cancelled', 'refunded'])->latest('id')->limit(8)->get(),
        ]);
    }

    public function create(Request $request, ReturnService $returns, int $order)
    {
        $order = $this->order($request, $order)->load('items', 'returns');

        return view('dashboard.returns.create', [
            'order'      => $order,
            'returnable' => $returns->returnable($order),
            'sessions'   => RegisterSession::where('store_id', $order->store_id)->where('status', 'open')->with('register:id,name')->get(),
            'methods'    => PosSaleService::METHODS,
        ]);
    }

    public function store(SaveReturnRequest $request, ReturnService $returns, int $order): RedirectResponse
    {
        $order = $this->order($request, $order);
        $actor = app(\App\Services\Pos\PosAccess::class)->authorize($request->user()->currentStore(), 'return', $request->input('approver_pin'));
        $return = $returns->create($order, $request->validated(), $request->user()->id, $actor);

        return redirect()->route('dashboard.returns.show', $return->id)->with('success', (float) $return->refunded > 0
            ? 'Devolución registrada: entrega S/ ' . number_format((float) $return->refunded, 2) . ' al cliente.'
            : 'Devolución registrada: se descontó de lo que el cliente debía.');
    }

    /** The 80 mm credit note; `?imprimir=1` prints on load. */
    public function show(Request $request, int $return)
    {
        abort_unless($this->schema->extrasReady(), 404);
        $return = OrderReturn::where('store_id', $request->user()->currentStore()->id)->with(['order.store', 'user:id,name'])->findOrFail($return);

        return view('dashboard.returns.show', ['return' => $return, 'methods' => PosSaleService::METHODS, 'print' => $request->boolean('imprimir')]);
    }

    private function order(Request $request, int $id): Order
    {
        abort_unless($this->schema->extrasReady(), 404);

        return Order::where('store_id', $request->user()->currentStore()->id)->where('source', 'pos')->findOrFail($id);
    }
}
