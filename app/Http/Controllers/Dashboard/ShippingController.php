<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ShippingRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ShippingController extends Controller
{
    private function getStore()
    {
        return Auth::user()->currentStore();
    }

    public function index()
    {
        $store = $this->getStore();
        $rates = $store->shippingRates()->orderBy('country_code')->get();
        $countries = app(\App\Services\Geo\GeoCatalog::class)->countries();
        return view('dashboard.shipping.index', compact('store', 'rates', 'countries'));
    }

    public function store(Request $request)
    {
        $store = $this->getStore();

        $request->validate([
            'country_code' => 'required|string|max:3',
            'state'        => 'nullable|string|max:100',
            'cost'         => 'required|numeric|min:0',
        ]);

        $country = strtoupper($request->country_code);
        $geo = app(\App\Services\Geo\GeoCatalog::class);
        if ($country !== 'ALL' && !$geo->isValidCountry($country)) {
            return back()->withErrors(['country_code' => 'Ese país no existe.'])->withInput();
        }
        $state = $country === 'ALL' ? null : $geo->canonical($country, $request->state);
        if ($state && !$geo->isValidState($country, $state)) {
            return back()->withErrors(['state' => 'Ese departamento/estado no existe para el país elegido.'])->withInput();
        }

        // One rate per destination: re-saving a zone updates its cost instead of duplicating it.
        $store->shippingRates()->updateOrCreate(
            ['country_code' => $country, 'state' => $state],
            ['cost' => $request->cost, 'is_active' => true],
        );

        return back()->with('success', 'Tarifa de envío guardada correctamente.');
    }

    public function destroy(ShippingRate $shipping)
    {
        if ($shipping->store_id !== $this->getStore()->id) {
            abort(403);
        }
        $shipping->delete();
        return back()->with('success', 'Tarifa eliminada.');
    }
}
