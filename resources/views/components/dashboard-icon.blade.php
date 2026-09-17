@props(['name' => 'grid'])
@php
    $paths = [
        'grid' => 'M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z',
        'box' => 'm12 3 9 5-9 5-9-5 9-5z M3 8v9l9 5 9-5V8 M12 13v9 M7.5 5.5l9 5',
        'folder' => 'M3 7V4h6l2 3h10v13H3V7z',
        'tag' => 'M3 3h8l10 10-8 8L3 11V3z M7 7h.01',
        'image' => 'M3 3h18v18H3z M3 16l6-6 5 5 3-3 4 4 M16 7h.01',
        'cart' => 'M2 3h3l3 13h11l3-9H6 M9 21h.01 M18 21h.01',
        'inventory' => 'M8 4H4v17h16V4h-4 M8 2h8v5H8z M8 12h8 M8 16h5',
        'truck' => 'M2 4h12v13H2z M14 9h4l4 4v4h-8 M5 20a2 2 0 1 0 0-4 2 2 0 0 0 0 4 M18 20a2 2 0 1 0 0-4 2 2 0 0 0 0 4',
        'store' => 'M3 10v11h18V10 M2 10l2-7h16l2 7 M2 10c0 4 5 4 5 0 0 4 5 4 5 0 0 4 5 4 5 0 0 4 5 4 5 0 M9 21v-7h6v7',
        'external' => 'M14 3h7v7 M21 3 10 14 M10 3H3v18h18v-7',
        'logout' => 'M9 3H3v18h6 M9 12h12 M17 8l4 4-4 4',
        'menu' => 'M4 6h16 M4 12h16 M4 18h16',
        'close' => 'm6 6 12 12 M6 18 18 6',
        'search' => 'M10.5 18a7.5 7.5 0 1 0 0-15 7.5 7.5 0 0 0 0 15 M16 16l5 5',
        'chevron' => 'm9 5 7 7-7 7',
        'plus' => 'M12 5v14 M5 12h14',
        'wallet' => 'M3 6h17v15H3V6z M3 6V3h14v3 M15 11h7v5h-7z',
        'alert' => 'm12 3 10 18H2L12 3z M12 9v5 M12 17h.01',
        'check' => 'm5 12 4 4L19 6',
    ];
@endphp
<svg {{ $attributes->merge(['class' => 'dash-icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $paths[$name] ?? $paths['grid'] }}"/></svg>
