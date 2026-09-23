@php($__marketing = app(\App\Services\Marketing\Meta\StorefrontTracking::class)->forView($store ?? null, $product ?? null))
@if($__marketing)
@if($__marketing['domain_verification'])
    <meta name="facebook-domain-verification" content="{{ $__marketing['domain_verification'] }}">
@endif
@if($__marketing['config'])
    <script>window.__tribioMarketing = @json($__marketing['config']);</script>
@endif
@endif
