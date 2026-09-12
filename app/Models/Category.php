<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Category extends Model
{
    protected $fillable = [
        'store_id', 'parent_id', 'name', 'slug', 'icon', 'image_path', 'color', 'sort_order', 'is_active', 'show_in_header', 'is_featured',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'show_in_header' => 'boolean',
        'is_featured' => 'boolean',
    ];

    protected $appends = ['image_url', 'translated_name'];

    public function getImageUrlAttribute(): ?string
    {
        if ($this->image_path) {
            return Storage::disk('public')->url($this->image_path);
        }

        // Fallback al primer producto activo con imagen
        $prod = $this->products()->whereNotNull('image_path')->first();
        return $prod ? $prod->image_url : null;
    }

    public function getTranslatedName(?string $lang = null): string
    {
        return \App\Helpers\TranslationHelper::transCategory($this->name, $lang);
    }

    public function getTranslatedNameAttribute(): string
    {
        return $this->getTranslatedName();
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'category_product')->withTimestamps();
    }

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order');
    }

    public function activeProducts()
    {
        return $this->belongsToMany(Product::class, 'category_product')
                    ->where('products.is_active', true)
                    ->withTimestamps();
    }
}
