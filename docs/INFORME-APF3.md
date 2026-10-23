# APF3 · Gestión del servicio y versión final

Material para los puntos 22 a 27 del trabajo final. Todas las cifras de este
documento están **medidas el 23 de octubre de 2026**, no recordadas de
entregas anteriores; donde una medición es anterior se dice su fecha.

---

## 22. Cuadro de observaciones del APF2 y su subsanación

### 22.1. Observaciones subsanadas

| N.º | Observación del APF2 | Qué se hizo | Evidencia |
|---|---|---|---|
| O-A1 | El intérprete PHP quedó expuesto a CVE-2026-109618 y CVE-2026-109619 (RCE sin autenticar, CVSS 9.2) | Imagen base y PHP local a 8.4.27; comprobador que aborta build y arranque sobre un intérprete vulnerable; endurecimiento en `docker/seguridad.ini` | commit `cf98072`; cabecera `X-Powered-By: PHP/8.4.27` en producción |
| O-A2 | El recuento de la suite estaba desfasado en cuatro lugares del informe (107, 120 y 139 según la página) | Sincronizado con la corrida real y añadido el dato a README e informe en Markdown | commit `e995733` |
| O-A3 | **El defecto D-05 reapareció**: `facturacion/documentos/{id}/xml` y `/pdf` no comprobaban propiedad | Regla trasladada a `ComprobanteElectronicoPolicy`, aplicada por igual a descargas y portal | commit `2315149`; 8 pruebas en `ComprobantesDelClienteTest` |
| O-A4 | `claude` figuraba en la lista de contributors del repositorio | Verificado que no procedía del historial (0 commits con atribución, incluidas las refs ocultas de PR); recálculo forzado renombrando la rama | Panel de contributors con un solo colaborador |
| O-A5 | El modal «Editar producto» mostraba el icono de imagen rota | Dos fallos: la ruta no se resolvía contra el origen de la API y no había respaldo. Ambos en `ImagenConRespaldo.vue` | commit `4fea5e5`; 9 pruebas |
| O-A6 | Cerrar sesión no invalidaba la sesión hasta pasados cinco segundos de cuenta atrás decorativa | La sesión se cierra primero; la pantalla informa del estado real | commit `454a3ac` |
| O-A7 | El frontend no tenía ninguna herramienta de pruebas | Vitest y Vue Test Utils configurados; 34 pruebas de componente | commit `3a653b7` |
| O-A8 | Las descargas de comprobantes no comprobaban propiedad | Política aplicada a descargas y portal | commit `2315149` |
| O-A9 | El apartado 18.3 decía «4 524 unidades vendibles» frente a «4 501» de la Tabla 62 | **Medido**: 4 501 en las tres formas de contarlo —stock contable, unidades en lotes y unidades vendibles— con cero unidades vencidas. El 4 524 era incorrecto y se corrigió | Consulta del 23/10/2026 |
| O-A10 | Las pantallas del Sprint 5 no tenían verificación visual | Siete capturas tomadas sobre el entorno local | `docs/evidencias/APF3-capturas/` |

### 22.2. Observaciones abiertas

Se listan porque un cuadro que solo recoge lo corregido no es un cuadro de
observaciones, es una lista de logros.

| N.º | Observación | Estado | Por qué sigue abierta |
|---|---|---|---|
| O-B1 | Las Figuras 43 y C.3 muestran ejecuciones de la suite con 98/246 y 139/421, cifras ya superadas | Pendiente | Son capturas de pantalla: hay que volver a tomarlas, no editar el texto. Reescribir el pie haría que mintiera sobre su propia imagen |
| O-B2 | La Figura 11 rotula «26 migraciones versionadas» dentro del PNG; hoy son 46 | Pendiente | El dato está incrustado en la imagen; exige regenerar el diagrama |
| O-B3 | Las cifras del portal público («más de 10 años», «8k clientes») no coinciden entre Inicio, Sobre Nosotros y el pie | Pendiente | Decisión de negocio sobre qué cifra es la correcta |
| O-B4 | Los 884 productos del catálogo apuntan a `images/default_image.png`, archivo que ya no existe | Pendiente | Todas las imágenes del catálogo responden 404. El respaldo lo disimula con dignidad, pero el catálogo sigue sin fotografías. Exige cargar las imágenes reales |
| O-B5 | Faltan capturas de `/registro` completo y de la tabla de productos del administrador | Pendiente | Las siete tomadas cubren lo esencial; estas dos completarían el anexo |

