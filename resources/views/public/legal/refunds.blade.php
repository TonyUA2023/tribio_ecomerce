@extends('layouts.public')

@section('title', 'Reembolsos y Cancelaciones | Tribio')

@section('content')
<section class="pt-28 pb-20 bg-white">
    <div class="container-tribio max-w-3xl">
        <p class="text-xs font-bold uppercase tracking-widest text-sky-500 mb-2">Legal · Tribio</p>
        <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-900 mb-2">Política de Reembolsos y Cancelación</h1>
        <p class="text-slate-500 text-sm mb-10">Vigente desde el {{ config('tribio.legal.effective_date') }}.</p>

        <div class="prose prose-slate prose-sm sm:prose-base max-w-none">
            <h2>1. Suscripción de tu tienda</h2>
            <p>Los planes de Tribio se cobran de forma mensual y recurrente mediante Culqi después de que confirmas el pago. Puedes solicitar la cancelación por WhatsApp al <a href="https://wa.me/{{ config('tribio.support.whatsapp_number') }}">{{ config('tribio.support.whatsapp_display') }}</a> o por correo a <a href="mailto:{{ config('tribio.legal.contact_email') }}">{{ config('tribio.legal.contact_email') }}</a>, indicando la cuenta y tienda. Verificaremos la titularidad y te confirmaremos la fecha de término de la renovación. Solicítala antes del próximo cobro para que podamos procesarla oportunamente.</p>
            <p>La cancelación de futuros cobros no implica automáticamente la devolución de un período ya pagado. Revisaremos cada solicitud de reembolso por cobro duplicado, error, cargo no autorizado o incumplimiento, respetando los derechos que corresponden según la legislación peruana. No establecemos una renuncia general a devoluciones legalmente exigibles.</p>

            <h2>2. Compras en tiendas de la plataforma</h2>
            <p>La venta de productos o servicios se realiza con el Negocio identificado en cada tienda. Para solicitar cambio, garantía, devolución o reembolso de un pedido, contacta primero al vendedor y conserva tu comprobante y los detalles del pedido. El Negocio debe atender las obligaciones que le corresponden como proveedor. Si tienes un problema técnico con el registro o pago del pedido en Tribio, también puedes contactar al soporte de la plataforma.</p>

            <h2>3. Cómo presentar una consulta</h2>
            <p>Escríbenos al <a href="https://wa.me/{{ config('tribio.support.whatsapp_number') }}">{{ config('tribio.support.whatsapp_display') }}</a> o a <a href="mailto:{{ config('tribio.legal.contact_email') }}">{{ config('tribio.legal.contact_email') }}</a> con fecha, importe, tienda y una descripción del caso. Nunca envíes por chat el número completo, CVV ni clave de tu tarjeta. Estos canales de soporte no restringen tu derecho a presentar un reclamo ante la autoridad competente.</p>
        </div>

        <div class="mt-10 pt-6 border-t border-slate-100 flex flex-wrap gap-4 text-sm">
            <a href="{{ route('legal.terms') }}" class="text-sky-600 font-semibold hover:underline">Términos y Condiciones</a>
            <a href="{{ route('legal.privacy') }}" class="text-sky-600 font-semibold hover:underline">Privacidad y Tratamiento de Datos</a>
        </div>
    </div>
</section>
@endsection
