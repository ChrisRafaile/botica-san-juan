# Conclusiones y recomendaciones técnicas

Punto 34 de la rúbrica.

Las cuatro conclusiones de este documento **no se dedujeron de la
bibliografía**: cada una tiene detrás un hecho fechado de este proyecto, con
su evidencia en `docs/evidencias/`. Esa es la razón de que sean cuatro y no
diez. Una conclusión que podría haberse escrito la semana 1, sin haber
construido nada, no es una conclusión del proyecto: es una cita.

El criterio de selección fue estricto: **solo entra lo que cambió una decisión
de ingeniería**. Las cuatro cambiaron una.

---

## C1 · Un servicio que responde no es un servicio que funciona

### Lo que ocurrió

El 4 de noviembre, tras publicar un *release*, la API seguía devolviendo `200`
en `/api/salud`. Todos los indicadores decían que el sistema estaba sano.
Estaba sirviendo el código de **cuatro días antes**.

La causa: `config/sentry.php` definía `before_send` como un *closure*. El
arranque de producción ejecuta `php artisan config:cache`, que serializa la
configuración con `var_export`, y un closure no se puede exportar:

```
Your configuration files could not be serialized because the value
at "sentry.before_send" is non-serializable.
Call to undefined method Closure::__set_state()
```

El contenedor moría en 38 segundos. Render —correctamente— **mantiene viva la
versión anterior cuando un despliegue falla**: evita una caída, y al hacerlo
convierte el fallo en invisible.

Y no era la primera vez. El mismo patrón había ocurrido en octubre con el
intérprete de PHP 8.3, y **estaba documentado en un comentario del
`Dockerfile`**. Volvió a pasar igual.

### Qué se concluye

**Un despliegue roto con el servicio en pie es peor que una caída**, porque
una caída se anuncia sola. Y de ahí se siguen dos cosas que no son obvias:

1. **Un sondeo de vida no verifica una versión.** `/api/salud` responde que
   *hay* un proceso escuchando y *hay* una base conectada. Nunca prometió
   decir *cuál* código está escuchando, y nadie debería haber esperado que lo
   hiciera. El error no fue del endpoint; fue de confundir *«responde»* con
   *«está bien»*.
2. **Una nota en un comentario no previene nada.** El modo de fallo estaba
   escrito y el proyecto volvió a caer en él. Lo que lo previene es algo que
   rompe la construcción.

Hay además una consecuencia más incómoda: **las 357 pruebas estaban en verde
con el fallo dentro**. En desarrollo la configuración no se cachea nunca, así
que ninguna prueba ejercía la ruta que fallaba. Una suite verde no dice que el
sistema funcione; dice que funciona *en las condiciones en que se la ejecuta*.

### Recomendación

| | |
|---|---|
| **R1.1** | Toda comprobación posterior al despliegue verifica **endpoints concretos con su respuesta esperada** —`/api/catalogo` → 200, `/docs/v1` → 200, `/api/productos/destacados` → 404—, no solo un latido. Es el indicador **M6** de `MONITOREO-Y-UMBRALES.md`, y es el único de los doce que no venía del APF1: lo pidió este incidente |
| **R1.2** | Todo modo de fallo que se documente en un comentario se acompaña de una **prueba que lo reproduzca**. El cierre de INC-01 no fue el arreglo: fue `DepuracionSentryTest::test_la_configuracion_se_puede_cachear`, que ejecuta `config:cache` de verdad. Cualquier aserción sobre el *contenido* del arreglo habría pasado igual con el closure dentro |
| **R1.3** | Lo que solo existe en producción —caché de configuración, caché de rutas, variables del proveedor— **se ejercita en la integración continua**, o no está verificado |

**Evidencia:** `docs/GESTION-DE-INCIDENTES.md` (INC-01), `SOA-06`–`SOA-11`.

---

## C2 · El motor de base de datos es un control de negocio, no un detalle de infraestructura

