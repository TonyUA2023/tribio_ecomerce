<section style="background-color: {{ $data['background_color'] ?? '#ffffff' }}; color: {{ $data['text_color'] ?? '#111827' }};" class="py-16">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        @if(!empty($data['title']))
            <h2 class="text-3xl font-extrabold tracking-tight mb-6">
                {{ $data['title'] }}
            </h2>
        @endif
        @if(!empty($data['content']))
            <div class="prose prose-lg mx-auto" style="color: {{ $data['text_color'] ?? '#111827' }}; opacity: 0.9;">
                {!! nl2br(e($data['content'])) !!}
            </div>
        @endif
        
        @include('components.store-sections.blocks')
    </div>
</section>
