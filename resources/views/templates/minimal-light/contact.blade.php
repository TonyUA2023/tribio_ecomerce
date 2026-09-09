@extends('templates.minimal-light.layout')

@section('title', 'Contacto | ' . $store->name)

@section('content')
<div class="bg-white" x-data="{ mobileMenuOpen: false, searchOpen: false }">
    <!-- Header -->
    <header class="bg-[#FDF8EF] border-b border-gray-200/50 shadow-sm" x-data="{ mobileMenuOpen: false, searchOpen: false }">
        <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-12 py-4 md:py-5">
            <!-- Top Row -->
            <div class="flex justify-between items-center">
                <!-- Left: Currency/Language -->
                <div class="flex-1 flex items-center space-x-4">
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" class="flex items-center text-xs font-bold text-gray-800 tracking-wider">
                            {{ request()->cookie('user_country') === 'US' ? 'USD' : 'PEN' }} <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>
                    </div>
                </div>

                <!-- Center: Logo -->
                <div class="flex-1 text-center">
                    <a href="{{ route('store.show', $store->slug) }}" class="inline-block">
                        @if($store->logo_path)
                            <img src="{{ $store->logo_url }}" alt="{{ $store->name }}" class="h-10 md:h-12 w-auto mx-auto object-contain">
                        @else
                            <span class="font-serif font-semibold text-2xl md:text-3xl tracking-wide text-[#1A1A1A]">{{ $store->name }}</span>
                        @endif
                    </a>
                </div>

                <!-- Right: Icons -->
                <div class="flex-1 flex items-center justify-end space-x-4 md:space-x-5">
                    <button @click="searchOpen = true" class="text-[#1A1A1A] hover:text-[#C8A68B] transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </button>
                </div>
            </div>

            <!-- Navigation -->
            <nav class="hidden md:flex justify-center space-x-10 mt-6 pb-2">
                <a href="{{ route('store.show', $store->slug) }}" class="text-[#1A1A1A] hover:text-[#C8A68B] font-medium text-sm transition">Home</a>
                <a href="{{ route('store.catalog', $store->slug) }}" class="text-[#1A1A1A] hover:text-[#C8A68B] font-medium text-sm transition">Shop</a>
                @foreach($categories->take(3) as $cat)
                    <a href="{{ route('store.catalog', ['slug' => $store->slug, 'category' => $cat->slug]) }}" class="text-[#1A1A1A] hover:text-[#C8A68B] font-medium text-sm transition">{{ $cat->name }}</a>
                @endforeach
                <a href="{{ route('store.contact', $store->slug) }}" class="text-[#C8A68B] font-bold text-sm transition">Contact</a>
            </nav>
        </div>

        <!-- Search Overlay -->
        <div x-show="searchOpen" style="display: none;" 
             class="absolute top-0 inset-x-0 bg-white border-b border-gray-100 shadow-2xl z-50 p-6 md:p-10">
            <div class="max-w-4xl mx-auto relative">
                <form action="{{ route('store.catalog', $store->slug) }}" method="GET" class="flex items-center">
                    <svg class="w-6 h-6 text-gray-400 absolute left-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    <input type="text" name="q" placeholder="Buscar productos..." class="w-full pl-14 pr-12 py-4 text-xl font-serif border-none rounded-full bg-gray-50 focus:ring-0" autofocus>
                </form>
                <button @click="searchOpen = false" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
        </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 py-16">
        <div class="text-center mb-12">
            <h1 class="text-4xl font-serif text-[#1A1A1A] mb-4">Ponte en contacto</h1>
            <p class="text-gray-600">¿Tienes alguna pregunta o comentario? Estaremos encantados de ayudarte.</p>
        </div>

        @if(session('success'))
            <div class="mb-8 p-4 bg-green-50 border border-green-200 text-green-700 rounded-xl text-center font-medium">
                {{ session('success') }}
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-3 gap-12">
            <!-- Info -->
            <div class="space-y-8">
                <div>
                    <h3 class="text-lg font-bold text-[#1A1A1A] mb-2">Correo Electrónico</h3>
                    <p class="text-gray-600">{{ $store->contact_email ?? 'hola@' . $store->slug . '.com' }}</p>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-[#1A1A1A] mb-2">Teléfono</h3>
                    <p class="text-gray-600">{{ $store->contact_phone ?? $store->whatsapp_number ?? 'No disponible' }}</p>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-[#1A1A1A] mb-2">Horario</h3>
                    <p class="text-gray-600">Lunes a Viernes<br>9:00 AM - 6:00 PM</p>
                </div>
            </div>

            <!-- Form -->
            <div class="md:col-span-2 bg-[#FDF8EF] p-8 rounded-2xl shadow-sm border border-gray-100">
                <form action="{{ route('store.contact.submit', $store->slug) }}" method="POST" class="space-y-6">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Nombre Completo</label>
                            <input type="text" name="name" required class="w-full bg-white border border-gray-200 rounded-xl px-4 py-3 focus:border-[#C8A68B] focus:ring-1 focus:ring-[#C8A68B] outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Correo Electrónico</label>
                            <input type="email" name="email" required class="w-full bg-white border border-gray-200 rounded-xl px-4 py-3 focus:border-[#C8A68B] focus:ring-1 focus:ring-[#C8A68B] outline-none">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Teléfono</label>
                            <input type="text" name="phone" class="w-full bg-white border border-gray-200 rounded-xl px-4 py-3 focus:border-[#C8A68B] focus:ring-1 focus:ring-[#C8A68B] outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Asunto</label>
                            <input type="text" name="subject" class="w-full bg-white border border-gray-200 rounded-xl px-4 py-3 focus:border-[#C8A68B] focus:ring-1 focus:ring-[#C8A68B] outline-none">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Mensaje</label>
                        <textarea name="message" rows="4" required class="w-full bg-white border border-gray-200 rounded-xl px-4 py-3 focus:border-[#C8A68B] focus:ring-1 focus:ring-[#C8A68B] outline-none"></textarea>
                    </div>

                    <button type="submit" class="w-full py-4 bg-[#1A1A1A] hover:bg-[#C8A68B] text-white font-bold rounded-xl transition-colors shadow-md">
                        Enviar Mensaje
                    </button>
                </form>
            </div>
        </div>
    </main>
</div>
@endsection
