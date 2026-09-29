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
    | Rubros de negocio (stores.category)
    |--------------------------------------------------------------------------
    | Única lista de rubros: registro, "Mi Tienda", directorio /negocios y admin la
    | leen de aquí (App\Support\BusinessProfile). Las claves ya guardadas en la base
    | no se cambian nunca; solo las etiquetas.
    |
    | Un rubro puede además ajustar la configuración de sus tiendas:
    |   made_to_order    → tiendas nuevas empiezan con "Ventas por encargo" activo
    |                      y el dashboard lo recomienda.
    |   deposit_percent  → adelanto inicial de esas tiendas nuevas.
    |   sizes            → tallas sugeridas (tabla de tallas por encargo).
    |   variant_options  → opciones sugeridas al crear variantes de un producto.
    |   templates        → plantillas recomendadas en el módulo "Plantillas".
    | Lo que un rubro no define usa 'business_profile_defaults'.
    */
    'business_categories' => [
        'textileria'  => ['label' => 'Textilería y confección', 'icon' => '🧵',
            'made_to_order'   => true,
            'deposit_percent' => 50,
            'sizes'           => ['S', 'M', 'L', 'XL', 'XXL'],
            'variant_options' => ['Talla' => ['S', 'M', 'L', 'XL'], 'Color' => ['Blanco', 'Negro']],
            'templates'       => ['textil-pro', 'soft-market', 'urban-style'],
        ],
        'moda'        => ['label' => 'Tienda de ropa',       'icon' => '👗',
            'sizes'           => ['XS', 'S', 'M', 'L', 'XL'],
            'variant_options' => ['Talla' => ['S', 'M', 'L', 'XL'], 'Color' => ['Negro', 'Blanco']],
            'templates'       => ['urban-style'],
        ],
        'calzado'     => ['label' => 'Tienda de zapatillas', 'icon' => '👟',
            'sizes'           => ['36', '37', '38', '39', '40', '41', '42', '43', '44'],
            'variant_options' => ['Talla' => ['38', '39', '40', '41', '42', '43'], 'Color' => ['Negro', 'Blanco']],
            'templates'       => ['sport-pro'],
        ],
        'tecnologia'  => ['label' => 'Tecnología',           'icon' => '💻'],
        'alimentos'   => ['label' => 'Alimentos y Bebidas',  'icon' => '🍕'],
        'joyeria'     => ['label' => 'Joyería y Accesorios', 'icon' => '💍'],
        'hogar'       => ['label' => 'Hogar y Decoración',   'icon' => '🏡'],
        'deporte'     => ['label' => 'Deporte y Fitness',    'icon' => '⚽'],
        'salud'       => ['label' => 'Salud y Belleza',      'icon' => '💄'],
        'servicios'   => ['label' => 'Servicios',            'icon' => '🔧'],
        'otros'       => ['label' => 'Otros',                'icon' => '🛍️'],
    ],

    'business_profile_defaults' => [
        'made_to_order'   => false,
        'deposit_percent' => null,
        'sizes'           => ['S', 'M', 'L', 'XL'],
        'variant_options' => ['Color' => ['Negro', 'Blanco'], 'Talla' => ['S', 'M', 'L']],
        'templates'       => [],
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
