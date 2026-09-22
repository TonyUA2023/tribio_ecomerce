<?php

/*
|--------------------------------------------------------------------------
| Catálogo de plantillas de tienda (módulo "Plantillas" del dashboard)
|--------------------------------------------------------------------------
|
| Cada plantilla es código: una carpeta resources/views/templates/{key}/ con las
| vistas de REQUIRED_VIEWS (ver App\Services\Storefront\TemplateRegistry) y una
| entrada aquí. Para publicar una nueva:
|   1. Crea las vistas (copiar soft-market es el camino más corto).
|   2. Regístrala aquí con status 'development' y revísala en la vista previa.
|   3. Cámbiala a 'available'. El registro se niega a ofrecerla si falta alguna
|      vista, así que un olvido nunca llega a romper la tienda de un cliente.
|
| status:
|   available   → se lista, se previsualiza y se puede aplicar.
|   development → se lista en "Próximamente"; no se puede previsualizar ni aplicar.
|   private     → no se lista. Solo la siguen usando las tiendas que ya la tenían
|                 (construcciones a medida congeladas, p. ej. minimal-light = Maetek).
|
| settings: grupos de campos que el dueño puede editar. Cada campo se guarda en
| stores.template_settings[{key}][...ruta con puntos...] salvo que declare
| 'column', en cuyo caso se guarda en esa columna de stores.
|   type:     color | select | font | text | textarea | toggle | emoji
|   default:  valor, o ['es' => ..., 'en' => ...] para textos bilingües
|   fallback: columna de stores que se usa si el dueño aún no personalizó el campo
|   logo:     'primary' | 'secondary' — el botón "Colores de mi logo" lo rellena
|
*/

$fontPresets = [
    'fredoka'  => 'Amigable · Fredoka',
    'jakarta'  => 'Moderna · Plus Jakarta Sans',
    'playfair' => 'Elegante · Playfair Display',
    'grotesk'  => 'Tecnológica · Space Grotesk',
];

