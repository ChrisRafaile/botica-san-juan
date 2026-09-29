# Arquitectura de pagos — Botica San Juan

**Proveedor:** Izipay (plataforma Lyra / micuentaweb)
**Backend:** Laravel 12 · PHP 8.4 · PostgreSQL 16 · **Frontend:** Vue 3 + TypeScript
**Fecha:** 29 de agosto de 2026
**Estado:** implementado y verificado en modo simulado. Migración destructiva **preparada, no ejecutada**.

---

## 1. Por qué Izipay y no Culqi ni Niubiz

Comparación verificada contra documentación oficial, no contra memoria.

| Criterio | **Izipay** | Culqi | Niubiz |
|---|---|---|---|
| **Firma de webhook documentada** | ✅ **HMAC-SHA-256**: `kr-hash` contra `hash_hmac('sha256', kr-answer, clave)` | ❌ Documenta los eventos, no publica cabecera de firma ni verificación criptográfica | ⚠️ No verificable: el portal de desarrolladores bloquea el acceso automatizado |
| **Dos claves separadas** | ✅ `sha256_key` para la respuesta del navegador, `password` para el IPN | — | — |
| **Repositorio oficial de Laravel** | ✅ `izipay-pe/Server-PaymentForm-Laravel` y `PopIn-PaymentForm-Laravel` | ❌ SDK oficial de PHP, sin ejemplo de Laravel | ❌ Solo implementaciones de la comunidad |
| **Captura de tarjeta** | Formulario embebido del proveedor → alcance PCI mínimo | Checkout Custom, equivalente | Depende de la modalidad |
| **Estado del producto** | Vigente | **Checkout v4 en retirada** por decisión del propio proveedor | Vigente |
| **Métodos en Perú** | Tarjeta, Yape, cuotas | Tarjeta, Yape, PagoEfectivo, billeteras, Cuotéalo | Tarjeta principalmente |

**Culqi tiene mejor catálogo de métodos de pago.** Es su ventaja real y conviene decirlo. Pero pierde en los dos criterios que pesan más para este proyecto: no publica un mecanismo de firma, y su Checkout v4 está anunciado como descontinuado. Si más adelante PagoEfectivo o Cuotéalo se vuelven necesarios, la arquitectura implementada permite añadir Culqi como segunda implementación de `PasarelaPago` sin tocar el dominio.

**Niubiz no se descarta por calidad, sino por falta de evidencia.** Es el adquirente dominante del país, pero no pude leer su documentación oficial y no voy a describir una integración que no verifiqué.

### ¿Es mejor tener firma publicada? Sí, y por una razón concreta

Sin firma, la única defensa es el secreto de la URL. Ese es un secreto *de portador*: quien lo ve, lo tiene. Y termina viéndose — en logs de proxy inverso, en historiales de configuración, en capturas de pantalla, en el panel del proveedor.

Con HMAC, cada mensaje se autentica por separado. Conocer la URL no basta: hay que poseer la clave, que nunca viaja. Y como la firma cubre el contenido, tampoco se puede alterar un `orderStatus` en tránsito.

**Matiz importante:** la firma prueba autenticidad e integridad, no *frescura*. Un mensaje legítimo capturado y reenviado sigue teniendo firma válida. Por eso la implementación combina tres capas y no una sola:

```
firma HMAC  →  autenticidad e integridad
idempotencia por UNIQUE  →  el replay no produce efecto
reconsulta a la API (modo real)  →  frescura y verdad
```

---

## 2. Modelo de datos

### Lo que se eliminó del pedido

```
pedidos
  - card_number    ← columna muerta, migración preparada
  - expiry_date    ← columna muerta, migración preparada
  - cvv            ← columna muerta, migración preparada
```

Mitigación ya activa: `App\Models\Pedido::$hidden` corta la fuga por la API **hoy**, aunque las columnas sigan existiendo. Antes de este cambio, `GET /api/pedidos` devolvía el número de tarjeta y el CVV en cada respuesta.

### Lo que se creó

**`pagos`** — la referencia opaca del cobro:

| Columna | Para qué |
|---|---|
| `proveedor`, `proveedor_pago_id` | Identificar el cobro ante el proveedor · `UNIQUE(proveedor, proveedor_pago_id)` |
| `referencia_pedido` | Identificador propio, el que viaja al proveedor · `UNIQUE` |
| `estado` | Máquina de estados interna |
| `metodo_pago`, `marca_tarjeta`, `ultimos4` | Que el cliente reconozca su pago |
| `monto`, `moneda`, `pagado_en` | Conciliar |
| `codigo_error`, `mensaje_error` | Atender un reclamo, con mensaje saneado |

**No contiene, deliberadamente:** número completo de tarjeta, CVV ni fecha de vencimiento completa.

**`pagos_eventos`** — bitácora e idempotencia:

```sql
evento_id  VARCHAR UNIQUE   ← la clave de todo
```

