<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'store_id', 'category_id', 'brand_id', 'parent_id', 'name', 'slug', 'description', 'short_description', 'sku', 'origin_code',
        'price', 'compare_price', 'price_usd', 'compare_price_usd', 'cost_price',
        'stock', 'track_stock', 'allow_backorder', 'low_stock_alert', 'unit',
        'image_path', 'gallery_images',
        'is_active', 'is_featured', 'is_new', 'is_digital',
        'has_variants', 'variant_options',
        'is_composite', 'composite_type', 'is_sold', 'sold_at',
        'weight', 'dimensions', 'tags', 'meta_title', 'meta_description',
        'views', 'sold_count', 'sort_order',
    ];

    protected $casts = [
        'gallery_images'  => 'array',
        'tags'            => 'array',
        'variant_options' => 'array',
        'has_variants'    => 'boolean',
        'price'           => 'decimal:2',
        'compare_price'   => 'decimal:2',
        'price_usd'       => 'decimal:2',
        'compare_price_usd' => 'decimal:2',
        'cost_price'      => 'decimal:2',
        'is_active'       => 'boolean',
        'is_featured'     => 'boolean',
        'is_new'          => 'boolean',
        'is_digital'      => 'boolean',
        'track_stock'     => 'boolean',
        'allow_backorder' => 'boolean',
        'is_composite'    => 'boolean',
        'is_sold'         => 'boolean',
        'sold_at'         => 'datetime',
    ];

    // ─── Scopes ──────────────────────────────────────────────────
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeInStock($query)
    {
        return $query->where(function ($q) {
            $q->where('track_stock', false)
              ->orWhere('stock', '>', 0)
              ->orWhere('allow_backorder', true);
        });
    }

    // ─── Helpers ─────────────────────────────────────────────────
    public function getImageUrlAttribute(): string
    {
        return $this->image_path
            ? asset('storage/' . $this->image_path)
            : asset('images/default-product.jpg');
    }

    public function getGalleryUrlsAttribute(): array
    {
        if (empty($this->gallery_images) || !is_array($this->gallery_images)) {
            return [];
        }
        return array_map(function ($path) {
            return asset('storage/' . $path);
        }, $this->gallery_images);
    }

    public function getAllImagesAttribute(): array
    {
        $images = [];
        if ($this->image_path) {
            $images[] = asset('storage/' . $this->image_path);
        } else {
            $images[] = asset('images/default-product.jpg');
        }
        foreach ($this->gallery_urls as $url) {
            $images[] = $url;
        }
        return $images;
    }

    public function getDiscountPercentAttribute(): ?int
    {
        if ($this->compare_price && $this->compare_price > $this->price) {
            return (int) round((1 - $this->price / $this->compare_price) * 100);
        }
        return null;
    }

    public function resolvePrice(): float
    {
        if (request()->cookie('user_country') === 'US' && $this->price_usd > 0) {
            return (float) $this->price_usd;
        }
        return (float) $this->price;
    }

    public function resolveComparePrice(): ?float
    {
        if (request()->cookie('user_country') === 'US' && $this->compare_price_usd > 0) {
            return (float) $this->compare_price_usd;
        }
        if ($this->compare_price > 0) {
            return (float) $this->compare_price;
        }
        return null;
    }

    public function resolveCurrencySymbol(): string
    {
        return request()->cookie('user_country') === 'US' ? '$' : 'S/';
    }

    public function isInStock(): bool
    {
        if (!$this->track_stock) return true;
        return $this->stock > 0 || $this->allow_backorder;
    }

    public function isLowStock(): bool
    {
        return $this->track_stock && $this->stock > 0 && $this->stock <= $this->low_stock_alert;
    }

    // ─── Lógica Financiera e Inventario Jerárquico ───────────────
    public function getRootProduct(): Product
    {
        $current = $this;
        while ($current->parent_id) {
            $parent = Product::find($current->parent_id);
            if (!$parent) break;
            $current = $parent;
        }
        return $current;
    }

    public function getTotalCostAttribute(): float
    {
        // El costo solo se asume del producto raíz
        return (float) ($this->cost_price ?? 0);
    }

    public function getRevenueGeneratedAttribute(): float
    {
        return $this->calculateRevenue();
    }

    protected function calculateRevenue(): float
    {
        $revenue = 0.0;
        
        // Si este producto individual está vendido, sumar su precio
        if ($this->is_sold) {
            $revenue += (float) $this->price;
        }
        
        // Sumar recursivamente los ingresos de sus hijos
        foreach ($this->children as $child) {
            $revenue += $child->calculateRevenue();
        }
        
        return $revenue;
    }

    public function getNetProfitAttribute(): float
    {
        if ($this->parent_id === null) {
            // Producto raíz: Ganancia = Ingresos Acumulados - Costo Total de Compra
            return $this->revenue_generated - $this->total_cost;
        }
        // Sub-ensamblaje: Ingresos acumulados de sus partes
        return $this->revenue_generated;
    }

    // ─── Relationships ───────────────────────────────────────────
    public function parent()
    {
        return $this->belongsTo(Product::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Product::class, 'parent_id');
    }

    public function allChildren()
    {
        return $this->hasMany(Product::class, 'parent_id')->with('allChildren');
    }

    public function stateHistories()
    {
        return $this->hasMany(ProductStateHistory::class)->orderBy('created_at', 'desc');
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function inventoryMovements()
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class)->orderBy('id');
    }

    public function activeVariants()
    {
        return $this->hasMany(ProductVariant::class)->where('is_active', true)->orderBy('id');
    }

    public function hasVariants(): bool
    {
        return (bool) $this->has_variants && $this->activeVariants()->count() > 0;
    }

    public function availableColors(): array
    {
        if (!$this->has_variants) {
            return [];
        }
        $colors = [];
        foreach ($this->activeVariants as $v) {
            $attrs = $v->attributes ?? [];
            foreach ($attrs as $key => $val) {
                if (strtolower($key) === 'color' && !empty($val)) {
                    $colors[$val] = true;
                }
            }
        }
        return array_keys($colors);
    }

    public function availableSizes(): array
    {
        if (!$this->has_variants) {
            return [];
        }
        $sizes = [];
        foreach ($this->activeVariants as $v) {
            $attrs = $v->attributes ?? [];
            foreach ($attrs as $key => $val) {
                if (in_array(strtolower($key), ['talla', 'tamaño', 'size']) && !empty($val)) {
                    $sizes[$val] = true;
                }
            }
        }
        return array_keys($sizes);
    }
}
