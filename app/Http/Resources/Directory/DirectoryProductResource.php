<?php

namespace App\Http\Resources\Directory;

use App\Services\Directory\DirectoryCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One product as the business directory shows it. Requires the `store` relation.
 * The campaign tags the outbound link's utm_campaign: vitrina | ofertas | busqueda.
 *
 * @mixin \App\Models\Product
 */
class DirectoryProductResource extends JsonResource
{
    private string $campaign = 'vitrina';

    /** @return list<array> the products resolved for one placement of the directory */
    public static function many(iterable $products, string $campaign, ?Request $request = null): array
    {
        $request ??= request();
        $resolved = [];
        foreach ($products as $product) {
            $resource = new self($product);
            $resource->campaign = $campaign;
            $resolved[] = $resource->resolve($request);
        }

        return $resolved;
    }

    public function toArray(Request $request): array
    {
        $catalog = app(DirectoryCatalog::class);
        $campaign = $this->campaign;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => (float) $this->price > 0 ? round((float) $this->price, 2) : null,
            'price_label' => $catalog->priceLabel($this->resource),
            'compare_label' => $this->discount_percent ? 'S/ ' . number_format((float) $this->compare_price, 2) : null,
            'discount_percent' => $this->discount_percent,
            'image' => $this->image_path ? $this->image_url : null,
            'url' => $catalog->productUrl($this->resource, $campaign),
            'available' => $catalog->isAvailable($this->resource),
            'made_to_order' => $catalog->isMadeToOrder($this->resource),
            'rating' => $catalog->rating($this->resource),
            'reviews_count' => (int) ($this->resource->getAttributes()['reviews_count'] ?? 0),
            'store' => [
                'id' => $this->store->id,
                'name' => $this->store->name,
                'slug' => $this->store->slug,
                'logo' => $this->store->logo_path ? $this->store->logo_url : null,
                'accent' => $this->store->directory_accent,
                'url' => $catalog->storeUrl($this->store, $campaign),
                'preview_url' => $catalog->previewUrl($this->store),
            ],
        ];
    }
}
