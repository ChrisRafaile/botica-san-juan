# Bloque 3: correlativo transaccional y comprobante UBL 2.1 firmado

**Estado:** análisis de arquitectura. No se ha implementado ni modificado código de producción.
**Alcance:** hallazgo C-03 de `AUDITORIA_Y_PLAN.md` (línea 46) y puntos 11 y 12 del Bloque 3 (líneas 124-126).
**Fecha del análisis:** 18/10/2026, contra el código del repositorio en el commit `727e0ae`.

Convención: todo lo marcado **(a verificar)** es un dato de SUNAT que no he podido respaldar con una fuente oficial leída en esta sesión. Debe contrastarse con la Guía de elaboración de comprobantes UBL 2.1 y el manual del servicio billService vigentes antes de escribir código que dependa de él. No hay acceso a documentación de SUNAT desde este análisis, así que los códigos de catálogo que cito salen de mi conocimiento general y se verifican antes de fijarlos en tests.

---

## 0. Resumen ejecutivo

1. **C-03 está corregido solo a medias.** La ruta principal (`ComprobanteService::emitir`, usada por el portal y el POS) ya no usa `max()` y sí bloquea con `lockForUpdate`, pero el diseño sigue dependiendo de un reintento y de la violación de `UNIQUE(serie, numero)`. La ruta de administración `generarDesdePedidos` conserva el patrón viejo, ahora con un `lockForUpdate()` puesto sobre un `max()` que PostgreSQL no admite. No hay ninguna prueba de concurrencia real. Detalle en la sección 1.2.
2. **No existe nada de UBL, firma ni envío real.** El "XML" de `descargarXml` es un XML inventado (`<ComprobanteElectronico>`), `SunatClient` está en modo simulado con 88 % de aceptación aleatoria, y el `hash_documento` se calcula con `now()->timestamp`. Es honesto: el propio `ComprobanteService` lo declara en su docblock (líneas 14-24).
3. **Hay deuda que bloquea el go-live fiscal, independiente de UBL:** nadie consume la cola en producción, el job nunca reintenta, el PDF recalcula el IGV dividiendo todo entre 1.18 (ignora lo exonerado) y el QR no sigue el formato de SUNAT.
4. **Recomendación de correlativo:** tabla `series_comprobante` con `lockForUpdate` dentro de la misma transacción que crea el comprobante. Sin huecos por rollback, compatible con Neon, y se descarta la secuencia PostgreSQL (sección 2).
5. **Recomendación de UBL:** usar una librería madura (Greenter es la candidata) para XML, firma y SOAP, en vez de escribirlos a mano, y envolverla detrás de una interfaz propia. La decisión OSE frente a SUNAT directa depende del dueño (sección 5).
6. **Esfuerzo:** 3 a 4 sprints de dos semanas con una persona, más tiempo de espera externo (certificado, RUC, credenciales SOL). El plan original de dos sprints (5 y 6) es optimista salvo que se contrate un OSE/PSE.

---

## 1. Diagnóstico del estado real del código

La auditoría es del 25 de agosto; el código ha cambiado bastante desde entonces. Lo que sigue es lo que hay hoy.

### 1.1 Mapa de piezas

| Pieza | Dónde | Qué hace hoy |
|---|---|---|
| Emisión interna | `botica-san-juan-backend/app/Services/ComprobanteService.php` | Asigna serie y número, liga al pedido. Llamado desde `PedidoController.php:186` tras `VentaService::registrar`. No firma, no genera XML, no envía (docblock, líneas 14-24). |
| Serie y tipo | `ComprobanteService.php:47-48, 125-134` | Series fijas `B001`/`F001` como constantes. RUC de 11 dígitos, o `cliente_tipo_documento = 'ruc'`, produce factura. |
| Modelo | `app/Models/ComprobanteElectronico.php` | Tabla `comprobantes_electronicos`. Sin relación con una tabla de series. |
| Esquema | `database/migrations/2026_04_08_002400_create_comprobantes_electronicos_table.php` | `tipo_comprobante` enum `boleta\|factura` (línea 16), `estado_sunat` enum `pendiente\|aceptada\|rechazada` (línea 22), `UNIQUE(serie, numero)` (línea 31). **No hay UNIQUE sobre `pedido_id`.** |
| Campos SUNAT | `database/migrations/2026_04_08_003200_add_sunat_and_commission_fields_to_comprobantes_table.php` | `sunat_ticket`, `sunat_payload`, `sunat_response` (json), `monto_comision`, `estado_comision`. |
| Controlador | `app/Http/Controllers/FacturacionController.php` | Listado, `generarDesdePedidos` (:67), `enviarSunat` síncrono (:88), `encolarEnvioSunat` (:136), `descargarXml` (:159), `descargarPdf` (:178). |
| Cliente SUNAT | `app/Services/SunatClient.php` | Modo `simulado` por defecto (:207); aleatorio con 88 % de aceptación (:259). Modo `api` hace un POST JSON con bearer a un `base_url` genérico (:226-229), que no es el protocolo de SUNAT. |
| Job | `app/Jobs/EnviarComprobanteASunat.php` | `ShouldQueue` + `ShouldBeUnique`, `tries = 3`, `backoff [10, 60, 300]` (:310-313), unicidad por comprobante (:324-327). |
| Desglose fiscal | `app/Services/DesgloseFiscalService.php` | Fuente única de base, IGV, exonerado e inafecto; el IGV se extrae del precio (los precios ya lo incluyen) y sale por resta (:72-94). |
| Datos de impresión | `ComprobanteService::datosParaImpresion` (:147-193) | Ya entrega los importes pensados "para armar el XML". Hoy solo se usa para impresión. |
| Cola | `config/queue.php:16`, `render.yaml` | `QUEUE_CONNECTION=database`. |
| Empresa | `config/company.php` | RUC `20123456789` y datos de relleno por defecto. Sin ubigeo ni código de establecimiento. |
| Tasa IGV | `config/inventario.php` (`tasa_igv`, 0.18) | Se guarda además en el pedido (`pedidos.tasa_igv`). |

### 1.2 Estado de C-03: parcialmente corregido

La auditoría decía: `FacturacionController.php` línea 185 usa `->max('numero')` + 1 fuera de transacción. Hoy:

**Lo que sí se corrigió (ruta del portal y del POS):**
- `ComprobanteService.php:83-104` envuelve la asignación en `DB::transaction` y bloquea la fila más alta de la serie con `orderByDesc('numero')->lockForUpdate()->first()` (:88-91). El comentario de las líneas 84-87 documenta correctamente que PostgreSQL rechaza `FOR UPDATE` con agregados.
- Hay reintentos (:81-113, tres intentos) que convierten la violación 23505 en reintento (:109).
- `emitir` es idempotente por pedido (:64-67).
- Hay una prueba del correlativo (`tests/Feature/CheckoutPortalTest.php:215`, tres emisiones seguidas con números distintos) y una de idempotencia (:234).

**Por qué no lo considero cerrado:**

1. **El diseño no previene la carrera, solo la detecta y reintenta.** Con `ORDER BY numero DESC LIMIT 1 FOR UPDATE`, la transacción que espera el bloqueo no vuelve a evaluar cuál es la fila más alta cuando se libera: sigue con la fila que bloqueó, calcula `N + 1` y choca con el `UNIQUE` (comportamiento de PostgreSQL en READ COMMITTED, **a verificar con una prueba real**, ver sección 6). Es decir, la segunda caja simultánea siempre pierde y reintenta. Con cuatro o más emisiones coincidentes, alguna agota los tres intentos y lanza `RuntimeException` (:115).
2. **El fallo ocurre después de cobrar.** `PedidoController.php:186` emite el comprobante cuando `VentaService::registrar` ya cerró su transacción. Si `emitir` lanza, la venta existe, el stock se descontó y no hay comprobante: exactamente el impacto que describía la auditoría. Hoy la probabilidad es baja por el volumen de una botica; el diseño sigue sin ser correcto por construcción.
3. **El primer comprobante de una serie no tiene fila que bloquear** (lo admite el comentario de :72-77). Se resuelve igualmente con el reintento, no con un bloqueo.
4. **La idempotencia no es atómica.** La comprobación `where('pedido_id')->first()` (:64) está fuera de la transacción y no hay `UNIQUE(pedido_id)` en la tabla. Dos solicitudes simultáneas del mismo pedido (doble clic, reintento de red) pueden crear dos comprobantes con números distintos: duplicidad tributaria, que es lo que ese bloque de código dice evitar.
5. **La ruta de administración conserva el patrón viejo.** `FacturacionController.php:257-261` hace `lockForUpdate()->max('numero')`. Por el comentario del propio `ComprobanteService` (:84-87), esa combinación debería fallar en PostgreSQL ("FOR UPDATE is not allowed with aggregate functions"). No lo he ejecutado y ninguna prueba cubre `generarDesdePedidos`, así que queda como **sospecha fundada, no comprobada**. Está conectada a un botón real: `botica-san-juan-frontend/src/admin/views/AdminBillingView.vue:409`. Además esa ruta:
   - decide factura con `total >= 700` (:254), regla que no es la de SUNAT (la factura exige RUC; los 700 soles son el umbral a partir del cual la boleta exige identificar al cliente, **a verificar**),
   - toma el documento de `usuario->dni` (:269) en lugar de los datos congelados en el pedido (`cliente_documento`, `cliente_tipo_documento`),
   - su docblock (:244-250) afirma una protección que no cumple.
