# Monitoreo y umbrales de operación

Punto 29 de la rúbrica: *«monitoreo del sistema: métricas, umbrales y
acciones»*.

Los compromisos no se inventan aquí. Salen de las **Tablas 33 y 34 del APF1**
—el acuerdo de nivel de servicio y los indicadores clave—, y este documento
hace tres cosas con ellos: los convierte en reglas que una herramienta puede
evaluar, les añade un nivel preventivo que el APF1 no tenía, y dice con
precisión **cuáles se están midiendo hoy y cuáles no**.

---

## 1 · La referencia: cómo modela esto una herramienta de verdad

Se analizó el tablero de demostración de **Blackfire** (entorno público
«BigFoot PHP») para no diseñar la tabla a ciegas. Cuatro cosas de allí se
adoptan y una se rechaza.

**Lo que se adopta.**

1. **La regla de alerta es una frase ejecutable, no una intención.** La regla
   publicada en la demo dice, literalmente:

   > *This alert rule sends notification to Email when the average of Response
   > Time >= 450 ms during the last 5 minutes.*

   Es decir: `canal` + `agregación` + `métrica` + `comparador` + `valor` +
   `ventana`. Un umbral sin ventana ni agregación no es una regla, es un deseo:
   «la latencia no debe pasar de 800 ms» no dice si se mide la media o el
   percentil, ni sobre cuánto tiempo, y por tanto no se puede evaluar ni
   incumplir. **Toda fila de la tabla de abajo lleva los seis elementos.**

2. **El percentil manda sobre el promedio.** El tablero muestra a la vez
   *96th percentile 700 ms* y *Median 100 ms* sobre la misma ventana. Son
   siete veces de diferencia: un promedio sano puede esconder que una de cada
   veinticinco peticiones tarda siete veces más. Por eso los umbrales de
   latencia se fijan en percentil, no en media.

3. **La distribución vale más que el número único.** La demo reparte las
   peticiones en cubos (50 ms, 100 ms, 200 ms, 0,4 s, 0,8 s, 1,6 s, más de
   1,6 s) y marca la variación de cada cubo contra la semana anterior. Lo que
   se vigila no es que el número suba, sino que **la cola engorde**.

4. **La segmentación.** El tablero filtra por host HTTP, método, servidor y
   runtime, y el informe de salud ordena por «transacciones más impactantes» e
   «indicadores que se degradan gradualmente en seis semanas». Un umbral global
   para todo el sistema no sirve: el catálogo público y la sincronización
   offline no tienen por qué responder igual.

**Lo que no se adopta.** Blackfire usa **un solo umbral por regla**. Aquí hacen
falta dos —preventivo y crítico—, porque no hay un equipo de guardia: hay una
persona. Un aviso que solo salta cuando el servicio ya está mal llega tarde
para alguien que además atiende el mostrador. El nivel preventivo existe para
que la acción sea *revisar esta semana* y no *levantarse de madrugada*.

---

## 2 · La matriz

Las dos últimas columnas son las que hacen la tabla útil: **qué se hace** al
cruzar el umbral, y **de dónde sale el compromiso**.

