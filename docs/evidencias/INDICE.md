# Índice de evidencias

Mapa de códigos para citar en el informe final. 118 archivos en 16 carpetas.

---

## La convención

```
docs/evidencias/<tema>/<CÓDIGO>-<nn>-<descripción-en-kebab>.<ext>
```

**El código identifica el tema y es único en todo el repositorio.** Esa es la
regla entera, y existe porque antes no se cumplía: `E12` significaba «login con
validaciones» en una carpeta y «checks del PR 27» en otra, y `C01` era a la vez
una captura de correo y un informe de cierre. Citar «la evidencia E12» no
señalaba un archivo, señalaba dos.

El número es secuencial **dentro de su tema** y sigue el orden en que ocurren
los hechos, no el orden alfabético: `R01` es el pedido sin receta y `R09` el
pedido entregado, de modo que leer la carpeta en orden es recorrer el flujo.

La descripción dice **qué se ve**, no qué archivo es. `M02-antes-marcador-que-no-decodifica`
se entiende sin abrirlo; `captura2.jpg` no.

### Para añadir una evidencia nueva

1. Si encaja en un tema existente, siguiente número libre de ese código.
2. Si es un tema nuevo, código de tres letras que no esté en la tabla de abajo.
3. Añadirla a este índice en el mismo commit. Una evidencia sin fila aquí es
   una evidencia que nadie encontrará cuando haga falta.

---

## Códigos

| Código | Carpeta | Tema | Archivos |
|---|---|---|---|
| `E1`–`E10` | *(raíz)* | Evidencias del APF2 | 27 |
| `E11`–`E25` | `APF3-capturas/` | Capturas del APF3 | 15 |
| `PER` | `perfil/` | Foto de perfil y cabeceras | 13 |
| `CRT` | `cortes/` | Cortes de datos: suites y migraciones | 10 |
| `R` | `recetas/` | Recetas médicas, de alta a entrega | 9 |
| `UI` | `ui/` | Interfaz del punto de venta | 6 |
| `COR` | `correos/` | Correo transaccional | 6 |
| `SOA` | `soa/` | Interoperabilidad y documentación de la API | 11 |
| `OFF` | `E2E-offline/` | Venta en contingencia de punta a punta | 4 |
| `O` | `observabilidad/` | Sentry, PostHog y privacidad | 4 |
| `EVT` | `eventos/` | Bitácora de operación · punto 30 de la rúbrica | 4 |
| `CI` | `CI-CD/` | Integración continua | 3 |
| `I` | `interacciones/` | Consultor de interacciones | 3 |
| `K` | `kpis/` | Panel de indicadores | 3 |
| `M` | `modal-producto/` | Modal «Editar Producto» | 3 |
| *(descriptivo)* | `figura11/` | Figura 11 corregida | 3 |
| `DEP` | `DESPLIEGUE/` | Despliegue en Vercel y Render | 2 |
| `CIE` | `cierre/` | Verificación del cierre de bloque | 2 |

---

## Qué demuestra cada tema

### `E1`–`E10` · raíz — APF2
Índice propio en **`docs/EVIDENCIAS-APF2.md`**, que el APF3 entregado cita por
nombre. **Estos archivos no se renombran**: romperían un índice al que apunta un
documento ya calificado. Cubren el índice único que rechaza duplicados, la
fusión de duplicados antes y después, los contratos de la API, el CLS del
catálogo en los dos temas, la suite automatizada, el historial de git, el
carrito y contacto, la venta en el punto de venta, los tiempos de respuesta y
el checkout.

### `E11`–`E25` · `APF3-capturas/` — APF3
Continúan la numeración de la raíz: una sola secuencia repartida en dos
carpetas, no dos secuencias que chocan. Autenticación, portal del cliente,
cierre de sesión, y el tablero de incidencias con sus seis solicitudes.

### `PER` · `perfil/`
Subida de la foto de perfil de punta a punta, y cómo aparece después en la
barra lateral y en las cabeceras de los dos roles. `PER-12` y `PER-13`, el PR 27
en verde y fusionado.

### `CRT` · `cortes/`
Salidas literales con su guion de reproducción en JSON al lado: la suite, el
recuento de migraciones en desarrollo y en producción, y las reglas de negocio.
Van en pares *(imagen, guion)* para que la cifra de la captura se pueda volver a
obtener.

