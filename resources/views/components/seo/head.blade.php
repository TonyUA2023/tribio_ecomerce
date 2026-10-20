{{-- SEO y vista previa al compartir (Google, WhatsApp, Facebook): título, descripción, dirección
     canónica e imagen de la tienda. Reemplaza al <title> de cada plantilla. Los datos salen de
     App\Services\Seo\StoreSeo (campos "Título SEO" y "Descripción SEO" de Mi tienda y del producto). --}}
@php
    $__seo = app(\App\Services\Seo\StoreSeo::class)->forPage(
        request(), $store, $product ?? null,
        trim($__env->yieldContent('title')),
        (bool) ($templatePreview ?? false),
    );
@endphp
<title>{{ $__seo['title'] }}</title>
<meta name="description" content="{{ $__seo['description'] }}">
<link rel="canonical" href="{{ $__seo['canonical'] }}">
@if($__seo['robots'])
<meta name="robots" content="{{ $__seo['robots'] }}">
@endif
<meta property="og:site_name" content="{{ $__seo['site_name'] }}">
<meta property="og:type" content="{{ $__seo['type'] }}">
<meta property="og:title" content="{{ $__seo['title'] }}">
<meta property="og:description" content="{{ $__seo['description'] }}">
<meta property="og:url" content="{{ $__seo['canonical'] }}">
<meta property="og:locale" content="es_PE">
@if($__seo['image'])
<meta property="og:image" content="{{ $__seo['image'] }}">
@endif
@if($__seo['price'])
<meta property="product:price:amount" content="{{ $__seo['price'] }}">
<meta property="product:price:currency" content="{{ $__seo['currency'] }}">
@endif
<meta name="twitter:card" content="{{ $__seo['image'] ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $__seo['title'] }}">
<meta name="twitter:description" content="{{ $__seo['description'] }}">
@if($__seo['image'])
<meta name="twitter:image" content="{{ $__seo['image'] }}">
@endif
