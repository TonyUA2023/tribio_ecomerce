<?php

return [
    'support' => [
        'whatsapp_display' => '+51 938 808 435',
        'whatsapp_number' => '51938808435',
    ],
    'legal' => [
        'operator_name' => 'Tony Ulloa Alvinagorta',
        'operator_ruc' => '10710764153',
        'operator_location' => 'Huancayo, Junín, Perú',
        'contact_email' => 'jstackinfo@gmail.com',
        'effective_date' => '22 de septiembre de 2026',
    ],

    // Las plantillas de tienda viven en config/storefront.php (módulo "Plantillas").

    /*
    |--------------------------------------------------------------------------
    | Categorías de negocios disponibles al registrarse
    |--------------------------------------------------------------------------
    */
    'business_categories' => [
        'moda'        => ['label' => 'Moda y Ropa',         'icon' => '👗'],
        'calzado'     => ['label' => 'Calzado',              'icon' => '👟'],
        'tecnologia'  => ['label' => 'Tecnología',           'icon' => '💻'],
        'alimentos'   => ['label' => 'Alimentos y Bebidas',  'icon' => '🍕'],
        'joyeria'     => ['label' => 'Joyería y Accesorios', 'icon' => '💍'],
        'hogar'       => ['label' => 'Hogar y Decoración',   'icon' => '🏡'],
        'deporte'     => ['label' => 'Deporte y Fitness',    'icon' => '⚽'],
        'salud'       => ['label' => 'Salud y Belleza',      'icon' => '💄'],
        'servicios'   => ['label' => 'Servicios',            'icon' => '🔧'],
        'otros'       => ['label' => 'Otros',                'icon' => '🛍️'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Planes de suscripción
    |--------------------------------------------------------------------------
    */
    'plans' => [
        'basic' => [
            'label'        => 'Emprendedor',
            'price'        => 19.90,
            'max_products' => 100,
            'max_gallery'  => 50,
            'features'     => [
                'Hasta 100 productos',
                '1 usuario / acceso',
                'Checkout híbrido (Web + WhatsApp)',
                'Catálogo interactivo (PDF / QR)',
                'Control básico de ingresos/egresos',
                'Soporte para billeteras (Yape/Plin)',
                'Sin facturación electrónica SUNAT',
            ],
            'highlight' => false,
        ],
        'professional' => [
            'label'        => 'Negocio / Pro',
            'price'        => 100.00,
            'max_products' => 1000,
            'max_gallery'  => 200,
            'features'     => [
                'Todo lo del Plan Emprendedor',
                'Facturación Electrónica (SUNAT)',
                'Asistente AI Copilot para productos',
                'Multi-usuario (hasta 5 accesos)',
                'Tarifas de envío dinámicas',
                'Módulo de inventario avanzado',
            ],
            'highlight' => true,
        ],
        'enterprise' => [
            'label'        => 'Corporativo / B2B',
            'price'        => 399.00,
            'max_products' => 9999,
            'max_gallery'  => 9999,
            'features'     => [
                'Todo lo anterior ilimitado',
                'Soporte Multimoneda & Multilenguaje',
                'Integración avanzada APIs Courier',
                'CRM re-marketing masivo WhatsApp',
                'Soporte corporativo prioritario',
            ],
            'highlight' => false,
        ],
    ],
];
