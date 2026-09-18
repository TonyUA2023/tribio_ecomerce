<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
view()->share('errors', new Illuminate\Support\ViewErrorBag());
$manifest = json_decode(file_get_contents(__DIR__.'/../public/build/manifest.json'), true);
$head = '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
foreach (['resources/css/app.css', 'resources/css/dashboard.css'] as $entry) $head .= '<link rel="stylesheet" href="/build/'.$manifest[$entry]['file'].'">';
$head .= '<script type="module" src="/build/'.$manifest['resources/js/app.js']['file'].'"></script></head><body class="dashboard-body dashboard-workspace" style="overflow:auto"><main style="max-width:800px;margin:auto;padding:24px"><h1 style="font-size:24px;margin-bottom:24px">Fotos del producto</h1><form onsubmit="event.preventDefault()"><div style="display:grid;gap:24px">';
$html = Illuminate\Support\Facades\Blade::render('<x-image-picker name="image" label="Imagen principal" current="/actual.png" :removable="true"/><x-image-picker name="gallery[]" label="Fotos secundarias" :multiple="true"/>');
file_put_contents(__DIR__.'/image-ux-review/index.html', $head.$html.'</div></form></main></body></html>');
