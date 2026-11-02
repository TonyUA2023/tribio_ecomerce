<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\GenerateCopyRequest;
use App\Http\Requests\Dashboard\SaveBrandKitRequest;
use App\Http\Requests\Dashboard\StoreFlyerRequest;
use App\Models\BrandKit;
use App\Models\MarketingAsset;
use App\Services\AiMarketing\AiCredits;
use App\Services\AiMarketing\AiMarketingException;
use App\Services\AiMarketing\AiMarketingSchema;
use App\Services\AiMarketing\CopyGenerator;
use App\Services\AiMarketing\FlyerTemplates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Dashboard → Marketing → Contenido con IA: brand kit, AI copy and flyers with real prices. */
class AiContentController extends Controller
{
    public function index(AiMarketingSchema $schema, AiCredits $credits, CopyGenerator $copy)
    {
        $store = Auth::user()->currentStore();
        $ready = $schema->ready();

        return view('dashboard.marketing.content', [
            'store'       => $store,
            'ready'       => $ready,
            'aiAvailable' => $ready && $copy->available(),
            'kit'         => $ready ? BrandKit::forStore($store) : null,
            'allowance'   => $credits->allowance($store),
            'remaining'   => $ready ? $credits->remaining($store) : 0,
            'products'    => $store->products()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'assets'      => $ready
                ? MarketingAsset::where('store_id', $store->id)->with('product:id,name')->latest('id')->limit(12)->get()
                : collect(),
        ]);
    }

    public function updateBrandKit(SaveBrandKitRequest $request, AiMarketingSchema $schema): RedirectResponse
    {
        if (!$schema->ready()) {
            return back()->with('error', 'Este módulo aún no está activo en el servidor. Inténtalo más tarde.');
        }

        $store = $request->user()->currentStore();
        BrandKit::updateOrCreate(['store_id' => $store->id], $request->validated());

        return redirect()->route('dashboard.marketing.content.index')->with('success', 'Guardamos tu kit de marca. Lo usaremos en tus textos y flyers.');
    }

    public function generateCopy(GenerateCopyRequest $request, AiMarketingSchema $schema, CopyGenerator $generator, AiCredits $credits): JsonResponse
    {
        if (!$schema->ready()) {
            return response()->json(['message' => 'Este módulo aún no está activo en el servidor.'], 503);
        }

        $store = $request->user()->currentStore();
        $product = $request->filled('product_id') ? $store->products()->findOrFail($request->integer('product_id')) : null;

        try {
            $asset = $generator->generate($store, $product, $request->input('objective'), $request->input('channels'), $request->input('notes'), $request->user()->id);
        } catch (AiMarketingException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'items'     => $asset->content['items'],
            'remaining' => $credits->remaining($store),
        ]);
    }

    public function flyer(Request $request, AiMarketingSchema $schema)
    {
        $store = $request->user()->currentStore();
        abort_unless($schema->ready(), 404);

        $kit = BrandKit::forStore($store);
        $products = $store->products()->where('is_active', true)->orderBy('name')->get();

        return view('dashboard.marketing.flyer', [
            'store'     => $store,
            'products'  => $products->map(fn ($product) => FlyerTemplates::data($store, $product, $kit))->values(),
            'brand'     => FlyerTemplates::brand($store, $kit),
            'selected'  => $request->integer('producto') ?: $products->first()?->id,
            'templates' => FlyerTemplates::TEMPLATES,
            'formats'   => FlyerTemplates::FORMATS,
        ]);
    }

    public function storeFlyer(StoreFlyerRequest $request, AiMarketingSchema $schema): JsonResponse
    {
        abort_unless($schema->ready(), 503);
        $store = $request->user()->currentStore();
        $product = $request->filled('product_id') ? $store->products()->findOrFail($request->integer('product_id')) : null;

        $path = $request->file('image')->storeAs("stores/{$store->id}/marketing", 'flyer-' . Str::uuid() . '.png', 'public');

        $asset = MarketingAsset::create([
            'store_id'   => $store->id,
            'product_id' => $product?->id,
            'user_id'    => $request->user()->id,
            'kind'       => MarketingAsset::KIND_FLYER,
            'template'   => $request->input('template'),
            'format'     => $request->input('format'),
            'title'      => $product?->name ?? 'Flyer',
            'file_path'  => $path,
        ]);

        return response()->json(['url' => $asset->file_url]);
    }

    public function destroyAsset(Request $request, MarketingAsset $asset): RedirectResponse
    {
        abort_unless($asset->store_id === $request->user()->currentStore()?->id, 404);

        if ($asset->file_path) {
            Storage::disk('public')->delete($asset->file_path);
        }
        $asset->delete();

        return back()->with('success', 'Lo quitamos de tu biblioteca.');
    }
}
