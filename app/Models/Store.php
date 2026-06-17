<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Store extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'name', 'slug', 'description', 'tagline', 'category',
        'logo_path', 'cover_path', 'favicon_path',
        'template_name', 'accent_color', 'secondary_color', 'text_color', 'bg_color',
        'hero_carousel', 'hero_style', 'custom_css_vars', 'distributors',
        'whatsapp_phone', 'phone', 'email', 'address', 'city', 'country',
        'facebook_url', 'instagram_url', 'tiktok_url', 'website_url', 'custom_domain',
        'status', 'plan', 'plan_expires_at', 'is_featured',
        'meta_title', 'meta_description',
        'total_views', 'total_orders', 'total_revenue',
    ];

    protected $casts = [
        'custom_css_vars'  => 'array',
        'distributors'     => 'array',
        'hero_carousel'    => 'boolean',
        'is_featured'      => 'boolean',
        'plan_expires_at'  => 'datetime',
        'total_revenue'    => 'decimal:2',
    ];

    // ─── Scopes ──────────────────────────────────────────────────
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    // ─── Helpers ─────────────────────────────────────────────────
    public function getLogoUrlAttribute(): string
    {
        return $this->logo_path
            ? asset('storage/' . $this->logo_path)
            : asset('images/default-logo.svg');
    }

    public function getCoverUrlAttribute(): string
    {
        return $this->cover_path
            ? asset('storage/' . $this->cover_path)
            : asset('images/default-cover.jpg');
    }

    public function getUrlAttribute(): string
    {
        return route('store.show', $this->slug);
    }

    public function getWhatsappLinkAttribute(): string
    {
        $phone = preg_replace('/[^0-9]/', '', $this->whatsapp_phone ?? '');
        return "https://wa.me/{$phone}";
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hasPlanExpired(): bool
    {
        return $this->plan_expires_at && $this->plan_expires_at->isPast();
    }

    // ─── Relationships ───────────────────────────────────────────
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function activeProducts()
    {
        return $this->hasMany(Product::class)->where('is_active', true);
    }

    public function featuredProducts()
    {
        return $this->hasMany(Product::class)->where('is_featured', true)->where('is_active', true);
    }

    public function categories()
    {
        return $this->hasMany(Category::class)->orderBy('sort_order');
    }

    public function galleryItems()
    {
        return $this->hasMany(GalleryItem::class)->orderBy('sort_order');
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function analytics()
    {
        return $this->hasMany(StoreAnalytic::class);
    }

    public function inventoryMovements()
    {
        return $this->hasMany(InventoryMovement::class);
    }
}
