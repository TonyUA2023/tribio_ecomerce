@extends('templates.elegant-refurbished.layout')

@section('title', 'Catálogo | ' . $store->name)

@section('content')
<!-- Hero Search Banner -->
<div style="background: url('{{ asset('img/hero-mac.png') }}') center/cover no-repeat; padding: 4rem 1rem; text-align: center; position: relative;">
    <div style="position: absolute; top:0; left:0; right:0; bottom:0; background: rgba(0,0,0,0.6);"></div>
    <div style="position: relative; z-index: 10; max-width: 800px; margin: 0 auto;">
        <h1 style="color: #fff; font-size: 2.5rem; margin-bottom: 1.5rem; font-weight: 700;">Encuentra tu próximo equipo Apple</h1>
        <form action="{{ route('store.catalog', ['slug' => $store->slug]) }}" method="GET" style="display: flex; gap: 0.5rem; background: #fff; padding: 0.5rem; border-radius: 50px; box-shadow: 0 10px 25px rgba(0,0,0,0.1);">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Buscar productos, marcas y más..." style="flex: 1; border: none; padding: 0.8rem 1.5rem; border-radius: 50px; outline: none; font-size: 1.1rem; color: #333;">
            <button type="submit" style="background: var(--er-accent); color: #fff; border: none; padding: 0 2rem; border-radius: 50px; cursor: pointer; font-weight: 600; font-size: 1rem;">Buscar</button>
        </form>
    </div>
</div>

