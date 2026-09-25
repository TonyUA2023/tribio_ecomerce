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
        $googleReady   = filled(config('services.google.client_id'));

        return view('public.home', compact('featuredStores', 'totalStores', 'totalProducts', 'googleReady'));
    }

    public function terms()
    {
        return view('public.legal.terms');
    }

    public function privacy()
    {
        return view('public.legal.privacy');
    }

    public function refunds()
    {
        return view('public.legal.refunds');
    }
}
