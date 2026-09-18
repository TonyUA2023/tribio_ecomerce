@extends('layouts.public')

@section('title', 'Política de Privacidad | Tribio')

@section('content')
<section class="pt-28 pb-20 bg-white">
    <div class="container-tribio max-w-3xl">
        <p class="text-xs font-bold uppercase tracking-widest text-sky-500 mb-2">Legal</p>
        <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-900 mb-2">Política de Privacidad</h1>
        <p class="text-slate-400 text-sm mb-10">Última actualización: {{ now()->format('d/m/Y') }}</p>

        <div class="prose prose-slate prose-sm sm:prose-base max-w-none">
            <p>En Tribio protegemos los datos personales de nuestros usuarios (Negocios y Clientes) conforme a la Ley N° 29733, Ley de Protección de Datos Personales del Perú, y su reglamento.</p>

            <h2>1. Datos que recopilamos</h2>
            <ul>
                <li><strong>Datos de cuenta (Tribio Pass):</strong> nombre, correo electrónico, teléfono y contraseña (almacenada de forma cifrada, nunca en texto plano).</li>
                <li><strong>Datos de negocio:</strong> nombre de tienda, categoría, logo, dirección y redes sociales que el Negocio decide publicar.</li>
                <li><strong>Datos de compra:</strong> dirección de envío, productos comprados e historial de pedidos.</li>
                <li><strong>Datos de pago:</strong> Tribio <u>nunca</u> almacena el número completo de tu tarjeta. La tokenización de tarjetas para el pago de suscripciones la realiza directamente Culqi, y el pago de pedidos dentro de cada tienda lo procesa Mercado Pago; ambos son procesadores certificados PCI-DSS.</li>
                <li><strong>Datos técnicos:</strong> dirección IP, tipo de navegador y páginas visitadas, con fines de seguridad y analítica básica.</li>
            </ul>

            <h2>2. Uso de los datos</h2>
            <p>Usamos tus datos para: crear y operar tu cuenta Tribio Pass, procesar pedidos y suscripciones, enviarte notificaciones transaccionales (confirmación de pedido, código de verificación), prevenir fraude y mejorar el Servicio. No vendemos tus datos personales a terceros.</p>

            <h2>3. Terceros con los que compartimos datos</h2>
            <ul>
                <li><strong>Culqi</strong> — procesamiento de pagos de suscripción de Negocios.</li>
                <li><strong>Mercado Pago</strong> — procesamiento de pagos de pedidos dentro de cada tienda.</li>
                <li><strong>Brevo</strong> — envío de correos transaccionales (confirmaciones, códigos de verificación).</li>
                <li><strong>WhatsApp</strong> — solo cuando tú decides enviar un pedido por ese medio; el mensaje se comparte directamente entre tu dispositivo y el del Negocio.</li>
            </ul>
            <p>Cada uno de estos terceros procesa los datos según sus propias políticas de privacidad.</p>

            <h2>4. Tus derechos (ARCO)</h2>
            <p>Puedes solicitar acceso, rectificación, cancelación u oposición al tratamiento de tus datos personales escribiéndonos por WhatsApp. Atenderemos tu solicitud dentro de los plazos que establece la ley peruana.</p>

            <h2>5. Cookies</h2>
            <p>Usamos cookies estrictamente necesarias para mantener tu sesión iniciada (Tribio Pass) y el contenido de tu carrito de compras. No usamos cookies de publicidad de terceros.</p>

            <h2>6. Conservación de datos</h2>
            <p>Conservamos tus datos mientras tu cuenta esté activa. Si solicitas la eliminación de tu cuenta, eliminaremos o anonimizaremos tus datos personales salvo que la ley nos obligue a conservar registros de transacciones por un período determinado.</p>

            <h2>7. Contacto</h2>
            <p>Para ejercer tus derechos o consultas sobre privacidad, escríbenos a <a href="https://wa.me/51902699916">+51 902 699 916</a>.</p>
        </div>

        <div class="mt-10 pt-6 border-t border-slate-100 flex flex-wrap gap-4 text-sm">
            <a href="{{ route('legal.terms') }}" class="text-sky-600 font-semibold hover:underline">Términos y Condiciones</a>
            <a href="{{ route('legal.refunds') }}" class="text-sky-600 font-semibold hover:underline">Política de Reembolsos y Cancelación</a>
        </div>
    </div>
</section>
@endsection
