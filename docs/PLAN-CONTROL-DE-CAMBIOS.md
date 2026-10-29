# Plan de Control de Cambios e Integración Continua · V1

> Botica San Juan · Curso Integrador II: Sistemas
> Entregable de la Semana 11 (sesiones 21-22). Complementa el capítulo 23 del
> informe del APF3, que ya recoge la clasificación y el caso CAM-001; aquí se
> añade lo que el material de clase pide y el informe no traía: la plantilla
> de solicitud de cambio, el calendario, el registro completo y la evidencia
> de integración continua.

---

## 1. Qué es un cambio aquí, y qué no

Tres cosas distintas que es fácil mezclar, y que en este proyecto se tratan
por caminos separados:

| | Solicitud de servicio | Incidente | Cambio |
|---|---|---|---|
| Qué dice el usuario | «Necesito algo» | «Algo que debería funcionar falla» | «Hay que modificar el servicio» |
| Dónde vive | `docs/SOLICITUDES-DE-SERVICIO.md` | Bitácora `eventos_operacion` | Este documento |
| Ejemplo real | SOL-003, alta de cuenta | El despliegue roto nueve días de octubre | Parche de PHP, portal del cliente, contingencia offline |

El control de cambios se aplica cuando una modificación puede afectar al
funcionamiento, la configuración, la seguridad, los datos, la documentación o
la disponibilidad del servicio. En la práctica: **casi todo lo que toca
producción**.

---

## 2. Clasificación

| Tipo | Cuándo | Autoriza | Ejemplo del proyecto |
|---|---|---|---|
| **Estándar** | Repetitivo, conocido y con resultado previsible | Preautorizado | Subir una dependencia menor |
| **Normal** | Afecta a funcionalidad o a datos | Propietario | Portal del cliente (Sprint 5) |
| **De emergencia** | Hay riesgo activo; esperar cuesta más que actuar | Se ejecuta y se documenta después | Parche de PHP del 21/10/2026 |

Que exista la categoría de emergencia **no significa saltarse el
procedimiento**: significa cambiar el orden. Se actúa primero y se registra
después, pero se registra.

---

## 3. El ciclo, paso a paso

Siete pasos, cada uno respondiendo una pregunta concreta:

| # | Paso | Pregunta | Dónde queda |
|---|---|---|---|
| 1 | **Solicitar** | ¿Qué quiero cambiar? | Ficha CR de este documento |
| 2 | **Analizar** | ¿Qué afecta? | Campos *Impacto*, *Riesgo* y *Dependencias* |
| 3 | **Aprobar** | ¿Se puede hacer? | Campo *Autorización* |
| 4 | **Planificar** | ¿Cuándo y cómo? | Calendario (sección 5) y campo *Vuelta atrás* |
| 5 | **Implementar** | — | Rama y commits enlazados en la ficha |
| 6 | **Verificar** | ¿Funcionó? | Pipeline de CI + comprobación sobre el entorno |
| 7 | **Cerrar** | — | Campo *Cierre*, con su evidencia |

**La regla que no se negocia:** un cambio que no se puede describir ni
revertir no pasa a producción. El plan de vuelta atrás se escribe **antes** de
ejecutar, no después de que algo salga mal.

---

## 4. Plantilla de solicitud de cambio (CR)

```
ID                  CAM-xxx
Cambio solicitado   Qué se modifica, en una frase
Motivo              Por qué hace falta
Tipo                Estándar | Normal | Emergencia
Componentes         Front-End / API / Base de datos / Infraestructura / Documentación
Impacto             Qué usuarios, módulos o datos se ven afectados
Riesgo              Qué puede salir mal y cuán grave sería
Dependencias        ¿Hace falta base de datos, proveedor, otro equipo?
Autorización        Quién aprueba
Prueba              Cómo se comprueba que funcionó
Vuelta atrás        Cómo se deshace
Fecha propuesta     Ventana del calendario
Rama / commit / PR  Enlace al trabajo real
Cierre              Evidencia adjunta
```

---

## 5. Calendario de cambios

Existe para que dos modificaciones críticas no compitan por la misma ventana.

