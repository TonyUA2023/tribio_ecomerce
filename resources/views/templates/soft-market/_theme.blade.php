{{-- Resolved theme for this store (see App\Services\Storefront\StorefrontTheme). Every
     value printed here is a validated hex or a whitelisted font, never raw owner input. --}}
@php $themeFont = $storefrontTheme->headingFont(); @endphp
@if($themeFont['href'] && !in_array($themeFont['key'], ['fredoka', 'jakarta'], true))
    <link href="{{ $themeFont['href'] }}" rel="stylesheet" data-tpl-font>
@endif
<style>
    :root { {!! $storefrontTheme->cssVariables() !!} }
</style>
