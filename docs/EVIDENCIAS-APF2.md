# Evidencias técnicas — APF2

**Proyecto:** Sistema de gestión y venta — Botica San Juan
**Curso:** Curso Integrador II: Sistemas · UTP
**Fecha de generación:** 6 de octubre de 2026
**Carpeta de artefactos:** [`docs/evidencias/`](evidencias/)

---

## El criterio que se aplicó

> «Evidencia ≠ captura decorativa: debe probar que el sistema funciona o que se
> tomó una decisión técnica.»

Cada archivo de esta carpeta cumple al menos una de dos condiciones:

1. **Es la salida literal de una ejecución real** — del motor de PostgreSQL, de
   un comando de Artisan, de una petición HTTP, de PHPUnit o de git. No se
   transcribió ni se reescribió nada a mano.
2. **Es reproducible**: debajo de cada evidencia está el comando exacto que la
   genera. Si mañana alguien rompe lo que la evidencia prueba, volver a
   ejecutarla lo delata.

Donde una medición contradijo lo que yo había afirmado antes, **queda registrada
la cifra mala junto a la buena** (ver E4). Una evidencia que solo enseña el
resultado favorable no prueba nada.

---

## Índice

| # | Evidencia | Categoría de la rúbrica | Artefactos |
|---|---|---|---|
| E1 | El motor de BD rechaza un producto duplicado | Base de datos / Integridad | `E1-unique-rechaza-duplicado.txt` |
| E2 | Fusión de 869 grupos duplicados: antes y después | Lógica de negocio / Script | `E2a-fusion-simulacion-antes.txt`, `E2b-fusion-verificacion-despues.txt` |
| E3 | Contratos de la API respondiendo 200 con datos reales | Contratos de API | `E3-api-contratos-200.txt` |
| E4 | CLS del catálogo e ilustraciones referenciales | Frontend / Performance | `E4-frontend-cls-y-svg.txt`, `E4-catalogo-{claro,oscuro}.png`, `E4-metricas-{claro,oscuro}.json` |
| E5 | Suite de pruebas en verde e historial de commits | Entorno y pruebas | `E5a-pruebas-automatizadas.txt`, `E5b-git-historial.txt` |
| E6 | Carrito y Contacto: stock, IGV, persistencia y datos operativos | Lógica de negocio + Frontend | `E6-carrito-y-contacto.txt`, `E6-{carrito,contacto}-{claro,oscuro}.png`, `E6-*-metricas-*.json`, `E6-contraste.json` |

---

## E1 — Integridad: la base de datos rechaza el duplicado

**Qué prueba:** que la no duplicación del catálogo ya no depende de que el código
se acuerde de comprobarla, sino de una restricción del motor que ningún camino
de la aplicación puede saltarse.

Se intenta insertar una fila idéntica en los campos de identidad a un producto
existente (`A FOLIC · 0.5 mg · IQ FARMA · TABLETA`). PostgreSQL responde:

```
SQLSTATE[23505]: Unique violation: 7 ERROR: llave duplicada viola
restricción de unicidad «productos_identidad_unique»
```

y el catálogo sigue en 884 productos, sin cambios.

**Decisión técnica que documenta:** el índice lleva `NULLS NOT DISTINCT`. Un
`UNIQUE` normal no habría impedido nada, porque en SQL dos `NULL` no son iguales
entre sí y cientos de productos tienen `concentracion` o `adicional` en `NULL`:
el índice los habría dejado pasar todos.

**Reproducir:** `php artisan tinker` con el guion incluido en la cabecera del
archivo.

---

## E2 — Lógica de negocio: fusión de duplicados sin inflar el inventario

**Qué prueba:** que el catálogo tenía 869 grupos de filas duplicadas por
reimportación, que el comando los resolvió, y que después no queda ninguno.

- **E2a** — simulación previa (modo por defecto, no escribe): el informe de los
  869 grupos, las filas que se eliminarían y las unidades que se descartan.
- **E2b** — el mismo comando tras aplicar la fusión: *«No hay grupos
  duplicados»*.

Resultado en local: **3 361 → 884 productos**, **17 086 → 4 524 unidades**, sin
una sola venta, movimiento de stock ni incidencia huérfana.

**Las dos decisiones técnicas que esta evidencia respalda:**

1. **No se suman los stocks.** De los 869 grupos, 858 tenían el stock idéntico en
   todas sus copias (8,8,8,8 · 10,10,10,10). Eso es la misma mercancía física
   reimportada cuatro veces, no cuatro entregas: sumarla habría llevado el
   inventario de 4 434 a **16 975 unidades** y el punto de venta habría prometido
   existencias que no están en el anaquel.
