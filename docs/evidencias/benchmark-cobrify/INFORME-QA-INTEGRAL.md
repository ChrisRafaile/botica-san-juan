# Informe QA integral · Benchmark Cobrify y Asistente IA

**Rama:** `feat/benchmark-cobrify-y-asistente-ia` · **Fecha:** 19 de noviembre de 2026
**Método:** Chrome real (v. instalada en la máquina) controlado con `playwright-core`, contra la API local `:8083` y el frontend `:5173`. Sesión iniciada por el formulario normal de `/login` con las cuentas de demostración (administrador y cliente). No se crearon tokens ni se modificaron datos de negocio; las contraseñas no se imprimen ni se guardan en ningún archivo.

> Las secciones 1 a 6 describen el estado **antes** de las correcciones (commit `74c445c`). La sección 7 recoge la revalidación posterior.

## 1. Resultado global

| Área | Resultado |
|---|---|
| Consola del navegador | **0 errores JS** en todas las páginas, viewports y temas |
| Red | **0 respuestas 5xx** y 0 respuestas 4xx contra la API |
| Pruebas backend | **475 passed**, 1538 aserciones (42,3 s) — coincide con lo esperado |
| Pruebas frontend (vitest) | **35 archivos, 207 pruebas passed** — coincide con lo esperado |
| `vite build` | Correcto (5,4 s) |
| `vue-tsc --build --force` | **FALLA** (TS2550, ver D3). El informe de implementación decía «exitoso» |
| Defectos | 4 (1 alta, 2 medias, 1 baja); los cuatro quedan corregidos en la sección 7, más un quinto hallado después (D5, asistente) |

## 2. Tabla pasa/falla por módulo, viewport y tema

Criterios: sin desbordamiento horizontal (`scrollWidth ≤ clientWidth`), CLS ≤ 0,015, contraste AA, 0 errores de consola, 0 respuestas 5xx. «Desb.» = desbordamiento.

| Módulo / ruta | 1280 Claro | 1280 Oscuro | 360 Claro | 360 Oscuro | Notas |
|---|---|---|---|---|---|
| Tablero `/admin/home` | Pasa | Pasa | **Falla** (Desb. 574/360) | **Falla** (Desb. 574/360) | D1. CLS 0,0001–0,0028 |
| Inventario · Alertas de vencimiento (pestaña) | Pasa | Pasa | **Falla** (CLS 0,1164) | **Falla** (CLS 0,1164) | D2. Sin desbordamiento |
| Inventario · Stock por producto | Pasa | Pasa | **Falla** (CLS 0,1164) | **Falla** (CLS 0,1164) | D2 |
| POS · badge `Nd` FEFO (lista de resultados) | Pasa (4,97:1) | **Falla** (3,93:1) | Pasa (4,97:1) | **Falla** (3,93:1) | D4. Sin desbordamiento |
| Control de Caja `/admin/caja` | Pasa | Pasa | Pasa | Pasa | Solo lectura, ver §5 |
| Indicadores `/admin/indicadores` | Pasa | Pasa | **Falla** (Desb. 363/360; CLS 0,0304) | **Falla** (Desb. 363/360; CLS 0,0304) | D2 |
| Asistente IA (admin) | Pasa | Pasa | Pasa* | Pasa* | *Panel dentro del viewport; el desbordamiento de la página base es D1 |
| Asistente IA (cliente) | Pasa | Pasa | Pasa | Pasa | Sin desbordamiento |

Nota: a 1280 px `scrollWidth` da 1272 frente a `clientWidth` 1280 por la barra de desplazamiento; no hay desbordamiento.

### Verificaciones funcionales