### 22.3. Matriz «Antes / Después»

Las capturas están en `docs/evidencias/APF3-capturas/` y se tomaron sobre el
entorno local en ejecución, no sobre un prototipo.

| Observación | Antes | Después | Qué cambió |
|---|---|---|---|
| O-A5 · Imagen rota en «Editar producto» | Captura del modal con el icono de archivo roto del navegador y el texto alternativo «Producto» | `E17-modal-editar-producto-respaldo.jpg` | La ruta se resuelve contra el origen de la API y, si el archivo no existe, aparece un respaldo en lugar del icono roto |
| O-A6 · Cierre de sesión | Tarjeta con barra de progreso decorativa; la sesión seguía abierta cinco segundos | `E16-logout-despedida-rol.jpg` | La sesión se cierra primero; la pantalla reconoce nombre y rol |
| Confirmación de salida | No existía: un clic cerraba la sesión sin preguntar | `E15-dialogo-confirmar-salida.jpg` | Diálogo accesible con el foco en la opción segura |
| O-A10 · Portal del cliente | 93 líneas con cuatro tarjetas de relleno sin datos | `E14-portal-cliente-escritorio.jpg` | Cifras agregadas en SQL, historial con estado y acceso a comprobantes |
| Validación del acceso | Sin gestión de foco | `E12-login-validaciones-y-foco.jpg` | El foco va al primer campo con error y el hueco reservado evita el salto |

**Comprobaciones hechas sobre el DOM, no solo a la vista:**

```json
// Diálogo de confirmación
{ "rol": "dialog", "modal": "true", "enfocado": "Seguir trabajando" }

// Acceso enviado en blanco
{ "enfocado": "acceso-dni",
  "invalidos": ["acceso-dni", "acceso-clave"] }
```

### 22.4. Un defecto que encontró el propio recorrido

El recorrido con navegador no sirvió solo para fotografiar lo hecho: **detectó
una regresión introducida en este mismo Sprint**. Al reescribir el portal del
cliente desapareció el botón de cerrar sesión que tenía la versión anterior, de
modo que se entraba al panel y no había forma de salir salvo escribiendo la URL
a mano.

Ninguna de las 34 pruebas de componente podía verlo: todas comprueban que lo
que existe se comporta bien, y ninguna comprueba que algo que debería existir
no falte. Es el argumento, con un caso concreto, de por qué la verificación
visual no es un trámite para el anexo.

---

## 23. Procedimiento de control de cambios

### 23.1. Qué es un cambio y qué no

| Solicitud | Incidente | Cambio |
|---|---|---|
| «Necesito algo» | «Algo que debería funcionar está fallando» | «Necesito modificar el servicio» |
| Catálogo en `docs/SOLICITUDES-DE-SERVICIO.md` | Despliegue roto nueve días (octubre) | Actualización de PHP, portal del cliente, política de comprobantes |

### 23.2. Clasificación

| Tipo | Cuándo | Autoriza | Ejemplo real del proyecto |
|---|---|---|---|
| **Estándar** | Repetitivo, con resultado conocido | Preaprobado | Actualizar una dependencia menor |
| **Normal** | Afecta a funcionalidad o datos | Propietaria | Portal del cliente (Sprint 5) |
| **De emergencia** | Riesgo activo; esperar cuesta más que actuar | Se ejecuta y se documenta después | Parche de PHP del 21/10/2026 |

### 23.3. Flujo

```
Registrar → Evaluar impacto y riesgo → Autorizar → Plan de vuelta atrás
   → Ejecutar → Verificar → Cerrar y documentar
```

El plan de vuelta atrás **no es opcional**: un cambio sin forma de deshacerlo
no se autoriza. En este proyecto es barato y por eso no hay excusa — Render
conserva los despliegues anteriores y uno se reactiva desde el panel.

### 23.4. Caso de estudio · CAM-001, cambio de emergencia

