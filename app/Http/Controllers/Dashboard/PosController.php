<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\SavePosSaleRequest;
use App\Models\Order;
use App\Models\Register;
use App\Models\RegisterSession;
use App\Services\Pos\PosCatalog;
use App\Services\Pos\PosSaleService;
use App\Services\Pos\PosSchema;
use App\Services\Pos\RegisterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Dashboard → Caja: the counter sale screen of an open register (ERP Fase 4a). */
class PosController extends Controller
{
    public function __construct(private PosSchema $schema)
    {
    }

    /** The open register (`?caja=` picks one) or the screen to open a register. */
    public function index(Request $request, RegisterService $registers, \App\Services\Pos\QuoteService $quotes, PosCatalog $catalog)
    {
        abort_unless($this->schema->ready(), 404);
        $store = $request->user()->currentStore();
        $access = app(\App\Services\Pos\PosAccess::class);
        $staff = $access->current($store);
        if ($access->active($store) && !$staff) {
            return view('dashboard.pos.locked', ['store' => $store, 'caja' => $request->integer('caja') ?: null]);
        }
        $all = $registers->registers($store);
        $register = $all->firstWhere('id', $request->integer('caja'))
            ?? $all->first(fn (Register $r) => $r->openSession?->opened_by === $request->user()->id)
            ?? $all->first(fn (Register $r) => $r->openSession !== null);

        if (!$register?->openSession || $request->boolean('cajas')) {
            return view('dashboard.pos.registers', [
                'registers' => $all,
                'kiosk'     => $access->active($store),
                'staffMember' => $staff,
                'staffReady' => $this->schema->staffReady(),
                'recent'    => RegisterSession::where('store_id', $store->id)->where('status', 'closed')->with('register:id,name')->latest('closed_at')->limit(5)->get(),
            ]);
        }

        $session = $register->openSession;

        return view('dashboard.pos.sell', [
            'store'     => $store,
            'register'  => $register,
            'session'   => $session,
            'summary'   => $registers->summary($session),
            'registers' => $all,
            'methods'   => PosSaleService::METHODS,
            'creditEnabled' => $this->schema->creditReady(),
            'kiosk'     => $access->active($store) ? [
                'name'    => $staff->name,
                'manager' => $staff->isManager(),
                'idle'    => \App\Services\Pos\PosAccess::IDLE_MINUTES,
            ] : null,
            'offline'   => [
                'catalogUrl' => route('dashboard.pos.catalog'),
                'tokenUrl'   => route('dashboard.pos.token'),
                'storeId'    => $store->id,
                'session'    => $session->id,
            ],
            'extras'    => $this->schema->extrasReady(),
            'documents' => ($docs = app(\App\Services\Pos\SalesDocuments::class))->ready() ? [
                'types'    => \App\Services\Pos\SalesDocuments::labels(),
                'short'    => array_map(fn ($meta) => $meta['short'], \App\Services\Pos\SalesDocuments::TYPES),
                'hasRuc'   => \App\Services\Pos\SalesDocuments::validRuc((string) $store->ruc),
                'idLimit'  => \App\Services\Pos\SalesDocuments::BOLETA_ID_LIMIT,
                'settings' => route('dashboard.pos.documents.edit'),
                // Facturación electrónica: qué se envía a SUNAT sin preguntar y qué se elige en cada venta.
                'sunat'    => ($sunat = app(\App\Services\Sunat\SunatSchema::class))->ready() && ($sunatSetting = app(\App\Services\Sunat\SunatSettings::class)->for($store))->enabled
                    ? ['boleta' => $sunatSetting->boleta_mode, 'factura' => $sunatSetting->factura_mode === 'auto' ? 'auto' : 'manual'] : null,
            ] : null,
            'priceLists' => \App\Services\Pos\PriceListService::LISTS,
            'quote'     => $this->schema->extrasReady() && $request->integer('proforma')
                ? (($found = \App\Models\Quote::where('store_id', $store->id)->where('status', 'open')->find($request->integer('proforma'))) ? $quotes->forRegister($found, $catalog) : null)
                : null,
            'firstDue'  => [
                'min'     => now('America/Lima')->toDateString(),
                'default' => now('America/Lima')->addDays(\App\Services\Pos\ReceivableService::DEFAULT_DAYS)->toDateString(),
            ],
        ]);
    }