| Elemento | Resultado |
|---|---|
| Semáforo de vencimiento (columna «Semáforo»): badge `422d` verde y «Sin fecha» | Presentes; contraste AA en los 4 casos (4,97–7,33:1 en claro, 5,01–6,41:1 en oscuro) |
| POS: badge `Nd` en la lista de resultados con `title` «Primer lote FEFO vence en 422 día(s)» | Presente en los 4 casos |
| Tablero: comparativa de 7 días, Top 5, métodos de pago | Las tres secciones presentes |
| Tablero: «Ocultar montos» | Enmascara los importes (`S/ ••••`), el botón pasa a «Mostrar montos» y revierte |
| Asistente: botón flotante, panel `role="dialog"` con nombre accesible | Presente en ambos roles |
| Asistente sin clave: entrada deshabilitada y mensaje «deshabilitado porque no hay un proveedor de IA configurado en el servidor» | Correcto (admin: «Modo gestión e inventario») |
| Asistente: Escape cierra el panel y el foco vuelve al botón «Abrir asistente» | Correcto en ambos roles |

## 3. Defectos

| # | Severidad | Defecto | Reproducción | Evidencia |
|---|---|---|---|---|
| D1 | **Alta** | **Tablero desborda horizontalmente a 360 px.** La sección «Últimas ventas» mide 558 px de ancho y el documento llega a 574 px. Rompe el reflujo WCAG a 360 px y aparece barra horizontal en móvil. Muy probable falta de `min-w-0` en un hijo de `grid` (trampa ya documentada en `CLAUDE.md`). | Iniciar sesión como administrador, viewport 360×740, abrir `/admin/home`, medir `document.documentElement.scrollWidth` (574 vs 360). Los elementos que sobresalen: `section` «Últimas ventas», su `ul`/`li` y el importe `S/ 40.00`. | `17-dashboard-360-claro.png`, `25-dashboard-360-oscuro.png`, `53-dashboard-top5-metodos-360-claro.png` |
| D2 | **Media** | **CLS fuera de presupuesto a 360 px.** Inventario (ambas pestañas): 0,1164 (7,8× el límite 0,015), causado por la rejilla de tarjetas de resumen (`grid-cols-1 sm:grid-cols-2`) que reacomoda al cargar. Indicadores: 0,0304 más 3 px de desbordamiento (tarjetas de 331 px con `p-6` que llegan a x=363). Reproducible en 5 de 5 corridas en frío. A 1280 px el CLS es 0,0023 (pasa). | Viewport 360×740, contexto nuevo, abrir `/admin/inventory/alerts` y leer `layout-shift` (sin `hadRecentInput`); igual en `/admin/indicadores`. | `18-`, `19-`, `22-`, `26-`, `27-`, `30-` (en `qa/integral/`) |
| D3 | **Media** | **`vue-tsc --build --force` falla** con `TS2550: Property 'replaceAll' does not exist on type 'string'` en `src/utils/markdownSeguro.ts:7`. El archivo entró en `546c79a`. `vite build` no comprueba tipos, por eso el build pasa. Contradice el informe de implementación (código 0). | `cd botica-san-juan-frontend; .\node_modules\.bin\vue-tsc.cmd --build --force` → exit 2. | Salida de comando (§4) |
| D4 | **Baja** | **Contraste del badge `Nd` del POS en tema oscuro: 3,93:1** (< 4,5:1 AA para texto pequeño). En claro es 4,97:1. | Tema oscuro, `/admin/pos`, escribir «AB-MOKS» en el buscador del POS y medir texto frente al fondo real de la píldora (muestreo de píxeles). | `66-pos-resultados-badge-fefo-1280-oscuro.png`, `68-…-360-oscuro.png` |

Correcciones sugeridas (no aplicadas; esta auditoría no modifica código): D1 y D2, añadir `min-w-0` al hijo de grid y reservar altura (`min-h`) a las tarjetas de resumen hasta que carguen; D3, sustituir `replaceAll` por `replace(/…/g, …)` o declarar `lib: ES2021+` en el tsconfig de la app; D4, subir la luminosidad del texto o la opacidad del fondo del tono de vencimiento «vigente» en oscuro.

## 4. Pruebas automáticas (salida real)

