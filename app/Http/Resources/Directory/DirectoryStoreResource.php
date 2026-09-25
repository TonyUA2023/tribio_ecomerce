<?php

namespace App\Http\Resources\Directory;

use App\Services\Directory\DirectoryCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * One store card of the business directory, with what its live preview needs.
 * Requires a Store prepared by DirectoryCatalog::stores() (`directoryProducts`, `directory_accent`).
 *
 * @mixin \App\Models\Store
 */
class DirectoryStoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $catalog = app(DirectoryCatalog::class);
        $category = config("tribio.business_categories.{$this->category}");
        $products = $this->directoryProducts;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'initial' => mb_strtoupper(mb_substr(trim($this->name), 0, 1)),
            'category' => $this->category,
            'category_label' => $category['label'] ?? null,
            'category_icon' => $category['icon'] ?? '🛍️',
            'summary' => Str::limit(trim(strip_tags((string) ($this->tagline ?: $this->description))), 140),
            'city' => $this->city,
            'logo' => $this->logo_path ? $this->logo_url : null,
            'accent' => $this->directory_accent,
            'is_featured' => (bool) $this->is_featured,
            'products_count' => $products->count(),
            'rating' => $catalog->storeRating($this->resource),
            'url' => $catalog->storeUrl($this->resource),
            'preview_url' => $catalog->previewUrl($this->resource),
            'whatsapp_url' => $this->whatsapp_link,
            'thumbnails' => $products->filter(fn ($p) => $p->image_path)->take(3)->map(fn ($p) => $p->image_url)->values()->all(),
            'products' => $products->take(6)->map(fn ($p) => [
                'name' => $p->name,
                'price_label' => $catalog->priceLabel($p),
                'image' => $p->image_path ? $p->image_url : null,
                'url' => $catalog->productUrl($p, 'vista_previa'),
            ])->values()->all(),
        ];
    }
}
