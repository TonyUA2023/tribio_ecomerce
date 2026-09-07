<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class BrandController extends Controller
{
    private function getStore()
    {
        return Auth::user()->store;
    }

    public function index()
    {
        $store  = $this->getStore();
        $brands = $store->brands()->withCount('products')->get();
        return view('dashboard.brands.index', compact('store', 'brands'));
    }

    public function create()
    {
        $store = $this->getStore();
        return view('dashboard.brands.create', compact('store'));
    }

    public function store(Request $request)
    {
        $store = $this->getStore();
        $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $store->brands()->create([
            'name' => $request->name,
            'slug' => Str::slug($request->name) . '-' . Str::random(3),
        ]);

        return redirect()->route('dashboard.marcas.index')->with('success', 'Marca creada correctamente.');
    }

    public function edit(Brand $brand)
    {
        $store = $this->getStore();
        abort_if($brand->store_id !== $store->id, 403);
        return view('dashboard.brands.edit', compact('store', 'brand'));
    }

    public function update(Request $request, Brand $brand)
    {
        $store = $this->getStore();
        abort_if($brand->store_id !== $store->id, 403);

        $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $brand->update([
            'name' => $request->name,
            'slug' => Str::slug($request->name) . '-' . Str::random(3),
        ]);

        return redirect()->route('dashboard.marcas.index')->with('success', 'Marca actualizada.');
    }

    public function destroy(Brand $brand)
    {
        $store = $this->getStore();
        abort_if($brand->store_id !== $store->id, 403);
        
        $brand->delete();
        return back()->with('success', 'Marca eliminada.');
    }
}
