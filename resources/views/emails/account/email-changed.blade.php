<x-mail::message>
# Tu correo de acceso cambió

Hola {{ $name }}, el correo con el que inicias sesión en Tribio se cambió a **{{ $maskedEmail }}**. Desde ahora usa ese correo para entrar a tu panel.

Si fuiste tú, no necesitas hacer nada más.

**¿No reconoces este cambio?** Escríbenos de inmediato para proteger tu cuenta.

<x-mail::button :url="$supportUrl">
Contactar a soporte de Tribio
</x-mail::button>

Equipo Tribio
</x-mail::message>
