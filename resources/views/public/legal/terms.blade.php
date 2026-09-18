@extends('layouts.public')

@section('title', 'Términos y Condiciones | Tribio')

@section('content')
<section class="pt-28 pb-20 bg-white">
    <div class="container-tribio max-w-3xl">
        <p class="text-xs font-bold uppercase tracking-widest text-sky-500 mb-2">Legal</p>
        <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-900 mb-2">Términos y Condiciones</h1>
        <p class="text-slate-400 text-sm mb-10">Última actualización: {{ now()->format('d/m/Y') }}</p>

        <div class="prose prose-slate prose-sm sm:prose-base max-w-none">
            <p>Estos Términos y Condiciones ("Términos") regulan el uso de la plataforma Tribio (el "Servicio"), operada como un espacio que permite a emprendedores y negocios ("Negocios") crear y administrar su propia tienda virtual, y a compradores ("Clientes") explorar y comprar en dichas tiendas. Al registrarte o usar Tribio aceptas estos Términos.</p>

            <h2>1. Cuenta y Tribio Pass</h2>
            <p>Tribio Pass es tu cuenta única en la plataforma. Con ella puedes comprar en cualquier tienda de la red y, si lo deseas, administrar tu propio negocio, sin necesidad de crear cuentas separadas. Eres responsable de mantener la confidencialidad de tu contraseña y de toda actividad realizada desde tu cuenta.</p>

            <h2>2. Suscripción de Negocios</h2>
            <p>Para publicar una tienda, el Negocio debe elegir un plan de suscripción pagado (Emprendedor, Negocio/Pro o Corporativo/B2B) y mantenerlo vigente. La suscripción se cobra de forma mensual y recurrente a través de nuestro procesador de pagos Culqi, hasta que el Negocio la cancele.</p>
            <ul>
                <li>El cobro se realiza automáticamente cada mes a la tarjeta registrada.</li>
                <li>Puedes cancelar tu suscripción en cualquier momento desde tu panel de administración. La cancelación aplica al final del ciclo de facturación vigente; no se realizan reembolsos por el período ya iniciado.</li>
                <li>Si un cobro recurrente falla repetidamente, Tribio podrá suspender la publicación de la tienda hasta que se regularice el pago.</li>
                <li>Tribio podrá modificar los precios de los planes con aviso previo razonable; los cambios no aplican retroactivamente al ciclo ya cobrado.</li>
            </ul>

            <h2>3. Relación entre Clientes y Negocios</h2>
            <p>Tribio actúa como plataforma tecnológica que conecta a Negocios y Clientes. La venta de productos o servicios, su calidad, disponibilidad, entrega y garantía son responsabilidad exclusiva de cada Negocio. Tribio no es parte de la transacción de compraventa entre el Cliente y el Negocio.</p>

            <h2>4. Uso aceptable</h2>
            <p>Está prohibido usar Tribio para publicar contenido ilegal, fraudulento, que infrinja derechos de terceros, o para procesar pagos de productos o servicios prohibidos por la ley peruana. Tribio puede suspender o eliminar cuentas que incumplan estos Términos.</p>

            <h2>5. Propiedad intelectual</h2>
            <p>La marca Tribio, su software y diseño son propiedad de Tribio. Cada Negocio conserva la propiedad de su contenido (productos, fotos, textos, marca), y otorga a Tribio una licencia limitada para mostrarlo dentro de la plataforma.</p>

            <h2>6. Limitación de responsabilidad</h2>
            <p>Tribio se ofrece "tal cual". No garantizamos disponibilidad ininterrumpida del Servicio. En la máxima medida permitida por la ley, Tribio no será responsable por daños indirectos derivados del uso de la plataforma o de transacciones entre Clientes y Negocios.</p>

            <h2>7. Ley aplicable</h2>
            <p>Estos Términos se rigen por las leyes de la República del Perú. Cualquier controversia se someterá a los jueces y tribunales competentes de Perú.</p>

            <h2>8. Contacto</h2>
            <p>Para consultas sobre estos Términos, escríbenos a <a href="https://wa.me/51902699916">+51 902 699 916</a>.</p>
        </div>

        <div class="mt-10 pt-6 border-t border-slate-100 flex flex-wrap gap-4 text-sm">
            <a href="{{ route('legal.privacy') }}" class="text-sky-600 font-semibold hover:underline">Política de Privacidad</a>
            <a href="{{ route('legal.refunds') }}" class="text-sky-600 font-semibold hover:underline">Política de Reembolsos y Cancelación</a>
        </div>
    </div>
</section>
@endsection
