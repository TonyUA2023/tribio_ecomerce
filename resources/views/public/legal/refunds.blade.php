@extends('layouts.public')

@section('title', 'Política de Reembolsos y Cancelación | Tribio')

@section('content')
<section class="pt-28 pb-20 bg-white">
    <div class="container-tribio max-w-3xl">
        <p class="text-xs font-bold uppercase tracking-widest text-sky-500 mb-2">Legal</p>
        <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-900 mb-2">Política de Reembolsos y Cancelación</h1>
        <p class="text-slate-400 text-sm mb-10">Última actualización: {{ now()->format('d/m/Y') }}</p>

        <div class="prose prose-slate prose-sm sm:prose-base max-w-none">
            <h2>1. Suscripción de tu negocio en Tribio</h2>
            <p>Tu plan (Emprendedor, Negocio/Pro o Corporativo/B2B) se cobra mensualmente de forma automática a través de Culqi.</p>
            <ul>
                <li><strong>Cancelación:</strong> puedes cancelar cuando quieras desde tu panel de administración. Tu tienda y todas sus funciones se mantienen activas hasta el final del ciclo de facturación ya pagado.</li>
                <li><strong>Reembolsos de la suscripción:</strong> no ofrecemos reembolsos parciales o prorrateados por el tiempo no utilizado dentro de un ciclo ya cobrado, salvo que la ley aplicable indique lo contrario.</li>
                <li><strong>Cambio de plan:</strong> puedes subir o bajar de plan cuando quieras; el nuevo precio se aplica a partir del siguiente ciclo de facturación.</li>
                <li><strong>Cobro fallido:</strong> si tu tarjeta rechaza el cobro recurrente, Culqi reintentará el cargo. Si los reintentos fallan, tu suscripción se cancela automáticamente y tu tienda deja de estar visible al público hasta que reactives el pago.</li>
            </ul>

            <h2>2. Compras hechas en una tienda de Tribio</h2>
            <p>Cuando compras un producto o servicio en cualquier tienda de la plataforma, la venta la realiza ese Negocio directamente, no Tribio. Por lo tanto:</p>
            <ul>
                <li>Las devoluciones, cambios y reembolsos de un pedido se coordinan directamente con el Negocio correspondiente, generalmente a través del mismo chat de WhatsApp usado para confirmar tu pedido.</li>
                <li>Tribio no retiene ni procesa los fondos de la venta entre el Cliente y el Negocio, por lo que no podemos ejecutar reembolsos de pedidos en nombre del Negocio.</li>
                <li>Si un Negocio no responde a tu solicitud de manera razonable, puedes escribirnos y mediaremos el caso, aunque la resolución final del reembolso depende del Negocio.</li>
            </ul>

            <h2>3. ¿Cómo cancelar tu suscripción?</h2>
            <p>Ingresa a tu Panel de Negocio → Configuración de la tienda, o escríbenos directamente si necesitas ayuda.</p>

            <h2>4. Contacto</h2>
            <p>Para cualquier consulta sobre cobros o cancelaciones, escríbenos a <a href="https://wa.me/51902699916">+51 902 699 916</a>.</p>
        </div>

        <div class="mt-10 pt-6 border-t border-slate-100 flex flex-wrap gap-4 text-sm">
            <a href="{{ route('legal.terms') }}" class="text-sky-600 font-semibold hover:underline">Términos y Condiciones</a>
            <a href="{{ route('legal.privacy') }}" class="text-sky-600 font-semibold hover:underline">Política de Privacidad</a>
        </div>
    </div>
</section>
@endsection
