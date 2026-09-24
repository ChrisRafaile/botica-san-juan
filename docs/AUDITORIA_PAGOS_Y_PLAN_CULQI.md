# Auditoría del almacenamiento de datos de tarjeta y plan de migración a pasarela

**Proyecto:** Botica San Juan
**Fecha:** 29 de agosto de 2026
**Estado:** auditoría e inventario de dependencias completados. **No se ha ejecutado ningún cambio destructivo.**

---

## 0. Corrección previa: el stack no es el que se declaró

El encargo describe el stack como *«Backend principal: Node.js + TypeScript … También existe un backend/módulos en Laravel + PHP»*. **Esto no coincide con el repositorio.** Verificado por inspección directa:

| Declarado | Real, verificado |
|---|---|
| Backend principal Node.js + TypeScript | **No existe backend Node.** El único backend es `botica-san-juan-backend`: Laravel 12.34.0 sobre PHP 8.4.13 |
| Módulos en Laravel + PHP | Correcto, pero es el backend **principal y único** |
| Frontend Vue.js | Correcto: Vue 3.5.22 + TypeScript + Vite. Node 24 aparece solo como *tooling* de compilación, no como servidor |
| PostgreSQL | Correcto: PostgreSQL 16 |
| Entornos desarrollo/staging y producción | **No verificado.** El repositorio solo tiene `.env` y `.env.example` para desarrollo local; no hay configuración de staging ni de producción, ni despliegue activo |

**Consecuencia práctica:** no hay que decidir entre Knex, Prisma o TypeORM. La migración es una **migración de Laravel**, y el SDK a evaluar es el de **PHP**, no el de Node. Igualmente, como no hay producción activa, el riesgo de «romper producción» hoy es nulo — pero el procedimiento se diseña como si la hubiera, para que siga siendo válido cuando exista.

Existe además un tercer componente que el encargo no menciona y que es **el origen del problema**: `legacy-php/`, el sistema monolítico original, aún versionado en el repositorio.

---

## 1. Por qué hay que eliminar `card_number`, `expiry_date` y `cvv`

Tres razones, en orden de gravedad.

**1. Almacenar el CVV está prohibido, sin excepción.** El estándar PCI-DSS permite almacenar el PAN (número de tarjeta) si está cifrado y con controles compensatorios, pero prohíbe de forma absoluta almacenar el código de verificación *después de autorizar la transacción* — cifrado, tokenizado o como sea. No hay configuración que lo vuelva aceptable. Por eso la respuesta correcta no es «ciframos el campo», sino «el campo no existe».

**2. Guardar el PAN convierte a la botica en entidad dentro del alcance PCI.** En el momento en que la base de datos contiene números de tarjeta, el alcance de cumplimiento deja de ser el cuestionario más simple (SAQ A, para comercios que delegan íntegramente la captura en un tercero) y pasa a uno que exige segmentación de red, cifrado en reposo, gestión de claves y auditoría. Para una botica independiente ese costo es inasumible, y el beneficio es cero: **no se necesita el número para nada**.

**3. Los datos ya se filtraron una vez, en este mismo proyecto.** La captura de `GET /api/pedidos` que se incluyó en el informe APF1 mostraba `card_number`, `cvv` y `expiry_date` en claro, porque el endpoint devuelve el modelo Eloquent completo. Un dato que no se guarda no se puede filtrar.

### ¿A qué se reemplaza?

A **una referencia opaca al pago**, emitida por la pasarela. El sistema deja de custodiar el instrumento de pago y pasa a custodiar únicamente el *comprobante de que alguien cobró*:

| Se elimina | Se guarda en su lugar | Para qué sirve |
|---|---|---|
| `card_number` | `card_brand` + `card_last4` | Que el cliente reconozca con qué pagó («Visa terminada en 4242») |
| `cvv` | *(nada)* | No tiene ningún uso posterior a la autorización |
| `expiry_date` | *(nada)* | Solo lo necesita quien procesa el cobro, no el comercio |
| — | `provider`, `provider_payment_id`, `status`, `payment_method`, `amount`, `currency`, `paid_at`, `failure_code`, `failure_message` | Conciliar, reembolsar, auditar y responder reclamos |

