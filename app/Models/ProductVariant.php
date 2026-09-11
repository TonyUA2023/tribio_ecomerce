<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'sku',
        'price',
        'compare_price',
        'price_usd',
        'stock',
        'attributes',
        'image_path',
        'is_active',
    ];

    protected $casts = [
        'price'         => 'decimal:2',
        'compare_price' => 'decimal:2',
        'price_usd'     => 'decimal:2',
        'stock'         => 'integer',
        'attributes'    => 'array',
        'is_active'     => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function getTitleAttribute(): string
    {
        if (empty($this->attributes) || !is_array($this->attributes)) {
            return $this->sku ?? 'Variante';
        }
        return implode(' / ', array_values($this->attributes));
    }

    public function getImageUrlAttribute(): string
    {
        if ($this->image_path) {
            return Storage::url($this->image_path);
        }
        return $this->product?->image_url ?? asset('images/product-placeholder.png');
    }

    public function resolvePrice(): float
    {
        $isUsd = request()->cookie('user_country') === 'US';
        if ($isUsd && !empty($this->price_usd) && $this->price_usd > 0) {
            return (float) $this->price_usd;
        }
        if (!empty($this->price) && $this->price > 0) {
            return (float) $this->price;
        }
        return (float) ($this->product?->resolvePrice() ?? 0);
    }

    public function resolveComparePrice(): float
    {
        $isUsd = request()->cookie('user_country') === 'US';
        if ($isUsd && !empty($this->compare_price_usd) && $this->compare_price_usd > 0) {
            return (float) $this->compare_price_usd;
        }
        if (!empty($this->compare_price) && $this->compare_price > 0) {
            return (float) $this->compare_price;
        }
        return (float) ($this->product?->resolveComparePrice() ?? 0);
    }

    public function inStock(): bool
    {
        return $this->stock > 0;
    }
}
