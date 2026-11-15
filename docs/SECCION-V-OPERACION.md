# Sección V · Operación del Sistema

**Maestro de contenido para `Proyecto_Final_Botica_San_Juan.docx`.**

Este archivo es la fuente de la que el script `ensamblar-proyecto-final.py`
inyecta las secciones **XXVIII a XXXIV** en la copia de trabajo. No es un
documento para leer suelto: es el texto del informe, con su numeración y sus
citas ya resueltas, mantenido en Markdown para que una cifra equivocada se
corrija aquí y no dentro de un `.docx` de 3 750 párrafos.

**Numeración continuada.** El APF3 cierra en la **Tabla 92** y la **Figura
73**. Esta sección empieza en la **Tabla 93** y la **Figura 74**. Las
secciones I a XXVII del APF3 no se modifican.

**Convención de evidencias.** Cada afirmación verificable cita su código de
`docs/evidencias/INDICE.md` (`CRT-14`, `EVT-02`, `CON-03`…). Un código remite
a un archivo que contiene, dentro de sí mismo, el procedimiento para
reproducirlo.

---
---

# XXVIII. CORRECCIONES DEL APF3

El APF3 cerró reconociendo cuatro observaciones sin resolver. Este apartado
las muestra **dos veces**: como estaban y como están. El contraste es el
contenido —una tabla que solo enseñe el resultado final no deja ver qué
trabajo costó llegar a él—, y además reescribir la historia sería fácil de
detectar: la Tabla 64 del APF3 ya está entregada y calificada.

## 28.1. Lo que quedó abierto

**Tabla 93**
*Observaciones pendientes al cierre del APF3 (reproducción de la Tabla 64)*

| N.º | Observación | Por qué seguía abierta |
|---|---|---|
| **O-B1** | Las Figuras 43 y C.3 muestran ejecuciones de la suite con cifras ya superadas | Son capturas: hay que volver a tomarlas. Reescribir el pie haría que mintiera sobre su propia imagen |
| **O-B2** | La Figura 11 rotula «26 migraciones versionadas» dentro del PNG; hoy son 49 | El dato está incrustado en la imagen; exige regenerar el diagrama |
| **O-B3** | Las cifras del portal público no coinciden entre Inicio, Sobre Nosotros y el pie | Decisión de negocio sobre cuál es la correcta |
| **O-B4** | Los 884 productos del catálogo apuntan a un archivo de imagen que ya no existe | Todas las imágenes responden 404. El respaldo lo resuelve visualmente, pero el catálogo sigue sin fotografías |

*Nota.* Reproducción literal de la Tabla 64 del APF3.

## 28.2. Lo que las cerró

**Tabla 94**
*Cierre de las observaciones O-B1 a O-B4*

| N.º | Qué se hizo | Resultado verificado | Evidencia |
|---|---|---|---|
| **O-B1** | Se volvió a ejecutar la suite completa y se recompusieron las figuras desde la salida literal | **379** pruebas de backend y **116** de frontend al cerrar la observación (`CRT-11` a `CRT-17`). El corte vigente, medido de nuevo el 13-11-2026, es de **419** de backend (1 172 aserciones, 34 clases) y **154** de frontend (19 archivos): 573 en total | `CRT-18` a `CRT-24`; Figuras 78 y 79 |
| **O-B2** | En vez de retocar el PNG se escribió `docs/scripts/generar-figura11.py`, que **cuenta** los archivos de migración | **54** migraciones, contadas por el script. La cifra no puede volver a quedarse vieja sin que alguien ejecute el script y lo vea | `docs/evidencias/figura11/` |
| **O-B3** | Decisión de negocio tomada y aplicada a las cuatro vistas públicas | Homologado: «+800 productos farmacéuticos», «Más de 8 años de servicio en San Juan de Lurigancho», «Atención directa en nuestro local principal y delivery local» | `Hero.vue`, `Stats.vue`, `AboutContent.vue`, `Footer.vue` |
| **O-B4** | Se comprobó el archivo real servido en producción, en lugar de suponer la causa | `200`, `image/png`, **2 255 B**, **512×512**, decodifica correctamente | `M02`, `M03` |

*Nota.* Elaboración propia.

## 28.3. Dos observaciones que cerraron mejor de lo que pedían

**O-B2 dejó de ser un dato y pasó a ser un cálculo.** La observación pedía
corregir un número incrustado en una imagen. Corregirlo a mano habría
funcionado hasta la siguiente migración. El script cuenta los archivos del
directorio y escribe el diagrama, de modo que el defecto —un número que
envejece sin avisar— ya no puede reaparecer.

**O-B4 enseñó a no diagnosticar por hipótesis.** La explicación plausible era
un desajuste entre la URL y la variable de entorno. La comprobación empírica
sobre el archivo servido mostró otra cosa: el fichero existía y pesaba 33
bytes de texto ASCII, no una imagen. La corrección fue distinta de la que se
habría aplicado siguiendo la hipótesis.

---
---

# XXIX. MONITOREO DEL SISTEMA

Un indicador sin umbral es un adorno: nadie sabe cuándo actuar. Este apartado
convierte los compromisos de las **Tablas 33 y 34 del APF1** en reglas que una
herramienta puede evaluar, les añade un nivel preventivo que el APF1 no tenía,
y —en 29.4— dice cuáles se están midiendo hoy y cuáles no.

## 29.1. Cómo se construyó la matriz

Para no diseñar los umbrales a ciegas se tomó como referencia el tablero
público de **Blackfire** (entorno de demostración «BigFoot PHP»), consultado
el 4 de noviembre de 2026. De allí se adoptan cuatro criterios y se rechaza
uno.

**Primero: la regla de alerta es una frase ejecutable.** La regla publicada en
la demostración dice literalmente *«envía notificación por correo cuando el
promedio del tiempo de respuesta sea ≥ 450 ms durante los últimos 5
minutos»*. Son seis piezas —canal, agregación, métrica, comparador, valor y
ventana—. Un umbral sin ventana ni agregación no es una regla: «la latencia no
debe pasar de 800 ms» no dice si se mide la media o el percentil ni sobre
cuánto tiempo, y por tanto no se puede evaluar ni incumplir. Las doce filas de
la Tabla 95 llevan las seis piezas.

