<?php
if (!str_starts_with(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/__flow-preview')) {
    if (str_starts_with($_SERVER['REQUEST_URI'], '/build/')) return false;
    header('Content-Type: application/json'); echo '{}'; return;
}
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$store = new App\Models\Store([
    'id' => 1, 'name' => 'Maetek Store', 'slug' => 'maetek-store', 'template_name' => 'minimal-light',
    'checkout_mode' => 'mixed', 'flow_enabled' => true, 'flow_api_key' => 'demo', 'flow_secret_key' => 'demo',
    'flow_mode' => 'sandbox', 'flow_currency' => 'PEN', 'accent_color' => '#1A1A1A', 'secondary_color' => '#C8A68B',
]);
if (isset($_GET['result'])) {
    $order = new App\Models\Order(['order_number' => 'TRB-2026-000123', 'total' => 149.90, 'currency' => 'PEN', 'payment_status' => $_GET['result'], 'flow_token' => 'demo']);
    echo view('payments.flow-result', ['store' => $store, 'order' => $order, 'unavailable' => false])->render();
    return;
}
$manifest = json_decode(file_get_contents(__DIR__.'/../public/build/manifest.json'), true);
echo '<!doctype html><html lang="es"><head><script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/build/'.$manifest['resources/css/app.css']['file'].'"><script type="module" src="/build/'.$manifest['resources/js/app.js']['file'].'"></script></head><body style="background:#FDF8EF"><h1 style="padding:30px">Maetek Store · Vista de prueba</h1>';
echo view('components.checkout.drawer', compact('store'))->render();
echo <<<'HTML'
<script>
window.addEventListener('load', () => {
 const drawer = document.querySelector('#cartDrawer');
 const state = window.Alpine.$data(drawer);
 state.cartItems = [{id:1,name:'Producto de muestra',price:149.90,quantity:1}];
 state.cartOpen = true;
 state.checkoutStep = 2;
 state.customerLoggedIn = true;
 state.customerUser = {name:'Cliente de prueba',email:'demo@example.test'};
 state.customer.name = 'Cliente de prueba';
 state.customer.email = 'demo@example.test';
 state.paymentMethod = 'flow';
});
</script></body></html>
HTML;
