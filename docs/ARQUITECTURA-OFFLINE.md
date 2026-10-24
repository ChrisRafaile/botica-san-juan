# Arquitectura offline / online del punto de venta

> Estado: en construcción · Sprint 7 · Botica San Juan
> Este documento es la fuente de verdad del contrato de sincronización. El
> informe del APF3 quedó congelado; lo que se construya aquí alimentará la
> entrega de la Semana 18 (operación, eventos, incidentes y continuidad).

---

## 1. El problema, dicho sin adornos

La botica está en un distrito donde el internet se cae. Hoy, cuando se cae, el
punto de venta deja de funcionar y la caja se atiende en papel, que es
exactamente el estado del que veníamos huyendo. La atención en mostrador no
puede depender de que Render responda.

La regla que gobierna todo lo que sigue es esta: **el producto ya salió del
anaquel**. Cuando el vendedor cobra, la mercadería se fue con el cliente, haya
o no internet. El sistema no decide si la venta ocurrió; la venta ya ocurrió y
el sistema tiene que registrarla. Esto condiciona dos decisiones que más
adelante podrían parecer raras:

- Una venta sincronizada **nunca se rechaza por falta de stock**. Si al llegar
  al servidor el stock ya no alcanza, se registra igual y la diferencia queda
  como incidencia de inventario para el conteo por ciclos. Rechazarla
  significaría que el sistema dice que algo no pasó cuando sí pasó.
- El comprobante de contingencia que se entrega en caja **no es un comprobante
  de pago**. Es un ticket interno. Ver la sección 6.

---

## 2. Qué pieza hace qué

| Capa | Dónde corre | Responsabilidad |
|---|---|---|
| Caja (Vue 3 + Tauri) | PC de la botica | Cobra, guarda en local, reintenta |
| Persistencia local | SQLite vía Tauri (IndexedDB mientras tanto) | Cola de ventas pendientes y bitácora |
| API (Laravel 12) | Render | Correlativo oficial, stock central, cola SUNAT |
| Base central | Neon PostgreSQL | Verdad final |

La capa de persistencia local se consume a través de una interfaz única, de
modo que pasar de IndexedDB a SQLite no toque la lógica de negocio. Hoy el
proyecto todavía no tiene el entorno nativo inicializado; esa es la razón de
que la interfaz exista desde el primer día y no cuando llegue Tauri.

---

## 3. Identidad de la venta: el UUID local

Cada venta cobrada sin conexión nace con un **UUID v4 generado en la caja**.
Ese identificador es la clave de idempotencia de todo el sistema.

No sirve usar la fecha, ni el total, ni el número de ticket: dos clientes
pueden comprar lo mismo, por el mismo importe, en el mismo segundo, y un
contador local se reinicia cuando se reinstala la aplicación. El UUID viaja
con la venta, se guarda en `pedidos.uuid_local` con índice `UNIQUE`, y es la
base de datos la que garantiza que no haya dos.

**Por qué la garantía vive en el índice y no en el código.** Comprobar «¿existe
ya este UUID?» y después insertar son dos operaciones; entre una y otra cabe
un segundo intento de sincronización disparado por una reconexión
intermitente. El índice único no deja margen: el segundo intento falla en el
motor y el servicio lo traduce a «duplicada», que es la respuesta correcta.

Reintentar es, por tanto, **seguro por diseño**. La caja puede reenviar el
mismo lote tantas veces como quiera.

---

## 4. Contrato: `POST /api/v1/ventas/sincronizar`

Autenticación: `auth:sanctum`, rol vendedor o administrador. La caja guarda un
token de larga duración obtenido en el último inicio de sesión con conexión.

### Petición

```json
{
  "dispositivo": "caja-01",
  "ventas": [
    {
      "uuid_local": "3f6b2a10-8c9d-4a71-9f0e-2b5c8d1e4a77",
      "nota_contingencia": "NV-000127",
      "registrada_en": "2026-10-24T15:42:11-05:00",
      "cliente": {
        "nombre": "Consumidor final",
        "documento": null,
        "tipo_documento": "sin_documento"
      },
      "observacion": null,
      "items": [
        { "producto_id": 412, "unidad_venta": "unidad", "cantidad": 2 },
        { "producto_id": 87,  "unidad_venta": "blister", "cantidad": 1 }
      ],
      "pagos": [
        { "medio": "efectivo", "monto": 18.50, "monto_recibido": 20.00 }
      ]
    }
  ]
}
```

Límite por lote: **50 ventas**. No es una cifra decorativa: el plan gratuito de
Render tiene un tiempo de respuesta acotado y cada venta abre su propia
transacción. Con más de 50, un lote lento empieza a competir con la atención
en mostrador de otra caja. La cola se envía en tandas.

### Respuesta `200`

