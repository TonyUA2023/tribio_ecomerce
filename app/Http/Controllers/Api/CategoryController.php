<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesCurrentStore;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SaveCategoryRequest;
use App\Http\Resources\Mobile\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    use ResolvesCurrentStore;

    /** Same limit the web dashboard enforces: the storefront header has room for 5. */
    private const HEADER_LIMIT = 5;

    private function owned(int $id): Category
    {
        return $this->currentStore()->categories()->findOrFail($id);
    }

    public function index(): JsonResponse
    {
        $categories = $this->currentStore()->categories()->withCount('activeProducts')->orderBy('sort_order')->get();

        return response()->json([
            'data'         => CategoryResource::collection($categories),
            'header_count' => $categories->where('show_in_header', true)->count(),
            'header_limit' => self::HEADER_LIMIT,
        ]);
    }

    public function store(SaveCategoryRequest $request): JsonResponse
    {
        $store = $this->currentStore();
        $showInHeader = $request->boolean('show_in_header');

        if ($showInHeader && $this->headerCount() >= self::HEADER_LIMIT) {
            return $this->headerLimitReached();
        }

        $category = $store->categories()->create([
            'name'           => $request->name,
            'parent_id'      => $request->parent_id,
            'slug'           => Str::slug($request->name) . '-' . Str::random(3),
            'icon'           => $request->icon,
            'color'          => $request->color,
            'image_path'     => $request->hasFile('image') ? $request->file('image')->store('categories', 'public') : null,
            'is_active'      => $request->boolean('is_active', true),
            'show_in_header' => $showInHeader,
            'is_featured'    => $request->boolean('is_featured'),
            'sort_order'     => ($store->categories()->max('sort_order') ?? -1) + 1,
        ]);

        return response()->json(['message' => 'Categoría creada.', 'category' => new CategoryResource($category)], 201);
    }

    public function update(SaveCategoryRequest $request, int $id): JsonResponse
    {
        $category = $this->owned($id);
        $showInHeader = $request->boolean('show_in_header');

        if ($showInHeader && !$category->show_in_header && $this->headerCount() >= self::HEADER_LIMIT) {
            return $this->headerLimitReached();
        }

        $imagePath = $category->image_path;
        if ($request->hasFile('image')) {
            $this->deleteImage($category);
            $imagePath = $request->file('image')->store('categories', 'public');
        } elseif ($request->boolean('remove_image')) {
            $this->deleteImage($category);
            $imagePath = null;
        }

        $category->update([
            'name'           => $request->name,
            // A category can never be its own parent.
            'parent_id'      => (int) $request->parent_id === $category->id ? null : $request->parent_id,
            'icon'           => $request->icon,
            'color'          => $request->color,
            'image_path'     => $imagePath,
            'is_active'      => $request->boolean('is_active'),
            'show_in_header' => $showInHeader,
            'is_featured'    => $request->boolean('is_featured'),
        ]);

        return response()->json(['message' => 'Categoría actualizada.', 'category' => new CategoryResource($category->refresh())]);
    }

    public function destroy(int $id): JsonResponse
    {
        $category = $this->owned($id);
        $this->deleteImage($category);
        $category->delete();

        return response()->json(['message' => 'Categoría eliminada.']);
    }

    public function toggleHeader(int $id): JsonResponse
    {
        $category = $this->owned($id);

        if (!$category->show_in_header && $this->headerCount() >= self::HEADER_LIMIT) {
            return $this->headerLimitReached();
        }
        $category->update(['show_in_header' => !$category->show_in_header]);

        return response()->json([
            'message'  => $category->show_in_header
                ? "«{$category->name}» se muestra en el encabezado."
                : "«{$category->name}» ya no está en el encabezado.",
            'category' => new CategoryResource($category),
            'header_count' => $this->headerCount(),
        ]);
    }

    public function toggleFeatured(int $id): JsonResponse
    {
        $category = $this->owned($id);
        $category->update(['is_featured' => !$category->is_featured]);

        return response()->json([
            'message'  => $category->is_featured ? "«{$category->name}» quedó destacada." : "«{$category->name}» ya no es destacada.",
            'category' => new CategoryResource($category),
        ]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $request->validate(['order' => 'required|array', 'order.*' => 'integer']);
        $store = $this->currentStore();

        foreach ($request->order as $index => $id) {
            $store->categories()->where('id', $id)->update(['sort_order' => $index]);
        }

        return response()->json(['message' => 'Orden actualizado.']);
    }

    private function headerCount(): int
    {
        return $this->currentStore()->categories()->where('show_in_header', true)->count();
    }

    private function headerLimitReached(): JsonResponse
    {
        return response()->json([
            'message' => 'Límite alcanzado: solo puedes mostrar hasta ' . self::HEADER_LIMIT . ' categorías en el encabezado.',
            'errors'  => ['show_in_header' => ['Límite de ' . self::HEADER_LIMIT . ' categorías en el encabezado.']],
        ], 422);
    }

    private function deleteImage(Category $category): void
    {
        if ($category->image_path) {
            Storage::disk('public')->delete($category->image_path);
        }
    }
}