| Ventana | Qué entra | Por qué |
|---|---|---|
| **Lunes a jueves, 09:00–17:00** | Cambios normales y estándar | Hay alguien delante para ver si algo se rompe |
| **Viernes** | Nada que toque base de datos o despliegue | Un fallo el viernes se descubre el lunes |
| **Fuera de horario de atención** | Migraciones con bloqueo de tablas | La botica no está vendiendo |
| **Inmediata** | Solo emergencias de seguridad | CAM-001 entró por aquí |

Regla práctica derivada del incidente de octubre: **un despliegue sin alguien
que lo verifique después no está terminado**. El despliegue que estuvo roto
nueve días pasó precisamente porque nadie comprobó el resultado.

---

## 6. Registro de cambios

### CAM-001 · Parche del intérprete PHP

| Campo | Valor |
|---|---|
| **Tipo** | **Emergencia** |
| **Fecha** | 21/10/2026 |
| **Motivo** | CVE-2026-109618 y CVE-2026-109619, CVSS 9.2, ejecución remota **sin autenticar** |
| **Componentes** | Imagen del contenedor, `php.ini` de producción, PHP local |
| **Impacto** | El fallo se dispara al parsear la petición, **antes de que corra una línea de Laravel**: ni Sanctum ni los roles intervienen |
| **Riesgo del cambio** | Bajo frente al de no hacerlo |
| **Autorización** | Propietario, con el riesgo explicado; ejecutado antes de documentar por tratarse de una emergencia |
| **Vuelta atrás** | Reactivar el despliegue anterior desde Render; volvería a quedar expuesto, así que solo ante caída del servicio |
| **Prueba** | `GET /api/salud` → 200 y cabecera `X-Powered-By: PHP/8.4.27` leída del servidor |
| **Commit** | `cf98072` |
| **Cierre** | Despliegue `dep-dbcleu3rjlhs73dt9ufg`, estado *live*. Catálogo intacto en 884 productos |

**Decisión documentada:** el aviso sugería `memory_limit = -1` como mitigación
y **no se aplicó**. Esa recomendación asume un límite de cgroup debajo que
mate solo al worker afectado; aquí el contenedor *es* el worker, en plan
gratuito, de modo que habría convertido un error 500 acotado en la caída del
servicio. Con el intérprete ya parcheado, habría empeorado el riesgo real.

### CAM-002 · Propiedad de los comprobantes

| Campo | Valor |
|---|---|
| **Tipo** | **Normal**, tratado con urgencia |
| **Fecha** | 22/10/2026 |
| **Motivo** | `facturacion/documentos/{id}/xml` y `/pdf` no comprobaban propiedad: cualquier cuenta autenticada podía descargar el comprobante de otra persona |
| **Componentes** | API: política, rutas y controlador del portal |
| **Impacto** | Datos personales de clientes expuestos entre cuentas |
| **Riesgo del cambio** | Medio: una política mal escrita podía dejar sin acceso al dueño legítimo |
| **Autorización** | Propietario |
| **Vuelta atrás** | Revertir el commit; se recupera el estado anterior, que es el vulnerable |
| **Prueba** | 8 pruebas en `ComprobantesDelClienteTest`: 200 para el titular, 403 para otra cuenta |
| **Commit** | `2315149` |
| **Cierre** | Reaparición del defecto D-05 del APF2; la regla se trasladó a una política única compartida por descargas y portal, para que no pueda divergir otra vez |

### CAM-003 · Contingencia offline del punto de venta

| Campo | Valor |
|---|---|
| **Tipo** | **Normal** |
| **Fecha** | 24 al 29/10/2026 |
| **Motivo** | La botica está en una zona donde el internet se cae; sin red el punto de venta dejaba de funcionar y la caja se atendía en papel |
| **Componentes** | Base de datos (dos migraciones), API (endpoint de sincronización), Front-End (almacén local, cola, búsqueda), documentación |
| **Impacto** | Toda la operación de caja |
| **Riesgo del cambio** | **Alto**: duplicar ventas o duplicar comprobantes es una contingencia tributaria, no un defecto cosmético |
| **Dependencias** | Migración sobre `pedidos` en producción, pendiente de ventana |
| **Autorización** | Propietario |
| **Vuelta atrás** | Las migraciones tienen `down()`; el endpoint es aditivo y ninguna pantalla anterior depende de él. Revertir no rompe lo que ya funcionaba |
| **Prueba** | 14 pruebas de sincronización en el backend y 24 en el frontend, más el ensayo de continuidad del 24/10 con corte y restablecimiento reales |
| **Commits** | `3174100`, `067e333`, `429bbae`, `65a03e0` |
| **Cierre** | Documentado en `docs/ARQUITECTURA-OFFLINE.md`, sección 11, con las dos bitácoras y las capturas |

