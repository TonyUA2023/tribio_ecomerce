<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$svc = app(App\Services\ExchangeRateService::class);
echo "RATES: " . json_encode($svc->getRates()) . "\n";
echo "100 PEN in USD: " . $svc->convert(100, 'PEN', 'USD') . "\n";
echo "100 PEN in EUR: " . $svc->convert(100, 'PEN', 'EUR') . "\n";
echo "100 PEN in MXN: " . $svc->convert(100, 'PEN', 'MXN') . "\n";
echo "100 PEN in COP: " . $svc->convert(100, 'PEN', 'COP') . "\n";
echo "100 PEN in CLP: " . $svc->convert(100, 'PEN', 'CLP') . "\n";
echo "100 PEN in ARS: " . $svc->convert(100, 'PEN', 'ARS') . "\n";