Con esas columnas se puede hacer todo lo que el negocio necesita —conciliar contra el estado de cuenta, emitir el comprobante, atender un reclamo, pedir un reembolso— sin custodiar un solo dígito sensible.

---

## 2. Inventario de dependencias (lo que pediste antes de tocar nada)

Búsqueda exhaustiva de `card_number`, `cardNumber`, `cvv`, `cvc`, `security_code`, `expiry_date`, `expiration_date`, `expirationDate` sobre `app/`, `database/`, `routes/`, `tests/`, `src/` (Vue), `legacy-php/` y `scripts/`.

### 2.1. Dónde aparecen

| # | Archivo | Qué hace | Riesgo al eliminar |
|---|---|---|---|
| 1 | `database/migrations/2025_10_19_043911_create_pedidos_table.php` | Crea las tres columnas | Ninguno: se corrige con una migración nueva, no editando esta |
| 2 | `database/seeders/PedidoSeeder.php` | **Contiene números de tarjeta y CVV escritos a mano en el repositorio** (líneas 23-36) | Ninguno: son datos de siembra |
| 3 | `legacy-php/cart/checkout.php` | Formulario HTML que captura número, CVV y vencimiento | Ninguno: el checkout legacy ya fue reemplazado por la SPA |
| 4 | `legacy-php/cart/confirmation.php` | `INSERT INTO pedidos (…, card_number, expiry_date, cvv)` | Ninguno: mismo motivo |
| 5 | Tabla `pedidos` en PostgreSQL | Los datos | Se pierden 26 filas de datos de prueba; los pedidos **no** se pierden |

### 2.2. Dónde NO aparecen — este es el hallazgo decisivo

| Ámbito | Resultado |
|---|---|
| `botica-san-juan-backend/app/` (controladores, modelos, servicios, middleware) | **Cero coincidencias.** Ningún controlador lee, escribe ni valida esos campos |
| `botica-san-juan-backend/routes/` | **Cero.** Ningún endpoint los recibe |
| `botica-san-juan-frontend/src/` | **Cero.** Ningún componente Vue los captura ni los almacena |
| `botica-san-juan-backend/tests/` | **Cero.** Ninguna prueba depende de ellos |
| `app/Models/Pedido.php` → `$fillable` | No los incluye: son **inasignables en masa** |

**Conclusión:** son **columnas muertas**. El sistema activo (Laravel + Vue) no las escribe ni las lee deliberadamente; solo las arrastra hacia afuera porque `PedidoController@index` y `@show` devuelven el modelo completo. Heredadas del checkout monolítico que ya no se usa.

Esto reduce la migración de «riesgosa» a **trivial en código y solo destructiva en datos**.

### 2.3. Endpoints afectados

- `GET /api/pedidos` y `GET /api/pedidos/{id}` — dejarán de exponer los tres campos. Ningún consumidor los usa.
- `GET /api/pedidos/usuario/{id}` — igual.
- Ningún endpoint de escritura se ve afectado.

### 2.4. Rollback

1. La migración incluye `down()` que recrea las tres columnas *vacías*. La estructura se restituye; **los valores no**, y eso es deliberado.
2. Antes de ejecutar: `pg_dump -Fc` con el script que ya existe en `infra/backup/backup_postgres.ps1`. Si hiciera falta recuperar un valor para una investigación, sale del respaldo, no de producción.
3. Como no hay producción activa, la ventana de riesgo real es cero.

### 2.5. Mitigación inmediata, sin esperar a la pasarela

Independientemente de Culqi, hay dos acciones que se pueden aplicar hoy y que no rompen nada:

- Añadir `card_number`, `cvv` y `expiry_date` a `$hidden` en `App\Models\Pedido` — corta la fuga por la API en una línea, aunque las columnas sigan existiendo.
- Vaciar los valores del seeder y sobrescribir los de la base con `NULL`.