<div class="er-container" style="max-width: 1300px; padding-top: var(--space-xl); padding-bottom: var(--space-xl);">
    
    <div style="display: flex; gap: 2rem; align-items: flex-start; flex-wrap: wrap;">
        <!-- Sidebar Filtros -->
        <aside style="flex: 1; min-width: 220px; max-width: 260px;">
            <h3 style="font-size: 1.2rem; margin-bottom: 1.5rem; color: var(--text-main); font-weight: 600;">Filtros</h3>
            
            <!-- Categorías -->
            <div style="margin-bottom: 2rem;">
                <h4 style="font-size: 1rem; margin-bottom: 0.8rem; font-weight: 600; color: var(--text-main);">Categorías</h4>
                <ul style="list-style: none; padding: 0; margin: 0;">
                    <li style="margin-bottom: 0.5rem;">
                        <a href="{{ route('store.catalog', ['slug' => $store->slug]) }}" style="color: {{ !request('category') ? 'var(--er-accent)' : 'var(--text-muted)' }}; text-decoration: none; font-size: 0.95rem;">Todas las categorías</a>
                    </li>
                    @foreach($categories as $cat)
                        <li style="margin-bottom: 0.5rem;">
                            <a href="{{ route('store.catalog', ['slug' => $store->slug, 'category' => $cat->slug]) }}" style="color: {{ request('category') == $cat->slug ? 'var(--er-accent)' : 'var(--text-muted)' }}; text-decoration: none; font-size: 0.95rem;">{{ $cat->name }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>

            <!-- Condición (Visual Only for Demo) -->
            <div style="margin-bottom: 2rem;">
                <h4 style="font-size: 1rem; margin-bottom: 0.8rem; font-weight: 600; color: var(--text-main);">Condición</h4>
                <ul style="list-style: none; padding: 0; margin: 0;">
                    <li style="margin-bottom: 0.5rem;"><a href="#" style="color: var(--text-muted); text-decoration: none; font-size: 0.95rem;">Como Nuevo</a></li>
                    <li style="margin-bottom: 0.5rem;"><a href="#" style="color: var(--text-muted); text-decoration: none; font-size: 0.95rem;">Excelente</a></li>
                    <li style="margin-bottom: 0.5rem;"><a href="#" style="color: var(--text-muted); text-decoration: none; font-size: 0.95rem;">Muy Bueno</a></li>
                </ul>
            </div>

            <!-- Precio -->
            <div style="margin-bottom: 2rem;">
                <h4 style="font-size: 1rem; margin-bottom: 0.8rem; font-weight: 600; color: var(--text-main);">Precio</h4>
                <form action="{{ route('store.catalog', ['slug' => $store->slug]) }}" method="GET" style="display: flex; align-items: center; gap: 0.5rem;">
                    @if(request('q')) <input type="hidden" name="q" value="{{ request('q') }}"> @endif
                    @if(request('category')) <input type="hidden" name="category" value="{{ request('category') }}"> @endif
                    <input type="number" name="min_price" placeholder="Mín." style="width: 70px; padding: 0.4rem; border: 1px solid var(--border-color); border-radius: 4px; outline: none; font-size: 0.9rem;">
                    <span style="color: var(--text-muted);">-</span>
                    <input type="number" name="max_price" placeholder="Máx." style="width: 70px; padding: 0.4rem; border: 1px solid var(--border-color); border-radius: 4px; outline: none; font-size: 0.9rem;">
                    <button type="submit" style="background: var(--text-muted); color: #fff; border: none; padding: 0.4rem; border-radius: 4px; cursor: pointer;">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width: 14px; height: 14px;">
                            <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </form>
            </div>
            
            @if(request()->anyFilled(['q', 'category', 'sort', 'min_price', 'max_price']))
                <a href="{{ route('store.catalog', ['slug' => $store->slug]) }}" style="display: inline-block; margin-top: 1rem; color: var(--er-accent); font-size: 0.9rem; text-decoration: none; font-weight: 500;">Limpiar Filtros</a>
            @endif
        </aside>

        <!-- Product Grid -->
        <div style="flex: 3; min-width: 0;">
            <!-- Header for grid (Sorting and counts) -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-color);">
                <div style="font-size: 0.95rem; color: var(--text-main); font-weight: 500;">
                    {{ $allProducts->total() }} resultados
                </div>
                <div>
                    <form action="{{ route('store.catalog', ['slug' => $store->slug]) }}" method="GET" style="display: flex; align-items: center; gap: 0.5rem;">
                        @if(request('q')) <input type="hidden" name="q" value="{{ request('q') }}"> @endif
                        @if(request('category')) <input type="hidden" name="category" value="{{ request('category') }}"> @endif
                        <span style="font-size: 0.9rem; color: var(--text-muted);">Ordenar por:</span>
                        <select name="sort" onchange="this.form.submit()" style="border: none; background: transparent; font-size: 0.9rem; font-weight: 600; color: var(--text-main); outline: none; cursor: pointer;">
                            <option value="position" {{ request('sort') == 'position' ? 'selected' : '' }}>Más relevantes</option>
                            <option value="price_asc" {{ request('sort') == 'price_asc' ? 'selected' : '' }}>Menor precio</option>
                            <option value="price_desc" {{ request('sort') == 'price_desc' ? 'selected' : '' }}>Mayor precio</option>
                        </select>
                    </form>
                </div>
            </div>

            <!-- Grid -->
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem;">
                @forelse($allProducts as $index => $product)
                    <a href="{{ route('store.product', ['slug' => $store->slug, 'product' => $product->slug]) }}" class="animate-on-scroll" style="animation-delay: {{ ($index % 4) * 0.1 }}s; background: #fff; border-radius: 8px; border: 1px solid var(--border-color); overflow: hidden; text-decoration: none; transition: box-shadow 0.2s, transform 0.2s; position: relative; display: flex; flex-direction: column;" onmouseover="this.style.boxShadow='0 8px 16px rgba(0,0,0,0.08)'; this.style.transform='translateY(-2px)'" onmouseout="this.style.boxShadow='none'; this.style.transform='none'">
                        
                        <div style="height: 180px; padding: 1.5rem; display: flex; align-items: center; justify-content: center; border-bottom: 1px solid #f9f9f9; background: #fff;">
                            <!-- Improved fallback using hero-iphone -->
                            <img src="{{ $product->image_url ?? asset('img/hero-iphone.png') }}" onerror="this.onerror=null; this.src='{{ asset('img/hero-iphone.png') }}';" alt="{{ $product->name }}" style="max-height: 100%; max-width: 100%; object-fit: contain;">
                        </div>
                        
                        <div style="padding: 1.2rem; flex: 1; display: flex; flex-direction: column;">
                            <div style="font-size: 1.4rem; font-weight: 400; color: var(--text-main); margin-bottom: 0.25rem;">S/ {{ number_format($product->price, 2) }}</div>
                            <div style="font-size: 0.8rem; color: #00a650; margin-bottom: 0.5rem; font-weight: 600;">Llega gratis mañana</div>
                            <h3 style="font-size: 0.9rem; font-weight: 400; color: var(--text-muted); line-height: 1.4; margin: 0; flex: 1;">{{ $product->name }}</h3>
                        </div>
                    </a>
                @empty
                    <div style="grid-column: 1 / -1; text-align: center; padding: 4rem; background: var(--bg-secondary); border-radius: var(--radius-md);">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 4rem; height: 4rem; color: var(--text-muted); margin: 0 auto 1rem auto;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                        </svg>
                        <h3 class="er-title-md" style="color: var(--text-main); margin-bottom: 0.5rem;">No encontramos coincidencias</h3>
                        <p style="color: var(--text-muted);">Revisa la ortografía o intenta usar términos más genéricos.</p>
                        <a href="{{ route('store.catalog', ['slug' => $store->slug]) }}" class="er-btn er-btn-primary" style="margin-top: 1rem;">Ver todo el catálogo</a>
                    </div>
                @endforelse
            </div>
            
            <div style="margin-top: var(--space-xl); display: flex; justify-content: center;">
                {{ $allProducts->links('templates.elegant-refurbished.pagination') }}
            </div>
        </div>
    </div>
</div>
@endsection