Esa restricción es lo que hace la idempotencia **estructural**. Una comprobación previa del tipo «si ya está pagado, no hagas nada» deja una ventana entre dos entregas simultáneas del mismo webhook. El `UNIQUE` no la deja: el segundo `INSERT` viola la restricción, se captura y se responde `200` sin volver a aplicar el efecto. La garantía es de PostgreSQL, no del orden de ejecución.

**`pedidos.estado_pago`** — separa el estado logístico del estado del cobro. Un pedido puede estar confirmado y su pago pendiente; esa combinación tiene que poder representarse.

---

## 3. Máquina de estados

```
                 ┌──────────────┐
                 │  pendiente   │
                 └──────┬───────┘
                        │ se crea la operación
                 ┌──────▼───────┐
                 │  procesando  │
                 └──┬────────┬──┘
                    │        │
              ┌─────▼──┐  ┌──▼──────┐
              │ pagado │  │ fallido │──→ reintento
              └─────┬──┘  └─────────┘
                    │
            ┌───────▼───────┐        cancelado ← abandono
            │  reembolsado  │
            └───────────────┘
```

Reglas que el código impone (`EstadoPago::permiteTransicion`):

- Desde `pagado` **solo** se avanza a `reembolsado`. Una notificación tardía de rechazo no revierte un cobro confirmado.
- Un `orderStatus` desconocido **nunca** se interpreta como pagado: cae en `procesando`, para que la reconsulta lo resuelva.
- Los estados finales no admiten retroceso.

El mapeo desde la nomenclatura de Izipay vive en un único archivo. El resto del sistema nunca ve `PAID` ni `UNPAID`.

---

## 4. Flujo

```
Cliente → SPA Vue                      Backend Laravel                Izipay
   │                                          │                          │
   │ 1. POST /api/pedidos/confirmar           │                          │
   │─────────────────────────────────────────>│                          │
   │            el servidor calcula el total del catálogo                │
   │            y descuenta stock en una transacción                     │
   │<──── pedido, total ──────────────────────│                          │
   │                                          │                          │
   │ 2. POST /api/pedidos/{id}/pago           │                          │
   │─────────────────────────────────────────>│── CreatePayment ────────>│
   │<──── formToken + llave PÚBLICA ──────────│<── formToken ────────────│
   │                                          │                          │
   │ 3. Formulario embebido de Izipay         │                          │
   │──────────── datos de tarjeta ────────────┼─────────────────────────>│
   │            (nunca pasan por el backend)  │                          │
   │                                          │                          │
   │ 4. /checkout/procesando                  │<══ IPN firmado ══════════│
   │      consulta el estado real             │  verifica HMAC           │
   │─────────────────────────────────────────>│  deduplica por UNIQUE    │
   │                                          │  reconsulta a la API     │
   │<──── pagado | fallido | pendiente ───────│                          │
```

**El importe se fija en el paso 1**, en el servidor, desde `productos.precio`. Un `amount` o un `monto` enviados por el navegador se ignoran: hay una prueba que lo demuestra.

---

## 5. Endpoints

| Método | Ruta | Protección |
|---|---|---|
| `POST` | `/api/pedidos/confirmar` | Token · calcula total y descuenta stock |
| `POST` | `/api/pedidos/{pedido}/pago` | Token · solo el dueño del pedido o un administrador |
| `GET` | `/api/pagos/{referencia}/estado` | Token · solo el dueño o un administrador |
| `POST` | `/api/pagos/notificacion/{secreto}` | Segmento secreto + firma HMAC + reconsulta |

La notificación no puede exigir token porque quien la invoca es el proveedor. Sus tres capas de protección compensan esa apertura.

---

## 6. Frontend

| Ruta | Vista | Qué hace |
|---|---|---|
| `/checkout/pago` | `CheckoutPagoView` | Resumen, subtotal, IGV, total, contenedor del formulario del proveedor |
| `/checkout/procesando` | `CheckoutProcesandoView` | Sondeo con espera creciente contra el backend |
| `/checkout/exitoso` · `/error` · `/cancelado` · `/pendiente` | `CheckoutResultadoView` | Un componente, cuatro rutas |

Decisiones que conviene subrayar:

- **Ningún componente Vue captura número de tarjeta, CVV ni vencimiento.** Esos campos pertenecen al formulario de Izipay. No se guardan en Pinia, ni en `localStorage`, ni en la URL.
- **Las pantallas de resultado no deciden nada.** Cada una consulta `/api/pagos/{ref}/estado` al montarse y, si la ruta no corresponde al estado real, corrige la URL. Navegar a mano a `/checkout/exitoso` no convierte un pedido en pagado.
- **Mapeo centralizado** en `src/services/pagos.ts`: estado del backend → estado visual → ruta. Ningún componente compara cadenas por su cuenta.
- **Accesibilidad:** `aria-live` en los cambios de estado, `role="progressbar"` en el indicador, `role="alert"` en los errores, y las animaciones respetan `prefers-reduced-motion` — sin movimiento se muestra el estado final, no una versión lenta.
- **Doble cobro:** el botón se deshabilita mientras la operación corre, pero esa es una cortesía visual. La protección real está en el backend, que reutiliza el pago existente del pedido dentro de una transacción con bloqueo.

