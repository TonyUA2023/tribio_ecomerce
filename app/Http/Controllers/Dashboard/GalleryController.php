<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\GalleryItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class GalleryController extends Controller
{
    private function getStore() { return Auth::user()->store; }

    public function index()
    {
        $store = $this->getStore();
        $items = $store->galleryItems;
        return view('dashboard.gallery.index', compact('store', 'items'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'images'    => 'required|array|min:1',
            'images.*'  => 'image|mimes:png,jpg,jpeg,webp|max:5120',
            'type'      => 'nullable|string|in:photo,banner,hero',
        ]);

        $store = $this->getStore();
        $lastOrder = $store->galleryItems()->max('sort_order') ?? -1;

        foreach ($request->file('images') as $i => $file) {
            $path = $file->store("stores/{$store->id}/gallery", 'public');
            $store->galleryItems()->create([
                'image_path'  => $path,
                'type'        => $request->type ?? 'photo',
                'sort_order'  => $lastOrder + $i + 1,
                'is_active'   => true,
            ]);
        }

        return back()->with('success', count($request->file('images')) . ' imágenes subidas correctamente.');
    }

    public function destroy(GalleryItem $item)
    {
        $store = $this->getStore();
        abort_if($item->store_id !== $store->id, 403);

        Storage::disk('public')->delete($item->image_path);
        $item->delete();

        return back()->with('success', 'Imagen eliminada.');
    }

    public function reorder(Request $request)
    {
        $store = $this->getStore();
        foreach ($request->order as $index => $id) {
            $store->galleryItems()->where('id', $id)->update(['sort_order' => $index]);
        }
        return response()->json(['success' => true]);
    }
}
