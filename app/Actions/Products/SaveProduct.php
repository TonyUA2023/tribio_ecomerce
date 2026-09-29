<?php

namespace App\Actions\Products;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\ExchangeRateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The one place that turns a product form into a saved product. The web dashboard
 * (Dashboard\ProductController, full form) and the mobile API (Api\ProductController, partial
 * updates) both call it, so a rule added here reaches both surfaces at once.
 *
 * Validation runs first (including the made-to-order, wholesale and ads blocks) so a bad
 * submission never leaves a half-saved product or orphan files. It throws ValidationException:
 * the web redirects back with errors, the API answers 422.
 *
 * $partial = the client may send only some fields (mobile switch "visible", etc.): booleans and
 * variants are then left alone when their key is absent. A full form (web) resets an unchecked
 * checkbox to false, as an HTML form does.
 */
class SaveProduct
{
    /** Home-page video slots per store (oldest one is replaced). */
    private const HOME_VIDEO_LIMIT = 3;

    /** Form-controlled checkboxes and their default when creating. */
    private const FLAGS = ['track_stock' => false, 'allow_backorder' => false, 'is_active' => true, 'is_featured' => false, 'is_new' => false];

    /** Not part of the web form: only touched when the client sends them. */
    private const OPTIONAL_FLAGS = ['is_composite', 'is_sold'];

    public function rules(Store $store, bool $partial, int $imageMaxKb): array
    {
        $req = $partial ? 'sometimes|required' : 'required';
        $image = "nullable|image|mimes:png,jpg,jpeg,webp|max:{$imageMaxKb}";

        return [
            'name'              => "{$req}|string|max:255",
            'description'       => 'nullable|string',
            'short_description' => 'nullable|string|max:200',
            'sku'               => 'nullable|string|max:50',
            'origin_code'       => 'nullable|string|max:100',
            'category_id'       => ['nullable', 'integer', Rule::exists('categories', 'id')->where('store_id', $store->id)],
            // A JSON string (mobile multipart) or an array (web form): parsed in categoryIds().
            'categories'        => 'nullable',
            'brand_id'          => ['nullable', 'integer', Rule::exists('brands', 'id')->where('store_id', $store->id)],
            'parent_id'         => ['nullable', 'integer', Rule::exists('products', 'id')->where('store_id', $store->id)],
            'price'             => "{$req}|numeric|min:0",
            'compare_price'     => 'nullable|numeric|min:0',
            'price_usd'         => 'nullable|numeric|min:0',
            'compare_price_usd' => 'nullable|numeric|min:0',
            'currency_prices'   => 'nullable|array',
            'compare_currency_prices' => 'nullable|array',
            'cost_price'        => 'nullable|numeric|min:0',
            'stock'             => ($partial ? 'sometimes|nullable' : 'required') . '|integer|min:0',
            'track_stock'       => 'nullable|boolean',
            'allow_backorder'   => 'nullable|boolean',
            'out_of_stock_message' => 'nullable|string|max:255',
            'low_stock_alert'   => 'nullable|integer|min:0',
            'unit'              => 'nullable|string|max:20',
            'is_active'         => 'nullable|boolean',
            'is_featured'       => 'nullable|boolean',
            'is_new'            => 'nullable|boolean',
            'is_composite'      => 'nullable|boolean',
            'composite_type'    => 'nullable|string|in:assembly,bucket,unit',
            'is_sold'           => 'nullable|boolean',
            'image'             => $image,
            'gallery'           => 'nullable|array|max:8',
            'gallery.*'         => $image,
            'remove_gallery'    => 'nullable',
            'video'             => 'nullable|file|mimes:mp4,webm,mov,quicktime|max:4096',
            'remove_video'      => 'nullable|boolean',
            'show_video_on_home' => 'nullable|boolean',
            'replace_home_video_id' => ['nullable', 'integer', Rule::exists('products', 'id')->where('store_id', $store->id)],
            'tags'              => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'   => 'Escribe el nombre del producto.',
            'name.max'        => 'El nombre puede tener como máximo 255 caracteres.',
            'price.required'  => 'Ingresa el precio de venta.',
            'price.numeric'   => 'El precio debe ser un número.',
            'price.min'       => 'El precio no puede ser negativo.',
            'compare_price.numeric' => 'El precio anterior debe ser un número.',
            'cost_price.numeric'    => 'El costo debe ser un número.',
            'stock.required'  => 'Ingresa el stock (0 si no tienes).',
            'stock.integer'   => 'El stock debe ser un número entero.',
            'stock.min'       => 'El stock no puede ser negativo.',
            'short_description.max' => 'La descripción corta puede tener hasta 200 caracteres.',
            'sku.max'         => 'El SKU puede tener hasta 50 caracteres.',
            'image.image'     => 'La foto principal debe ser una imagen.',
            'image.max'       => 'La foto principal es demasiado pesada.',
            'gallery.max'     => 'Puedes subir hasta 8 fotos adicionales.',
            'gallery.*.image' => 'Todas las fotos adicionales deben ser imágenes.',
            'gallery.*.max'   => 'Una de las fotos adicionales es demasiado pesada.',
            'video.max'       => 'El video del producto no debe superar los 4 MB.',
            'video.mimes'     => 'El formato del video debe ser MP4, WebM o MOV.',
            'video.uploaded'  => 'El video no se pudo subir. Asegúrate de que pese menos de 4 MB y que el servidor permita este tamaño de archivo.',
        ];
    }