```json
{
  "procesadas": 3,
  "resultados": [
    {
      "uuid_local": "3f6b2a10-...",
      "estado": "creada",
      "pedido_id": 1042,
      "comprobante": "B001-00000318",
      "incidencias": []
    },
    { "uuid_local": "9a1c...", "estado": "duplicada", "pedido_id": 1039,
      "comprobante": "B001-00000315", "incidencias": [] },
    { "uuid_local": "c04f...", "estado": "rechazada",
      "motivo": "El producto 9999 no existe en el catálogo." }
  ]
}
```

Tres estados y nada más:

- **`creada`** — se registró ahora. Trae el correlativo oficial.
- **`duplicada`** — ya estaba. Trae el correlativo de la vez anterior. **No es
  un error**: la caja debe marcarla como sincronizada igual que una creada.
- **`rechazada`** — no se pudo registrar y no se va a poder sin intervención
  humana (un producto que no existe, un cobro que no cuadra). Trae el motivo
  en texto, pensado para que el vendedor lo lea.

### Por qué la respuesta es siempre `200`

Un lote de veinte ventas donde una falla no es un fallo de la petición. Si el
endpoint respondiera `422`, la caja tendría que adivinar cuáles de las veinte
entraron. El estado va por venta, no por lote. Sólo hay código de error cuando
falla la petición entera: `401` sin token, `403` sin rol, `422` si el cuerpo no
cumple el esquema.

**Cada venta va en su propia transacción.** Una venta rechazada no arrastra a
las otras diecinueve. Esto es deliberado y es lo contrario de lo que haría un
`DB::transaction()` envolviendo el bucle completo.

---

## 5. Qué hace el servidor con cada venta

1. Comprueba si el `uuid_local` ya existe. Si existe → `duplicada`, se
   devuelve lo que se emitió entonces y se termina.
2. Abre transacción.
3. Registra la venta con `VentaService::registrar(... origen: pos)`, que ya
   descuenta por lotes con criterio FEFO, calcula el desglose de IGV y
   registra incidencias cuando el stock no alcanza.
4. Emite el comprobante con `ComprobanteService`, que bloquea la fila de la
   serie (`FOR UPDATE`) y asigna el correlativo. Esta pieza ya existía y ya
   estaba protegida contra dobles emisiones; la sincronización no inventa
   numeración propia.
5. Guarda `uuid_local`, `nota_contingencia` y `registrado_offline_en`.
6. Escribe el evento en la bitácora.
7. Confirma la transacción.

El correlativo oficial se asigna **en el momento de la sincronización**, no en
la caja. La caja no tiene forma de saber qué número le toca: otra caja o el
portal pueden haber emitido mientras tanto.

---

## 6. La nota de contingencia no es un comprobante de pago

Lo que la caja imprime sin conexión (`NV-000127`) es un **ticket interno de
control**. Sirve para que el cliente se lleve algo con el detalle de lo que
compró y para que la venta sea rastreable, pero no reemplaza a la boleta.

Esto importa y conviene que quede escrito antes de que alguien asuma lo
contrario: en el Perú la emisión electrónica tiene reglas sobre plazos de
envío y sobre qué se entrega al cliente, y no soy la fuente adecuada para
fijarlas. **Antes de operar esto en producción hay que confirmarlo con el
contador de la botica**, en particular dos cosas: qué se le entrega al cliente
en el momento de la venta cuando no hay conexión, y en qué plazo debe quedar
enviado el resumen a la SUNAT.

Lo que el sistema sí hace, y es responsabilidad técnica nuestra:

- Conserva la **fecha real de la venta** (`registrada_en`) separada de la fecha
  de sincronización. Las dos se guardan. Nunca se reescribe la fecha de la
  venta con la del envío.
- Avisa cuando hay ventas pendientes de enviar. Dos umbrales:
  **24 h → advertencia**, **48 h → alerta**. El panel y la caja lo muestran, y
  queda en la bitácora como evento `warning` y `error` respectivamente.

---

## 7. Bitácora de eventos (`eventos_operacion`)

Pensada para la entrega de la Semana 18: sin esta tabla, «monitoreo y gestión
de eventos» sería una afirmación sin respaldo.

| Nivel | Cuándo | Ejemplos |
|---|---|---|
| `info` | Operación normal | Venta sincronizada, catálogo descargado, caja abierta sin conexión |
| `warning` | Algo que hay que mirar, el sistema sigue | Venta duplicada reenviada, stock insuficiente resuelto como incidencia, 24 h con cola pendiente |
| `error` | Algo falló o incumple una regla | Venta rechazada, 48 h con cola pendiente, fallo al emitir el comprobante |

Campos: `nivel`, `codigo` (estable, para poder contar), `mensaje` (legible),
`contexto` (JSON), `origen` (`caja` / `api`), `dispositivo`, `usuario_id`,
`uuid_local`, `created_at`.

El `codigo` existe para que la Semana 18 pueda decir «37 eventos
`SYNC_DUPLICADA` en el periodo» sin tener que buscar por texto libre.

