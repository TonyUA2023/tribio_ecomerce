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

        $store->update(['ruc' => $data['ruc'] ?? null, 'legal_name' => filled($data['legal_name'] ?? null) ? trim($data['legal_name']) : null]
            // The business's basics, printed on every ticket (only what the form sent: nothing is erased by omission).
            + array_map(fn ($value) => filled($value) ? trim($value) : null, array_intersect_key($data, array_flip(['phone', 'address', 'city'])))
            + (isset($data['name']) ? ['name' => trim($data['name'])] : []));

        foreach ($this->documents->series($store) as $type => $row) {
            $series = $data['series'][$type];
            if ($series !== $row->series) {
                if (\App\Models\DocumentSeries::where('store_id', $store->id)->where('type', $type)->where('series', $series)->where('id', '!=', $row->id)->exists()) {
                    throw \Illuminate\Validation\ValidationException::withMessages(["series.{$type}" => "La serie {$series} ya la usa una caja o sede: elige otra."]);
                }
                // The correlative belongs to the series: a new one starts at 1, and one used before goes on after its last number.
                $row->update(['series' => $series, 'last_number' => (int) \App\Models\Order::where('store_id', $store->id)->where('document_type', $type)->where('document_series', $series)->max('document_number')]);
            }
        }

        return redirect()->route('dashboard.pos.documents.edit')->with('success', 'Guardamos los datos de tus comprobantes.');
    }

    private function guard(): void
    {
        abort_unless($this->schema->ready() && $this->documents->ready(), 404);
    }
}
