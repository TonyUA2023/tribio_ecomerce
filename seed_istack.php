<?php
use App\Models\Store;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Support\Str;

$store = Store::where('slug', 'istack')->first();
if (!$store) {
    echo "Store not found.\n";
    exit;
}

// Ensure categories exist
$cats = ['iphone', 'mac', 'ipad', 'accesorios'];
$categoryIds = [];
foreach ($cats as $cat) {
    $c = Category::firstOrCreate(['slug' => $cat], [
        'name' => ucfirst($cat),
        'description' => 'Categoría ' . ucfirst($cat),
        'store_id' => $store->id,
        'is_active' => true,
    ]);
    $categoryIds[$cat] = $c->id;
}

$products = [
    [
        'name' => 'iPhone 15 Pro Max 256GB - Reacondicionado',
        'slug' => Str::slug('iPhone 15 Pro Max 256GB - Reacondicionado'),
        'sku' => 'IP15PM-256',
        'price' => 4599.00,
        'category_id' => $categoryIds['iphone'],
        'image_url' => 'https://store.storeimages.cdn-apple.com/4982/as-images.apple.com/is/iphone-15-pro-finish-select-202309-6-1inch-bluetitanium?wid=5120&hei=2880&fmt=p-jpg&qlt=80&.v=1692846360609',
        'is_featured' => true,
    ],
    [
        'name' => 'iPhone 14 Pro 128GB - Grado A+',
        'slug' => Str::slug('iPhone 14 Pro 128GB - Grado A+'),
        'sku' => 'IP14P-128',
        'price' => 3299.00,
        'category_id' => $categoryIds['iphone'],
        'image_url' => 'https://store.storeimages.cdn-apple.com/4982/as-images.apple.com/is/iphone-14-pro-model-unselect-gallery-2-202209_GEO_US?wid=5120&hei=2880&fmt=p-jpg&qlt=80&.v=1660753617539',
        'is_featured' => true,
    ],
    [
        'name' => 'iPhone 13 128GB - Excelente Estado',
        'slug' => Str::slug('iPhone 13 128GB - Excelente Estado'),
        'sku' => 'IP13-128',
        'price' => 2149.00,
        'category_id' => $categoryIds['iphone'],
        'image_url' => 'https://store.storeimages.cdn-apple.com/4982/as-images.apple.com/is/iphone-13-model-unselect-gallery-2-202207_GEO_US?wid=5120&hei=2880&fmt=p-jpg&qlt=80&.v=1654894188550',
        'is_featured' => true,
    ],
    [
        'name' => 'iPhone 12 64GB - Grado A',
        'slug' => Str::slug('iPhone 12 64GB - Grado A'),
        'sku' => 'IP12-64',
        'price' => 1599.00,
        'category_id' => $categoryIds['iphone'],
        'image_url' => 'https://store.storeimages.cdn-apple.com/4982/as-images.apple.com/is/iphone-12-finish-unselect-gallery-1-202207?wid=5120&hei=2880&fmt=p-jpg&qlt=80&.v=1662145805562',
        'is_featured' => false,
    ],
    [
        'name' => 'MacBook Air M2 8GB 256GB SSD',
        'slug' => Str::slug('MacBook Air M2 8GB 256GB SSD'),
        'sku' => 'MBA-M2-256',
        'price' => 4299.00,
        'category_id' => $categoryIds['mac'],
        'image_url' => 'https://store.storeimages.cdn-apple.com/4982/as-images.apple.com/is/macbook-air-midnight-select-20220606?wid=904&hei=840&fmt=jpeg&qlt=90&.v=1653084303665',
        'is_featured' => true,
    ],
    [
        'name' => 'MacBook Pro 14" M1 Pro 16GB 512GB',
        'slug' => Str::slug('MacBook Pro 14" M1 Pro 16GB 512GB'),
        'sku' => 'MBP-14-M1P',
        'price' => 5999.00,
        'category_id' => $categoryIds['mac'],
        'image_url' => 'https://store.storeimages.cdn-apple.com/4982/as-images.apple.com/is/mbp14-spacegray-select-202110?wid=904&hei=840&fmt=jpeg&qlt=90&.v=1632788573000',
        'is_featured' => true,
    ],
    [
        'name' => 'iPad Air (5ta Gen) 64GB',
        'slug' => Str::slug('iPad Air (5ta Gen) 64GB'),
        'sku' => 'IPADA-5-64',
        'price' => 2499.00,
        'category_id' => $categoryIds['ipad'],
        'image_url' => 'https://store.storeimages.cdn-apple.com/4982/as-images.apple.com/is/ipad-air-storage-select-202207-blue-wifi_FMT_WHH?wid=1280&hei=720&fmt=jpeg&qlt=90&.v=1670856064287',
        'is_featured' => true,
    ],
    [
        'name' => 'iPad Pro 11" M2 128GB',
        'slug' => Str::slug('iPad Pro 11" M2 128GB'),
        'sku' => 'IPADP-11-M2',
        'price' => 3899.00,
        'category_id' => $categoryIds['ipad'],
        'image_url' => 'https://store.storeimages.cdn-apple.com/4982/as-images.apple.com/is/ipad-pro-11-select-wifi-spacegray-202210?wid=940&hei=1112&fmt=png-alpha&.v=1664411207099',
        'is_featured' => true,
    ]
];

foreach ($products as $p) {
    Product::updateOrCreate(
        ['store_id' => $store->id, 'sku' => $p['sku']],
        array_merge($p, [
            'description' => 'Equipo ' . $p['name'] . ' en excelentes condiciones, importado de USA.',
            'stock' => 10,
            'is_active' => true,
        ])
    );
}
echo "Products populated!\n";
