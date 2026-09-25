<?php

namespace App\Http\Controllers;

use App\Http\Requests\DirectorySearchRequest;
use App\Http\Resources\Directory\DirectoryProductResource;
use App\Http\Resources\Directory\DirectoryStoreResource;
use App\Services\Directory\DirectoryCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * /negocios — the public showcase of every Tribio store: a product search across all
 * of them, a fair daily "vitrina", and a live preview of each storefront. Every link out
 * lands on the client's own store (tagged utm_source=tribio) — Tribio never sells here.
 */
class DirectoryController extends Controller
{
    public function __construct(private readonly DirectoryCatalog $catalog)
    {
    }

    public function index(DirectorySearchRequest $request): View
    {
        // The daily rotation is stable within a day, so the landing is cheap to cache.
        $home = Cache::remember('directory:home:' . today()->toDateString(), now()->addMinutes(10), fn () => $this->home($request));
        $searching = $request->searchQuery() !== '' || $request->category() !== null || $request->sort() === 'ofertas';

        return view('public.directory', [
            'home' => $home,
            'results' => $searching ? $this->results($request) : null,
            'query' => $request->searchQuery(),
            'category' => $request->category(),
            'sort' => $request->sort(),
        ]);
    }

    /** Live search used by the page as the visitor types (contract: vault Directory-API). */
    public function search(DirectorySearchRequest $request): JsonResponse
    {
        return response()->json($this->results($request));
    }

    /** The old store-only search page now lives inside the directory. */
    public function legacySearch(DirectorySearchRequest $request): RedirectResponse
    {
        return redirect()->route('directory', array_filter([
            'q' => $request->searchQuery(), 'categoria' => $request->category(),
        ]), 301);
    }

    private function home(DirectorySearchRequest $request): array
    {
        $stores = $this->catalog->stores();
        $showcase = $this->catalog->showcase($stores, 12);
        $wall = $this->catalog->showcase($stores, 18);

        return [
            'stores' => DirectoryStoreResource::collection($stores)->resolve($request),
            'showcase' => DirectoryProductResource::many($showcase, 'vitrina', $request),
            'deals' => DirectoryProductResource::many($this->catalog->deals($stores), 'ofertas', $request),
            'wall' => DirectoryProductResource::many($wall, 'vitrina', $request),
            'categories' => $this->catalog->categories($stores),
            'suggestions' => $showcase->take(4)->map(fn ($p) => $this->suggestion($p->name))->unique()->values()->all(),
            'stats' => [
                'stores' => $stores->count(),
                'products' => $stores->sum(fn ($store) => $store->directoryProducts->count()),
            ],
        ];
    }

    private function results(DirectorySearchRequest $request): array
    {
        $found = $this->catalog->search($request->searchQuery(), $request->category(), $request->sort());

        return [
            'query' => $request->searchQuery(),
            'category' => $request->category(),
            'sort' => $request->sort(),
            'terms' => $found['terms'],
            'total' => $found['products']->count(),
            'products' => DirectoryProductResource::many($found['products'], 'busqueda', $request),
            'stores' => DirectoryStoreResource::collection($found['stores'])->resolve($request),
        ];
    }

    /** A short, typeable example from a real product name ("Torta de chocolate" → "torta de chocolate"). */
    private function suggestion(string $name): string
    {
        $words = preg_split('/\s+/u', mb_strtolower(trim(preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $name))));

        return implode(' ', array_slice(array_filter($words), 0, 3));
    }
}
