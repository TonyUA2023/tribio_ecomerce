<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Store;
use App\Models\Product;
use App\Models\Category;
use App\Models\GalleryItem;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TflPartsSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Crear el usuario
        $user = User::updateOrCreate(
            ['email' => 'tflparts@tribio.com'],
            [
                'name'     => 'TFL Parts Admin',
                'password' => Hash::make('password'),
                'role'     => 'store_owner',
                'phone'    => '+51902699916',
            ]
        );

        // 2. Crear la tienda
        $store = Store::updateOrCreate(
            ['user_id' => $user->id],
            [
                'name'          => 'TFL Parts',
                'slug'          => 'tflparts',
                'description'   => 'Importación y distribución de repuestos premium para maquinaria agrícola y tractores. Motores, retenes, sistemas hidráulicos y repuestos en general de alta durabilidad para mantener su maquinaria operativa en el campo.',
                'tagline'       => 'El motor del agro no se detiene: repuestos premium garantizados.',
                'category'      => 'otros',
                'template_name' => 'industrial-light',
                'accent_color'  => '#DC2626', // Rojo
                'secondary_color' => '#16A34A', // Verde
                'text_color'    => '#1F2937', // Gris Oscuro
                'bg_color'      => '#FFFFFF', // Blanco
                'logo_path'     => 'logos/tfl_parts_logo.png',
                'whatsapp_phone'=> '51902699916',
                'status'        => 'active',
                'plan'          => 'enterprise',
                'plan_expires_at' => now()->addYears(2),
                'is_featured'   => true,
                'city'          => 'Chiclayo',
                'country'       => 'PE',
                'address'       => 'Av. Agricultura 450, Chiclayo',
                'facebook_url'  => 'https://facebook.com/tflparts',
                'instagram_url' => 'https://instagram.com/tflparts',
                'distributors'  => [
                    [
                        'region' => 'AMÉRICA DEL SUR',
                        'locations' => [
                            'Perú (Chiclayo - Oficina Central B2B)',
                            'Brasil (São Paulo - Distribución Mercosur)',
                            'Colombia (Cali - Almacén y Logística)',
                        ]
                    ],
                    [
                        'region' => 'AMÉRICA DEL NORTE',
                        'locations' => [
                            'EE.UU. (Miami, FL - Importadora B2B)',
                            'México (Querétaro - Almacén Central Bajío)',
                        ]
                    ],
                    [
                        'region' => 'EUROPA Y ASIA',
                        'locations' => [
                            'Alemania (Stuttgart - Centro de Despacho)',
                            'Turquía (Estambul - Centro Manufacturero)',
                        ]
                    ]
                ],
            ]
        );

        // 3. Crear Categorías
        $categoriesData = [
            [
                'name' => 'Repuestos de Tractores',
                'slug' => 'repuestos-de-tractores',
                'icon' => '🚜',
                'color' => '#DC2626',
                'description' => 'Componentes de motor, embragues, frenos y transmisión para tractores John Deere, Massey Ferguson, Case IH, etc.'
            ],
            [
                'name' => 'Motores y Partes',
                'slug' => 'motores-y-partes',
                'icon' => '⚙️',
                'color' => '#16A34A',
                'description' => 'Pistones, camisas, empaquetaduras, cigüeñales e inyectores para motores diésel de maquinaria pesada.'
            ],
            [
                'name' => 'Retenes y Sellos',
                'slug' => 'retenes-y-sellos',
                'icon' => '⭕',
                'color' => '#2563EB',
                'description' => 'Retenes de aceite, o-rings, sellos hidráulicos de vitón y nitrilo de alta resistencia térmica.'
            ],
            [
                'name' => 'Sistemas Hidráulicos',
                'slug' => 'sistemas-hidraulicos',
                'icon' => '🧪',
                'color' => '#D97706',
                'description' => 'Bombas hidráulicas, mangueras R2 de alta presión, distribuidores y cilindros.'
            ],
        ];

        $categories = [];
        foreach ($categoriesData as $i => $catData) {
            $categories[$catData['slug']] = Category::updateOrCreate(
                [
                    'store_id' => $store->id,
                    'slug' => $catData['slug']
                ],
                [
                    'name' => $catData['name'],
                    'icon' => $catData['icon'],
                    'color' => $catData['color'],
                    'sort_order' => $i
                ]
            );
        }

        // 4. Crear Productos
        $productsData = [
            // Repuestos de Tractores
            [
                'category' => 'repuestos-de-tractores',
                'name' => 'Bomba de Agua John Deere RE546906',
                'description' => 'Bomba de agua de repuesto premium para tractores John Deere de la serie 5000 y 6000. Diseñada para un flujo óptimo de refrigerante y alta disipación térmica.',
                'short_description' => 'Bomba de agua premium para John Deere serie 5000/6000.',
                'sku' => 'JD-WAP-906',
                'price' => 450.00,
                'compare_price' => 520.00,
                'stock' => 12,
                'is_featured' => true,
            ],
            [
                'category' => 'repuestos-de-tractores',
                'name' => 'Disco de Embrague 12" Massey Ferguson 3280145M91',
                'description' => 'Disco de embrague orgánico de 12 pulgadas (300mm) y 10 estrías para Massey Ferguson MF 265, MF 275 y MF 290. Excelente fricción y acoplamiento suave.',
                'short_description' => 'Disco embrague 12" para Massey Ferguson MF 265/275/290.',
                'sku' => 'MF-CLD-145',
                'price' => 880.00,
                'compare_price' => 950.00,
                'stock' => 5,
                'is_featured' => true,
            ],
            [
                'category' => 'repuestos-de-tractores',
                'name' => 'Filtro de Aire Primario Donaldson P777868',
                'description' => 'Filtro de aire primario de alto rendimiento Donaldson para tractores Case IH y New Holland. Protege el motor contra polvo y partículas finas del campo.',
                'short_description' => 'Filtro de aire primario Donaldson para Case IH y New Holland.',
                'sku' => 'DN-FLP-868',
                'price' => 175.00,
                'compare_price' => null,
                'stock' => 24,
                'is_featured' => false,
            ],

            // Motores y Partes
            [
                'category' => 'motores-y-partes',
                'name' => 'Pistón Completo Motor Perkins 4.236 U5LL0014',
                'description' => 'Pistón completo original OEM para motor diésel Perkins 4.236. Incluye pasador, anillos y seguros. Fabricado en aleación de aluminio de alta resistencia.',
                'short_description' => 'Pistón completo OEM para Perkins 4.236 (pasador, anillos y seguros).',
                'sku' => 'PK-PST-014',
                'price' => 320.00,
                'compare_price' => 380.00,
                'stock' => 16,
                'is_featured' => true,
            ],
            [
                'category' => 'motores-y-partes',
                'name' => 'Juego de Empaquetaduras Superior Cummins 6BT 5.9',
                'description' => 'Kit completo de empaques superiores para motor Cummins 6BT de 5.9L de 12 válvulas. Incluye empaque de culata de grafito reforzado, retenes de válvula y empaque de múltiple.',
                'short_description' => 'Kit de empaquetaduras de culata y accesorios para Cummins 6BT.',
                'sku' => 'CM-GKT-6BT',
                'price' => 690.00,
                'compare_price' => 780.00,
                'stock' => 8,
                'is_featured' => false,
            ],
            [
                'category' => 'motores-y-partes',
                'name' => 'Inyector de Combustible John Deere SE501101',
                'description' => 'Inyector diésel reacondicionado y calibrado de fábrica John Deere para motores PowerTech de 4.5L y 6.8L. Pulverización perfecta y ahorro de combustible.',
                'short_description' => 'Inyector diésel original John Deere calibrado.',
                'sku' => 'JD-INJ-101',
                'price' => 540.00,
                'compare_price' => null,
                'stock' => 18,
                'is_featured' => true,
            ],

            // Retenes y Sellos
            [
                'category' => 'retenes-y-sellos',
                'name' => 'Retén Cigüeñal Trasero Massey Ferguson 1448135M1',
                'description' => 'Retén de aceite de labio vitón de alta temperatura para el cigüeñal trasero de tractores Massey Ferguson. Resiste la abrasión y evita fugas en el motor.',
                'short_description' => 'Retén cigüeñal trasero Massey Ferguson en vitón de alta duración.',
                'sku' => 'MF-RET-135',
                'price' => 110.00,
                'compare_price' => 140.00,
                'stock' => 30,
                'is_featured' => false,
            ],
            [
                'category' => 'retenes-y-sellos',
                'name' => 'Caja Organizadora de O-Rings Milímetros (382 piezas)',
                'description' => 'Juego completo de o-rings nitrilo NBR 70 en diferentes medidas milimétricas (de 3mm a 50mm). Indispensable para sellado de bombas hidráulicas y uniones mecánicas.',
                'short_description' => 'Kit maletín de O-Rings milimétricos en nitrilo (382 piezas).',
                'sku' => 'OR-KIT-MET',
                'price' => 160.00,
                'compare_price' => 190.00,
                'stock' => 40,
                'is_featured' => true,
            ],

            // Sistemas Hidráulicos
            [
                'category' => 'sistemas-hidraulicos',
                'name' => 'Bomba Hidráulica Massey Ferguson Dual 1684343M92',
                'description' => 'Bomba hidráulica dual de engranajes para Massey Ferguson MF 290, MF 292 y MF 298. Genera una presión de hasta 180 bar para operar brazos hidráulicos e implementos.',
                'short_description' => 'Bomba hidráulica doble para Massey Ferguson MF 290.',
                'sku' => 'MF-HYD-343',
                'price' => 1290.00,
                'compare_price' => 1450.00,
                'stock' => 4,
                'is_featured' => true,
            ],
            [
                'category' => 'sistemas-hidraulicos',
                'name' => 'Manguera Hidráulica 1/2" SAE 100 R2AT (por metro)',
                'description' => 'Manguera hidráulica reforzada con doble malla de alambre de alta resistencia (R2) de 1/2 pulgada de diámetro. Soporta presiones de trabajo de hasta 3500 PSI.',
                'short_description' => 'Manguera hidráulica 1/2" reforzada doble malla R2 (por metro).',
                'sku' => 'MG-HYD-R212',
                'price' => 48.00,
                'compare_price' => 55.00,
                'stock' => 150,
                'is_featured' => false,
            ]
        ];

        foreach ($productsData as $i => $prodData) {
            $cat = $categories[$prodData['category']] ?? null;
            Product::updateOrCreate(
                [
                    'store_id' => $store->id,
                    'slug' => Str::slug($prodData['name']),
                ],
                [
                    'category_id' => $cat ? $cat->id : null,
                    'name' => $prodData['name'],
                    'description' => $prodData['description'],
                    'short_description' => $prodData['short_description'],
                    'sku' => $prodData['sku'],
                    'price' => $prodData['price'],
                    'compare_price' => $prodData['compare_price'],
                    'stock' => $prodData['stock'],
                    'track_stock' => true,
                    'is_active' => true,
                    'is_featured' => $prodData['is_featured'],
                    'sort_order' => $i,
                ]
            );
        }

        // 5. Crear items de galería demo
        $galleryData = [
            ['title' => 'Almacén de Repuestos TFL Parts', 'description' => 'Stock permanente de las principales marcas agrícolas.'],
            ['title' => 'Entrega de Repuestos a Campo', 'description' => 'Asistencia y despacho directo a su zona agrícola.'],
            ['title' => 'Garantía de Motores Perkins', 'description' => 'Motores diésel listos para reparación y ensamblaje.'],
            ['title' => 'Retenes y Sellado de Precisión', 'description' => 'Gran catálogo de retenes agrícolas importados.'],
        ];

        foreach ($galleryData as $i => $item) {
            GalleryItem::updateOrCreate(
                [
                    'store_id' => $store->id,
                    'title' => $item['title'],
                ],
                [
                    'image_path' => 'logos/tfl_parts_logo.png',
                    'description' => $item['description'],
                    'is_active' => true,
                    'sort_order' => $i,
                ]
            );
        }
    }
}