---

## 3. Culqi: lo que dice la documentación oficial y dónde contradice el encargo

Consultada la documentación oficial vigente. **Dos contradicciones que hay que resolver antes de escribir código.**

### ⚠️ Contradicción 1 — Checkout v4 está en retirada

El encargo pide evaluar «Checkout v4». La propia página de Culqi Checkout v4 advierte:

> «Culqi Checkout V4 dejará de estar disponible pronto», y recomienda migrar a **Checkout Custom**.

Integrar sobre v4 sería construir sobre una pieza anunciada como descontinuada. **Recomendación: Checkout Custom.**

### ⚠️ Contradicción 2 — Culqi no publica firma de webhook

El encargo exige «validación de autenticidad/firma del webhook según la documentación oficial». La documentación de webhooks de Culqi describe cómo crearlos en CulqiPanel y qué eventos existen (Tokens, Cargos, Devoluciones, Clientes, Tarjetas, Planes, Suscripciones, Órdenes), **pero no publica ninguna cabecera de firma HMAC ni un procedimiento de verificación criptográfica.**

No puedo implementar una validación de firma que el proveedor no documenta, y tampoco voy a inventarla. La alternativa profesional, y la que recomiendo, es **no confiar nunca en el cuerpo del webhook**:

1. URL de webhook con un segmento secreto largo y aleatorio, fuera del repositorio.
2. Lista blanca de IP de origen si Culqi las publica para tu cuenta.
3. **El webhook solo actúa como disparador.** Al recibirlo, el backend **vuelve a consultar el cargo o la orden contra la API de Culqi con la llave secreta**, y decide según *esa* respuesta. La fuente de verdad es la API, nunca el payload entrante.

Este patrón es más robusto que verificar una firma, porque resiste incluso a que alguien conozca la URL secreta.

### 3.1. Comparación de modalidades

| Modalidad | Dónde se teclea la tarjeta | Alcance PCI | Personalización | Veredicto |
|---|---|---|---|---|
| **Checkout Custom** (`https://js.culqi.com/checkout-js`) | Componente de Culqi, tokeniza automáticamente | Mínimo | CSS, fuentes, imágenes, `container` para incrustarlo | **Recomendada** |
| Checkout v4 (`https://checkout.culqi.com/js/v4`) | Componente de Culqi | Mínimo | Limitada a `style` | Descartada: en retirada |
| API directa / tokenización manual | **Tu formulario** → tu servidor ve el PAN | **Máximo** — te mete de lleno en PCI | Total | **Descartada** por el objetivo declarado |

### 3.2. Lo que Checkout Custom ofrece, según la documentación

- **Script:** `<script src="https://js.culqi.com/checkout-js"></script>`
- **Configuración:** `settings` (`currency`, `amount`, `order`, `title`), `client` (`email`), `options` (`lang`, `modal`, `installments`, `container`, `paymentMethods`, `paymentMethodsSort`)
- **Métodos de pago en Perú:** tarjetas de débito y crédito, **Yape**, **PagoEfectivo**, billeteras móviles, banca móvil, agentes y bodegas, **Cuotéalo BCP**
- **Retorno al comercio:** callback `handleCulqiAction()`, con `Culqi.token.id`, `Culqi.order` y `Culqi.error`
- **Claves:** la **llave pública** va en el frontend; la **secreta** solo en el backend

> Nota sobre `amount`: Culqi trabaja el monto en la unidad mínima de la moneda (céntimos para PEN). Es un punto a confirmar contra la referencia de la API antes de codificar, porque un error de factor 100 en producción es caro.

### 3.3. Lo que aún falta confirmar

