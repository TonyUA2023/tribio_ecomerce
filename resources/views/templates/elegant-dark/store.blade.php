{{-- Placeholder - Elegant Dark Store Template --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $store->name }} - Tienda Online</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root {
            --accent: {{ $store->accent_color }};
            --secondary: {{ $store->secondary_color }};
            --bg: {{ $store->bg_color }};
        }
    </style>
</head>
<body style="background: {{ $store->bg_color }}; font-family: 'Outfit', sans-serif; color: white; min-height: 100vh;">

    <!-- Dynamic Header will be rendered in the section loop -->

    @if(isset($sections) && $sections->isNotEmpty())
        @foreach($sections as $section)
            @if(View::exists("components.store-sections.{$section->type}"))
                <div class="tribio-section-wrapper relative group" data-section-id="{{ $section->id }}" id="section-{{ $section->id }}">
                    <div class="tribio-section-content">
                        @include("components.store-sections.{$section->type}", ['data' => $section->data])
                    </div>
                </div>
            @endif
        @endforeach
        
        @if(request()->query('editor'))
            <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
            @include('components.store-sections.editor-scripts')
        @endif
    @else
        {{-- Hero --}}
        <section class="relative py-24 text-center overflow-hidden"
                 style="background: linear-gradient(135deg, rgba(124,58,237,0.2) 0%, transparent 60%), {{ $store->bg_color }};">
            <div class="absolute inset-0" style="background-image: linear-gradient(rgba(255,255,255,0.02) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.02) 1px, transparent 1px); background-size: 50px 50px;"></div>
            <div class="relative z-10 container-tribio">
                @if($store->logo_path)
                <img src="{{ $store->logo_url }}" alt="{{ $store->name }}" class="h-24 w-auto mx-auto mb-6 object-contain">
                @else
                <div class="w-24 h-24 rounded-3xl mx-auto mb-6 flex items-center justify-center text-4xl"
                     style="background: {{ $store->accent_color }}22; border: 1px solid {{ $store->accent_color }}44;">
                    🏪
                </div>
                @endif
                <h1 class="text-5xl font-black text-white mb-4">{{ $store->name }}</h1>
                @if($store->tagline)
                <p class="text-xl text-white/60">{{ $store->tagline }}</p>
                @endif
            </div>
        </section>

        {{-- Products --}}
        <section class="py-16">
            <div class="container-tribio">
                <h2 class="text-2xl font-bold text-white mb-8">Nuestros Productos</h2>
                @if($allProducts->isEmpty())
                <p class="text-white/40 text-center py-12">Próximamente productos disponibles...</p>
                @else
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-5">
                    @foreach($allProducts as $product)
                    <div class="glass-card overflow-hidden group hover:border-white/20 transition-all cursor-pointer"
                         onclick="window.TribioCart && window.TribioCart.add({{ $product->id }}, '{{ addslashes($product->name) }}', {{ $product->price }}, '{{ $product->image_path ? $product->image_url : '' }}')">
                        <div class="aspect-square overflow-hidden bg-white/5">
                            @if($product->image_path)
                                <img src="{{ $product->image_url }}" alt="{{ $product->name }}"
                                     class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                            @else
                                <div class="w-full h-full flex items-center justify-center text-5xl">📦</div>
                            @endif
                        </div>
                        <div class="p-4">
                            <p class="text-white font-semibold text-sm leading-tight">{{ $product->name }}</p>
                            @if($product->short_description)
                            <p class="text-white/40 text-xs mt-1 line-clamp-2">{{ $product->short_description }}</p>
                            @endif
                            <div class="flex items-center justify-between mt-3">
                                <div>
                                    <span class="font-black text-lg" style="color: {{ $store->secondary_color }}">S/. {{ number_format($product->price, 2) }}</span>
                                    @if($product->compare_price)
                                    <span class="text-white/30 text-xs line-through ml-1">S/. {{ number_format($product->compare_price, 2) }}</span>
                                    @endif
                                </div>
                                <button class="w-8 h-8 rounded-full flex items-center justify-center text-white text-sm font-bold transition-all hover:scale-110"
                                        style="background: {{ $store->accent_color }}">+</button>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
        </section>
    @endif

    {{-- Cart Drawer --}}
    <div id="cartDrawer" x-data="{
             checkoutStep: 1,
             customer: { name: '', phone: '', address: '', notes: '', express_shipping: false },
             storeSlug: '{{ $store->slug }}',
             isExpressEnabled: {{ $store->is_express_shipping_enabled ? 'true' : 'false' }},
             expressCost: {{ $store->express_shipping_cost ?? 0 }},
             get cartItems() { return window.TribioCart ? window.TribioCart.items : []; },
             get cartTotal() { 
                 let total = this.cartItems.reduce((acc, item) => acc + (item.price * item.quantity), 0);
                 if (this.customer.express_shipping) total += this.expressCost;
                 return total;
             },
             submitOrder() {
                 if(!this.customer.name || !this.customer.phone) {
                     alert('Por favor completa los campos obligatorios (Nombre y Teléfono).');
                     return;
                 }
                 if(window.TribioCart) {
                     const btn = document.getElementById('btnSubmitOrder');
                     btn.innerText = 'Procesando...';
                     btn.disabled = true;
                     window.TribioCart.checkout(this.storeSlug, this.customer);
                 }
             }
         }"
         @cart-updated.window="$forceUpdate()"
         style="display:none; position: fixed; inset: 0; z-index: 999; justify-content: flex-end;">
        <div style="background: rgba(0,0,0,0.5);" class="absolute inset-0" onclick="document.getElementById('cartDrawer').style.display='none'"></div>
        <div class="relative w-full max-w-md h-full flex flex-col" style="background: #1A1A2E; border-left: 1px solid rgba(255,255,255,0.1);">
            <div class="flex items-center justify-between p-5 border-b" style="border-color: rgba(255,255,255,0.1);">
                <h3 class="text-white font-bold text-lg" x-text="checkoutStep === 1 ? '🛒 Mi carrito' : 'Finalizar Compra'"></h3>
                <button onclick="document.getElementById('cartDrawer').style.display='none'" class="text-white/50 hover:text-white">✕</button>
            </div>
            
            <div class="flex-1 p-5 overflow-y-auto">
                <template x-if="cartItems.length === 0">
                    <p class="text-white/40 text-sm text-center mt-8">Tu carrito está vacío.</p>
                </template>

                <template x-if="cartItems.length > 0 && checkoutStep === 1">
                    <div class="space-y-4">
                        <template x-for="(item, index) in cartItems" :key="index">
                            <div class="flex gap-4 p-3 bg-white/5 rounded-xl border border-white/10 items-center">
                                <template x-if="item.image">
                                    <img :src="item.image" class="w-16 h-16 object-cover rounded-lg">
                                </template>
                                <template x-if="!item.image">
                                    <div class="w-16 h-16 bg-white/10 rounded-lg flex items-center justify-center text-xl">📦</div>
                                </template>
                                <div class="flex-1">
                                    <h4 class="text-white font-semibold text-sm leading-tight" x-text="item.name"></h4>
                                    <div class="flex justify-between items-center mt-2">
                                        <p class="text-tribio-cyan font-bold text-sm" x-text="'S/. ' + (item.price * item.quantity).toFixed(2)"></p>
                                        <div class="flex items-center gap-2 text-white text-xs">
                                            <button @click="window.TribioCart.updateQuantity(item.id, item.quantity - 1); $dispatch('cart-updated')" class="w-6 h-6 rounded-full bg-white/10 hover:bg-white/20">-</button>
                                            <span x-text="item.quantity" class="w-4 text-center"></span>
                                            <button @click="window.TribioCart.updateQuantity(item.id, item.quantity + 1); $dispatch('cart-updated')" class="w-6 h-6 rounded-full bg-white/10 hover:bg-white/20">+</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>

                <template x-if="cartItems.length > 0 && checkoutStep === 2">
                    <div class="space-y-4 text-white">
                        <div>
                            <label class="block text-xs font-bold text-white/70 mb-1">Nombre Completo *</label>
                            <input type="text" x-model="customer.name" class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-2.5 text-sm focus:border-tribio-cyan focus:ring-1 focus:ring-tribio-cyan outline-none transition" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-white/70 mb-1">Teléfono (WhatsApp) *</label>
                            <input type="text" x-model="customer.phone" class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-2.5 text-sm focus:border-tribio-cyan focus:ring-1 focus:ring-tribio-cyan outline-none transition" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-white/70 mb-1">Dirección de Envío</label>
                            <input type="text" x-model="customer.address" class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-2.5 text-sm focus:border-tribio-cyan focus:ring-1 focus:ring-tribio-cyan outline-none transition">
                        </div>
                        
                        <template x-if="isExpressEnabled">
                            <div class="p-4 bg-tribio-cyan/10 border border-tribio-cyan/30 rounded-xl mt-4">
                                <label class="flex items-start gap-3 cursor-pointer">
                                    <input type="checkbox" x-model="customer.express_shipping" class="mt-1 accent-tribio-cyan w-4 h-4 rounded">
                                    <div>
                                        <p class="font-bold text-sm text-tribio-cyan flex items-center gap-1">🚀 ¡Quiero Envío Express!</p>
                                        <p class="text-xs text-white/70 mt-1">Llega más rápido a tu domicilio. <span x-show="expressCost > 0" x-text="'+ S/. ' + expressCost.toFixed(2)"></span><span x-show="expressCost == 0">¡Es gratis!</span></p>
                                    </div>
                                </label>
                            </div>
                        </template>
                        
                        <div>
                            <label class="block text-xs font-bold text-white/70 mb-1 mt-2">Notas adicionales</label>
                            <textarea x-model="customer.notes" rows="2" class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-2 text-sm focus:border-tribio-cyan focus:ring-1 focus:ring-tribio-cyan outline-none transition"></textarea>
                        </div>
                    </div>
                </template>
            </div>
            
            <template x-if="cartItems.length > 0">
                <div class="p-5 border-t" style="border-color: rgba(255,255,255,0.1);">
                    <div class="flex justify-between items-center mb-4 text-white">
                        <span class="font-bold text-white/70">Total a pagar:</span>
                        <span class="font-black text-xl" x-text="'S/. ' + cartTotal.toFixed(2)"></span>
                    </div>
                    
                    <template x-if="checkoutStep === 1">
                        <button @click="checkoutStep = 2" class="w-full py-3 rounded-xl font-bold text-white transition-all shadow-lg shadow-tribio-cyan/20 hover:-translate-y-0.5 text-center flex items-center justify-center gap-2" style="background: var(--accent, #7c3aed)">
                            Siguiente Paso →
                        </button>
                    </template>
                    
                    <template x-if="checkoutStep === 2">
                        <div class="flex gap-2">
                            <button @click="checkoutStep = 1" class="w-12 h-12 flex-shrink-0 flex items-center justify-center rounded-xl bg-white/10 hover:bg-white/20 text-white transition-colors">
                                ←
                            </button>
                            <button id="btnSubmitOrder" @click="submitOrder()" class="flex-1 py-3 rounded-xl font-bold text-white transition-all shadow-lg text-center" style="background: var(--accent, #7c3aed)">
                                Confirmar y Pagar
                            </button>
                        </div>
                    </template>
                </div>
            </template>
        </div>
    </div>

    <!-- Dynamic Footer will be rendered in the section loop -->

</body>
</html>