6. **No hay prueba de concurrencia real.** `phpunit.xml:39-40` ya usa PostgreSQL, lo cual permite escribirla, pero `RefreshDatabase` envuelve cada prueba en una transacción única y no puede reproducir dos conexiones compitiendo (ver sección 6, paso 1).

**Veredicto:** C-03 **sigue abierto como riesgo residual** (severidad baja-media en volumen actual, alta en cuanto exista envío real a SUNAT, porque un número perdido o duplicado ya no es solo interno). Es corregible con el diseño de la sección 2.

### 1.3 Otros hallazgos que afectan al Bloque 3

| # | Hallazgo | Evidencia |
|---|---|---|
| H-1 | **Nadie consume la cola en producción.** `QUEUE_CONNECTION=database` pero el arranque solo ejecuta `config:cache`, `route:cache`, `view:cache`, `migrate` y el servidor; no hay `queue:work`. Un `dispatch` quedaría en la tabla `jobs` sin ejecutarse. Los tests lo esconden porque llaman a `handle()` directamente. | `docker/entrypoint.sh:39-49`; `render.yaml:57-58`; ausencia de `queue:work` en `Dockerfile`, `render.yaml` y `scripts/` |
| H-2 | **El job nunca reintenta.** `handle()` no lanza excepción cuando `SunatClient::send` devuelve `ok=false`; marca `rechazada` y termina. `tries` y `backoff` son decorativos salvo ante una excepción de transporte, y en ese caso el comprobante queda `pendiente` sin registro del error. Un 503 de SUNAT se guardaría como "rechazada". | `EnviarComprobanteASunat.php:359-370` y `SunatClient.php:231-240` (todo no-200 se mapea a `rechazada`) |
| H-3 | **No hay envío automático.** `emitir` no despacha el job. El comprobante queda `pendiente` hasta que un administrador pulse el botón. | `ComprobanteService.php:62-116` (sin `dispatch`); `PedidoController.php:186` |
| H-4 | **Dos caminos de envío con lógica duplicada** (`enviarSunat` síncrono y el job), con el mismo bloque de actualización de campos y el mismo hash falso. | `FacturacionController.php:96-121` frente a `EnviarComprobanteASunat.php:345-370` |
| H-5 | **`hash_documento` es ficticio**: `sha256(serie-numero-total-timestamp)`. El hash real es el `DigestValue` de la firma XML. Se imprime en el QR. | `EnviarComprobanteASunat.php:369`, `FacturacionController.php:120, 406` |
| H-6 | **El PDF calcula el IGV mal para lo exonerado**: `baseImponible = total / 1.18` para todo el comprobante, ignorando `subtotal_exonerado` e `subtotal_inafecto` que el sistema ya guarda. Contradice a `DesgloseFiscalService` y, para un comprobante con medicamentos exonerados, imprime un IGV que no existe. | `FacturacionController.php:194-195`; `resources/views/pdf/comprobante.blade.php:219-220` |
| H-7 | **El QR no sigue el formato de SUNAT** (usa `ruc\|TIPO\|numero\|total\|ticket\|hash`). El formato recomendado lleva RUC, tipo de documento, serie, número, IGV, total, fecha de emisión, tipo y número de documento del cliente **(a verificar)**. | `FacturacionController.php:401-408` |
| H-8 | **Estados insuficientes**: el enum solo tiene `pendiente\|aceptada\|rechazada`. Faltan `en_proceso`/`enviado`, `aceptada_con_observaciones`, `error_envio` (transitorio, reintentable), `anulada`/`dada_de_baja`. Cambiar un enum en PostgreSQL es una migración con restricción CHECK. | migración 002400 :22 |
| H-9 | **Tipos de comprobante insuficientes**: el enum solo admite `boleta\|factura`. Sin notas de crédito no hay forma tributariamente válida de corregir o anular un comprobante ya aceptado. | migración 002400 :16 |
| H-10 | **El almacenamiento local es efímero.** `LOG_CHANNEL=stderr` y el comentario de `render.yaml` ("el disco del contenedor es efímero") confirman que `storage/app/private` no sobrevive a un reinicio. `descargarXml` escribe ahí (:167). El XML firmado y el CDR deben conservarse años; no pueden vivir en ese disco. | `FacturacionController.php:165-170`; `config/filesystems.php:34-35`; `render.yaml:45-46` |
| H-11 | **Datos del emisor de relleno**: `config/company.php` trae RUC `20123456789` por defecto y no hay ubigeo, código de local anexo ni régimen. Un XML con esos datos sería rechazado, o peor, aceptado con datos falsos si se configura mal. | `config/company.php` |
| H-12 | **`cliente_nombre` por defecto "Cliente sin identificar"** (:98). Para boletas de importe alto SUNAT exige identificar al adquirente **(a verificar el umbral, S/ 700)**. | `ComprobanteService.php:98` |
| H-13 | **Trazabilidad documental desactualizada** en `docs/INFORME-APF2.md`: la tabla de entidades nombra `documentos_tributarios` (línea 61) y la matriz RF12/HU10 apunta a `POST /api/documentos-tributarios` y `documentos_tributarios` (línea 121). Ninguna de las dos existe: la tabla real es `comprobantes_electronicos` y las rutas reales son `/api/facturacion/documentos/...` (`routes/api.php:194-196, 265-269`). Además CP12 (línea 234) solo prueba 401/403; ninguna prueba cubre la emisión ni el envío contra una SUNAT real. | `docs/INFORME-APF2.md:61, 121, 234` |

### 1.4 Lo que ya está bien y se reutiliza

- **Afectación al IGV por línea, congelada en la venta**: `pedido_detalles.tipo_afectacion_igv` (códigos 10/20/30 del catálogo 07) y `pedidos.subtotal_gravado/exonerado/inafecto/igv/tasa_igv` (migración `2026_09_18_130000`). Es exactamente el dato que pide el UBL por línea.
- **`DesgloseFiscalService`** como fuente única de cifras, y la decisión de que los precios incluyen IGV.
- **Datos del cliente congelados** en el pedido (`cliente_nombre`, `cliente_documento`, `cliente_tipo_documento` con valores `dni|ruc|ce|sin_documento`).
- **Job asíncrono con `ShouldBeUnique`, backoff y pruebas** (`EnvioAsincronoSunatTest`), y la tabla `jobs` ya migrada. Se mantiene la forma; se corrige su contrato.
- **Pruebas sobre PostgreSQL** (`phpunit.xml:39-40`), lo que hace viable probar bloqueos reales.
- **El endpoint directo de Neon** ya es una decisión documentada (`DESPLIEGUE.md:129-133`).

### 1.5 Dependencias instaladas (composer.json / composer.lock)

Instaladas: `laravel/framework ^12`, `laravel/sanctum`, `barryvdh/laravel-dompdf 3.1.2`, `chillerlan/php-qrcode 5.0`, `dedoc/scramble`, `guzzlehttp/guzzle` (vía Laravel, cliente HTTP).
**No** instaladas: `robrichards/xmlseclibs`, `phpseclib`, `greenter/*`, `league/flysystem-aws-s3-v3` (aunque `config/filesystems.php:51` declara un disco `s3` y `.env.example` tiene variables `AWS_*`).
PHP requerido: `^8.4` (composer.json). La imagen Docker instala `pdo_pgsql pgsql intl opcache zip gd` (`Dockerfile:41-47`); `dom`, `libxml` y `openssl` vienen con PHP base, pero **`ext-soap` no está** (en el entorno de este análisis tampoco, `php -m`). Si se usa una librería que dependa de SOAP nativo, hay que añadirla al Dockerfile **(a verificar según la librería elegida)**.