**Segundo: el percentil manda sobre el promedio.** El mismo tablero muestra, en
la misma ventana y sobre el mismo tráfico, un percentil 96 de **700 ms** y una
mediana de **100 ms**. Siete veces de diferencia. Un promedio sano puede
convivir con que una de cada veinticinco peticiones tarde siete veces más, y
esa petición es un cliente esperando en el mostrador.

**Tercero: la distribución importa más que el número único.** La referencia
reparte las peticiones en cubos de duración y marca la variación de cada cubo
contra la semana anterior. Lo que se vigila no es que la cifra suba, sino que
**la cola engorde**.

**Cuarto: la segmentación.** El tablero filtra por servidor, método y entorno
de ejecución. Un umbral único para todo el sistema garantiza que algo esté mal
calibrado: el punto de venta y un reporte gerencial no tienen por qué
responder igual.

**Lo que se rechaza.** Blackfire usa **un solo umbral por regla**. Aquí hacen
falta dos. No hay un equipo de guardia: hay una persona que además atiende el
mostrador, y un aviso que solo salta cuando el servicio ya está mal llega
tarde. El nivel preventivo existe para que la acción sea *revisar esta semana*
y no *levantarse de madrugada*.

## 29.2. Matriz de indicadores y umbrales

**Tabla 95**
*Matriz de monitoreo: indicador, umbrales y acción operativa*

| # | Indicador | Cómo se mide | Preventivo | Crítico | Acción al cruzarlo |
|---|---|---|---|---|---|
| **M1** | Disponibilidad del servicio | Sondeo de `/api/salud` y `/api/health/db` cada 5 min; disponible = `200` con `ok:true`. Mensual sobre 08:00–22:00 | < 99,5 % en 7 días | 2 sondeos seguidos fallidos (10 min), o < 99 % mensual | Revisar el despliegue vivo y la conexión a la base; incidente P1 |
| **M2** | Latencia de la API, p95 | Percentil 95 de las rutas de lectura, ventana de 15 min, trazas de Sentry | p95 > 800 ms sostenido 15 min | p95 > 1 500 ms sostenido 15 min | Identificar la ruta por la traza; si es consulta, revisar índices |
| **M3** | Latencia de la búsqueda | p95 de `/api/productos?buscar=` y `/api/catalogo?buscar=` bajo carga | p95 > 1 000 ms | p95 > 2 000 ms | Supera el compromiso con el propietario: se detiene la promesa de «menos de dos segundos» hasta corregir |
| **M4** | Tasa de error del servidor | `5xx ÷ total`, ventana de 15 min | > 0,5 % en 15 min | > 1 % en 15 min, **o cualquier 5xx en ruta pública del catálogo** | Incidente P2 como mínimo |
| **M5** | Errores del cliente por versión | Eventos de Sentry agrupados por *release*, primeras 24 h del despliegue | > 5 eventos nuevos | Error que afecta a > 1 % de las sesiones | Revertir el despliegue en Vercel y diagnosticar después |
| **M6** | Coherencia del despliegue | Tras publicar: `/api/catalogo` → 200, `/docs/v1` → 200, `/api/productos/destacados` → 404 | Cualquier respuesta distinta de la esperada | Ídem, y el despliegue figura `update_failed` | El servicio sirve código viejo: volver a desplegar y no dar por bueno el *release* |
| **M7** | Memoria del proceso | Métrica de la instancia, media de 15 min | > 70 % del límite del plan | > 90 % sostenido 15 min, o reinicio por memoria | Buscar la petición que la dispara antes de subir de plan |
| **M8** | Consultas SQL por petición | Presupuesto por ruta de listado | > 15 consultas | > 40 consultas, o crecimiento proporcional al número de filas | Es un N+1: cargar la relación por adelantado |
| **M9** | Éxito del respaldo diario | Código de salida del respaldo programado; mensual, el simulacro | Un fallo en 7 días | Dos fallos seguidos, o el simulacro no recupera las filas | El RPO de 24 h deja de estar cubierto: respaldo manual inmediato |
| **M10** | Comprobantes aceptados por la SUNAT | Aceptados ÷ enviados, sobre el CDR registrado | < 99 % | < 97 %, o tres rechazos del mismo código | Detener el envío automático y revisar el armado del XML |
| **M11** | Pruebas automatizadas en verde | Resultado del pipeline por subida | Cualquier prueba en rojo | Rojo en `main` | No se despliega; el pipeline ya lo impide |
| **M12** | Eventos de nivel `error` | Recuento en `eventos_operacion`, ventana de 24 h, visible en `/admin/eventos` | > 10 en 24 h | > 50 en 24 h, o 5 del mismo código en 1 h | Abrir incidente: el código repetido indica fallo sistemático |

*Nota.* Elaboración propia. Detalle completo en `docs/MONITOREO-Y-UMBRALES.md`.

## 29.3. Segmentación de los umbrales

Los indicadores M2, M4, M7 y M8 no se aplican igual a todo el sistema.

**Tabla 96**
*Ajuste de los umbrales por segmento de tráfico*

| Segmento | Qué incluye | Ajuste sobre el umbral base |
|---|---|---|
| **Mostrador** | Punto de venta, confirmación de venta, búsqueda del POS | **La mitad.** Si el POS tarda, hay un cliente esperando en el local |
| **Catálogo público** | `GET /api/catalogo*`, documentación de la API | Base. Es lo que consumen terceros |
| **Panel de administración** | Tablero, inventario, reportes gerenciales | **El doble.** Un reporte puede tardar; nadie está en caja |
| **Procesos de fondo** | Envío a la SUNAT, sincronización offline, importación DIGEMID | Sin umbral de latencia; se vigilan por tasa de error y cola pendiente |

*Nota.* Elaboración propia.

## 29.4. Qué se mide hoy

Este apartado existe porque una tabla de umbrales sin él es una declaración de
intenciones con formato de tabla.

**Tabla 97**
*Estado real de la medición de cada indicador*

