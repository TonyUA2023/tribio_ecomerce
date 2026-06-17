<?php

namespace App\Http\Controllers;

use App\Models\Store;

class PublicController extends Controller
{
    public function home()
    {
        $featuredStores = Store::active()->featured()
            ->withCount('activeProducts')
            ->with(['user'])
            ->limit(6)
            ->get();

        $totalStores   = Store::active()->count();
        $totalProducts = \App\Models\Product::where('is_active', true)->count();

        return view('public.home', compact('featuredStores', 'totalStores', 'totalProducts'));
    }

    public function search()
    {
        $query = request('q', '');
        $category = request('categoria', '');

        $stores = Store::active()
            ->when($query, fn($q) => $q->where('name', 'like', "%{$query}%")
                ->orWhere('description', 'like', "%{$query}%")
                ->orWhere('slug', 'like', "%{$query}%"))
            ->when($category, fn($q) => $q->where('category', $category))
            ->withCount('activeProducts')
            ->paginate(12);

        return view('public.search', compact('stores', 'query', 'category'));
    }

    public function directory()
    {
        $stores = Store::active()
            ->withCount('activeProducts')
            ->orderByDesc('is_featured')
            ->orderByDesc('created_at')
            ->paginate(16);

        return view('public.directory', compact('stores'));
    }
}
