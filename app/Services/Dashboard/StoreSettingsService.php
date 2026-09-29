<?php

namespace App\Services\Dashboard;

use App\Models\Store;
use App\Services\LogoPaletteService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * "Mi tienda" writes for the mobile API. Applies the same normalization the web form
 * does (Dashboard\StoreSettingsController), kept here so the API controller stays thin.
 */
class StoreSettingsService
{
    private const NUMERIC_NULLABLE = [
        'free_shipping_min_quantity', 'free_shipping_min_amount',
        'bulk_discount_min_quantity', 'bulk_discount_value', 'national_shipping_cost',
    ];

    public function __construct(private LogoPaletteService $palette)
    {
    }

    /** @param array<string, mixed> $input validated UpdateStoreSettingsRequest data */
    public function update(Store $store, array $input): Store
    {
        $data = $input;

        // An emptied numeric field must be NULL, not 0: national_shipping_cost 0.00 means
        // "Peru always free", NULL means "use the Zonas de envio rate" (see the web form).
        foreach (self::NUMERIC_NULLABLE as $field) {
            if (array_key_exists($field, $data) && ($data[$field] === '' || $data[$field] === null)) {
                $data[$field] = null;
            }
        }
        if (array_key_exists('bulk_discount_type', $data) && empty($data['bulk_discount_type'])) {
            $data['bulk_discount_type'] = null;
        }

        if (array_key_exists('enabled_countries', $data)) {
            $countries = is_array($data['enabled_countries']) ? $data['enabled_countries'] : ['PE', 'US'];
            if (!in_array('PE', $countries, true)) {
                $countries[] = 'PE'; // Peru is always sold
            }
            $data['enabled_countries'] = array_values(array_unique($countries));
        }

        if (array_key_exists('country_shipping_costs', $data)) {
            $clean = [];
            foreach ((array) $data['country_shipping_costs'] as $code => $cost) {
                if ($cost !== null && $cost !== '' && is_numeric($cost)) {
                    $clean[strtoupper((string) $code)] = round((float) $cost, 2);
                }
            }
            $data['country_shipping_costs'] = $clean;
        }

        if (!empty($data['slug'])) {
            $data['slug'] = Str::slug($data['slug']);
        } elseif (isset($data['name']) && $data['name'] !== $store->name) {
            $data['slug'] = $this->uniqueSlug($store, $data['name']);
        } else {
            unset($data['slug']);
        }

        $store->update($data);

        return $store->refresh();
    }

    /** @return bool whether a color palette could be read from the logo */
    public function storeLogo(Store $store, UploadedFile $file): bool
    {
        $previous = $store->logo_path;
        $palette = $this->palette->extract($file->getRealPath());
        $store->update(['logo_path' => $file->store('logos', 'public')]);

        if ($palette) {
            $this->applyPalette($store, $palette);
        } else {
            $store->update(['logo_palette' => null]);
        }
        if ($previous) {
            Storage::disk('public')->delete($previous);
        }

        return (bool) $palette;
    }

    public function storeCover(Store $store, UploadedFile $file): void
    {
        if ($store->cover_path) {
            Storage::disk('public')->delete($store->cover_path);
        }
        $store->update(['cover_path' => $file->store('covers', 'public')]);
    }

    private function applyPalette(Store $store, array $palette): void
    {
        $previous = $store->logo_palette;
        $changes = ['logo_palette' => $palette];
        if (!$previous || strcasecmp($store->accent_color ?? '', $previous['primary']) === 0) {
            $changes['accent_color'] = $palette['primary'];
        }
        if (!$previous || strcasecmp($store->secondary_color ?? '', $previous['secondary']) === 0) {
            $changes['secondary_color'] = $palette['secondary'];
        }
        $store->update($changes);

        foreach ($store->sections()->where('type', 'hero')->get() as $hero) {
            $data = $hero->data ?? [];
            $mode = $data['palette_mode'] ?? null;
            $untouched = ($data['background_color'] ?? '#f3f4f6') === '#f3f4f6'
                && ($data['text_color'] ?? '#111827') === '#111827';
            if ($mode === 'auto' || ($mode === null && $untouched)) {
                $hero->update(['data' => $this->palette->applyToHero($data, $palette)]);
            }
        }
    }

    private function uniqueSlug(Store $store, string $name): string
    {
        $base = Str::slug($name) ?: 'tienda';
        $slug = $base;
        $i = 1;
        while (Store::where('slug', $slug)->where('id', '!=', $store->id)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
