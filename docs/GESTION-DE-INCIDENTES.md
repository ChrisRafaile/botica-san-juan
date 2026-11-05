# Gestión de incidentes

Punto 31 de la rúbrica: *«registro, prioridad, atención y cierre»*.

Los dos incidentes de este documento **ocurrieron de verdad**, en producción,
durante las semanas 17-18. No son simulacros. Se eligieron sobre un ejercicio
inventado por un motivo: en un simulacro, quien lo escribe ya sabe el final, y
lo que se aprende de un incidente es justamente lo que nadie sabía.

El procedimiento general —clasificación, prioridades y rutas de escalamiento—
está en `docs/SOLICITUDES-DE-SERVICIO.md`. Aquí se aplica.

---

## Clasificación usada

| Prioridad | Criterio | Objetivo de atención |
|---|---|---|
| **P1** | El servicio no funciona, o funciona mal sin que se note | Inmediata |
| **P2** | Una función falla; hay alternativa | Mismo día |
| **P3** | Defecto menor o cosmético | Siguiente iteración |

---

## INC-01 · Despliegue fallido que no se notaba

| | |
|---|---|
| **Fecha** | 4 de noviembre de 2026 |
| **Prioridad** | **P1** |
| **Componente** | Despliegue · API en Render |
| **Estado** | **Cerrado** |
| **Tiempo de atención** | ~12 minutos desde la detección |

### Detección

No la dio una alerta. Salió al **verificar manualmente** los endpoints contra
producción después de un release, como parte del bloque de SOA:

```
404  /api/catalogo              (debía existir tras el release)
500  /api/productos/destacados  (debía devolver 404 tras el arreglo)
403  /docs/v1                   (debía estar publicada)
```

Las tres respuestas correspondían al código **anterior** al release. Y sin
embargo `/api/salud` devolvía `200`.

> **Por qué la detección es el dato más importante de este incidente.** Si no
> llega a hacerse esa comprobación manual, nadie se entera. El monitoreo de
> `/api/salud` seguía en verde porque *había* un servicio respondiendo: el de
> cuatro días antes.

### Clasificación

**P1.** No porque el servicio estuviera caído —no lo estaba—, sino por lo
contrario: estaba sirviendo una versión obsoleta mientras todos los
indicadores decían que todo iba bien. Un fallo que se anuncia solo es menos
grave que uno que no.

### Diagnóstico

Se consultó el estado de los despliegues por la API del proveedor:

| Despliegue | Commit | Estado |
|---|---|---|
| `dep-dblbst49v7es73curfh0` | `ad623d8` (release) | **`update_failed`** |
| `dep-dbin1t7b9u1s73au3pb0` | `e62a54b` (31 de octubre) | `live` |

El despliegue murió en 38 segundos. En su bitácora:

```
Your configuration files could not be serialized because the value
at "sentry.before_send" is non-serializable.
Call to undefined method Closure::__set_state()
```

**Causa raíz.** `before_send` se había escrito como un *closure* dentro de
`config/sentry.php`. El arranque de producción ejecuta `php artisan
config:cache`, que serializa la configuración con `var_export`, y un closure
no se puede exportar. El contenedor moría antes de escuchar.

**Por qué las pruebas no lo vieron.** En desarrollo la configuración **no se
cachea nunca**. Las 357 pruebas pasaban en verde con el fallo dentro.

**Por qué nadie lo notó desde fuera.** Render mantiene viva la versión
anterior cuando un despliegue falla. Es un buen comportamiento —evita una
caída— y a la vez es el que convierte el fallo en invisible.

### Resolución

La lógica pasó a `App\Support\Observabilidad\FiltroDeEnvio`, una clase
invocable con `__set_state`, que es lo único que `var_export` necesita para
reconstruirla. PR **#36**, commit `e8c77e4`.

No hubo *workaround*: el arreglo era de minutos y aplicarlo era más rápido que
cualquier paliativo.

### Verificación

| | |
|---|---|
| Despliegue | `dep-dblc2k7b9u1s73b9r4fg`, commit `63b71d7`, estado **`live`** |
| `/api/catalogo` | `200` |
| `/docs/v1` | `200` |
| Arranque completo | `config:cache`, `route:cache` y `view:cache` comprobados a mano |

### Prueba de regresión

`DepuracionSentryTest::test_la_configuracion_se_puede_cachear` **ejecuta
`config:cache` de verdad** y limpia después.