---

## 7. Configuración

```bash
IZIPAY_MODE=simulado          # 'api' para el proveedor real
IZIPAY_BASE_URL=https://api.micuentaweb.pe
IZIPAY_USERNAME=              # solo backend
IZIPAY_PASSWORD=              # solo backend · firma del IPN
IZIPAY_PUBLIC_KEY=            # única que llega al navegador
IZIPAY_SHA256_KEY=            # solo backend · firma de la respuesta del navegador
IZIPAY_WEBHOOK_PATH=          # segmento secreto, largo y aleatorio
IZIPAY_CURRENCY=PEN
```

**Modo simulado.** Sigue el mismo patrón que el `SunatClient` que ya existía: resuelve las operaciones localmente y firma sus propias notificaciones con una clave derivada de `APP_KEY`, de modo que la verificación de firma sea **real** aunque no haya credenciales. Cuando consigas las llaves, solo cambias el `.env`. No se toca código.

Una prueba verifica explícitamente que la respuesta al navegador nunca contenga las claves privadas.

---

## 8. Verificación

### Suite automatizada — 80 pruebas, 198 aserciones, todas en verde

Las 17 nuevas de pagos cubren: token de formulario, no exposición de llaves privadas, pedido ajeno, doble intento, notificación válida, firma inválida, segmento secreto incorrecto, notificación duplicada, notificación fuera de orden, rechazo, abandono, referencia desconocida, saneamiento del evento, autorización de la consulta, autoridad del servidor sobre el importe, estado desconocido, y no exposición de los campos de tarjeta heredados.

### Ejecución real contra el sistema en marcha

```
1) pedido 34 · total S/ 2.00 · estado_pago=pendiente
2) pago BSJ-00000034-yibvol · estado=procesando · modo=simulado
   ¿la respuesta contiene la llave privada? False
a) firma inválida          → 401
b) segmento secreto malo   → 404
c) notificación válida     → 200
d) notificación repetida   → 200 · "Evento ya procesado."
e) estado final            → pagado · VISA ****4242 · final=True
```

---

## 9. Lo que queda pendiente

1. **Confirmar contra la referencia oficial de la API**: la ruta de consulta de una operación y los nombres literales de todos los `orderStatus`. `IzipayClient::consultarOperacion()` está escrito para no inventar: en modo real devuelve un resultado no concluyente, y el servicio lo interpreta como «no confirmado», de modo que **nunca marca un pedido como pagado sin evidencia**.
2. **Verificar la unidad del importe.** Está implementado en céntimos (`amount = monto × 100`), que es la convención de la plataforma. Un error de factor 100 en producción es caro: conviene confirmarlo con una transacción de prueba real.
3. **Ejecutar la migración destructiva.** Está preparada en `database/migrations-pendientes/` con extensión `.pendiente` para que `php artisan migrate` no la tome por accidente:

   ```powershell
   .\infra\backup\backup_postgres.ps1
   Move-Item database\migrations-pendientes\2026_08_29_110000_drop_card_columns_from_pedidos_table.php.pendiente `
             database\migrations\2026_08_29_110000_drop_card_columns_from_pedidos_table.php
   php artisan migrate --force
   ```

4. **Retirar `legacy-php/cart/`**, el checkout monolítico que capturaba los datos de tarjeta. Ya no lo usa nadie.
5. **Montar el formulario de Izipay** sobre `#izipay-container` en `CheckoutPagoView`, cuando haya `form_token` y `public_key` reales.

---

## 10. Checklist de seguridad

- [x] Ningún componente del frontend captura número de tarjeta, CVV ni vencimiento
- [x] Ninguna clave privada llega al navegador — con prueba que lo verifica
- [x] La firma HMAC se compara con `hash_equals` (tiempo constante)
- [x] El segmento secreto de la ruta también se compara en tiempo constante
- [x] Idempotencia garantizada por restricción de base de datos, no por lógica
- [x] Los eventos se persisten saneados: `pan`, `cvv`, `expiryMonth` y afines se descartan
- [x] Los registros contienen referencia, estado y ticket; nunca el cuerpo con datos de tarjeta
- [x] El importe lo determina el servidor
- [x] Un usuario no puede iniciar ni consultar el pago de un pedido ajeno
- [x] Un estado desconocido del proveedor nunca se interpreta como pagado
- [x] `$hidden` corta la fuga de los campos heredados mientras la migración espera
- [x] Números de tarjeta retirados del seeder versionado (26 registros)
- [ ] Migración destructiva ejecutada
- [ ] `legacy-php/cart/` retirado
- [ ] Credenciales reales de prueba y transacción de extremo a extremo