    public function execute(Request $request, Store $store, User $user, ?Product $product = null, bool $partial = false, int $imageMaxKb = 5120): Product
    {
        $data = $request->validate($this->rules($store, $partial, $imageMaxKb), $this->messages());

        // Tiers must be cheaper than the price the product will have.
        if ($product && !$request->has('price')) {
            $request->merge(['price' => (string) $product->price]);
        }
        $madeToOrder = app(SaveMadeToOrderSettings::class)->validate($request, $store);
        $wholesale = app(SaveWholesaleSettings::class)->validate($request);
        $adsCatalog = app(SaveAdsCatalogSettings::class)->validate($request);

        $data = $this->prices($request, $data, $product);
        $data = $this->flags($request, $data, $partial, $product === null);

        if (array_key_exists('tags', $data)) {
            $tags = array_values(array_filter(array_map('trim', explode(',', (string) $data['tags']))));
            $data['tags'] = $tags ?: null;
        }
        foreach (['stock', 'low_stock_alert'] as $notNull) {
            if (array_key_exists($notNull, $data) && $data[$notNull] === null) {
                unset($data[$notNull]);
            }
        }
        if (array_key_exists('is_sold', $data)) {
            $data['sold_at'] = $data['is_sold'] ? ($product?->is_sold ? $product->sold_at : now()) : null;
        }

        // Variants (has_variants + options); rows are synced after the product exists.
        $variantsTouched = !$partial || $request->has('has_variants');
        if ($variantsTouched) {
            $hasVariants = $request->boolean('has_variants');
            $data['has_variants'] = $hasVariants;
            if ($hasVariants && $request->filled('variant_options_json')) {
                $data['variant_options'] = json_decode($request->input('variant_options_json'), true);
            } elseif (!$hasVariants) {
                $data['variant_options'] = null;
            }
        }

        $categoryIds = $this->categoryIds($request, $store);
        $categoriesTouched = !$partial || $request->has('categories') || $request->filled('category_id');
        if ($categoriesTouched) {
            [$data['category_id'], $categoryIds] = $this->primaryCategory($request, $categoryIds);
        }

        $data = $this->files($request, $store, $product, $data, $partial);
        unset($data['image'], $data['gallery'], $data['remove_gallery'], $data['video'], $data['remove_video'], $data['replace_home_video_id'], $data['categories']);

        if ($product) {
            $previousStock = (int) $product->stock;
            $product->update($data);
        } else {
            $data['store_id'] = $store->id;
            $data['slug'] = Str::slug($data['name']) . '-' . Str::random(4);
            $data['sku'] = empty($data['sku']) ? $this->generateSku($store) : $data['sku'];
            $data['stock'] = (int) ($data['stock'] ?? 0);
            $previousStock = 0;
            $product = Product::create($data);
        }

        app(SaveMadeToOrderSettings::class)->apply($product, $madeToOrder);
        app(SaveWholesaleSettings::class)->apply($product, $wholesale);
        app(SaveAdsCatalogSettings::class)->apply($product, $adsCatalog);

        if ($categoriesTouched) {
            $product->categories()->sync($categoryIds);
        }
        if ($variantsTouched) {
            $this->syncVariants($request, $product);
        }

        $this->recordStockMovement($product, $store, $user, $previousStock, isset($data['stock']) || $product->wasRecentlyCreated);

        return $product->refresh();
    }

