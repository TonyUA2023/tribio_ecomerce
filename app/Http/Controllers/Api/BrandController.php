<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesCurrentStore;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BrandController extends Controller
{
    use ResolvesCurrentStore;

    public function index(): JsonResponse
    {
        $brands = $this->currentStore()->brands()->withCount('products')->orderBy('name')->get(['id', 'name']);

        return response()->json(['data' => $brands]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => 'required|string|max:100']);

        $brand = $this->currentStore()->brands()->create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']) . '-' . Str::random(3),
        ]);

        return response()->json(['message' => 'Marca creada correctamente.', 'brand' => $brand->only(['id', 'name'])], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['name' => 'required|string|max:100']);
        $brand = $this->currentStore()->brands()->findOrFail($id);

        $brand->update(['name' => $data['name'], 'slug' => Str::slug($data['name']) . '-' . Str::random(3)]);

        return response()->json(['message' => 'Marca actualizada.', 'brand' => $brand->only(['id', 'name'])]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->currentStore()->brands()->findOrFail($id)->delete();

        return response()->json(['message' => 'Marca eliminada.']);
    }
}
