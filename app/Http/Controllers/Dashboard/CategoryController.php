<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            'name'       => 'required|string|max:100',
            'parent_id'  => 'nullable|exists:categories,id',
            'icon'       => 'nullable|string|max:10',
            'color'      => 'nullable|string|max:7',
            'is_active'  => 'nullable|boolean',
        ]);

        $store->categories()->create([
            'name'      => $request->name,
            'parent_id' => $request->parent_id,
            'slug'      => Str::slug($request->name) . '-' . Str::random(3),
            'icon'      => $request->icon,
            'color'     => $request->color,
            'is_active' => $request->boolean('is_active', true),
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
            'name'      => 'required|string|max:100',
            'parent_id' => 'nullable|exists:categories,id',
            'icon'      => 'nullable|string|max:10',
            'color'     => 'nullable|string|max:7',
            'is_active' => 'nullable|boolean',
        ]);

        // Prevent circular reference (a category cannot be its own parent)
        $parentId = $request->parent_id == $category->id ? null : $request->parent_id;

        $category->update([
            'name'      => $request->name,
            'parent_id' => $parentId,
            'icon'      => $request->icon,
            'color'     => $request->color,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('dashboard.categorias.index')->with('success', 'Categoría actualizada.');
    }

    public function destroy(Category $category)
    {
        $store = $this->getStore();
        abort_if($category->store_id !== $store->id, 403);
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
