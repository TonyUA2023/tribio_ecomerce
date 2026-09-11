<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    private function getStore() { return Auth::user()->store; }

    public function index()
    {
        $store      = $this->getStore();
        $categories = $store->categories()->withCount('activeProducts')->get();
        return view('dashboard.categories.index', compact('store', 'categories'));
    }

    public function create()
    {
        $store = $this->getStore();
        $parentCategories = $store->categories()->whereNull('parent_id')->get();
        return view('dashboard.categories.create', compact('store', 'parentCategories'));
    }

    public function store(Request $request)
    {
        $store = $this->getStore();
        $request->validate([
            'name'           => 'required|string|max:100',
            'parent_id'      => 'nullable|exists:categories,id',
            'icon'           => 'nullable|string|max:20',
            'color'          => 'nullable|string|max:7',
            'image'          => 'nullable|image|max:3072',
            'is_active'      => 'nullable|boolean',
            'show_in_header' => 'nullable|boolean',
            'is_featured'    => 'nullable|boolean',
        ]);

        $showInHeader = $request->boolean('show_in_header');
        if ($showInHeader) {
            $headerCount = $store->categories()->where('show_in_header', true)->count();
            if ($headerCount >= 5) {
                return back()->withInput()->with('error', 'Límite alcanzado: solo puedes mostrar hasta 5 categorías en el encabezado.');
            }
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('categories', 'public');
        }

        $store->categories()->create([
            'name'           => $request->name,
            'parent_id'      => $request->parent_id,
            'slug'           => Str::slug($request->name) . '-' . Str::random(3),
            'icon'           => $request->icon,
            'color'          => $request->color,
            'image_path'     => $imagePath,
            'is_active'      => $request->boolean('is_active', true),
            'show_in_header' => $showInHeader,
            'is_featured'    => $request->boolean('is_featured'),
        ]);

        return redirect()->route('dashboard.categorias.index')->with('success', 'Categoría creada.');
    }

    public function edit(Category $category)
    {
        $store = $this->getStore();
        abort_if($category->store_id !== $store->id, 403);
        $parentCategories = $store->categories()->whereNull('parent_id')->where('id', '!=', $category->id)->get();
        return view('dashboard.categories.edit', compact('store', 'category', 'parentCategories'));
    }

    public function update(Request $request, Category $category)
    {
        $store = $this->getStore();
        abort_if($category->store_id !== $store->id, 403);

        $request->validate([
            'name'           => 'required|string|max:100',
            'parent_id'      => 'nullable|exists:categories,id',
            'icon'           => 'nullable|string|max:20',
            'color'          => 'nullable|string|max:7',
            'image'          => 'nullable|image|max:3072',
            'is_active'      => 'nullable|boolean',
            'show_in_header' => 'nullable|boolean',
            'is_featured'    => 'nullable|boolean',
        ]);

        $showInHeader = $request->boolean('show_in_header');
        if ($showInHeader && !$category->show_in_header) {
            $headerCount = $store->categories()->where('show_in_header', true)->count();
            if ($headerCount >= 5) {
                return back()->withInput()->with('error', 'Límite alcanzado: solo puedes mostrar hasta 5 categorías en el encabezado.');
            }
        }

        $imagePath = $category->image_path;
        if ($request->hasFile('image')) {
            if ($category->image_path) {
                Storage::disk('public')->delete($category->image_path);
            }
            $imagePath = $request->file('image')->store('categories', 'public');
        } elseif ($request->boolean('remove_image')) {
            if ($category->image_path) {
                Storage::disk('public')->delete($category->image_path);
            }
            $imagePath = null;
        }

        // Prevent circular reference (a category cannot be its own parent)
        $parentId = $request->parent_id == $category->id ? null : $request->parent_id;

        $category->update([
            'name'           => $request->name,
            'parent_id'      => $parentId,
            'icon'           => $request->icon,
            'color'          => $request->color,
            'image_path'     => $imagePath,
            'is_active'      => $request->boolean('is_active'),
            'show_in_header' => $showInHeader,
            'is_featured'    => $request->boolean('is_featured'),
        ]);

        return redirect()->route('dashboard.categorias.index')->with('success', 'Categoría actualizada.');
    }

    public function toggleHeader(Category $category)
    {
        $store = $this->getStore();
        abort_if($category->store_id !== $store->id, 403);

        if (!$category->show_in_header) {
            $headerCount = $store->categories()->where('show_in_header', true)->count();
            if ($headerCount >= 5) {
                if (request()->ajax() || request()->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Límite alcanzado: solo puedes mostrar un máximo de 5 categorías en el encabezado.',
                    ], 422);
                }
                return back()->with('error', 'Límite alcanzado: solo puedes mostrar un máximo de 5 categorías en el encabezado.');
            }

            $category->update(['show_in_header' => true]);
            $msg = "Categoría '{$category->name}' añadida al encabezado.";
        } else {
            $category->update(['show_in_header' => false]);
            $msg = "Categoría '{$category->name}' removida del encabezado.";
        }

        if (request()->ajax() || request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'show_in_header' => $category->show_in_header,
                'header_count' => $store->categories()->where('show_in_header', true)->count(),
                'message' => $msg,
            ]);
        }

        return back()->with('success', $msg);
    }

    public function toggleFeatured(Category $category)
    {
        $store = $this->getStore();
        abort_if($category->store_id !== $store->id, 403);

        $category->update(['is_featured' => !$category->is_featured]);
        $msg = $category->is_featured
            ? "Categoría '{$category->name}' marcada como destacada."
            : "Categoría '{$category->name}' desmarcada de destacadas.";

        if (request()->ajax() || request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'is_featured' => $category->is_featured,
                'message' => $msg,
            ]);
        }

        return back()->with('success', $msg);
    }

    public function destroy(Category $category)
    {
        $store = $this->getStore();
        abort_if($category->store_id !== $store->id, 403);
        if ($category->image_path) {
            Storage::disk('public')->delete($category->image_path);
        }
        $category->delete();
        return back()->with('success', 'Categoría eliminada.');
    }

    public function reorder(Request $request)
    {
        $store = $this->getStore();
        foreach ($request->order as $index => $id) {
            $store->categories()->where('id', $id)->update(['sort_order' => $index]);
        }
        return response()->json(['success' => true]);
    }
}