Es la parte que importa: cualquier afirmación sobre el *contenido* del arreglo
de configuración habría pasado igual con el closure dentro. Solo ejecutando el
comando se detecta.

### Lo que deja

Este modo de fallo **ya estaba documentado** en el `Dockerfile`, por un
incidente de PHP 8.3 en octubre con el mismo patrón: build roto, versión
anterior viva, servicio respondiendo `200`. Volvió a ocurrir igual.

La conclusión no es «documentarlo mejor». Es que **una nota en un comentario
no previene nada**; lo que lo previene es una prueba que rompe la suite. Por
eso el cierre de este incidente no es el arreglo, es el test.

---

## INC-02 · Error 500 en una ruta pública

| | |
|---|---|
| **Fecha** | 4 de noviembre de 2026 |
| **Prioridad** | **P2** |
| **Componente** | API pública · catálogo |
| **Estado** | **Cerrado** |

### Detección

Al comprobar los endpoints públicos para el bloque de SOA:

```
GET /api/productos/destacados  →  500  { "message": "Server Error" }
```

### Clasificación

**P2.** Afecta a una ruta pública y sin autenticación —la que van a consumir
otros proyectos—, pero no impide operar: el catálogo responde por sus otras
rutas y el mostrador no se entera.

Agravante que se tuvo en cuenta: un `500` en una API abierta es lo primero que
encuentra quien explora, y un error de servidor donde correspondía un `404`
sugiere un fallo más profundo del que hay.

### Diagnóstico

No existía tal endpoint. La palabra `destacados` entraba por
`productos/{producto}`, y PostgreSQL rechazaba convertirla en entero:

```
GET /api/productos/destacados
  → Route::apiResource('productos')->only(['index','show'])
  → ProductoController::show('destacados')
  → Producto::findOrFail('destacados')
  → PostgreSQL: invalid input syntax for type integer
  → 500
```

**Causa raíz.** El parámetro de ruta no estaba restringido. Cualquier segmento
no numérico entraba a `show()` y reventaba en la capa de base de datos en vez
de no casar con la ruta.

### Resolución

`->whereNumber('producto')` en el recurso. PR **#32**.

**Y aquí apareció un segundo hallazgo.** Restringir solo las rutas de lectura
arreglaba el `500` pero dejaba un `405 Method Not Allowed`: mientras `PUT`,
`PATCH` y `DELETE` siguieran aceptando cualquier cadena, la URI seguía casando
con *alguna* ruta y el `GET` respondía «método no permitido» en lugar del
`404` que corresponde. La restricción se aplicó también a las tres rutas de
escritura.

### Verificación

| Ruta | Antes | Después |
|---|---|---|
| `/api/productos/destacados` | `500` | **`404`** |
| `/api/catalogo/destacados` | no existía | **`200`** |
| `/api/catalogo/999999` | — | `404` |

Comprobado contra producción. Evidencia en `SOA-08`.

### Prueba de regresión

`CatalogoPublicoTest::test_una_palabra_en_lugar_de_un_id_no_revienta`, que
afirma `404` tanto en `/api/productos/destacados` como en
`/api/catalogo/loquesea`.

### Lo que deja

El endpoint existía en la cabeza de quien lo pidió, no en el código. Un
framework que enruta por patrón convierte esa diferencia en un `500` si nadie
restringe los parámetros — y el `405` posterior enseña que un arreglo a medias
cambia el síntoma sin cerrar el hueco.

---

## Resumen

| | INC-01 | INC-02 |
|---|---|---|
| Prioridad | P1 | P2 |
| Detectado por | Verificación manual | Verificación manual |
| Causa raíz | Closure no serializable en configuración | Parámetro de ruta sin restringir |
| Resolución | PR #36 | PR #32 |
| Regresión | `test_la_configuracion_se_puede_cachear` | `test_una_palabra_en_lugar_de_un_id_no_revienta` |
| Estado | Cerrado | Cerrado |

**Los dos los encontró una persona mirando, no una alerta.** Es el hallazgo
común, y apunta a la misma mejora: el monitoreo comprueba que el servicio
responde, no que responda *lo que debe*. Una comprobación posterior al
despliegue que verifique endpoints concretos —no solo `/api/salud`— habría
detectado INC-01 en segundos en lugar de en una revisión manual.

Queda propuesto en las recomendaciones del informe final.
