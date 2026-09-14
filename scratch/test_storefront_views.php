<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$store = \App\Models\Store::find(3) ?? \App\Models\Store::first();
view()->share('errors', new \Illuminate\Support\ViewErrorBag);

$featuredProducts = $store->featuredProducts()->limit(4)->get();
$categories = $store->categories()->whereNull('parent_id')->get();
$galleryItems = $store->galleryItems()->limit(4)->get();
$allProducts = $store->activeProducts()->paginate(8);
$sections = collect();

$html = view('templates.minimal-light.store', compact('store', 'featuredProducts', 'categories', 'galleryItems', 'allProducts', 'sections'))->render();
echo "Store page rendered successfully! Length: " . strlen($html) . "\n";