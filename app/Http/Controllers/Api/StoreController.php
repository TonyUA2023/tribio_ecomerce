<?php

namespace App\Http\Controllers\Api;

use App\Helpers\CurrencyHelper;
use App\Http\Controllers\Api\Concerns\ResolvesCurrentStore;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateStoreSettingsRequest;
use App\Http\Resources\Mobile\CategoryResource;
use App\Http\Resources\Mobile\StoreSettingsResource;
use App\Services\Dashboard\StoreSettingsService;
use App\Services\Storefront\TemplateRegistry;
use App\Support\BusinessProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** "Mi tienda": the store's public identity, contact, selling rules and logo/cover. */
class StoreController extends Controller
{
    use ResolvesCurrentStore;

    public function __construct(private StoreSettingsService $settings)
    {
    }

    public function show(): JsonResponse
    {
        $store = $this->currentStore();

        return response()->json([
            'store'      => new StoreSettingsResource($store),
            'categories' => CategoryResource::collection($store->categories()->orderBy('sort_order')->get()),
            'brands'     => $store->brands()->orderBy('name')->get(['id', 'name']),
            'options'    => [
                'business_categories' => collect(config('tribio.business_categories', []))
                    ->map(fn (array $c) => trim(($c['icon'] ?? '') . ' ' . $c['label']))->all(),
                'countries' => collect(CurrencyHelper::supportedCountries())
                    ->map(fn (array $c) => ['code' => $c['code'], 'name' => $c['name'], 'flag' => $c['flag'], 'currency' => $c['currency'], 'symbol' => $c['symbol']])
                    ->values(),
                // Precios por mayor: false until the wholesale migration has run.
                'wholesale_available' => \App\Services\Pricing\WholesalePricing::ready(),
                'recommends_made_to_order' => BusinessProfile::forStore($store)->recommendsMadeToOrder(),
                // Stores on a customizable template edit their hero copy in Plantillas (web).
                'hero_managed_by_template' => app(TemplateRegistry::class)->isCustomizable($store->template_name) && !$store->template_locked,
            ],
        ]);
    }

    public function update(UpdateStoreSettingsRequest $request): JsonResponse
    {
        $store = $this->currentStore();
        $data = $request->validated();

        if (app(TemplateRegistry::class)->isCustomizable($store->template_name) && !$store->template_locked) {
            unset($data['hero_title'], $data['hero_subtitle'], $data['hero_badge']);
        }

        $store = $this->settings->update($store, $data);

        return response()->json([
            'message' => 'Información de la tienda actualizada correctamente.',
            'store'   => new StoreSettingsResource($store),
        ]);
    }

    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate(['logo' => 'required|image|mimes:png,jpg,jpeg,webp|max:2048']);
        $store = $this->currentStore();

        $hasPalette = $this->settings->storeLogo($store, $request->file('logo'));

        return response()->json([
            'message' => $hasPalette
                ? 'Logo guardado. Preparamos una paleta de colores a partir de él.'
                : 'Logo guardado. No pudimos identificar colores visibles en la imagen.',
            'store'   => new StoreSettingsResource($store->refresh()),
        ]);
    }

    public function uploadCover(Request $request): JsonResponse
    {
        $request->validate(['cover' => 'required|image|mimes:png,jpg,jpeg,webp|max:5120']);
        $store = $this->currentStore();

        $this->settings->storeCover($store, $request->file('cover'));

        return response()->json([
            'message' => 'Portada actualizada correctamente.',
            'store'   => new StoreSettingsResource($store->refresh()),
        ]);
    }
}