`apidocs.culqi.com` es una aplicación JavaScript que no se puede leer sin renderizarla, de modo que **no pude extraer de fuente oficial**: la URL base exacta de la API, los nombres literales de los estados de un cargo y de una orden, la política de reintentos de los webhooks, y la estructura exacta del payload. **No los voy a inventar.** Se resuelven abriendo esa página en el navegador antes de implementar; puedo hacerlo contigo en la siguiente sesión.

---

## 4. Arquitectura propuesta

### 4.1. Modelo de datos

Se separa el pago del pedido. Nombres en español, siguiendo la convención que ya usa el proyecto (`pedidos`, `pedido_detalles`, `comprobantes_electronicos`).

**`pedidos`** — se le quitan tres columnas y se le añade una:

```
id, usuario_id, fecha_pedido, total, moneda, estado, estado_pago, address,
created_at, updated_at
- card_number    ← ELIMINADA
- expiry_date    ← ELIMINADA
- cvv            ← ELIMINADA
```

**`pagos`** — tabla nueva:

```
id
pedido_id            FK → pedidos
proveedor            'culqi'
proveedor_pago_id    id del cargo u orden en Culqi   UNIQUE
estado               pendiente|procesando|pagado|fallido|cancelado|reembolsado
metodo_pago          tarjeta|yape|pago_efectivo|billetera|cuotealo
marca_tarjeta        Visa | Mastercard | …           NULL salvo tarjeta
ultimos4             4 dígitos                        NULL salvo tarjeta
monto                NUMERIC(10,2)
moneda               'PEN'
pagado_en            TIMESTAMP NULL
codigo_error         NULL
mensaje_error        texto saneado, NULL
created_at, updated_at
UNIQUE (proveedor, proveedor_pago_id)
```

**`pagos_eventos`** — bitácora de webhooks, y la pieza que da idempotencia:

```
id
evento_id            identificador del evento en Culqi   UNIQUE  ← clave de idempotencia
pago_id              FK → pagos, NULL si aún no se resolvió
tipo                 nombre del evento
payload              JSONB, sin datos sensibles
procesado_en         TIMESTAMP NULL
created_at
```

`UNIQUE(evento_id)` es lo que hace la idempotencia **estructural** y no dependiente de la lógica: si el mismo webhook llega dos veces, el segundo `INSERT` viola la restricción, se captura la violación y se responde `200` sin volver a procesar. No hay ventana de carrera.

### 4.2. Máquina de estados

```
                 ┌──────────────┐
                 │  pendiente   │  pedido creado, aún sin intento de cobro
                 └──────┬───────┘
                        │ se crea el cargo/orden en Culqi
                 ┌──────▼───────┐
                 │  procesando  │  esperando confirmación del proveedor
                 └──┬────────┬──┘
        webhook OK  │        │  webhook rechazo / timeout
                 ┌──▼───┐ ┌──▼──────┐
                 │pagado│ │ fallido │──→ reintento: vuelve a pendiente
                 └──┬───┘ └─────────┘
                    │ devolución confirmada
              ┌─────▼────────┐
              │ reembolsado  │
              └──────────────┘

  cancelado: el usuario abandona el checkout. Nunca llega a "pagado".
```

Los nombres de los estados de Culqi se traducen a estos en un **mapeo centralizado**, en un solo archivo. El resto del sistema nunca ve la nomenclatura del proveedor. Ese mapeo se completa cuando se confirmen los nombres literales (§3.3).

### 4.3. Flujo

```
Cliente → SPA Vue                    Backend Laravel              Culqi
   │                                       │                        │
   │ 1. POST /api/pedidos/confirmar        │                        │
   │──────────────────────────────────────>│                        │
   │                        el backend calcula el total del catálogo│
   │                        crea pedido (pendiente) y pago (pendiente)
   │<──── pedido_id, monto, llave pública ─│                        │
   │                                       │                        │
   │ 2. Checkout Custom de Culqi           │                        │
   │───────────── datos de tarjeta ────────┼───────────────────────>│
   │                        (nunca pasan por nuestro backend)       │
   │<──────────── token.id / order ────────┼────────────────────────│
   │                                       │                        │
   │ 3. POST /api/pagos/{id}/cobrar (token)│                        │
   │──────────────────────────────────────>│── crear cargo ────────>│
   │                                       │<── id del cargo ───────│
   │<──── 202, estado "procesando" ────────│                        │
   │                                       │                        │
   │ 4. /checkout/procesando               │<══ WEBHOOK ════════════│
   │      consulta el estado real          │  reconsulta a la API   │
   │──────────────────────────────────────>│───────────────────────>│
   │<──── pagado | fallido | pendiente ────│                        │
```