### Lo que ocurrió

El simulacro de restauración sobre PostgreSQL 16 necesitaba **dañar la base a
propósito** para poder comprobar que la recuperación servía de algo. El primer
intento borraba 50 productos. El motor lo rechazó:

```
ERROR: update o delete en «lotes» viola la llave foránea
«pedido_detalle_lotes_lote_id_foreign»
DETALLE: La llave (id)=(4) todavía es referida desde «pedido_detalle_lotes».
```

No era un obstáculo del simulacro. Era **el esquema haciendo exactamente lo
que se diseñó que hiciera**: impedir que desaparezca un producto que ya figura
en una venta.

### Qué se concluye

El hallazgo tiene dos caras, y las dos importan.

**La primera:** la integridad referencial con `RESTRICT` demostró el **RNF06**
—operaciones de inventario con integridad— mejor que cualquier captura de
pantalla, y lo hizo *resistiéndose a una operación que el propio equipo estaba
intentando ejecutar*. Un control que solo frena a los demás no está probado;
este frenó a quien lo escribió.

**La segunda, y es la que cambió el método:** el simulacro que ya existía
desde hacía semanas (`run_restore_drill.ps1`) **no podía fallar**. Copiaba la
base, restauraba la copia encima del original y comprobaba que los tres hashes
coincidían. Si la restauración no hubiera hecho absolutamente nada, los hashes
habrían coincidido igual.

> **Una verificación que no puede fallar no verifica: tranquiliza.**

Es, probablemente, la lección más transferible del proyecto. Se aplica igual a
una prueba unitaria que afirma lo que el código acaba de hacer, a un
`assertNull` sobre una variable que nunca se asignó, y a un simulacro de
recuperación que se restaura sobre sí mismo.

El simulacro reescrito sigue la secuencia de un desastre real —respaldar,
restaurar, **romper**, restaurar otra vez, comprobar que lo borrado volvió— y
termina con código 1 si las filas no vuelven. El paso de romper es el que lo
convierte en una prueba; sin él, el último paso se cumple solo.

### Recomendación

| | |
|---|---|
| **R2.1** | Las reglas que protegen la historia del negocio se declaran **en el esquema**, no solo en la capa de aplicación. Una validación en PHP se puede saltar desde una consola; una llave foránea, no |
| **R2.2** | Antes de dar por buena cualquier verificación, preguntar: **¿qué tendría que pasar para que esto fallara?** Si no hay respuesta, la verificación sobra. Este proyecto encontró tres casos —el simulacro, dos pruebas vacuas sobre `limpiarUrl` y una aserción sobre `text()` que no podía fallar— y los tres parecían correctos al leerlos |
| **R2.3** | El daño simulado se aplica a una **tabla hoja** con valor real. Se eligió el padrón DIGEMID: sin dependientes, y es información de cumplimiento cuya pérdida importa de verdad |
| **R2.4** | El RTO medido —**0,93 s** frente a los 3 600 s comprometidos— se presenta **con su condición**: se midió sobre 0,16 MB en la misma máquina. Demuestra que el procedimiento funciona y que el respaldo es restaurable; no es el tiempo de una recuperación real, y hay que volver a medirlo si la base crece un orden de magnitud |

**Evidencia:** `docs/evidencias/continuidad/` (`CON-01`, `CON-03`),
`infra/backup/run_restore_drill_postgres.ps1`.

---

## C3 · Exponer un servicio obliga a decidir qué no se expone

### Lo que ocurrió

Para el curso de arquitectura orientada a servicios se abrió una fachada
pública de catálogo —sin autenticación, consumible desde cualquier origen—
documentada con **OpenAPI 3.1** y navegable en producción con Scalar y
Stoplight.

La parte interesante no fue publicarla. Fue decidir **qué campos salen**.

El controlador interno de productos devuelve el objeto completo: costos,
márgenes, stock por lote, proveedor. La fachada pública devuelve exactamente
**trece campos**, elegidos uno a uno, y en lugar de `stock` devuelve un
booleano `disponible`.

