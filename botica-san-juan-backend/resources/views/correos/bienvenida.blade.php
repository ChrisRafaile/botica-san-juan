<x-mail::message>
# Hola {{ $primerNombre }},

Tu cuenta en **Botica San Juan** ya está lista. Desde el portal puedes consultar el catálogo de medicamentos, hacer tus pedidos en línea y revisar el estado de tus compras.

<x-mail::button :url="$urlPortal">
Ir al portal
</x-mail::button>

Si no creaste esta cuenta, puedes ignorar este mensaje.

Saludos,<br>
Botica San Juan
</x-mail::message>