    public function products(Request $request, PosCatalog $catalog): JsonResponse
    {
        abort_unless($this->schema->ready(), 404);

        return response()->json($catalog->search($request->user()->currentStore(), mb_substr((string) $request->query('q'), 0, 80)));
    }

    public function customers(Request $request): JsonResponse
    {
        abort_unless($this->schema->ready(), 404);
        $term = mb_substr(trim((string) $request->query('q')), 0, 80);

        return response()->json(mb_strlen($term) < 2 ? [] : $request->user()->currentStore()->contacts()->search($term)
            ->orderByDesc('last_order_at')->limit(8)->get(['id', 'name', 'phone', 'document_number', 'relationship'])
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'detail' => $c->phone_display ?? $c->document_number, 'relationship' => $c->relationship]));
    }

    public function sell(SavePosSaleRequest $request, PosSaleService $sales, \App\Services\Pos\PosAccess $access, int $session): JsonResponse
    {
        $session = $this->session($request, $session);
        $data = $request->validated();
        $actor = $access->authorizeMany($request->user()->currentStore(), $this->restrictedActions($request->user()->currentStore(), $data), $request->input('approver_pin'));
        [$order, $duplicate] = $sales->sell($session, $data, $request->user()->id, $actor);

        return response()->json([
            'order_id'     => $order->id,
            'order_number' => $order->order_number,
            'document_type'   => $order->document_type ?? \App\Services\Pos\SalesDocuments::NOTA,
            'document_label'  => $order->document_label,
            'document_number' => $order->document_code,
            'total'        => (float) $order->total,
            'change'       => (float) ($order->change_given ?? 0),
            'owed'         => (float) $order->balance_due,
            'receipt_url'  => route('dashboard.pos.receipt', $order->id),
            'whatsapp_url' => $order->customer_phone
                ? 'https://wa.me/' . $order->customer_phone . '?text=' . rawurlencode(app(\App\Services\Pos\PosReceipt::class)->text($order))
                : null,
            'duplicate'    => $duplicate,
        ], $duplicate ? 200 : 201);
    }

    /** The 80 mm ticket (print CSS); `?imprimir=1` opens the print dialog. */
    public function receipt(Request $request, int $order)
    {
        abort_unless($this->schema->ready(), 404);
        $order = Order::where('store_id', $request->user()->currentStore()->id)->where('source', 'pos')
            ->with(['items', 'payments', 'store', 'registerSession.register'])->findOrFail($order);
        if ($this->schema->creditReady()) {
            $order->load('installments');
        }

        return view('dashboard.pos.receipt', ['order' => $order, 'methods' => PosSaleService::METHODS, 'print' => $request->boolean('imprimir')]);
    }

    /**
     * What in this sale only a manager may allow (ERP Fase 4d): a typed price, a discount,
     * credit. Prices and discount of the open quote being charged are already agreed.
     *
     * @return list<string>
     */
    private function restrictedActions(\App\Models\Store $store, array $data): array
    {
        $quote = !empty($data['quote_id']) && $this->schema->extrasReady()
            ? \App\Models\Quote::where('store_id', $store->id)->where('status', 'open')->find($data['quote_id'])
            : null;
        $quoted = collect($quote?->items ?? [])->mapWithKeys(fn ($item) => [($item['product_id'] . ':' . ($item['variant_id'] ?? '') . ':' . ($item['unit_id'] ?? '')) => (float) $item['price']]);

        $typedPrice = collect($data['items'])->contains(function ($item) use ($quoted) {
            if (!isset($item['price']) || $item['price'] === null) {
                return false;
            }
            $key = $item['product_id'] . ':' . ($item['variant_id'] ?? '') . ':' . ($item['unit_id'] ?? '');

            return !isset($quoted[$key]) || abs($quoted[$key] - (float) $item['price']) > 0.004;
        });

        return array_values(array_filter([
            $typedPrice ? 'price' : null,
            (float) ($data['discount'] ?? 0) > ($quote ? (float) $quote->discount : 0) + 0.004 ? 'discount' : null,
            !empty($data['credit']) ? 'credit' : null,
        ]));
    }

    private function session(Request $request, int $id): RegisterSession
    {
        abort_unless($this->schema->ready(), 404);

        return RegisterSession::where('store_id', $request->user()->currentStore()->id)->findOrFail($id);
    }
}