| Estado | Indicadores | Detalle |
|---|---|---|
| **Activo** | M11, M12, M5, M9 (parcialmente) | El pipeline bloquea; el visor de eventos es consultable; Sentry agrupa por *release* cuando hay claves |
| **Instrumentado sin umbral configurado** | M2, M4 | Las trazas llegan a Sentry; nadie ha creado todavía la regla que avise |
| **Manual** | M6 | Se hace, y encontró el INC-01. No está automatizado |
| **Script existente, sin programar** | M1 | `infra/monitoring/healthcheck_monitor.ps1`, más el impedimento del apartado 29.5 |
| **Sin medir** | M3, M7, M8 | La prueba de carga con p95 nunca se ejecutó; memoria y consultas SQL no están instrumentadas |
| **Medible sin volumen real** | M10 | El cliente conmutable está; la homologación con el proveedor no se cerró |

*Nota.* Elaboración propia. Cuatro activos, tres parciales, cinco sin medir.

## 29.5. Por qué el 99 % de disponibilidad no es verificable hoy

La capa gratuita del proveedor **duerme la instancia tras quince minutos sin
tráfico**, con unos 50 segundos de arranque en frío. De ahí se siguen dos
cosas, y la segunda es la incómoda:

1. La disponibilidad del 99 % **no es alcanzable** en el sentido que el APF1
   quería decir.
2. Un sondeo cada cinco minutos **mantendría el servicio despierto**, con lo
   que el monitor dejaría de medir disponibilidad para pasar a fabricarla.

Lo honesto es declarar el compromiso como **no verificable en la
infraestructura actual** y poner su condición al lado: exige un plan de pago.
Un indicador que solo da verde porque quien lo mide lo sostiene no es un
indicador.

## 29.6. Reconciliación con el acuerdo de nivel de servicio del APF1

**Tabla 98**
*Correspondencia entre los compromisos del APF1 y la matriz de monitoreo*

| Compromiso (Tablas 33 y 34 del APF1) | Indicador | Cambio introducido y por qué |
|---|---|---|
| Disponibilidad ≥ 99 %, alerta < 98,5 % | M1 | Se añade un disparador inmediato (dos sondeos fallidos). Un corte de dos horas se nota en el mostrador ese mismo día, no a fin de mes |
| Latencia p95 ≤ 800 ms, alerta > 1 500 ms | M2 | Se conservan los dos valores: la meta pasa a preventivo y la alerta a crítico. Al APF1 solo le faltaban ventana y agregación |
| Error de servidor ≤ 0,5 %, alerta > 1 % | M4 | Ídem, más una condición extra: cualquier 5xx en el catálogo público es crítico, por la lección del INC-02 |
| Búsqueda ≤ 2 s en p95 | M3 | Sin cambio. Es el único umbral que todavía no tiene medición |
| Detección de incidencia ≤ 5 min | M1 y M6 | **Se corrige.** Cinco minutos era el intervalo del sondeo, no el tiempo de detección. El INC-01 demuestra que un latido puede no detectar nada nunca |
| Contención 15 min / restauración 60 min | Sección XXXI | INC-01 se atendió en ~12 minutos |
| RPO 24 h / RTO 60 min | M9 | Procedimiento verificado; programación diaria no activa |
| Comprobantes SUNAT ≥ 99 % | M10 | Sin cambio |
| Pruebas en verde 100 % | M11 | Cumplido: 419 de backend y 154 de frontend |
| — | M6, M7, M8, M12 | Cuatro indicadores nuevos: M6 y M12 los pide la experiencia del proyecto, M7 y M8 la referencia |

*Nota.* Elaboración propia. Los indicadores de negocio del APF1 —reducción del
tiempo de atención y exactitud del inventario— no figuran aquí a propósito: se
miden contra la operación de la botica, no contra el sistema, y van en la
sección XXXIII.

---
---

# XXX. GESTIÓN DE EVENTOS DE OPERACIÓN

## 30.1. Qué había y qué faltaba

El modelo `EventoOperacion` existía desde el Sprint 4, con los tres niveles que
la rúbrica nombra —`info`, `warning`, `error`—, diez puntos del código que
registran eventos, su tabla `eventos_operacion` y un espejo local para cuando
no hay conexión.

**Pero no había forma de verlos.** Ni endpoint, ni pantalla. La única evidencia
posible era una consulta SQL contra la base, que como demostración de *gestión*
de eventos es pobre: enseña que el dato existe, no que se gestione. La
diferencia entre registrar y gestionar es exactamente esa pantalla.

## 30.2. Los tres niveles y su criterio

**Tabla 99**
*Niveles de evento y cuándo se usa cada uno*

| Nivel | Criterio | Ejemplo real registrado |
|---|---|---|
| `info` | La operación ocurrió como se esperaba y conviene dejar rastro | Sincronización de ventas offline completada; importación del padrón DIGEMID |
| `warning` | Algo salió de lo normal pero el sistema siguió operando | Intento de venta sobre un lote vencido; reintento de envío a la SUNAT |
| `error` | Una operación no se completó | Fallo al generar el comprobante; rechazo del CDR |

*Nota.* Elaboración propia. La constante del nivel intermedio es
`NIVEL_AVISO`.

## 30.3. El visor

Se construyó `GET /api/eventos` y `GET /api/eventos/resumen`, ambos tras el
middleware `admin`, y la pantalla `/admin/eventos` con filtro por **nivel**,
por **código**, por **texto** y por **rango de fechas**.

Tres decisiones del visor que conviene justificar:

- **El filtro de nivel son botones, no un desplegable.** Los tres niveles
  caben a la vista; obligar a abrir un menú para cambiar entre ellos añade un
  gesto sin ganar nada.
- **El esqueleto de carga ocupa la altura exacta de una fila real.** Si no, la
  tabla salta al llegar los datos, y el estándar del Sprint 5 fija un CLS
  ≤ 0,015.
- **El mensaje y el contexto pasan por `Depurador::texto` antes de mostrarse.**
  Un evento puede haber recogido un correo o un DNI; el visor los tapa en la
  propia pantalla. En la Figura 75 se ve el enmascarado actuando sobre un
  correo real.

**Figura 74.** Bitácora de operación con los tres niveles que pide la rúbrica.
*Fuente:* `docs/evidencias/eventos/EVT-01-bitacora-tres-niveles.jpg`.

**Figura 75.** Filtro aplicado al nivel `error`, con el depurador tapando un
correo en la propia pantalla.
*Fuente:* `docs/evidencias/eventos/EVT-02-filtro-por-nivel-error.jpg`.

