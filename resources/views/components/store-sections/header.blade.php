<!-- Wrapper for live preview -->
<div data-section-id="{{ $section->id ?? '' }}">
    @php
        $headerBg = $data['background_color'] ?? '#FFFFFF';
    @endphp
    
    <header style="background-color: {{ $headerBg }};" class="sticky top-0 z-40 shadow-sm relative w-full overflow-hidden">
        @if(!empty($data['blocks']))
            @include('components.store-sections.blocks', ['data' => $data])
        @endif
    </header>
</div>
