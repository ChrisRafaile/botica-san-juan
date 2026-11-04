# Plan del Proyecto Final · semana 18

Qué pide la rúbrica, qué hay ya en el repositorio y qué falta construir.

Fuente: `Trabajo_Final.pdf`, sección **V. Operación del Sistema** (puntos 28–34)
y **VI. Evidencias mínimas**. Nada de lo que sigue sale de suponer qué querrá
la profesora: sale de ese PDF.

---

## La idea que gobierna esta entrega

Las tres entregas anteriores demostraban que el sistema **existe**: que está
diseñado, construido, desplegado y probado. Esta demuestra algo distinto —que
**se puede operar como un servicio de TI**—, y eso cambia qué cuenta como
evidencia.

Un panel bonito no prueba que haya monitoreo. Lo prueba un umbral declarado de
antemano, un evento registrado cuando se cruza, y alguien que lo atendió. La
rúbrica lo dice en su frase final: *sistema implementado + desplegado +
probado + **gestionado como servicio de TI***.

Conviene tenerlo presente porque el reflejo natural es volver a enseñar
funcionalidad, y la funcionalidad ya está calificada.

---

## Punto 28 · Corrección del APF3

La Tabla 64 del APF3 dejó **cuatro observaciones abiertas**. Dos las cerró el
trabajo de las semanas 17-18, sin que nadie se lo propusiera:

| N.º | Observación | Estado | Evidencia |
|---|---|---|---|
| **O-B1** | Las Figuras 43 y C.3 muestran la suite con cifras ya superadas | **Abierta** | Hoy son **363** pruebas; la figura dice 198. Son capturas: hay que volver a tomarlas |
| **O-B2** | La Figura 11 rotula «26 migraciones»; la fila decía 49 | **CERRADA** | Figura regenerada con **54**, contadas por script. `docs/evidencias/figura11/` |
| **O-B3** | Las cifras del portal no coinciden entre Inicio, Sobre Nosotros y el pie | **Abierta** | Es una decisión de negocio: hay que elegir cuál es la correcta |
| **O-B4** | Los 884 productos apuntan a una imagen que ya no existe; todas dan 404 | **CERRADA** | Verificado en producción: `200`, `image/png`, 2 255 B, **512×512**, decodifica |

**O-B2 cerró mejor de lo que pedía.** No se retocó el PNG: se escribió
`docs/scripts/generar-figura11.py`, que **cuenta** los archivos de migración en
lugar de llevar el número escrito. La cifra no puede volver a quedarse vieja
sin que alguien ejecute el script y lo vea.

Para el informe hacen falta **dos tablas**: las observaciones cerradas con su
evidencia, y las que siguen abiertas con el motivo. Reconocer una observación
abierta puntúa más que esconderla; ocultar O-B1 y O-B3 sería además fácil de
detectar.

---

## Punto 29 · Monitoreo: indicadores, umbrales y evidencias

**Lo que hay.** Sentry y PostHog con política de enmascarado
(`docs/OBSERVABILIDAD.md`), panel de indicadores gerenciales, `/api/salud` y
`/api/health/db`, `infra/monitoring/healthcheck_monitor.ps1`,
`run_daily_health_smoke.ps1` y las métricas del proveedor.

**Lo que falta: los umbrales.** La rúbrica pide «indicadores, **umbrales** y
evidencias», y hoy hay indicadores y evidencias pero ningún número que separe
«normal» de «hay que mirar esto». Sin umbral, un indicador es un adorno: nadie
sabe cuándo actuar.

Hace falta una tabla corta y honesta: indicador, cómo se mide, umbral, qué se
hace al cruzarlo. Y conviene reconciliarla con los SLA/KPI que el APF1 ya
declaró, en lugar de inventar unos nuevos que no coincidan con los de hace
trece semanas.

---

## Punto 30 · Gestión de eventos: info, warning y error

**Es el hueco más claro, y el de mejor relación esfuerzo/resultado.**

El modelo `EventoOperacion` ya tiene **exactamente los tres niveles que la
rúbrica nombra** —`info`, `warning`, `error`—, diez puntos del código que
registran eventos, su tabla `eventos_operacion` y hasta un espejo local para
cuando no hay conexión (`src/offline/bitacora.ts`).

**Pero no hay forma de verlos.** Ni endpoint, ni pantalla. La única evidencia
posible hoy es una consulta SQL contra la base, que como demostración de
«gestión de eventos» es pobre: enseña que el dato existe, no que se gestione.

Falta un visor en el panel —filtro por nivel, por código y por fecha— y
asegurar que haya ejemplos reales de los tres niveles. Es poco trabajo sobre
algo que ya está construido, y convierte un punto flojo en uno fuerte.

---

## Punto 31 · Gestión de incidentes: registro, prioridad, atención y cierre

**Lo que hay.** `docs/SOLICITUDES-DE-SERVICIO.md` (272 líneas), el tablero de
GitHub Projects con las seis solicitudes SOL-001 a SOL-006 y sus capturas, y
`infra/monitoring/run_incident_simulation.ps1`.

**Lo que falta, y ya ocurrió.** El procedimiento está escrito, pero la rúbrica
pide el recorrido completo: registro, prioridad, atención y cierre. Y este
ciclo produjo **dos incidentes reales** que lo recorren entero:

1. **El despliegue que falló en silencio.** `config:cache` no pudo serializar
   un closure en `config/sentry.php`; el contenedor murió al arrancar, Render
   mantuvo viva la versión anterior y el servicio siguió respondiendo `200`
   con código de cuatro días antes. *Desde fuera no se veía nada.* Detectado
   al verificar los endpoints contra producción, no por una alerta — lo cual
   es en sí un hallazgo sobre el monitoreo.

