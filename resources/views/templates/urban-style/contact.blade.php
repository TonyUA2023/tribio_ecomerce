@extends('templates.urban-style.layout')

@section('title', 'Contacto | ' . $store->name)

@section('content')
<div class="bg-white" x-data="{ mobileMenuOpen: false, searchOpen: false }">
    <!-- Unified Header -->
    @include('templates.urban-style.header')

    <main class="max-w-4xl mx-auto px-4 py-16">
        <div class="text-center mb-12">
            <h1 class="text-4xl font-bold text-[#1A1A1A] mb-4 font-brand">Ponte en contacto</h1>
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
                @if($store->contact_email)
                <div>
                    <h3 class="text-lg font-bold text-[#1A1A1A] mb-2 font-brand">Correo Electrónico</h3>
                    <a href="mailto:{{ $store->contact_email }}" class="text-gray-600 hover:text-[var(--t-primary)] break-all">{{ $store->contact_email }}</a>
                </div>
                @endif
                @if($store->contact_phone || $store->whatsapp_phone)
                <div>
                    <h3 class="text-lg font-bold text-[#1A1A1A] mb-2 font-brand">Teléfono</h3>
                    <p class="text-gray-600">{{ $store->contact_phone ?: $store->whatsapp_phone }}</p>
                </div>
                @endif
                <div>
                    <h3 class="text-lg font-bold text-[#1A1A1A] mb-2 font-brand">Horario</h3>
                    <p class="text-gray-600">Lunes a Viernes<br>9:00 AM - 6:00 PM</p>
                </div>
            </div>

            <!-- Form -->
            <div class="md:col-span-2 bg-[var(--t-bg)] p-8 rounded-2xl shadow-sm border border-gray-100">
                <form action="{{ route('store.contact.submit', $store->slug) }}" method="POST" class="space-y-6">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Nombre Completo</label>
                            <input type="text" name="name" required class="w-full bg-white border border-gray-200 rounded-xl px-4 py-3 focus:border-[var(--t-primary)] focus:ring-1 focus:ring-[var(--t-primary)] outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Correo Electrónico</label>
                            <input type="email" name="email" required class="w-full bg-white border border-gray-200 rounded-xl px-4 py-3 focus:border-[var(--t-primary)] focus:ring-1 focus:ring-[var(--t-primary)] outline-none">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Teléfono</label>
                            <input type="text" name="phone" class="w-full bg-white border border-gray-200 rounded-xl px-4 py-3 focus:border-[var(--t-primary)] focus:ring-1 focus:ring-[var(--t-primary)] outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Asunto</label>
                            <input type="text" name="subject" class="w-full bg-white border border-gray-200 rounded-xl px-4 py-3 focus:border-[var(--t-primary)] focus:ring-1 focus:ring-[var(--t-primary)] outline-none">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Mensaje</label>
                        <textarea name="message" rows="4" required class="w-full bg-white border border-gray-200 rounded-xl px-4 py-3 focus:border-[var(--t-primary)] focus:ring-1 focus:ring-[var(--t-primary)] outline-none"></textarea>
                    </div>

                    <button type="submit" class="w-full py-4 bg-[#1A1A1A] hover:bg-[var(--t-primary)] hover:text-[var(--t-on-primary)] text-white font-bold rounded-xl transition-colors shadow-md">
                        Enviar Mensaje
                    </button>
                </form>
            </div>
        </div>
    </main>

    <!-- Main Footer -->
    @include('templates.urban-style.footer')
</div>
@endsection