2. **El superviviente se elige por actividad, no por id.** En los 11 grupos con
   stock discordante, la fila con movimientos reales era siempre la de *menos*
   stock, y en uno de ellos (AMOXICILINA / PHARMAGEN) **no** era la de id menor
   — era el id 835 frente al 72. Conservar «el primero» habría dejado viva una
   copia de importación y descartado la fila que realmente se vendía.

**Procedimiento completo, incluido el respaldo previo:**
[`docs/RUNBOOK-fusion-duplicados.md`](RUNBOOK-fusion-duplicados.md).
Pendiente de aplicar en producción (Neon), cuando el usuario lo autorice.

---

## E3 — Contratos de la API

**Qué prueba:** que los endpoints públicos del catálogo responden `200` con
datos reales, y que la paginación y los filtros se resuelven **en el servidor**,
no ocultando en el navegador productos ya descargados.

Tres llamadas HTTP con su respuesta JSON literal:

| Petición | Qué demuestra |
|---|---|
| `GET /api/productos?per_page=2&orden=nombre` | `total = 884`, `last_page = 442` — el servidor pagina de verdad: con `per_page=2` devuelve 2 filas, no 884 |
| `GET /api/productos/facetas` | tipos, laboratorios y categorías agregados **en SQL**, no contando en el cliente |
| `GET /api/productos?tipo=INYECTABLE` | el filtro llega al `WHERE`; antes el servidor lo ignoraba en silencio |

Esta evidencia sostiene la columna *Endpoint* de la cadena de trazabilidad
**RF → HU → Pantalla → Endpoint → Tabla → Caso de prueba**. El contrato completo
en OpenAPI 3.1 se consulta en el visor interactivo **`/docs/api`** (generado por
Scramble desde el propio código, protegido por `RestrictedDocsAccess` para que
solo se sirva en entorno local).

---

## E4 — Frontend: salto de maquetación e ilustraciones referenciales

**Qué prueba:** que el catálogo carga sin que el contenido salte bajo el cursor,
y que las fichas sin fotografía muestran una imagen que informa sin mentir.

**Aquí la medición desmintió una afirmación mía.** Yo había dado la página por
«cero CLS» porque los SVG llevaban `width` y `height`. Medido con
`PerformanceObserver`:

| | CLS | Veredicto |
|---|---|---|
| Antes | **0.44382** | POBRE (el umbral de «pobre» está en 0.25) |
| Después — tema claro | **0.01524** | BUENO (≤ 0.1) |
| Después — tema oscuro | **0.01350** | BUENO |

El culpable no eran las imágenes: **0.4237 de los 0.44382 los aportaba el pie de
página**, que se pintaba a 519 px del borde mientras la rejilla estaba vacía y
se iba fuera de pantalla al llegar los 24 productos. La corrección fue un
esqueleto de carga que ocupa exactamente el sitio de las tarjetas futuras.
**Reducción del 96.6 %.**

La misma ejecución verifica, en los dos temas: 24 tarjetas (= `per_page`),
16 ilustraciones de forma farmacéutica, **16 de 16 con `width`/`height`
explícitos**, 16 etiquetas visibles «Imagen referencial».

**Decisión técnica que documenta:** la botica no tiene fotografías de sus 884
productos. Una foto de archivo parecida sería peor que ninguna — en un
medicamento, sugerir un envase que no es el real puede llevar a comprar lo que
no se quería. Por eso la imagen representa la **forma farmacéutica** (tableta,
jarabe, inyectable), la tarjeta lo advierte con una etiqueta visible, y el texto
alternativo repite la advertencia para quien usa lector de pantalla.

**Reproducir:** `node scripts/capturar-evidencias-apf2.mjs` desde
`botica-san-juan-frontend/`. El script fija el tamaño de ventana, espera la
carga, mide y captura: si alguien quita el esqueleto, la cifra lo delata.

---

## E5 — Entorno y pruebas

### E5a — Suite automatizada en verde

```
107 passed · 298 assertions · 9.57 s
```

PHPUnit 11.5.42 sobre PHP 8.4, contra la base `botica_san_juan_test`.

**Decisión técnica que documenta:** llegar a este verde costó descubrir un fallo
del entorno, no del código. **Windows trunca nombres de archivo largos al
extraer los ZIP de Composer en la ruta de este proyecto** — 13 archivos por
instalación limpia, *sin que Composer dé ningún error*. Las clases dejaban de
existir y PHPUnit abortaba antes de ejecutar una sola prueba, con un mensaje que
apuntaba a un sitio equivocado. Se resolvió montando el proyecto en una unidad
corta con `subst X:`, lo que baja la ruta más larga de ~230 a ~110 caracteres:
**0 archivos truncados, 107 pruebas en verde.** Diagnóstico completo y lo que
*no* funcionó en [`docs/ENTORNO-ruta-larga-windows.md`](ENTORNO-ruta-larga-windows.md).

