<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$store = \App\Models\Store::find(3) ?? \App\Models\Store::first();
\Illuminate\Support\Facades\Auth::login($store->user);
view()->share('errors', new \Illuminate\Support\ViewErrorBag);
$rendered = view('dashboard.store.edit', ['store' => $store, 'templates' => config('tribio.templates')])->render();
echo "Rendered dashboard.store.edit length: " . strlen($rendered) . "\n";