El monto se calcula **siempre** en el paso 1, en el servidor, a partir de `productos.precio`. Si la SPA envía un `amount`, se ignora.

### 4.4. Variables de entorno

```
CULQI_PUBLIC_KEY=      # frontend, expuesta por diseño
CULQI_SECRET_KEY=      # solo backend, nunca en Vue ni en el repositorio
CULQI_WEBHOOK_PATH=    # segmento secreto de la URL del webhook
CULQI_ENV=test|live
```

Los nombres definitivos se ajustan a los que exija el SDK oficial de PHP.

---

## 5. Lo que sí quedó implementado hoy

Estas mejoras ya están aplicadas y verificadas, y son independientes de la pasarela:

| Mejora | Estado |
|---|---|
| Descarga de XML y PDF del comprobante, listado de documentos y comisiones: pasan a exigir token | ✅ Verificado en ejecución: 401 sin token, 200 con token |
| Exportación gerencial en CSV: exige rol administrador, no solo estar autenticado | ✅ |
| Descuento de stock al confirmar la venta, con transacción, bloqueo pesimista y validación de existencias (hallazgo crítico C-02) | ✅ `POST /api/pedidos/confirmar` — stock 5 → 3 verificado |
| El importe lo calcula el servidor: un `total` enviado desde el cliente se ignora | ✅ Con prueba automatizada |
| Envío del comprobante a SUNAT de forma asíncrona, con cola, reintentos escalonados e idempotencia | ✅ `POST /api/facturacion/documentos/{id}/enviar-sunat-async` → 202 → cola → aceptada con ticket |
| Cobertura de pruebas | 47 → **63 pruebas, 141 aserciones**, todas en verde |

---

## 6. Checklist de seguridad de pagos

- [x] Inventario de dependencias de los campos sensibles
- [x] Confirmado que ningún código activo los lee ni los escribe
- [x] Confirmado que ninguna prueba depende de ellos
- [x] Documentación oficial de Culqi consultada; contradicciones reportadas
- [x] Modalidad de checkout elegida con el criterio de minimizar el alcance PCI
- [ ] Confirmar la URL base de la API, los estados literales y el payload del webhook
- [ ] `$hidden` en el modelo `Pedido` *(mitigación inmediata, 1 línea)*
- [ ] Respaldo `pg_dump -Fc` previo a la migración
- [ ] Migración que elimina las tres columnas
- [ ] Limpieza de `PedidoSeeder.php`
- [ ] Retiro del checkout de `legacy-php/`
- [ ] Tabla `pagos` y tabla `pagos_eventos`
- [ ] Endpoint de webhook con reconsulta a la API como fuente de verdad
- [ ] Vistas de checkout en Vue: pago, procesando, exitoso, error, cancelado, pendiente
- [ ] Las 15 pruebas de pago del encargo

---

## 7. Qué necesito de ti para continuar

1. **¿Confirmas Checkout Custom en lugar de v4?** La documentación de Culqi desaconseja v4.
2. **¿Aceptas el esquema de webhook sin firma** —URL secreta más reconsulta a la API— dado que Culqi no publica un mecanismo de firma?
3. **¿Tienes cuenta en CulqiPanel** con llaves de prueba? Sin ellas la integración no se puede verificar de extremo a extremo, solo escribir.
4. **¿Confirmas eliminar las tres columnas?** Se pierden 26 filas de datos de tarjeta de prueba; los 32 pedidos se conservan íntegros.