| Campo | Valor |
|---|---|
| **Código** | CAM-001 |
| **Fecha** | 21/10/2026 |
| **Tipo** | De emergencia |
| **Origen** | Aviso de seguridad de php.net: CVE-2026-109618 y CVE-2026-109619, CVSS v4.0 9.2, ejecución remota de código **sin autenticar** |
| **Impacto si no se actúa** | El aviso nombra a FrankenPHP, que es lo que sirve esta API. El fallo se dispara durante el parseo de la petición, **antes de que corra una línea de Laravel**: ni Sanctum ni los roles intervienen |
| **Alcance** | Imagen base del contenedor, `php.ini` de producción y PHP local de desarrollo |
| **Autorización** | Propietaria, con el riesgo explicado. Por ser de emergencia, se ejecutó primero sobre producción y se documentó después |
| **Plan de vuelta atrás** | Reactivar el despliegue anterior desde Render. Riesgo asumido: volvería a quedar expuesto, de modo que solo se usaría ante una caída del servicio |
| **Ejecución** | 1) Verificado que el tag `1-php8.4-alpine` ya apuntaba a 8.4.27 comparando digests con `1-php8.4.27-alpine`. 2) Redespliegue **con caché limpia**: sin eso la capa `FROM` cacheada habría reutilizado la imagen antigua y el despliegue habría *parecido* correcto. 3) Refuerzo en `docker/seguridad.ini` y comprobador de versión |
| **Verificación** | `GET /api/salud` → 200; `X-Powered-By: PHP/8.4.27` leído del servidor; catálogo intacto en 884 productos |
| **Cierre** | Despliegue `dep-dbcleu3rjlhs73dt9ufg`, estado *live* |

**Decisión técnica documentada:** el aviso sugiere `memory_limit = -1` como
mitigación. **No se aplicó.** Esa recomendación asume un límite de cgroup
debajo que mate solo al worker afectado; aquí el contenedor *es* el worker, en
plan gratuito, así que convertiría un error 500 acotado en la caída del
servicio. Con el intérprete ya parcheado, habría empeorado el riesgo real.

### 23.5. Qué dejó el cambio para no repetirlo

El tag de la imagen se mantiene **flotante** a propósito: la próxima
corrección llega sola al reconstruir. Lo que un tag flotante no permite es
demostrar qué versión se ejecuta, y eso lo cubre
`docker/comprobar-php-seguro.php`, que aborta la construcción y el arranque
sobre un intérprete vulnerable. Usa una tabla de mínimos **por rama**, porque
comparar contra un único número daría un falso negativo con PHP 8.5.11, que es
mayor que 8.4.27 y sigue siendo vulnerable.

Es la lección del incidente de octubre: una discrepancia de versión tiene que
gritar, no quedarse nueve días en un registro que nadie mira.

---

## 24. Solicitudes de servicio

