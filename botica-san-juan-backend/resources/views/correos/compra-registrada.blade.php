<x-mail::message>
# Pedido #{{ $numeroPedido }} registrado

Registramos tu pedido en **Botica San Juan**. A continuación tienes el resumen de tu compra:

- **Número de pedido:** #{{ $numeroPedido }}
- **Comprobante:** {{ $identificadorComprobante }}
- **Modalidad:** {{ $modalidad }}
- **Estado:** {{ $estado }}

<x-mail::table>
| Producto | Cant. | Subtotal |
| :--- | :---: | ---: |
@foreach ($items as $item)
| {{ $item['nombre'] }} | {{ $item['cantidad'] }} | S/ {{ $item['subtotal'] }} |
@endforeach
</x-mail::table>

**Total:** S/ {{ $total }}

<x-mail::button :url="$urlPedidos">
Ver mis pedidos
</x-mail::button>

Gracias por tu compra,<br>
Botica San Juan
</x-mail::message>
