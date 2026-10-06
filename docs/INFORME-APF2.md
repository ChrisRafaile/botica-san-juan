# Informe técnico APF2 — Sistema Web Botica San Juan

**Curso:** Curso Integrador II: Sistemas · UTP
**Autor:** Christopher Rafaile
**Fecha:** 6 de octubre de 2026
**Repositorio:** `github.com/ChrisRafaile/botica-san-juan`
**Evidencias:** [`docs/evidencias/`](evidencias/) · índice en [`docs/EVIDENCIAS-APF2.md`](EVIDENCIAS-APF2.md)

---

## Cómo leer este informe

La guía docente lo dice en una línea: *«El APF2 no reemplaza al APF1: lo corrige,
lo integra y demuestra que la solución ya funciona como primera versión»*, y
cierra con *«Evidencia ≠ captura decorativa. Debe probar que el sistema funciona
o que el equipo tomó una decisión técnica»*.

Este documento se escribió con esas dos frases como norma. En consecuencia:

- **Cada cifra que aparece aquí se midió**, y debajo está el comando que la
  reproduce. No hay ninguna estimación presentada como medición.
- **Se incluyen las cifras malas junto a las buenas.** El CLS del catálogo se
  documenta en 0.44382 *y* en 0.01524, porque la primera desmintió una
  afirmación que yo había dado por buena. Una evidencia que sólo enseña el
  resultado favorable no prueba nada.
- **Se declara lo que no funciona o no está medido**, en §8. Un informe que sólo
  lista logros obliga al lector a buscar los huecos por su cuenta.

---

## 1. APF1 corregido e integrado

| Observación del APF1 | Qué se hizo | Evidencia |
|---|---|---|
| El catálogo mostraba productos repetidos | 869 grupos duplicados por reimportación, fusionados preservando el inventario real. 3 361 → 884 productos | E2a, E2b |
| Nada impedía que volvieran a aparecer | Índice `UNIQUE` con `NULLS NOT DISTINCT` sobre la identidad del producto | E1 |
| El enlace «Productos» del menú llevaba a una pantalla en blanco | La ruta no estaba declarada en el enrutador | commit `a139f4e` |
| El icono del carrito llevaba a «página no encontrada» | Mismo fallo, misma causa: `/cart` tampoco estaba declarada | E6, commit `da87146` |
| Figura 44 con rutas inventadas | Rehecha con las rutas reales del enrutador | commit `28` |
| RUC del sistema heredado publicado en el pie | Retirado: contenía el DNI del propietario | commit `054f11d` |

---

## 2. Base de datos

### 2.1 Qué tablas responden a cada RF

| RF | Tablas principales |
|---|---|
| RF01 Autenticación | `usuarios`, `personal_access_tokens` |
| RF02 Roles | `usuarios.rol` |
| RF03 Productos | `productos`, `categorias`, `subcategorias` |
| RF04 Carga masiva | `productos` (vía importación) |
| RF05 Búsqueda | `productos` (índices sobre nombre, código de barras, DIGEMID) |
| RF06 Venta por lote con FEFO | `pedidos`, `pedido_detalles`, `pedido_detalle_lotes`, `lotes` |
| RF07 Inventario automático | `movimientos_stock`, `lotes` |
| RF08 Venta fraccionada | `productos.unidad_base`, `precio_blister`, `precio_caja` |
| RF09 Alertas | `lotes.fecha_vencimiento`, `productos.stock_minimo` |
| RF10 Compras | `proveedores`, `compras`, `compra_detalles` |
| RF11 DIGEMID | `digemid_catalogos` |
| RF12 Comprobantes | `documentos_tributarios` |
| RF13 Tablero | agregados en SQL sobre `pedidos` y `lotes` |
| RF14 Reportes CSV | `pedidos`, `pedido_detalles` |
| RF15 Bitácora | `movimientos_stock`, `incidencias_venta` |

### 2.2 Las tres decisiones de diseño que hay que explicar

**a) `stock` no es lo mismo que stock vendible.**
`productos.stock` es el stock contable: incluye los lotes vencidos, porque la
mercancía sigue físicamente en el anaquel. Lo vendible se calcula sobre `lotes`
excluyendo lo vencido y lo inactivo. Mostrar el primero como disponible lleva a
prometer unidades que el mostrador no puede entregar, y la promesa se rompe al
recoger el pedido, no al hacerlo. Por eso el catálogo público y el carrito usan
`stock_disponible` y nunca `stock`.