2. **El `500` en una ruta pública.** `/api/productos/destacados` reventaba
   porque la palabra caía en `productos/{producto}` y PostgreSQL no podía
   convertirla en entero.

Los dos tienen fecha, diagnóstico, corrección, verificación y prueba de
regresión que impide la recaída. Un incidente real contado con honestidad vale
más que un simulacro, y evita el problema de fondo del simulacro: que quien lo
escribe ya sabe el final.

---

## Punto 32 · Continuidad: backups y recuperación

**Lo que hay.** `infra/backup/` con respaldo y restauración para PostgreSQL y
SQLite, y —lo más valioso— **`run_restore_drill.ps1`**, un simulacro de
restauración. `docs/CONTINUIDAD_OPERATIVA.md` existe, con 62 líneas.

**Lo que falta.** Ejecutar el simulacro y guardar su salida: un script de
restauración que nadie ha corrido es una hipótesis, no un plan. Y el documento
es fino para lo que pide la rúbrica —falta el plan de recuperación ante
fallas, con tiempos y responsable—.

Aquí es donde pertenece, dicho sin rodeos, **la limitación del disco efímero
de Render**: `storage:link` hace que los archivos subidos se *sirvan*, no que
*sobrevivan* a un despliegue. Nombrar una limitación real y proponer su
solución (almacenamiento de objetos) es exactamente lo que se espera de un
plan de continuidad; omitirla sería el tipo de silencio que un evaluador
atento encuentra.

---

## Punto 33 · Resultados finales

**Lo que falta es la tabla que más peso tiene y que no existe:** la matriz de
**requerimientos implementados**. RF/RNF del APF1 → estado hoy → evidencia que
lo respalda.

El sistema está desplegado y probado, pero el informe tiene que poder
responder «¿se hizo lo que se prometió en la semana 5?» sin que el lector vaya
a buscarlo. Hay que recuperar los RF/RNF del APF1 y confrontarlos uno a uno,
incluidos los que no se hicieron — que los hay, y decirlo cuesta menos que
justificar una matriz toda en verde.

Materia prima disponible: 363 pruebas de backend, 116 de frontend, el sistema
en producción, la API pública consumible y 125 archivos de evidencia.

---

## Punto 34 · Conclusiones y recomendaciones

Por escribir. Dos candidatas que salen del trabajo real y no de la plantilla:

- **Un despliegue que falla mientras el servicio sigue respondiendo 200 es
  peor que una caída**, porque nadie se entera. Pasó dos veces en este
  proyecto —PHP 8.3 y `config:cache`— y la segunda estaba documentada en el
  Dockerfile por la primera. Lo que lo cierra no es documentarlo: es la
  prueba que ejecuta `config:cache` y rompe la suite.
- **Lo que no se mide en el entorno real no está verificado.** Las 363
  pruebas pasaban en verde con el fallo dentro, porque en desarrollo la
  configuración no se cachea nunca.

---

## Evidencias mínimas (sección VI)

| Requisito | Estado |
|---|---|
| Capturas o enlaces del sistema funcionando | **Listo** — 125 archivos en 16 carpetas, índice en `docs/evidencias/INDICE.md` |
| Repositorio GitHub | **Listo** — 183+ commits, `main` y `develop` alineados |
| Herramienta de gestión | **Listo** — GitHub Projects, capturas `E18`–`E25` |
| Evidencias de pruebas | **Listo** — `CRT-01` a `CRT-10` |
| Evidencias de despliegue | **Listo** — `DEP-01`, `DEP-02`, `SOA-06` a `SOA-11` |
| Evidencias de monitoreo | **Parcial** — falta la captura con umbrales |
| Evidencias de incidentes | **Parcial** — falta el recorrido completo |
| Evidencias de backups | **Parcial** — falta ejecutar el simulacro |

---

## Orden propuesto

Primero lo que cierra un punto entero de la rúbrica con poco trabajo; al final
lo que depende de decisiones.

| # | Trabajo | Cierra | Por qué en esta posición |
|---|---|---|---|
| 1 | Visor de eventos en el panel | 30, alimenta 29 y 31 | El modelo ya existe con los tres niveles; es el mayor salto por el menor esfuerzo |
| 2 | Matriz RF/RNF → estado → evidencia | 33 | Es la tabla de más peso y no existe |
| 3 | Los dos incidentes reales, documentados | 31 | Ya ocurrieron; solo hay que redactarlos |
| 4 | Ejecutar el simulacro de restauración | 32 | El script existe y nunca se ha corrido |
| 5 | Tabla de umbrales | 29 | Requiere decidir números y reconciliarlos con el APF1 |
| 6 | Retomar las capturas de la suite | 28 (O-B1) | Mecánico, pero hay que hacerlo al final para que las cifras sean las definitivas |
| 7 | Conclusiones y recomendaciones | 34 | Se escribe cuando lo demás está |

---

## Dos decisiones que hacen falta antes de empezar

1. **O-B3: qué cifra es la correcta** en el portal público. Inicio, Sobre
   Nosotros y el pie dicen cosas distintas, y es una decisión de negocio, no
   técnica. Mientras no se decida, la observación sigue abierta.

2. **Formato del entregable.** El APF3 es un `.docx` ya entregado y calificado
   que no se toca. El Proyecto Final puede ser un documento nuevo que lo
   incorpore corregido, o una copia del APF3 sobre la que se trabaje. La
   rúbrica dice que cada entrega es acumulativa, pero no obliga a una de las
   dos formas.
