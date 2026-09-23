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
        // getAttribute(), not $this->attributes: inside the model that property is
        // Eloquent's raw column array (id, sku, price…), not this `attributes` JSON
        // column — the name collision used to put every column into the title.
        $options = $this->getAttribute('attributes');
        if (empty($options) || !is_array($options)) {
            return $this->sku ?? 'Variante';
        }
        return implode(' / ', array_values($options));
    }

    public function getImageUrlAttribute(): string
    {
        if ($this->image_path) {
            return Storage::url($this->image_path);
        }
        return $this->product?->image_url ?? asset('images/product-placeholder.png');
    }

    public function resolvePrice(?string $currency = null): float
    {
        $currency = $currency ? strtoupper(trim($currency)) : \App\Helpers\CurrencyHelper::currentCurrency();

        if ($currency === 'USD' && !empty($this->price_usd) && $this->price_usd > 0) {
            return (float) $this->price_usd;
        }

        if (!empty($this->price) && $this->price > 0) {
            if ($currency === 'PEN') {
                return (float) $this->price;
            }
            return app(\App\Services\ExchangeRateService::class)->convert((float) $this->price, 'PEN', $currency);
        }

        return (float) ($this->product?->resolvePrice($currency) ?? 0);
    }

    public function resolveComparePrice(?string $currency = null): float
    {
        $currency = $currency ? strtoupper(trim($currency)) : \App\Helpers\CurrencyHelper::currentCurrency();

        if ($currency === 'USD' && !empty($this->compare_price_usd) && $this->compare_price_usd > 0) {
            return (float) $this->compare_price_usd;
        }

        if (!empty($this->compare_price) && $this->compare_price > 0) {
            if ($currency === 'PEN') {
                return (float) $this->compare_price;
            }
            return app(\App\Services\ExchangeRateService::class)->convert((float) $this->compare_price, 'PEN', $currency);
        }

        return (float) ($this->product?->resolveComparePrice($currency) ?? 0);
    }

    public function inStock(): bool
    {
        return $this->stock > 0;
    }
}
