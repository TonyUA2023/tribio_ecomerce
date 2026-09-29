<?php

namespace App\Http\Resources\Mobile;

use App\Support\BusinessProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * "Mi tienda" as the mobile app edits it. An explicit allow-list on purpose: the Store
 * row also holds payment credentials, which must never travel to a phone.
 */
class StoreSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $s = $this->resource;

        return [
            'id'             => $s->id,
            'name'           => $s->name,
            'slug'           => $s->slug,
            'url'            => $s->url,
            'status'         => $s->status,
            'plan'           => $s->plan,
            'category'       => $s->category,
            'category_label' => BusinessProfile::forStore($s)->label(),
            'tagline'        => $s->tagline,
            'description'    => $s->description,
            'logo_url'       => $s->logo_url,
            'cover_url'      => $s->cover_url,
            'has_logo'       => (bool) $s->logo_path,
            'has_cover'      => (bool) $s->cover_path,
            'accent_color'    => $s->accent_color,
            'secondary_color' => $s->secondary_color,

            'contact_email'  => $s->contact_email,
            'contact_phone'  => $s->contact_phone,
            'email'          => $s->email,
            'phone'          => $s->phone,
            'whatsapp_phone' => $s->whatsapp_phone,
            'address'        => $s->address,
            'city'           => $s->city,
            'country'        => $s->country,
            'facebook_url'   => $s->facebook_url,
            'instagram_url'  => $s->instagram_url,
            'tiktok_url'     => $s->tiktok_url,

            'enabled_countries'           => $s->getEnabledCountriesList(),
            'country_shipping_costs'      => $s->country_shipping_costs ?: (object) [],
            'is_express_shipping_enabled' => (bool) $s->is_express_shipping_enabled,
            'express_shipping_cost'       => $this->money($s->express_shipping_cost),
            'national_shipping_cost'      => $this->money($s->national_shipping_cost),
            'free_shipping_min_quantity'  => $s->free_shipping_min_quantity,
            'free_shipping_min_amount'    => $this->money($s->free_shipping_min_amount),
            'bulk_discount_min_quantity'  => $s->bulk_discount_min_quantity,
            'bulk_discount_type'          => $s->bulk_discount_type,
            'bulk_discount_value'         => $this->money($s->bulk_discount_value),

            'made_to_order_enabled'    => (bool) $s->made_to_order_enabled,
            'deposit_percent'          => (int) ($s->deposit_percent ?: 100),
            'is_multilanguage_enabled' => (bool) $s->is_multilanguage_enabled,

            'custom_domain'    => $s->custom_domain,
            'meta_title'       => $s->meta_title,
            'meta_description' => $s->meta_description,
            'hero_badge'       => $s->hero_badge,
            'hero_title'       => $s->hero_title,
            'hero_subtitle'    => $s->hero_subtitle,
            'distributors'     => $s->distributors ?: [],
            'checkout_mode'    => $s->checkout_mode,
        ];
    }

    private function money(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }
}