**b) El `UNIQUE` necesita `NULLS NOT DISTINCT`.**
Un `UNIQUE` corriente sobre (nombre, concentración, presentación, laboratorio,
tipo, adicional) no habría impedido nada: en SQL dos `NULL` no son iguales entre
sí, y cientos de productos tienen `concentracion` o `adicional` en `NULL`. El
índice los habría dejado pasar todos. Verificado en E1: el intento de insertar
un duplicado devuelve `SQLSTATE[23505]`.

**c) Las claves foráneas no se comportan igual, y es deliberado.**
`pedido_detalle_lotes.lote_id` es `ON DELETE RESTRICT`: borrar un lote vendido
debe fallar, porque sin esa fila no hay forma de saber a quién se le vendió ante
una alerta sanitaria. `lotes.producto_id` es `CASCADE`. Y
`movimientos_stock.lote_id` es `SET NULL`, que **destruye trazabilidad en
silencio**: queda anotado como deuda técnica en §8.

### 2.3 Cómo se protege la información

- Contraseñas con `bcrypt`; nunca en texto plano, nunca en respuestas de la API.
- La base de producción contiene **sólo el catálogo**. Los usuarios, pedidos y
  comprobantes reales no se copiaron: llevan nombres, DNI, correos y teléfonos
  de personas.
- Los volcados de respaldo viven en `infra/backup/salida/`, en `.gitignore`: un
  volcado incluye el esquema completo, tablas de credenciales incluidas.

---

## 3. Matriz de trazabilidad

Cadena completa **RF → HU → Pantalla → Endpoint → Tabla → Caso de prueba**.
Los endpoints están documentados en OpenAPI 3.1, generados desde el propio
código por Scramble, y navegables en **`/docs/api`** (restringido a entorno local
por `RestrictedDocsAccess`).

| RF | HU | Pantalla | Endpoint | Tabla | Caso de prueba |
|---|---|---|---|---|---|
| RF01 | HU01 | `/login` | `POST /api/login` | `usuarios` | CP01 · `AutenticacionTest` |
| RF02 | HU01 | `/admin/*` | middleware `admin` | `usuarios.rol` | CP02 · `AutorizacionRolTest` |
| RF03 | HU02 | `/admin/products` | `GET·POST·PUT·DELETE /api/productos` | `productos` | CP03 · `ProductoApiTest` |
| RF05 | HU05 | `/products` | `GET /api/productos?q=` | `productos` | CP04 · E3 + E8 |
| RF05 | HU05 | `/products` | `GET /api/productos/facetas` | `productos`, `categorias` | CP05 · E3 |
| RF06 | HU03·HU06 | `/admin/pos` | `POST /api/pedidos/confirmar` | `pedidos`, `pedido_detalles`, `pedido_detalle_lotes` | CP06 · `ConfirmacionVentaTest` + E7 |
| RF07 | HU06 | `/admin/pos` | `POST /api/pedidos/confirmar` | `movimientos_stock`, `lotes` | CP07 · E7 |
| RF07 | HU03 | `/cart` | `POST /api/carrito/cotizar` | `lotes`, `productos` | CP08 · `CarritoCotizarTest` (13 pruebas) |
| RF08 | HU09 | `/admin/pos` | `POST /api/pedidos/confirmar` | `pedido_detalles.unidad_venta` | CP09 · `pos:probar` |
| RF09 | HU03 | `/admin/inventory/alerts` | `GET /api/productos/resumen` | `lotes`, `productos` | CP10 · `ProductoApiTest` |
| RF11 | HU02 | `/admin/supply/digemid` | `GET /api/digemid` | `digemid_catalogos` | CP11 · parcial, ver §8 |
| RF12 | HU10 | `/admin/billing` | `POST /api/documentos-tributarios` | `documentos_tributarios` | CP12 · `AccesoDocumentosTributariosTest`, `EnvioAsincronoSunatTest` |
| RF13 | HU04 | `/admin/home` | `GET /api/tablero` | agregados SQL | CP13 · `ProductoApiTest` |
| RF15 | HU12 | — | — | `movimientos_stock`, `incidencias_venta` | CP14 · E7 |
| — | — | `/contact` | `POST /api/contacto` | `contactos` | CP15 · E6 |