### Qué se concluye

**El contrato público no es una vista del modelo interno: es una decisión de
negocio con forma de JSON.** Publicar las existencias exactas de una botica
le dice a un competidor cuánto rota cada producto y cuándo se queda sin
inventario. `disponible: true` responde la única pregunta que el consumidor
legítimo necesita —¿puedo pedirlo?— sin responder la que no le corresponde.

Lo mismo con CORS. `Access-Control-Allow-Origin: *` se aplicó **solo a las
rutas públicas de lectura**, nunca al resto. Y es incompatible con
credenciales por diseño del navegador, lo cual aquí es una ventaja: la
configuración permisiva no puede extenderse accidentalmente a una ruta
autenticada.

Hay un detalle de método que conviene anotar, porque costó tiempo: **la
cabecera `Access-Control-Allow-Origin` no se puede leer desde otro origen**
—no está en la lista segura—, así que la comprobación de CORS no consiste en
leer la cabecera. La prueba válida es el **contraste**: desde un dominio
ajeno, la ruta pública responde y la no pública queda bloqueada.

El desacoplamiento también tuvo un coste que no se previó. Al separar las
rutas apareció un `500` en `/api/productos/destacados`: la palabra entraba por
`productos/{producto}` y PostgreSQL no podía convertirla en entero. Y el
arreglo a medias —restringir solo las rutas de lectura— cambió el síntoma a un
`405` sin cerrar el hueco, porque mientras `PUT`, `PATCH` y `DELETE`
aceptaran cualquier cadena, la URI seguía casando con *alguna* ruta.

### Recomendación

| | |
|---|---|
| **R3.1** | Una API pública se construye con un **transformador explícito**, campo a campo, no serializando el modelo. El método `comoContrato()` lista los trece campos; añadir una columna a la tabla no la publica por accidente |
| **R3.2** | Donde el dato interno sea sensible, **publicar la respuesta y no el dato**: `disponible` en lugar de `stock` |
| **R3.3** | Los parámetros de ruta numéricos se restringen con `whereNumber` **en todos los verbos**, no solo en los de lectura. Un arreglo parcial cambia el código de error y deja el hueco |
| **R3.4** | La política CORS permisiva se declara por **lista de rutas**, nunca global, y se verifica por contraste desde un dominio externo real |

**Evidencia:** `docs/API-PUBLICA.md`, `docs/evidencias/soa/` (`SOA-06`–`SOA-11`),
`CatalogoPublicoTest` (20 pruebas), `docs/GESTION-DE-INCIDENTES.md` (INC-02).

---

## C4 · El promedio es el peor resumen de una distribución con cola

### Lo que ocurrió

Al modelar la matriz de umbrales se tomó como referencia el tablero público de
Blackfire. En la misma ventana de treinta minutos, sobre el mismo tráfico, el
tablero mostraba:

| Medida | Valor |
|---|---|
| Mediana del tiempo de respuesta | **100 ms** |
| Percentil 96 | **700 ms** |

Siete veces de diferencia. Un promedio saludable puede convivir con que una de
cada veinticinco peticiones tarde siete veces más —y esa peticion es un
cliente esperando en el mostrador.

El mismo tablero reparte las peticiones en cubos de duración y marca cómo ha
variado cada cubo contra la semana anterior. Lo que se vigila no es que el
número suba: es **que la cola engorde**.

### Qué se concluye

**Un umbral sin agregación ni ventana no es una regla, es un deseo.** El APF1
declaró «latencia ≤ 800 ms» y «error de servidor ≤ 0,5 %». Ninguno de los dos
decía si se medía la media o un percentil, ni sobre cuánto tiempo. Así
enunciados no se pueden evaluar y, por tanto, tampoco incumplir.