| # | Indicador | Cómo se mide | Preventivo (atención) | Crítico (alerta) | Acción operativa | Origen en el APF1 |
|---|---|---|---|---|---|---|
| **M1** | Disponibilidad del servicio | Sondeo de `GET /api/salud` y `GET /api/health/db` cada 5 min desde `infra/monitoring/healthcheck_monitor.ps1`; disponible = `200` con `ok:true`. Mensual, sobre la franja 08:00–22:00 | < 99,5 % en 7 días | **2 sondeos seguidos fallidos** (10 min) o < 99 % mensual | Crítico: revisar el despliegue vivo en Render y la conexión a Neon, y abrir incidente P1 según `GESTION-DE-INCIDENTES.md`. Preventivo: revisar la bitácora de la semana | Tabla 33 (99 % mensual, 08:00–22:00) y Tabla 34 (meta ≥ 99 %, alerta < 98,5 %) |
| **M2** | Latencia de la API, p95 | Percentil 95 del tiempo de respuesta de las rutas de lectura, ventana de 15 min, medido con las trazas de Sentry Performance | p95 > 800 ms sostenido 15 min | p95 > 1 500 ms sostenido 15 min | Crítico: identificar la ruta culpable por la traza y, si es consulta, revisar índices. Preventivo: anotar la ruta y perfilarla en la siguiente iteración | Tabla 34 (meta ≤ 800 ms, alerta > 1 500 ms), diaria |
| **M3** | Latencia de la búsqueda de productos | p95 de `GET /api/productos?buscar=` y `GET /api/catalogo?buscar=`, bajo prueba de carga | p95 > 1 000 ms | p95 > 2 000 ms | Supera el compromiso contractual con el propietario: se detiene la promesa de «búsqueda en menos de dos segundos» hasta corregir | Tabla 33 (máximo 2 s en p95) y RNF03 |
| **M4** | Tasa de error del servidor (5xx) | `5xx ÷ total de respuestas`, ventana de 15 min, de las trazas de Sentry y de los registros de Render | > 0,5 % en 15 min | > 1 % en 15 min, o **cualquier 5xx en una ruta pública del catálogo** | Crítico: incidente P2 como mínimo; un 5xx en la API abierta es lo primero que ve quien la consume (fue exactamente el INC-02) | Tabla 34 (meta ≤ 0,5 %, alerta > 1 %), diaria |
| **M5** | Errores del cliente por versión | Eventos de Sentry en el frontend agrupados por *release*, primeras 24 h tras cada despliegue | > 5 eventos nuevos no vistos antes | Un error que afecta a > 1 % de las sesiones | Crítico: revertir el despliegue en Vercel, que es inmediato, y diagnosticar después | Derivado de Tabla 34 «pruebas en verde por despliegue» |
| **M6** | Coherencia del despliegue | Tras cada publicación, comprobar **endpoints concretos**, no solo `/api/salud`: `/api/catalogo` → 200, `/docs/v1` → 200, `/api/productos/destacados` → 404 | Cualquier respuesta distinta de la esperada | Idem, y el despliegue figura `update_failed` en el proveedor | Crítico: el servicio está sirviendo código viejo. Volver a desplegar y no dar por bueno el release | **No estaba en el APF1.** Nace del INC-01: `/api/salud` daba 200 con código de cuatro días antes |
| **M7** | Memoria del proceso | Métrica de la instancia de Render, media de 15 min | > 70 % del límite del plan | > 90 % sostenido 15 min, o reinicio por falta de memoria | Crítico: buscar la petición que la dispara antes de subir de plan; en la capa gratuita subir no es opción | Adoptado de la referencia (la demo vigila memoria por transacción); el APF1 no lo declaraba |
| **M8** | Consultas SQL por petición | Presupuesto por ruta: consultas emitidas en una petición del catálogo y del tablero | > 15 consultas en una ruta de listado | > 40 consultas, o crecimiento proporcional al número de filas (N+1) | Crítico: es un N+1; cargar la relación por adelantado. El síntoma llega como latencia, la causa está aquí | Adoptado de la referencia (la demo ordena las llamadas SQL por impacto); el APF1 no lo declaraba |
| **M9** | Éxito del respaldo diario | Código de salida del respaldo programado; mensual, el simulacro de restauración | Un fallo en 7 días | Dos fallos seguidos, o el simulacro no recupera las filas | Crítico: el RPO de 24 h deja de estar cubierto. Respaldo manual inmediato | Tabla 33 (RPO 24 h, RTO 60 min) y Tabla 34 (éxito 100 %) |
| **M10** | Comprobantes aceptados por la SUNAT | Aceptados ÷ enviados, diario, sobre la respuesta registrada de cada CDR | < 99 % | < 97 %, o un rechazo del mismo código tres veces | Crítico: se detiene el envío automático y se revisa el armado del XML antes de seguir acumulando rechazos | Tabla 34 (meta ≥ 99 %, alerta < 97 %) |
| **M11** | Pruebas automatizadas en verde | Resultado del pipeline por cada subida | Cualquier prueba en rojo | Rojo en `main` | No se despliega. El pipeline ya lo impide | Tabla 34 (100 %, por despliegue) |
| **M12** | Eventos de nivel `error` en la bitácora | Recuento de `eventos_operacion` con `nivel = error`, visible en `/admin/eventos`, ventana de 24 h | > 10 en 24 h | > 50 en 24 h, o 5 del mismo código en 1 h | Crítico: abrir incidente. El código repetido indica un fallo sistemático, no un caso aislado | **No estaba en el APF1.** Lo habilita el visor construido para el punto 30 |