```
Backend   Tests: 475 passed (1538 assertions)   Duration: 42.28s
Frontend  Test Files 35 passed (35) · Tests 207 passed (207)
vue-tsc   src/utils/markdownSeguro.ts(7,6): error TS2550: Property 'replaceAll' does not exist on type 'string'.  → exit 2
vite build  ✓ built in 5.43s → exit 0
```

Las pruebas de backend usan la base `botica_san_juan_test`, aislada de la de desarrollo.

## 5. Limitaciones

- **Asistente IA sin proveedor.** No hay `GEMINI_API_KEY` en el `.env` local, así que no se pudieron hacer preguntas reales ni comprobar que el cliente NO recibe datos de lotes, costos o ventas, ni ver las sugerencias por rol (el panel las oculta cuando está deshabilitado). Solo se verificó el estado deshabilitado, Escape y foco. Esas herramientas siguen cubiertas por el FAKE de las pruebas.
- **Control de Caja en solo lectura.** No se abrió, movió, arqueó ni cerró ninguna sesión de caja, porque eso crea datos de negocio. Se midió la pantalla en su estado actual (sin desbordamiento, CLS 0,0017 y 0,0004). Los flujos de escritura quedan cubiertos solo por `ControlCajaTest`.
- **Datos de vencimiento pobres.** Solo 1 de 879 lotes tiene fecha (`AB-MOKS`, 422 días, verde). Los estados amarillo y rojo del semáforo no se vieron con datos reales.
- **«Promociones» no existe** como módulo entre los commits de esta rama; los cuatro entregados son inventario/vencimientos, caja, tablero y asistente.
- **Contraste.** Una primera pasada automática con heurística de fondo dio falsos «1:1» (Chrome devuelve colores `oklch`/`color-mix`, y mis lecturas los tomaban como RGB). Se descartó. Solo valen las mediciones de las píldoras con conversión real a sRGB y muestreo de píxeles de §2.
- La cuenta de demostración se creó con el seeder `UsuariosDemostracionSeeder` (autorizado) en la base local.

## 6. Capturas (`qa/integral/`, estado previo a las correcciones)

Solo se conservan las que muestran los defectos y el tablero de referencia:
`17`, `25`, `53` (D1, tablero a 360 px); `18`, `19`, `26`, `27` (D2, Inventario) y `22`, `30` (D2, Indicadores);
`42`–`44` (tablero a 1280 px: montos visibles, Top 5/métodos de pago, montos ocultos); `66`, `68` (D4, badge `Nd` en oscuro).

## 7. Revalidación tras las correcciones

Mismo método (Chrome real con `playwright-core`, API local `:8083`, frontend `:5173`, ambos con respuesta 200).

### Defectos

| # | Antes | Después | Qué se cambió |
|---|---|---|---|
| D1 | Desborde 574/360 px en el tablero | `scrollWidth` 352 de 360 en claro y oscuro (la diferencia es la barra de desplazamiento) | Rejillas con una sola columna por defecto sin `grid-cols-1`: su pista implícita era `auto` y crecía con el contenido. Ahora `grid-cols-1` (`minmax(0,1fr)`) en `AdminHomeView`, Indicadores e Inventario. |
| D2 | CLS 0,1164 (Inventario) y 0,0304 + 3 px de desborde (Indicadores) | CLS 0,0005–0,0007 (Inventario), 0,0003–0,0077 (Indicadores); sin desborde | Inventario: la causa **no** era la rejilla de tarjetas sino la barra de pestañas, que pasaba de una a dos líneas al cargar la fuente; ahora no admite salto de línea (`overflow-x-auto`, `whitespace-nowrap`). Indicadores: cabecera en columna a 360 px, y altura mínima reservada (`max-sm:min-h-*`) igual a la que miden los gráficos ya cargados. |
| D3 | `vue-tsc --build --force` exit 2 (TS2550) | exit 0, también con `--noEmit` | `replaceAll` sustituido por `replace` con expresión regular global en `markdownSeguro.ts`. |
| D4 | Badge `Nd` en oscuro: 3,93:1 | **6,12:1** en oscuro (1280 y 360); 4,97:1 en claro, sin cambio | Token nuevo `--color-exito-300` y `dark:text-exito-300` en el tono «vigente» de `BoticaBadge`. |