return [

    // Fuentes que una plantilla puede ofrecer en un campo type=font. La lista blanca
    // también protege el <link> que se inyecta en la tienda: nunca se usa texto libre.
    'fonts' => [
        'fredoka'  => ['family' => "'Fredoka', 'Quicksand', sans-serif", 'google' => 'Fredoka:wght@400;500;600;700'],
        'jakarta'  => ['family' => "'Plus Jakarta Sans', sans-serif", 'google' => 'Plus+Jakarta+Sans:wght@400;500;600;700;800'],
        'playfair' => ['family' => "'Playfair Display', Georgia, serif", 'google' => 'Playfair+Display:wght@500;600;700;800'],
        'grotesk'  => ['family' => "'Space Grotesk', sans-serif", 'google' => 'Space+Grotesk:wght@400;500;600;700'],
    ],

    // Plantilla que recibe cada tienda nueva (SubscriptionController).
    'default_template' => 'soft-market',

    'templates' => [

        'soft-market' => [
            'name'        => 'Soft Market',
            'tagline'     => 'Cálida, redondeada y cercana',
            'description' => 'Tipografía amable, tarjetas suaves y un hero a pantalla completa con carrusel. Pensada para marcas de hogar, lifestyle, regalos y accesorios que quieren transmitir confianza.',
            'ideal_for'   => ['Hogar', 'Lifestyle', 'Regalos', 'Accesorios'],
            'tags'        => ['claro', 'amigable', 'carrusel', 'videos'],
            'status'      => 'available',
            'thumbnail'   => null,
            'swatches'    => ['#7DA268', '#D4B48A', '#FAF7F2', '#1E1D1B'],
            'default_accent'    => '#7DA268',
            'default_secondary' => '#D4B48A',
            'default_bg'        => '#FAF7F2',
            'settings' => [
                [
                    'key' => 'brand', 'title' => 'Colores y tipografía', 'icon' => 'palette',
                    'description' => 'Toda la paleta de la tienda se genera a partir de estos colores.',
                    'fields' => [
                        'colors.primary' => ['type' => 'color', 'label' => 'Color principal', 'default' => '#7DA268', 'logo' => 'primary',
                            'help' => 'Botones, precios, enlaces y detalles activos.'],
                        'colors.secondary' => ['type' => 'color', 'label' => 'Color complementario', 'default' => '#D4B48A', 'logo' => 'secondary',
                            'help' => 'Tonos cálidos de apoyo: fondos de íconos y tarjetas.'],
                        'colors.background' => ['type' => 'select', 'label' => 'Fondo de la tienda', 'default' => 'cream',
                            'options' => ['cream' => 'Crema', 'white' => 'Blanco puro', 'mist' => 'Niebla', 'tint' => 'Tinte de tu color']],
                        'typography.heading' => ['type' => 'font', 'label' => 'Estilo de títulos', 'default' => 'fredoka', 'options' => $fontPresets],
                    ],
                ],
                [
                    'key' => 'announcement', 'title' => 'Barra de anuncios', 'icon' => 'megaphone',
                    'description' => 'La franja delgada sobre el menú.',
                    'fields' => [
                        'announcement.enabled' => ['type' => 'toggle', 'label' => 'Mostrar barra de anuncios', 'default' => true],
                        'announcement.text' => ['type' => 'text', 'label' => 'Mensaje', 'max' => 90,
                            'default' => ['es' => 'Envíos a todo el país • Compra 100% protegida', 'en' => 'Nationwide shipping • 100% protected checkout']],
                    ],
                ],
                [
                    'key' => 'hero', 'title' => 'Portada (hero)', 'icon' => 'image',
                    'description' => 'Lo primero que ven tus clientes. Las fotos del carrusel se eligen en Galería (marca fotos como "Hero").',
                    'fields' => [
                        'hero.badge' => ['type' => 'text', 'label' => 'Etiqueta superior', 'max' => 40, 'fallback' => 'hero_badge',
                            'default' => ['es' => 'Productos exclusivos', 'en' => 'Premium quality']],
                        'hero.show_store_name' => ['type' => 'toggle', 'label' => 'Mostrar el nombre de la tienda en grande', 'default' => true],
                        'hero.title' => ['type' => 'text', 'label' => 'Título principal', 'max' => 90, 'fallback' => 'hero_title',
                            'default' => ['es' => 'Descubre productos pensados para ti', 'en' => 'Discover products made for you']],
                        'hero.subtitle' => ['type' => 'textarea', 'label' => 'Texto de apoyo', 'max' => 180, 'fallback' => 'hero_subtitle',
                            'default' => ''],
                        'hero.cta' => ['type' => 'text', 'label' => 'Texto del botón', 'max' => 24,
                            'default' => ['es' => 'Comprar ahora', 'en' => 'Shop now']],
                    ],
                ],
                [
                    'key' => 'pillars', 'title' => 'Pilares de tu marca', 'icon' => 'sparkles',
                    'description' => 'Tres tarjetas debajo de la portada con lo que te hace diferente.',
                    'fields' => [
                        'pillars.enabled' => ['type' => 'toggle', 'label' => 'Mostrar pilares', 'default' => true],
                        'pillars.items.0.icon' => ['type' => 'emoji', 'label' => 'Ícono 1', 'default' => '🚚'],
                        'pillars.items.0.title' => ['type' => 'text', 'label' => 'Título 1', 'max' => 40,
                            'default' => ['es' => 'Envíos rápidos', 'en' => 'Fast shipping']],
                        'pillars.items.0.text' => ['type' => 'text', 'label' => 'Descripción 1', 'max' => 80,
                            'default' => ['es' => 'Recibe tu pedido en pocos días', 'en' => 'Get your order in just a few days']],
                        'pillars.items.1.icon' => ['type' => 'emoji', 'label' => 'Ícono 2', 'default' => '🌿'],
                        'pillars.items.1.title' => ['type' => 'text', 'label' => 'Título 2', 'max' => 40,
                            'default' => ['es' => 'Calidad garantizada', 'en' => 'Guaranteed quality']],
                        'pillars.items.1.text' => ['type' => 'text', 'label' => 'Descripción 2', 'max' => 80,
                            'default' => ['es' => 'Productos elegidos con cuidado', 'en' => 'Products picked with care']],
                        'pillars.items.2.icon' => ['type' => 'emoji', 'label' => 'Ícono 3', 'default' => '💬'],
                        'pillars.items.2.title' => ['type' => 'text', 'label' => 'Título 3', 'max' => 40,
                            'default' => ['es' => 'Atención cercana', 'en' => 'Friendly support']],
                        'pillars.items.2.text' => ['type' => 'text', 'label' => 'Descripción 3', 'max' => 80,
                            'default' => ['es' => 'Te ayudamos antes y después de comprar', 'en' => 'We help you before and after you buy']],
                        'pillars.note' => ['type' => 'text', 'label' => 'Nota pequeña bajo los pilares (opcional)', 'max' => 120, 'default' => ''],
                    ],
                ],
                [
                    'key' => 'sections', 'title' => 'Títulos de secciones', 'icon' => 'heading',
                    'description' => 'Los encabezados de cada bloque de la página de inicio.',
                    'fields' => [
                        'sections.trending_title' => ['type' => 'text', 'label' => 'Productos con video · título', 'max' => 60,
                            'default' => ['es' => 'Productos en tendencia', 'en' => 'Trending products']],
                        'sections.trending_subtitle' => ['type' => 'text', 'label' => 'Productos con video · subtítulo', 'max' => 120,
                            'default' => ['es' => 'Detalles y acabados reales de nuestros productos destacados.', 'en' => 'Real details and finishes of our featured products.']],
                        'sections.quicklinks_enabled' => ['type' => 'toggle', 'label' => 'Mostrar accesos rápidos por precio', 'default' => true],
                        'sections.categories_title' => ['type' => 'text', 'label' => 'Categorías · título', 'max' => 60,
                            'default' => ['es' => 'Categorías destacadas', 'en' => 'Featured categories']],
                        'sections.products_title' => ['type' => 'text', 'label' => 'Productos · título', 'max' => 60,
                            'default' => ['es' => 'Lo más vendido', 'en' => 'Best sellers']],
                    ],
                ],
                [
                    'key' => 'benefits', 'title' => 'Beneficios', 'icon' => 'shield',
                    'description' => 'Las tres garantías antes del pie de página.',
                    'fields' => [
                        'benefits.enabled' => ['type' => 'toggle', 'label' => 'Mostrar beneficios', 'default' => true],
                        'benefits.items.0.title' => ['type' => 'text', 'label' => 'Beneficio 1', 'max' => 40,
                            'default' => ['es' => 'Envíos a nivel nacional', 'en' => 'Nationwide shipping']],
                        'benefits.items.0.text' => ['type' => 'text', 'label' => 'Detalle 1', 'max' => 80,
                            'default' => ['es' => 'Entregas rápidas y seguras a todo el país', 'en' => 'Fast, safe delivery across the country']],
                        'benefits.items.1.title' => ['type' => 'text', 'label' => 'Beneficio 2', 'max' => 40,
                            'default' => ['es' => 'Múltiples formas de pago', 'en' => 'Multiple payment methods']],
                        'benefits.items.1.text' => ['type' => 'text', 'label' => 'Detalle 2', 'max' => 80,
                            'default' => ['es' => 'Tarjetas, transferencias y pagos locales', 'en' => 'Cards, transfers and local wallets']],
                        'benefits.items.2.title' => ['type' => 'text', 'label' => 'Beneficio 3', 'max' => 40,
                            'default' => ['es' => 'Pagos 100% seguros', 'en' => '100% secure payments']],
                        'benefits.items.2.text' => ['type' => 'text', 'label' => 'Detalle 3', 'max' => 80,
                            'default' => ['es' => 'Tus datos protegidos y encriptados', 'en' => 'Your data, protected and encrypted']],
                    ],
                ],
                [
                    'key' => 'footer', 'title' => 'Pie de página', 'icon' => 'layout',
                    'description' => 'El cierre de cada página de tu tienda.',
                    'fields' => [
                        'footer.newsletter_title' => ['type' => 'text', 'label' => 'Título del bloque de suscripción', 'max' => 60,
                            'default' => ['es' => 'Únete a nuestra comunidad', 'en' => 'Join our community']],
                        'footer.about' => ['type' => 'textarea', 'label' => 'Sobre tu tienda', 'max' => 240, 'fallback' => 'description',
                            'default' => ['es' => 'Productos de calidad seleccionados para hacer tu día a día más fácil.', 'en' => 'Quality products picked to make your everyday life easier.']],
                    ],
                ],
            ],
        ],

        // Construcción a medida de Maetek, congelada tal cual. No se ofrece a nadie más
        // (su gemela configurable es soft-market) y su tienda está bloqueada en el módulo.
        'minimal-light' => [
            'name'        => 'Minimal Light',
            'tagline'     => 'Diseño exclusivo',
            'description' => 'Construcción a medida protegida. Su versión configurable es Soft Market.',
            'ideal_for'   => [],
            'tags'        => ['a medida'],
            'status'      => 'private',
            'thumbnail'   => null,
            'swatches'    => ['#7DA268', '#EFE3D0', '#FAF7F2', '#1E1D1B'],
            'default_accent'    => '#1F2937',
            'default_secondary' => '#D97706',
            'default_bg'        => '#FFFFFF',
            'settings' => [],
        ],

        'industrial-light' => [
            'name'        => 'Industrial Light',
            'tagline'     => 'Robusta y técnica',
            'description' => 'Diseño industrial con fondo claro y detalles en rojo y verde. Ideal para repuestos, maquinaria, herramientas y talleres.',
            'ideal_for'   => ['Repuestos', 'Maquinaria', 'Ferretería'],
            'tags'        => ['industrial', 'claro', 'catálogo técnico'],
            'status'      => 'development',
            'status_note' => 'Se está adaptando desde una tienda a medida.',
            'thumbnail'   => null,
            'swatches'    => ['#DC2626', '#16A34A', '#FFFFFF', '#1F2937'],
            'default_accent'    => '#DC2626',
            'default_secondary' => '#16A34A',
            'default_bg'        => '#FFFFFF',
            'settings' => [],
        ],

        'elegant-refurbished' => [
            'name'        => 'Elegant Refurbished',
            'tagline'     => 'Minimalismo estilo Apple',
            'description' => 'Elegante y aireada, con tipografía de sistema. Especializada en tecnología premium y equipos reacondicionados.',
            'ideal_for'   => ['Tecnología', 'Celulares', 'Laptops'],
            'tags'        => ['apple-style', 'claro', 'tecnología'],
            'status'      => 'development',
            'status_note' => 'Se está adaptando desde una tienda a medida.',
            'thumbnail'   => null,
            'swatches'    => ['#0071E3', '#86868B', '#FFFFFF', '#1D1D1F'],
            'default_accent'    => '#0071e3',
            'default_secondary' => '#86868b',
            'default_bg'        => '#FFFFFF',
            'settings' => [],
        ],

        'elegant-dark' => [
            'name'        => 'Elegant Dark',
            'tagline'     => 'Oscura y premium',
            'description' => 'Diseño oscuro con glassmorphism. Ideal para tecnología, moda y productos de lujo.',
            'ideal_for'   => ['Moda', 'Lujo', 'Tecnología'],
            'tags'        => ['premium', 'oscuro', 'moderno'],
            'status'      => 'development',
            'status_note' => 'En diseño.',
            'thumbnail'   => null,
            'swatches'    => ['#8B5CF6', '#EC4899', '#0F0F1A', '#FFFFFF'],
            'default_accent'    => '#8B5CF6',
            'default_secondary' => '#EC4899',
            'default_bg'        => '#0F0F1A',
            'settings' => [],
        ],

        'vibrant-fresh' => [
            'name'        => 'Vibrant Fresh',
            'tagline'     => 'Colorida y juvenil',
            'description' => 'Colores alegres y bordes redondeados. Ideal para alimentos, repostería, juguetes y artesanías.',
            'ideal_for'   => ['Alimentos', 'Repostería', 'Juguetes'],
            'tags'        => ['colorido', 'alegre', 'juvenil'],
            'status'      => 'development',
            'status_note' => 'En diseño.',
            'thumbnail'   => null,
            'swatches'    => ['#EC4899', '#F59E0B', '#FFF7F0', '#1F2937'],
            'default_accent'    => '#EC4899',
            'default_secondary' => '#F59E0B',
            'default_bg'        => '#FFF7F0',
            'settings' => [],
        ],
    ],
];
