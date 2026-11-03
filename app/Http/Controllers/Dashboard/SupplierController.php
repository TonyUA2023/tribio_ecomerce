<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\SaveSupplierRequest;
use App\Models\Supplier;
use App\Services\Purchasing\PurchasingSchema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Dashboard → Compras → Proveedores. */
class SupplierController extends Controller
{
    public function __construct(private PurchasingSchema $schema)
    {
    }

    public function index(Request $request)
    {
        abort_unless($this->schema->ready(), 404);
        $store = Auth::user()->currentStore();
        $search = trim((string) $request->query('buscar'));

        return view('dashboard.suppliers.index', [
            'suppliers' => $store->suppliers()
                ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('document_number', 'like', "%{$search}%")))
                ->withCount('purchaseOrders')
                ->withSum(['purchaseOrders as received_total' => fn ($q) => $q->where('status', 'received')], 'total')
                ->orderBy('name')->paginate(25)->withQueryString(),
            'search'    => $search,
        ]);
    }

    public function store(SaveSupplierRequest $request): RedirectResponse
    {
        abort_unless($this->schema->ready(), 404);
        $supplier = $request->user()->currentStore()->suppliers()->create($request->validated());

        // From the purchase form ("+ nuevo proveedor") go back there with it selected.
        if ($request->boolean('return_to_purchase')) {
            return redirect()->route('dashboard.compras.create', ['proveedor' => $supplier->id])->with('success', "Agregamos a {$supplier->name}.");
        }

        return redirect()->route('dashboard.proveedores.index')->with('success', "Agregamos a {$supplier->name}.");
    }

    public function edit(int $supplier)
    {
        $supplier = $this->owned($supplier);

        return view('dashboard.suppliers.edit', [
            'supplier' => $supplier,
            'orders'   => $supplier->purchaseOrders()->latest('id')->limit(20)->get(),
        ]);
    }

    public function update(SaveSupplierRequest $request, int $supplier): RedirectResponse
    {
        $supplier = $this->owned($supplier);
        $supplier->update($request->validated());

        return redirect()->route('dashboard.proveedores.edit', $supplier->id)->with('success', 'Guardamos los datos del proveedor.');
    }

    public function destroy(int $supplier): RedirectResponse
    {
        $supplier = $this->owned($supplier);
        if ($supplier->purchaseOrders()->exists()) {
            return back()->with('error', 'Este proveedor tiene compras registradas: no se puede eliminar.');
        }
        $supplier->delete();

        return redirect()->route('dashboard.proveedores.index')->with('success', 'Eliminamos el proveedor.');
    }

    private function owned(int $id): Supplier
    {
        abort_unless($this->schema->ready(), 404);

        return Auth::user()->currentStore()->suppliers()->findOrFail($id);
    }
}
