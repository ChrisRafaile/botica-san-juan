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

### 22.2. Observaciones abiertas

Se listan porque un cuadro que solo recoge lo corregido no es un cuadro de
observaciones, es una lista de logros.

| N.º | Observación | Estado | Por qué sigue abierta |
|---|---|---|---|
| O-B1 | Las Figuras 43 y C.3 muestran ejecuciones de la suite con 98/246 y 139/421, cifras ya superadas | Pendiente | Son capturas de pantalla: hay que volver a tomarlas, no editar el texto. Reescribir el pie haría que mintiera sobre su propia imagen |
| O-B2 | La Figura 11 rotula «26 migraciones versionadas» dentro del PNG; hoy son 46 | Pendiente | El dato está incrustado en la imagen; exige regenerar el diagrama |
| O-B3 | El apartado 18.3 dice «4 524 unidades vendibles» y la Tabla 62 dice «4 501» | Pendiente | Una de las dos es incorrecta, o miden cosas distintas (stock contable frente a vendible). Se resuelve con una consulta, no a ojo |
| O-B4 | Las pantallas del Sprint 5 no tienen verificación visual | Pendiente | Hay 34 pruebas de componente, que demuestran la lógica y no el aspecto. Requiere entorno local levantado |
| O-B5 | Las cifras del portal público («más de 10 años», «8k clientes») no coinciden entre Inicio, Sobre Nosotros y el pie | Pendiente | Decisión de negocio sobre qué cifra es la correcta |

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
[GitHub Projects · tablero 3](https://github.com/users/ChrisRafaile/projects/3),
verificado el 23/10/2026 leyendo los campos desde la API:

| Código | Tipo | Estado | Fecha |
|---|---|---|---|
| SOL-001 | Información | Cerrada | 14/10/2026 |
| SOL-002 | Información | Cerrada | 16/10/2026 |
| SOL-003 | Acceso | Cerrada | 17/10/2026 |
| SOL-004 | Acceso | **Rechazada** | 18/10/2026 |
| SOL-005 | Estándar | Cerrada | 19/10/2026 |
| SOL-006 | Estándar | Cerrada | 20/10/2026 |

Las seis son issues del repositorio (#14 a #19) con los doce campos exigidos.
Se retiraron del tablero de Sprints, donde la automatización las había
colocado, para no mezclar entregables de desarrollo con gestión del servicio.

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