**Figura 76.** Detalle de un evento con su contexto completo.
*Fuente:* `docs/evidencias/eventos/EVT-03-detalle-con-contexto.jpg`.

## 30.4. Verificación

**Tabla 100**
*Comprobaciones del módulo de eventos*

| Qué se comprobó | Resultado |
|---|---|
| Acceso sin sesión | `401` |
| Acceso con rol cliente | `403` |
| Filtro por nivel, código, texto y rango de fechas | Cada uno devuelve solo lo que corresponde |
| El filtro `hasta` incluye el día completo | Verificado con `endOfDay()` |
| El mensaje y el contexto salen depurados | Verificado sobre un correo y un DNI reales |
| Pruebas automatizadas | **16**, en `BitacoraDeOperacionTest` |

*Nota.* Elaboración propia. Acta completa en `EVT-04`.

---
---

# XXXI. GESTIÓN DE INCIDENTES

Los dos incidentes de este apartado **ocurrieron de verdad**, en producción,
durante las semanas 17-18. Se eligieron sobre un ejercicio inventado por un
motivo: en un simulacro, quien lo escribe ya sabe el final, y lo que se
aprende de un incidente es justamente lo que nadie sabía.

El procedimiento general está en `docs/SOLICITUDES-DE-SERVICIO.md`; aquí se
aplica.

## 31.1. Clasificación empleada

**Tabla 101**
*Prioridades de incidente*

| Prioridad | Criterio | Objetivo de atención |
|---|---|---|
| **P1** | El servicio no funciona, o funciona mal sin que se note | Inmediata |
| **P2** | Una función falla; hay alternativa | Mismo día |
| **P3** | Defecto menor o cosmético | Siguiente iteración |

*Nota.* Elaboración propia.

## 31.2. INC-01 · Despliegue fallido que no se notaba

**Tabla 102**
*Ficha del incidente INC-01*

| Campo | Contenido |
|---|---|
| **Fecha** | 4 de noviembre de 2026 |
| **Prioridad** | **P1** |
| **Componente** | Despliegue · API |
| **Detección** | Verificación manual de endpoints tras un *release*. **No la dio una alerta** |
| **Síntoma** | `/api/catalogo` → 404, `/api/productos/destacados` → 500, `/docs/v1` → 403, mientras `/api/salud` → **200** |
| **Causa raíz** | `before_send` escrito como *closure* en `config/sentry.php`. El arranque ejecuta `config:cache`, que serializa con `var_export`, y un closure no se puede exportar: `Call to undefined method Closure::__set_state()` |
| **Por qué no se veía** | El proveedor mantiene viva la versión anterior cuando un despliegue falla. Evita una caída y, al hacerlo, vuelve el fallo invisible |
| **Por qué las pruebas no lo vieron** | En desarrollo la configuración **no se cachea nunca**. Las 357 pruebas de entonces pasaban en verde con el fallo dentro |
| **Resolución** | La lógica pasó a la clase invocable `App\Support\Observabilidad\FiltroDeEnvio`, con `__set_state`. PR #36 |
| **Verificación** | Despliegue `live`; `/api/catalogo` → 200, `/docs/v1` → 200; `config:cache`, `route:cache` y `view:cache` comprobados |
| **Prueba de regresión** | `DepuracionSentryTest::test_la_configuracion_se_puede_cachear`, que **ejecuta el comando de verdad** |
| **Tiempo de atención** | ~12 minutos desde la detección |
| **Estado** | **Cerrado** |

*Nota.* Elaboración propia.

**Lo que deja.** Este modo de fallo **ya estaba documentado** en un comentario
del `Dockerfile`, por un incidente de octubre con el mismo patrón: construcción
rota, versión anterior viva, servicio respondiendo `200`. Volvió a ocurrir
igual. La conclusión no es «documentarlo mejor»: una nota en un comentario no
previene nada. Lo que lo previene es una prueba que rompe la suite, y por eso
el cierre del incidente no es el arreglo, es el test. Cualquier aserción sobre
el *contenido* del arreglo de configuración habría pasado igual con el closure
dentro; solo ejecutando el comando se detecta.

## 31.3. INC-02 · Error 500 en una ruta pública

**Tabla 103**
*Ficha del incidente INC-02*

| Campo | Contenido |
|---|---|
| **Fecha** | 4 de noviembre de 2026 |
| **Prioridad** | **P2** |
| **Componente** | API pública · catálogo |
| **Detección** | Comprobación de los endpoints públicos para el bloque de interoperabilidad |
| **Síntoma** | `GET /api/productos/destacados` → `500 Server Error` |
| **Causa raíz** | El endpoint no existía. La palabra `destacados` entraba por `productos/{producto}`, llegaba a `findOrFail()` y PostgreSQL rechazaba convertirla en entero. El parámetro de ruta no estaba restringido |
| **Agravante considerado** | Un `500` en una API abierta es lo primero que encuentra quien la explora, y sugiere un fallo más profundo del que hay |
| **Resolución** | `->whereNumber('producto')` en el recurso. PR #32 |
| **Segundo hallazgo** | Restringir **solo** las rutas de lectura arreglaba el 500 pero dejaba un `405`: mientras `PUT`, `PATCH` y `DELETE` aceptaran cualquier cadena, la URI seguía casando con alguna ruta. La restricción se aplicó también a los tres verbos de escritura |
| **Verificación** | `/api/productos/destacados` → **404**; `/api/catalogo/destacados` → **200**; `/api/catalogo/999999` → 404. Comprobado contra producción |
| **Prueba de regresión** | `CatalogoPublicoTest::test_una_palabra_en_lugar_de_un_id_no_revienta` |
| **Estado** | **Cerrado** |

*Nota.* Elaboración propia. Evidencia en `SOA-08`.

**Figura 77.** Respuesta JSON del catálogo público en producción tras la
corrección.
*Fuente:* `docs/evidencias/soa/SOA-09-respuesta-json-destacados-produccion.jpg`.

**Lo que deja.** El endpoint existía en la cabeza de quien lo pidió, no en el
código. Un framework que enruta por patrón convierte esa diferencia en un
`500` si nadie restringe los parámetros; y el `405` posterior enseña que un
arreglo a medias cambia el síntoma sin cerrar el hueco.

## 31.4. Lo que tienen en común

**Tabla 104**
*Comparación de los dos incidentes*