La regla de la referencia, en cambio, se lee entera en una frase: *notificar
por correo cuando el **promedio** del tiempo de respuesta sea **≥ 450 ms**
durante los **últimos 5 minutos***. Seis piezas: canal, agregación, métrica,
comparador, valor y ventana. Los doce indicadores de la matriz las llevan.

Dos cosas se adoptaron y una se rechazó deliberadamente. Se adoptaron el
percentil y la segmentación —el punto de venta y el panel gerencial no tienen
por qué responder igual, y aplicarles el mismo número garantiza que uno de los
dos esté mal calibrado—. Se rechazó el **umbral único** de Blackfire: aquí no
hay un equipo de guardia, hay una persona que además atiende el mostrador, y
un aviso que solo salta cuando el servicio ya está mal llega tarde. De ahí los
dos niveles, preventivo y crítico.

También obligó a corregir un compromiso mal planteado del APF1: *«detección de
una incidencia en 5 minutos»* describía el **intervalo del sondeo**, no el
tiempo de detección. El INC-01 demuestra que un latido cada cinco minutos
puede no detectar nada nunca.

### Recomendación

| | |
|---|---|
| **R4.1** | Todo umbral se enuncia con sus seis piezas. «p95 > 1 500 ms sostenido 15 min» es una regla; «la API debe ser rápida» no |
| **R4.2** | Latencia en **percentil**, nunca en media. La media oculta justo el caso que duele |
| **R4.3** | Dos niveles: **preventivo** (revisar esta semana) y **crítico** (actuar ahora). Un solo umbral obliga a elegir entre avisar tarde o avisar siempre |
| **R4.4** | Los umbrales se **segmentan**: mostrador a la mitad del valor base, panel de administración al doble, procesos de fondo sin umbral de latencia |
| **R4.5** | Añadir a lo que ya se vigilaba los **presupuestos de recurso** que la referencia trata como métricas de primera clase: memoria del proceso (M7) y consultas SQL por petición (M8). El N+1 llega disfrazado de latencia, pero la causa está en el recuento de consultas |

**Evidencia:** `docs/MONITOREO-Y-UMBRALES.md`, Tablas 33 y 34 del APF1.

---

## Lo que queda abierto

Una lista de recomendaciones sin pendientes es sospechosa. Estos son, en orden
de esfuerzo:

| Pendiente | Cierra | Esfuerzo |
|---|---|---|
| Programar el respaldo diario y guardar su código de salida | RPO de 24 h (RNF08) | Bajo: el script existe |
| Convertir M6 en un paso automático posterior al despliegue | Raíz del INC-01 | Bajo |
| Crear en Sentry las reglas de M2 y M4 con sus dos niveles | Puntos 29 y 33 | Bajo |
| Añadir Pint y ESLint al pipeline | RNF07 | Bajo |
| Prueba de carga con percentil 95 sobre la búsqueda | RNF03 | Medio |
| Mover los archivos subidos a almacenamiento de objetos | Disco efímero de Render | Medio |

Y uno que **no se cierra con trabajo**: la disponibilidad del 99 % del RNF05
es incompatible con una instancia que se duerme a los quince minutos. Peor
aún, un sondeo cada cinco minutos la mantendría despierta, con lo que el
monitor dejaría de medir disponibilidad para pasar a fabricarla. Lo honesto es
declarar el compromiso como **no verificable en la infraestructura actual** y
poner su condición al lado: exige un plan de pago.

---

## La conclusión de las conclusiones

Las cuatro lecciones dicen lo mismo desde cuatro sitios distintos:

> **Una afirmación sobre un sistema vale lo que vale el procedimiento que
> podría desmentirla.**

Un `200` que no puede decir que no. Un simulacro cuyos hashes coinciden pasara
lo que pasara. Un contrato público que serializa el modelo y por tanto no
puede ocultar nada. Un promedio que no puede subir aunque la cola se duplique.

Los cuatro parecían correctos. Ninguno podía fallar, y por eso ninguno
verificaba nada.
