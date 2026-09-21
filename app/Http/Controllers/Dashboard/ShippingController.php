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
        $supported = \App\Helpers\CurrencyHelper::supportedCountries();
        return view('dashboard.shipping.index', compact('store', 'rates', 'supported'));
    }

    public function store(Request $request)
    {
        $store = $this->getStore();

        $request->validate([
            'country_code' => 'required|string|max:3',
            'state'        => 'nullable|string|max:100',
            'cost'         => 'required|numeric|min:0',
        ]);

        $store->shippingRates()->create([
            'country_code' => strtoupper($request->country_code),
            'state'        => $request->state,
            'cost'         => $request->cost,
            'is_active'    => true,
        ]);

        return back()->with('success', 'Tarifa de envío agregada correctamente.');
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
