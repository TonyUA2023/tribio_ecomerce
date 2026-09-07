@php
    $py = isset($data['padding_y']) ? "padding-top: {$data['padding_y']}px; padding-bottom: {$data['padding_y']}px;" : '';
    $px = isset($data['padding_x']) ? "padding-left: {$data['padding_x']}px; padding-right: {$data['padding_x']}px;" : '';
    $minH = isset($data['min_height']) ? "min-height: {$data['min_height']}px;" : '';
@endphp
<section class="tribio-custom-section relative w-full overflow-hidden" style="background-color: {{ $data['background_color'] ?? 'transparent' }}; {{ $py }} {{ $px }} {{ $minH }}">
    @include('components.store-sections.blocks', ['data' => $data, 'pathPrefix' => 'data.blocks.'])
</section>
