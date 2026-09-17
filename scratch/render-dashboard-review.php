<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\URL::forceRootUrl('http://127.0.0.1:8765');
$user = new App\Models\User(['name' => 'Lucía Torres', 'role' => 'store_owner']);
$store = new App\Models\Store(['name' => 'Estudio Lucía', 'slug' => 'estudio-lucia', 'status' => 'active']);
$user->setRelation('store', $store);
Illuminate\Support\Facades\Auth::setUser($user);
$stats = ['active_products'=>24,'total_products'=>28,'pending_orders'=>8,'total_orders'=>126,'revenue_month'=>8420.50,'revenue_total'=>32650,'low_stock_products'=>3,'out_of_stock'=>2];
$topProducts = collect(['Bolso de viaje', 'Zapatillas urbanas', 'Vestido de verano', 'Mochila clásica', 'Camisa de lino'])->map(function ($name, $i) { $p = new App\Models\Product(['name'=>$name,'price'=>89 + $i * 30,'sold_count'=>36 - $i * 4]); $p->id = $i + 1; return $p; });
$recentOrders = collect(['Mariana López','Carlos Mendoza','Ana Ramírez','Diego Flores','Valeria Rojas'])->map(function ($name, $i) { $o = new App\Models\Order(['customer_name'=>$name,'order_number'=>'TRB-2026-000'.($i+1),'total'=>129 + $i * 20,'status'=>['pending','delivered','confirmed','shipped','pending'][$i]]); $o->id=$i+1; $o->created_at=now()->subHours($i * 6); return $o; });
$html = view('dashboard.index', compact('store','stats','topProducts','recentOrders'))->render();
file_put_contents(__DIR__.'/dashboard-review/index.html', $html);