### 3.1 Integración demostrada de punta a punta (el ejemplo de la guía)

La guía pide demostrar una integración completa. Esta es la del carrito, con sus
cuatro requisitos:

```
RF07 → HU03 → Pantalla /cart → POST /api/carrito/cotizar → lotes + productos → CP08
```

| Requisito de la guía | Dónde está |
|---|---|
| Pantalla antes y después de enviar | `E6-carrito-{claro,oscuro}.png` |
| Datos registrados en la BD | E7: pedido 186 con sus dos asignaciones de lote |
| Mensaje de éxito o error | Avisos de ajuste y bloque de error en `/cart`, con `role="alert"` |
| Prueba con datos reales | 884 productos reales, stock real, 13 pruebas automatizadas |

---

## 4. Back-End: la cadena pantalla → controlador → servicio → base

Ejemplo real y completo, el que la guía usa como modelo:

```
POST /api/carrito/cotizar
  body  { "items": [ { "producto_id": 306, "cantidad": 1 } ] }

  CarritoPublicoController::cotizar()
    → agrupa líneas repetidas del mismo producto
    → Lote::disponible()          consulta el stock vendible real (excluye vencidos)
    → topa la cantidad al stock      ← el límite que de verdad cuenta
    → DesgloseFiscalService::repartir()
  ← 200 { lineas, ajustes, unidades, receta, desglose }
```

**La decisión técnica que sostiene este endpoint.** El carrito vive en
`localStorage` para que un visitante sin cuenta pueda armarlo y no lo pierda al
recargar; antes exigía iniciar sesión y el botón principal del catálogo no servía
a quien todavía no era cliente. Pero allí se guarda **únicamente**
`{producto_id, cantidad}`: ni precio, ni stock, ni si requiere receta.

Esos tres se piden al servidor cada vez, por tres razones distintas:

1. el precio de hace tres días no es el de hoy;
2. el stock pudo acabarse mientras la pestaña estaba abierta;
3. lo que está en `localStorage` lo edita cualquiera desde la consola — un precio
   guardado ahí no es un dato, es una sugerencia.

Probado: mandando `cantidad: 99` de un producto con 3 unidades, la respuesta
devuelve 3; mandando `precio: 0.01`, cobra los S/ 25.50 reales.

**`DesgloseFiscalService`.** El cálculo del IGV vivía dentro de
`VentaService::registrar()`. Al necesitarlo el carrito, la salida fácil era
repetir las tres líneas de aritmética. Se extrajo a un servicio que usan **el
portal y el mostrador**, porque duplicarlo habría separado el total de la web del
de la boleta el día que cambie la tasa, y nadie lo habría notado hasta que un
cliente los comparase. Hay una prueba cuyo único trabajo es impedir esa
duplicación.

La regla, que no es la intuitiva: en el Perú el precio de mostrador **ya incluye**
el IGV, así que el impuesto **se extrae** del precio y no se suma encima.

```
base = precio / (1 + 0.18)        igv = precio − base
S/ 118.00  →  base S/ 100.00 + IGV S/ 18.00
```

Sumándolo habrían salido S/ 139.24, cifra que el cliente no reconoce y que no
coincide con lo que paga en caja.

---

## 5. Controles de seguridad

| Control | Implementación | Verificado por |
|---|---|---|
| Autenticación | DNI + contraseña → `POST /api/login` → token Sanctum | `AutenticacionTest` |
| Autorización | Middleware `admin`; sin token → 401, rol incorrecto → 403 | `AutorizacionRolTest` |
| Contraseñas | `bcrypt`; nunca en texto plano ni en respuestas | `SaludYSeguridadTest` |
| Validación | Reglas en servidor para todos los campos; el 422 vuelve al campo que lo causó | `CarritoCotizarTest`, `/contact` |
| Mensajes de error | Genéricos en credenciales: no distinguen «usuario no existe» de «contraseña incorrecta» | `AutenticacionTest` |
| **Enumeración por tiempos** | Se midió una diferencia de **160 ms** entre un DNI existente y uno inexistente: bastaba para descubrir qué DNI están registrados. Corregido con un hash señuelo que iguala el coste de los dos caminos | `UsuarioController::HASH_SENUELO` |
| Credenciales en consola | Se eliminaron todos los `console.log` que escribían DNI, nombre, token y respuesta de sesión en la consola del navegador | `services/auth.ts`, `stores/auth.ts` |
| Documentación de la API | `/docs/api` restringido a entorno local por `RestrictedDocsAccess` | — |
| Secretos | `.env` en `.gitignore`; sólo se versiona `.env.example` con plantillas | — |

