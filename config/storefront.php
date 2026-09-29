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

// Sport Pro: un banner de portada (título grande, subtítulo espaciado, botón).
$sportSlide = static function (int $i, array $copy): array {
    $n = $i + 1;
    $fields = [];
    if ($i > 0) {
        $fields["hero.items.{$i}.enabled"] = ['type' => 'toggle', 'label' => "Mostrar banner {$n}", 'default' => $copy['enabled']];
    }

    return $fields + [
        "hero.items.{$i}.image" => ['type' => 'image', 'label' => "Imagen para computadora {$n}", 'aspect' => 'wide',
            'help' => 'Horizontal, ideal 1920 × 800 px. JPG, PNG o WEBP de hasta 4 MB.'],
        "hero.items.{$i}.image_mobile" => ['type' => 'image', 'label' => "Imagen para celular {$n}", 'aspect' => 'tall',
            'help' => 'Vertical, ideal 900 × 1200 px. Si no subes una, se usa la de computadora.'],
        "hero.items.{$i}.tone" => ['type' => 'select', 'label' => "Estilo del texto {$n}", 'default' => $copy['tone'] ?? 'light',
            'options' => ['light' => 'Texto blanco', 'dark' => 'Texto negro', 'image' => 'Solo la imagen (ya trae texto)']],
        "hero.items.{$i}.align" => ['type' => 'select', 'label' => "Posición del texto {$n}", 'default' => $copy['align'] ?? 'left',
            'options' => ['left' => 'Izquierda', 'center' => 'Centro', 'bottom' => 'Abajo al centro']],
        "hero.items.{$i}.title" => ['type' => 'text', 'label' => "Título {$n}", 'max' => 48, 'optional' => true, 'default' => $copy['title'] ?? ''],
        "hero.items.{$i}.subtitle" => ['type' => 'text', 'label' => "Subtítulo {$n}", 'max' => 60, 'optional' => true, 'default' => $copy['subtitle'] ?? '',
            'help' => 'Se muestra con el color destacado y letras espaciadas.'],
        "hero.items.{$i}.cta" => ['type' => 'text', 'label' => "Texto del botón {$n}", 'max' => 24, 'optional' => true, 'default' => $copy['cta'] ?? ''],
        "hero.items.{$i}.link" => ['type' => 'link', 'label' => "Al hacer clic lleva a {$n}", 'default' => $copy['link'] ?? 'catalog'],
    ];
};

// Sport Pro: una tarjeta de imagen con título y botón (bloques "Lo nuevo" y "Campañas").
$sportCard = static function (string $group, int $i, array $copy, bool $withSubtitle = true): array {
    $n = $i + 1;
    $fields = [
        "{$group}.items.{$i}.image" => ['type' => 'image', 'label' => "Foto {$n}", 'aspect' => $copy['aspect'] ?? 'portrait',
            'help' => $copy['help'] ?? 'Sin foto se usa la de una categoría o un producto.'],
        "{$group}.items.{$i}.title" => ['type' => 'text', 'label' => "Título {$n}", 'max' => 40, 'optional' => true, 'default' => $copy['title'] ?? ''],
    ];
    if ($withSubtitle) {
        $fields["{$group}.items.{$i}.subtitle"] = ['type' => 'text', 'label' => "Subtítulo {$n}", 'max' => 60, 'optional' => true, 'default' => $copy['subtitle'] ?? ''];
    }

    return $fields + [
        "{$group}.items.{$i}.cta" => ['type' => 'text', 'label' => "Texto del botón {$n}", 'max' => 20, 'optional' => true, 'default' => $copy['cta'] ?? ''],
        "{$group}.items.{$i}.link" => ['type' => 'link', 'label' => "Lleva a {$n}", 'default' => $copy['link'] ?? 'catalog'],
    ];
};