**Qué nunca entra en la bitácora:** contraseñas, tokens, el DNI completo del
cliente ni el contenido del comprobante. El contexto guarda identificadores,
no datos personales.

---

## 8. Catálogo maestro en la caja

Al iniciar sesión con conexión, la caja descarga el catálogo vendible (884
productos) y lo guarda en local. Sin eso no hay búsqueda offline.

Dos decisiones:

- Se descarga **con precio y presentaciones**, pero **sin stock**. El stock
  cambia con cada venta de cualquier caja y un número desactualizado en
  pantalla es peor que ninguno: invita a prometer lo que no hay. Sin conexión
  la pantalla muestra «stock no verificable».
- La descarga es **incremental por `updated_at`** a partir de la segunda vez.
  Bajar 884 filas completas en cada inicio de sesión gasta tráfico de Neon sin
  necesidad.

---

## 9. Autenticación sin conexión

Para poder abrir caja con el internet caído se guarda en local un **hash** de
la credencial del último usuario que inició sesión con conexión, con su rol y
una caducidad. Nunca la contraseña.

Límites, por si alguien los da por hechos: el acceso offline **no concede
permisos de administración**, caduca, y sólo habilita cobrar y consultar el
catálogo. Todo lo que toque configuración, usuarios o reportes exige conexión.

---

## 10. La capa local, pieza por pieza

Construida en `src/offline/` y `src/stores/offline.ts`.

| Archivo | Qué resuelve |
|---|---|
| `offline/tipos.ts` | La interfaz `AlmacenLocal`: ocho métodos en términos del negocio |
| `offline/almacenIndexedDB.ts` | Driver por defecto en el navegador |
| `offline/almacenMemoria.ts` | Último recurso y doble para las pruebas |
| `offline/almacen.ts` | Elige el driver; aquí entrará SQLite |
| `offline/bitacora.ts` | Diario local con los mismos códigos que el servidor |
| `stores/offline.ts` | Cola, estado de red, reintentos y umbrales |

### El orden al cobrar no es negociable

**Primero se guarda en local, después se intenta enviar.** Si se enviara
primero y la petición saliera pero la respuesta no llegara, la caja no sabría
si la venta entró y tampoco la tendría guardada: se habría perdido una venta
real. Guardando primero, el peor caso es un reenvío, que no cuesta nada
porque el servidor es idempotente.

### `duplicada` es un éxito, no un error

Es la trampa más fácil de caer en el cliente. Si la caja tratara esa respuesta
como fallo, la venta se quedaría en la cola reintentándose para siempre contra
un servidor que ya la tiene. Se marca como sincronizada igual que una
`creada`, y se le copia el correlativo que el servidor emitió entonces.

### Esperar significa esperar

`sincronizar()` no sale de vacío cuando ya hay un envío en curso: devuelve ese
mismo compromiso. Con un retorno vacío, un `await sincronizar()` podría
resolverse mientras el envío de verdad sigue a medias y el código de arriba
daría por terminado algo que no lo está.

### Umbrales de atraso

El store mide las horas desde la venta pendiente **más antigua** y expone
`nivelDeAtraso`: `ninguno`, `aviso` (24 h) y `alerta` (48 h). Cada umbral se
anota una sola vez por venta; sin esa guarda, el reintento de cada minuto
escribiría más de mil advertencias al día y la bitácora dejaría de servir.

El sistema **avisa, no decide**. Qué hacer con una cola de 48 horas lo decide
la persona que atiende, y los plazos legales siguen pendientes de confirmar
con el contador (sección 6).

### Cuando el almacén no está disponible

IndexedDB puede fallar: ventana privada, almacenamiento bloqueado por política
del equipo, cuota agotada. En ese caso se cae al driver de memoria para que la
caja pueda seguir cobrando, pero **la cola se pierde al cerrar la
aplicación**. El store lo expone en `almacenamientoDegradado` y la pantalla
debe decirlo en voz alta. Degradar en silencio sería peor que fallar, porque
nadie se enteraría hasta echar en falta las ventas.

### Qué viaja y qué no

La caja guarda nombre y precio de cada línea para poder **reimprimir el ticket
sin conexión**, pero al servidor solo le manda `producto_id`, `unidad_venta` y
`cantidad`. El precio bueno lo pone el servidor con su propia tabla; enviar el
precio local invitaría a que alguien lo tomara por válido.

---

## 11. Lo que todavía no está decidido

Se deja escrito para no fingir que está resuelto:

- El comportamiento exacto ante **dos cajas** operando offline a la vez con el
  mismo producto escaso. Hoy la segunda genera incidencia de inventario. Falta
  confirmar con el negocio si eso basta.
- El **plazo y la forma legal** del envío a SUNAT, pendiente de confirmación
  con el contador (sección 6).
- Si la **anulación** de una venta offline no sincronizada se resuelve en la
  caja o espera a la sincronización.