Las dos primeras no estaban en el APF1 como defectos conocidos: **aparecieron al
medirlas**, y por eso se listan con la cifra.

---

## 6. Pruebas funcionales

Suite completa: **120 pruebas, 352 aserciones, en verde** (PHPUnit 11.5.42 sobre
PHP 8.4). Eran 107 antes de este bloque.

| Caso | RF/HU | Acción | Resultado esperado | Resultado obtenido | Evidencia |
|---|---|---|---|---|---|
| CP01 | RF01/HU01 | Iniciar sesión con credenciales válidas | Token y acceso según rol | Correcto | `AutenticacionTest` |
| CP02 | RF02/HU01 | Entrar a ruta de gestión sin token / con rol cliente | 401 / 403 | Correcto | `AutorizacionRolTest` |
| CP03 | RF03/HU02 | Alta y edición de producto | Persistido con sus relaciones | Correcto | `ProductoApiTest` |
| CP04 | RF05/HU05 | Buscar «amoxicilina» en 884 productos | Resultados en < 2 s | **222 ms** mediana | E8 |
| CP05 | RF05/HU05 | Pedir facetas del catálogo | Agregados calculados en SQL | total 884, 83 laboratorios | E3 |
| CP06 | RF06/HU06 | Vender 9 unidades con 2 lotes disponibles | FEFO: 7 del que antes vence + 2 del siguiente | **Exactamente eso** | E7 |
| CP07 | RF07/HU06 | Confirmar esa venta | Stock descontado y movimientos registrados | 12 → 5 → 3, dos movimientos | E7 |
| CP08 | RF07/HU03 | Pedir 99 unidades de un producto con 3 | Se sirve 3 y se avisa | 3 + ajuste `stock_insuficiente` | `CarritoCotizarTest` |
| CP08b | RF07/HU03 | Mandar un precio falso desde el navegador | Ignorado; manda el servidor | S/ 25.50, no S/ 0.01 | `CarritoCotizarTest` |
| CP08c | RF07/HU03 | Producto con lote vencido | No se ofrece | `stock_disponible = 0` | `CarritoCotizarTest` |
| CP09 | RF08/HU09 | Vender por unidad, blíster y caja | IGV por producto, no por presentación | Correcto | `pos:probar` |
| CP12 | RF12/HU10 | Acceder a comprobantes sin permiso | 401 / 403 | Correcto | `AccesoDocumentosTributariosTest` |
| CP14 | RF15/HU12 | Revisar integridad tras la venta | Cero filas huérfanas | **0 en las 5 comprobaciones** | E7 |
| CP15 | — | Enviar el formulario de contacto con datos inválidos | 422 con el error en su campo | Correcto | E6 |

### 6.1 La venta de mostrador, paso a paso (CP06 + CP07)

Comando: `php artisan pos:venta-demostracion --cantidad=9 --aplicar`
Simula y revierte por omisión; sólo escribe con `--aplicar`.

**Dato de entrada:** AB-MOKS 500 mg + 30 mg (id 11), 12 unidades vendibles
repartidas en dos lotes.

```
ANTES     lote L-2601  vence 2027-03-31   7 unidades
          lote L-2705  vence 2028-01-15   5 unidades

VENTA     9 unidades · S/ 360.00

DESPUÉS   lote L-2601  7 → 0   (−7)   ← se agota primero el que antes vence
          lote L-2705  5 → 3   (−2)

TRAZA     pedido_detalle_lotes:  L-2601 → 7 u  ·  L-2705 → 2 u
MOVIM.    venta lote 3387  −7   stock 12 → 5
          venta lote 3388  −2   stock  5 → 3

FISCAL    base S/ 305.08 + IGV S/ 54.92 = total S/ 360.00
```

