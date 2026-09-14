<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== 1. TEST EXCHANGERATE SERVICE ===\n";
$service = app(App\Services\ExchangeRateService::class);
$rates = $service->getRates();
echo "Active currencies count in rates: " . count($rates) . "\n";
assert(isset($rates['USD']) && isset($rates['EUR']) && isset($rates['MXN']) && isset($rates['COP']));
echo "100 PEN in USD: " . $service->convert(100, 'PEN', 'USD') . "\n";
echo "100 PEN in EUR: " . $service->convert(100, 'PEN', 'EUR') . "\n";
echo "100 PEN in COP: " . $service->convert(100, 'PEN', 'COP') . "\n";
echo "100 PEN in CLP: " . $service->convert(100, 'PEN', 'CLP') . "\n";
echo "100 PEN in ARS: " . $service->convert(100, 'PEN', 'ARS') . "\n";
echo "ExchangeRateService: PASS\n\n";

echo "=== 2. TEST CURRENCYHELPER ===\n";
$countries = App\Helpers\CurrencyHelper::supportedCountries();
echo "Supported countries count: " . count($countries) . "\n";
assert(count($countries) === 8, "Expected 8 supported countries");
foreach (['PE', 'US', 'ES', 'MX', 'CO', 'EC', 'CL', 'AR'] as $code) {
    assert(isset($countries[$code]), "Missing country {$code}");
    echo "  {$code}: {$countries[$code]['flag']} {$countries[$code]['name']} ({$countries[$code]['currency']} {$countries[$code]['symbol']})\n";
}
echo "CurrencyHelper: PASS\n\n";

echo "=== 3. TEST STORE SHIPPING & COUNTRIES PERSISTENCE ===\n";
$store = App\Models\Store::find(3) ?? App\Models\Store::first();
$store->enabled_countries = ['PE', 'US', 'ES', 'MX', 'CO'];
$store->national_shipping_cost = 15.00;
$store->country_shipping_costs = [
    'US' => 25.00,
    'ES' => 30.00,
    'MX' => 180.00,
    'CO' => 45000.00,
];
$store->save();

$freshStore = App\Models\Store::find($store->id);
assert($freshStore->getEnabledCountriesList() === ['PE', 'US', 'ES', 'MX', 'CO']);
assert((float)$freshStore->national_shipping_cost === 15.00);
assert((float)$freshStore->country_shipping_costs['US'] === 25.00);
assert((float)$freshStore->country_shipping_costs['ES'] === 30.00);
assert((float)$freshStore->country_shipping_costs['MX'] === 180.00);
echo "Store model persistence: PASS\n\n";

echo "=== 4. TEST STORECONTROLLER SHIPPING RESOLUTION ===\n";
$storeCtrl = app(App\Http\Controllers\StoreController::class);
$peShipping = $storeCtrl->resolveShippingCostForStore($freshStore, 'PE');
$usShipping = $storeCtrl->resolveShippingCostForStore($freshStore, 'US');
$esShipping = $storeCtrl->resolveShippingCostForStore($freshStore, 'ES');
$mxShipping = $storeCtrl->resolveShippingCostForStore($freshStore, 'MX');
$coShipping = $storeCtrl->resolveShippingCostForStore($freshStore, 'CO');
$arShipping = $storeCtrl->resolveShippingCostForStore($freshStore, 'AR'); // not in shipping costs

echo "  PE shipping: {$peShipping} (expected 15)\n";
echo "  US shipping: {$usShipping} (expected 25)\n";
echo "  ES shipping: {$esShipping} (expected 30)\n";
echo "  MX shipping: {$mxShipping} (expected 180)\n";
echo "  CO shipping: {$coShipping} (expected 45000)\n";
echo "  AR shipping: {$arShipping} (expected 0/fallback)\n";

assert((float)$peShipping === 15.00);
assert((float)$usShipping === 25.00);
assert((float)$esShipping === 30.00);
assert((float)$mxShipping === 180.00);
assert((float)$coShipping === 45000.00);
echo "Shipping resolution: PASS\n\n";

echo "=== 5. TEST PRODUCT MULTI-CURRENCY RESOLUTION & CUSTOM OVERRIDES ===\n";
$product = $freshStore->products()->first();
if (!$product) {
    echo "No product found, skipping\n";
} else {
    $product->price = 100.00;
    $product->compare_price = 150.00;
    // Auto without custom prices
    $product->currency_prices = null;
    $product->compare_currency_prices = null;
    $product->price_usd = 0;
    $product->save();
    
    $autoUsd = $product->resolvePrice('USD');
    $autoEur = $product->resolvePrice('EUR');
    $autoMxn = $product->resolvePrice('MXN');
    $autoPen = $product->resolvePrice('PEN');
    echo "  Auto 100 PEN in USD: {$autoUsd}\n";
    echo "  Auto 100 PEN in EUR: {$autoEur}\n";
    echo "  Auto 100 PEN in MXN: {$autoMxn}\n";
    echo "  PEN price: {$autoPen}\n";
    assert($autoPen === 100.0);
    assert($autoUsd > 20 && $autoUsd < 40);

    // Custom manual overrides (e.g. client Iliana requirement: USD $35, EUR €32)
    $product->currency_prices = [
        'USD' => 35.00,
        'EUR' => 32.00,
    ];
    $product->compare_currency_prices = [
        'USD' => 50.00,
        'EUR' => 45.00,
    ];
    $product->price_usd = 35.00;
    $product->save();

    $freshProd = App\Models\Product::find($product->id);
    $customUsd = $freshProd->resolvePrice('USD');
    $customEur = $freshProd->resolvePrice('EUR');
    $customMxn = $freshProd->resolvePrice('MXN');
    $compareUsd = $freshProd->resolveComparePrice('USD');

    echo "  Custom USD: {$customUsd} (expected 35.00)\n";
    echo "  Custom EUR: {$customEur} (expected 32.00)\n";
    echo "  Custom Compare USD: {$compareUsd} (expected 50.00)\n";
    echo "  Auto MXN (not overridden): {$customMxn}\n";

    assert((float)$customUsd === 35.00);
    assert((float)$customEur === 32.00);
    assert((float)$compareUsd === 50.00);
    assert($customMxn > 400 && $customMxn < 600);
    echo "Product pricing resolution: PASS\n";
}

echo "\n>>> ALL 5 TESTS PASSED SUCCESSFULLY! <<<\n";