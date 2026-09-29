<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesCurrentStore;
use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\GalleryItemResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class GalleryController extends Controller
{
    use ResolvesCurrentStore;

    public function index(): JsonResponse
    {
        return response()->json(['data' => GalleryItemResource::collection($this->currentStore()->galleryItems)]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'images'   => 'required|array|min:1|max:10',
            'images.*' => 'image|mimes:png,jpg,jpeg,webp|max:5120',
            'type'     => 'nullable|string|in:photo,banner,hero',
        ]);

        $store = $this->currentStore();
        $last = $store->galleryItems()->max('sort_order') ?? -1;
        $created = [];

        foreach ($request->file('images') as $i => $file) {
            $created[] = $store->galleryItems()->create([
                'image_path' => $file->store("stores/{$store->id}/gallery", 'public'),
                'type'       => $request->input('type', 'photo'),
                'sort_order' => $last + $i + 1,
                'is_active'  => true,
            ]);
        }

        return response()->json([
            'message' => count($created) === 1 ? 'Imagen subida correctamente.' : count($created) . ' imágenes subidas correctamente.',
            'data'    => GalleryItemResource::collection(collect($created)),
        ], 201);
    }

    public function destroy(int $id): JsonResponse
    {
        $item = $this->currentStore()->galleryItems()->findOrFail($id);
        Storage::disk('public')->delete($item->image_path);
        $item->delete();

        return response()->json(['message' => 'Imagen eliminada.']);
    }

    public function toggleHero(int $id): JsonResponse
    {
        $item = $this->currentStore()->galleryItems()->findOrFail($id);
        $item->update(['type' => $item->type === 'hero' ? 'photo' : 'hero']);

        return response()->json([
            'message' => $item->type === 'hero'
                ? 'Imagen agregada al carrusel principal.'
                : 'Imagen quitada del carrusel principal.',
            'item'    => new GalleryItemResource($item),
        ]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $request->validate(['order' => 'required|array', 'order.*' => 'integer']);
        $store = $this->currentStore();

        foreach ($request->order as $index => $id) {
            $store->galleryItems()->where('id', $id)->update(['sort_order' => $index]);
        }

        return response()->json(['message' => 'Orden actualizado.']);
    }
}
