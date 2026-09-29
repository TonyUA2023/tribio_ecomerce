<?php

namespace App\Http\Requests\Api;

use App\Support\BusinessProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * "Mi tienda" from the mobile app. Same rules as the web form, but every field is
 * `sometimes`: the app may save one section at a time without resending the rest.
 */
class UpdateStoreSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->currentStore();
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('custom_domain')) {
            $domain = preg_replace('#^https?://#i', '', (string) $this->input('custom_domain'));
            $this->merge(['custom_domain' => strtolower(trim(explode('/', $domain)[0]))]);
        }
    }

    public function rules(): array
    {
        $storeId = $this->user()->currentStore()->id;

        return [
            'name'             => 'sometimes|required|string|max:255',
            'slug'             => 'sometimes|nullable|string|max:150|alpha_dash|unique:stores,slug,' . $storeId,
            'tagline'          => 'sometimes|nullable|string|max:150',
            'description'      => 'sometimes|nullable|string|max:1000',
            'category'         => ['sometimes', 'required', 'string', Rule::in(BusinessProfile::keys())],
            'whatsapp_phone'   => 'sometimes|nullable|string|max:20',
            'phone'            => 'sometimes|nullable|string|max:20',
            'email'            => 'sometimes|nullable|email|max:255',
            'contact_email'    => 'sometimes|nullable|email|max:255',
            'contact_phone'    => 'sometimes|nullable|string|max:20',
            'address'          => 'sometimes|nullable|string|max:255',
            'city'             => 'sometimes|nullable|string|max:100',
            'country'          => 'sometimes|nullable|string|max:100',
            'facebook_url'     => 'sometimes|nullable|url',
            'instagram_url'    => 'sometimes|nullable|url',
            'tiktok_url'       => 'sometimes|nullable|url',
            'meta_title'       => 'sometimes|nullable|string|max:70',
            'meta_description' => 'sometimes|nullable|string|max:160',
            'custom_domain'    => [
                'sometimes', 'nullable', 'string', 'max:255',
                'unique:stores,custom_domain,' . $storeId,
                'regex:/^[a-zA-Z0-9][-a-zA-Z0-9]{0,62}(\.[a-zA-Z0-9][-a-zA-Z0-9]{0,62})+(:[0-9]{1,5})?$/',
            ],
            'accent_color'    => ['sometimes', 'nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'secondary_color' => ['sometimes', 'nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],

            'distributors'               => 'sometimes|nullable|array',
            'distributors.*.region'      => 'nullable|string|max:100',
            'distributors.*.locations'   => 'nullable|array',
            'distributors.*.locations.*' => 'nullable|string|max:150',

            'is_express_shipping_enabled' => 'sometimes|boolean',
            'express_shipping_cost'       => 'sometimes|nullable|numeric|min:0',
            'national_shipping_cost'      => 'sometimes|nullable|numeric|min:0',
            'free_shipping_min_quantity'  => 'sometimes|nullable|integer|min:1',
            'free_shipping_min_amount'    => 'sometimes|nullable|numeric|min:0',
            'bulk_discount_min_quantity'  => 'sometimes|nullable|integer|min:1',
            'bulk_discount_type'          => 'sometimes|nullable|string|in:percentage,fixed',
            'bulk_discount_value'         => 'sometimes|nullable|numeric|min:0',
            'enabled_countries'           => 'sometimes|nullable|array',
            'enabled_countries.*'         => 'string|in:PE,US,ES,MX,CO,EC,CL,AR',
            'country_shipping_costs'      => 'sometimes|nullable|array',
            'is_multilanguage_enabled'    => 'sometimes|boolean',
            'made_to_order_enabled'       => 'sometimes|boolean',
            'deposit_percent'             => 'sometimes|nullable|integer|min:1|max:100',

            'hero_title'    => 'sometimes|nullable|string|max:100',
            'hero_subtitle' => 'sometimes|nullable|string|max:200',
            'hero_badge'    => 'sometimes|nullable|string|max:50',
        ];
    }

    public function messages(): array
    {
        $url = 'Escribe un enlace completo que empiece con https://';
        $email = 'Escribe un correo válido (por ejemplo, hola@mitienda.com).';
        $color = 'Usa un color en formato #RRGGBB (por ejemplo, #0052CC).';

        return [
            'name.required'        => 'El nombre del negocio es obligatorio.',
            'name.max'             => 'El nombre puede tener como máximo 255 caracteres.',
            'slug.alpha_dash'      => 'El enlace solo puede tener letras, números y guiones.',
            'slug.unique'          => 'Ese enlace ya lo usa otra tienda.',
            'category.in'          => 'Elige un rubro de la lista.',
            'facebook_url.url'     => $url,
            'instagram_url.url'    => $url,
            'tiktok_url.url'       => $url,
            'email.email'          => $email,
            'contact_email.email'  => $email,
            'accent_color.regex'   => $color,
            'secondary_color.regex' => $color,
            'custom_domain.regex'  => 'El formato del dominio no es válido. Debe ser similar a "mitienda.com" (sin http:// ni / al final).',
            'custom_domain.unique' => 'Este dominio ya está configurado en otra tienda.',
            'meta_title.max'       => 'El título para Google puede tener hasta 70 caracteres.',
            'meta_description.max' => 'La descripción para Google puede tener hasta 160 caracteres.',
            'deposit_percent.max'  => 'El adelanto no puede pasar de 100%.',
            'enabled_countries.*.in' => 'Ese país todavía no está disponible.',
        ];
    }
}
