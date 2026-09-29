<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Actions\Products\SaveProduct;
use App\Models\Product;
use App\Models\InventoryMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    private function getStore()
    {
        return Auth::user()->currentStore();
    }

    public function index(Request $request)
    {
        $store = $this->getStore();

        $products = $store->products()
            ->with(['categories', 'category'])
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->when($request->category, function($q) use ($request) {
                $catId = $request->category;
                $q->where(function($sub) use ($catId) {
                    $sub->where('category_id', $catId)
                        ->orWhereHas('categories', fn($sq) => $sq->where('categories.id', $catId));
                });
            })
            ->when($request->status === 'active', fn($q) => $q->where('is_active', true))
            ->when($request->status === 'inactive', fn($q) => $q->where('is_active', false))
            ->when($request->stock === 'low', fn($q) => $q->where('track_stock', true)->whereColumn('stock', '<=', 'low_stock_alert')->where('stock', '>', 0))
            ->when($request->stock === 'out', fn($q) => $q->where('track_stock', true)->where('stock', 0))
            ->orderBy('sort_order')
            ->paginate(20);

        $categories = $store->categories;
        $homeVideoProducts = $store->products()
            ->whereNotNull('video_path')
            ->where('show_video_on_home', true)
            ->get();

        return view('dashboard.products.index', compact('store', 'products', 'categories', 'homeVideoProducts'));
    }

    public function create()
    {
        $store      = $this->getStore();
        $categories = $store->categories;
        $brands     = $store->brands;
        $homeVideoProducts = $store->products()
            ->whereNotNull('video_path')
            ->where('show_video_on_home', true)
            ->get();

        return view('dashboard.products.create', compact('store', 'categories', 'brands', 'homeVideoProducts'));
    }

    public function store(Request $request, SaveProduct $saveProduct)
    {
        $store = $this->getStore();

        // Verificación de errores a nivel de servidor PHP en la subida de video
        if (isset($_FILES['video']) && !empty($_FILES['video']['name'])) {
            $err = $_FILES['video']['error'];
            if ($err !== UPLOAD_ERR_OK && $err !== UPLOAD_ERR_NO_FILE) {
                $maxServer = ini_get('upload_max_filesize') ?: '2M';
                $errorMsgs = [
                    UPLOAD_ERR_INI_SIZE   => "El archivo de video supera el límite de subida del servidor PHP (upload_max_filesize = {$maxServer}). Aumenta este valor en php.ini a al menos 10M o comprime tu video a menos de 4 MB.",
                    UPLOAD_ERR_FORM_SIZE  => "El archivo de video supera el tamaño máximo permitido por el formulario.",
                    UPLOAD_ERR_PARTIAL    => "La subida del video se interrumpió y quedó incompleta. Por favor inténtalo de nuevo.",
                    UPLOAD_ERR_NO_TMP_DIR => "Error del servidor: Falta la carpeta temporal de PHP (upload_tmp_dir).",
                    UPLOAD_ERR_CANT_WRITE => "Error del servidor: No se pudo escribir el archivo temporal en el disco.",
                    UPLOAD_ERR_EXTENSION  => "Una extensión de PHP detuvo la subida del video.",
                ];
                $errorMsg = $errorMsgs[$err] ?? "Error al subir el video al servidor (código {$err}).";
                return back()->withInput()->withErrors(['video' => $errorMsg]);
            }
        }

        // Todo el guardado (validación, imágenes, variantes, inventario…) vive en SaveProduct,
        // compartido con la API móvil: una regla nueva llega a la web y a la app a la vez.
        $product = $saveProduct->execute($request, $store, Auth::user(), imageMaxKb: 3072);

        return redirect()->route('dashboard.productos.index')
            ->with('success', "Producto '{$product->name}' creado correctamente.");
    }

    public function edit(Product $product)
    {
        $store = $this->getStore();
        abort_if($product->store_id !== $store->id, 403);
        $categories = $store->categories;
        $brands     = $store->brands;
        $product->load(['variants', 'categories']);

        $homeVideoProducts = $store->products()
            ->where('id', '!=', $product->id)
            ->whereNotNull('video_path')
            ->where('show_video_on_home', true)
            ->get();

        return view('dashboard.products.edit', compact('store', 'product', 'categories', 'brands', 'homeVideoProducts'));
    }

    public function update(Request $request, Product $product, SaveProduct $saveProduct)
    {
        $store = $this->getStore();
        abort_if($product->store_id !== $store->id, 403);

        // Verificación de errores a nivel de servidor PHP en la subida de video
        if (isset($_FILES['video']) && !empty($_FILES['video']['name'])) {
            $err = $_FILES['video']['error'];
            if ($err !== UPLOAD_ERR_OK && $err !== UPLOAD_ERR_NO_FILE) {
                $maxServer = ini_get('upload_max_filesize') ?: '2M';
                $errorMsgs = [
                    UPLOAD_ERR_INI_SIZE   => "El archivo de video supera el límite de subida del servidor PHP (upload_max_filesize = {$maxServer}). Aumenta este valor en php.ini a al menos 10M o comprime tu video a menos de 4 MB.",
                    UPLOAD_ERR_FORM_SIZE  => "El archivo de video supera el tamaño máximo permitido por el formulario.",
                    UPLOAD_ERR_PARTIAL    => "La subida del video se interrumpió y quedó incompleta. Por favor inténtalo de nuevo.",
                    UPLOAD_ERR_NO_TMP_DIR => "Error del servidor: Falta la carpeta temporal de PHP (upload_tmp_dir).",
                    UPLOAD_ERR_CANT_WRITE => "Error del servidor: No se pudo escribir el archivo temporal en el disco.",
                    UPLOAD_ERR_EXTENSION  => "Una extensión de PHP detuvo la subida del video.",
                ];
                $errorMsg = $errorMsgs[$err] ?? "Error al subir el video al servidor (código {$err}).";
                return back()->withInput()->withErrors(['video' => $errorMsg]);
            }
        }

        $product = $saveProduct->execute($request, $store, Auth::user(), $product, imageMaxKb: 3072);

        return redirect()->route('dashboard.productos.index')
            ->with('success', "Producto '{$product->name}' actualizado correctamente.");
    }

    public function destroy(Product $product)
    {
        $store = $this->getStore();
        abort_if($product->store_id !== $store->id, 403);

        if ($product->image_path) Storage::disk('public')->delete($product->image_path);
        if ($product->video_path) Storage::disk('public')->delete($product->video_path);
        if (!empty($product->gallery_images) && is_array($product->gallery_images)) {
            foreach ($product->gallery_images as $img) {
                Storage::disk('public')->delete($img);
            }
        }
        $product->delete();

        return back()->with('success', 'Producto eliminado.');
    }

    public function toggleHomeVideo(Request $request, Product $product)
    {
        $store = $this->getStore();
        abort_if($product->store_id !== $store->id, 403);

        if (!$product->video_path) {
            return back()->with('error', 'El producto no tiene un video subido.');
        }

        if ($product->show_video_on_home) {
            $product->update(['show_video_on_home' => false]);
            return back()->with('success', "Se quitó '{$product->name}' de los videos del Home.");
        }

        $activeCount = $store->products()
            ->where('show_video_on_home', true)
            ->where('id', '!=', $product->id)
            ->count();

        if ($activeCount >= 3) {
            $oldest = $store->products()
                ->where('show_video_on_home', true)
                ->where('id', '!=', $product->id)
                ->orderBy('updated_at', 'asc')
                ->first();
            if ($oldest) {
                $oldest->update(['show_video_on_home' => false]);
            }
        }

        $product->update(['show_video_on_home' => true]);
        return back()->with('success', "Se asignó '{$product->name}' a los videos destacados del Home.");
    }
}