CLS medido a 360 px, contexto nuevo por ruta y tema: Tablero 0,0003–0,0028, Inventario (ambas pestañas) 0,0005–0,0007, Indicadores 0,0003–0,0077, Caja 0,0004; todos por debajo del límite de 0,015. A 1280 px el máximo es 0,0017 (Caja).

### Asistente IA en vivo (Groq)

El proveedor primario pasa a ser **Groq** (`GROQ_API_KEY`). Gemini queda como respaldo: su proyecto de Google no tiene saldo (402 «prepayment credits are depleted») y `gemini-2.5-flash` está retirado, así que no se pudo validar con él.

Hallazgos de la validación:

1. **Entorno local sin certificados.** El PHP de esta máquina no tiene `curl.cainfo` (`cURL error 60`). Se resolvió solo para la sesión de prueba, con un `.ini` temporal fuera del repositorio.
2. **Modelo retirado en Groq.** `llama-3.3-70b-versatile` responde 404 `model_not_found` para esta cuenta. `GET /openai/v1/models` lista, entre los modelos de chat, `openai/gpt-oss-120b`, `openai/gpt-oss-20b` y `qwen/qwen3.8-27b`. El modelo por defecto pasa a `openai/gpt-oss-120b`; se cambia con `ASISTENTE_IA_MODELO`.
3. **D5, ya corregido.** Un fallo del proveedor devolvía 503, que el widget lee como «asistente deshabilitado». Ahora devuelve 502 y el campo sigue habilitado.
4. **Moneda.** El modelo escribía «€» y «$». Se añadió una regla a las instrucciones del agente para usar `S/`; en la segunda pasada todos los importes salieron en soles.

Consultas reales en Chrome, con herramientas reales y base local (todas HTTP 200, campo de entrada habilitado):

| Rol | Consulta | Resultado |
|---|---|---|
| Administrador | ¿Qué productos tienen stock crítico? | Dice con honestidad que no tiene herramienta para listar stock crítico y pide un producto. **Limitación del diseño:** no existe una herramienta de stock crítico global |
| Administrador | Lotes de amoxicilina por FEFO | Tabla de lotes con cantidad y stock mínimo; todos son el lote «INICIAL» sin fecha de vencimiento, y lo indica |
| Administrador | Valor en riesgo por vencimiento a 90 días | `S/ 0`, coherente con que los datos locales no tienen fechas |
| Administrador | ¿Cómo va la caja hoy? | Ventas del día S/ 0, 11 comprobantes en efectivo (S/ 468.50), comparativa semanal y «sin caja abierta para tu usuario» |
| Cliente | ¿Tienen amoxicilina disponible? | Presentaciones, stock y precio público S/ 10; remite al químico farmacéutico |
| Cliente | Costo de compra y número de lote | **Rechaza**: «no puedo proporcionar información de costos internos ni números de lote» |

Límites de esta validación: los datos locales no tienen fechas de vencimiento, así que el orden FEFO real no se pudo ver con fechas distintas; y «stock crítico» sigue sin herramienta propia.

### Pruebas automáticas (salida real)

```
Backend   Tests: 477 passed (1543 assertions)   Duration: 51.79s
Frontend  Test Files 35 passed (35) · Tests 207 passed (207)
vue-tsc   --noEmit → exit 0 · --build --force → exit 0
vite build  ✓ built in 5.60s
```

### Capturas (`qa/post-correccion/`)

`tablero-360-*`, `inventario-360-*`, `indicadores-360-*` (claro y oscuro, página completa), `pos-badge-fefo-{1280,360}-{claro,oscuro}` y `groq-asistente-{admin-1..4,cliente-1..2}` (consultas en vivo al asistente).