| | INC-01 | INC-02 |
|---|---|---|
| Prioridad | P1 | P2 |
| Detectado por | Verificación manual | Verificación manual |
| Causa raíz | Closure no serializable en configuración | Parámetro de ruta sin restringir |
| Resolución | PR #36 | PR #32 |
| Prueba de regresión | `test_la_configuracion_se_puede_cachear` | `test_una_palabra_en_lugar_de_un_id_no_revienta` |
| Estado | Cerrado | Cerrado |

*Nota.* Elaboración propia.

**A los dos los encontró una persona mirando, no una alerta.** Es el hallazgo
común, y apunta a la misma mejora: el monitoreo comprobaba que el servicio
respondiera, no que respondiera *lo que debe*. De ahí sale el indicador **M6**
de la Tabla 95, que es el único de los doce que no venía del APF1.

---
---

# XXXII. CONTINUIDAD Y RECUPERACIÓN

## 32.1. Lo que comprometía el RNF08

> *El sistema deberá contar con respaldo diario automatizado, con RPO de 24
> horas y RTO de 60 minutos.*
> Verificación declarada: **simulacro de restauración con verificación por
> hash**.

Hasta la semana 18 esa verificación **nunca se había ejecutado**. El RTO de 60
minutos era una estimación, no un dato.

## 32.2. El simulacro que ya existía, y por qué no bastaba

`infra/backup/run_restore_drill.ps1` llevaba semanas escrito. Se ejecutó y
pasó: `BACKUP_DRILL_OK`, 7,8 s, los tres hashes SHA-256 idénticos (`CON-02`).

Tiene dos limitaciones que conviene decir antes de presentarlo como prueba de
nada:

1. **Opera sobre SQLite**, que es la base de contingencia del mostrador.
   Producción corre PostgreSQL. Un simulacro que nunca toca el motor real no
   demuestra que se pueda restaurar la base que importa.
2. **No puede fallar.** Copia la base, restaura la copia encima del original y
   comprueba que los tres hashes coinciden. Si la restauración no hiciera
   absolutamente nada, los hashes coincidirían igual.

> **Una verificación que no puede fallar no verifica: tranquiliza.**

## 32.3. El simulacro que sí puede fallar

Se escribió `infra/backup/run_restore_drill_postgres.ps1`, que sigue la
secuencia de un desastre real:

1. Respaldar el origen.
2. Restaurar en una base de ensayo.
3. **Romper la base de ensayo a propósito**, borrando filas.
4. Restaurar otra vez desde el respaldo.
5. Comprobar que lo borrado **volvió**.

El paso 3 es el que lo convierte en una prueba; sin él, el paso 5 se cumple
solo. El script termina con código 1 y escribe «Recuperación INCORRECTA» si las
filas no vuelven.

**Nunca toca producción.** Trabaja contra la base local, restaura en
`botica_staging` —que se recrea en cada corrida— y aborta si la base de ensayo
coincide con la de origen.

## 32.4. Un hallazgo que no se buscaba

El primer intento simulaba el daño borrando 50 productos. El motor lo rechazó:

```
ERROR: update o delete en «lotes» viola la llave foránea
«pedido_detalle_lotes_lote_id_foreign»
DETALLE: La llave (id)=(4) todavía es referida desde «pedido_detalle_lotes».
```

Es decir: **la integridad referencial impide perder un producto que ya figura
en una venta**. Es exactamente lo que debe hacer, y demuestra el **RNF06**
—operaciones de inventario con integridad— mejor que cualquier captura, porque
el control frenó a quien lo escribió y no solo a un tercero hipotético.

El daño se trasladó a una tabla hoja: el **padrón DIGEMID**, sin dependientes,
que además es información de cumplimiento cuya pérdida importa de verdad.

## 32.5. Resultado medido

**Tabla 105**
*Resultado del simulacro de restauración sobre PostgreSQL*

| Elemento | Valor |
|---|---|
| Motor | PostgreSQL 16.15 |
| Base de origen / de ensayo | `botica_san_juan` / `botica_staging` |
| Tamaño del respaldo | 0,16 MB |
| Tiempo de respaldo | 0,19 s |
| Tiempo de restauración inicial | 0,73 s |
| Daño simulado | Padrón DIGEMID: **5 → 0** filas |
| Tiempo de recuperación | 0,74 s |
| Filas recuperadas | **5 de 5** |
| Productos intactos | **884 de 884** |
| Pedidos / usuarios recuperados | 50 / 9 |
| **Recuperación correcta** | **SÍ** |
| **RTO medido** | **0,93 s** |
| **RTO comprometido** | 3 600 s (60 min) |
| **Cumple** | **SÍ**, con tres órdenes de magnitud de margen |

*Nota.* Salida literal del propio script. Evidencia completa en `CON-01`; acta
en `CON-03`.

## 32.6. Lo que ese número no dice

El RTO de 0,93 s se midió sobre una base de **0,16 MB en la misma máquina**. No
es el tiempo de una recuperación real, y presentarlo como tal sería engañoso.
Lo que demuestra es que **el procedimiento funciona y el respaldo es
restaurable**; el tiempo crecerá con el tamaño y con la latencia de red. La
cifra hay que volver a medirla si la base crece en un orden de magnitud.

Sobre el **RPO de 24 horas**: los scripts existen y el simulacro confirma que
lo que producen se puede restaurar, **pero la programación diaria no está
activa**. Mientras nadie ejecute el respaldo a diario, el RPO de 24 horas es
una intención. Es la diferencia entre poder y hacer.

## 32.7. Limitaciones de infraestructura

**Tabla 106**
*Limitaciones conocidas y plan de resolución*

| Limitación | Efecto real | Plan |
|---|---|---|
| **Disco efímero del proveedor** | `storage:link` hace que los archivos subidos se *sirvan*, no que *sobrevivan* a un despliegue. Fotos de perfil y recetas se pierden al redesplegar | Migrar a almacenamiento de objetos (S3 o R2). Documentado como trabajo futuro |
| **Instancia que se duerme** | 15 min sin tráfico, ~50 s de arranque en frío. Hace el 99 % de disponibilidad no verificable (apartado 29.5) | Plan de pago, o declarar el compromiso con su condición |
| **Respaldo sin programar** | El RPO de 24 h no está cubierto | Programar la tarea y registrar su código de salida (indicador M9) |

