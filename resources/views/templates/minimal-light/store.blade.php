@include('templates.elegant-dark.store', compact('store', 'allProducts', 'featuredProducts', 'categories', 'galleryItems'))

@if(request()->query('editor'))
            @include('components.store-sections.editor-scripts')
        @endif
