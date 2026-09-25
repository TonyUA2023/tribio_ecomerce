@extends('layouts.public')

@section('title', 'Política de Privacidad y Tratamiento de Datos | Tribio')
@section('meta_description', 'Conoce qué datos personales trata Tribio, para qué los utiliza, con quién los comparte y cómo ejercer tus derechos.')

@section('content')
<section class="pt-28 pb-20 bg-white">
    <div class="container-tribio max-w-3xl">
        <p class="text-xs font-bold uppercase tracking-widest text-sky-500 mb-2">Legal · Tribio</p>
        <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-900 mb-2">Política de Privacidad y Tratamiento de Datos Personales</h1>
        <p class="text-slate-500 text-sm mb-10">Vigente desde el {{ config('tribio.legal.effective_date') }}.</p>

        <div class="prose prose-slate prose-sm sm:prose-base max-w-none">
            <p>Esta política explica cómo Tribio trata los datos de quienes visitan la plataforma, crean una tienda, usan Tribio Pass o compran en una tienda alojada aquí. Aplicamos la Ley N.° 29733 y su Reglamento, aprobado por el Decreto Supremo N.° 016-2024-JUS. La tienda que vende un producto o servicio también debe informar al comprador sobre el tratamiento que realice por su propia cuenta.</p>

            <h2>1. Responsable y canal de contacto</h2>
            <p>El titular de Tribio es <strong>{{ config('tribio.legal.operator_name') }}</strong>, persona natural con RUC <strong>{{ config('tribio.legal.operator_ruc') }}</strong>, ubicado en {{ config('tribio.legal.operator_location') }}. Es responsable del tratamiento de los datos necesarios para gestionar la plataforma, las cuentas Tribio Pass, las suscripciones, la seguridad y la atención de consultas. Para consultas o solicitudes sobre tus datos, escribe a <a href="mailto:{{ config('tribio.legal.contact_email') }}">{{ config('tribio.legal.contact_email') }}</a> o al WhatsApp <a href="https://wa.me/{{ config('tribio.support.whatsapp_number') }}">{{ config('tribio.support.whatsapp_display') }}</a> e indica “Privacidad y datos personales”.</p>

            <h2>2. Datos que tratamos</h2>
            <ul>
                <li><strong>Cuenta e identidad:</strong> nombre, correo, teléfono, identificador de Google si eliges ese acceso, credenciales protegidas y datos de inicio de sesión. Las contraseñas se guardan mediante hash; no se muestran en texto legible.</li>
                <li><strong>Negocio:</strong> nombre y categoría de la tienda, datos de contacto, dirección, logo, imágenes, catálogo, precios y configuración que el emprendedor carga o publica.</li>
                <li><strong>Compras y atención:</strong> productos elegidos, importes, dirección de entrega, datos del pedido, estado de pago o envío y mensajes que nos envías para soporte.</li>
                <li><strong>Suscripciones y pagos:</strong> plan contratado, importes, identificadores de la operación y estado de la suscripción. Los datos completos de tarjeta se ingresan en el entorno del proveedor de pagos correspondiente; Tribio conserva los identificadores necesarios para gestionar la transacción.</li>
                <li><strong>Uso y seguridad:</strong> dirección IP, navegador, identificadores de sesión y registros técnicos necesarios para operar, diagnosticar errores y prevenir abuso.</li>
            </ul>

            <h2>3. Finalidades y carácter de los datos</h2>
            <p>Usamos los datos para crear y proteger la cuenta; abrir y administrar tiendas; mostrar catálogos; gestionar carrito, pedidos, pagos y suscripciones; enviar códigos y mensajes vinculados al servicio; prestar soporte; resolver incidentes y reclamos; cumplir obligaciones legales y mantener la seguridad de la plataforma. Los campos señalados como obligatorios en cada formulario son necesarios para esa operación; si no los proporcionas, no podremos completar el registro, pedido o pago correspondiente.</p>
            <p>La información necesaria para prestar el servicio y cumplir obligaciones legales se trata para esas finalidades. Cuando una finalidad adicional requiera consentimiento, lo solicitaremos de forma específica. No utilizamos la aceptación de estos términos como autorización general para publicidad.</p>

            <h2>4. Quién recibe la información</h2>
            <p>El negocio que recibe un pedido accede a los datos necesarios para prepararlo, entregarlo y atender al comprador. Según el medio elegido, podemos comunicar datos estrictamente necesarios a Culqi (suscripciones de Tribio), al proveedor de pago configurado por la tienda —como Mercado Pago, Flow o PayPal—, al proveedor de correo transaccional Brevo, o a Google si usas su inicio de sesión. Cuando decides abrir WhatsApp para soporte o contactar a una tienda, el uso de ese servicio también se rige por las condiciones de WhatsApp/Meta. Si una tienda activó su conexión con Meta o con Google y aceptaste las cookies de publicidad, se comunica a Meta Platforms o a Google la información descrita en la sección 7. Los proveedores tecnológicos que alojan u operan la plataforma pueden tratar datos bajo nuestras instrucciones.</p>
            <p>No vendemos bases de datos personales. También podremos comunicar información cuando una autoridad competente lo requiera conforme a ley.</p>

            <h2>5. Transferencias y servicios fuera del Perú</h2>
            <p>Algunos proveedores tecnológicos pueden tratar o almacenar información fuera del Perú. En esos casos aplicaremos las condiciones y medidas exigidas por la normativa de protección de datos para la transferencia o el encargo de tratamiento, según corresponda.</p>

            <h2>6. Plazo de conservación</h2>
            <p>Conservamos los datos mientras sean necesarios para prestar el servicio y mantener la cuenta. Después, conservaremos únicamente los registros necesarios para obligaciones contables o legales, atención de reclamos, seguridad o defensa de derechos durante los plazos aplicables. Cumplida la finalidad, los eliminaremos o anonimizaremos, salvo conservación legal obligatoria. La cancelación de una cuenta puede no borrar pedidos o comprobantes que debamos conservar.</p>

            <h2 id="cookies">7. Cookies, publicidad y recursos externos</h2>
            <p>Tribio usa cookies de sesión y seguridad para identificar tu cuenta y proteger formularios. El navegador puede contactar servicios externos al cargar fuentes, recursos de interfaz, autenticación o medios de pago.</p>
            <p>Algunas tiendas alojadas en Tribio usan el Píxel de Meta (Facebook e Instagram), Google Analytics o Google Ads para medir sus visitas y anuncios y mostrarte ofertas relevantes. En esas tiendas verás un aviso de cookies: estas herramientas solo se activan si pulsas «Aceptar», y puedes rechazarlas sin que eso afecte tu compra. Con Google, la tienda comunica los productos que ves, agregas al carrito o compras, el importe y el número de pedido, sin tus datos de contacto. Si aceptas, la tienda puede comunicar a Meta los productos que ves, agregas al carrito o compras y, al completar un pedido, el correo, teléfono, nombre, ciudad y país del pedido cifrados de forma irreversible (hash), para que Meta relacione la compra con sus anuncios. Nunca se comparten tu documento de identidad ni tu dirección. Meta trata esa información conforme a su propia política de datos.</p>
            <p>En esas tiendas también guardamos por 30 días, en una cookie, la campaña o el anuncio desde el que llegaste (parámetros utm y el identificador de clic), para que la tienda sepa qué campañas le generan ventas. Para cambiar tu decisión, borra las cookies de la tienda en tu navegador y el aviso volverá a aparecer.</p>

            <h2>8. Seguridad y acceso</h2>
            <p>Aplicamos controles técnicos y organizativos razonables para limitar el acceso no autorizado, proteger las cuentas y registrar incidentes. Ningún sistema conectado a internet ofrece seguridad absoluta. Si detectas un acceso indebido o un problema con tus datos, avísanos por el canal indicado para investigarlo.</p>

            <h2>9. Tus derechos sobre los datos</h2>
            <p>Puedes pedir información y acceso a tus datos, su rectificación, cancelación u oposición al tratamiento, así como retirar un consentimiento cuando corresponda. Envía tu solicitud a <a href="mailto:{{ config('tribio.legal.contact_email') }}">{{ config('tribio.legal.contact_email') }}</a> o al WhatsApp <a href="https://wa.me/{{ config('tribio.support.whatsapp_number') }}">{{ config('tribio.support.whatsapp_display') }}</a> con la descripción de lo que solicitas. Para proteger tu información podremos verificar tu identidad antes de responder. El trámite es gratuito; el plazo ordinario es de 20 días hábiles para acceso y 10 días hábiles para rectificación, cancelación u oposición, sujeto a las reglas legales aplicables.</p>
            <p>Si no atendemos tu solicitud o no estás conforme con la respuesta, puedes acudir a la <a href="https://www.gob.pe/9269" target="_blank" rel="noopener noreferrer">Autoridad Nacional de Protección de Datos Personales</a>.</p>

            <h2>10. Datos de menores</h2>
            <p>La creación y administración de tiendas está dirigida a personas con capacidad legal para contratar. Si advertimos que se proporcionaron datos de un menor sin la autorización exigida por ley, adoptaremos las medidas correspondientes para restringir su tratamiento.</p>

            <h2>11. Cambios en esta política</h2>
            <p>Publicaremos la versión vigente con su fecha de entrada en vigor. Cuando un cambio afecte materialmente el uso de los datos, lo comunicaremos por un medio adecuado y recabaremos el consentimiento que sea necesario antes de aplicar nuevas finalidades.</p>
        </div>

        <div class="mt-10 pt-6 border-t border-slate-100 flex flex-wrap gap-4 text-sm">
            <a href="{{ route('legal.terms') }}" class="text-sky-600 font-semibold hover:underline">Términos y Condiciones</a>
            <a href="{{ route('legal.refunds') }}" class="text-sky-600 font-semibold hover:underline">Reembolsos y Cancelaciones</a>
        </div>
    </div>
</section>
@endsection
