<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Plantillas disponibles para las tiendas
    |--------------------------------------------------------------------------
    */
    'templates' => [
        'elegant-dark' => [
            'name'        => 'Elegant Dark',
            'description' => 'Diseño oscuro premium con glassmorphism. Ideal para tecnología, moda y productos de lujo.',
            'preview'     => 'images/templates/elegant-dark-preview.jpg',
            'hero_styles' => ['full', 'split', 'minimal'],
            'supports_carousel' => true,
            'default_accent'    => '#8B5CF6',
            'default_secondary' => '#EC4899',
            'default_bg'        => '#0F0F1A',
            'tags'              => ['premium', 'oscuro', 'moderno'],
        ],
        'minimal-light' => [
            'name'        => 'Minimal Light',
            'description' => 'Estética limpia y minimalista con fondo blanco. Perfecta para joyería, boutiques y productos artesanales.',
            'preview'     => 'images/templates/minimal-light-preview.jpg',
            'hero_styles' => ['split', 'minimal'],
            'supports_carousel' => false,
            'default_accent'    => '#1F2937',
            'default_secondary' => '#D97706',
            'default_bg'        => '#FFFFFF',
            'tags'              => ['minimalista', 'claro', 'elegante'],
        ],
        'vibrant-fresh' => [
            'name'        => 'Vibrant Fresh',
            'description' => 'Colores alegres y bordes redondeados. Ideal para alimentos, repostería, juguetes y artesanías.',
            'preview'     => 'images/templates/vibrant-fresh-preview.jpg',
            'hero_styles' => ['full', 'split'],
            'supports_carousel' => true,
            'default_accent'    => '#EC4899',
            'default_secondary' => '#F59E0B',
            'default_bg'        => '#FFF7F0',
            'tags'              => ['colorido', 'alegre', 'juvenil'],
        ],
        'industrial-light' => [
            'name'        => 'Industrial Light',
            'description' => 'Diseño industrial premium con fondo claro y detalles en rojo y verde. Ideal para repuestos, maquinaria, herramientas y talleres.',
            'preview'     => 'images/templates/industrial-light-preview.jpg',
            'hero_styles' => ['full', 'split'],
            'supports_carousel' => true,
            'default_accent'    => '#DC2626',
            'default_secondary' => '#16A34A',
            'default_bg'        => '#FFFFFF',
            'tags'              => ['industrial', 'claro', 'repuestos'],
        ],
        'elegant-refurbished' => [
            'name'        => 'Elegant Refurbished',
            'description' => 'Estilo Apple, elegante y minimalista. Especializado en productos premium reacondicionados (celulares, laptops) y tecnología importada.',
            'preview'     => 'images/templates/elegant-refurbished-preview.jpg',
            'hero_styles' => ['split', 'minimal'],
            'supports_carousel' => true,
            'default_accent'    => '#0071e3',
            'default_secondary' => '#86868b',
            'default_bg'        => '#FFFFFF',
            'tags'              => ['apple-style', 'claro', 'tecnología'],
        ],
    ],

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
            'price'        => 59.00,
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
            'price'        => 149.00,
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
