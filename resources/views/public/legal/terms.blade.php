@extends('layouts.public')

@section('title', 'Términos y Condiciones | Tribio')
@section('meta_description', 'Condiciones para crear una tienda, contratar un plan y comprar en negocios alojados en Tribio.')

@section('content')
<section class="pt-28 pb-20 bg-white">
    <div class="container-tribio max-w-3xl">
        <p class="text-xs font-bold uppercase tracking-widest text-sky-500 mb-2">Legal · Tribio</p>
        <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-900 mb-2">Términos y Condiciones</h1>
        <p class="text-slate-500 text-sm mb-10">Vigentes desde el {{ config('tribio.legal.effective_date') }}.</p>

        <div class="prose prose-slate prose-sm sm:prose-base max-w-none">
            <p>Estos términos regulan el uso de Tribio y la contratación de sus planes. El servicio es operado por <strong>{{ config('tribio.legal.operator_name') }}</strong>, persona natural con RUC <strong>{{ config('tribio.legal.operator_ruc') }}</strong>, ubicada en {{ config('tribio.legal.operator_location') }}. “Tribio” identifica a la plataforma; “Negocio” es quien publica y vende en su tienda; “Cliente” es quien compra o consulta en ella. Antes de registrarte, contratar o comprar, revisa estas condiciones y la información particular de la tienda.</p>

            <h2>1. Qué ofrece Tribio</h2>
            <p>Tribio proporciona herramientas para crear una tienda digital, publicar un catálogo, organizar pedidos y ofrecer métodos de pago y contacto según la configuración de cada Negocio. Tribio Pass permite acceder con una misma cuenta a funciones de compra y, cuando corresponda, de administración de tiendas. Las funciones disponibles dependen del plan contratado y de las integraciones activadas.</p>

            <h2>2. Cuenta y uso responsable</h2>
            <p>Debes proporcionar datos correctos y mantenerlos actualizados. La persona que abre una tienda debe tener capacidad legal para contratar y las facultades necesarias para representar al Negocio. Eres responsable de proteger tus credenciales y avisarnos por el canal de soporte si detectas un acceso no autorizado. Podemos limitar temporalmente una cuenta cuando sea necesario para investigar fraude, riesgos de seguridad o un incumplimiento, comunicando la medida cuando corresponda.</p>

            <h2>3. Planes, precio y contratación</h2>
            <p>Antes de confirmar el pago, Tribio muestra el plan elegido, sus prestaciones, precio mensual en soles y el resumen del cargo. Los precios vigentes para nuevas contrataciones se muestran en la <a href="{{ route('home') }}#precios">página de planes</a> y en el paso de pago. La tienda se crea inicialmente en borrador y se activa cuando se confirma el pago, si el servicio y el medio de cobro están disponibles. La contratación de Tribio es distinta de las compras que los Clientes hacen a cada Negocio.</p>
            <p>La suscripción es mensual y recurrente: al contratar autorizas los cobros periódicos indicados en la pantalla de pago mediante Culqi. El comprobante o documento tributario correspondiente se emitirá conforme a la normativa aplicable y a los datos proporcionados para la contratación.</p>

            <h2>4. Renovación, cambios y cancelación</h2>
            <p>La suscripción se renueva y cobra en cada período mientras permanezca activa. Puedes solicitar su cancelación por WhatsApp al <a href="https://wa.me/{{ config('tribio.support.whatsapp_number') }}">{{ config('tribio.support.whatsapp_display') }}</a>, indicando la tienda y la cuenta titular. Verificaremos la solicitud y confirmaremos la fecha en que termina la renovación. La cancelación evita nuevos cobros una vez procesada y no elimina por sí sola un cargo ya efectuado ni los derechos de reembolso que correspondan por ley.</p>
            <p>Si un pago falla, te informaremos y podremos limitar temporalmente la publicación de la tienda mientras se regulariza la suscripción. Cualquier cambio de precio o prestación se informará antes de aplicarse a la renovación afectada; el precio anunciado para nuevas contrataciones no modifica automáticamente una suscripción ya existente.</p>

            <h2>5. Reembolsos y cobros</h2>
            <p>Las solicitudes de devolución por cobros duplicados, no autorizados, errores del servicio o incumplimientos se revisarán según el caso y la legislación aplicable. No se excluyen los derechos que la ley reconoce al consumidor. Las reglas operativas se explican en la <a href="{{ route('legal.refunds') }}">Política de Reembolsos y Cancelación</a>. Si ves un cargo que no reconoces, contáctanos con la fecha e importe para investigarlo.</p>

            <h2>6. Compras realizadas en una tienda</h2>
            <p>Cada Negocio identifica al vendedor de sus productos o servicios y determina su oferta, precio, stock, envío, atención posventa y condiciones particulares, que debe comunicar claramente al Cliente. El contrato de compraventa se celebra entre Cliente y Negocio. Tribio facilita la infraestructura tecnológica y responde por sus propias obligaciones como proveedor de la plataforma; esta distribución de funciones no reduce los derechos del consumidor ni excluye responsabilidades que correspondan por ley.</p>
            <p>El medio de pago de un pedido depende de la tienda: puede ser una pasarela configurada por el Negocio o una coordinación directa por WhatsApp. Antes de pagar, el Cliente debe revisar el resumen del pedido, el vendedor, el importe y las condiciones de entrega. Para cambios, garantías y devoluciones de productos, el primer contacto es el Negocio vendedor; Tribio puede recibir una consulta de soporte y ayudar a identificar la tienda o el estado técnico del pedido.</p>

            <h2>7. Obligaciones de los Negocios</h2>
            <p>El Negocio debe publicar información veraz y suficiente; contar con derechos sobre sus imágenes y contenidos; respetar precios, promociones y condiciones ofrecidas; cumplir obligaciones tributarias, de consumo y protección de datos; atender pedidos y reclamos; y mantener actualizados sus canales de contacto. No puede ofrecer bienes o servicios ilegales, fraudulentos, que vulneren derechos de terceros o que infrinjan las reglas del proveedor de pagos utilizado.</p>

            <h2>8. Contenido y propiedad intelectual</h2>
            <p>El software, diseño y signos distintivos de Tribio pertenecen a su titular o a quienes le otorgaron licencia. El Negocio conserva los derechos sobre el contenido que aporta y autoriza a Tribio a almacenarlo, reproducirlo y mostrarlo únicamente para operar y promocionar su tienda dentro de la plataforma mientras corresponda. El Negocio garantiza que tiene permiso para usar ese contenido y atenderá reclamaciones legítimas sobre él.</p>

            <h2>9. Disponibilidad y seguridad</h2>
            <p>Trabajamos para mantener la plataforma operativa y segura, pero pueden existir mantenimientos, fallas de red o interrupciones de servicios externos. Informaremos incidentes relevantes por canales adecuados y tomaremos medidas razonables para restablecer el servicio. Ninguna parte de estos términos elimina obligaciones o responsabilidades que no puedan limitarse legalmente.</p>

            <h2>10. Datos personales</h2>
            <p>El tratamiento de datos por Tribio se explica en la <a href="{{ route('legal.privacy') }}">Política de Privacidad y Tratamiento de Datos Personales</a>. El Negocio que utiliza datos de sus Clientes para atender pedidos u otras finalidades debe cumplir también sus propias obligaciones de información, seguridad y atención de derechos.</p>

            <h2>11. Reclamos y solución de controversias</h2>
            <p>Para consultas o reclamos sobre la plataforma, una suscripción o un cobro de Tribio, escribe a <a href="mailto:{{ config('tribio.legal.contact_email') }}">{{ config('tribio.legal.contact_email') }}</a> o al WhatsApp <a href="https://wa.me/{{ config('tribio.support.whatsapp_number') }}">{{ config('tribio.support.whatsapp_display') }}</a> con los datos de la operación. Los reclamos relativos al producto, entrega o garantía deben dirigirse al Negocio vendedor, sin perjuicio de los derechos y vías de reclamación que establece la ley. El contacto con soporte no limita la posibilidad de acudir a Indecopi o a otra autoridad competente.</p>

            <h2>12. Modificaciones y ley aplicable</h2>
            <p>Podemos actualizar estos términos por cambios del servicio o de la normativa. Mostraremos la fecha de vigencia y comunicaremos con antelación razonable los cambios materiales que afecten una suscripción activa. Si una modificación requiere una nueva aceptación, la solicitaremos antes de aplicarla. Rige la legislación peruana y las controversias se atenderán ante las autoridades o jueces competentes conforme a ella.</p>
        </div>

        <div class="mt-10 pt-6 border-t border-slate-100 flex flex-wrap gap-4 text-sm">
            <a href="{{ route('legal.privacy') }}" class="text-sky-600 font-semibold hover:underline">Privacidad y Tratamiento de Datos</a>
            <a href="{{ route('legal.refunds') }}" class="text-sky-600 font-semibold hover:underline">Reembolsos y Cancelaciones</a>
        </div>
    </div>
</section>
@endsection