**13 comprobaciones, todas pasadas**, incluidas: que lo descontado de los lotes
es exactamente lo entregado; que se respetó el orden FEFO; que ninguna asignación
quedó sin lote; que `stock_anterior + cantidad = stock_posterior` en cada
movimiento; que el IGV coincide con el que calcula el carrito del portal; y cinco
de integridad referencial sobre toda la base.

Verificado además **desde fuera del comando que vendió**, releyendo el pedido
desde la base en una sesión aparte (E7-venta-pos-verificacion-externa.txt).

---

## 7. Pruebas no funcionales

Tabla con indicador, método de medición, resultado y evidencia, como pide la
guía.

| # | Indicador | RNF | Método de medición | Umbral | Resultado | Evidencia |
|---|---|---|---|---|---|---|
| 1 | Tiempo de búsqueda de productos | RNF03 | 12 peticiones HTTP por ruta, descartando 2 de calentamiento; se reporta mediana y p95 | < 2 000 ms (p95) | **222 ms** mediana · **264 ms** p95 | E8 |
| 2 | Tiempo del catálogo paginado | RNF03 | ídem | < 2 000 ms | **238 ms** mediana · 276 ms p95 | E8 |
| 3 | Tiempo de página profunda (400 de 442) | RNF03 | ídem | < 2 000 ms | **229 ms** mediana | E8 |
| 4 | Tiempo de cotización del carrito | RNF03 | ídem, con cuerpo de 3 líneas | < 2 000 ms | **231 ms** mediana | E8 |
| 5 | Salto de maquetación (CLS) del catálogo | RNF04 | `PerformanceObserver` sobre `layout-shift`, ventana 1280×900, headless | ≤ 0.1 | **0.44382 → 0.01524** (−96.6 %) | E4 |
| 6 | CLS del carrito | RNF04 | ídem | ≤ 0.1 | **0.053** claro · **0.050** oscuro | E6 |
| 7 | CLS de contacto | RNF04 | ídem | ≤ 0.1 | **0.015** claro · **0.013** oscuro | E6 |
| 8 | Contraste de texto (WCAG AA) | RNF04 | Lienzo 1×1: se pinta el color y se lee sRGB; compone alfa y degradados | 4.5:1 texto · 3:1 UI | **683 → 0 fallos** en el portal | auditoría de contraste |
| 9 | Contraste en carrito y contacto | RNF04 | ídem, los dos temas | 0 fallos | **302 comprobaciones, 0 fallos** | `E6-contraste.json` |
| 10 | Atomicidad de las operaciones de inventario | RNF06 | Simulación de venta dentro de transacción, luego `ROLLBACK`, y se comprueba que no quedó rastro | Sin escritura parcial | Pedidos 184 y 185 **no existen** tras revertir | E7 |
| 11 | Integridad referencial | RNF06 | 5 consultas `LEFT JOIN … WHERE NULL` sobre toda la base | 0 huérfanos | **0 / 0 / 0 / 0 / 0** | E7 |
| 12 | Peso de los recursos de imagen | RNF03 | `ls` sobre `public/formas/` | — | **12 SVG, 5.5 kB en total** | E4 |
| 13 | Suite automatizada | RNF07 | `php artisan test` | 100 % en verde | **120 pruebas, 352 aserciones** | E5a |
| 14 | Compilación sin avisos | RNF07 | `pnpm type-check` y `pnpm build` | Sin errores | Limpio · build 9.15 s | E6 |
| 15 | Vulnerabilidades de dependencias | RNF07 | `composer audit` | Reducir | **52 → 5 avisos** | E5a |
| 16 | Movimiento reducido | RNF04 | `prefers-reduced-motion` respetado en todas las vistas animadas | Sin animación si se pide | `utils/motion.ts` | — |

### 7.1 Sobre el indicador 5, que es el que más enseña

Yo había dado el catálogo por «cero CLS» porque las ilustraciones llevan `width`
y `height`. La medición lo desmintió: **0.44382**, veredicto POBRE, casi el doble
del umbral de «pobre».

Inspeccionando las fuentes de cada desplazamiento, el culpable no eran las
imágenes: **0.4237 de esos 0.44382** los aportaba el pie de página, que se
pintaba a 519 px del borde mientras la rejilla estaba vacía y se iba fuera de
pantalla al llegar los 24 productos. La corrección fue un esqueleto de carga que
ocupa exactamente el sitio de las tarjetas futuras.

