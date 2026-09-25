@php($__marketing = app(\App\Services\Marketing\Meta\StorefrontTracking::class)->forView($store ?? null, $product ?? null))
@if($__marketing)
@if($__marketing['meta_verification'])
    <meta name="facebook-domain-verification" content="{{ $__marketing['meta_verification'] }}">
@endif
@if($__marketing['google_verification'])
    <meta name="google-site-verification" content="{{ $__marketing['google_verification'] }}">
@endif
@if($__marketing['config'])
    <script>window.__tribioMarketing = @json($__marketing['config']);</script>
@endif
@if($__marketing['structured_data'])
    <script type="application/ld+json">{!! json_encode($__marketing['structured_data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endif
@endif
