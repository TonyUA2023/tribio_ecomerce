<?php

namespace App\Helpers;

class TranslationHelper
{
    /**
     * Obtiene el código del idioma actual ('es' o 'en').
     */
    public static function currentLang(): string
    {
        // 1. Parámetro query explícito ?lang=en o ?lang=es
        if (request()->has('lang')) {
            $qLang = strtolower(trim(request()->query('lang')));
            if (in_array($qLang, ['en', 'es'])) {
                return $qLang;
            }
        }

        // 2. Cookie store_lang
        $cookieLang = request()->cookie('store_lang');
        if ($cookieLang) {
            $cookieLang = strtolower(trim($cookieLang));
            if (in_array($cookieLang, ['en', 'es'])) {
                return $cookieLang;
            }
        }

        // 3. Fallback a cookie googtrans (/es/en)
        $googtrans = request()->cookie('googtrans');
        if ($googtrans && str_contains($googtrans, '/en')) {
            return 'en';
        }

        return 'es';
    }

    /**
     * Retorna verdadero si el idioma actual es inglés.
     */
    public static function isEn(): bool
    {
        return static::currentLang() === 'en';
    }

    /**
     * Diccionario de categorías comerciales (Español => Inglés).
     */
    protected static array $categoriesEsToEn = [
        'limpieza'              => 'Cleaning',
        'belleza'               => 'Beauty',
        'hogar'                 => 'Home & Living',
        'productos destacados'  => 'Featured Products',
        'producto destacado'    => 'Featured Product',
        'ropa'                  => 'Clothing',
        'calzado'               => 'Footwear',
        'calzados'              => 'Footwear',
        'zapatos'               => 'Shoes',
        'tecnología'            => 'Technology',
        'tecnologia'            => 'Technology',
        'accesorios'            => 'Accessories',
        'salud'                 => 'Health & Wellness',
        'salud y belleza'       => 'Health & Beauty',
        'deportes'              => 'Sports & Outdoors',
        'deporte'               => 'Sports',
        'joyería'               => 'Jewelry',
        'joyeria'               => 'Jewelry',
        'alimentos'             => 'Food & Groceries',
        'comida'                => 'Food',
        'bebidas'               => 'Beverages',
        'mascotas'              => 'Pets',
        'juguetes'              => 'Toys',
        'automotriz'            => 'Automotive',
        'librería'              => 'Books & Stationery',
        'libreria'              => 'Books & Stationery',
        'moda'                  => 'Fashion',
        'electrodomésticos'     => 'Home Appliances',
        'electrodomesticos'     => 'Home Appliances',
        'cocina'                => 'Kitchen',
        'herramientas'          => 'Tools',
        'bebés'                 => 'Baby & Kids',
        'bebes'                 => 'Baby & Kids',
        'muebles'               => 'Furniture',
        'jardín'                => 'Garden & Outdoor',
        'jardin'                => 'Garden & Outdoor',
        'oficina'               => 'Office Supplies',
        'ferretería'            => 'Hardware',
        'ferreteria'            => 'Hardware',
        'electrónica'           => 'Electronics',
        'electronica'           => 'Electronics',
        'cuidado personal'      => 'Personal Care',
        'perfumería'            => 'Perfumes & Fragrances',
        'perfumeria'            => 'Perfumes & Fragrances',
        'instrumentos'          => 'Musical Instruments',
        'arte'                  => 'Arts & Crafts',
        'prendas'               => 'Apparel',
        'celulares'             => 'Phones & Tablets',
        'computación'           => 'Computers & Laptops',
        'computacion'           => 'Computers & Laptops',
        'audio'                 => 'Audio & Sound',
        'videojuegos'           => 'Video Games',
        'general'               => 'General',
        'destacados'            => 'Featured',
        'ofertas'               => 'Deals & Offers',
        'novedades'             => 'New Arrivals',
        'outlet'                => 'Outlet',
        'esenciales'            => 'Essentials',
        'deco'                  => 'Home Decor',
        'por mayor'             => 'Wholesale',
    ];