---

## 7. Integración continua

### 7.1. Qué valida el pipeline

El flujo de trabajo vive en `.github/workflows/ci-cd.yml` y se dispara en cada
`push` a `main` y en cada Pull Request. Tres trabajos encadenados:

```
secrets-scan ──┬──> backend-tests   (PostgreSQL 16 + PHPUnit)
               └──> frontend-build  (type-check + build de producción)
```

| Trabajo | Qué comprueba | Por qué está primero o después |
|---|---|---|
| `secrets-scan` | Que no se haya colado una credencial en el código | Va **primero y bloquea a los demás**: si hay un secreto en el commit, lo último que interesa es gastar cinco minutos corriendo pruebas |
| `backend-tests` | 179 pruebas sobre **PostgreSQL 16**, el mismo motor que producción | SQLite no sirve: no entiende `GREATEST`, `FILTER (WHERE …)` ni `ILIKE`, que es justo lo que usan las consultas del proyecto |
| `frontend-build` | Verificación de tipos y compilación real | Un error de tipos que solo aparece al compilar no debe descubrirse en el despliegue |

Dos detalles que costaron fallos antes de quedar bien:

- El servicio de PostgreSQL lleva `--health-cmd`. Sin esa espera, las pruebas
  arrancaban antes de que la base aceptara conexiones y fallaban con
  *connection refused* sin que hubiera nada roto en el código.
- `pnpm install --frozen-lockfile`, no `install` a secas: falla si el
  `package.json` y el lockfile no concuerdan en vez de resolver versiones
  nuevas en silencio. Es lo que hace que la compilación sea reproducible.

### 7.2. Qué pasa cuando el pipeline se pone en rojo

Lo que el material describe en abstracto, aquí tiene historial:

| Indicador | Valor |
|---|---|
| Ejecuciones registradas | 71 |
| En verde | 27 |
| En rojo | 44 |
| Ejecuciones de las últimas doce entregas | **12 de 12 en verde** |

**Las 44 ejecuciones en rojo no son un dato que convenga esconder: son el
argumento a favor de tener CI.** Cada una detuvo un cambio antes de que
llegara a producción. Un ejemplo con nombre: la ejecución `39099119085` del
18/10 pasó `secrets-scan` y `backend-tests` y falló en `frontend-build` — es
decir, el código del servidor estaba bien y lo que se habría desplegado era un
frontend que no compilaba. Sin pipeline, eso se descubre en producción.

El periodo con más fallos es el de septiembre, cuando el propio flujo de
trabajo estaba a medio ajustar (`ci: corregir la integracion continua`, run
`34406861180`). Conviene distinguir las dos cosas: **fallos del pipeline** son
ruido que hay que arreglar; **fallos detectados por el pipeline** son su
trabajo.

### 7.3. Evidencia

