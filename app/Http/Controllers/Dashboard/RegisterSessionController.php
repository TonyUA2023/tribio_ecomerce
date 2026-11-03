<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\CloseRegisterRequest;
use App\Http\Requests\Dashboard\OpenRegisterRequest;
use App\Http\Requests\Dashboard\RegisterMovementRequest;
use App\Http\Requests\Dashboard\SaveRegisterRequest;
use App\Models\Register;
use App\Models\RegisterSession;
use App\Services\Pos\PosAccess;
use App\Services\Pos\PosSaleService;
use App\Services\Pos\PosSchema;
use App\Services\Pos\RegisterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Dashboard → Caja: registers, opening, cash in/out, closing count and the shift history (ERP Fase 4a). */
class RegisterSessionController extends Controller
{
    public function __construct(private PosSchema $schema, private PosAccess $access)
    {
    }

    public function storeRegister(SaveRegisterRequest $request, RegisterService $registers): RedirectResponse
    {
        abort_unless($this->schema->ready(), 404);
        $register = $registers->create($request->user()->currentStore(), $request->validated('name'));

        return redirect()->route('dashboard.pos.index', ['cajas' => 1])->with('success', "Agregamos «{$register->name}».");
    }

    public function open(OpenRegisterRequest $request, RegisterService $registers, int $register): RedirectResponse
    {
        abort_unless($this->schema->ready(), 404);
        $register = Register::where('store_id', $request->user()->currentStore()->id)->where('is_active', true)->findOrFail($register);
        $actor = $this->access->authorize($request->user()->currentStore(), null);
        $registers->open($register, (float) $request->validated('opening_float'), $request->user()->id, $actor);

        return redirect()->route('dashboard.pos.index', ['caja' => $register->id])->with('success', "Abriste «{$register->name}». ¡Buenas ventas!");
    }

    public function movement(RegisterMovementRequest $request, RegisterService $registers, int $session): RedirectResponse
    {
        $session = $this->owned($request, $session);
        $data = $request->validated();
        $actor = $this->access->authorize($request->user()->currentStore(), $data['type'] === 'out' ? 'cash_out' : null, $request->input('approver_pin'));
        $registers->movement($session, $data['type'], (float) $data['amount'], $data['reason'], $request->user()->id, $actor);

        return back()->with('success', $data['type'] === 'in' ? 'Anotamos el ingreso de efectivo.' : 'Anotamos el retiro de efectivo.');
    }

    public function closeForm(Request $request, RegisterService $registers, int $session)
    {
        $session = $this->owned($request, $session);
        if (!$session->isOpen()) {
            return redirect()->route('dashboard.pos.sessions.show', $session->id);
        }

        return view('dashboard.pos.close', ['session' => $session->load('register', 'movements.user:id,name'), 'summary' => $registers->summary($session), 'methods' => PosSaleService::METHODS]);
    }

    public function close(CloseRegisterRequest $request, RegisterService $registers, int $session): RedirectResponse
    {
        $session = $this->owned($request, $session);
        $actor = $this->access->authorize($request->user()->currentStore(), 'close', $request->input('approver_pin'));
        $session = $registers->close($session, (float) $request->validated('counted_cash'), $request->validated('notes'), $request->user()->id, $actor);
        $difference = (float) $session->difference;

        return redirect()->route('dashboard.pos.sessions.show', $session->id)->with($difference == 0.0 ? 'success' : 'error', match (true) {
            $difference == 0.0 => 'Caja cerrada: el efectivo cuadra.',
            $difference > 0    => 'Caja cerrada con S/ ' . number_format($difference, 2) . ' de más.',
            default            => 'Caja cerrada con S/ ' . number_format(abs($difference), 2) . ' de menos.',
        });
    }

    public function index(Request $request)
    {
        abort_unless($this->schema->ready(), 404);

        return view('dashboard.pos.sessions', [
            'sessions' => RegisterSession::where('store_id', $request->user()->currentStore()->id)
                ->with(['register:id,name', 'opener:id,name'])->withCount(['orders' => fn ($q) => $q->where('status', '!=', 'cancelled')])
                ->withSum(['orders' => fn ($q) => $q->where('status', '!=', 'cancelled')], 'total')
                ->latest('opened_at')->paginate(25),
        ]);
    }

    public function show(Request $request, RegisterService $registers, int $session)
    {
        $session = $this->owned($request, $session)->load(['register', 'opener:id,name', 'closer:id,name', 'movements.user:id,name']);

        return view('dashboard.pos.session', [
            'session' => $session,
            'summary' => $session->isOpen() ? $registers->summary($session) : $session->totals,
            'orders'  => $session->orders()->with($this->schema->staffReady() ? ['payments', 'staff:id,name', 'approver:id,name'] : ['payments'])->latest('id')->paginate(50),
            // Who sold how much in the shift (ERP Fase 4d), when the staff works with PIN.
            'byStaff' => $this->schema->staffReady()
                ? \App\Models\Order::where('register_session_id', $session->id)->where('status', '!=', 'cancelled')->whereNotNull('staff_id')
                    ->selectRaw('staff_id, COUNT(*) as sales, SUM(total) as amount')->groupBy('staff_id')->with('staff:id,name')->get()
                : collect(),
            'methods' => PosSaleService::METHODS,
        ]);
    }

    private function owned(Request $request, int $id): RegisterSession
    {
        abort_unless($this->schema->ready(), 404);

        return RegisterSession::where('store_id', $request->user()->currentStore()->id)->findOrFail($id);
    }
}