    /**
     * Diccionario invertido (Inglés => Español) para tiendas con categorías creadas en inglés.
     */
    protected static array $categoriesEnToEs = [
        'cleaning'              => 'Limpieza',
        'beauty'                => 'Belleza',
        'home & living'         => 'Hogar',
        'home'                  => 'Hogar',
        'featured products'     => 'Productos Destacados',
        'clothing'              => 'Ropa',
        'footwear'              => 'Calzado',
        'technology'            => 'Tecnología',
        'accessories'           => 'Accesorios',
        'health & wellness'     => 'Salud',
        'health'                => 'Salud',
        'sports & outdoors'     => 'Deportes',
        'sports'                => 'Deportes',
        'jewelry'               => 'Joyería',
        'food & groceries'      => 'Alimentos',
        'food'                  => 'Alimentos',
        'beverages'             => 'Bebidas',
        'pets'                  => 'Mascotas',
        'toys'                  => 'Juguetes',
        'automotive'            => 'Automotriz',
        'books & stationery'    => 'Librería',
        'fashion'               => 'Moda',
        'home appliances'       => 'Electrodomésticos',
        'appliances'            => 'Electrodomésticos',
        'kitchen'               => 'Cocina',
        'tools'                 => 'Herramientas',
        'baby & kids'           => 'Bebés',
        'baby'                  => 'Bebés',
        'furniture'             => 'Muebles',
        'garden & outdoor'      => 'Jardín',
        'garden'                => 'Jardín',
        'office supplies'       => 'Oficina',
        'hardware'              => 'Ferretería',
        'electronics'           => 'Electrónica',
        'personal care'         => 'Cuidado Personal',
        'perfumes & fragrances' => 'Perfumería',
        'musical instruments'   => 'Instrumentos',
        'arts & crafts'         => 'Arte',
        'phones & tablets'      => 'Celulares',
        'computers & laptops'   => 'Computación',
        'audio & sound'         => 'Audio',
        'video games'           => 'Videojuegos',
        'new arrivals'          => 'Novedades',
        'essentials'            => 'Esenciales',
        'home decor'            => 'Deco',
    ];