---

## 2. Diseño del correlativo transaccional

### 2.1 Requisitos

1. Sin duplicados, nunca (lo garantiza el motor, no el orden de ejecución).
2. Sin huecos salvo causa explícita y registrada. SUNAT espera numeración consecutiva por serie; un salto debe poder justificarse **(a verificar el tratamiento exacto de saltos)**.
3. Sin fallos visibles al vendedor por contención: la caja no debe recibir un error por coincidir con otra.
4. Correcto con Neon: endpoint directo hoy, y que no se rompa si algún día se usa `-pooler` (PgBouncer en modo transacción).
5. Idempotente por pedido.
6. Series independientes por ambiente (pruebas frente a producción) y, opcionalmente, por caja.

### 2.2 Opción A: tabla `series_comprobante` con `lockForUpdate`

Esquema propuesto (no implementado):

```
series_comprobante
  id
  ambiente            -- 'beta' | 'produccion'  (o 'simulado' para lo ya emitido)
  tipo_documento      -- '01' | '03' | '07' | '08'  (catálogo 01)
  serie               -- 'F001', 'B001', 'FC01', 'BC01'...
  ultimo_numero       -- bigint, empieza en 0
  activa              -- bool
  UNIQUE (ambiente, serie)
```

Algoritmo, todo en una sola transacción corta:

```
DB::transaction:
    serie = SerieComprobante::where(ambiente, tipo, activa)->lockForUpdate()->firstOrFail()
    serie.ultimo_numero += 1 ; serie.save()
    ComprobanteElectronico::create(serie, numero = serie.ultimo_numero, pedido_id, ...)
```

Variante equivalente en una sentencia: `UPDATE series_comprobante SET ultimo_numero = ultimo_numero + 1 WHERE id = ? RETURNING ultimo_numero`.

Propiedades:
- **Bloqueo siempre sobre una fila que existe** (se siembra en migración con `ultimo_numero = max(numero)` actual), así que desaparece el problema del primer comprobante.
- **La espera es real, no un choque.** La segunda transacción queda bloqueada hasta que la primera confirma y entonces lee el valor ya incrementado. No hay reintento por 23505.
- **Sin huecos por rollback**: si falla el `INSERT` del comprobante, el incremento se revierte con él.
- Serializa la emisión por serie. Con el volumen de una botica (decenas o cientos de ventas diarias) y una transacción de milisegundos, es irrelevante. Regla de oro: **dentro de esa transacción no se genera XML, no se firma y no se llama a SUNAT**.
- Funciona con PgBouncer en modo transacción: `SELECT ... FOR UPDATE` y `UPDATE` viven y mueren dentro de una transacción, que es la unidad que PgBouncer asigna a una conexión. No usa bloqueos de sesión.

### 2.3 Opción B: secuencia de PostgreSQL (`CREATE SEQUENCE` + `nextval`)

Una secuencia por serie y ambiente (`CREATE SEQUENCE seq_B001`), con `nextval()`.

- **Pros:** sin contención ni espera; mínimo código.
- **Contras decisivos aquí:**
  - **`nextval` no es transaccional.** Si la transacción hace rollback, el número se pierde: hueco. Para boletas y facturas que SUNAT espera consecutivas es exactamente lo que se quiere evitar. Los huecos se generan además en reinicios abruptos o failover.
  - **Una secuencia por serie obliga a ejecutar DDL** (`CREATE SEQUENCE`) cada vez que se abre una serie nueva, o a mantener un catálogo aparte de todos modos.
  - **Reiniciar o consultar** ("¿cuál es el último emitido?") exige funciones propias y permisos de DDL en una base gestionada.
  - Sobre PgBouncer en modo transacción funciona (no depende de la sesión), pero el problema de huecos permanece.
- Solo sería defendible si se aceptara una política de "huecos justificados con comunicación de baja", cosa que añade trabajo operativo permanente.

### 2.4 Comparación

| Criterio | A. Tabla + `lockForUpdate` | B. Secuencia PG |
|---|---|---|
| Duplicados | Imposible (bloqueo + `UNIQUE`) | Imposible |
| Huecos en rollback | **No** | **Sí** |
| Fallos por contención | No (espera) | No |
| Primer número de una serie | Fila sembrada, sin caso especial | Sin caso especial |
| Nueva serie / ambiente | `INSERT` de una fila | DDL |
| Auditable ("último emitido") | `SELECT` simple | función especial |
| Neon / PgBouncer transacción | Sí | Sí |
| Rendimiento | Serializa por serie; suficiente | Mejor, innecesario |
| Esfuerzo | Bajo | Bajo-medio |

### 2.5 Recomendación

**Opción A, tabla de series con `lockForUpdate`** (o `UPDATE ... RETURNING`), con `UNIQUE(serie, numero)` conservado como segunda red de seguridad, y los cambios siguientes:

1. **`UNIQUE` parcial sobre `pedido_id`** para comprobantes de venta (tipos 01 y 03) para que la idempotencia la garantice el motor (`CREATE UNIQUE INDEX ... WHERE tipo IN (...)`; no se puede declarar con el builder de Laravel, va en `DB::statement`). La migración debe comprobar antes que no haya duplicados existentes.
2. **`emitir` reescrito** para hacer la comprobación de idempotencia dentro de la misma transacción y, ante violación de `UNIQUE(pedido_id)`, devolver el existente.
3. **Un único servicio de asignación** (`AsignadorCorrelativo` o dentro de `ComprobanteService`); `FacturacionController::crearComprobanteParaPedido` pasa a delegar en él, o se elimina `generarDesdePedidos` si el negocio ya no lo necesita (pregunta abierta 9).
4. **Migración de datos**: sembrar `ultimo_numero` con el `max(numero)` actual por serie. Los comprobantes existentes se han emitido en modo simulado (nunca llegaron a SUNAT); se marcan con `ambiente = 'simulado'` y no cuentan para la numeración real (pregunta abierta 6).
5. **Emisión desacoplada de la venta**: si `emitir` falla, la venta no debe quedar sin comprobante en silencio. Opción simple: dejar el comprobante como tarea reintentable (job o comando `comprobantes:emitir-pendientes`) y registrar el pedido como "pendiente de comprobante", en vez de devolver 500 después de haber cobrado. Con la tabla de series la probabilidad de fallo baja casi a cero, pero la salvaguarda es barata.

### 2.6 Huecos de numeración y anulaciones

- **Con la opción A no hay huecos por error técnico.** Un hueco solo puede nacer de una decisión explícita.
- **Un comprobante emitido y firmado es inmutable.** No se corrige editando el registro ni se borra.
- **Anulación antes de aceptación por SUNAT**: el número ya está consumido y firmado. Política propuesta: se reenvía el mismo documento hasta resolverlo (aceptado o rechazado). Qué hacer exactamente con un rechazo con CDR (2000-3999) en cuanto a reutilizar el número **es a verificar** con la guía de SUNAT: no debo asumirlo.
- **Anulación después de aceptado**:
  - Factura: nota de crédito (tipo 07, serie propia que empieza por F y mismo tipo, p. ej. `FC01`) o comunicación de baja dentro del plazo permitido **(a verificar plazos)**.
  - Boleta: nota de crédito, o anulación mediante el resumen diario **(a verificar)**.
- **Devoluciones parciales**: nota de crédito con motivo del catálogo 09 **(a verificar códigos)**.
- **Series para NC/ND**: numeración independiente con el mismo mecanismo (por eso `tipo_documento` va en la tabla de series).
- **Rechazos y reenvíos**: el reenvío conserva serie, número y firma del documento original mientras se mantenga el mismo XML. Si hay que regenerar el XML (porque el rechazo fue por contenido), se vuelve a firmar con el mismo número **(a verificar si SUNAT lo permite o exige nuevo número)**.

### 2.7 Reintentos y fallos

- Con la opción A, no se reintenta por 23505. Se mantiene un reintento acotado **solo** para deadlocks (SQLSTATE 40P01) y fallos de serialización (40001), que Laravel resuelve con `DB::transaction($cb, $attempts)`.
- Los reintentos pasan al envío (sección 4.5), que es donde la latencia y la indisponibilidad externas sí son reales.

