<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\SavePriceListRequest;
use App\Models\Product;
use App\Services\Pos\PriceListService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Dashboard → Listas de precios: mayorista and distribuidor prices next to retail (ERP Fase 4c). */
class PriceListController extends Controller
{
    public function __construct(private PriceListService $lists)
    {
    }

    public function index(Request $request)
    {
        abort_unless($this->lists->ready(), 404);
        $search = trim((string) $request->query('q'));
        $products = Product::where('store_id', $request->user()->currentStore()->id)
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "{$search}%")))
            ->orderBy('name')->paginate(50)->withQueryString();

        return view('dashboard.price-lists.index', [
            'products' => $products,
            'prices'   => $this->lists->prices($products->pluck('id')->all()),
            'lists'    => PriceListService::LISTS,
        ]);
    }

    public function update(SavePriceListRequest $request): RedirectResponse
    {
        abort_unless($this->lists->ready(), 404);
        $this->lists->save($request->user()->currentStore(), $request->validated('rows'));

        return back()->with('success', 'Guardamos los precios.');
    }

    public function percent(Request $request): RedirectResponse
    {
        abort_unless($this->lists->ready(), 404);
        $data = $request->validate([
            'list'      => ['required', 'in:' . implode(',', array_keys(PriceListService::LISTS))],
            'percent'   => ['required', 'numeric', 'min:0', 'max:90'],
            'overwrite' => ['nullable', 'boolean'],
        ], ['percent.required' => 'Escribe el porcentaje de descuento.', 'percent.max' => 'El descuento no puede pasar del 90 %.']);
        $count = $this->lists->applyPercent($request->user()->currentStore(), $data['list'], (float) $data['percent'], $request->boolean('overwrite'));

        return back()->with('success', $count === 1 ? 'Pusimos precio a 1 producto.' : "Pusimos precio a {$count} productos.");
    }
}
