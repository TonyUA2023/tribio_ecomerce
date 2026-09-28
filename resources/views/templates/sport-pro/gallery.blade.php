@extends('templates.sport-pro.layout')

@php $isEn = \App\Helpers\TranslationHelper::isEn(); @endphp

@section('title', ($isEn ? 'Lookbook' : 'Galería') . ' | ' . $store->name)

@section('content')
<div class="sp-narrow">
    <nav class="sp-crumbs" aria-label="{{ $isEn ? 'Breadcrumb' : 'Ruta' }}">
        <a href="{{ route('store.show', $store->slug) }}">{{ $isEn ? 'Home' : 'Inicio' }}</a>
        <i aria-hidden="true"></i>
        <span>{{ $isEn ? 'Lookbook' : 'Galería' }}</span>
    </nav>
    <div class="sp-page-head">
        <h1>{{ $isEn ? 'Lookbook' : 'Galería' }}</h1>
        <p>{{ $isEn ? 'Our latest drops, in action.' : 'Nuestros últimos lanzamientos, en acción.' }}</p>
    </div>

    @if($galleryItems->isEmpty())
        <div class="sp-empty" style="margin: 30px 0 70px">
            <h2>{{ $isEn ? 'Coming soon' : 'Muy pronto' }}</h2>
            <p>{{ $isEn ? 'We are preparing new photos.' : 'Estamos preparando nuevas fotos.' }}</p>
            <a class="sp-btn" href="{{ route('store.catalog', $store->slug) }}">{{ $isEn ? 'Shop now' : 'Ver productos' }}</a>
        </div>
    @else
        <div class="sp-lookbook">
            @foreach($galleryItems as $item)
                @php $hasCaption = $item->title || $item->description; @endphp
                <figure class="sp-look" style="margin: 0 0 12px">
                    @if($item->link_url)<a href="{{ $item->link_url }}" style="display: block; color: inherit">@endif
                    <img src="{{ $item->image_url }}" alt="{{ $item->title ?: $store->name }}" loading="lazy">
                    @if($hasCaption)
                        <figcaption>
                            @if($item->title)<strong>{{ $item->title }}</strong>@endif
                            @if($item->description)<span>{{ $item->description }}</span>@endif
                        </figcaption>
                    @endif
                    @if($item->link_url)</a>@endif
                </figure>
            @endforeach
        </div>
        @if($galleryItems->hasPages())
            <nav class="sp-pager" style="margin: 0 0 60px">
                @if(!$galleryItems->onFirstPage())<a href="{{ $galleryItems->previousPageUrl() }}" rel="prev">‹</a>@endif
                <span class="is-current">{{ $galleryItems->currentPage() }}</span>
                @if($galleryItems->hasMorePages())<a href="{{ $galleryItems->nextPageUrl() }}" rel="next">›</a>@endif
            </nav>
        @endif
    @endif
</div>
@endsection