---

## 3. Diseño UBL 2.1 para Boleta y Factura (y notas de crédito)

> Todo lo de esta sección es el diseño; la forma exacta de cada nodo y de cada código se valida contra la **Guía de elaboración de comprobantes de pago electrónicos UBL 2.1** y los XSD oficiales **(a verificar)**. Mi recomendación es no escribir tests con códigos "de memoria": descargar la guía y los catálogos y fijar los valores desde ahí.

### 3.1 Tipos de documento (catálogo 01)

| Código | Documento | Serie | Origen en el sistema |
|---|---|---|---|
| 01 | Factura | `F` + 3 caracteres (p. ej. `F001`) | RUC del cliente (regla ya vigente en `ComprobanteService::correspondeFactura`) |
| 03 | Boleta de venta | `B` + 3 caracteres (p. ej. `B001`) | DNI, CE o sin documento |
| 07 | Nota de crédito | `FC01` / `BC01` (empieza por F o B según el documento que modifica) **(a verificar patrón)** | Nuevo |
| 08 | Nota de débito | idem | No prioritario |

Nombre de archivo: `{RUC emisor}-{tipo}-{serie}-{numero}.xml`, p. ej. `20XXXXXXXXX-03-B001-00000042.xml` **(a verificar formato exacto del número: sin ceros a la izquierda en el nombre o con ellos)**. El ZIP lleva el mismo nombre con extensión `.zip` y contiene un solo XML con el mismo nombre base.

Columna `tipo_comprobante` hoy es el enum `boleta|factura`; se añade el código SUNAT (`tipo_documento` char(2)) como dato canónico y se amplía el enum o se reemplaza por una restricción CHECK.

### 3.2 Estructura del XML (Invoice)

Raíz `Invoice` (facturas y boletas comparten esquema; la nota de crédito usa `CreditNote`, con otra raíz y otros nodos de referencia).

```
Invoice
 ├─ ext:UBLExtensions / ext:UBLExtension / ext:ExtensionContent
 │     └─ ds:Signature            ← la firma XMLDSig va aquí (sección 3.6)
 ├─ cbc:UBLVersionID              2.1
 ├─ cbc:CustomizationID           2.0
 ├─ cbc:ID                        F001-00000001            (serie-numero)
 ├─ cbc:IssueDate / cbc:IssueTime
 ├─ cbc:InvoiceTypeCode           01 | 03     (+ listID de tipo de operación, p. ej. 0101 venta interna)
 ├─ cbc:Note languageLocaleID="1000"          monto en letras  (leyenda obligatoria)
 ├─ cbc:DocumentCurrencyCode      PEN
 ├─ cac:Signature                 referencia al firmante (RUC y razón social)
 ├─ cac:AccountingSupplierParty   emisor: RUC (schemeID 6), razón social, nombre comercial, ubigeo, dirección, código de local anexo
 ├─ cac:AccountingCustomerParty   adquirente: tipo (catálogo 06) y número de documento, nombre
 ├─ cac:TaxTotal                  totales de impuestos del documento (subtotales por tributo)
 ├─ cac:LegalMonetaryTotal        valor de venta, importe total, importe a pagar
 └─ cac:InvoiceLine (1..n)        una por línea, ver 3.4
```

Notas de diseño:
- `cbc:InvoiceTypeCode` 01 o 03 sale del tipo de comprobante; el sufijo/atributo de tipo de operación (catálogo 51, p. ej. `0101` venta interna) **(a verificar sintaxis exacta)**.
- Fecha de emisión: `fecha_emision` del comprobante en zona horaria de Lima. Hoy se guarda con `now()` en el momento de emitir; se debe fijar zona `America/Lima` en la generación **(a verificar la tolerancia de SUNAT en antigüedad y futuro de la fecha)**.
- Moneda: PEN fija (el sistema no maneja otra).
- Leyenda 1000 (monto en letras) es obligatoria; la frase actual "Los precios incluyen IGV" (`ComprobanteService.php:191`) es informativa y **no sustituye** a la leyenda 1000. Hay que implementar el convertidor de importe a letras o usar la utilidad de la librería elegida.
- Datos del emisor: de `config/company.php`, ampliado con ubigeo, dirección fiscal completa y código de establecimiento anexo. Sin valores por defecto: si falta uno, la generación falla de forma explícita (corrige H-11).

### 3.3 Catálogos relevantes

| Catálogo | Uso | Valores que importan aquí |
|---|---|---|
| 01 Tipo de documento | `InvoiceTypeCode` | 01 factura, 03 boleta, 07 NC, 08 ND |
| 06 Tipo de documento de identidad | `schemeID` del adquirente | 6 = RUC, 1 = DNI, 4 = carnet de extranjería, 0 = sin documento (con número `-`) **(a verificar)** |
| 07 Afectación IGV | `TaxExemptionReasonCode` por línea | **10 gravado – operación onerosa, 20 exonerado – operación onerosa, 30 inafecto – operación onerosa**. Coincide uno a uno con `Producto::GRAVADO/EXONERADO/INAFECTO` (`'10'`, `'20'`, `'30'`), por eso la migración `2026_09_18_130000` ya los guarda como código. Existen muchos más (exportación, gratuitos, retiros…); no aplican a la botica hoy. |
| 05 Tributos | `TaxScheme/ID`, `Name`, `TaxTypeCode` | 1000 = IGV (código `VAT`); 9997 = Exonerado (`EXO`, `VAT`); 9998 = Inafecto (`INA`, `FRE`) **(a verificar los pares nombre/código internacional)** |
| 03 Unidad de medida | `unitCode` de la cantidad | `NIU` (unidad, bienes), `BX` (caja). **El código de blíster no lo conozco con certeza: a verificar.** |
| 09 Motivo de nota de crédito | NC | 01 anulación de la operación, 06 devolución total, 07 devolución por ítem, entre otros **(a verificar)** |
| 16 Tipo de precio | `PriceTypeCode` en la referencia de precio | 01 = precio unitario (incluye IGV) |
| 51 Tipo de operación | `InvoiceTypeCode` | 0101 venta interna **(a verificar)** |

**Unidades de venta del sistema** (`pedido_detalles.unidad_venta`: `unidad|blister|caja`) frente al catálogo 03: propuesta de mapeo en una tabla de configuración, no hardcodeada: `unidad → NIU`, `caja → BX`, `blister → (a verificar; si no hay código adecuado, emitir la línea en NIU con cantidad = unidades y precio unitario por unidad)`. Esta última alternativa es válida siempre porque `cantidad_unidades` y `precio_unitario` ya existen en la línea, aunque pierda la presentación en el XML (la presentación puede ir en la descripción del ítem).

### 3.4 Línea de factura (`cac:InvoiceLine`) y cálculo coherente con el carrito

Como los precios del catálogo **incluyen** IGV (decisión del sistema, `DesgloseFiscalService.php:24-31`), cada línea necesita las dos cifras:

| Nodo | Contenido | Derivado de |
|---|---|---|
| `cbc:InvoicedQuantity` @unitCode | cantidad en la unidad de venta | `pedido_detalles.cantidad` + `unidad_venta` |
| `cac:PricingReference / cac:AlternativeConditionPrice` | **precio unitario con IGV** (tipo de precio 01) | `precio_unitario` de la línea |
| `cac:Price / cbc:PriceAmount` | **valor unitario sin IGV** | `precio_unitario / (1 + tasa)` para gravados; igual al precio para exonerados e inafectos |
| `cbc:LineExtensionAmount` | **valor de venta de la línea** (sin IGV) | `valor_unitario × cantidad` |
| `cac:TaxTotal` de la línea | `TaxableAmount`, `TaxAmount`, `Percent`, `TaxExemptionReasonCode`, `TaxScheme` | afectación 10/20/30 |
| `cac:Item / cbc:Description` | descripción | nombre + concentración (como en `datosParaImpresion`, :175) |
| `cac:Item / cac:SellersItemIdentification` | código interno | `producto_id` o código del producto |

IGV: gravado (10) → `Percent` 18.00 (de `inventario.tasa_igv`, congelado en `pedidos.tasa_igv`), `TaxAmount` = IGV de la línea; exonerado (20) → tributo 9997, `TaxAmount` 0.00; inafecto (30) → tributo 9998, `TaxAmount` 0.00.