// Textil Pro: tarjetas repetidas (servicios, pasos, rangos de precio…) que comparten campos.
// $fields: clave => definición (su 'label' recibe el número de tarjeta); $items: textos por
// defecto de cada tarjeta, clave => valor.
$textilItems = static function (string $group, array $fields, array $items): array {
    $out = [];
    foreach ($items as $i => $copy) {
        foreach ($fields as $key => $spec) {
            $field = $spec;
            $field['label'] = $spec['label'] . ' ' . ($i + 1);
            if (array_key_exists($key, $copy)) {
                $field['default'] = $copy[$key];
            }
            $out["{$group}.items.{$i}.{$key}"] = $field;
        }
    }

    return $out;
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
        'barlow'   => ['family' => "'Barlow Condensed', 'Oswald', sans-serif", 'google' => 'Barlow+Condensed:wght@500;600;700;800'],
        'oswald'   => ['family' => "'Oswald', 'Poppins', sans-serif", 'google' => 'Oswald:wght@500;600;700'],
    ],

    // Plantilla que recibe cada tienda nueva (SubscriptionController).
    'default_template' => 'soft-market',

    'templates' => [

        'soft-market' => [
            'name'        => 'Soft Market',
            'tagline'     => 'Cálida, redondeada y cercana',
            'description' => 'Tipografía amable, tarjetas suaves y un hero a pantalla completa con carrusel. Pensada para marcas de hogar, lifestyle, regalos y accesorios que quieren transmitir confianza.',
            'ideal_for'   => ['Hogar', 'Lifestyle', 'Regalos', 'Accesorios', 'Textilería'],
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
            'ideal_for'   => ['Ropa', 'Moda', 'Calzado', 'Accesorios', 'Textilería'],
            'tags'        => ['moda', 'banners', 'promociones', 'carrusel'],
            'status'      => 'available',
            'thumbnail'   => null,
            'swatches'    => ['#E0157A', '#3B0A2E', '#FFFFFF', '#111111'],
            'default_accent'    => '#E0157A',
            'default_secondary' => '#3B0A2E',
            'default_bg'        => '#FFFFFF',
            // Sin "multi-idioma" activado la tienda se muestra siempre en español.
            'respects_language_setting' => true,
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
                            'options' => ['white' => 'En blanco (solo logos PNG con fondo transparente)', 'original' => 'Con sus colores originales'],
                            'help' => 'Si tu logo tiene fondo (JPG o PNG con fondo de color) se muestra siempre con sus colores. Al bajar por la página el menú se vuelve blanco.'],
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

        'sport-pro' => [
            'name'        => 'Sport Pro',
            'tagline'     => 'Deportiva y audaz, como las grandes marcas',
            'description' => 'Pensada para tiendas de zapatillas y ropa deportiva: header negro, banners de campaña a pantalla completa, carruseles de producto, filtros por talla y color, y una ficha de producto con selector de tallas.',
            'ideal_for'   => ['Zapatillas', 'Deportes', 'Streetwear', 'Calzado'],
            'tags'        => ['deportiva', 'zapatillas', 'tallas', 'banners'],
            'status'      => 'available',
            'thumbnail'   => null,
            'swatches'    => ['#111111', '#D0021B', '#F2C94C', '#FFFFFF'],
            'default_accent'    => '#D0021B',
            'default_secondary' => '#F2C94C',
            'default_bg'        => '#FFFFFF',
            // Sin "multi-idioma" activado la tienda se muestra siempre en español.
            'respects_language_setting' => true,
            'settings' => [
                [
                    'key' => 'brand', 'title' => 'Colores y estilo', 'icon' => 'palette',
                    'description' => 'El menú y los botones van en negro; tus colores marcan ofertas y detalles.',
                    'fields' => [
                        'colors.primary' => ['type' => 'color', 'label' => 'Color de ofertas', 'default' => '#D0021B', 'logo' => 'primary',
                            'help' => 'Etiquetas de descuento, precios rebajados y "SALE" del menú.'],
                        'colors.secondary' => ['type' => 'color', 'label' => 'Color destacado', 'default' => '#F2C94C', 'logo' => 'secondary',
                            'help' => 'El subtítulo de los banners y pequeños detalles.'],
                        'colors.background' => ['type' => 'select', 'label' => 'Fondo de la tienda', 'default' => 'white',
                            'options' => ['white' => 'Blanco puro', 'mist' => 'Niebla', 'cream' => 'Crema', 'tint' => 'Tinte de tu color']],
                        'typography.heading' => ['type' => 'font', 'label' => 'Estilo de títulos', 'default' => 'barlow', 'options' => [
                            'barlow'  => 'Deportiva · Barlow Condensed',
                            'oswald'  => 'Impacto · Oswald',
                            'grotesk' => 'Tecnológica · Space Grotesk',
                            'poppins' => 'Urbana · Poppins',
                        ]],
                        'style.header' => ['type' => 'select', 'label' => 'Color del menú', 'default' => 'dark',
                            'options' => ['dark' => 'Negro', 'light' => 'Blanco']],
                        'style.logo_mode' => ['type' => 'select', 'label' => 'Tu logo sobre el menú negro', 'default' => 'white',
                            'options' => ['white' => 'En blanco (solo logos PNG con fondo transparente)', 'original' => 'Con sus colores originales'],
                            'help' => 'Un logo con fondo (JPG o PNG con fondo de color) siempre se muestra con sus colores.'],
                        'style.product_image' => ['type' => 'select', 'label' => 'Fotos de producto', 'default' => 'cover',
                            'options' => ['cover' => 'Foto a todo el recuadro', 'contain' => 'Producto completo sobre gris (ideal para fotos con fondo blanco)']],
                        'style.corners' => ['type' => 'select', 'label' => 'Esquinas de botones y fotos', 'default' => 'square',
                            'options' => ['square' => 'Rectas (deportivo)', 'soft' => 'Suaves']],
                    ],
                ],
                [
                    'key' => 'topbars', 'title' => 'Barras superiores', 'icon' => 'megaphone', 'item_label' => 'Mensaje',
                    'description' => 'La franja blanca con tres mensajes cortos y la franja gris bajo el menú.',
                    'fields' => [
                        'utility.enabled' => ['type' => 'toggle', 'label' => 'Mostrar la franja de mensajes', 'default' => true],
                        'utility.items.0.text' => ['type' => 'text', 'label' => 'Mensaje 1', 'max' => 60, 'optional' => true,
                            'default' => ['es' => 'Compra 100% segura', 'en' => '100% secure checkout']],
                        'utility.items.1.text' => ['type' => 'text', 'label' => 'Mensaje 2', 'max' => 60, 'optional' => true,
                            'default' => ['es' => 'Cambios de talla sin complicaciones', 'en' => 'Easy size exchanges']],
                        'utility.items.2.text' => ['type' => 'text', 'label' => 'Mensaje 3', 'max' => 60, 'optional' => true,
                            'default' => ['es' => 'Envíos a todo el país', 'en' => 'Nationwide shipping']],
                        'utility.links' => ['type' => 'toggle', 'label' => 'Mostrar accesos "Mis pedidos" y "Contáctanos"', 'default' => true],
                        'shipping.enabled' => ['type' => 'toggle', 'label' => 'Mostrar la franja gris bajo el menú', 'default' => true],
                        'shipping.text' => ['type' => 'text', 'label' => 'Texto de la franja gris', 'max' => 80,
                            'default' => ['es' => 'Envío en 48 horas para Lima y Callao*', 'en' => '48-hour delivery in Lima and Callao*']],
                        'shipping.link' => ['type' => 'link', 'label' => 'Al hacer clic lleva a', 'default' => 'none'],
                    ],
                ],
                [
                    'key' => 'menu', 'title' => 'Menú', 'icon' => 'menu',
                    'description' => 'Muestra tus categorías marcadas "Mostrar en el menú" (o las primeras seis) y un acceso destacado a ofertas.',
                    'fields' => [
                        'menu.sale_enabled' => ['type' => 'toggle', 'label' => 'Mostrar acceso destacado a ofertas', 'default' => true],
                        'menu.sale_label' => ['type' => 'text', 'label' => 'Texto del acceso', 'max' => 16, 'default' => 'SALE'],
                        'menu.sale_link' => ['type' => 'link', 'label' => 'El acceso lleva a', 'default' => 'sale'],
                    ],
                ],
                [
                    'key' => 'hero', 'title' => 'Banners de portada', 'icon' => 'image', 'item_label' => 'Banner',
                    'description' => 'Hasta 4 banners de campaña a pantalla completa. Sube tus fotos o banners ya diseñados.',
                    'fields' => [
                        'hero.autoplay' => ['type' => 'toggle', 'label' => 'Cambiar de banner automáticamente', 'default' => true],
                        'hero.height' => ['type' => 'select', 'label' => 'Alto de la portada', 'default' => 'tall',
                            'options' => ['full' => 'Pantalla completa', 'tall' => 'Alta', 'medium' => 'Mediana']],
                    ]
                    + $sportSlide(0, ['tone' => 'light', 'align' => 'left', 'link' => 'new',
                        'title' => ['es' => 'Hechas para moverte', 'en' => 'Made to move'],
                        'subtitle' => ['es' => 'Nueva colección', 'en' => 'New collection'],
                        'cta' => ['es' => 'Comprar ahora', 'en' => 'Shop now']])
                    + $sportSlide(1, ['enabled' => true, 'tone' => 'light', 'align' => 'bottom', 'link' => 'catalog',
                        'title' => ['es' => 'Estilo para cada paso', 'en' => 'Style for every step'],
                        'subtitle' => ['es' => 'Clásicos que nunca fallan', 'en' => 'Classics that never fail'],
                        'cta' => ['es' => 'Comprar', 'en' => 'Shop']])
                    + $sportSlide(2, ['enabled' => false, 'tone' => 'light', 'align' => 'center', 'link' => 'sale',
                        'title' => ['es' => 'Sale de temporada', 'en' => 'Season sale'],
                        'subtitle' => ['es' => 'Precios que te van a encantar', 'en' => 'Prices you will love'],
                        'cta' => ['es' => 'Ver ofertas', 'en' => 'Shop the sale']])
                    + $sportSlide(3, ['enabled' => false, 'tone' => 'dark', 'align' => 'left', 'link' => 'catalog',
                        'title' => ['es' => 'Rendimiento diario', 'en' => 'Everyday performance'],
                        'cta' => ['es' => 'Descubre', 'en' => 'Discover']]),
                ],
                [
                    'key' => 'rail', 'title' => 'Carrusel de productos', 'icon' => 'grid',
                    'description' => 'La fila deslizable justo debajo de los banners.',
                    'fields' => [
                        'rail.enabled' => ['type' => 'toggle', 'label' => 'Mostrar el carrusel', 'default' => true],
                        'rail.title' => ['type' => 'text', 'label' => 'Título (opcional)', 'max' => 40, 'default' => ''],
                        'rail.source' => ['type' => 'select', 'label' => 'Qué productos mostrar', 'default' => 'featured',
                            'options' => ['featured' => 'Destacados', 'newest' => 'Lo más nuevo', 'sale' => 'En oferta']],
                    ],
                ],
                [
                    'key' => 'tiles', 'title' => 'Lo nuevo', 'icon' => 'sparkles', 'item_label' => 'Tarjeta',
                    'description' => 'Tres fotos verticales para destacar colecciones o categorías.',
                    'fields' => [
                        'tiles.enabled' => ['type' => 'toggle', 'label' => 'Mostrar esta sección', 'default' => true],
                        'tiles.title' => ['type' => 'text', 'label' => 'Título de la sección', 'max' => 40, 'optional' => true,
                            'default' => ['es' => 'Lo nuevo', 'en' => 'New in']],
                    ]
                    + $sportCard('tiles', 0, ['title' => ['es' => 'Mujer', 'en' => 'Women'], 'cta' => ['es' => 'Comprar', 'en' => 'Shop']], false)
                    + $sportCard('tiles', 1, ['title' => ['es' => 'Hombre', 'en' => 'Men'], 'cta' => ['es' => 'Comprar', 'en' => 'Shop']], false)
                    + $sportCard('tiles', 2, ['title' => ['es' => 'Niños', 'en' => 'Kids'], 'cta' => ['es' => 'Comprar', 'en' => 'Shop']], false),
                ],
                [
                    'key' => 'campaigns', 'title' => 'Campañas', 'icon' => 'layout', 'item_label' => 'Campaña',
                    'description' => 'Fotos grandes lado a lado con título, subtítulo y botón debajo.',
                    'fields' => [
                        'campaigns.enabled' => ['type' => 'toggle', 'label' => 'Mostrar campañas', 'default' => true],
                        'campaigns.second_row' => ['type' => 'toggle', 'label' => 'Mostrar una segunda fila (campañas 3 y 4)', 'default' => false],
                    ]
                    + $sportCard('campaigns', 0, ['aspect' => 'wide', 'help' => 'Ideal 1200 × 850 px. Sin foto se usa la de un producto.', 'link' => 'new',
                        'title' => ['es' => 'Clásicos urbanos', 'en' => 'Urban classics'], 'subtitle' => ['es' => 'Estilo que nunca pasa de moda', 'en' => 'Style that never fades'], 'cta' => ['es' => 'Descubre', 'en' => 'Discover']])
                    + $sportCard('campaigns', 1, ['aspect' => 'wide', 'help' => 'Ideal 1200 × 850 px. Sin foto se usa la de un producto.',
                        'title' => ['es' => 'Running', 'en' => 'Running'], 'subtitle' => ['es' => 'Hechas para ir más lejos', 'en' => 'Built to go further'], 'cta' => ['es' => 'Descubre', 'en' => 'Discover']])
                    + $sportCard('campaigns', 2, ['aspect' => 'wide', 'help' => 'Ideal 1200 × 850 px.',
                        'title' => ['es' => 'Entrenamiento', 'en' => 'Training'], 'subtitle' => ['es' => 'Rendimiento en cada paso', 'en' => 'Performance in every step'], 'cta' => ['es' => 'Ver todo', 'en' => 'View all']])
                    + $sportCard('campaigns', 3, ['aspect' => 'wide', 'help' => 'Ideal 1200 × 850 px.', 'link' => 'sale',
                        'title' => ['es' => 'Outlet', 'en' => 'Outlet'], 'subtitle' => ['es' => 'Precios que te van a encantar', 'en' => 'Prices you will love'], 'cta' => ['es' => 'Ver todo', 'en' => 'View all']]),
                ],
                [
                    'key' => 'sections', 'title' => 'Categorías y productos', 'icon' => 'heading',
                    'description' => 'Tus categorías con foto y la grilla de productos al final de la portada.',
                    'fields' => [
                        'categories.enabled' => ['type' => 'toggle', 'label' => 'Mostrar categorías', 'default' => true],
                        'categories.title' => ['type' => 'text', 'label' => 'Categorías · título', 'max' => 60, 'optional' => true,
                            'default' => ['es' => 'Tus favoritos', 'en' => 'Your favorites']],
                        'sections.products_title' => ['type' => 'text', 'label' => 'Grilla · título', 'max' => 60,
                            'default' => ['es' => 'Explora la colección', 'en' => 'Explore the collection']],
                    ],
                ],
                [
                    'key' => 'product', 'title' => 'Página de producto', 'icon' => 'tag',
                    'description' => 'Textos de ayuda que ve el cliente al elegir su talla.',
                    'fields' => [
                        'product.shipping_note' => ['type' => 'text', 'label' => 'Nota bajo el botón de compra', 'max' => 90, 'optional' => true,
                            'default' => ['es' => 'Envíos a todo el país · Pago 100% seguro', 'en' => 'Nationwide shipping · 100% secure payment']],
                        'product.size_guide' => ['type' => 'textarea', 'label' => 'Guía de tallas (opcional)', 'max' => 600, 'default' => '',
                            'help' => 'Una línea por talla, por ejemplo "39 = 25 cm". Si lo dejas vacío no se muestra el enlace "Guía de tallas".'],
                        'product.returns' => ['type' => 'textarea', 'label' => 'Cambios y devoluciones', 'max' => 400, 'optional' => true,
                            'default' => ['es' => 'Puedes solicitar un cambio de talla dentro de los 7 días posteriores a la entrega, con el producto sin uso y en su caja original.', 'en' => 'You can request a size exchange within 7 days of delivery, unused and in the original box.']],
                    ],
                ],
                [
                    'key' => 'signup', 'title' => 'Invitación a registrarse', 'icon' => 'star',
                    'description' => 'La pestaña flotante abajo a la izquierda. Abre el registro de Tribio Pass (mis pedidos y datos guardados).',
                    'fields' => [
                        'signup.enabled' => ['type' => 'toggle', 'label' => 'Mostrar la pestaña', 'default' => true],
                        'signup.text' => ['type' => 'text', 'label' => 'Texto', 'max' => 40,
                            'default' => ['es' => 'Regístrate y compra más rápido', 'en' => 'Sign up and check out faster'],
                            'help' => 'Si ofreces un descuento al registrarse, escríbelo aquí (por ejemplo "Regístrate y obtén 10% OFF").'],
                    ],
                ],
                [
                    'key' => 'footer', 'title' => 'Pie de página', 'icon' => 'layout',
                    'description' => 'Incluye el Libro de Reclamaciones y la suscripción a novedades (llegan a tus mensajes).',
                    'fields' => [
                        'footer.newsletter_title' => ['type' => 'text', 'label' => 'Suscripción · título', 'max' => 40,
                            'default' => ['es' => 'Mantente al día', 'en' => 'Stay in the loop']],
                        'footer.newsletter_text' => ['type' => 'text', 'label' => 'Suscripción · texto', 'max' => 100,
                            'default' => ['es' => 'Inscríbete y entérate primero de lanzamientos y ofertas.', 'en' => 'Sign up and be the first to hear about drops and sales.']],
                        'footer.about' => ['type' => 'textarea', 'label' => 'Sobre tu tienda', 'max' => 240, 'fallback' => 'description',
                            'default' => ['es' => 'Zapatillas y ropa deportiva originales para entrenar, correr y vivir la ciudad.', 'en' => 'Original sneakers and sportswear to train, run and live the city.']],
                    ],
                ],
            ],
        ],

        'textil-pro' => [
            'name'        => 'Textil Pro',
            'tagline'     => 'Para talleres de estampado, confección y personalizados',
            'description' => 'Pensada para negocios textiles que venden por mayor y menor: cotización por WhatsApp a un clic, servicios, cómo funciona, precios por volumen, impresión por metro, para quién trabajas y preguntas frecuentes. Lista para pedidos por encargo con diseño del cliente.',
            'ideal_for'   => ['Textilería', 'Estampados', 'Uniformes', 'Ropa'],
            'tags'        => ['textil', 'estampado', 'por mayor', 'cotización'],
            'status'      => 'available',
            'thumbnail'   => null,
            'swatches'    => ['#1F2A5C', '#FFC400', '#FFFFFF', '#141A2E'],
            'default_accent'    => '#FFC400',
            'default_secondary' => '#1F2A5C',
            'default_bg'        => '#FFFFFF',
            // Sin "multi-idioma" activado la tienda se muestra siempre en español.
            'respects_language_setting' => true,
            'settings' => [
                [
                    'key' => 'brand', 'title' => 'Colores y estilo', 'icon' => 'palette',
                    'description' => 'Tu color vivo pinta los botones de cotizar y los destacados; tu color oscuro, el menú y las bandas.',
                    'fields' => [
                        'colors.primary' => ['type' => 'color', 'label' => 'Color vivo (botones y destacados)', 'default' => '#FFC400', 'logo' => 'primary',
                            'help' => 'Botón "Cotizar", cifras, banda de impresión por metro y detalles.'],
                        'colors.secondary' => ['type' => 'color', 'label' => 'Color oscuro (menú y bandas)', 'default' => '#1F2A5C', 'logo' => 'secondary',
                            'help' => 'Usa un tono oscuro de tu logo: el texto encima se pone blanco solo.'],
                        'colors.background' => ['type' => 'select', 'label' => 'Fondo de la tienda', 'default' => 'white',
                            'options' => ['white' => 'Blanco puro', 'mist' => 'Niebla', 'cream' => 'Crema', 'tint' => 'Tinte de tu color']],
                        'typography.heading' => ['type' => 'font', 'label' => 'Estilo de títulos', 'default' => 'poppins', 'options' => [
                            'poppins' => 'Urbana · Poppins',
                            'jakarta' => 'Moderna · Plus Jakarta Sans',
                            'oswald'  => 'Impacto · Oswald',
                            'grotesk' => 'Tecnológica · Space Grotesk',
                            'fredoka' => 'Amigable · Fredoka',
                        ]],
                        'style.header' => ['type' => 'select', 'label' => 'Color del menú', 'default' => 'brand',
                            'options' => ['brand' => 'Tu color oscuro', 'dark' => 'Negro', 'light' => 'Blanco']],
                        'style.logo_mode' => ['type' => 'select', 'label' => 'Tu logo sobre el menú oscuro', 'default' => 'original',
                            'options' => ['original' => 'Con sus colores originales', 'white' => 'En blanco (solo logos PNG con fondo transparente)']],
                        'style.product_image' => ['type' => 'select', 'label' => 'Fotos de producto', 'default' => 'cover',
                            'options' => ['cover' => 'Foto a todo el recuadro', 'contain' => 'Producto completo sobre gris (ideal para fotos con fondo blanco)']],
                        'style.corners' => ['type' => 'select', 'label' => 'Esquinas de botones y fotos', 'default' => 'soft',
                            'options' => ['soft' => 'Suaves', 'square' => 'Rectas']],
                    ],
                ],
                [
                    'key' => 'topbars', 'title' => 'Barras superiores', 'icon' => 'megaphone', 'item_label' => 'Mensaje',
                    'description' => 'La franja de tres mensajes cortos arriba y la franja bajo el menú.',
                    'fields' => [
                        'utility.enabled' => ['type' => 'toggle', 'label' => 'Mostrar la franja de mensajes', 'default' => true],
                        'utility.items.0.text' => ['type' => 'text', 'label' => 'Mensaje 1', 'max' => 60, 'optional' => true, 'default' => 'Atención por mayor y menor'],
                        'utility.items.1.text' => ['type' => 'text', 'label' => 'Mensaje 2', 'max' => 60, 'optional' => true, 'default' => 'Diseños exclusivos con tu marca'],
                        'utility.items.2.text' => ['type' => 'text', 'label' => 'Mensaje 3', 'max' => 60, 'optional' => true, 'default' => 'Envíos a todo el país'],
                        'utility.links' => ['type' => 'toggle', 'label' => 'Mostrar accesos "Mis pedidos" y "Contáctanos"', 'default' => true],
                        'shipping.enabled' => ['type' => 'toggle', 'label' => 'Mostrar la franja bajo el menú', 'default' => false],
                        'shipping.text' => ['type' => 'text', 'label' => 'Texto de la franja bajo el menú', 'max' => 80, 'default' => 'Pedidos por mayor con precios especiales · ¡Cotiza hoy!'],
                        'shipping.link' => ['type' => 'link', 'label' => 'Al hacer clic lleva a', 'default' => 'none'],
                    ],
                ],
                [
                    'key' => 'menu', 'title' => 'Menú', 'icon' => 'menu',
                    'description' => 'Tus categorías marcadas "Mostrar en el menú" (o las primeras seis), accesos a Servicios y Por mayor, y el botón para cotizar.',
                    'fields' => [
                        'menu.quote_enabled' => ['type' => 'toggle', 'label' => 'Mostrar el botón para cotizar por WhatsApp', 'default' => true,
                            'help' => 'Abre WhatsApp con tu mensaje de cotización. Sin WhatsApp configurado lleva a tu página de contacto.'],
                        'menu.quote_label' => ['type' => 'text', 'label' => 'Texto del botón', 'max' => 24, 'default' => 'Cotizar por WhatsApp'],
                        'menu.sale_enabled' => ['type' => 'toggle', 'label' => 'Mostrar acceso destacado a ofertas', 'default' => false],
                        'menu.sale_label' => ['type' => 'text', 'label' => 'Texto del acceso a ofertas', 'max' => 16, 'default' => 'Ofertas'],
                        'menu.sale_link' => ['type' => 'link', 'label' => 'El acceso a ofertas lleva a', 'default' => 'sale'],
                    ],
                ],
                [
                    'key' => 'hero', 'title' => 'Portada', 'icon' => 'image', 'item_label' => 'Ventaja',
                    'description' => 'Lo primero que ve tu cliente: qué haces y un botón para cotizar. Sin foto se muestra una prenda con tus colores y tu logo estampado.',
                    'fields' => [
                        'hero.style' => ['type' => 'select', 'label' => 'Fondo de la portada', 'default' => 'brand',
                            'options' => ['brand' => 'Tu color oscuro', 'light' => 'Claro']],
                        'hero.eyebrow' => ['type' => 'text', 'label' => 'Texto pequeño sobre el título', 'max' => 48, 'optional' => true, 'default' => 'Estampado DTF · Por mayor y menor'],
                        'hero.title' => ['type' => 'text', 'label' => 'Título', 'max' => 60, 'default' => 'Tu diseño, estampado'],
                        'hero.title_highlight' => ['type' => 'text', 'label' => 'Segunda línea del título (con tu color vivo)', 'max' => 60, 'optional' => true, 'default' => 'en la prenda que quieras'],
                        'hero.text' => ['type' => 'textarea', 'label' => 'Descripción', 'max' => 240, 'optional' => true,
                            'default' => 'Polos, poleras, casacas y gorras con diseños exclusivos para empresas, colegios, emprendedores y eventos. Desde una unidad hasta pedidos por mayor.'],
                        'hero.cta_primary' => ['type' => 'text', 'label' => 'Botón principal (abre WhatsApp)', 'max' => 28, 'default' => 'Cotizar por WhatsApp'],
                        'hero.cta_secondary' => ['type' => 'text', 'label' => 'Botón secundario', 'max' => 24, 'optional' => true, 'default' => 'Ver catálogo'],
                        'hero.cta_secondary_link' => ['type' => 'link', 'label' => 'El botón secundario lleva a', 'default' => 'catalog'],
                        'hero.image' => ['type' => 'image', 'label' => 'Foto de portada (opcional)', 'aspect' => 'portrait',
                            'help' => 'Una foto de tus prendas o de tu taller, ideal 1000 × 1100 px. Sin foto se muestra la prenda ilustrada.'],
                    ]
                    + $textilItems('hero.badges', ['text' => ['type' => 'text', 'label' => 'Ventaja', 'max' => 32, 'optional' => true]], [
                        ['text' => 'Desde 1 unidad'],
                        ['text' => 'Full color de alta duración'],
                        ['text' => 'Envíos a todo el país'],
                    ]),
                ],
                [
                    'key' => 'stats', 'title' => 'Cifras', 'icon' => 'sparkles', 'item_label' => 'Cifra',
                    'description' => 'La franja de tu color vivo con cuatro datos cortos. Deja una cifra vacía para ocultarla.',
                    'fields' => [
                        'stats.enabled' => ['type' => 'toggle', 'label' => 'Mostrar las cifras', 'default' => true],
                    ]
                    + $textilItems('stats', [
                        'value' => ['type' => 'text', 'label' => 'Cifra', 'max' => 12, 'optional' => true],
                        'label' => ['type' => 'text', 'label' => 'Descripción de la cifra', 'max' => 40, 'optional' => true],
                    ], [
                        ['value' => 'DTF', 'label' => 'Full color y alta duración'],
                        ['value' => 'Desde 1', 'label' => 'unidad por diseño'],
                        ['value' => 'Por mayor', 'label' => 'precios por volumen'],
                        ['value' => 'Todo el país', 'label' => 'envíos seguros'],
                    ]),
                ],
                [
                    'key' => 'services', 'title' => 'Servicios', 'icon' => 'grid', 'item_label' => 'Servicio',
                    'description' => 'Qué haces, en seis tarjetas. Deja un título vacío para ocultar esa tarjeta.',
                    'fields' => [
                        'services.enabled' => ['type' => 'toggle', 'label' => 'Mostrar servicios', 'default' => true],
                        'services.eyebrow' => ['type' => 'text', 'label' => 'Texto pequeño sobre el título', 'max' => 40, 'optional' => true, 'default' => 'Lo que hacemos'],
                        'services.title' => ['type' => 'text', 'label' => 'Título de la sección', 'max' => 60, 'default' => 'Estampamos todo lo que imaginas'],
                    ]
                    + $textilItems('services', [
                        'icon' => ['type' => 'emoji', 'label' => 'Ícono'],
                        'title' => ['type' => 'text', 'label' => 'Título', 'max' => 40, 'optional' => true],
                        'text' => ['type' => 'text', 'label' => 'Descripción', 'max' => 110, 'optional' => true],
                        'link' => ['type' => 'link', 'label' => 'Al hacer clic lleva a', 'default' => 'none'],
                    ], [
                        ['icon' => '🖨️', 'title' => 'Estampado DTF', 'text' => 'Full color, colores vivos y gran duración en algodón, poliéster y más.'],
                        ['icon' => '👕', 'title' => 'Polos personalizados', 'text' => 'Con tu diseño, nombre, foto o logo. Desde una sola unidad.'],
                        ['icon' => '🏢', 'title' => 'Uniformes corporativos', 'text' => 'Polos, casacas y gorras con el logo de tu empresa.'],
                        ['icon' => '🎓', 'title' => 'Promociones y colegios', 'text' => 'Polos de promoción, egresados, aniversarios y actividades escolares.'],
                        ['icon' => '🎉', 'title' => 'Eventos y fechas especiales', 'text' => 'Cumpleaños, bodas, campeonatos y recuerdos únicos.'],
                        ['icon' => '🚀', 'title' => 'Tu propia marca', 'text' => 'Estampamos para emprendedores que venden con su propio estilo.'],
                    ]),
                ],
                [
                    'key' => 'process', 'title' => 'Cómo funciona', 'icon' => 'layout', 'item_label' => 'Paso',
                    'description' => 'Los cuatro pasos de un pedido, para que el cliente sepa qué esperar.',
                    'fields' => [
                        'process.enabled' => ['type' => 'toggle', 'label' => 'Mostrar esta sección', 'default' => true],
                        'process.title' => ['type' => 'text', 'label' => 'Título de la sección', 'max' => 60, 'default' => 'Pedir es así de fácil'],
                    ]
                    + $textilItems('process', [
                        'title' => ['type' => 'text', 'label' => 'Paso', 'max' => 40],
                        'text' => ['type' => 'text', 'label' => 'Detalle del paso', 'max' => 110, 'optional' => true],
                    ], [
                        ['title' => 'Elige tu prenda', 'text' => 'Polos, poleras, casacas, gorras, tazas y más.'],
                        ['title' => 'Envíanos tu diseño', 'text' => 'Tu logo o tu idea. Si aún no tienes diseño, lo creamos contigo.'],
                        ['title' => 'Aprueba la vista previa', 'text' => 'Te mostramos cómo quedará antes de producir.'],
                        ['title' => 'Recibe tu pedido', 'text' => 'Lo producimos y lo enviamos a todo el país.'],
                    ]),
                ],
                [
                    'key' => 'wholesale', 'title' => 'Por mayor y menor', 'icon' => 'tag', 'item_label' => 'Rango',
                    'description' => 'Tus rangos de cantidad y el beneficio de cada uno. El botón abre WhatsApp para cotizar.',
                    'fields' => [
                        'wholesale.enabled' => ['type' => 'toggle', 'label' => 'Mostrar esta sección', 'default' => true],
                        'wholesale.eyebrow' => ['type' => 'text', 'label' => 'Texto pequeño sobre el título', 'max' => 40, 'optional' => true, 'default' => 'Precios por volumen'],
                        'wholesale.title' => ['type' => 'text', 'label' => 'Título', 'max' => 60, 'default' => 'Por mayor y menor'],
                        'wholesale.text' => ['type' => 'textarea', 'label' => 'Descripción', 'max' => 240,
                            'default' => 'Mientras más unidades pidas, mejor es tu precio. Ideal para empresas, colegios, eventos y emprendedores que revenden.'],
                        'wholesale.cta' => ['type' => 'text', 'label' => 'Texto del botón', 'max' => 28, 'default' => 'Cotizar pedido por mayor'],
                        'wholesale.note' => ['type' => 'text', 'label' => 'Nota bajo los rangos', 'max' => 120, 'optional' => true,
                            'default' => 'El precio final depende de la prenda y del diseño. Te lo confirmamos al cotizar.'],
                    ]
                    + $textilItems('wholesale', [
                        'qty' => ['type' => 'text', 'label' => 'Cantidad', 'max' => 24, 'optional' => true],
                        'benefit' => ['type' => 'text', 'label' => 'Beneficio', 'max' => 40, 'optional' => true],
                    ], [
                        ['qty' => '1 – 11 unidades', 'benefit' => 'Precio por menor'],
                        ['qty' => '12 – 49 unidades', 'benefit' => 'Precio por mayor'],
                        ['qty' => '50 – 99 unidades', 'benefit' => 'Mejor precio por volumen'],
                        ['qty' => '100 a más', 'benefit' => 'Cotización especial'],
                    ]),
                ],
                [
                    'key' => 'promo', 'title' => 'Banda promocional', 'icon' => 'megaphone',
                    'description' => 'Una banda de tu color vivo para un servicio estrella, por ejemplo impresión DTF por metro. Deja el precio vacío para no mostrarlo.',
                    'fields' => [
                        'promo.enabled' => ['type' => 'toggle', 'label' => 'Mostrar la banda', 'default' => true],
                        'promo.eyebrow' => ['type' => 'text', 'label' => 'Texto pequeño sobre el título', 'max' => 40, 'optional' => true, 'default' => 'Impresión DTF'],
                        'promo.title' => ['type' => 'text', 'label' => 'Título', 'max' => 60, 'default' => 'Imprimimos tus diseños por metro'],
                        'promo.text' => ['type' => 'textarea', 'label' => 'Descripción', 'max' => 200, 'optional' => true,
                            'default' => 'Envíanos tu archivo listo y te entregamos tus transfers full color, con colores vibrantes y calidad premium.'],
                        'promo.price_prefix' => ['type' => 'text', 'label' => 'Antes del precio', 'max' => 16, 'optional' => true, 'default' => 'Desde'],
                        'promo.price' => ['type' => 'text', 'label' => 'Precio', 'max' => 12, 'optional' => true, 'default' => '',
                            'help' => 'Por ejemplo "S/25". Vacío = no se muestra el precio.'],
                        'promo.price_note' => ['type' => 'text', 'label' => 'Después del precio', 'max' => 20, 'optional' => true, 'default' => 'el metro'],
                        'promo.cta' => ['type' => 'text', 'label' => 'Texto del botón', 'max' => 24, 'default' => 'Pedir impresión'],
                        'promo.link' => ['type' => 'link', 'label' => 'El botón lleva a', 'default' => 'none',
                            'help' => '"Sin enlace" abre WhatsApp con tu mensaje de cotización.'],
                        'promo.image' => ['type' => 'image', 'label' => 'Foto (opcional)', 'aspect' => 'wide',
                            'help' => 'Tu máquina, rollos impresos o un trabajo terminado, ideal 1200 × 800 px.'],
                    ],
                ],
                [
                    'key' => 'segments', 'title' => 'Para quién trabajas', 'icon' => 'star', 'item_label' => 'Tarjeta',
                    'description' => 'Cuatro tarjetas con tus tipos de cliente. Cada una abre WhatsApp para cotizar.',
                    'fields' => [
                        'segments.enabled' => ['type' => 'toggle', 'label' => 'Mostrar esta sección', 'default' => true],
                        'segments.title' => ['type' => 'text', 'label' => 'Título de la sección', 'max' => 60, 'default' => '¿Para quién trabajamos?'],
                    ]
                    + $textilItems('segments', [
                        'icon' => ['type' => 'emoji', 'label' => 'Ícono'],
                        'title' => ['type' => 'text', 'label' => 'Título', 'max' => 32, 'optional' => true],
                        'text' => ['type' => 'text', 'label' => 'Descripción', 'max' => 90, 'optional' => true],
                        'image' => ['type' => 'image', 'label' => 'Foto (opcional)', 'aspect' => 'portrait', 'help' => 'Sin foto se muestra el ícono sobre tu color.'],
                    ], [
                        ['icon' => '🏢', 'title' => 'Empresas', 'text' => 'Uniformes y merchandising con tu logo.'],
                        ['icon' => '🎓', 'title' => 'Colegios y promociones', 'text' => 'Polos de promoción, egresados y eventos.'],
                        ['icon' => '🛍️', 'title' => 'Emprendedores', 'text' => 'Tu marca estampada para vender.'],
                        ['icon' => '🎉', 'title' => 'Eventos', 'text' => 'Cumpleaños, campeonatos y celebraciones.'],
                    ]),
                ],
                [
                    'key' => 'sections', 'title' => 'Categorías y productos', 'icon' => 'heading',
                    'description' => 'Tus categorías con foto y tus productos. Mientras no tengas productos se muestra una invitación a cotizar.',
                    'fields' => [
                        'categories.enabled' => ['type' => 'toggle', 'label' => 'Mostrar categorías', 'default' => true],
                        'categories.title' => ['type' => 'text', 'label' => 'Categorías · título', 'max' => 60, 'optional' => true, 'default' => 'Elige tu prenda'],
                        'sections.products_title' => ['type' => 'text', 'label' => 'Productos · título', 'max' => 60, 'default' => 'Nuestros productos'],
                        'sections.empty_title' => ['type' => 'text', 'label' => 'Sin productos · título', 'max' => 60, 'default' => '¿Tienes un diseño en mente?'],
                        'sections.empty_text' => ['type' => 'text', 'label' => 'Sin productos · texto', 'max' => 160,
                            'default' => 'Cuéntanos qué prenda, cuántas unidades y tu idea. Te enviamos una cotización sin compromiso.'],
                    ],
                ],
                [
                    'key' => 'faq', 'title' => 'Preguntas frecuentes', 'icon' => 'heading', 'item_label' => 'Pregunta',
                    'description' => 'Resuelve las dudas de siempre antes de que te escriban. Deja una pregunta vacía para ocultarla.',
                    'fields' => [
                        'faq.enabled' => ['type' => 'toggle', 'label' => 'Mostrar preguntas frecuentes', 'default' => true],
                        'faq.title' => ['type' => 'text', 'label' => 'Título de la sección', 'max' => 60, 'default' => 'Preguntas frecuentes'],
                    ]
                    + $textilItems('faq', [
                        'question' => ['type' => 'text', 'label' => 'Pregunta', 'max' => 90, 'optional' => true],
                        'answer' => ['type' => 'textarea', 'label' => 'Respuesta', 'max' => 300, 'optional' => true],
                    ], [
                        ['question' => '¿Cuál es el pedido mínimo?', 'answer' => 'Estampamos desde una unidad. En pedidos por mayor obtienes mejores precios.'],
                        ['question' => '¿Cómo envío mi diseño?', 'answer' => 'Por WhatsApp o al hacer tu pedido: tu logo o imagen en buena calidad (PNG, JPG o PDF). Si no tienes diseño, te ayudamos a crearlo.'],
                        ['question' => '¿Cuánto demora mi pedido?', 'answer' => 'Depende de la cantidad y del tipo de prenda. Te confirmamos la fecha de entrega al cotizar.'],
                        ['question' => '¿Hacen envíos?', 'answer' => 'Sí, enviamos a todo el país. También puedes recoger tu pedido en nuestro local.'],
                        ['question' => '¿Cómo pago?', 'answer' => 'Con los medios de pago de la tienda. En pedidos por encargo puedes pagar un adelanto y el saldo antes de la entrega.'],
                    ]),
                ],
                [
                    'key' => 'contact', 'title' => 'Contacto y ubicación', 'icon' => 'megaphone',
                    'description' => 'La banda final con tu dirección, horario, WhatsApp y redes (se toman de "Mi Tienda"), y el botón flotante de WhatsApp.',
                    'fields' => [
                        'contact.enabled' => ['type' => 'toggle', 'label' => 'Mostrar la banda de contacto', 'default' => true],
                        'contact.title' => ['type' => 'text', 'label' => 'Título', 'max' => 60, 'default' => 'Visítanos o escríbenos'],
                        'contact.text' => ['type' => 'text', 'label' => 'Texto', 'max' => 160, 'optional' => true,
                            'default' => 'Te asesoramos para elegir la prenda y el estampado ideal para tu pedido.'],
                        'contact.hours' => ['type' => 'text', 'label' => 'Horario de atención', 'max' => 80, 'optional' => true, 'default' => 'Lunes a sábado · 9:00 a. m. – 7:00 p. m.'],
                        'contact.whatsapp_message' => ['type' => 'text', 'label' => 'Mensaje con el que el cliente abre WhatsApp', 'max' => 120,
                            'default' => 'Hola, quiero cotizar un pedido de estampado.'],
                        'contact.floating' => ['type' => 'toggle', 'label' => 'Mostrar el botón flotante de WhatsApp', 'default' => true],
                    ],
                ],
                [
                    'key' => 'product', 'title' => 'Página de producto', 'icon' => 'tag',
                    'description' => 'Textos de ayuda que ve el cliente al elegir talla y cantidad.',
                    'fields' => [
                        'product.shipping_note' => ['type' => 'text', 'label' => 'Nota bajo el botón de compra', 'max' => 90, 'optional' => true,
                            'default' => 'Envíos a todo el país · ¿Pedido por mayor? Cotiza por WhatsApp'],
                        'product.size_guide' => ['type' => 'textarea', 'label' => 'Guía de tallas (opcional)', 'max' => 600, 'default' => '',
                            'help' => 'Una línea por talla, por ejemplo "M = 52 cm de ancho × 72 cm de largo". Vacío = no se muestra "Guía de tallas".'],
                        'product.returns' => ['type' => 'textarea', 'label' => 'Cambios y devoluciones', 'max' => 400, 'optional' => true,
                            'default' => 'Los productos personalizados se fabrican a pedido, por eso no tienen cambio salvo fallas de fabricación. Revisa la vista previa antes de aprobar.'],
                    ],
                ],
                [
                    'key' => 'signup', 'title' => 'Invitación a registrarse', 'icon' => 'star',
                    'description' => 'La pestaña flotante abajo a la izquierda. Abre el registro de Tribio Pass (mis pedidos y datos guardados).',
                    'fields' => [
                        'signup.enabled' => ['type' => 'toggle', 'label' => 'Mostrar la pestaña', 'default' => false],
                        'signup.text' => ['type' => 'text', 'label' => 'Texto', 'max' => 40, 'default' => 'Regístrate y sigue tus pedidos'],
                    ],
                ],
                [
                    'key' => 'footer', 'title' => 'Pie de página', 'icon' => 'layout',
                    'description' => 'Incluye el Libro de Reclamaciones y la suscripción a novedades (llegan a tus mensajes).',
                    'fields' => [
                        'footer.newsletter_title' => ['type' => 'text', 'label' => 'Suscripción · título', 'max' => 40, 'default' => 'Novedades y promociones'],
                        'footer.newsletter_text' => ['type' => 'text', 'label' => 'Suscripción · texto', 'max' => 100, 'default' => 'Déjanos tu correo y entérate primero de nuevos diseños y ofertas.'],
                        'footer.about' => ['type' => 'textarea', 'label' => 'Sobre tu negocio', 'max' => 240, 'fallback' => 'description',
                            'default' => 'Taller de estampado y confección: diseños exclusivos por mayor y menor para empresas, colegios, emprendedores y eventos.'],
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
