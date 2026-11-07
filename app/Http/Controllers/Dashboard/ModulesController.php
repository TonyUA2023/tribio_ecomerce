<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Configuración → Módulos: the owner decides what the business uses. Tienda virtual, punto de venta (POS)
 * and CRM are independent — the POS works without the CRM and without a storefront, the storefront without
 * the POS — and the menu only shows what is on. Productos, inventario, compras and ganancias are shared by all.
 * Turning a module off hides it; nothing is deleted, so turning it on again finds everything as it was.
 */
class ModulesController extends Controller
{
    public function edit(Request $request)
    {
        $store = $this->store($request);

        return view('dashboard.modules', [
            'store'    => $store,
            'ready'    => Store::modesReady(),
            'crmReady' => Store::crmReady(),
            'state'    => [
                'store'  => $store->hasStorefront(),
                'pos'    => $store->hasPos(),
                'simple' => $store->isSimple(),
                'crm'    => $store->hasCrm(),
            ],
            'storeUrl' => route('store.show', $store->slug),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $store = $this->store($request);
        abort_unless(Store::modesReady(), 404);

        $withStore = $request->boolean('store');
        $withPos = $request->boolean('pos');
        if (!$withStore && !$withPos) {
            throw ValidationException::withMessages(['pos' => 'Deja encendido al menos uno: la tienda virtual o el punto de venta.']);
        }

        // Tienda virtual + POS = both; the quick screen ("modo simple") is the POS on its own, for a phone.
        $mode = match (true) {
            $withStore && $withPos => Store::MODE_BOTH,
            $withStore             => Store::MODE_ONLINE,
            $request->boolean('simple') => Store::MODE_SIMPLE,
            default                => Store::MODE_POS,
        };

        $openedStore = $withStore && !$store->hasStorefront();
        $store->update(['mode' => $mode] + (Store::crmReady() ? ['crm_enabled' => $request->boolean('crm')] : []));

        $message = $openedStore
            ? 'Abriste tu tienda virtual. Revísala y personalízala en «Mi tienda».'
            : 'Guardamos los módulos de tu negocio. El menú ya muestra solo lo que usas.';

        return redirect()->route($openedStore ? 'dashboard.store.edit' : 'dashboard.modules.edit')->with('success', $message);
    }

    private function store(Request $request): Store
    {
        $store = $request->user()->currentStore();
        abort_unless($store, 404);

        return $store;
    }
}
