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
        'flow_enabled', 'flow_api_key', 'flow_secret_key', 'flow_mode', 'flow_currency',
        'user_id', 'name', 'slug', 'description', 'tagline', 'category',
        'logo_path', 'cover_path', 'favicon_path',
        'template_name', 'build_mode', 'accent_color', 'secondary_color', 'text_color', 'bg_color',
        'hero_carousel', 'hero_style', 'custom_css_vars', 'distributors',
        'whatsapp_phone', 'phone', 'email', 'address', 'city', 'country',
        'facebook_url', 'instagram_url', 'tiktok_url', 'website_url', 'custom_domain',
        'status', 'plan', 'plan_expires_at', 'is_featured',
        'meta_title', 'meta_description',
        'total_views', 'total_orders', 'total_revenue',
        'checkout_mode', 'payment_gateway', 'gateway_public_key', 'gateway_private_key', 'gateway_access_token',
        'mp_access_token', 'mp_public_key', 'contact_email', 'contact_phone',
        'culqi_customer_id', 'culqi_card_id', 'culqi_subscription_id',
        'paypal_client_id', 'paypal_client_secret', 'paypal_mode', 'paypal_webhook_id',
        'is_express_shipping_enabled', 'express_shipping_cost',
        'is_multilanguage_enabled', 'hero_badge', 'hero_title', 'hero_subtitle',
        'enabled_countries', 'national_shipping_cost', 'country_shipping_costs',
        'free_shipping_min_quantity', 'free_shipping_min_amount',
        'bulk_discount_min_quantity', 'bulk_discount_type', 'bulk_discount_value',
    ];

    protected $hidden = ['flow_api_key', 'flow_secret_key'];

    protected $casts = [
        'flow_enabled' => 'boolean',
        'flow_api_key' => 'encrypted',
        'flow_secret_key' => 'encrypted',
        'custom_css_vars'  => 'array',
        'published_layout' => 'array',
        'distributors'     => 'array',
        'enabled_countries'=> 'array',
        'country_shipping_costs' => 'array',
        'hero_carousel'    => 'boolean',
        'is_featured'      => 'boolean',
        'is_express_shipping_enabled' => 'boolean',
        'is_multilanguage_enabled'    => 'boolean',
        'plan_expires_at'  => 'datetime',
        'total_revenue'    => 'decimal:2',
        'express_shipping_cost' => 'decimal:2',
        'national_shipping_cost' => 'decimal:2',
        'free_shipping_min_amount' => 'decimal:2',
        'bulk_discount_value'      => 'decimal:2',
    ];

    public function getEnabledCountriesList(): array
    {
        $countries = $this->enabled_countries;
        if (empty($countries) || !is_array($countries)) {
            return ['PE', 'US'];
        }
        return array_values(array_unique($countries));
    }

    public function getEnabledCountriesWithDetails(): array
    {
        $enabled = $this->getEnabledCountriesList();
        $all = \App\Helpers\CurrencyHelper::supportedCountries();
        $result = [];
        foreach ($enabled as $code) {
            if (isset($all[$code])) {
                $result[$code] = $all[$code];
            }
        }
        return $result;
    }

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
        if ($this->custom_domain && str_contains($this->custom_domain, '.')) {
            $scheme = request()->getScheme();
            $port = request()->getPort();
            $portStr = ($port && !in_array($port, [80, 443])) ? ':' . $port : '';
            return "{$scheme}://{$this->custom_domain}{$portStr}";
        }
        return route('store.show', $this->slug);
    }

    public function getWhatsappLinkAttribute(): ?string
    {
        $phone = preg_replace('/[^0-9]/', '', $this->whatsapp_phone ?? '');
        return !empty($phone) ? "https://wa.me/{$phone}" : null;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hasPlanExpired(): bool
    {
        return $this->plan_expires_at && $this->plan_expires_at->isPast();
    }

    /**
     * Free shipping when EITHER configured threshold is met (OR, not AND) — a store
     * owner can use just one, both, or neither. Either column being null/0 disables
     * that specific condition without affecting the other.
     */
    public function qualifiesForFreeShipping(float $subtotal, int $quantity): bool
    {
        if ($this->free_shipping_min_quantity && $quantity >= $this->free_shipping_min_quantity) {
            return true;
        }

        if ($this->free_shipping_min_amount && $subtotal >= (float) $this->free_shipping_min_amount) {
            return true;
        }

        return false;
    }

    /**
     * A single quantity-triggered discount on the order subtotal (e.g. "10% off from
     * 10 items"). Never discounts more than the subtotal itself, and a percentage
     * above 100 is clamped — both guard against a store owner mistyping the value.
     */
    public function calculateBulkDiscount(float $subtotal, int $quantity): float
    {
        if (!$this->bulk_discount_min_quantity || $quantity < $this->bulk_discount_min_quantity) {
            return 0.0;
        }

        $value = (float) ($this->bulk_discount_value ?? 0);
        if ($value <= 0) {
            return 0.0;
        }

        if ($this->bulk_discount_type === 'percentage') {
            return round($subtotal * (min($value, 100) / 100), 2);
        }

        return round(min($value, $subtotal), 2);
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

    public function shippingRates()
    {
        return $this->hasMany(ShippingRate::class);
    }

    public function contactMessages()
    {
        return $this->hasMany(ContactMessage::class);
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

    public function brands()
    {
        return $this->hasMany(Brand::class);
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

    public function sections()
    {
        return $this->hasMany(StoreSection::class)->orderBy('order');
    }
}
