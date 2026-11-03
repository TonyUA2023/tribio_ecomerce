<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\SaveStaffRequest;
use App\Models\StoreStaff;
use App\Services\Pos\PosAccess;
use App\Services\Pos\PosCatalog;
use App\Services\Pos\PosSchema;
use App\Services\Pos\StaffService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Dashboard → Caja → Personal y modo caja (ERP Fase 4d): cashiers and managers with PIN,
 * handing a device to the staff, unlocking and locking the register; plus what the
 * register needs to keep selling without connection (catalog and a fresh CSRF token).
 */
class PosStaffController extends Controller
{
    public function __construct(private PosSchema $schema, private PosAccess $access)
    {
    }

    public function index(Request $request)
    {
        abort_unless($this->schema->staffReady(), 404);

        return view('dashboard.pos.staff', [
            'staff'      => StoreStaff::where('store_id', $request->user()->currentStore()->id)->orderByDesc('is_active')->orderBy('name')->get(),
            'roles'      => StaffService::ROLES,
            'restricted' => StaffService::RESTRICTED,
            'idle'       => PosAccess::IDLE_MINUTES,
        ]);
    }

    public function store(SaveStaffRequest $request, StaffService $staff): RedirectResponse
    {
        abort_unless($this->schema->staffReady(), 404);
        $person = $staff->create($request->user()->currentStore(), $request->validated('name'), $request->validated('role'), $request->validated('pin'));

        return back()->with('success', "Agregamos a {$person->name}.");
    }

    public function update(SaveStaffRequest $request, StaffService $staff, int $person): RedirectResponse
    {
        abort_unless($this->schema->staffReady(), 404);
        $person = StoreStaff::where('store_id', $request->user()->currentStore()->id)->findOrFail($person);
        $staff->update($person, $request->validated());

        return back()->with('success', "Guardamos los cambios de {$person->name}.");
    }

    /** Hands this device to the staff: from now on only the register opens, with a PIN. */
    public function enableKiosk(Request $request): RedirectResponse
    {
        abort_unless($this->schema->staffReady(), 404);
        $this->access->enable($request->user()->currentStore());

        return redirect()->route('dashboard.pos.index')->with('success', 'Modo caja activado en este dispositivo. Cada persona entra con su PIN.');
    }

    public function disableKiosk(Request $request): RedirectResponse
    {
        abort_unless($this->schema->staffReady(), 404);
        $request->validate(['pin' => ['required', 'digits_between:4,6']], ['pin.required' => 'Ingresa el PIN de un encargado.']);
        $manager = $this->access->disable($request->user()->currentStore(), $request->input('pin'));

        return redirect()->route('dashboard.pos.staff.index')->with('success', "{$manager->name} salió del modo caja.");
    }

    public function unlock(Request $request): RedirectResponse|JsonResponse
    {
        abort_unless($this->schema->staffReady(), 404);
        $request->validate(['pin' => ['required', 'digits_between:4,6']], ['pin.required' => 'Ingresa tu PIN.', 'pin.digits_between' => 'El PIN tiene de 4 a 6 números.']);
        $person = $this->access->unlock($request->user()->currentStore(), $request->input('pin'));

        return $request->expectsJson()
            ? response()->json(['name' => $person->name, 'role' => $person->role])
            : redirect()->route('dashboard.pos.index', array_filter(['caja' => $request->integer('caja') ?: null]))->with('success', "Hola, {$person->name}.");
    }

    public function lock(Request $request): RedirectResponse
    {
        abort_unless($this->schema->staffReady(), 404);
        $this->access->lock($request->user()->currentStore());

        return redirect()->route('dashboard.pos.index');
    }

    /** A fresh CSRF token: sales queued without connection are sent with it when the connection is back. */
    public function token(Request $request): JsonResponse
    {
        return response()->json(['token' => csrf_token()]);
    }

    /** The whole sellable catalog, kept on the device so the register can search without connection. */
    public function catalog(Request $request, PosCatalog $catalog): JsonResponse
    {
        abort_unless($this->schema->ready(), 404);

        return response()->json(['products' => $catalog->all($request->user()->currentStore()), 'at' => now()->toIso8601String()]);
    }
}