### Segmentación

Los umbrales M2, M4, M7 y M8 **no se aplican igual a todo**. Siguiendo la
segmentación de la referencia:

| Segmento | Qué incluye | Ajuste sobre el umbral base |
|---|---|---|
| **Mostrador** | Punto de venta, confirmación de venta, búsqueda del POS | **La mitad.** Si el POS tarda, hay un cliente esperando en el local |
| **Catálogo público** | `GET /api/catalogo*`, documentación | Base. Es lo que consumen terceros |
| **Panel de administración** | Tablero, inventario, reportes | **El doble.** Un reporte gerencial puede tardar; nadie está en caja |
| **Procesos de fondo** | Envío a la SUNAT, sincronización offline, importación DIGEMID | Sin umbral de latencia; se vigilan por tasa de error y por cola pendiente |

---

## 3 · Qué se mide hoy, de verdad

Esta sección existe porque una tabla de umbrales sin ella es una declaración de
intenciones con formato de tabla.

| Indicador | Estado real |
|---|---|
| M11 pruebas en verde | **Activo.** El pipeline corre en cada subida y bloquea |
| M12 eventos de error | **Activo y consultable** en `/admin/eventos`, con filtro por nivel. No hay alerta automática: hay que mirar |
| M5 errores del cliente | **Activo cuando hay claves.** Sentry recoge y agrupa por release; sin `VITE_SENTRY_DSN` no se carga nada (`docs/OBSERVABILIDAD.md`) |
| M9 respaldo | **Verificado el procedimiento, no la periodicidad.** El simulacro se ejecutó y recuperó las filas (`CON-01`, `CON-03`). La programación diaria **no está activa** |
| M6 coherencia del despliegue | **Manual.** Se hace, y encontró el INC-01. No está automatizado |
| M1 disponibilidad | **El script existe y no está programado.** Y hay un impedimento de fondo, abajo |
| M2, M4 latencia y error de servidor | **Instrumentado, sin umbral configurado.** Las trazas llegan a Sentry; nadie ha creado la regla que avise |
| M3 búsqueda bajo carga | **No medido.** La prueba de carga con p95 nunca se ejecutó (RNF03) |
| M7, M8 memoria y consultas SQL | **No medidos.** Son los dos indicadores que la referencia añade y que el proyecto no tiene instrumentados |
| M10 SUNAT | **Medible pero sin volumen real.** El cliente conmutable está, la homologación con el proveedor no se cerró |

**Cuatro activos, tres parciales, cinco sin medir.** Decirlo así es más útil
que una tabla en la que todo tiene umbral y nada tiene dato.

### El impedimento del 99 %

El M1 no se cierra con trabajo, se cierra con una decisión. La capa gratuita de
Render **duerme la instancia tras quince minutos sin tráfico**, con unos 50
segundos de arranque en frío. Con esa infraestructura:

- La disponibilidad del 99 % **no es alcanzable** en el sentido que el APF1
  quería decir.
- Un sondeo cada cinco minutos **mantendría el servicio despierto**, de modo
  que el monitor dejaría de medir disponibilidad para pasar a fabricarla.