| Qué | Dónde |
|---|---|
| Definición del flujo | `.github/workflows/ci-cd.yml` |
| Historial de ejecuciones | Pestaña *Actions* del repositorio |
| Pull Request con revisión | [#13 · Sprint 3-4: punto de venta, conteo por ciclos](https://github.com/ChrisRafaile/botica-san-juan/pull/13), fusionado tras pasar CI |
| Cambios enlazados a commits | Sección 6 de este documento |

---

## 8. Vinculación del cambio con rama, commit y Pull Request

| Cambio | Rama | Commits | Pull Request |
|---|---|---|---|
| CAM-001 · Parche PHP | `main` (emergencia) | `cf98072` | — ejecutado directamente por urgencia |
| CAM-002 · Propiedad de comprobantes | `develop` | `2315149` | — |
| CAM-003 · Contingencia offline | `develop` | `3174100`, `067e333`, `429bbae`, `65a03e0` | — |
| CAM-004 · Búsqueda sin conexión | `develop` | `0002075` | — |
| CAM-005 · Este plan | `docs/CAM-005-plan-control-cambios` | ver PR | **sí** |

### Lo que este proyecto no venía cumpliendo

El material recomienda trabajar en rama y revisar mediante Pull Request. Hasta
CAM-004 **esto se cumplía a medias**: existía un PR real —el #13, del Sprint
3-4— pero el resto del trabajo entraba con commits directos a `develop` y un
*fast-forward* a `main`.

En un proyecto de un solo autor, un PR consigo mismo parece un trámite. El
procedimiento, sin embargo, no está escrito para el caso cómodo: sirve para
que el pipeline se pronuncie **antes** de que el cambio toque la rama que
despliega, y para que exista un sitio donde quede escrito qué se revisó.

**A partir de CAM-005 el flujo cambia**: rama por cambio, Pull Request, y
fusión solo con el pipeline en verde. Este mismo documento entra por ese
camino, de modo que su propia evidencia demuestra el procedimiento que
describe.

Lo que sí se cumplía desde el principio: **ningún cambio llega a producción
sin pasar el pipeline**, porque el despliegue de Render se dispara desde
`main` y `main` está cubierta por CI en cada push.

---

## 9. Cambios propuestos y todavía no ejecutados

Forman el embudo de entrada del procedimiento. Están registrados pero **no
implementados**, y se distinguen del registro de la sección 6 precisamente por
eso: un plan de control de cambios que mezcla lo hecho con lo deseado deja de
servir para rendir cuentas.

| ID | Cambio | Tipo | Riesgo | Vuelta atrás |
|---|---|---|---|---|
| CR-P01 | Indicador de vencimiento FEFO en los resultados del punto de venta | Normal | Medio · puede penalizar la búsqueda si se calcula por fila | Revertir el commit de la rama |
| CR-P02 | Aviso automático de stock crítico por correo, como tarea programada | Normal | Bajo · corre en segundo plano, no toca la venta | Desactivar la tarea y revertir |
| CR-P03 | Índice GIN sobre `principio_activo` para acelerar la búsqueda | Normal | Medio · un índice mal creado puede bloquear la tabla al crearse | `migrate:rollback` con respaldo previo |

CR-P03 merece una nota: en PostgreSQL, crear un índice bloquea la tabla salvo
que se use `CONCURRENTLY`, que a su vez no puede ir dentro de una transacción.
Es exactamente el tipo de detalle que el paso de *Analizar* existe para
descubrir antes y no durante la ventana de cambio.

---

## 9. Ficha de ejemplo para la práctica de clase

El material pide seleccionar un cambio pendiente y recorrerlo entero. El
elegido es el que se está ejecutando ahora mismo:

| Campo | Valor |
|---|---|
| **ID** | CAM-004 |
| **Cambio solicitado** | Búsqueda de productos en la caja sin conexión |
| **Motivo** | El ensayo de continuidad del 24/10 demostró que la venta se guardaba bien, pero el vendedor no podía **encontrar** el producto para armarla |
| **Tipo** | Normal |
| **Componentes** | API (`GET /pos/catalogo`), Front-End (catálogo local y búsqueda híbrida) |
| **Impacto** | Pantalla de caja; ninguna otra |
| **Riesgo** | Bajo. Lo peor que puede pasar es que la búsqueda local no encuentre algo, y entonces se queda como estaba: sin resultados |
| **Dependencias** | Ninguna. El endpoint es aditivo y el catálogo local no toca la base central |
| **Autorización** | Propietario |
| **Prueba** | 9 pruebas de catálogo local y 3 de endpoint; el stock se declara «sin verificar» en vez de inventarse |
| **Vuelta atrás** | Revertir el commit; la búsqueda vuelve a depender de la red, que es como estaba |
| **Fecha** | 29/10/2026, dentro de ventana |
| **Cierre** | Pendiente de recorrido de verificación con la red cortada |
