<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\DocumentSeries;
use App\Models\Register;
use App\Services\Pos\RegisterService;
use App\Services\Pos\SalesDocuments;
use App\Services\Sunat\Branches;
use App\Services\Sunat\SunatSchema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Caja → Comprobantes → Sedes y cajas: the fiscal establishments of the business, which sede each cash
 * register sells from, and the series (B001, F002…) of the whole business, of a sede or of a single caja.
 */
class BranchController extends Controller
{
    public function __construct(private SunatSchema $schema, private Branches $branches, private SalesDocuments $documents)
    {
    }

    public function index(Request $request, RegisterService $registers)
    {
        $store = $this->current($request);
        $branches = $this->branches->all($store);

        return view('dashboard.pos.branches', [
            'store'     => $store,
            'branches'  => $branches,
            'registers' => $registers->registers($store),
            'series'    => DocumentSeries::where('store_id', $store->id)->with(['branch:id,name,sunat_code', 'register:id,name'])->orderBy('type')->orderBy('series')->get(),
            'types'     => SalesDocuments::TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->branches->save($this->current($request), $this->validated($request));

        return back()->with('success', 'Agregamos la sede.');
    }

    public function update(Request $request, int $branch): RedirectResponse
    {
        $store = $this->current($request);
        $this->branches->save($store, $this->validated($request), $this->owned($store, $branch));

        return back()->with('success', 'Guardamos la sede.');
    }

    public function destroy(Request $request, int $branch): RedirectResponse
    {
        $this->branches->delete($this->owned($this->current($request), $branch));

        return back()->with('success', 'Eliminamos la sede.');
    }

    public function assign(Request $request, int $register): RedirectResponse
    {
        $store = $this->current($request);
        $model = Register::where('store_id', $store->id)->findOrFail($register);
        $this->branches->assign($model, $request->filled('branch_id') ? $request->integer('branch_id') : null);

        return back()->with('success', "«{$model->name}» vende desde su nueva sede.");
    }

    /** A new series for a sede or for one caja, starting at the number you say (to continue what you had on paper). */
    public function storeSeries(Request $request): RedirectResponse
    {
        $store = $this->current($request);
        $data = $request->validate([
            'type'        => ['required', Rule::in(array_keys(SalesDocuments::TYPES))],
            'series'      => ['required', 'string', 'size:4'],
            'scope'       => ['required', Rule::in(['branch', 'register'])],
            'branch_id'   => ['nullable', 'integer'],
            'register_id' => ['nullable', 'integer'],
            'start'       => ['nullable', 'integer', 'min:1', 'max:99999999'],
        ]);
        $series = strtoupper($data['series']);
        $prefix = ['boleta' => 'B', 'factura' => 'F'][$data['type']] ?? null;
        if (!preg_match($prefix ? "/^{$prefix}[A-Z0-9]{3}$/" : '/^[A-Z0-9]{4}$/', $series)) {
            throw ValidationException::withMessages(['series' => $prefix ? "La serie de {$data['type']} empieza con {$prefix} (ej. {$prefix}002)." : 'La serie tiene 4 letras o números.']);
        }
        if (DocumentSeries::where('store_id', $store->id)->where('type', $data['type'])->where('series', $series)->exists()) {
            throw ValidationException::withMessages(['series' => "La serie {$series} ya existe."]);
        }

        $scope = ['branch_id' => null, 'register_id' => null];
        if ($data['scope'] === 'register') {
            $scope['register_id'] = Register::where('store_id', $store->id)->findOrFail((int) $data['register_id'])->id;
        } else {
            $scope['branch_id'] = Branch::where('store_id', $store->id)->findOrFail((int) $data['branch_id'])->id;
        }
        if (DocumentSeries::where('store_id', $store->id)->where('type', $data['type'])->where($scope)->exists()) {
            throw ValidationException::withMessages(['series' => 'Ya hay una serie de ese tipo para esa sede o caja: una sola por tipo.']);
        }

        DocumentSeries::create(['store_id' => $store->id, 'type' => $data['type'], 'series' => $series, 'last_number' => max(0, (int) ($data['start'] ?? 1) - 1)] + $scope);

        return back()->with('success', "Creamos la serie {$series}.");
    }

    public function destroySeries(Request $request, int $series): RedirectResponse
    {
        $row = DocumentSeries::where('store_id', $this->current($request)->id)->findOrFail($series);
        if (!$row->branch_id && !$row->register_id) {
            throw ValidationException::withMessages(['series' => 'Las series del negocio no se eliminan: se cambian en Datos y series.']);
        }
        if ($row->last_number > 0) {
            throw ValidationException::withMessages(['series' => 'Esta serie ya emitió comprobantes: no se puede eliminar.']);
        }
        $row->delete();

        return back()->with('success', 'Eliminamos la serie.');
    }

    private function current(Request $request): \App\Models\Store
    {
        abort_unless($this->schema->ready() && $this->documents->ready(), 404);
        $store = $request->user()->currentStore();
        abort_unless($store, 404);

        return $store;
    }

    private function owned(\App\Models\Store $store, int $id): Branch
    {
        return Branch::where('store_id', $store->id)->findOrFail($id);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name'       => ['required', 'string', 'max:80'],
            'sunat_code' => ['nullable', 'digits_between:1,4'],
            'address'    => ['nullable', 'string', 'max:200'],
            'ubigeo'     => ['nullable', 'digits:6'],
            'department' => ['nullable', 'string', 'max:60'],
            'province'   => ['nullable', 'string', 'max:60'],
            'district'   => ['nullable', 'string', 'max:60'],
            'phone'      => ['nullable', 'string', 'max:30'],
            'is_active'  => ['nullable', 'boolean'],
        ], [
            'ubigeo.digits' => 'El ubigeo tiene 6 dígitos (búscalo en el buscador de ubigeos de INEI o SUNAT).',
            'sunat_code.digits_between' => 'El código de establecimiento son números (ej. 0001).',
        ]);
    }
}
