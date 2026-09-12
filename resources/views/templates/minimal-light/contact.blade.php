@extends('templates.minimal-light.layout')

@section('title', 'Contacto | ' . $store->name)

@section('content')
<div class="bg-white" x-data="{ mobileMenuOpen: false, searchOpen: false }">
    <!-- Unified Header -->
    @include('templates.minimal-light.header')

    <main class="max-w-4xl mx-auto px-4 py-16">
        <div class="text-center mb-12">
            <h1 class="text-4xl  text-[#1A1A1A] mb-4">Ponte en contacto</h1>
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

    <!-- Footer -->
    <footer class="bg-[#FDF8EF] border-t border-stone-200/60 py-8 text-center mt-12">
        <p class="text-xs font-semibold text-gray-500 tracking-wider">
            {{ \App\Helpers\TranslationHelper::isEn() ? 'Powered by' : 'Impulsado por' }} <span class="text-[#1A1A1A] font-bold">Tribio</span>
        </p>
    </footer>
</div>
@endsection
