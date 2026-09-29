<x-mail::message>
# {{ $isComplaint ? 'Recibiste un reclamo' : 'Tienes un mensaje nuevo' }}

**{{ $contact->name }}** ({{ $contact->email }}{{ $contact->phone ? ' · ' . $contact->phone : '' }}) te escribió desde tu tienda **{{ $contact->store->name }}**.

**Asunto:** {{ $contact->subject ?: 'Sin asunto' }}

<x-mail::panel>
{{ \Illuminate\Support\Str::limit($contact->message, 1500) }}
</x-mail::panel>

@if($isComplaint)
Es un registro del Libro de Reclamaciones: respóndelo por escrito dentro de los 15 días hábiles.

@endif
Puedes responder a este correo y le llegará directamente al cliente.

<x-mail::button :url="$url">
Ver en mis mensajes
</x-mail::button>

Tribio
</x-mail::message>