### E5b — Control de versiones auditable

Historial de `develop → main` en `github.com/ChrisRafaile/botica-san-juan`, con
el reparto de commits por tipo de **Conventional Commits**: `fix` 21, `feat` 15,
`docs` 5, `chore` 4, `test` 2, `build` 1, `refactor` 1.

Los mensajes describen el cambio en términos del problema que resuelve
(*«el catálogo no mostraba nada con 3 361 productos en la base»*), no en términos
del archivo tocado. Eso hace el historial legible como evidencia de avance.

---

## E6 — Carrito y Contacto

**Qué prueba:** que el carrito no puede prometer unidades que no están en el
anaquel, que el IGV se desglosa como lo desglosa la boleta, y que los datos de
contacto que publica el portal son los reales.

**El punto de partida:** `/cart` no existía como ruta. El icono del carrito de
la cabecera enlazaba ahí desde el principio y llevaba a «página no encontrada» —
el mismo fallo que ya tuvo `/products`.

Tres cosas que esta evidencia sostiene:

1. **El tope de cantidad lo aplica el servidor.** El carrito guarda en el
   navegador sólo `{producto_id, cantidad}`; el precio, el stock y el aviso de
   receta se piden a `POST /api/carrito/cotizar` cada vez. Mandando 99 unidades
   de un producto con 3, la respuesta devuelve 3. Mandando un precio inventado
   de `0.01`, cobra los S/ 25.50 reales. Un lote vencido cuenta 0, aunque esté
   físicamente en el anaquel. **13 pruebas** lo cubren.

2. **El IGV se extrae del precio, no se suma.** En la captura: S/ 4.24 + S/ 0.76
   = S/ 5.00. Sumando el impuesto habrían salido S/ 5.90. El cálculo se extrajo
   a `DesgloseFiscalService`, que ahora usan **el portal y el mostrador**: tenerlo
   duplicado habría hecho que la web y la boleta se separaran el día que cambie
   la tasa, sin que nadie lo notase. Una prueba existe sólo para impedir esa
   duplicación.

3. **El portal publicaba cuatro números de WhatsApp a la vez**, uno de ellos el
   teléfono fijo (que no tiene WhatsApp) y otro de relleno. Ahora hay una fuente
   única, `src/datos/botica.ts`, y mientras el número no esté confirmado los
   botones de WhatsApp **no se pintan** en vez de enlazar a uno inventado. Se
   quitaron además las promesas de «24/7» publicadas junto a un horario que
   cierra a las diez, y tres correos en un dominio que rebota.

**Mediciones:** CLS 0.053 / 0.050 (carrito) y 0.015 / 0.013 (contacto) en los
temas claro y oscuro — todos BUENO; **302 comprobaciones de contraste, 0 fallos**;
120 pruebas en verde (eran 107); `type-check` y `build` limpios.

**Lo que esta evidencia NO prueba, y conviene decirlo:** el aviso de receta está
implementado y probado, pero **0 de los 884 productos** están marcados como venta
bajo receta y **0** como exonerados de IGV, así que con los datos actuales ni el
aviso ni la línea de exonerado aparecen nunca. Es un dato regulatorio que sale
del catálogo DIGEMID (hoy con 5 filas de demo), no una decisión de programación:
queda como dato pendiente de cargar, no como función pendiente de escribir.

---

## Pendientes declarados

Se listan aquí por honestidad del informe: son cosas que esta carpeta **no**
prueba todavía.

- **Validación del POS contra la API desplegada en Render.** La hará el usuario
  con las cuentas de demostración.
- **Fusión de duplicados en producción (Neon).** El runbook está escrito y
  probado en local; falta ejecutarlo, con respaldo previo, cuando se autorice.
- **Número de WhatsApp de la botica.** El portal tenía cuatro distintos y ninguno
  verificable. Hay que confirmarlo con el dueño y ponerlo en
  `VITE_BOTICA_WHATSAPP`; hasta entonces los botones de WhatsApp no se muestran.
- **Catálogo DIGEMID.** Con 5 filas de demo y 1 producto cruzado, el aviso de
  venta bajo receta y el desglose de exonerados no llegan a activarse nunca.
- **Cifras del portal:** unificar a «más de 10 años» y «más de 8k clientes +»
  (el pie ya dice 10; Inicio y Sobre Nosotros siguen dando números distintos).