*Nota.* Elaboración propia. Nombrar una limitación real y proponer su solución
es parte de un plan de continuidad; omitirla sería el tipo de silencio que un
evaluador atento encuentra.

---
---

# XXXIII. RESULTADOS FINALES

Este apartado responde a una sola pregunta: **¿se hizo lo que se prometió en
la semana 5?** Los requerimientos salen de las **Tablas 7 y 8 del APF1** y no
se han reescrito para que encajen con lo construido. Si algo quedó corto, la
fila lo dice.

Criterio de estado: **Implementado** = funciona en producción y hay evidencia;
**Parcial** = lo esencial funciona y la fila dice qué falta; **Pendiente** = no
se construyó y la fila dice por qué. No hay ninguna fila cuya evidencia sea
«existe el código».

## 33.1. Requerimientos funcionales

**Tabla 107**
*Estado de los requerimientos funcionales del APF1*

| ID | Requerimiento | Estado | Evidencia |
|---|---|---|---|
| **RF01** | Autenticación cifrada con segundo factor para administrador | **Parcial** | bcrypt y Sanctum verificados (`AutorizacionRolTest`). El segundo factor está construido y se exige cuando la cuenta lo tiene activo, pero **no está activado en la cuenta de administrador de producción**: la capacidad existe, la exigencia efectiva no |
| **RF02** | Gestión de usuarios y roles | **Implementado** | `AutorizacionRolTest`, middleware `admin`, `PER-01`–`PER-13` |
| **RF03** | Registro y consulta de productos | **Implementado** | `ProductoApiTest`, `M01`–`M03` |
| **RF04** | Carga masiva desde CSV o Excel | **Implementado** | `POST /api/productos/bulk` |
| **RF05** | Búsqueda en menos de dos segundos | **Implementado**; tiempo pendiente (ver RNF03) | `E8`; el límite de 2 s no se ha medido bajo carga |
| **RF06** | Venta con lote y vencimiento, sugiriendo el más próximo | **Implementado** | FEFO: `TrazabilidadLotesTest`, `E7`, `UI-01`–`UI-05` |
| **RF07** | Inventario actualizado al confirmar la venta | **Implementado** | `ConfirmacionVentaTest` |
| **RF08** | Venta fraccionada por unidad, blíster o caja | **Implementado** | `unidad_venta` en `pedido_detalles` |
| **RF09** | Alertas de stock bajo y próximo a vencer | **Implementado** | `/admin/inventory` |
| **RF10** | Proveedores y órdenes de compra | **Implementado** | `/api/proveedores`, `/api/compras` |
| **RF11** | Importación y conciliación del catálogo DIGEMID | **Implementado** | `I01`–`I03` |
| **RF12** | Comprobantes electrónicos enviados a la SUNAT | **Implementado** | XML y CDR, `SOA-01`–`SOA-03` |
| **RF13** | Tablero de indicadores y alertas | **Implementado** | `IndicadoresTest`, `K01`–`K03` |
| **RF14** | Exportación de reportes en CSV | **Implementado** | `ReporteContableController::csv` |
| **RF15** | Bitácora de modificaciones de precios, inventario y facturación | **Implementado** | `EventoOperacion`, visor en `/admin/eventos`, `EVT-01`–`EVT-04` |

*Nota.* Elaboración propia. **13 implementados, 2 parciales, 0 pendientes.**

## 33.2. Requerimientos no funcionales

La Tabla 8 del APF1 declaró, para cada RNF, **cómo debía verificarse**. Esa
columna es la que decide el estado: un RNF cuya verificación no se ejecutó no
está verificado, por correcto que parezca el código.

**Tabla 108**
*Estado de los requerimientos no funcionales del APF1*

| ID | Verificación declarada | Estado | Evidencia y matiz |
|---|---|---|---|
| **RNF01** | Prueba automatizada de autorización | **Verificado** | `AutorizacionRolTest`; `BitacoraDeOperacionTest` añade el caso del visor |
| **RNF02** | Inspección de la base y prueba de límite | **Parcial** | bcrypt verificado. El bloqueo **no es de cuenta**: es un límite de 5 intentos por minuto por IP y DNI. Frena la fuerza bruta, pero no bloquea la cuenta como prometía el RNF |
| **RNF03** | Prueba de carga, percentil 95 | **Pendiente** | La prueba de carga **nunca se ejecutó**, y el catálogo real tiene 884 productos, no 10 000 |
| **RNF04** | Matriz de pruebas de compatibilidad | **Parcial** | Diseño adaptable verificado a 1280×720 y 1440×950. No existe la **matriz** que pedía la verificación |
| **RNF05** | Monitoreo cada cinco minutos | **Pendiente** | El script existe sin programar, y el 99 % no es verificable en esta infraestructura (apartado 29.5) |
| **RNF06** | Prueba de interrupción forzada | **Verificado por vía inesperada** | `DB::transaction` con `lockForUpdate`. **El simulacro de restauración lo demostró**: la llave foránea impidió borrar un producto con ventas (apartado 32.4) |
| **RNF07** | Ejecución del pipeline de CI | **Parcial** | 54 migraciones versionadas y reversibles. El pipeline corre pruebas, `vue-tsc` y un escáner de secretos; **no ejecuta Pint, PHPStan ni ESLint**. `CI-01`–`CI-03` |
| **RNF08** | Simulacro de restauración | **Parcial** | **El simulacro se ejecutó** y recuperó lo borrado; RTO medido 0,93 s (`CON-01`, `CON-03`). Sigue pendiente la **programación diaria** del respaldo |

*Nota.* Elaboración propia. **2 verificados, 4 parciales, 2 pendientes.**

## 33.3. Lo que esta matriz dice de verdad

**Lo funcional está construido; lo no funcional está construido pero poco
verificado.** Trece de quince RF funcionan en producción; de ocho RNF, solo
dos tienen la verificación que el propio APF1 exigía.

No es casualidad. Un requerimiento funcional se nota cuando falta —la pantalla
no está— y uno no funcional, no. Un sistema puede pasar meses sin que nadie
compruebe si restaura un respaldo, y parecer que todo va bien. Es la misma
lección que el INC-01 enseñó desde el otro lado.