La lección operativa: `pnpm type-check` y `pnpm build` pasaban limpios con el CLS
en 0.44, igual que pasaron limpios con dos imágenes rotas en el carrito que sólo
delató la captura. **Compilar sin errores no es evidencia de que la pantalla
funcione.**

---

## 8. Lo que no está terminado

Se declara aquí, con nombre y causa, en vez de dejar que el lector lo descubra.

| Pendiente | Estado real | Por qué |
|---|---|---|
| **Catálogo DIGEMID** | 5 filas de demostración; 1 producto cruzado de 884 | Sin él, `requiere_receta` y la exoneración de IGV no se pueblan solos. Se cubrió con `RecetaMedicaDemoSeeder` (34 productos de 5 familias) **para poder demostrar el comportamiento**; es una marcación de demostración, no la fuente regulatoria, y debe retirarse cuando se cargue el catálogo real |
| **Exoneración de IGV** | 0 productos marcados como exonerados | La línea «Exonerado» del desglose está implementada y probada, pero no se activa con los datos actuales |
| **Número de WhatsApp** | Configurable, sin confirmar | El portal llegó a ofrecer **cuatro números distintos**, uno de ellos el teléfono fijo (que no tiene WhatsApp). Ahora hay fuente única y, sin `VITE_BOTICA_WHATSAPP`, el botón no se pinta |
| **`movimientos_stock.lote_id` es `SET NULL`** | Deuda técnica conocida | Borrar un lote deja sus movimientos apuntando a `NULL` **sin error**: destruye trazabilidad en silencio. Debería ser `RESTRICT`, como ya lo es en `pedido_detalle_lotes` |
| **Fusión de duplicados en Neon** | Runbook escrito y probado en local | Falta ejecutarlo en producción, con respaldo previo. [`RUNBOOK-fusion-duplicados.md`](RUNBOOK-fusion-duplicados.md) |
| **Fechas de vencimiento reales** | Parciales | El stock heredado llegó sin fecha. El conteo por ciclos (`/admin/inventory/conteo`) las va poblando: 20-30 productos al día corrigen el inventario sin parar la botica |
| **PHPUnit 12** | Bloqueado | Incompatible con `nunomaduro/collision`. Se intentó, rompió la suite y se revirtió |
| **Cifras del portal** | Inconsistentes | El pie ya dice «más de 10 años»; Inicio y Sobre Nosotros siguen dando números distintos |
| **Pruebas de carga con 10 000 registros** | No ejecutada | El RNF03 habla de 10 000 productos; lo medido es sobre 884 reales. La cifra de 222 ms no se puede extrapolar sin medirla |

---

## 9. Despliegue

| Elemento | Estado |
|---|---|
| Código | `github.com/ChrisRafaile/botica-san-juan`, ramas `develop` y `main` sincronizadas |
| Front-End | Desplegado en Vercel |
| API | Desplegada en Render |
| Base de datos | Neon (PostgreSQL 16). **Usar siempre el endpoint directo, nunca el `-pooler`**: PgBouncer en modo transacción no conserva las sentencias preparadas de PDO y las migraciones fallan con `SQLSTATE[25P02]` señalando la sentencia siguiente |
| Contenido de producción | **Sólo el catálogo.** Usuarios, pedidos y comprobantes reales no se copiaron |
| Pasos reproducibles | [`DEPLOYMENT.md`](DEPLOYMENT.md), [`SECURITY_DEPLOYMENT.md`](SECURITY_DEPLOYMENT.md) |

**Aviso de entorno que cuesta un día encontrar:** la ruta de este proyecto tiene
120 caracteres, y Windows **trunca nombres de archivo al extraer los ZIP de
Composer** — 13 archivos por instalación limpia, sin que Composer dé ningún
error. PHPUnit aborta entonces con un mensaje que apunta a un sitio equivocado.
Se resuelve con `subst X:`. Diagnóstico y lo que *no* funciona en
[`ENTORNO-ruta-larga-windows.md`](ENTORNO-ruta-larga-windows.md).

---

## 10. Avance por sprints

| Sprint | Objetivo | HU | Evidencia |
|---|---|---|---|
| Sprint 3 | Base de datos + Back-End | HU01, HU02, HU03 | Commits, endpoints, migraciones |
| Sprint 4 | Integrar, probar y desplegar la V1 | HU03, HU04, HU05, HU06 | E1–E8, 120 pruebas, URL pública |