**Punto crítico: el redondeo.** Hoy `DesgloseFiscalService::repartir` redondea **por documento**: suma las líneas gravadas con IGV incluido, divide entre 1.18 una sola vez y obtiene el IGV por resta (:78-84). UBL pide valores **por línea** y que los totales del documento sean coherentes con ellos **(a verificar tolerancia de SUNAT; recuerdo que se admite una diferencia de unos céntimos, pero no lo doy por cierto)**. Dividir cada línea por separado y sumar puede diferir en uno o dos céntimos del total que el cliente ya vio en su pedido.

Diseño propuesto, de menor a mayor invasión:
1. **Preferido:** que los importes del documento (base, IGV, total) del XML sean la **suma de los de las líneas**, calculados por un método nuevo `DesgloseFiscalService::repartirPorLineas()` que reutiliza la misma tasa y la misma regla (IGV por resta, dentro de cada línea), y que ese mismo método alimente `pedidos.subtotal_gravado/igv` para que el XML, la boleta impresa y el carrito coincidan exactamente. El total cobrado (`total`, con IGV) **nunca cambia**: lo que se ajusta es el reparto entre base e IGV.
2. **Prueba obligatoria**: sobre un conjunto amplio de importes (p. ej. todos los precios del catálogo × cantidades 1..12), `suma(líneas) == total` y `base + igv == total` con tolerancia cero, y que `repartir()` (carrito) y `repartirPorLineas()` (XML) no discrepen más de lo que SUNAT tolera. Si discrepan, hay que decidir con el dueño qué cifra manda antes de seguir (pregunta abierta 8).
3. Corregir `FacturacionController::descargarPdf` (H-6) para que lea del mismo servicio, o reemplazar su cálculo con `datosParaImpresion`. Esto es un cambio de comportamiento visible y debe hacerse con su propia prueba.

Descuentos: hoy no existen en `pedido_detalles`; si el negocio los añade, se modelan como `cac:AllowanceCharge` (**a verificar**) y cambian el cálculo.

### 3.5 Totales del documento

- `cac:TaxTotal`: monto total de impuestos, con un `TaxSubtotal` por tributo presente (1000 IGV, 9997 EXO, 9998 INA), cada uno con `TaxableAmount` (base de esas líneas) y `TaxAmount`.
- `cac:LegalMonetaryTotal`: `LineExtensionAmount` (suma de valores de venta), `TaxInclusiveAmount` (importe total con impuestos), `PayableAmount` (importe a pagar, igual al total cobrado).
- Un comprobante mixto (medicamento gravado + medicamento exonerado) es normal en una botica y produce tres subtotales; la estructura lo admite.

### 3.6 Firma XMLDSig

- **Tipo:** firma envuelta (enveloped) dentro de `ext:ExtensionContent`, referenciando el documento completo; canonicalización C14N; el `DigestValue` es el hash del documento que debe imprimirse como "código hash" y puede formar parte del QR. Algoritmo de firma y de resumen (RSA-SHA1 históricamente; SHA-256 en esquemas más recientes) **(a verificar qué acepta el ambiente que se use)**.
- **Certificado digital**: de propiedad del RUC emisor. Debe ser emitido por una entidad de certificación reconocida **(a verificar si SUNAT acepta certificados autogenerados en beta y qué exige en producción)**. En el ambiente de pruebas SUNAT suele aceptar un certificado de prueba **(a verificar)**.
- **Dónde guardarlo (nunca en el repositorio):**
  - Formato PFX/P12 o PEM. El `.gitignore` raíz ya bloquea `*.pem` y `*.key` (líneas 15-16) pero **no** `*.pfx`, `*.p12`, `*.cer`, `*.crt`; hay que añadirlos antes de cualquier prueba con un certificado real.
  - El disco local del contenedor es efímero (H-10), así que el archivo no puede quedarse allí tampoco.
  - Opción recomendada para el tamaño del proyecto: el certificado PFX codificado en base64 en una **variable de entorno secreta del panel de Render** (`SUNAT_CERT_PFX_B64`) y su contraseña en otra (`SUNAT_CERT_PASSWORD`), con `sync: false` en `render.yaml` igual que `APP_KEY` y `DB_URL`. Se decodifica en memoria en el momento de firmar; nunca se escribe en disco ni se registra en logs.
  - Alternativas más robustas: secret manager de la plataforma o un bucket privado cifrado. Desproporcionado para una botica salvo que el dueño ya use uno.
  - Rotación: el certificado caduca (normalmente tras uno o más años; **a verificar** con la entidad emisora). El sistema debe exponer la fecha de vencimiento y avisar con antelación (comando programado o indicador en el panel), porque un certificado vencido detiene toda la facturación.
- **Credenciales de envío (usuario SOL secundario y su clave):** igualmente en variables de entorno, ligadas al RUC; nunca en `.env.example` con valores reales.
- **Responsable de firmar**: un servicio `FirmadorXml` que recibe el XML sin firmar y devuelve el XML firmado y el `DigestValue`; es lo único que toca la clave privada.

### 3.7 Nota de crédito (si aplica)

Necesaria para: anular una venta ya aceptada, devolución total o parcial y corrección de datos del receptor. Diferencias con el Invoice:
- Raíz `CreditNote`; código de tipo 07; serie propia.
- `cac:DiscrepancyResponse` con motivo (catálogo 09) y descripción; `cac:BillingReference` con la serie-número y tipo del documento original.
- Líneas `cac:CreditNoteLine` con las mismas reglas de IGV por afectación.
- Debe devolver el stock cuando corresponda (relación con `VentaService`/lotes; **fuera del alcance de este bloque**, pero hay que decidir el contrato: ¿la NC dispara una devolución de inventario o es un acto contable separado?).
- Prioridad: **segunda fase**, después de que el flujo 01/03 esté aceptado de punta a punta en beta.

---

## 4. Envío a SUNAT

### 4.1 Canal: OSE, PSE o SUNAT directa

| Canal | Qué es | Pros | Contras |
|---|---|---|---|
| **SUNAT directa (SEE del contribuyente)** | Se firma localmente y se envía por SOAP a SUNAT con usuario SOL secundario | Sin costo por comprobante; control total; es lo que la mayoría de librerías PHP implementan | El mantenimiento del certificado, la firma y los cambios normativos recaen en el equipo; SUNAT no es un servicio especialmente estable; la validación definitiva es la de SUNAT |
| **OSE** (Operador de Servicios Electrónicos) | Tercero autorizado que valida y envía; devuelve CDR | Descarga la complejidad de la validación; suele estar disponible aunque SUNAT falle | Costo recurrente; dependencia de un proveedor; hay que verificar que esté autorizado por SUNAT **(a verificar)** |
| **PSE** (Proveedor de Servicios Electrónicos) | Tercero que emite y firma en nombre del contribuyente | Mínimo desarrollo | Costo; menos control; menos oportunidad de aprendizaje |

Recomendación técnica: **diseñar contra una interfaz** (`ProveedorComprobantes::enviar(XmlFirmado): ResultadoEnvio`) con dos implementaciones, SUNAT directa (beta y producción) y una para el OSE o PSE que se elija, de modo que la decisión del dueño no obligue a rehacer el sistema. Si el dueño no tiene una preferencia, el camino de menor riesgo operativo para una botica pequeña **podría** ser un OSE/PSE, pero eso es una decisión comercial (pregunta abierta 1).

### 4.2 Ambientes

- **Beta (pruebas) de SUNAT** y **producción**, con endpoints y credenciales distintas. Los endpoints de `billService` que conozco son `https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService` y `https://e-factura.sunat.gob.pe/ol-ti-itcpfegem/billService` **(a verificar ambos antes de usarlos)**. En beta suele usarse el RUC de pruebas con credenciales de pruebas publicadas por SUNAT **(a verificar)**.
- Variable `SUNAT_AMBIENTE` (`beta|produccion`) y la tabla de series con columna `ambiente`: así los números de prueba jamás contaminan la numeración real.
- `config/services.php:77-82` hoy ofrece `mode`, `base_url`, `token`, `timeout`. Se sustituye por `ambiente`, `ruc`, `usuario_sol`, `clave_sol`, `wsdl/endpoint`, `timeout`, y el modo `simulado` se conserva solo para desarrollo y tests.

