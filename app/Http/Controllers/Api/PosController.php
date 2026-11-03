<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesCurrentStore;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\CloseRegisterRequest;
use App\Http\Requests\Dashboard\OpenRegisterRequest;
use App\Http\Requests\Dashboard\RegisterMovementRequest;
use App\Http\Requests\Dashboard\SavePosSaleRequest;
use App\Models\Order;
use App\Models\Register;
use App\Models\RegisterSession;
use App\Services\Pos\PosCatalog;
use App\Services\Pos\PosReceipt;
use App\Services\Pos\PosSaleService;
use App\Services\Pos\PosSchema;
use App\Services\Pos\PriceListService;
use App\Services\Pos\RegisterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobile quick sale (ERP Fase 4e): the same registers, shifts and counter sales as the web
 * register (Point-Of-Sale), for a seller on foot with the app. The phone is the owner's
 * account (Sanctum), so there is no PIN mode here; every rule of the sale lives in
 * PosSaleService and is shared with the web.
 */
class PosController extends Controller
{
    use ResolvesCurrentStore;

    public function __construct(private PosSchema $schema)
    {
    }

    /** Registers with their open shift and what the app can offer (credit, price lists). */
    public function overview(RegisterService $registers): JsonResponse
    {
        $store = $this->store();

        return response()->json([
            'registers' => $registers->registers($store)->map(fn (Register $register) => [
                'id'      => $register->id,
                'name'    => $register->name,
                'session' => $register->openSession ? $this->shift($registers, $register->openSession) : null,
            ])->values(),
            'methods'     => collect(PosSaleService::METHODS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'credit'      => $this->schema->creditReady(),
            'price_lists' => $this->schema->extrasReady()
                ? collect(['menor' => 'Menor'] + PriceListService::LISTS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values()
                : [],
        ]);
    }

    public function open(OpenRegisterRequest $request, RegisterService $registers, int $register): JsonResponse
    {
        $register = Register::where('store_id', $this->store()->id)->where('is_active', true)->findOrFail($register);
        $session = $registers->open($register, (float) $request->validated('opening_float'), $request->user()->id);

        return response()->json(['message' => "Abriste «{$register->name}».", 'session' => $this->shift($registers, $session)], 201);
    }

    public function products(Request $request, PosCatalog $catalog): JsonResponse
    {
        return response()->json($catalog->search($this->store(), mb_substr((string) $request->query('q'), 0, 80)));
    }

    public function customers(Request $request): JsonResponse
    {
        $term = mb_substr(trim((string) $request->query('q')), 0, 80);

        return response()->json(['data' => mb_strlen($term) < 2 ? [] : $this->store()->contacts()->search($term)
            ->orderByDesc('last_order_at')->limit(10)->get(['id', 'name', 'phone', 'document_number', 'relationship'])
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'detail' => $c->phone_display ?? $c->document_number, 'relationship' => $c->relationship])->values()]);
    }

    /** The shift: totals, money per method and its latest sales. */
    public function session(RegisterService $registers, int $session): JsonResponse
    {
        $session = $this->owned($session)->load('register');

        return response()->json([
            'session' => $this->shift($registers, $session),
            'sales'   => $session->orders()->with('payments')->latest('id')->limit(30)->get()->map(fn (Order $order) => [
                'id'            => $order->id,
                'order_number'  => $order->order_number,
                'customer_name' => $order->customer_name,
                'total'         => (float) $order->total,
                'owed'          => (float) $order->balance_due,
                'status'        => $order->status,
                'created_at'    => $order->created_at?->toIso8601String(),
                'methods'       => $order->payments->where('status', 'paid')->pluck('method')->unique()->map(fn ($m) => PosSaleService::METHODS[$m] ?? 'Pago')->values(),
            ]),
        ]);
    }

    public function sell(SavePosSaleRequest $request, PosSaleService $sales, PosReceipt $receipt, int $session): JsonResponse
    {
        [$order, $duplicate] = $sales->sell($this->owned($session), $request->validated(), $request->user()->id);

        return response()->json(['duplicate' => $duplicate, 'receipt' => $receipt->data($order)], $duplicate ? 200 : 201);
    }

    public function movement(RegisterMovementRequest $request, RegisterService $registers, int $session): JsonResponse
    {
        $session = $this->owned($session);
        $data = $request->validated();
        $registers->movement($session, $data['type'], (float) $data['amount'], $data['reason'], $request->user()->id);

        return response()->json(['message' => $data['type'] === 'in' ? 'Anotamos el ingreso de efectivo.' : 'Anotamos el retiro de efectivo.',
            'session' => $this->shift($registers, $session->fresh('register'))], 201);
    }

    public function close(CloseRegisterRequest $request, RegisterService $registers, int $session): JsonResponse
    {
        $closed = $registers->close($this->owned($session), (float) $request->validated('counted_cash'), $request->validated('notes'), $request->user()->id);
        $difference = (float) $closed->difference;

        return response()->json([
            'message'    => $difference == 0.0 ? 'Caja cerrada: el efectivo cuadra.'
                : ($difference > 0 ? 'Caja cerrada con S/ ' . number_format($difference, 2) . ' de más.' : 'Caja cerrada con S/ ' . number_format(abs($difference), 2) . ' de menos.'),
            'expected'   => (float) $closed->expected_cash,
            'counted'    => (float) $closed->counted_cash,
            'difference' => $difference,
        ]);
    }

    public function receipt(PosReceipt $receipt, int $order): JsonResponse
    {
        $order = Order::where('store_id', $this->store()->id)->where('source', 'pos')->findOrFail($order);

        return response()->json(['receipt' => $receipt->data($order)]);
    }

    private function shift(RegisterService $registers, RegisterSession $session): array
    {
        return [
            'id'            => $session->id,
            'register'      => $session->register?->name,
            'status'        => $session->status,
            'opened_at'     => $session->opened_at?->toIso8601String(),
            'opening_float' => (float) $session->opening_float,
            'summary'       => $session->isOpen() ? $registers->summary($session) : $session->totals,
        ];
    }

    private function store(): \App\Models\Store
    {
        abort_unless($this->schema->ready(), 404, 'La caja aún no está disponible en tu cuenta.');

        return $this->currentStore();
    }

    private function owned(int $id): RegisterSession
    {
        return RegisterSession::where('store_id', $this->store()->id)->findOrFail($id);
    }
}
