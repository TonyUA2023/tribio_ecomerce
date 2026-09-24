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
|             image (archivo subido; se guarda la ruta en el disco public)
|             link  (destino dentro de la tienda: catálogo, novedades, categoría…)
|             date  (AAAA-MM-DD, p. ej. el fin de una promoción con cuenta regresiva)
|   default:  valor, o ['es' => ..., 'en' => ...] para textos bilingües
|   fallback: columna de stores que se usa si el dueño aún no personalizó el campo
|   logo:     'primary' | 'secondary' — el botón "Colores de mi logo" lo rellena
|   optional: true en un texto con valor por defecto que el dueño puede vaciar para
|             ocultarlo (el formulario muestra el valor efectivo en vez del placeholder)
|
| Un grupo puede declarar 'item_label' ('Banner', 'Beneficio'…): los campos
| "{grupo}.items.{n}.*" se muestran juntos como una tarjeta "{item_label} {n+1}".
|
*/

$fontPresets = [
    'fredoka'  => 'Amigable · Fredoka',
    'jakarta'  => 'Moderna · Plus Jakarta Sans',
    'playfair' => 'Elegante · Playfair Display',
    'grotesk'  => 'Tecnológica · Space Grotesk',
];

// Urban Style: un banner del carrusel de portada. Todos comparten los mismos campos;
// solo cambian los textos de ejemplo. Devuelve arrays planos (config:cache los serializa).
$urbanSlide = static function (int $i, array $copy): array {
    $n = $i + 1;
    $t = static fn (string $key) => $copy[$key] ?? '';
    $fields = [];
    if ($i > 0) {
        $fields["hero.items.{$i}.enabled"] = ['type' => 'toggle', 'label' => "Mostrar banner {$n}", 'default' => $copy['enabled']];
    }

    return $fields + [
        "hero.items.{$i}.image" => ['type' => 'image', 'label' => "Imagen para computadora {$n}", 'aspect' => 'wide',
            'help' => 'Horizontal, ideal 1920 × 900 px. JPG, PNG o WEBP de hasta 4 MB.'],
        "hero.items.{$i}.image_mobile" => ['type' => 'image', 'label' => "Imagen para celular {$n}", 'aspect' => 'tall',
            'help' => 'Vertical, ideal 900 × 1200 px. Si no subes una, se usa la de computadora.'],
        "hero.items.{$i}.tone" => ['type' => 'select', 'label' => "Estilo del texto {$n}", 'default' => $copy['tone'],
            'options' => ['light' => 'Texto claro', 'dark' => 'Texto oscuro', 'image' => 'Solo la imagen (ya trae texto)']],
        "hero.items.{$i}.align" => ['type' => 'select', 'label' => "Posición del texto {$n}", 'default' => $copy['align'],
            'options' => ['left' => 'Izquierda', 'center' => 'Centro', 'right' => 'Derecha']],
        "hero.items.{$i}.title" => ['type' => 'text', 'label' => "Título {$n}", 'max' => 40, 'optional' => true, 'default' => $t('title')],
        "hero.items.{$i}.eyebrow" => ['type' => 'text', 'label' => "Texto bajo el título {$n}", 'max' => 40, 'optional' => true, 'default' => $t('eyebrow')],
        "hero.items.{$i}.highlight_prefix" => ['type' => 'text', 'label' => "Antes de la cifra {$n}", 'max' => 16, 'optional' => true, 'default' => $t('prefix'),
            'help' => 'Por ejemplo "Hasta".'],
        "hero.items.{$i}.highlight" => ['type' => 'text', 'label' => "Cifra grande {$n}", 'max' => 8, 'optional' => true, 'default' => $t('highlight'),
            'help' => 'Por ejemplo "70%" o "2x1". Déjalo vacío si el banner no es de descuento.'],
        "hero.items.{$i}.highlight_note" => ['type' => 'text', 'label' => "Junto a la cifra {$n}", 'max' => 12, 'optional' => true, 'default' => $t('note')],
        "hero.items.{$i}.subtitle" => ['type' => 'text', 'label' => "Etiqueta destacada {$n}", 'max' => 48, 'optional' => true, 'default' => $t('subtitle')],
        "hero.items.{$i}.legal" => ['type' => 'text', 'label' => "Letra pequeña {$n}", 'max' => 60, 'optional' => true, 'default' => $t('legal')],
        "hero.items.{$i}.cta" => ['type' => 'text', 'label' => "Texto del botón {$n}", 'max' => 24, 'optional' => true, 'default' => $t('cta')],
        "hero.items.{$i}.link" => ['type' => 'link', 'label' => "Al hacer clic lleva a {$n}", 'default' => $copy['link']],
    ];
};