### 4.3 Protocolo SOAP billService

Operaciones relevantes **(a verificar contra el manual del servicio)**:
- `sendBill`: envío síncrono de un comprobante en ZIP (base64). Responde con un ZIP que contiene el **CDR** (Constancia de Recepción), un XML `ApplicationResponse` con código de respuesta y descripción.
- `sendSummary` + `getStatus`: resúmenes diarios y comunicaciones de baja; devuelven un **ticket** y se consulta el estado después.
- `getStatus`/`getStatusCdr`: consulta del estado/CDR de un comprobante.
- Autenticación: cabecera WS-Security con usuario `{RUC}{usuario SOL}` y clave **(a verificar)**.
- Facturas se envían individualmente con `sendBill`. Las boletas pueden enviarse individualmente o mediante resumen diario **(a verificar cuál admite el régimen y cuál conviene; esto cambia el diseño del job)**.

### 4.4 Interpretación del CDR

- Código `0` = aceptado. Códigos de la franja 4000 en adelante = aceptado con observaciones (se guarda el aviso, el comprobante es válido). Códigos de las franjas 0100-1999 = excepción sin CDR (suele ser un fallo de formato o de autenticación, corregible y reenviable); 2000-3999 = rechazo con CDR **(a verificar rangos y consecuencias, incluida la regla de numeración tras un rechazo)**.
- Se persisten: ZIP enviado, XML firmado, CDR (XML y ZIP), código y descripción, fecha de recepción, y el `DigestValue`. Es la prueba legal de que SUNAT recibió el documento; se conserva el tiempo que exija la normativa **(a verificar plazo de conservación)**.
- **Almacenamiento**: no en el disco local (H-10). Opciones: (a) columnas `bytea`/`text` en una tabla `comprobante_archivos` aparte (cómodo, con backup incluido, peso moderado: un XML de boleta pesa pocos kB); (b) un bucket S3-compatible usando el disco `s3` ya declarado en `config/filesystems.php` (requiere añadir `league/flysystem-aws-s3-v3`, no instalado). Para el volumen de una botica, (a) es lo más simple; (b) escala mejor y evita inflar la base. Decisión para el dueño/equipo.

### 4.5 Manejo asíncrono: qué se reutiliza y qué se corrige

Se **reutiliza** el job `EnviarComprobanteASunat` (cola `database`, `ShouldBeUnique`, backoff, idempotencia por estado aceptado) y las pruebas existentes (`EnvioAsincronoSunatTest`). Se **corrige**:

1. **Distinguir tres desenlaces** (H-2): aceptado (final), rechazado por SUNAT con CDR (final, no se reintenta sin intervención) y fallo transitorio (timeout, 5xx, caída de red, excepciones SOAP de indisponibilidad) → el job debe **lanzar la excepción** para que la cola aplique `backoff`, y dejar el comprobante en `error_envio`/`enviado_pendiente_respuesta`, no en `rechazada`.
2. **Estados nuevos** (H-8): `pendiente → firmado → en_proceso → aceptada | aceptada_con_observaciones | rechazada | error_envio`, más `anulada` cuando exista NC. Cada transición validada en un solo lugar (máquina de estados pequeña en el modelo).
3. **Cadena de trabajos**: tras `emitir` se despacha una cadena `GenerarYFirmarXml → EnviarASunat`, o un solo job con pasos idempotentes: si el XML firmado ya existe no se regenera (la firma cambia con cada ejecución; regenerarla altera el hash que ya pudo imprimirse).
4. **`$job->failed()`**: al agotar reintentos, marcar `error_envio` con el último mensaje y dejar visible en el panel para reenvío manual. Hoy un fallo definitivo no deja rastro.
5. **Despacho automático** (H-3): `emitir` despacha el job con `afterCommit()` para que el worker no lea un comprobante todavía no confirmado. Para el modo transicional se puede mantener un flag de configuración que lo deshabilite.
6. **Proceso consumidor en producción** (H-1): sin un worker, todo lo anterior no se ejecuta. Opciones en Render: un servicio de tipo worker (`php artisan queue:work --tries=3 --max-time=3600`) o un cron que ejecute `queue:work --stop-when-empty` cada minuto. El plan gratuito de Render puede no incluir workers en segundo plano **(a verificar según el plan contratado)**; hay que verlo con el dueño porque tiene costo.
7. **Comando de reconciliación** programado: reenvía los comprobantes en `error_envio`/`pendiente` con antigüedad, y consulta el estado de los `en_proceso` huérfanos (por ejemplo, si el worker murió después de enviar y antes de guardar el CDR). Debe usar `getStatus`, no reenviar a ciegas, para no duplicar.
8. **Plazos**: SUNAT tiene un plazo máximo de envío tras la emisión (varios días) **(a verificar plazos vigentes para facturas y boletas)**. El panel debe resaltar comprobantes que se acerquen al límite.
9. **Colas y concurrencia de envío**: el `uniqueId` actual evita envíos paralelos del mismo comprobante; se conserva. Con un solo worker el envío es secuencial, lo cual es suficiente.
10. **Eliminación del camino síncrono** `FacturacionController::enviarSunat` (H-4), o hacerlo delegar en el mismo servicio que el job (`ServicioEnvio::enviar($comprobante)`), con un solo lugar que escribe el resultado.

### 4.6 Librerías PHP candidatas

Lo instalado hoy está en 1.5. No se ha instalado ni comprobado compatibilidad de ninguna; **no cito versiones**. Antes de decidir, verificar en Packagist y en el repositorio de cada una: mantenimiento reciente, compatibilidad con PHP 8.4 y Laravel 12, licencia, extensiones requeridas y cómo cubren cada pieza.

| Opción | Qué da | Pros | Contras / dudas |
|---|---|---|---|
| **Greenter** (familia `greenter/*`: modelo de documentos, generación XML UBL, firma, cliente SOAP, validador, reportes) | Cubre XML UBL 2.1 de factura, boleta, NC/ND, resúmenes, bajas y guía de remisión; firma y envío SOAP | Es la solución de facto del ecosistema PHP peruano; evita escribir el XML y la firma a mano; usada en producción por muchos proyectos | Hay que **verificar compatibilidad con PHP 8.4**, su requerimiento de `ext-soap` (no está en el Dockerfile) y su política de mantenimiento; trae sus propias abstracciones que conviene aislar tras una interfaz; el generador PDF propio compite con dompdf ya instalado (no es necesario usarlo) |
| **UBL + firma propios** con `DOMDocument`/`XMLWriter` y `robrichards/xmlseclibs` (o la firma nativa de `ext-openssl`) y cliente SOAP o HTTP con Guzzle | Control total, pocas dependencias, encaja con el estilo del proyecto | Educativo (valioso para un proyecto académico); sin SOAP nativo se arma el envelope a mano | Alto riesgo de errores sutiles (canonicalización, orden de nodos, espacios de nombres); mucho mayor esfuerzo; mantener los catálogos; cada cambio normativo es trabajo propio |
| **Híbrido**: Greenter solo para el XML y la firma; envío SOAP propio con Guzzle (ya instalado vía Laravel) o `SoapClient` | Menos dependencia de sus abstracciones de red | Más piezas que integrar | Complejidad media |
| **API de un OSE/PSE** | Envío de JSON, el proveedor genera XML, firma y CDR | Menor desarrollo y menor riesgo normativo | Costo recurrente; datos de ventas en un tercero; dependencia comercial; no se aprende ni se controla el XML |

**Recomendación:** empezar por **Greenter detrás de una interfaz propia**, tras comprobar su compatibilidad, y dejar la puerta abierta al OSE. Implementar a mano solo si la verificación de compatibilidad falla o si la finalidad académica prefiere entender la firma. Un criterio de validación compartido entre todas las opciones: **validar el XML contra los XSD oficiales de UBL 2.1 en las pruebas**, para no depender de la aceptación en beta como único detector de errores.

---

## 5. Cómo encajan las piezas

```
POS / Portal
   │  POST /api/pedidos/confirmar
   ▼
VentaService::registrar        (stock, lotes, desglose fiscal)   ← ya existe
   │
   ▼
ComprobanteService::emitir      (TX corta: serie bloqueada + INSERT)   ← se rediseña (sección 2)
   │  afterCommit
   ▼
Cola ──► Job GenerarXml ──► ConstructorUbl (datos congelados del pedido)
                     └──► FirmadorXml (certificado desde entorno)
         Job EnviarASunat ─► ProveedorComprobantes (SUNAT directa | OSE)
                     └──► interpreta CDR → estado, guarda XML/CDR
   ▼
Panel de facturación: estados, reenvío manual, descarga XML/CDR/PDF (PDF con las mismas cifras)
```

