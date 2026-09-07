<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\StoreSection;

class StoreBuilderController extends Controller
{
    public function index()
    {
        $store = auth()->user()->store;
        $sections = $store->sections;

        return view('dashboard.store.builder', compact('store', 'sections'));
    }

    public function store(Request $request)
    {
        $store = auth()->user()->store;
        
        $request->validate([
            'type' => 'required|string',
            'order' => 'integer'
        ]);

        $block = \App\Builder\Blocks\BlockRegistry::getBlock($request->type);
        
        if (!$block) {
            return response()->json(['error' => 'Tipo de bloque no soportado'], 400);
        }

        $defaultData = $block->getDefaultData();

        $section = $store->sections()->create([
            'type' => $request->type,
            'order' => $request->order ?? $store->sections()->count(),
            'data' => $defaultData,
            'is_active' => true,
        ]);

        $categories = $store->categories;
        $products = $store->products;
        $isEditor = true;
        
        $html = '';
        if (view()->exists("components.store-sections.{$section->type}")) {
            $data = $section->data;
            $html = view("components.store-sections.{$section->type}", compact('data', 'store', 'categories', 'products', 'isEditor'))->render();
        }

        return response()->json(['message' => 'Sección creada', 'section' => $section, 'html' => $html]);
    }

    public function update(Request $request, $id)
    {
        $store = auth()->user()->store;
        $section = $store->sections()->findOrFail($id);

        $request->validate([
            'data' => 'nullable|array',
            'is_active' => 'nullable|boolean'
        ]);

        if ($request->has('data')) {
            $section->data = $request->data;
        }

        if ($request->has('is_active')) {
            $section->is_active = $request->is_active;
        }

        $section->save();

        $categories = $store->categories;
        $products = $store->products;
        $isEditor = true;
        
        $html = '';
        if (view()->exists("components.store-sections.{$section->type}")) {
            $data = $section->data;
            $html = view("components.store-sections.{$section->type}", compact('data', 'store', 'categories', 'products', 'isEditor'))->render();
        }

        return response()->json([
            'message' => 'Sección actualizada', 
            'section' => $section,
            'html' => $html
        ]);
    }

    public function reorder(Request $request)
    {
        $store = auth()->user()->store;
        
        $request->validate([
            'sections' => 'required|array',
            'sections.*.id' => 'required|exists:store_sections,id',
            'sections.*.order' => 'required|integer',
        ]);

        foreach ($request->sections as $sectionData) {
            $store->sections()->where('id', $sectionData['id'])->update(['order' => $sectionData['order']]);
        }

        return response()->json(['message' => 'Orden actualizado']);
    }

    public function destroy($id)
    {
        $store = auth()->user()->store;
        $section = $store->sections()->findOrFail($id);
        
        $section->delete();

        return response()->json(['message' => 'Sección eliminada']);
    }

    public function renderPreview(Request $request)
    {
        $request->validate([
            'type' => 'required|string',
            'data' => 'required|array'
        ]);

        $type = $request->type;
        $data = $request->data;
        $store = auth()->user()->store;
        $categories = $store->categories;
        $products = $store->products;

        if (!view()->exists("components.store-sections.{$type}")) {
            return response()->json(['error' => 'Componente no encontrado'], 404);
        }

        $isEditor = true; // renderPreview is always called from the editor
        // Renderizamos el HTML del componente inyectándole los datos
        $html = view("components.store-sections.{$type}", compact('data', 'store', 'categories', 'products', 'isEditor'))->render();

        return response()->json(['html' => $html]);
    }

    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:2048'
        ]);

        $path = $request->file('image')->store('store-sections', 'public');
        
        return response()->json(['url' => \Illuminate\Support\Facades\Storage::url($path)]);
    }

    public function loadFromTemplate(Request $request)
    {
        $store = auth()->user()->store;
        $template = $store->template_name;

        // Limpiar secciones existentes
        $store->sections()->delete();

        // Determinar bloques predeterminados según plantilla
        $blocks = [];
        if ($template === 'elegant-dark' || $template === 'vibrant-fresh') {
            $blocks = ['header', 'hero', 'products_carousel', 'footer'];
        } elseif ($template === 'industrial-light') {
            $blocks = ['header', 'hero', 'categories', 'products_carousel', 'text', 'gallery', 'footer'];
        } elseif ($template === 'minimal-light' || $template === 'elegant-refurbished') {
            $blocks = ['header', 'hero', 'products_carousel', 'text', 'footer'];
        } else {
            $blocks = ['header', 'hero', 'products_carousel', 'footer'];
        }

        $order = 0;
        foreach ($blocks as $type) {
            $req = new Request(['type' => $type, 'order' => $order]);
            $this->store($req); // Reutiliza el método store para generar la data por defecto
            $order++;
        }

        return response()->json(['message' => 'Plantilla base cargada con éxito']);
    }

    public function publish(Request $request)
    {
        $store = auth()->user()->store;
        
        // Obtener todas las secciones (activas e inactivas) en el orden correcto
        $sections = $store->sections()->orderBy('order')->get();
        
        $publishedLayout = [];
        foreach($sections as $section) {
            $publishedLayout[] = [
                'id' => $section->id,
                'type' => $section->type,
                'data' => $section->data,
                'is_active' => $section->is_active,
            ];
        }

        $store->published_layout = $publishedLayout;
        $store->save();

        return response()->json(['message' => 'Cambios publicados con éxito en tu tienda en vivo.']);
    }
}