    /**
     * Diccionario de textos de Interfaz (UI).
     */
    protected static array $ui = [
        'es' => [
            'home'                => 'Inicio',
            'shop'                => 'Catálogo',
            'catalog'             => 'Catálogo',
            'contact'             => 'Contacto',
            'gallery'             => 'Galería',
            'my_account'          => 'Mi Cuenta / Pedidos',
            'search_placeholder'  => 'Buscar productos, marcas o categorías...',
            'search_products'     => 'Buscar productos...',
            'featured_categories' => 'Categorías destacadas',
            'featured_products'   => 'Productos destacados',
            'best_selling'        => 'Los más vendidos',
            'our_collection'      => 'Nuestra Colección',
            'collection_desc'     => 'Explora nuestra colección y encuentra piezas únicas para ti.',
            'all_products'        => 'Todos los Productos',
            'view_all'            => 'Ver todo',
            'view_details'        => 'Ver Detalles',
            'add_to_cart'         => 'Agregar al Carrito',
            'buy_now'             => 'Comprar Ahora',
            'special_offer'       => 'Oferta Especial',
            'filters'             => 'Filtros',
            'categories'          => 'Categorías',
            'brands'              => 'Marcas',
            'price'               => 'Precio',
            'sort_by'             => 'Ordenar por',
            'in_stock'            => 'Solo en stock',
            'out_of_stock'        => 'Agotado',
            'quantity'            => 'Cantidad',
            'description'         => 'Descripción',
            'specifications'      => 'Especificaciones',
            'shipping_returns'    => 'Envío y Devoluciones',
            'related_products'    => 'Productos Relacionados',
            'your_cart'           => 'Tu Carrito',
            'empty_cart'          => 'Tu carrito está vacío',
            'subtotal'            => 'Subtotal',
            'shipping'            => 'Envío',
            'total'               => 'Total',
            'checkout'            => 'Proceder al Pago',
            'order_whatsapp'      => 'Finalizar por WhatsApp',
            'fast_shipping'       => 'Envío Rápido',
            'secure_payment'      => 'Pago 100% Seguro',
            'quality_guarantee'   => 'Garantía de Calidad',
            'support_247'         => 'Atención Personalizada',
            'shop_now'            => 'Comprar Ahora',
            'new'                 => 'Nuevo',
            'outlet'              => 'Outlet',
            'essentials'          => 'Esenciales',
            'back_in_stock'       => 'De regreso',
            'deco'                => 'Deco',
            'wholesale'           => 'Por mayor',
        ],
        'en' => [
            'home'                => 'Home',
            'shop'                => 'Shop',
            'catalog'             => 'Catalog',
            'contact'             => 'Contact',
            'gallery'             => 'Gallery',
            'my_account'          => 'My Account / Orders',
            'search_placeholder'  => 'Search products, brands or categories...',
            'search_products'     => 'Search products...',
            'featured_categories' => 'Featured Categories',
            'featured_products'   => 'Featured Products',
            'best_selling'        => 'Best Selling',
            'our_collection'      => 'Our Collection',
            'collection_desc'     => 'Explore our collection and find unique pieces for you.',
            'all_products'        => 'All Products',
            'view_all'            => 'View All',
            'view_details'        => 'View Details',
            'add_to_cart'         => 'Add to Cart',
            'buy_now'             => 'Buy Now',
            'special_offer'       => 'Special Offer',
            'filters'             => 'Filters',
            'categories'          => 'Categories',
            'brands'              => 'Brands',
            'price'               => 'Price',
            'sort_by'             => 'Sort by',
            'in_stock'            => 'In stock only',
            'out_of_stock'        => 'Out of stock',
            'quantity'            => 'Quantity',
            'description'         => 'Description',
            'specifications'      => 'Specifications',
            'shipping_returns'    => 'Shipping & Returns',
            'related_products'    => 'Related Products',
            'your_cart'           => 'Your Cart',
            'empty_cart'          => 'Your cart is empty',
            'subtotal'            => 'Subtotal',
            'shipping'            => 'Shipping',
            'total'               => 'Total',
            'checkout'            => 'Proceed to Checkout',
            'order_whatsapp'      => 'Finish via WhatsApp',
            'fast_shipping'       => 'Fast Delivery',
            'secure_payment'      => '100% Secure Payment',
            'quality_guarantee'   => 'Quality Guarantee',
            'support_247'         => 'Dedicated Support',
            'shop_now'            => 'Shop Now',
            'new'                 => 'New',
            'outlet'              => 'Outlet',
            'essentials'          => 'Essentials',
            'back_in_stock'       => 'Back in stock',
            'deco'                => 'Deco',
            'wholesale'           => 'Wholesale',
        ],
    ];

    /**
     * Traduce el nombre de una categoría según el idioma actual.
     */
    public static function transCategory(?string $name, ?string $lang = null): string
    {
        if (!$name) {
            return '';
        }

        $targetLang = $lang ? strtolower(trim($lang)) : static::currentLang();
        $normalized = mb_strtolower(trim($name), 'UTF-8');

        if ($targetLang === 'en') {
            return static::$categoriesEsToEn[$normalized] ?? $name;
        }

        // Si es español y la categoría está en inglés
        return static::$categoriesEnToEs[$normalized] ?? $name;
    }

    /**
     * Traduce una clave o frase de la interfaz según el idioma actual.
     */
    public static function trans(string $key, ?string $default = null, ?string $lang = null): string
    {
        $targetLang = $lang ? strtolower(trim($lang)) : static::currentLang();
        $normKey = strtolower(trim($key));

        if (isset(static::$ui[$targetLang][$normKey])) {
            return static::$ui[$targetLang][$normKey];
        }

        // Búsqueda inversa o directa
        if ($targetLang === 'en') {
            foreach (static::$ui['es'] as $k => $val) {
                if (mb_strtolower($val, 'UTF-8') === mb_strtolower($key, 'UTF-8')) {
                    return static::$ui['en'][$k] ?? ($default ?? $key);
                }
            }
        } else {
            foreach (static::$ui['en'] as $k => $val) {
                if (mb_strtolower($val, 'UTF-8') === mb_strtolower($key, 'UTF-8')) {
                    return static::$ui['es'][$k] ?? ($default ?? $key);
                }
            }
        }

        return $default ?? $key;
    }
}
