{{-- Wireframe stand-in for designs that can't be previewed live yet, painted with their own palette. --}}
@php
    [$mAccent, $mSecondary, $mBg, $mInk] = array_pad(array_values($swatches), 4, '#e7edf4');
@endphp
<div class="tpl-mockup" style="--m-accent: {{ $mAccent }}; --m-secondary: {{ $mSecondary }}; --m-bg: {{ $mBg }}; --m-ink: {{ $mInk }};" aria-hidden="true">
    <div class="tpl-mockup-nav"><i></i><b></b><b></b><b></b></div>
    <div class="tpl-mockup-hero"><span></span><span></span><em></em></div>
    <div class="tpl-mockup-grid"><i></i><i></i><i></i><i></i></div>
</div>
