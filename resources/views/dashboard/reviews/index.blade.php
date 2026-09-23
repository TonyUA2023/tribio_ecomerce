@extends('layouts.dashboard')
@section('title', 'Reseñas')
@section('page_title', '⭐ Reseñas')
@section('content')
@php
    $chipBase = 'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border text-xs font-semibold transition whitespace-nowrap';
    $chipOn   = 'bg-tribio-cyan/10 border-tribio-cyan/40 text-tribio-cyan';
    $chipOff  = 'bg-white/5 border-white/10 text-white/60 hover:border-white/20';
    // Query string of the current filters, with optional overrides (null clears one).
    $keep = fn (array $override = []) => array_filter(
        array_merge(['estado' => $status, 'calificacion' => $rating ?: null, 'producto' => $productId ?: null], $override),
        fn ($value) => $value !== null && $value !== ''
    );
    $stars = fn (int $n) => str_repeat('★', $n) . str_repeat('☆', 5 - $n);
    $filtered = $status || $rating || $productId;
@endphp

{{-- Resumen --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
    <div class="glass-card p-4">
        <p class="text-white/40 text-xs">Calificación promedio</p>
        <p class="text-white text-2xl font-black mt-1">
            {{ $stats['average'] !== null ? number_format($stats['average'], 1) : '—' }}
            <span class="text-tribio-gold text-base">★</span>
        </p>
        <p class="text-white/40 text-xs mt-1">de {{ $stats['published'] }} {{ $stats['published'] === 1 ? 'reseña visible' : 'reseñas visibles' }}</p>
    </div>
    <div class="glass-card p-4">
        <p class="text-white/40 text-xs">Reseñas en total</p>
        <p class="text-white text-2xl font-black mt-1">{{ $stats['total'] }}</p>
        <p class="text-white/40 text-xs mt-1">de clientes con compra verificada</p>
    </div>
    <a href="{{ route('dashboard.resenas.index', ['estado' => 'unanswered']) }}" class="glass-card p-4 block hover:bg-white/5 transition">
        <p class="text-white/40 text-xs">Sin responder</p>
        <p class="text-white text-2xl font-black mt-1">
            {{ $stats['unanswered'] }}
            @if($stats['unanswered'] > 0)<span class="badge badge-gold align-middle">Pendientes</span>@endif
        </p>
        <p class="text-white/40 text-xs mt-1">Responder genera confianza</p>
    </a>
    <div class="glass-card p-4">
        <p class="text-white/40 text-xs">Ocultas</p>
        <p class="text-white text-2xl font-black mt-1">{{ $stats['hidden'] }}</p>
        <p class="text-white/40 text-xs mt-1">no aparecen en tu tienda</p>
    </div>
</div>

@if($stats['total'] > 0)
{{-- Filtros --}}
<div class="flex flex-wrap gap-2 mb-3">
    <a href="{{ route('dashboard.resenas.index', $keep(['estado' => null])) }}" class="{{ $chipBase }} {{ !$status ? $chipOn : $chipOff }}">
        Todas <span class="opacity-70">{{ $stats['total'] }}</span>
    </a>
    <a href="{{ route('dashboard.resenas.index', $keep(['estado' => 'unanswered'])) }}" class="{{ $chipBase }} {{ $status === 'unanswered' ? $chipOn : $chipOff }}">
        Sin responder <span class="opacity-70">{{ $stats['unanswered'] }}</span>
    </a>
    <a href="{{ route('dashboard.resenas.index', $keep(['estado' => 'published'])) }}" class="{{ $chipBase }} {{ $status === 'published' ? $chipOn : $chipOff }}">
        Visibles <span class="opacity-70">{{ $stats['published'] }}</span>
    </a>
    <a href="{{ route('dashboard.resenas.index', $keep(['estado' => 'hidden'])) }}" class="{{ $chipBase }} {{ $status === 'hidden' ? $chipOn : $chipOff }}">
        Ocultas <span class="opacity-70">{{ $stats['hidden'] }}</span>
    </a>
</div>

<form method="GET" action="{{ route('dashboard.resenas.index') }}" class="flex flex-wrap items-center gap-2 mb-5">
    @if($status)<input type="hidden" name="estado" value="{{ $status }}">@endif
    <select name="calificacion" class="input-field text-sm" style="width:auto" onchange="this.form.submit()" aria-label="Filtrar por calificación">
        <option value="">Todas las calificaciones</option>
        @foreach([5, 4, 3, 2, 1] as $n)
            <option value="{{ $n }}" @selected($rating === $n)>{{ $n }} {{ $n === 1 ? 'estrella' : 'estrellas' }}</option>
        @endforeach
    </select>
    <select name="producto" class="input-field text-sm" style="width:auto;max-width:100%" onchange="this.form.submit()" aria-label="Filtrar por producto">
        <option value="">Todos los productos</option>
        @foreach($products as $product)
            <option value="{{ $product->id }}" @selected($productId === $product->id)>{{ \Illuminate\Support\Str::limit($product->name, 48) }}</option>
        @endforeach
    </select>
    <noscript><button type="submit" class="btn-primary text-xs">Filtrar</button></noscript>
    @if($filtered)
        <a href="{{ route('dashboard.resenas.index') }}" class="btn-ghost text-xs inline-flex">Quitar filtros</a>
    @endif
</form>
@endif

{{-- Lista --}}
@if($reviews->isEmpty())
    <div class="glass-card text-center py-14 px-6">
        <p class="text-5xl mb-4">⭐</p>
        @if($filtered)
            <p class="text-white font-bold">Ninguna reseña coincide con estos filtros</p>
            <p class="text-white/40 text-sm mt-1">Prueba con otra calificación o producto.</p>
            <a href="{{ route('dashboard.resenas.index') }}" class="btn-ghost mt-4 inline-flex">Ver todas las reseñas</a>
        @else
            <p class="text-white font-bold">Aún no tienes reseñas</p>
            <p class="text-white/50 text-sm mt-2 max-w-md mx-auto">
                Tus clientes podrán calificar un producto cuando su pedido llegue al estado <strong>Entregado</strong>.
                Marca tus pedidos como entregados y las reseñas aparecerán aquí y en la página de cada producto.
            </p>
            <a href="{{ route('dashboard.pedidos.index', ['status' => 'shipped']) }}" class="btn-secondary mt-4 inline-flex">Ver pedidos enviados</a>
        @endif
    </div>
@else
    <div class="space-y-4">
        @foreach($reviews as $review)
        @php
            $replyOpen = $errors->has('reply') && (int) old('review_id') === $review->id;
            $productPublicUrl = $review->product && !$review->product->trashed()
                ? route('store.product', ['slug' => $store->slug, 'product' => $review->product->slug])
                : null;
        @endphp
        <article id="resena-{{ $review->id }}" class="glass-card p-4 sm:p-5 {{ $review->isPublished() ? '' : 'opacity-80' }}" x-data="{ reply: {{ $replyOpen ? 'true' : 'false' }} }">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div class="min-w-0">
                    @if($productPublicUrl)
                        <a href="{{ $productPublicUrl }}#resenas" target="_blank" rel="noopener" class="text-white font-bold text-sm hover:underline">{{ $review->product->name }}</a>
                    @else
                        <p class="text-white font-bold text-sm">{{ $review->product?->name ?? 'Producto eliminado' }}</p>
                    @endif
                    <p class="text-white/40 text-xs">
                        {{ $review->created_at->locale('es')->translatedFormat('d M Y · H:i') }}@if($review->edited_at) · editada @endif
                    </p>
                </div>
                @if(!$review->isPublished())
                    <span class="badge badge-gray">Oculta</span>
                @elseif($review->hasReply())
                    <span class="badge badge-green">Respondida</span>
                @else
                    <span class="badge badge-gold">Sin responder</span>
                @endif
            </div>

            <div class="flex flex-wrap items-center mt-3" style="column-gap:.75rem;row-gap:.25rem">
                <span class="text-tribio-gold text-lg tracking-wide" role="img" aria-label="{{ $review->rating }} de 5 estrellas">{{ $stars($review->rating) }}</span>
                <span class="text-white font-semibold text-sm">{{ $review->reviewer_name }}</span>
                <span class="text-xs font-semibold" style="color:#047857">✓ Compra verificada</span>
            </div>

            @if($review->comment)
                <p class="text-white/80 text-sm mt-2" style="white-space:pre-line;overflow-wrap:anywhere">{{ $review->comment }}</p>
            @else
                <p class="text-white/40 text-sm mt-2 italic">Solo dejó la calificación, sin comentario.</p>
            @endif

            @if($review->hasReply())
                <div class="mt-3 rounded-xl border border-white/10 bg-white/5 p-3">
                    <p class="text-white/50 text-xs mb-1">
                        Tu respuesta pública
                        @if($review->store_replied_at) · {{ $review->store_replied_at->locale('es')->translatedFormat('d M Y') }}@endif
                    </p>
                    <p class="text-white/80 text-sm" style="white-space:pre-line;overflow-wrap:anywhere">{{ $review->store_reply }}</p>
                </div>
            @endif

            <div class="flex flex-wrap gap-2 mt-4">
                <button type="button" class="btn-secondary text-xs" @click="reply = !reply" :aria-expanded="reply.toString()">
                    💬 {{ $review->hasReply() ? 'Editar mi respuesta' : 'Responder públicamente' }}
                </button>
                @if($review->hasReply())
                    <form method="POST" action="{{ route('dashboard.resenas.reply.destroy', ['review' => $review->id] + request()->query()) }}"
                          onsubmit="return confirm('¿Quitar tu respuesta de esta reseña?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn-ghost text-xs">Quitar respuesta</button>
                    </form>
                @endif
                <form method="POST" action="{{ route('dashboard.resenas.visibility', ['review' => $review->id] + request()->query()) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn-ghost text-xs"
                            title="{{ $review->isPublished() ? 'Deja de mostrarla en tu tienda y de contarla en la calificación' : 'Vuelve a mostrarla en tu tienda' }}">
                        {{ $review->isPublished() ? '🙈 Ocultar de mi tienda' : '👁️ Volver a mostrar' }}
                    </button>
                </form>
            </div>

            <form x-show="reply" x-cloak method="POST" action="{{ route('dashboard.resenas.reply', ['review' => $review->id] + request()->query()) }}" class="mt-3 space-y-2">
                @csrf
                @method('PUT')
                <input type="hidden" name="review_id" value="{{ $review->id }}">
                <textarea name="reply" rows="3" maxlength="{{ \App\Models\ProductReview::MAX_REPLY }}" class="input-field text-sm"
                          placeholder="Escribe una respuesta amable y útil: la verán todos los visitantes del producto.">{{ $replyOpen ? old('reply') : $review->store_reply }}</textarea>
                @if($replyOpen)<p class="text-red-400 text-xs">{{ $errors->first('reply') }}</p>@endif
                <button type="submit" class="btn-primary text-xs">Publicar respuesta</button>
            </form>
        </article>
        @endforeach
    </div>

    @if($reviews->hasPages())
        <div class="mt-5">{{ $reviews->links() }}</div>
    @endif
@endif

<p class="text-white/40 text-xs mt-6">
    Las reseñas se publican al instante. Solo pueden dejarlas clientes con un pedido entregado de ese producto; puedes ocultar una si incumple las normas (spam, insultos, datos personales) y responder públicamente a cualquiera.
</p>
@endsection