**Dos promesas conviene reformularlas, no justificarlas.** El límite por minuto
del RNF02 protege mejor que un bloqueo de cuenta —que puede usarse para dejar
fuera a un usuario legítimo a propósito—, y el segundo factor del RF01 está
construido aunque no activado. Las dos se explican mejor como decisiones
razonadas que como incumplimientos, siempre que la matriz las muestre.

## 33.4. Cierre cuantitativo

**Tabla 109**
*Estado final del sistema*

| Dimensión | Valor | Evidencia |
|---|---|---|
| Pruebas de backend | **419** (1 172 aserciones, 34 clases) | `CRT-18`, `CRT-21` |
| Pruebas de frontend | **154** (19 archivos) | `CRT-20`, `CRT-23` |
| Total de pruebas automatizadas | **573** | `CRT-24` |
| Migraciones versionadas y reversibles | **54** | `docs/evidencias/figura11/` |
| Productos en el catálogo de producción | **884** | `CON-01` |
| Archivos de evidencia | **149** en 19 carpetas | `docs/evidencias/INDICE.md` |
| Entornos en producción | API y portal desplegados; documentación pública | `DEP-01`, `DEP-02`, `SOA-06`, `SOA-07` |
| Contrato público de la API | OpenAPI **3.1**, navegable con Scalar y Stoplight | `SOA-10`, `SOA-11` |

*Nota.* Elaboración propia.

**Figura 78.** Suite de backend: 419 pruebas y 1 172 aserciones sobre
PostgreSQL 16.
*Fuente:* `docs/evidencias/cortes/CRT-21-suite-419.png`.

**Figura 79.** Suite de frontend: 154 pruebas en 19 archivos.
*Fuente:* `docs/evidencias/cortes/CRT-23-suite-frontend-154.png`.

**Figura 80.** Documentación pública de la API accesible en producción.
*Fuente:* `docs/evidencias/soa/SOA-06-scalar-docs-v1-produccion.jpg`.

## 33.5. El sistema como servicio consumible

El bloque de interoperabilidad añadió algo que no estaba en el alcance
original: una **fachada pública de catálogo**, sin autenticación, consumible
desde cualquier origen, con su contrato documentado y navegable.

La parte que importa no fue publicarla, sino decidir **qué no se publica**. El
controlador interno devuelve costos, márgenes, stock por lote y proveedor; la
fachada pública devuelve **trece campos elegidos uno a uno**, y en lugar de
`stock` devuelve un booleano `disponible`. Publicar las existencias exactas de
una botica le diría a un competidor cuánto rota cada producto y cuándo se
queda sin inventario; `disponible: true` responde la única pregunta que el
consumidor legítimo necesita.

La política CORS permisiva se declaró **por lista de rutas**, nunca global. Y
se verificó por contraste desde un dominio externo real, porque la cabecera
`Access-Control-Allow-Origin` no se puede leer desde otro origen: la prueba
válida es que la ruta pública responde y la no pública queda bloqueada.
Evidencia en `SOA-08`.

---
---

# XXXIV. CONCLUSIONES Y RECOMENDACIONES

Las cuatro conclusiones que siguen **no se dedujeron de la bibliografía**:
cada una tiene detrás un hecho fechado de este proyecto, con su evidencia.
Ese es el motivo de que sean cuatro y no diez. Una conclusión que podría
haberse escrito en la semana 1, sin haber construido nada, no es una conclusión
del proyecto: es una cita.

El criterio fue estricto: **solo entra lo que cambió una decisión de
ingeniería**.

## 34.1. Un servicio que responde no es un servicio que funciona

Durante cuatro días la API devolvió `200` sirviendo código obsoleto. El sondeo
de vida respondía que *había* un proceso escuchando y *había* una base
conectada; nunca prometió decir **cuál** código estaba escuchando. El error no
fue del endpoint: fue confundir *responde* con *está bien*.

De ahí se siguen dos cosas que no son obvias. La primera: un despliegue roto
con el servicio en pie **es peor que una caída**, porque una caída se anuncia
sola. La segunda: **una nota en un comentario no previene nada** —el modo de
fallo estaba documentado en el `Dockerfile` y el proyecto volvió a caer en él—;
lo que lo previene es algo que rompe la construcción.

Y una consecuencia más incómoda: **las 357 pruebas estaban en verde con el
fallo dentro**, porque en desarrollo la configuración no se cachea nunca. Una
suite verde no dice que el sistema funcione; dice que funciona *en las
condiciones en que se la ejecuta*.

## 34.2. El motor de base de datos es un control de negocio

La integridad referencial con `RESTRICT` demostró el RNF06 **resistiéndose a
una operación que el propio equipo estaba intentando ejecutar**. Un control que
solo frena a los demás no está probado; este frenó a quien lo escribió.

La segunda cara del hallazgo es la que cambió el método. El simulacro anterior
no podía fallar, y por eso no verificaba nada. Generalizado:

> **Una verificación que no puede fallar no verifica: tranquiliza.**

Es la lección más transferible del proyecto. Se aplica igual a un simulacro que
se restaura sobre sí mismo, a una prueba unitaria que afirma lo que el código
acaba de hacer y a un `assertNull` sobre una variable que nunca se asignó. El
proyecto encontró **cuatro** casos, y los cuatro parecían correctos al leerlos.

## 34.3. Exponer un servicio obliga a decidir qué no se expone

El contrato público **no es una vista del modelo interno: es una decisión de
negocio con forma de JSON**. Trece campos elegidos uno a uno, `disponible` en
lugar de `stock`, y una política CORS declarada por lista de rutas en lugar de
global.

El desacoplamiento tuvo además un coste imprevisto —el `500` del INC-02— y una
lección sobre los arreglos a medias: restringir solo las rutas de lectura
cambió el síntoma a un `405` sin cerrar el hueco.

## 34.4. El promedio es el peor resumen de una distribución con cola

Mediana de 100 ms y percentil 96 de 700 ms, en la misma ventana y sobre el
mismo tráfico. **Un umbral sin agregación ni ventana no es una regla, es un
deseo**: «latencia ≤ 800 ms» no dice si se mide la media o un percentil ni
sobre cuánto tiempo, y así enunciado no se puede incumplir.

## 34.5. Recomendaciones

**Tabla 110**
*Recomendaciones derivadas de las conclusiones*