    // ─── Pieces ──────────────────────────────────────────────────

    /** Multi-currency maps and the price_usd backward-compat sync. */
    private function prices(Request $request, array $data, ?Product $product): array
    {
        foreach (['currency_prices', 'compare_currency_prices'] as $field) {
            if (!$request->has($field)) {
                continue;
            }
            $clean = [];
            foreach ((array) $request->input($field, []) as $currency => $value) {
                if ($value !== null && $value !== '' && is_numeric($value) && (float) $value > 0) {
                    $clean[strtoupper((string) $currency)] = (float) $value;
                }
            }
            $data[$field] = $clean ?: null;
        }

        if (isset($data['currency_prices']['USD'])) {
            $data['price_usd'] = $data['currency_prices']['USD'];
        } elseif (isset($data['price']) && empty($data['price_usd']) && !$product?->price_usd) {
            // No USD price typed and none saved: derive it. A hand-set one survives edits.
            $data['price_usd'] = app(ExchangeRateService::class)->convert((float) $data['price'], 'PEN', 'USD');
        } elseif ($product && empty($data['price_usd'])) {
            unset($data['price_usd']);
        }
        if (isset($data['compare_currency_prices']['USD'])) {
            $data['compare_price_usd'] = $data['compare_currency_prices']['USD'];
        }

        return $data;
    }

    private function flags(Request $request, array $data, bool $partial, bool $creating): array
    {
        foreach (self::FLAGS as $flag => $default) {
            if ($partial && !$request->has($flag)) {
                unset($data[$flag]);
                continue;
            }
            $data[$flag] = $request->boolean($flag, $creating ? $default : false);
        }
        foreach (self::OPTIONAL_FLAGS as $flag) {
            if ($request->has($flag)) {
                $data[$flag] = $request->boolean($flag);
            } else {
                unset($data[$flag]);
            }
        }

        return $data;
    }

    /** Main image, gallery and short video (with the home-page video slots). */
    private function files(Request $request, Store $store, ?Product $product, array $data, bool $partial): array
    {
        if ($request->hasFile('image')) {
            $this->deleteImage($product);
            $data['image_path'] = $request->file('image')->store("stores/{$store->id}/products", 'public');
        }

        if ($request->hasFile('gallery') || $request->has('remove_gallery')) {
            $gallery = is_array($product?->gallery_images) ? $product->gallery_images : [];
            foreach ($this->listInput($request->input('remove_gallery')) as $path) {
                if (in_array($path, $gallery, true)) {
                    Storage::disk('public')->delete($path);
                    $gallery = array_values(array_filter($gallery, fn ($p) => $p !== $path));
                }
            }
            foreach ((array) $request->file('gallery', []) as $img) {
                $gallery[] = $img->store("stores/{$store->id}/gallery-products", 'public');
            }
            $data['gallery_images'] = array_values($gallery);
        }

        if ($request->hasFile('video')) {
            if ($product?->video_path) {
                Storage::disk('public')->delete($product->video_path);
            }
            $data['video_path'] = $request->file('video')->store("stores/{$store->id}/videos", 'public');
        } elseif ($request->boolean('remove_video')) {
            if ($product?->video_path) {
                Storage::disk('public')->delete($product->video_path);
            }
            $data['video_path'] = null;
            $data['show_video_on_home'] = false;
        }

        if (!$partial || $request->has('show_video_on_home') || $request->hasFile('video') || $request->boolean('remove_video')) {
            $hasVideo = !empty($data['video_path']) || (!empty($product?->video_path) && !$request->boolean('remove_video'));
            $showOnHome = $request->boolean('show_video_on_home') && $hasVideo;
            $data['show_video_on_home'] = $showOnHome;
            if ($showOnHome) {
                $this->freeHomeVideoSlot($request, $store, $product);
            }
        }

        return $data;
    }

