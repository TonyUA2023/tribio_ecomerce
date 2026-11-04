<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\SaveSalesDocumentsRequest;
use App\Services\Pos\PosSchema;
use App\Services\Pos\SalesDocuments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Dashboard → Caja → Comprobantes: RUC, razón social and the series of nota de venta, boleta and factura. */
class SalesDocumentsController extends Controller
{
    public function __construct(private PosSchema $schema, private SalesDocuments $documents)
    {
    }

    public function edit(Request $request)
    {
        $this->guard();
        $store = $request->user()->currentStore();

        return view('dashboard.pos.documents', ['store' => $store, 'series' => $this->documents->series($store), 'types' => SalesDocuments::TYPES]);
    }

    public function update(SaveSalesDocumentsRequest $request): RedirectResponse
    {
        $this->guard();
        $store = $request->user()->currentStore();
        $data = $request->validated();

        $store->update(['ruc' => $data['ruc'] ?? null, 'legal_name' => filled($data['legal_name'] ?? null) ? trim($data['legal_name']) : null]);

        foreach ($this->documents->series($store) as $type => $row) {
            $series = $data['series'][$type];
            if ($series !== $row->series) {
                // The correlative belongs to the series: a new series starts again at 1.
                $row->update(['series' => $series, 'last_number' => 0]);
            }
        }

        return redirect()->route('dashboard.pos.documents.edit')->with('success', 'Guardamos los datos de tus comprobantes.');
    }

    private function guard(): void
    {
        abort_unless($this->schema->ready() && $this->documents->ready(), 404);
    }
}