Principios:
- **Una sola fuente de cifras**: el XML, el PDF y el carrito se alimentan de `DesgloseFiscalService`.
- **El XML firmado es inmutable** una vez generado: se guarda y se reutiliza; no se vuelve a calcular.
- **Datos congelados**: el XML se construye a partir de lo guardado en el pedido y el comprobante (cliente, tasa, afectación por línea), nunca de datos vivos del producto o del usuario.
- **Sin secretos en el repositorio ni en logs**; el log registra solo identificador, estado y código, como ya hace el job (`EnviarComprobanteASunat.php:372-378`).

---

## 6. Plan de implementación por pasos pequeños

Cada paso: **primero la prueba**, luego el cambio, ambos en un commit pequeño. Todos sobre PostgreSQL (como `phpunit.xml`).

### Fase A: correlativo y base de estados (≈ 1 sprint)

1. **Prueba de concurrencia que falle hoy.** Lanzar N procesos `php artisan` en paralelo (con `Symfony\Process`) que emitan comprobantes contra una base preparada con `DatabaseMigrations`/`DatabaseTruncation` (no con `RefreshDatabase`, cuya transacción envolvente impide la contención real). Afirmaciones: sin duplicados, numeración contigua, ningún proceso falla. Con 6-8 procesos el código actual debería mostrar los reintentos agotados; esa prueba es también la que confirma o descarta la hipótesis del punto 1 de 1.2.
2. **Prueba de doble emisión simultánea del mismo pedido** (expone la falta de `UNIQUE(pedido_id)`).
3. **Prueba de `generarDesdePedidos` contra PostgreSQL** (confirma o descarta la sospecha del `lockForUpdate()->max()`).
4. Migración `series_comprobante` + siembra desde el `max(numero)` actual + columna `ambiente`.
5. `ComprobanteService::emitir` con el nuevo asignador; `UNIQUE` parcial sobre `pedido_id`. Las pruebas 1-3 pasan.
6. `generarDesdePedidos`: delegar en `emitir` (con datos del pedido, no `usuario->dni`) o eliminar el endpoint y su botón, según la pregunta abierta 9. Retirar el cálculo duplicado.
7. Ampliar estados y tipos (migración con CHECK), con la máquina de estados y sus pruebas de transición.
8. Salvaguarda de la venta sin comprobante (2.5, punto 5).
9. **Despliegue del worker** (H-1) y prueba de humo en la nube equivalente a `docs/` de despliegue existentes: un job encolado se consume.

### Fase B: UBL sin firma (≈ 1 sprint)

10. Descargar las guías y catálogos oficiales; fijar los valores en una carpeta de fixtures versionada (sin datos reales).
11. Decisión de librería (prueba de concepto de compatibilidad con PHP 8.4/Laravel 12 en una rama, sin tocar `main`).
12. Prueba de cifras: `repartirPorLineas()` frente a `repartir()` sobre una malla de importes (3.4).
13. `ConstructorUbl` para boleta y factura con pruebas: XML válido contra XSD, un caso por combinación (solo gravado, solo exonerado, mixto, con inafecto, factura con RUC, boleta sin documento).
14. Corregir PDF (H-6) y QR (H-7) para leer del mismo servicio, con prueba de que PDF y XML muestran los mismos importes.
15. Completar `config/company.php` con ubigeo y establecimiento, sin valores por defecto engañosos (H-11).

### Fase C: firma y envío a beta (≈ 1 sprint)

16. `FirmadorXml` con certificado de prueba generado para la ocasión (nunca uno real en el repo); prueba: verificar la firma resultante y que el `DigestValue` cambia si se altera un byte.
17. Almacenamiento de XML y CDR fuera del disco local (4.4).
18. `ProveedorComprobantes` (interfaz) + implementación SUNAT beta; **prueba contra beta como prueba manual documentada**, no automática, porque depende de un servicio externo; las automáticas usan un doble de la interfaz con respuestas CDR grabadas.
19. Interpretación del CDR y estados; rediseño del job (tres desenlaces, `failed()`, `afterCommit`).
20. Comando de reconciliación y panel (estados nuevos, reenvío, descarga de XML/CDR).
21. Correr un lote de casos en beta con el certificado de prueba y registrar la evidencia.

### Fase D: producción y notas de crédito (≈ 0.5 a 1 sprint, más esperas externas)

22. Notas de crédito (3.7) y, según la pregunta abierta 4, resumen diario/baja.
23. Cambio a producción: certificado real, credenciales reales, serie nueva de producción, prueba con un comprobante real de monto mínimo coordinada con el dueño, y un plan de vuelta atrás (apagar el despacho automático con un flag).
24. Actualizar `docs/INFORME-APF2.md` (H-13), `docs/DEPLOYMENT.md` y el runbook con el procedimiento de rotación de certificado y reenvío.

### Estimación

Supuestos: sprint de dos semanas, una persona con dedicación parcial, sin contar los tiempos de espera ajenos al equipo (obtención del certificado, activación de credenciales SOL, aprobación de un OSE).

| Fase | Contenido | Esfuerzo |
|---|---|---|
| A | Correlativo, estados, worker | 1 sprint |
| B | UBL sin firmar, cifras, PDF/QR | 1 sprint |
| C | Firma, envío a beta, CDR, cola | 1 a 1.5 sprints |
| D | Producción + NC | 0.5 a 1 sprint |
| **Total** | | **3.5 a 4.5 sprints** (3 si se usa Greenter sin sorpresas de compatibilidad; ≈ 1.5 si se opta por un OSE/PSE y se omite la firma propia) |

El Bloque 3 de la auditoría asigna los sprints 5 y 6. Con SUNAT directa, **dos sprints solo alcanzan para las fases A y B** y una parte de C; la fase A sola sí cabe en el sprint 5 y cierra C-03.

### Riesgos

| Riesgo | Efecto | Mitigación |
|---|---|---|
| Sin certificado o credenciales a tiempo | Bloquea fases C y D | Pedirlos ya (pregunta 1-3); avanzar A y B mientras tanto |
| Greenter incompatible con PHP 8.4 / Laravel 12 o exige `ext-soap` | Retraso o rediseño | Prueba de concepto en rama (paso 11); alternativa híbrida o OSE |
| Diferencias de redondeo entre carrito y XML | Comprobantes con un céntimo de descuadre; rechazo o reclamo del cliente | Paso 12 antes de generar XML; decisión explícita del dueño |
| Rechazos por detalles de catálogo (unidad de blíster, tipo de operación) | Iteraciones largas contra beta | Fixtures oficiales + validación XSD en pruebas; empezar por un caso simple |
| Worker no desplegado o plan sin procesos en segundo plano | Los comprobantes nunca se envían | Paso 9 primero; prueba de humo; decisión de costo con el dueño |
| Pérdida de XML/CDR por disco efímero | Pérdida de la prueba legal | Almacenamiento persistente antes del primer envío real |
| Certificado vencido o clave expuesta | Corte de facturación o incidente de seguridad | Aviso de vencimiento, secretos solo en entorno, `.gitignore` ampliado, escaneo de secretos |
| Numeración de prueba mezclada con la real | Series quemadas en producción | Columna `ambiente`, series distintas por ambiente |
| Caída de SUNAT | Ventas sin comprobante enviado | Emisión local desacoplada del envío, reintentos con backoff, reconciliación y alerta por antigüedad |
| Contingencia legal por mala configuración | Sanciones | Revisión por el contador del dueño antes de producción |

---

## 7. Preguntas abiertas para el dueño

