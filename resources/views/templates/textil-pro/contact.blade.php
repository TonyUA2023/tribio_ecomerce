@extends('templates.textil-pro.layout')

@php
    $isEn = \App\Helpers\TranslationHelper::isEn();
    $libro = request()->boolean('libro');
    $phone = $store->contact_phone ?: $store->whatsapp_phone;
    $phoneClean = preg_replace('/[^0-9]/', '', (string) $phone);
    $email = $store->contact_email ?: $store->email;
@endphp

@section('title', ($libro ? ($isEn ? 'Complaints book' : 'Libro de Reclamaciones') : ($isEn ? 'Contact' : 'Contacto')) . ' | ' . $store->name)

@section('content')
<div class="tx-narrow">
    <nav class="tx-crumbs" aria-label="{{ $isEn ? 'Breadcrumb' : 'Ruta' }}">
        <a href="{{ route('store.show', $store->slug) }}">{{ $isEn ? 'Home' : 'Inicio' }}</a>
        <i aria-hidden="true"></i>
        <span>{{ $libro ? ($isEn ? 'Complaints book' : 'Libro de Reclamaciones') : ($isEn ? 'Contact' : 'Contacto') }}</span>
    </nav>

    <div class="tx-page-head">
        <h1>{{ $libro ? ($isEn ? 'Complaints book' : 'Libro de Reclamaciones') : ($isEn ? 'Contact us' : 'Contáctanos') }}</h1>
        <p>{{ $libro
            ? ($isEn ? 'Register a claim or complaint about a product or our service. It goes straight to the store.' : 'Registra aquí un reclamo o una queja sobre un producto o nuestra atención. Llega directamente a la tienda.')
            : ($isEn ? 'Questions about sizes, orders or exchanges? Write to us and we will get back to you.' : '¿Dudas con tallas, pedidos o cambios? Escríbenos y te responderemos.') }}</p>
    </div>

    <div class="tx-tabs" style="margin-top: 24px">
        <a href="{{ route('store.contact', $store->slug) }}" class="{{ !$libro ? 'is-active' : '' }}">{{ $isEn ? 'Contact' : 'Contacto' }}</a>
        <a href="{{ route('store.contact', ['slug' => $store->slug, 'libro' => 1]) }}" class="{{ $libro ? 'is-active' : '' }}">{{ $isEn ? 'Complaints book' : 'Libro de Reclamaciones' }}</a>
    </div>

    <div class="tx-contact" style="padding-top: 0">
        <aside class="tx-contact-info">
            <div>
                <h3>{{ $isEn ? 'Store' : 'Tienda' }}</h3>
                <p>{{ $store->name }}</p>
                @if($store->address || $store->city)<p style="color: var(--tx-muted)">{{ trim($store->address . ($store->city ? ', ' . $store->city : ''), ', ') }}</p>@endif
            </div>
            @if($email)
                <div><h3>{{ $isEn ? 'Email' : 'Correo' }}</h3><a href="mailto:{{ $email }}">{{ $email }}</a></div>
            @endif
            @if($phone)
                <div>
                    <h3>{{ $isEn ? 'Phone / WhatsApp' : 'Teléfono / WhatsApp' }}</h3>
                    @if($phoneClean)<a href="https://wa.me/{{ $phoneClean }}" target="_blank" rel="noopener">{{ $phone }}</a>@else<p>{{ $phone }}</p>@endif
                </div>
            @endif
            <div>
                <h3>{{ $isEn ? 'Your orders' : 'Tus pedidos' }}</h3>
                <button type="button" class="tx-btn is-outline" @click="window.openCustomerModal ? window.openCustomerModal('orders') : $dispatch('open-customer-modal')">{{ $isEn ? 'Track my order' : 'Rastrear mi pedido' }}</button>
            </div>
        </aside>

        <div>
            @if(session('success'))
                <div class="tx-alert" role="status" style="margin-bottom: 20px">
                    {{ $libro
                        ? ($isEn ? 'Your claim was sent to the store. They will reply to your email.' : 'Tu reclamo fue enviado a la tienda. Te responderán al correo que registraste.')
                        : session('success') }}
                </div>
            @endif
            @if($errors->any())
                <div class="tx-alert is-error" role="alert" style="margin-bottom: 20px">
                    @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            @if($libro)
                {{-- Libro de Reclamaciones virtual: se envía como mensaje a la tienda con todos los datos. --}}
                <form class="tx-form" method="POST" action="{{ route('store.contact.submit', $store->slug) }}"
                      x-data="{ tipo: 'Reclamo', bien: 'Producto', f: { nombre: '', doc_tipo: 'DNI', doc: '', domicilio: '', telefono: '', email: '', menor: '', monto: '', descripcion: '', pedido_nro: '', detalle: '', pedido: '' } }"
                      @submit="$refs.message.value = [
                          'LIBRO DE RECLAMACIONES — ' + tipo.toUpperCase(),
                          'Fecha: ' + new Date().toLocaleString(),
                          '',
                          '1. Consumidor',
                          'Nombre: ' + f.nombre,
                          'Documento: ' + f.doc_tipo + ' ' + f.doc,
                          'Domicilio: ' + f.domicilio,
                          'Teléfono: ' + f.telefono,
                          'Correo: ' + f.email,
                          f.menor ? 'Padre/madre o apoderado (menor de edad): ' + f.menor : '',
                          '',
                          '2. Bien contratado: ' + bien,
                          'Monto: ' + f.monto,
                          'N.º de pedido: ' + f.pedido_nro,
                          'Descripción: ' + f.descripcion,
                          '',
                          '3. Detalle del ' + tipo.toLowerCase() + ':',
                          f.detalle,
                          '',
                          'Pedido del consumidor:',
                          f.pedido,
                      ].filter((line) => line !== null).join('\n')">
                    @csrf
                    <input type="hidden" name="name" :value="f.nombre">
                    <input type="hidden" name="email" :value="f.email">
                    <input type="hidden" name="phone" :value="f.telefono">
                    <input type="hidden" name="subject" :value="'Libro de Reclamaciones — ' + tipo">
                    <textarea name="message" x-ref="message" hidden></textarea>

                    <fieldset style="border: 0; padding: 0; margin: 0; display: grid; gap: 10px">
                        <legend class="tx-label">{{ $isEn ? 'Type' : 'Tipo' }}</legend>
                        <div class="tx-radio-row">
                            <label class="tx-radio"><input type="radio" value="Reclamo" x-model="tipo"> {{ $isEn ? 'Claim' : 'Reclamo' }}</label>
                            <label class="tx-radio"><input type="radio" value="Queja" x-model="tipo"> {{ $isEn ? 'Complaint' : 'Queja' }}</label>
                        </div>
                        <small style="color: var(--tx-muted)">{{ $isEn ? 'Claim: disagreement with the product or service. Complaint: dissatisfaction with the service not related to the product.' : 'Reclamo: disconformidad con el producto o servicio. Queja: malestar con la atención, no relacionado al producto.' }}</small>
                    </fieldset>

                    <div class="tx-form-row">
                        <label><span class="tx-label">{{ $isEn ? 'Full name' : 'Nombre completo' }} *</span><input class="tx-input" x-model="f.nombre" required maxlength="255" autocomplete="name"></label>
                        <label><span class="tx-label">{{ $isEn ? 'Email' : 'Correo electrónico' }} *</span><input class="tx-input" type="email" x-model="f.email" required maxlength="255" autocomplete="email"></label>
                    </div>
                    <div class="tx-form-row">
                        <label><span class="tx-label">{{ $isEn ? 'ID document' : 'Documento' }} *</span>
                            <span style="display: flex; gap: 8px">
                                <select class="tx-input" style="max-width: 110px" x-model="f.doc_tipo" aria-label="{{ $isEn ? 'Document type' : 'Tipo de documento' }}"><option>DNI</option><option>CE</option><option>{{ $isEn ? 'Passport' : 'Pasaporte' }}</option><option>RUC</option></select>
                                <input class="tx-input" x-model="f.doc" required maxlength="20" aria-label="{{ $isEn ? 'Document number' : 'Número de documento' }}">
                            </span>
                        </label>
                        <label><span class="tx-label">{{ $isEn ? 'Phone' : 'Teléfono' }} *</span><input class="tx-input" type="tel" x-model="f.telefono" required maxlength="20" autocomplete="tel"></label>
                    </div>
                    <label><span class="tx-label">{{ $isEn ? 'Address' : 'Domicilio' }} *</span><input class="tx-input" x-model="f.domicilio" required maxlength="255" autocomplete="street-address"></label>
                    <label><span class="tx-label">{{ $isEn ? 'Parent or guardian (only if you are a minor)' : 'Padre, madre o apoderado (solo si eres menor de edad)' }}</span><input class="tx-input" x-model="f.menor" maxlength="255"></label>

                    <fieldset style="border: 0; padding: 0; margin: 0; display: grid; gap: 10px">
                        <legend class="tx-label">{{ $isEn ? 'Purchased item' : 'Bien contratado' }}</legend>
                        <div class="tx-radio-row">
                            <label class="tx-radio"><input type="radio" value="Producto" x-model="bien"> {{ $isEn ? 'Product' : 'Producto' }}</label>
                            <label class="tx-radio"><input type="radio" value="Servicio" x-model="bien"> {{ $isEn ? 'Service' : 'Servicio' }}</label>
                        </div>
                    </fieldset>
                    <div class="tx-form-row">
                        <label><span class="tx-label">{{ $isEn ? 'Amount' : 'Monto reclamado' }}</span><input class="tx-input" x-model="f.monto" maxlength="40" placeholder="{{ \App\Helpers\CurrencyHelper::symbol() }} 0.00"></label>
                        <label><span class="tx-label">{{ $isEn ? 'Order number' : 'N.º de pedido' }}</span><input class="tx-input" x-model="f.pedido_nro" maxlength="40"></label>
                    </div>
                    <label><span class="tx-label">{{ $isEn ? 'Item description' : 'Descripción del producto o servicio' }}</span><input class="tx-input" x-model="f.descripcion" maxlength="255"></label>
                    <label><span class="tx-label">{{ $isEn ? 'Details' : 'Detalle' }} *</span><textarea class="tx-input" x-model="f.detalle" required maxlength="3000"></textarea></label>
                    <label><span class="tx-label">{{ $isEn ? 'What do you request?' : '¿Qué solicitas?' }} *</span><textarea class="tx-input" x-model="f.pedido" required maxlength="1500" style="min-height: 80px"></textarea></label>

                    <div class="tx-legal-box">
                        {{ $isEn
                            ? 'Filing a claim does not prevent you from using other dispute-resolution channels, nor is it a prerequisite for filing a complaint with INDECOPI. The store must answer within the time limit set by law (15 business days).'
                            : 'La formulación del reclamo no impide acudir a otras vías de solución de controversias ni es requisito previo para interponer una denuncia ante el INDECOPI. El proveedor debe dar respuesta en el plazo que fija la ley (15 días hábiles).' }}
                    </div>
                    <button type="submit" class="tx-btn is-block">{{ $isEn ? 'Send' : 'Enviar' }} <span x-text="tipo.toLowerCase()"></span></button>
                </form>
            @else
                <form class="tx-form" method="POST" action="{{ route('store.contact.submit', $store->slug) }}">
                    @csrf
                    <div class="tx-form-row">
                        <label><span class="tx-label">{{ $isEn ? 'Full name' : 'Nombre completo' }} *</span><input class="tx-input" name="name" value="{{ old('name') }}" required maxlength="255" autocomplete="name"></label>
                        <label><span class="tx-label">{{ $isEn ? 'Email' : 'Correo electrónico' }} *</span><input class="tx-input" type="email" name="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email"></label>
                    </div>
                    <div class="tx-form-row">
                        <label><span class="tx-label">{{ $isEn ? 'Phone' : 'Teléfono' }}</span><input class="tx-input" type="tel" name="phone" value="{{ old('phone') }}" maxlength="20" autocomplete="tel"></label>
                        <label><span class="tx-label">{{ $isEn ? 'Subject' : 'Asunto' }}</span>
                            <select class="tx-input" name="subject">
                                @foreach($isEn ? ['Sizes', 'My order', 'Exchanges', 'Other'] : ['Tallas', 'Mi pedido', 'Cambios', 'Otro'] as $subject)
                                    <option @selected(old('subject') === $subject)>{{ $subject }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                    <label><span class="tx-label">{{ $isEn ? 'Message' : 'Mensaje' }} *</span><textarea class="tx-input" name="message" required maxlength="3000">{{ old('message') }}</textarea></label>
                    <button type="submit" class="tx-btn is-block">{{ $isEn ? 'Send message' : 'Enviar mensaje' }}</button>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection
