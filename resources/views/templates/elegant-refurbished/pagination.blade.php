@if ($paginator->hasPages())
    <nav style="display: flex; justify-content: center; align-items: center; gap: 0.5rem; margin-top: 2rem;">
        {{-- Previous Page Link --}}
        @if ($paginator->onFirstPage())
            <span style="padding: 0.5rem 1rem; background: var(--bg-secondary); color: var(--text-muted); border-radius: 4px; border: 1px solid var(--border-color); cursor: not-allowed; font-size: 0.9rem;">&laquo; Anterior</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" style="padding: 0.5rem 1rem; background: #fff; border: 1px solid var(--border-color); color: var(--text-main); border-radius: 4px; text-decoration: none; font-size: 0.9rem; transition: all 0.2s;" onmouseover="this.style.background='var(--bg-secondary)'" onmouseout="this.style.background='#fff'">&laquo; Anterior</a>
        @endif

        {{-- Pagination Elements --}}
        @foreach ($elements as $element)
            {{-- "Three Dots" Separator --}}
            @if (is_string($element))
                <span style="padding: 0.5rem 1rem; color: var(--text-muted);">{{ $element }}</span>
            @endif

            {{-- Array Of Links --}}
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span style="padding: 0.5rem 1rem; background: var(--er-accent); border: 1px solid var(--er-accent); color: #fff; border-radius: 4px; font-weight: bold; font-size: 0.9rem;">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" style="padding: 0.5rem 1rem; background: #fff; border: 1px solid var(--border-color); color: var(--text-main); border-radius: 4px; text-decoration: none; font-size: 0.9rem; transition: all 0.2s;" onmouseover="this.style.background='var(--bg-secondary)'" onmouseout="this.style.background='#fff'">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        {{-- Next Page Link --}}
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" style="padding: 0.5rem 1rem; background: #fff; border: 1px solid var(--border-color); color: var(--text-main); border-radius: 4px; text-decoration: none; font-size: 0.9rem; transition: all 0.2s;" onmouseover="this.style.background='var(--bg-secondary)'" onmouseout="this.style.background='#fff'">Siguiente &raquo;</a>
        @else
            <span style="padding: 0.5rem 1rem; background: var(--bg-secondary); color: var(--text-muted); border-radius: 4px; border: 1px solid var(--border-color); cursor: not-allowed; font-size: 0.9rem;">Siguiente &raquo;</span>
        @endif
    </nav>
@endif