Lo honesto es declarar el compromiso como **no verificable en la
infraestructura actual** y poner el número al lado de su condición: el 99 %
exige un plan de pago. Un indicador que solo da verde porque quien lo mide lo
sostiene no es un indicador.

---

## 4 · Reconciliación con el APF1

| Compromiso del APF1 | Dónde quedó | Cambio y por qué |
|---|---|---|
| Disponibilidad ≥ 99 %, alerta < 98,5 % | M1 | Se añade un disparador **inmediato** (dos sondeos fallidos) además del mensual. Un corte de dos horas se nota en el mostrador el mismo día, no a fin de mes |
| Latencia p95 ≤ 800 ms, alerta > 1 500 ms | M2 | Se conservan ambos valores: la meta pasa a ser el umbral preventivo y la alerta, el crítico. El APF1 ya traía los dos niveles; solo les faltaba ventana y agregación |
| Error de servidor ≤ 0,5 %, alerta > 1 % | M4 | Igual, más una condición extra: **cualquier** 5xx en el catálogo público es crítico, por la lección del INC-02 |
| Búsqueda ≤ 2 s en p95 | M3 | Sin cambio. Es el único umbral que todavía no tiene medición |
| Detección de incidencia ≤ 5 min | M1, M6 | **Se corrige.** Cinco minutos era el intervalo del sondeo, no el tiempo de detección: el INC-01 demuestra que un sondeo de vida puede no detectar nada nunca. La detección real exige M6 |
| Contención 15 min / restauración 60 min | `GESTION-DE-INCIDENTES.md` | INC-01 se atendió en ~12 min. RTO medido en el simulacro: 0,93 s sobre 0,16 MB (`CON-03`) |
| RPO 24 h / RTO 60 min | M9 | El procedimiento está verificado; la programación diaria no. Es la diferencia entre poder y hacer |
| Comprobantes SUNAT ≥ 99 % | M10 | Sin cambio |
| Pruebas en verde 100 % | M11 | Sin cambio. Cumplido: **379 de backend y 116 de frontend** |
| — | M6, M7, M8, M12 | **Cuatro indicadores nuevos.** M6 y M12 los pide la experiencia del proyecto; M7 y M8, la referencia de Blackfire |

Dos indicadores del APF1 quedan fuera de esta tabla a propósito —**reducción
del tiempo de atención** y **exactitud del inventario**—: son indicadores de
negocio que se miden contra la operación de la botica, no métricas de
monitoreo del sistema. Van en el apartado de resultados del informe.

---

## 5 · Lo que falta para que esto deje de ser una tabla

En orden de esfuerzo creciente:

| Qué | Cierra | Esfuerzo |
|---|---|---|
| Programar el respaldo diario y guardar su código de salida | M9, RPO | Bajo: el script existe |
| Convertir M6 en un paso del pipeline posterior al despliegue | M6, raíz del INC-01 | Bajo |
| Crear en Sentry las reglas de M2 y M4 con sus dos niveles | M2, M4 | Bajo: la instrumentación ya está |
| Prueba de carga con p95 sobre la búsqueda | M3, RNF03 | Medio |
| Contador de consultas por petición en desarrollo | M8 | Medio |
| Resolver el 99 % de disponibilidad | M1, RNF05 | Decisión, no trabajo |

---

## Referencias

- Tablas 33 y 34 del APF1 (acuerdo de nivel de servicio e indicadores clave).
- `docs/GESTION-DE-INCIDENTES.md` — INC-01 e INC-02, de donde salen M6 y la
  condición extra de M4.
- `docs/MATRIZ-REQUERIMIENTOS.md` — estado de RNF03, RNF05 y RNF08.
- `docs/OBSERVABILIDAD.md` — qué recoge Sentry y PostHog, y qué se anonimiza
  antes de salir del navegador.
- `docs/evidencias/continuidad/` — simulacro de restauración y RTO medido.
- Tablero de demostración de Blackfire, entorno público «BigFoot PHP»
  (`demo.blackfire.io`), consultado el 4 de noviembre de 2026.
