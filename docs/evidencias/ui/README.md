# Evidencias visuales de la interfaz · punto de venta

Capturas tomadas el **29/10/2026** sobre el entorno local en ejecución —API en
`127.0.0.1:8083`, interfaz en `localhost:5173`, PostgreSQL con los 884
productos del catálogo— y no sobre un prototipo ni una maqueta.

Sirven de respaldo directo para el informe de la Semana 18.

| Archivo | Qué demuestra |
|---|---|
| `UI-01-busqueda-hibrida-sin-conexion.jpg` | Búsqueda resuelta contra el catálogo guardado en la caja: indicador «Sin conexión» en ámbar, aviso de que los resultados vienen del catálogo local y la insignia «Stock sin verificar» en cada fila |
| `UI-02-cobro-F4-vidrio.jpg` | Panel de cobro abierto con F4, sobre superficie translúcida; el fondo se ve desenfocado sin que el texto pierda contraste |
| `UI-03-ticket-contingencia-NV-000003.jpg` | Ticket interno de contingencia a 72 mm, con la advertencia «No es comprobante de pago» en su propia cara y el contador de cola en «1 venta por enviar» |
| `UI-04-reconexion-cola-vacia.jpg` | Tras restablecer la red: indicador «En línea» y el contador de cola desaparecido, porque la venta ya se sincronizó sin intervención |
| `UI-05-pos-en-linea-elevaciones.jpg` | La misma pantalla con conexión: stock real por producto y las micro-elevaciones de la capa premium sobre el panel de resultados |

## Lo que las capturas no muestran por sí solas

La **transición** del indicador es la pareja `UI-01` → `UI-04`: una captura
fija no puede mostrar un cambio de estado. Lo que sí demuestran juntas es que
el mismo equipo, en la misma sesión, pasó de operar sin red a sincronizar sin
que nadie pulsara nada.

El **corte de red se simuló** sobreescribiendo `navigator.onLine` y
despachando el evento `offline`, que es la señal que emite el navegador
cuando cae la interfaz y la que escucha el sistema. No se desconectó el cable.

## Comprobación en la base de datos

La venta de `UI-03` llegó al servidor con su correlativo oficial:

```
NV-000001  S/ 9.00  ->  B001-00000035   disp=caja-sin-identificar
NV-000002  S/ 6.50  ->  B001-00000036   disp=caja-sin-identificar
NV-000003  S/ 9.00  ->  B001-00000037   disp=CAJA-PRINCIPAL
```

Las dos primeras son del ensayo del 24/10. La tercera es la de estas
capturas, y confirma de paso que el respaldo `CAJA-PRINCIPAL` del nombre de
equipo funciona: las anteriores se registraron cuando ese valor todavía era
«caja-sin-identificar».