| N.º | Recomendación | Deriva de |
|---|---|---|
| **R1.1** | Toda comprobación posterior al despliegue verifica **endpoints concretos con su respuesta esperada**, no solo un latido | 34.1 |
| **R1.2** | Todo modo de fallo que se documente en un comentario se acompaña de una **prueba que lo reproduzca** | 34.1 |
| **R1.3** | Lo que solo existe en producción —caché de configuración, de rutas, variables del proveedor— **se ejercita en la integración continua** | 34.1 |
| **R2.1** | Las reglas que protegen la historia del negocio se declaran **en el esquema**, no solo en la aplicación | 34.2 |
| **R2.2** | Antes de dar por buena una verificación, preguntar **qué tendría que pasar para que fallara**. Si no hay respuesta, sobra | 34.2 |
| **R2.3** | El daño simulado se aplica a una **tabla hoja** con valor real | 34.2 |
| **R2.4** | Toda métrica medida se presenta **con su condición de medida** | 34.2 |
| **R3.1** | Una API pública se construye con un **transformador explícito**, campo a campo | 34.3 |
| **R3.2** | Donde el dato interno sea sensible, **publicar la respuesta y no el dato** | 34.3 |
| **R3.3** | Los parámetros de ruta numéricos se restringen **en todos los verbos** | 34.3 |
| **R3.4** | La política CORS permisiva se declara **por lista de rutas** y se verifica por contraste | 34.3 |
| **R4.1** | Todo umbral se enuncia con sus **seis piezas** | 34.4 |
| **R4.2** | Latencia en **percentil**, nunca en media | 34.4 |
| **R4.3** | **Dos niveles**: preventivo y crítico | 34.4 |
| **R4.4** | Los umbrales se **segmentan** por tipo de tráfico | 34.4 |
| **R4.5** | Añadir **presupuestos de recurso**: memoria y consultas SQL por petición | 34.4 |

*Nota.* Elaboración propia. Desarrollo completo en
`docs/CONCLUSIONES-Y-RECOMENDACIONES.md`.

## 34.6. Lo que queda abierto

Una lista de recomendaciones sin pendientes es sospechosa.

**Tabla 111**
*Trabajo pendiente al cierre del proyecto*

| Pendiente | Cierra | Esfuerzo |
|---|---|---|
| Programar el respaldo diario y registrar su código de salida | RPO de 24 h (RNF08) | Bajo: el script existe |
| Automatizar M6 como paso posterior al despliegue | Raíz del INC-01 | Bajo |
| Crear las reglas de alerta de M2 y M4 con sus dos niveles | Punto 29 | Bajo |
| Añadir Pint y ESLint al pipeline | RNF07 | Bajo |
| Prueba de carga con percentil 95 sobre la búsqueda | RNF03 | Medio |
| Migrar los archivos subidos a almacenamiento de objetos | Disco efímero | Medio |
| Matriz de compatibilidad de navegadores y resoluciones | RNF04 | Medio |

*Nota.* Elaboración propia.

Y uno que **no se cierra con trabajo**: la disponibilidad del 99 % del RNF05 es
incompatible con una instancia que se duerme a los quince minutos, y un sondeo
cada cinco minutos la mantendría despierta —el monitor dejaría de medir
disponibilidad para pasar a fabricarla—. Lo honesto es declararlo **no
verificable en la infraestructura actual** y poner su condición al lado: exige
un plan de pago.

## 34.7. La conclusión de las conclusiones

Las cuatro lecciones dicen lo mismo desde cuatro sitios distintos:

> **Una afirmación sobre un sistema vale lo que vale el procedimiento que
> podría desmentirla.**

Un `200` que no puede decir que no. Un simulacro cuyos hashes coinciden pasara
lo que pasara. Un contrato que serializa el modelo y por tanto no puede
ocultar nada. Un promedio que no puede subir aunque la cola se duplique.

Los cuatro parecían correctos. Ninguno podía fallar, y por eso ninguno
verificaba nada.

---
---

## Apéndice del maestro · Inventario de inserción

No forma parte del informe. Es la lista que consume el script de ensamblado.

### Numeración asignada

| Elemento | Rango | Comprobación |
|---|---|---|
| Tablas | **93 – 111** (19 tablas) | El APF3 cierra en la Tabla 92 |
| Figuras | **74 – 80** (7 figuras) | El APF3 cierra en la Figura 73 |
| Secciones | **XXVIII – XXXIV** | Se insertan antes de la actual `XXVI. CONCLUSIONES` |

### Figuras y su archivo de origen

| Fig. | Archivo |
|---|---|
| 74 | `docs/evidencias/eventos/EVT-01-bitacora-tres-niveles.jpg` |
| 75 | `docs/evidencias/eventos/EVT-02-filtro-por-nivel-error.jpg` |
| 76 | `docs/evidencias/eventos/EVT-03-detalle-con-contexto.jpg` |
| 77 | `docs/evidencias/soa/SOA-09-respuesta-json-destacados-produccion.jpg` |
| 78 | `docs/evidencias/cortes/CRT-21-suite-419.png` |
| 79 | `docs/evidencias/cortes/CRT-23-suite-frontend-154.png` |
| 80 | `docs/evidencias/soa/SOA-06-scalar-docs-v1-produccion.jpg` |

### Qué hace el script con las secciones existentes

| Sección del APF3 | Tratamiento |
|---|---|
| I – XXV | **Intactas.** No se tocan |
| XXVI. CONCLUSIONES | Se **absorbe** en la nueva XXXIV; el texto original se conserva como apartado inicial de 34.0 o se retira según revisión |
| XXVII. REFERENCIAS | Se mantiene y se desplaza detrás de XXXIV |
| Anexos A – D | Intactos, al final |
| ÍNDICE | Se regenera |

### Salvaguardas obligatorias del script

1. **No sobrescribe**: escribe a un archivo temporal y renombra al final.
2. **Es idempotente**: si la copia ya contiene una sección `XXVIII`, aborta.
3. **Verifica el origen**: comprueba por hash que
   `APF3 - … - ChristopherRafaile.docx` no ha cambiado.
4. **No escribe credenciales**: ninguna cifra de este maestro contiene el RUC
   del emisor ni el nombre del contribuyente del sistema anterior.