return [

    // Fuentes que una plantilla puede ofrecer en un campo type=font. La lista blanca
    // también protege el <link> que se inyecta en la tienda: nunca se usa texto libre.
    'fonts' => [
        'fredoka'  => ['family' => "'Fredoka', 'Quicksand', sans-serif", 'google' => 'Fredoka:wght@400;500;600;700'],
        'jakarta'  => ['family' => "'Plus Jakarta Sans', sans-serif", 'google' => 'Plus+Jakarta+Sans:wght@400;500;600;700;800'],
        'playfair' => ['family' => "'Playfair Display', Georgia, serif", 'google' => 'Playfair+Display:wght@500;600;700;800'],
        'grotesk'  => ['family' => "'Space Grotesk', sans-serif", 'google' => 'Space+Grotesk:wght@400;500;600;700'],
        'poppins'  => ['family' => "'Poppins', sans-serif", 'google' => 'Poppins:wght@400;500;600;700;800;900'],
        'oswald'   => ['family' => "'Oswald', 'Poppins', sans-serif", 'google' => 'Oswald:wght@500;600;700'],
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

        'urban-style' => [
            'name'        => 'Urban Style',
            'tagline'     => 'Moda con banners de impacto',
            'description' => 'Hecha para tiendas de ropa: banners de campaña a pantalla completa que tú diseñas, barra de cupón con cuenta regresiva, vitrina de productos por categoría y pop-up promocional.',
            'ideal_for'   => ['Ropa', 'Moda', 'Calzado', 'Accesorios'],
            'tags'        => ['moda', 'banners', 'promociones', 'carrusel'],
            'status'      => 'available',
            'thumbnail'   => null,
            'swatches'    => ['#E0157A', '#3B0A2E', '#FFFFFF', '#111111'],
            'default_accent'    => '#E0157A',
            'default_secondary' => '#3B0A2E',
            'default_bg'        => '#FFFFFF',
            'settings' => [
                [
                    'key' => 'brand', 'title' => 'Colores y tipografía', 'icon' => 'palette',
                    'description' => 'El color principal pinta botones, etiquetas de descuento y el degradado de tus banners.',
                    'fields' => [
                        'colors.primary' => ['type' => 'color', 'label' => 'Color principal', 'default' => '#E0157A', 'logo' => 'primary',
                            'help' => 'Botones, etiquetas de descuento, cupones y enlaces activos.'],
                        'colors.secondary' => ['type' => 'color', 'label' => 'Color de contraste', 'default' => '#3B0A2E', 'logo' => 'secondary',
                            'help' => 'El tono oscuro del degradado de los banners y de la barra de cupón.'],
                        'colors.background' => ['type' => 'select', 'label' => 'Fondo de la tienda', 'default' => 'white',
                            'options' => ['white' => 'Blanco puro', 'mist' => 'Niebla', 'cream' => 'Crema', 'tint' => 'Tinte de tu color']],
                        'typography.heading' => ['type' => 'font', 'label' => 'Estilo de títulos', 'default' => 'poppins', 'options' => [
                            'poppins'  => 'Urbana · Poppins',
                            'oswald'   => 'Impacto · Oswald',
                            'grotesk'  => 'Tecnológica · Space Grotesk',
                            'playfair' => 'Elegante · Playfair Display',
                        ]],
                    ],
                ],
                [
                    'key' => 'header', 'title' => 'Menú superior', 'icon' => 'menu',
                    'description' => 'El menú usa tus categorías marcadas "Mostrar en el menú" (o las primeras seis).',
                    'fields' => [
                        'header.style' => ['type' => 'select', 'label' => 'En la página de inicio', 'default' => 'overlay',
                            'options' => ['overlay' => 'Transparente sobre los banners', 'solid' => 'Siempre blanco']],
                        'header.logo_on_hero' => ['type' => 'select', 'label' => 'Tu logo sobre los banners', 'default' => 'white',
                            'options' => ['white' => 'En blanco (ideal para logos oscuros)', 'original' => 'Con sus colores originales'],
                            'help' => 'Al bajar por la página el menú se vuelve blanco y el logo recupera sus colores.'],
                    ],
                ],
                [
                    'key' => 'topbars', 'title' => 'Barras de aviso y cupón', 'icon' => 'megaphone',
                    'description' => 'Las dos franjas sobre el menú: un aviso corto y tu promoción con cuenta regresiva.',
                    'fields' => [
                        'announcement.enabled' => ['type' => 'toggle', 'label' => 'Mostrar la barra de aviso (negra)', 'default' => true],
                        'announcement.text' => ['type' => 'text', 'label' => 'Aviso', 'max' => 90,
                            'default' => ['es' => 'Envío gratis por compras mayores a S/149', 'en' => 'Free shipping on orders over S/149']],
                        'promo.enabled' => ['type' => 'toggle', 'label' => 'Mostrar la barra de cupón (de color)', 'default' => true],
                        'promo.text' => ['type' => 'text', 'label' => 'Mensaje de la promoción', 'max' => 110,
                            'default' => ['es' => 'Usa el cupón BIENVENIDA y obtén 10% de descuento en toda la web', 'en' => 'Use code BIENVENIDA and get 10% off sitewide']],
                        'promo.ends_at' => ['type' => 'date', 'label' => 'La promoción termina el', 'default' => '',
                            'help' => 'Muestra una cuenta regresiva hasta el final de ese día. Déjalo vacío para no mostrarla.'],
                        'promo.link' => ['type' => 'link', 'label' => 'Al hacer clic en la barra lleva a', 'default' => 'catalog'],
                    ],
                ],
                [
                    'key' => 'hero', 'title' => 'Banners de portada', 'icon' => 'image', 'item_label' => 'Banner',
                    'description' => 'El carrusel a pantalla completa. Sube tus fotos y escribe el texto encima, o sube banners que ya traen su propio diseño.',
                    'fields' => [
                        'hero.autoplay' => ['type' => 'toggle', 'label' => 'Cambiar de banner automáticamente', 'default' => true],
                        'hero.height' => ['type' => 'select', 'label' => 'Alto de la portada', 'default' => 'tall',
                            'options' => ['full' => 'Pantalla completa', 'tall' => 'Alta', 'medium' => 'Mediana']],
                    ]
                    + $urbanSlide(0, [
                        'tone' => 'light', 'align' => 'left', 'link' => 'catalog',
                        'title' => ['es' => 'Fashion Days', 'en' => 'Fashion Days'],
                        'eyebrow' => ['es' => 'Exclusivo online', 'en' => 'Online exclusive'],
                        'prefix' => ['es' => 'Hasta', 'en' => 'Up to'],
                        'highlight' => '50%',
                        'note' => ['es' => 'dscto', 'en' => 'off'],
                        'subtitle' => ['es' => 'En cientos de prendas', 'en' => 'On hundreds of styles'],
                        'legal' => ['es' => '*Válido hasta agotar stock', 'en' => '*While supplies last'],
                        'cta' => ['es' => 'Ver promoción', 'en' => 'Shop the sale'],
                    ])
                    + $urbanSlide(1, [
                        'enabled' => true, 'tone' => 'light', 'align' => 'center', 'link' => 'new',
                        'title' => ['es' => 'Nueva colección', 'en' => 'New collection'],
                        'eyebrow' => ['es' => 'Recién llegados', 'en' => 'Just landed'],
                        'subtitle' => ['es' => 'Las tendencias de la temporada', 'en' => 'This season’s trends'],
                        'cta' => ['es' => 'Comprar ahora', 'en' => 'Shop now'],
                    ])
                    + $urbanSlide(2, [
                        'enabled' => false, 'tone' => 'dark', 'align' => 'right', 'link' => 'catalog',
                        'title' => ['es' => 'Básicos que combinan con todo', 'en' => 'Basics that go with everything'],
                        'eyebrow' => ['es' => 'Esenciales', 'en' => 'Essentials'],
                        'cta' => ['es' => 'Ver básicos', 'en' => 'Shop basics'],
                    ]),
                ],
                [
                    'key' => 'categories', 'title' => 'Compra por categoría', 'icon' => 'grid',
                    'description' => 'Accesos a tus categorías con su foto: las destacadas o, si no marcaste ninguna, las primeras.',
                    'fields' => [
                        'categories.enabled' => ['type' => 'toggle', 'label' => 'Mostrar categorías', 'default' => true],
                        'categories.title' => ['type' => 'text', 'label' => 'Título', 'max' => 60, 'optional' => true,
                            'default' => ['es' => 'Compra por categoría', 'en' => 'Shop by category']],
                        'categories.style' => ['type' => 'select', 'label' => 'Forma', 'default' => 'card',
                            'options' => ['card' => 'Tarjetas', 'circle' => 'Círculos']],
                    ],
                ],
                [
                    'key' => 'editorial', 'title' => 'Banners de colección', 'icon' => 'layout', 'item_label' => 'Colección',
                    'description' => 'Dos fotos grandes lado a lado, como una vitrina (por ejemplo "Blusas y pantalones" y "Polos y jeans").',
                    'fields' => [
                        'editorial.enabled' => ['type' => 'toggle', 'label' => 'Mostrar banners de colección', 'default' => true],
                        'editorial.items.0.image' => ['type' => 'image', 'label' => 'Foto 1', 'aspect' => 'portrait',
                            'help' => 'Vertical, ideal 1000 × 1200 px. Sin foto se usa la de una categoría.'],
                        'editorial.items.0.title' => ['type' => 'text', 'label' => 'Título 1', 'max' => 40,
                            'default' => ['es' => 'Nueva temporada', 'en' => 'New season']],
                        'editorial.items.0.cta' => ['type' => 'text', 'label' => 'Texto del enlace 1', 'max' => 24,
                            'default' => ['es' => 'Descubrir', 'en' => 'Discover']],
                        'editorial.items.0.link' => ['type' => 'link', 'label' => 'Lleva a 1', 'default' => 'new'],
                        'editorial.items.1.image' => ['type' => 'image', 'label' => 'Foto 2', 'aspect' => 'portrait',
                            'help' => 'Vertical, ideal 1000 × 1200 px. Sin foto se usa la de una categoría.'],
                        'editorial.items.1.title' => ['type' => 'text', 'label' => 'Título 2', 'max' => 40,
                            'default' => ['es' => 'Esenciales de siempre', 'en' => 'Everyday essentials']],
                        'editorial.items.1.cta' => ['type' => 'text', 'label' => 'Texto del enlace 2', 'max' => 24,
                            'default' => ['es' => 'Ver colección', 'en' => 'Shop the edit']],
                        'editorial.items.1.link' => ['type' => 'link', 'label' => 'Lleva a 2', 'default' => 'catalog'],
                    ],
                ],
                [
                    'key' => 'sections', 'title' => 'Vitrinas de productos', 'icon' => 'heading',
                    'description' => 'El carrusel con pestañas por categoría y la grilla de novedades.',
                    'fields' => [
                        'showcase.enabled' => ['type' => 'toggle', 'label' => 'Mostrar carrusel por categoría', 'default' => true],
                        'showcase.title' => ['type' => 'text', 'label' => 'Carrusel · título', 'max' => 60, 'optional' => true,
                            'default' => ['es' => 'Lo más buscado', 'en' => 'Most wanted']],
                        'showcase.label' => ['type' => 'text', 'label' => 'Carrusel · texto antes de las pestañas', 'max' => 24, 'optional' => true,
                            'default' => ['es' => 'Selecciona:', 'en' => 'Choose:']],
                        'sections.products_title' => ['type' => 'text', 'label' => 'Grilla · título', 'max' => 60,
                            'default' => ['es' => 'Novedades', 'en' => 'New in']],
                    ],
                ],
                [
                    'key' => 'popup', 'title' => 'Pop-up promocional', 'icon' => 'tag',
                    'description' => 'Una ventana con tu cupón que aparece una sola vez por visita, a los pocos segundos.',
                    'fields' => [
                        'popup.enabled' => ['type' => 'toggle', 'label' => 'Mostrar el pop-up', 'default' => false],
                        'popup.image' => ['type' => 'image', 'label' => 'Imagen propia (opcional)', 'aspect' => 'portrait',
                            'help' => 'Si subes una, el pop-up muestra solo tu imagen. Ideal 800 × 1000 px.'],
                        'popup.eyebrow' => ['type' => 'text', 'label' => 'Texto superior', 'max' => 70, 'optional' => true,
                            'default' => ['es' => 'Por compras mayores a S/200 llévate', 'en' => 'On orders over S/200 get']],
                        'popup.amount' => ['type' => 'text', 'label' => 'Beneficio grande', 'max' => 10, 'default' => 'S/20'],
                        'popup.amount_note' => ['type' => 'text', 'label' => 'Debajo del beneficio', 'max' => 24, 'optional' => true,
                            'default' => ['es' => 'de descuento', 'en' => 'off']],
                        'popup.code_label' => ['type' => 'text', 'label' => 'Antes del cupón', 'max' => 30, 'optional' => true,
                            'default' => ['es' => 'Ingresa el cupón:', 'en' => 'Use code:']],
                        'popup.code' => ['type' => 'text', 'label' => 'Código del cupón', 'max' => 24, 'optional' => true, 'default' => 'BIENVENIDA'],
                        'popup.footer' => ['type' => 'text', 'label' => 'Texto inferior', 'max' => 40, 'optional' => true,
                            'default' => ['es' => 'en tu carrito de compras', 'en' => 'at checkout']],
                        'popup.legal' => ['type' => 'text', 'label' => 'Letra pequeña', 'max' => 60, 'optional' => true,
                            'default' => ['es' => '*Aplican términos y condiciones', 'en' => '*Terms and conditions apply']],
                        'popup.link' => ['type' => 'link', 'label' => 'El botón lleva a', 'default' => 'catalog'],
                    ],
                ],
                [
                    'key' => 'benefits', 'title' => 'Beneficios', 'icon' => 'shield', 'item_label' => 'Beneficio',
                    'description' => 'Las tres garantías antes del pie de página.',
                    'fields' => [
                        'benefits.enabled' => ['type' => 'toggle', 'label' => 'Mostrar beneficios', 'default' => true],
                        'benefits.items.0.title' => ['type' => 'text', 'label' => 'Beneficio 1', 'max' => 40,
                            'default' => ['es' => 'Envíos a todo el país', 'en' => 'Nationwide shipping']],
                        'benefits.items.0.text' => ['type' => 'text', 'label' => 'Detalle 1', 'max' => 80,
                            'default' => ['es' => 'Recibe tus prendas en la puerta de tu casa', 'en' => 'Get your clothes delivered to your door']],
                        'benefits.items.1.title' => ['type' => 'text', 'label' => 'Beneficio 2', 'max' => 40,
                            'default' => ['es' => 'Cambios sin complicaciones', 'en' => 'Easy exchanges']],
                        'benefits.items.1.text' => ['type' => 'text', 'label' => 'Detalle 2', 'max' => 80,
                            'default' => ['es' => '¿No te quedó? Te ayudamos a cambiar tu talla', 'en' => 'Wrong size? We help you swap it']],
                        'benefits.items.2.title' => ['type' => 'text', 'label' => 'Beneficio 3', 'max' => 40,
                            'default' => ['es' => 'Pagos 100% seguros', 'en' => '100% secure payments']],
                        'benefits.items.2.text' => ['type' => 'text', 'label' => 'Detalle 3', 'max' => 80,
                            'default' => ['es' => 'Tarjetas, billeteras digitales y más', 'en' => 'Cards, digital wallets and more']],
                    ],
                ],
                [
                    'key' => 'footer', 'title' => 'Pie de página', 'icon' => 'layout',
                    'description' => 'El cierre de cada página de tu tienda.',
                    'fields' => [
                        'footer.newsletter_title' => ['type' => 'text', 'label' => 'Título del bloque de suscripción', 'max' => 60,
                            'default' => ['es' => 'Entérate primero de las novedades', 'en' => 'Be the first to know']],
                        'footer.about' => ['type' => 'textarea', 'label' => 'Sobre tu tienda', 'max' => 240, 'fallback' => 'description',
                            'default' => ['es' => 'Moda para todos los días: prendas con estilo, buena calidad y precios que te encantan.', 'en' => 'Everyday fashion: stylish, quality pieces at prices you will love.']],
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