Catálogo completo en `docs/SOLICITUDES-DE-SERVICIO.md`. Seguimiento en
[GitHub Projects · tablero 3](https://github.com/users/ChrisRafaile/projects/3).

### 24.1. El ciclo de atención, paso a paso

El procedimiento sigue los siete pasos del estándar del curso. Ninguno se
omite, y el cuarto es el que más tentación da de saltarse:

| # | Paso | Qué ocurre aquí | Dónde queda registrado |
|---|---|---|---|
| 1 | **Registrar** | La petición entra como issue del repositorio con su código SOL-xxx. La issue **es** el ticket | Issues #14 a #19 |
| 2 | **Clasificar** | Se decide si es información, acceso o estándar, y se etiqueta | Etiquetas `tipo: informacion`, `tipo: acceso`, `tipo: estandar` |
| 3 | **Validar** | Se comprueba que es una solicitud y no un incidente ni un cambio, y que los datos están completos | Campos del ticket |
| 4 | **Autorizar** | Si requiere aprobación, se pide. **Si la aprobación no corresponde, se rechaza o se escala; no se salta** | Campo *Aprobación* del tablero |
| 5 | **Atender** | El responsable ejecuta la acción | Campo *Responsable de atención* |
| 6 | **Verificar** | Se comprueba el resultado antes de dar nada por hecho | Campo *Evidencia* |
| 7 | **Cerrar** | Se cierra con su evidencia adjunta. Una solicitud sin evidencia no se cierra | Estado final |

### 24.2. El ciclo aplicado a las seis solicitudes

| Paso | SOL-001 | SOL-002 | SOL-003 | SOL-004 | SOL-005 | SOL-006 |
|---|---|---|---|---|---|---|
| 1 · Registrar | #14 · 14/10 | #15 · 16/10 | #16 · 17/10 | #17 · 18/10 | #18 · 19/10 | #19 · 20/10 |
| 2 · Clasificar | Información | Información | Acceso | Acceso | Estándar | Estándar |
| 3 · Validar | Es orientación, no una falla | Se confirma que no es un error del sistema | Datos completos del alta | Se confirma que es acceso, no incidente | Identidad por verificar | Motivo y fecha objetivo |
| 4 · **Autorizar** | No requiere | No requiere | Propietaria **aprueba** | Propietaria **deniega** | Propietaria aprueba | Propietaria aprueba |
| 5 · Atender | Se indica la ruta y el criterio de fecha | Se identifica el lote vencido | Cuenta con rol vendedor | — *(no se atiende lo denegado)* | Restablecimiento con cambio obligatorio | Respaldo antes de la carga |
| 6 · Verificar | Captura de la pantalla con el rango | Consulta sobre `lotes` | Registro del usuario y su rol | La API responde 401/403 al rol cliente | Registro del acceso posterior | Conteo de filas antes y después |
| 7 · Cerrar | Cerrada | Cerrada | Cerrada | **Rechazada** | Cerrada | Cerrada |

**SOL-004 es el caso que justifica el paso 4.** Un cliente pidió acceso a los
reportes de ventas. Conceder lo que se pide es lo cómodo; el procedimiento
existe precisamente para los casos en que no corresponde. Se denegó por mínimo
privilegio —los reportes contienen márgenes, costos y datos de otros
clientes— y se ofreció la alternativa que sí le pertenece: su propio historial
y sus comprobantes. La evidencia no es una captura de pantalla: **es el propio
sistema devolviendo 401/403 a un token de rol cliente**, con pruebas
automatizadas que fijan esa regla.

### 24.3. Lo que el ciclo deja medible

| Indicador | Resultado |
|---|---|
| Solicitudes registradas | 6 |
| Reparto por tipo | 2 información · 2 acceso · 2 estándar |
| Atendidas y cerradas | 5 |
| Rechazadas, con motivo documentado | 1 |
| Cerradas sin evidencia adjunta | **0** |
| Solicitudes que requirieron aprobación | 4 de 6 |

---

## 25. Gestión de capacidad y rendimiento

### 25.1. Qué se mide y contra qué

| Indicador | Cómo se obtiene | Umbral | Medición (23/10/2026) | Resultado |
|---|---|---|---|---|
| Respuesta del catálogo en producción | 7 peticiones a `/api/productos`, se descarta la primera por arranque en frío | ≤ 2 000 ms (RNF03) | mediana **382 ms**, min 359, max 434 | Cumple con margen de 5× |
| Salto de maquetación (CLS) | `PerformanceObserver` sobre las vistas públicas | ≤ 0.1 (bueno) | 0.015 en catálogo, 0.013–0.015 en carrito y contacto | Cumple |
| Suite de pruebas del backend | `php artisan test` sobre PostgreSQL 16 | Toda en verde | **165 pruebas, 497 aserciones, 14.05 s** | Cumple |
| Suite de componentes | `pnpm test` (Vitest) | Toda en verde | **34 pruebas, 1.40 s** | Cumple |
| Compilación del frontend | `pnpm build` | Sin avisos | **6.12 s** | Cumple |
| Peso entregado al navegador | `dist/assets` tras compilar | — | 95 archivos JS (1 515 kB) y 12 CSS (157 kB); la carga inicial son 189 kB (71 kB comprimidos) | Informativo |

### 25.2. Capacidad del sistema

| Dimensión | Valor medido |
|---|---|
| Productos en catálogo | 884 (tras fusionar 869 grupos duplicados) |
| Rutas de API | 119 |
| Migraciones versionadas | 46 |
| Modelos / controladores / servicios | 23 / 22 / 10 |
| Commits en `main` | 125 |

### 25.3. Límites conocidos, dichos en voz alta

- El plan de Render es **gratuito**: un solo contenedor, que se suspende por
  inactividad. La primera petición tras la suspensión tarda del orden de
  segundos, y por eso la medición descarta la primera muestra. Para producción
  real haría falta un plan que no duerma.
- **No se ha hecho una prueba de carga.** Las cifras de arriba son de
  respuesta con un usuario, no de comportamiento con varios a la vez. Decir
  que el sistema «soporta X usuarios» sin haberlo medido sería inventarlo.
- El archivo `AdminBulkUploadView` pesa 483 kB porque incorpora el lector de
  hojas de cálculo. Solo se descarga al entrar en esa pantalla, pero es el
  candidato evidente a dividir si alguna vez estorba.

---

## 25.4. Evidencias visuales del rendimiento percibido

Las siete capturas se tomaron con el entorno local en ejecución (API en 8083,
Vite en 5173) y muestran el estado cargado de cada pantalla. El portal del
cliente resuelve tres peticiones en paralelo —resumen, pedidos y
comprobantes— y reserva la altura de tarjetas y filas antes de que lleguen,
de modo que la respuesta de la API no desplaza nada.

---

## 26. Despliegue de la versión final

| Componente | Dónde | Estado al 23/10/2026 |
|---|---|---|
| API | Render · `botica-san-juan-api.onrender.com` | Live, PHP 8.4.27, auto-deploy desde `main` |
| Portal | Vercel · `botica-san-juan.vercel.app` | Live |
| Base de datos | Neon · PostgreSQL 16 | 884 productos, 46 migraciones aplicadas |

---

## 27. Retrospectiva del Sprint 6

### 27.1. Qué mantener

| Práctica | Qué evitó |
|---|---|
| Leer el código antes de afirmar qué le falta | Dos de los cinco «fallos» de la pantalla de acceso ya estaban resueltos: `aria-invalid` vivía en el componente de campo y el medidor de contraseña se llamaba `fortaleza`. Un `grep` en inglés sobre un solo archivo no los encontró |
| Verificar leyendo de vuelta, no por el eco del propio comando | Los campos del tablero de GitHub se dieron por guardados cuando estaban vacíos: el script imprimía su intención y mandaba los errores a `Out-Null` |
| Mover la regla a un solo sitio en lugar de repetirla | El defecto D-05 reapareció justo porque la primera vez se corrigió escribiendo la comprobación dentro de un método |
| Reservar el espacio antes de que lleguen los datos | El CLS sigue en 0.015 con tarjetas, historial y comprobantes nuevos |

### 27.2. Qué dejar de hacer

| Práctica | Qué costó |
|---|---|
| Diagnosticar con `grep` lo que exige leer | Dos informes de alcance equivocados en turnos consecutivos |
| Silenciar la salida de un comando que puede fallar | Treinta ediciones del tablero fallaron sin que nadie se enterara. La causa real: `$pid` es variable automática de PowerShell y el script enviaba el identificador del proceso |
| Dar por protegido un endpoint porque su grupo exige sesión | Exigir identidad no es exigir propiedad: las descargas de comprobantes estaban abiertas a cualquier cuenta |
| Afirmar en el informe cifras que no se acaban de medir | El recuento de la suite llegó a aparecer con tres valores distintos en el mismo documento |

### 27.3. Qué empezar a hacer

1. **Capturar la pantalla, no solo compilarla.** Las tres etapas del Sprint 5
   tienen 34 pruebas y cero capturas. En este proyecto el compilador ya dejó
   pasar dos imágenes rotas que delató una captura.
2. **Prueba de carga antes de hablar de capacidad.** Hasta entonces, el
   apartado 25 dice tiempos de respuesta y no capacidad de usuarios.
3. **Cerrar las cinco observaciones abiertas del cuadro 22.2**, empezando por
   las dos figuras que muestran cifras superadas.
4. **Revisar los endpoints existentes con el mismo criterio que los nuevos.**
   D-05 reapareció en una ruta que ya existía; nadie había vuelto a mirarla.

### 27.4. Compromisos para el Sprint 7

| N.º | Acción | Indicador de éxito | Responsable |
|---|---|---|---|
| A-08 | Capturar las pantallas del Sprint 5 en el entorno local | Cuatro capturas incorporadas al informe | Equipo |
| A-09 | Recapturar las Figuras 43 y C.3 con la corrida actual | Los pies coinciden con lo que muestran | Equipo |
| A-10 | Resolver la discrepancia 4 501 / 4 524 con una consulta | Una sola cifra en todo el documento | Equipo |
| A-11 | Auditar las rutas de API que no tienen prueba de autorización | Lista revisada, con o sin hallazgos | Equipo |