### `R` · `recetas/`
El flujo clínico completo y en orden: pedido que exige receta, zona de
arrastre, envío, bandeja del administrador, visor, rechazo con motivo
obligatorio, el motivo que ve el cliente, reenvío y entrega.

### `UI` · `ui/`
El punto de venta: búsqueda sin conexión, cobro, ticket de contingencia,
reconexión con la cola vacía. Tiene su propio `README.md`.

### `COR` · `correos/`
Recuperación de contraseña sin revelar si la cuenta existe, medidor de
contraseña, cierre de las demás sesiones y confirmación del correo.
`COR-06` lleva el texto íntegro de los dos correos.

### `SOA` · `soa/`
Interoperabilidad. `SOA-01`–`SOA-03`, el PR 25 de SUNAT. `SOA-04`–`SOA-05`, la
documentación con Scalar. `SOA-06`–`SOA-09`, la verificación **en producción**
de la API pública: Scalar y Stoplight accesibles, la respuesta JSON real y el
acta `SOA-08` con los diez códigos de respuesta, el contrato campo a campo y la
comprobación de CORS hecha desde otro dominio. `SOA-10`–`SOA-11`, las dos
interfaces anunciando ya el servidor en `https://`.

### `OFF` · `E2E-offline/`
Dos ventas emitidas sin conexión, con sus correlativos, y la cola vaciándose al
reconectar.

### `O` · `observabilidad/`
El contrato de privacidad del monitoreo: las pruebas de los dos lados y la
medición de cuánto pesa el paquete con y sin claves. Criterios en
`docs/OBSERVABILIDAD.md`.

### `EVT` · `eventos/`
El visor de la bitácora con los tres niveles que pide el punto 30, el filtro
por nivel y el detalle con su contexto. `EVT-04` es el acta: qué pide la
rúbrica, qué había antes —la tabla existía y no había forma de verla—, los
ejemplos reales de cada nivel y cómo reproducirlo. En las capturas se ve que
el depurador tapa un correo en la propia pantalla.

### `CI` · `CI-CD/`
Historial de ejecuciones, los checks de un PR y —a propósito— también las
ejecuciones **fallidas**: una tubería que solo enseña verdes no demuestra que
sirva para algo.

### `I` · `interacciones/`
Aviso de interacción en el carrito con su cobertura declarada, y el PR 29.

### `K` · `kpis/`
Panel de indicadores con datos reales y su esqueleto de carga ocupando el mismo
hueco, con la medición de estabilidad visual en `K03`.

### `M` · `modal-producto/`
Los botones visibles a 1280×720, y el marcador de imagen antes y después.

### `figura11/`
La Figura 11 con el recuento real, la original para comparar, y un `LEEME.md`
que explica por qué el APF3 no se tocó. Nombres descriptivos y no numerados:
son tres archivos que se citan por lo que son.

### `DEP` · `DESPLIEGUE/`
Despliegues en Vercel y el endpoint de salud respondiendo en producción.

### `CIE` · `cierre/`
Verificación de cada cierre de bloque: suites, tipos, compilación y lo que
quedó pendiente.

---

## Lo que se renombró, y lo que no

**Se renombró** lo que chocaba o no tenía código: `perfil/` (`E##` → `PER-##`),
`correos/` (`C##` → `COR-##`), `cierre/` (`C##` → `CIE-##`), `E2E-offline/`
(sin prefijo → `OFF-##`), `cortes/` y `soa/` (nombres libres → `CRT-##`,
`SOA-##`), y un archivo suelto en `kpis/` → `K03`.

**No se renombró** nada que estuviera citado desde fuera: los `E1`–`E10` de la
raíz y los `E11`–`E25` del APF3, porque `docs/EVIDENCIAS-APF2.md` los nombra uno
a uno y el APF3 entregado cita ese índice. Tampoco las carpetas cuyo código ya
era único —`R`, `K`, `M`, `O`, `I`, `UI`, `CI`, `DEP`—, porque renombrarlas
habría sido ruido sin ganancia.

Todo se movió con `git mv`, así que el historial de cada archivo sigue
completo.
