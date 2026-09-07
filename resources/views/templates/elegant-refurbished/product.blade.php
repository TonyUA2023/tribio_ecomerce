@extends('templates.elegant-refurbished.layout')

@section('title', $product->name ?? 'iPhone 13 Pro Reacondicionado')
@section('meta_description', 'Compra el ' . ($product->name ?? 'iPhone 13 Pro') . ' al mejor precio en Perú. Equipos probados, garantía de 12 meses y envío seguro.')

@section('content')
<div class="er-container er-section" style="padding-top: var(--space-md);">
    <div class="er-grid" style="grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); align-items: start;">
        
        <!-- Product Images -->
        <div style="background-color: var(--bg-secondary); border-radius: var(--radius-lg); padding: var(--space-lg); text-align: center;">
            @if(isset($product) && $product->image_url)
                <img src="{{ $product->image_url }}" onerror="this.onerror=null; this.src='{{ asset('img/dummy-iphone.png') }}';" alt="{{ $product->name }}" style="width: 100%; max-width: 400px; height: auto;">
            @else
                <!-- Dummy SVG for presentation -->
                <svg viewBox="0 0 200 250" fill="none" xmlns="http://www.w3.org/2000/svg" style="width: 100%; max-width: 300px; height: auto;">
                    <rect x="50" y="10" width="100" height="200" rx="20" fill="var(--bg-primary)" stroke="var(--border-color)" stroke-width="4"/>
                    <circle cx="100" cy="110" r="15" fill="var(--border-color)"/>
                    <rect x="90" y="25" width="20" height="5" rx="2" fill="var(--border-color)"/>
                </svg>
            @endif
        </div>

        <!-- Product Info -->
        <div>
            <span class="er-product-badge" style="margin-bottom: var(--space-sm);">Reacondicionado Premium</span>
            <h1 class="er-title-lg" style="margin-bottom: 0.5rem;">{{ $product->name ?? 'iPhone 13 Pro' }}</h1>
            <p class="er-subtitle" style="margin-bottom: var(--space-md); color: var(--er-accent); font-weight: 700; font-size: 1.5rem;">S/ {{ number_format($product->price ?? 2499, 2) }}</p>

            <form action="{{ route('store.checkout', ['slug' => $store->slug]) }}" method="POST">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id ?? 1 }}">
                
                <!-- Status Selector -->
                <div class="er-form-group" style="margin-bottom: var(--space-md);">
                    <label style="display: block; font-weight: 600; margin-bottom: 0.5rem;">Condición Estética</label>
                    <div style="display: flex; gap: var(--space-sm);">
                        <label style="flex: 1; border: 1px solid var(--er-accent); border-radius: var(--radius-sm); padding: 1rem; text-align: center; cursor: pointer; background-color: rgba(0, 113, 227, 0.05);">
                            <input type="radio" name="condition" value="excelente" checked class="er-sr-only">
                            <div style="font-weight: 600;">Excelente</div>
                            <div style="font-size: 0.8rem; color: var(--text-muted);">Sin rasguños visibles</div>
                        </label>
                        <label style="flex: 1; border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 1rem; text-align: center; cursor: pointer;">
                            <input type="radio" name="condition" value="bueno" class="er-sr-only">
                            <div style="font-weight: 600;">Muy Bueno</div>
                            <div style="font-size: 0.8rem; color: var(--text-muted);">Ligeras marcas de uso</div>
                        </label>
                    </div>
                </div>

                <!-- Storage Selector -->
                <div class="er-form-group" style="margin-bottom: var(--space-lg);">
                    <label style="display: block; font-weight: 600; margin-bottom: 0.5rem;">Almacenamiento</label>
                    <div style="display: flex; gap: var(--space-sm);">
                        <label style="border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 0.8rem 1.5rem; cursor: pointer;">
                            <input type="radio" name="storage" value="128" class="er-sr-only">
                            128 GB
                        </label>
                        <label style="border: 1px solid var(--er-accent); border-radius: var(--radius-sm); padding: 0.8rem 1.5rem; cursor: pointer; background-color: rgba(0, 113, 227, 0.05);">
                            <input type="radio" name="storage" value="256" checked class="er-sr-only">
                            256 GB
                        </label>
                    </div>
                </div>

                <button type="submit" class="er-btn er-btn-primary" style="width: 100%; font-size: 1.1rem; padding: 1rem;">
                    Comprar Ahora
                </button>
            </form>

            <div style="margin-top: var(--space-lg); border-top: 1px solid var(--border-color); padding-top: var(--space-md);">
                <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                    <div style="color: var(--er-accent);">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 2rem; height: 2rem;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 10.5h.375c.621 0 1.125.504 1.125 1.125v2.25c0 .621-.504 1.125-1.125 1.125H21M4.5 10.5h6.75V15H4.5v-4.5zM3 8.25h15c.828 0 1.5.672 1.5 1.5v4.5c0 .828-.672 1.5-1.5 1.5H3c-.828 0-1.5-.672-1.5-1.5v-4.5c0-.828.672-1.5 1.5-1.5z" />
                        </svg>
                    </div>
                    <div>
                        <div style="font-weight: 600; color: var(--text-main);">Salud de Batería Garantizada</div>
                        <div style="font-size: 0.9rem; color: var(--text-muted);">Mínimo 85% de capacidad asegurada.</div>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <div style="color: var(--er-accent);">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 2rem; height: 2rem;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                        </svg>
                    </div>
                    <div>
                        <div style="font-weight: 600; color: var(--text-main);">Envío Rápido y Seguro</div>
                        <div style="font-size: 0.9rem; color: var(--text-muted);">Recibe tu equipo en 24/48 horas.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