**Control de versiones.** Conventional Commits: `fix` 21, `feat` 15, `docs` 5,
`chore` 4, `test` 2, `build` 1, `refactor` 1. Los mensajes describen el problema
resuelto, no el archivo tocado — *«el catálogo no mostraba nada con 3 361
productos en la base»* dice más, un mes después, que *«update ProductsView»*.

---

## 11. Retrospectiva del Sprint 4

**Qué salió bien.** Medir en lugar de revisar a ojo encontró lo que la revisión
no veía: 683 fallos de contraste, una fuga de 160 ms que permitía enumerar DNI,
un filtro que el servidor ignoraba en silencio, 869 grupos duplicados y un CLS de
0.44. Ninguno de los seis se habría detectado leyendo el código.

**Qué problemas hubo, sin adornos.**

- *Afirmé cosas sin medirlas.* Di el catálogo por «cero CLS»; la medición dijo
  0.44. Es el error más repetido del avance.
- *Repetí un error ya documentado.* Las imágenes del carrito salieron rotas por
  escribir `urlDeMedia(...) || ilustracionDe(...)`, cuando el aviso de que el
  respaldo debe ser un hermano está en mayúsculas en `utils/media.ts` desde que
  el mismo fallo apareció en el catálogo.
- *Rompí la suite al actualizar dependencias de más.* PHPUnit 12 es incompatible
  con `nunomaduro/collision`; durante un rato el `vendor` quedó peor de como
  estaba.
- *Ejecuté dos ventas reales en lugar de una.* El primer `--aplicar` falló al
  escribir el archivo de salida, pero Artisan ya había confirmado la transacción.
  Quedaron los pedidos 186 y 187: el 186 es el que demuestra el reparto FEFO
  entre dos lotes.
- *Una medición temprana era inválida.* Comparé dos caminos de «usuario no
  encontrado» creyendo medir la diferencia entre existente e inexistente.

**Qué haremos mejor.** Medir antes de afirmar, y publicar la cifra —también
cuando contradice lo que se esperaba. Releer el aviso del módulo antes de tocarlo.
Separar la actualización de dependencias del trabajo funcional. Y comprobar que
un comando que escribe en la base no se ejecutó ya, antes de repetirlo.

---

## 12. Índice de evidencias

Explicación de cada una en [`docs/EVIDENCIAS-APF2.md`](EVIDENCIAS-APF2.md).

| # | Qué prueba | Categoría | Artefactos |
|---|---|---|---|
| E1 | PostgreSQL rechaza un producto duplicado | Base de datos | `E1-unique-rechaza-duplicado.txt` |
| E2 | Fusión de 869 grupos: antes y después | Lógica de negocio | `E2a-…`, `E2b-…` |
| E3 | Los contratos públicos respondiendo 200 | API | `E3-api-contratos-200.txt` |
| E4 | CLS del catálogo e ilustraciones referenciales | Front-End | `E4-*.txt`, `.png`, `.json` |
| E5 | Suite en verde e historial de commits | Entorno y pruebas | `E5a-…`, `E5b-…` |
| E6 | Carrito y contacto: stock, IGV, receta, datos reales | Integración | `E6-*.txt`, `.png`, `.json` |
| E7 | Venta de mostrador verificada de punta a punta | Integración + BD | `E7-venta-pos-aplicada.txt`, `E7-…-verificacion-externa.txt` |
| E8 | Tiempos de respuesta de la API | No funcional | `E8-tiempos-respuesta-api.txt` |

---

## Reproducir todo

```bash
# Backend  (en Windows: subst X: "<ruta del proyecto>\botica-san-juan-backend" y cd X:\)
php artisan test                                    # 120 pruebas
php artisan pos:venta-demostracion --cantidad=9     # simula y revierte
php artisan pos:venta-demostracion --cantidad=9 --aplicar
php artisan catalogo:fusionar-duplicados            # debe decir 0 grupos
php artisan db:seed --class=RecetaMedicaDemoSeeder  # idempotente
powershell -File scripts/medir-tiempos-api.ps1

# Frontend
pnpm type-check && pnpm build
node scripts/capturar-evidencias-apf2.mjs
node scripts/auditar-contraste-portal.mjs --temas=claro,oscuro
```