    private function freeHomeVideoSlot(Request $request, Store $store, ?Product $product): void
    {
        $others = fn () => $store->products()->when($product, fn ($q) => $q->where('id', '!=', $product->id));

        if ($replaceId = $request->input('replace_home_video_id')) {
            $others()->where('id', $replaceId)->update(['show_video_on_home' => false]);
        }
        if ($others()->where('show_video_on_home', true)->count() >= self::HOME_VIDEO_LIMIT) {
            $others()->where('show_video_on_home', true)->orderBy('updated_at')->first()?->update(['show_video_on_home' => false]);
        }
    }

    /** The old photo goes away unless a lot's visual history still points at it. */
    private function deleteImage(?Product $product): void
    {
        if (!$product?->image_path) {
            return;
        }
        if ($product->stateHistories()->where('image_path', $product->image_path)->exists()) {
            return;
        }
        Storage::disk('public')->delete($product->image_path);
    }

    /** @return list<int> only categories of this store */
    private function categoryIds(Request $request, Store $store): array
    {
        $ids = array_values(array_filter(array_map('intval', $this->listInput($request->input('categories')))));

        return $ids ? $store->categories()->whereIn('id', $ids)->pluck('id')->map(fn ($i) => (int) $i)->all() : [];
    }

    /** @return array{0: ?int, 1: list<int>} main category and the full list to sync */
    private function primaryCategory(Request $request, array $ids): array
    {
        $main = $request->filled('category_id') ? (int) $request->input('category_id') : null;

        if ($main && in_array($main, $ids, true)) {
            return [$main, $ids];
        }
        if ($ids) {
            return [$ids[0], $ids];
        }

        return $main ? [$main, [$main]] : [null, []];
    }

    private function syncVariants(Request $request, Product $product): void
    {
        if (!$request->boolean('has_variants')) {
            $product->variants()->delete();

            return;
        }
        if (!$request->filled('variants_json')) {
            return;
        }
        $rows = json_decode($request->input('variants_json'), true);
        if (!is_array($rows)) {
            return;
        }

        $kept = [];
        $totalStock = 0;
        foreach ($rows as $v) {
            $stock = (int) ($v['stock'] ?? 0);
            $totalStock += $stock;
            $payload = [
                'sku'           => !empty($v['sku']) ? $v['sku'] : ($product->sku . '-' . Str::random(4)),
                'price'         => !empty($v['price']) ? (float) $v['price'] : null,
                'compare_price' => !empty($v['compare_price']) ? (float) $v['compare_price'] : null,
                'price_usd'     => !empty($v['price_usd']) ? (float) $v['price_usd'] : null,
                'stock'         => $stock,
                'attributes'    => $v['attributes'] ?? [],
                'is_active'     => isset($v['is_active']) ? (bool) $v['is_active'] : true,
            ];

            $existing = !empty($v['id']) ? $product->variants()->find($v['id']) : null;
            if ($existing) {
                $existing->update($payload);
                $kept[] = $existing->id;
            } else {
                $kept[] = $product->variants()->create($payload)->id;
            }
        }
        $product->variants()->whereNotIn('id', $kept)->delete();

        if ($totalStock > 0 && (int) $product->stock === 0) {
            $product->update(['stock' => $totalStock]);
        }
    }

    private function recordStockMovement(Product $product, Store $store, User $user, int $previousStock, bool $stockSent): void
    {
        if (!$product->track_stock || !$stockSent) {
            return;
        }
        $created = $product->wasRecentlyCreated;
        $current = (int) $product->stock;
        if ($created ? $current <= 0 : $current === $previousStock) {
            return;
        }

        InventoryMovement::create([
            'product_id'   => $product->id,
            'store_id'     => $store->id,
            'type'         => $created ? 'in' : 'adjustment',
            'quantity'     => $created ? $current : $current - $previousStock,
            'stock_before' => $created ? 0 : $previousStock,
            'stock_after'  => $current,
            'reason'       => $created ? 'Stock inicial al crear producto' : 'Ajuste manual desde edición de producto',
            'user_id'      => $user->id,
        ]);
    }

    private function generateSku(Store $store): string
    {
        $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $store->slug), 0, 3)) ?: 'PROD';
        $num = $store->products()->count() + 1;
        do {
            $sku = $prefix . '-' . str_pad((string) $num++, 4, '0', STR_PAD_LEFT);
        } while (Product::where('store_id', $store->id)->where('sku', $sku)->exists());

        return $sku;
    }

    /** Multipart clients send lists either as arrays or as a JSON string. */
    private function listInput(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        return is_array($value) ? $value : [];
    }
}
