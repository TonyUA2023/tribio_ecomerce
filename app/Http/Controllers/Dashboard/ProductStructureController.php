<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\SaveKitRequest;
use App\Http\Requests\Dashboard\SaveProductUnitRequest;
use App\Models\Product;
use App\Services\Inventory\InventorySchema;
use App\Services\Inventory\ProductStructureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/** Inventario → producto: packagings and combo components (ProductStructureService). */
class ProductStructureController extends Controller
{
    public function __construct(private InventorySchema $schema)
    {
    }

    public function storeUnit(SaveProductUnitRequest $request, ProductStructureService $service, int $product): RedirectResponse
    {
        $product = $this->owned($product);
        $unit = $service->addUnit($product, $request->input('name'), $request->integer('factor'), $request->input('barcode'));

        return back()->with('success', "Agregamos la presentación {$unit->name} ({$unit->factor} und).");
    }

    public function destroyUnit(int $product, int $unit): RedirectResponse
    {
        $this->owned($product)->units()->findOrFail($unit)->delete();

        return back()->with('success', 'Quitamos la presentación.');
    }

    public function updateKit(SaveKitRequest $request, ProductStructureService $service, int $product): RedirectResponse
    {
        $product = $this->owned($product);
        $components = $request->components();
        $service->saveComponents($product, $components);

        return back()->with('success', $components === []
            ? "«{$product->name}» ya no es un combo."
            : "Guardamos el combo «{$product->name}». Su stock se calcula con el de sus productos.");
    }

    private function owned(int $id): Product
    {
        abort_unless($this->schema->unitsReady(), 404);

        return Auth::user()->currentStore()->products()->findOrFail($id);
    }
}