1. **¿Cuál es el RUC de la botica** y el régimen tributario (RUS, RER, régimen MYPE, general)? No está en el repositorio; `config/company.php` solo tiene un RUC de relleno. El régimen influye en si debe emitir facturas y boletas por el sistema electrónico y con qué obligaciones.
2. **¿Ya tiene certificado digital?** Si no, ¿se obtendrá uno (y de quién), y quién lo custodia? ¿Hay un certificado de pruebas para beta?
3. **¿Tiene usuario SOL secundario** habilitado para facturación electrónica, y quién lo administra?
4. **¿SUNAT directa u OSE/PSE?** ¿Hay presupuesto mensual para un proveedor? ¿Tiene preferencia por alguno ya contratado?
5. **¿Boletas individuales o resumen diario?** Cambia el diseño del envío.
6. **¿Qué se hace con los comprobantes ya generados en modo simulado** (`B001-…`, `F001-…`)? ¿Se reinicia la numeración de producción en una serie nueva y se marcan los antiguos como "simulado"? ¿Hubo ya algún comprobante real emitido por otro medio con la serie B001/F001?
7. **¿Cuántas cajas o puntos de venta** hay? ¿Se quiere una serie por caja (reduce contención y facilita el cuadre) o una sola por tipo?
8. **Cifras:** si el XML por línea y el desglose actual por documento difieren en un céntimo, ¿qué manda? (Propuesta: las líneas, porque UBL es por línea, sin cambiar nunca el total cobrado.)
9. **¿Se sigue usando el botón "Generar desde pedidos"** del panel (`AdminBillingView.vue:409`) o puede retirarse, dado que el portal y el POS ya emiten al confirmar?
10. **Boletas a clientes sin documento:** ¿se acepta rechazar la venta si supera el umbral que exige identificar al cliente, o se pide DNI siempre?
11. **Devoluciones:** ¿cómo se gestionan hoy? ¿La nota de crédito debe reponer stock automáticamente?
12. **Hosting del worker:** ¿hay presupuesto para un proceso en segundo plano permanente en Render, o se acepta un cron cada minuto?
13. **Conservación de XML y CDR:** ¿en la base de datos o en un bucket externo? ¿Quién hace el respaldo?
14. **Datos fiscales adicionales:** dirección fiscal exacta, ubigeo, código de establecimiento anexo, nombre comercial, logo y leyendas del PDF.
15. **Alcance de esta etapa:** ¿es parte de la entrega académica (APF) o del producto real en producción? Cambia el nivel de exigencia de la fase D y qué se considera "terminado".

---

## 8. Resumen de evidencias (referencias principales)

| Afirmación | Evidencia |
|---|---|
| C-03 original | `AUDITORIA_Y_PLAN.md:46-51` |
| Asignación con `lockForUpdate` sobre fila más alta | `ComprobanteService.php:88-91` |
| Número calculado como `ultimo + 1` | `ComprobanteService.php:97` |
| Reintento ante 23505, máximo 3 intentos | `ComprobanteService.php:81, 109, 115` |
| Idempotencia no atómica | `ComprobanteService.php:64-67` |
| Sin `UNIQUE(pedido_id)`; sí `UNIQUE(serie, numero)` | migración `2026_04_08_002400...` línea 31 |
| Patrón antiguo todavía en el controlador | `FacturacionController.php:257-261` |
| Factura por total >= 700 | `FacturacionController.php:254` |
| Botón que invoca la ruta antigua | `AdminBillingView.vue:409`, `routes/api.php:265` |
| XML simulado, no UBL | `FacturacionController.php:313-330` |
| Hash ficticio | `EnviarComprobanteASunat.php:369` |
| Envío simulado aleatorio | `SunatClient.php:253-279` |
| El job nunca lanza ante rechazo/error HTTP | `EnviarComprobanteASunat.php:359-370`, `SunatClient.php:231-240` |
| Cola `database` sin worker | `render.yaml:57-58`, `docker/entrypoint.sh:39-49` |
| PDF divide todo entre 1.18 | `FacturacionController.php:194-195` |
| IGV extraído del precio, por resta | `DesgloseFiscalService.php:24-31, 72-94` |
| Afectación congelada por línea | migración `2026_09_18_130000`, `VentaService.php:252` |
| Pruebas existentes de cola/acceso | `EnvioAsincronoSunatTest.php`, `AccesoDocumentosTributariosTest.php` |
| Pruebas del correlativo (solo secuenciales) | `CheckoutPortalTest.php:215, 234` |
| Tabla/ruta de RF12 desactualizadas en el informe | `docs/INFORME-APF2.md:61, 121, 234` |
| Paquetes disponibles / ausentes | `composer.json:11-19`, `composer.lock` |
| Disco efímero | `render.yaml` (comentario de `LOG_CHANNEL`), `config/filesystems.php:34-35` |
| Neon endpoint directo | `DESPLIEGUE.md:129-133` |

---

## 9. Estado de la Fase A (correlativo)

**Fecha:** 18/10/2026. Implementado en la rama `claude/cool-dijkstra-m2ahwb`, sin commit todavía. **Las pruebas no se han ejecutado**: el entorno donde se escribió no tenía servidor PostgreSQL ni PHP 8.4. Solo se validó la sintaxis (`php -l`) y se revisó el SQL a mano. Hasta correr la suite contra PostgreSQL 16, la Fase A no se da por cerrada.

**Hecho (pasos 4, 5 y 6 de la sección 6, y las pruebas de los pasos 2 y 3 en su versión secuencial):**

- Migración `2026_10_18_100000_create_series_comprobante_table.php`: crea `series_comprobante` (`ambiente`, `serie`, `tipo_comprobante`, `ultimo_numero`, `UNIQUE(ambiente, serie)`), la siembra con el `MAX(numero)` por serie de `comprobantes_electronicos` (todo como ambiente `simulado`) y garantiza las filas `B001` y `F001`. Crea el índice `UNIQUE (pedido_id) WHERE pedido_id IS NOT NULL`. Antes de nada aborta con la lista de pedidos afectados si ya hay pedidos con más de un comprobante, sin borrar datos. `down()` elimina el índice y la tabla.
- Modelo `SerieComprobante::bloquear()`: lee la fila con `FOR UPDATE`; si no existe la crea con `INSERT ... ON CONFLICT DO NOTHING` partiendo del máximo ya emitido, lo que es seguro ante concurrencia.
- `ComprobanteService::emitirConDatos()` es ya el único camino que reserva números. Dentro de una sola transacción: bloquea la serie, comprueba la idempotencia por pedido, incrementa el contador y crea el comprobante. `emitir()` delega en él. Se eliminó el bucle de reintentos por 23505; `DB::transaction(..., 3)` solo reintenta deadlocks. Si el contador estuviera por debajo del máximo real de la serie, se corrige hacia adelante y se registra un aviso (no puede producir duplicados). Si salta el índice de `pedido_id`, se devuelve el comprobante existente.
- `FacturacionController::generarDesdePedidos` ya no usa `lockForUpdate()->max()`: delega en `emitirConDatos()` conservando sus reglas propias (factura si total >= 700, cliente tomado del usuario, mismo JSON de respuesta; `created` cuenta solo los realmente creados).
- Pruebas en `tests/Feature/CorrelativoComprobanteTest.php`: numeración contigua, series independientes, idempotencia, rechazo del segundo comprobante activo por el índice, contador desfasado, serie inexistente, `FOR UPDATE` presente en la consulta, `generarDesdePedidos` de punta a punta, y la migración (siembra y aborto por duplicados).

**Queda de la Fase A:**

- **Ejecutar la suite contra PostgreSQL 16** y confirmar en particular que `generarDesdePedidos` ya no falla (la sospecha del paso 3 no se pudo comprobar en su estado anterior).
- **Prueba de concurrencia real (pasos 1 y 2):** varios procesos `php artisan` en paralelo con `DatabaseTruncation`. Con `RefreshDatabase` (una sola conexión y transacción) no se puede reproducir la espera de bloqueo entre cajas; lo cubierto aquí es el contrato, no la carrera misma.
- Paso 7: ampliar estados y tipos. Al añadir `anulada`, recrear el índice de `pedido_id` con `AND estado_sunat <> 'anulada'`.
- Paso 8: salvaguarda para que una venta cobrada no quede sin comprobante si `emitir` falla; paso 9: despliegue del worker.
- Decisiones pendientes del dueño que afectan a lo hecho: preguntas 6 (qué hacer con los comprobantes `simulado`), 7 (serie por caja) y 9 (si `generarDesdePedidos` se retira; hoy conserva su regla de 700 soles y `usuario->dni`).
- `ambiente` se fija en `simulado` por defecto; no hay aún `SUNAT_AMBIENTE`. `UNIQUE(serie, numero)` de `comprobantes_electronicos` no incluye el ambiente, así que una serie beta/producción deberá usar un nombre de serie distinto o ampliar ese índice en la Fase C.
