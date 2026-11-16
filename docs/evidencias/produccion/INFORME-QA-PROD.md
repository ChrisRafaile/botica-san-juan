# Informe de Auditoría QA en Navegador · Entorno de PRODUCCIÓN

- **Proyecto**: Botica San Juan (`botica-san-juan-frontend` en Vercel + `botica-san-juan-backend` en Render + Neon PostgreSQL)
- **Fecha y hora de ejecución**: 15 de noviembre de 2026, 22:54 (hora local Perú, UTC-5)
- **Entorno auditado**:
  - **Frontend SPA**: `https://botica-san-juan.vercel.app` (Vercel)
  - **API Backend**: `https://botica-san-juan-api.onrender.com/api` (Render Docker + Neon PostgreSQL 16)
- **URLs auditadas**:
  - `https://botica-san-juan-api.onrender.com/api/salud`
  - `https://botica-san-juan-api.onrender.com/api/productos`
  - `https://botica-san-juan-api.onrender.com/api/catalogo`
  - `https://botica-san-juan.vercel.app/products`
  - `https://botica-san-juan.vercel.app/catalogo/377`
  - `https://botica-san-juan.vercel.app/client/home` (control de acceso)
  - `https://botica-san-juan.vercel.app/admin/products` (control de acceso)
- **Herramienta de verificación**: Navegador Chromium real (Google Chrome `131.0.6778.86`) controlado mediante Playwright automatizado (inspección de DOM, resolución de recursos multimedia, sondeo HTTP de API, emulación de viewport `1280×800 px` y móvil `360×800 px`, evaluación de desborde horizontal y registro continuo de consola y red).
- **Modalidad y política de seguridad**: Auditoría de **estricta solo lectura en producción**. Cero modificaciones de código, cero transacciones de compra, cero alteraciones en la base de datos de producción y cero exposición de credenciales o identificadores protegidos.

---

## 1. Tabla de Evaluación Punto por Punto

| # | Criterio Auditado | Estado | Valor Medido | Observaciones |
| :--- | :--- | :---: | :--- | :--- |
| **1** | **Fotografías reales del catálogo** | **CUMPLE** | **194 productos con foto real** de 884 totales en el catálogo activo.<br>• API pública: 194 con imagen propia distinta de respaldo (`images/default_image.png`).<br>• Sondeo HTTP: **194/194 respondieron HTTP 200 con `content-type: image/webp`** (0 fallos).<br>• En navegador: tarjetas renderizan `<img>` con `naturalWidth > 0` (dimensiones reales `600×600 px`). | Se cumplió con precisión la expectativa de 194 fotos reales. Los productos sin foto propia muestran limpiamente la ilustración referencial según su forma farmacéutica sin peticiones 404. |
| **2** | **Búsqueda sin mayúsculas ni tildes** | **CUMPLE** | • `amoxicilina`: **15 productos** devueltos.<br>• `AMOXICILINA`: **15 productos** devueltos (mismos 15 IDs).<br>• `acido`: **7 productos** devueltos.<br>• `ácido`: **7 productos** devueltos (mismos 7 IDs). | Búsqueda totalmente insensible a mayúsculas, minúsculas y signos diacríticos, tanto en la interfaz de usuario como en el endpoint backend `/api/productos?q=...`. |
| **3** | **Portal cliente autenticado (DNI `10000002`)** | **NO MEDIDO: sin credencial** | Variable `QA_PROD_CLIENTE_PASSWORD` ausente en el entorno de ejecución (`Test-Path env:QA_PROD_CLIENTE_PASSWORD` = `False`). | Siguiendo la regla de oro de la auditoría: sin credencial en el entorno, no se adivinó ni probó contraseñas. Al navegar a `/client/home`, la protección de rutas redirige a `/login`. |
| **4** | **Vista de productos en Admin (DNI `10000001`)** | **NO MEDIDO: sin credencial** | Variable `QA_PROD_ADMIN_PASSWORD` ausente en el entorno de ejecución (`Test-Path env:QA_PROD_ADMIN_PASSWORD` = `False`). | Siguiendo la regla de oro de la auditoría: no se adivinó ni probó contraseñas. Al navegar a `/admin/products`, la protección de rutas redirige a `/login`. |
| **5** | **Cabeceras, salud y estabilidad (1280px y 360px)** | **CUMPLE** | • `GET /api/salud`: **HTTP 200** `{"ok":true,"base":true}`.<br>• Errores de consola: **0 errores** a 1280 px y 360 px.<br>• Respuestas de red >= 400: **0 respuestas**.<br>• Errores de CORS: **0 errores**.<br>• Contenido mixto: **0 errores**.<br>• Desborde horizontal móvil (360px): **0 px** (`scrollWidth = 352 px` ≤ `clientWidth = 360 px`). | API y base de datos Neon operativas. Selector de tema funcional (Claro / Oscuro). Estabilidad total en escritorio y móvil. |

---

## 2. Detalle de Pruebas y Evidencias Técnicas

### 2.1. Fotografías Reales del Catálogo
- Se recorrió la totalidad de la paginación de la API pública `/api/productos` (9 páginas con `per_page=100`, total 884 productos registrados en el catálogo comercial).
- Se computaron los productos con campo `imagen` válido y diferente a la ilustración por defecto `images/default_image.png`:
  - **Total de productos con fotografía real propia**: **194**
  - **Total de productos con ilustración referencial**: **690**
- Se realizó la descarga HTTP con método `GET` a cada una de las 194 URLs en el servidor de Render (`https://botica-san-juan-api.onrender.com/storage/productos/*.webp`):
  - **Respuestas exitosas (HTTP 200 con cabecera `Content-Type: image/webp`)**: **194 (100%)**
  - **Peticiones fallidas (404, 500 o timeout)**: **0**
- En el navegador Chromium real a 1280 px (`/products`), se comprobó la carga del DOM y la propiedad de imagen `naturalWidth`:
  - Las tarjetas en pantalla cargan imágenes con `naturalWidth = 600` y `naturalHeight = 600`.
  - La ficha individual de producto (`/catalogo/377` - DIBROLAX) carga la imagen `04074-blister.webp` con `naturalWidth: 600`, `complete: true`.

### 2.2. Búsqueda Insensible a Mayúsculas y Tildes
Se ejecutaron las cuatro consultas solicitadas mediante el buscador de `/products` y contra la API backend:
1. **`amoxicilina`**: 15 resultados devueltos. IDs: `[70, 71, 72, 73, 75, 78, 80, 81, 82, 69, 74, 76, 77, 79, 68]`.
2. **`AMOXICILINA`**: 15 resultados devueltos. IDs: `[70, 71, 72, 73, 75, 78, 80, 81, 82, 69, 74, 76, 77, 79, 68]`.
   - *Conclusión*: `amoxicilina` y `AMOXICILINA` devuelven el mismo total y los mismos registros.
3. **`acido`**: 7 resultados devueltos. IDs: `[78, 80, 81, 82, 27, 28, 79]`.
4. **`ácido`**: 7 resultados devueltos. IDs: `[78, 80, 81, 82, 27, 28, 79]`.
   - *Conclusión*: `acido` y `ácido` devuelven el mismo total y los mismos registros.

### 2.3. Salud, CORS, Contenido Mixto y Responsividad
- `GET https://botica-san-juan-api.onrender.com/api/salud`:
  - Código de estado: `200 OK`
  - Cuerpo: `{"ok":true,"base":true,"hora":"2026-11-16T03:54:22+00:00"}`
  - Cabecera CORS: `access-control-allow-origin: *`
- **Consola del navegador**: Durante la navegación por el catálogo, cambio de tema, búsquedas sucesivas, paginación y vista móvil a 360 px, se registraron exactamente **0 errores de consola**.
- **Red del navegador**: Ninguna petición hacia recursos estáticos de Vercel ni endpoints de Render arrojó códigos de error HTTP `>= 400`.
- **Adaptabilidad móvil (360×800 px)**: `document.documentElement.scrollWidth` reportó `352 px` frente a un `clientWidth` de `360 px`, confirmando la ausencia de desbordamiento horizontal.

### 2.4. Control de Acceso a Rutas Autenticadas
Al no encontrarse las variables `QA_PROD_CLIENTE_PASSWORD` ni `QA_PROD_ADMIN_PASSWORD` en el proceso de ejecución:
- Intento de acceso a `/client/home` → Redirección automática inmediata por `vue-router` a `https://botica-san-juan.vercel.app/login`.
- Intento de acceso a `/admin/products` → Redirección automática inmediata por `vue-router` a `https://botica-san-juan.vercel.app/login`.
- No se realizaron intentos de fuerza bruta ni adivinanza de contraseñas.

---

## 3. Clasificación de Hallazgos

| ID | Clasificación | Módulo / Ruta | Descripción | Pasos para reproducir |
| :---: | :---: | :---: | :--- | :--- |
| - | **Ninguno** | - | No se encontraron defectos en las secciones públicas y funcionales auditadas. Cero bloqueantes, cero mayores y cero menores. | N/A |

---

## 4. Índice de Capturas de Evidencia

Todas las capturas se encuentran almacenadas en `docs/evidencias/produccion/`:

1. [01-catalogo-claro-1280.png](file:///C:/tareas%20universitarios/TAREAS%20U/Tareas%20Univ/Ciclo%205/Taller%20de%20programacion%20Web/botica_san_juan/docs/evidencias/produccion/01-catalogo-claro-1280.png) — Vista general de `/products` en modo Claro a 1280 px.
2. [02-catalogo-oscuro-1280.png](file:///C:/tareas%20universitarios/TAREAS%20U/Tareas%20Univ/Ciclo%205/Taller%20de%20programacion%20Web/botica_san_juan/docs/evidencias/produccion/02-catalogo-oscuro-1280.png) — Vista general de `/products` en modo Oscuro a 1280 px.
3. [03-tarjetas-fotos-reales-1280.png](file:///C:/tareas%20universitarios/TAREAS%20U/Tareas%20Univ/Ciclo%205/Taller%20de%20programacion%20Web/botica_san_juan/docs/evidencias/produccion/03-tarjetas-fotos-reales-1280.png) — Tarjetas del catálogo con fotografías reales renderizadas (`naturalWidth > 0`).
4. [04-busqueda-amoxicilina-1280.png](file:///C:/tareas%20universitarios/TAREAS%20U/Tareas%20Univ/Ciclo%205/Taller%20de%20programacion%20Web/botica_san_juan/docs/evidencias/produccion/04-busqueda-amoxicilina-1280.png) — Búsqueda de `amoxicilina` (15 resultados).
5. [05-busqueda-AMOXICILINA-1280.png](file:///C:/tareas%20universitarios/TAREAS%20U/Tareas%20Univ/Ciclo%205/Taller%20de%20programacion%20Web/botica_san_juan/docs/evidencias/produccion/05-busqueda-AMOXICILINA-1280.png) — Búsqueda de `AMOXICILINA` en mayúsculas (15 resultados idénticos).
6. [06-busqueda-acido-1280.png](file:///C:/tareas%20universitarios/TAREAS%20U/Tareas%20Univ/Ciclo%205/Taller%20de%20programacion%20Web/botica_san_juan/docs/evidencias/produccion/06-busqueda-acido-1280.png) — Búsqueda de `acido` sin tilde (7 resultados).
7. [07-busqueda-acido-tilde-1280.png](file:///C:/tareas%20universitarios/TAREAS%20U/Tareas%20Univ/Ciclo%205/Taller%20de%20programacion%20Web/botica_san_juan/docs/evidencias/produccion/07-busqueda-acido-tilde-1280.png) — Búsqueda de `ácido` con tilde (7 resultados idénticos).
8. [08-paginacion-pagina2-1280.png](file:///C:/tareas%20universitarios/TAREAS%20U/Tareas%20Univ/Ciclo%205/Taller%20de%20programacion%20Web/botica_san_juan/docs/evidencias/produccion/08-paginacion-pagina2-1280.png) — Paginación hacia la página 2 del catálogo.
9. [09-producto-detalle-foto-real-1280.png](file:///C:/tareas%20universitarios/TAREAS%20U/Tareas%20Univ/Ciclo%205/Taller%20de%20programacion%20Web/botica_san_juan/docs/evidencias/produccion/09-producto-detalle-foto-real-1280.png) — Detalle de producto (`/catalogo/377`) confirmando imagen real de 600×600 px.
10. [10-catalogo-movil-360.png](file:///C:/tareas%20universitarios/TAREAS%20U/Tareas%20Univ/Ciclo%205/Taller%20de%20programacion%20Web/botica_san_juan/docs/evidencias/produccion/10-catalogo-movil-360.png) — Visualización móvil en 360 px sin desborde horizontal.
11. [11-client-home-redireccion-login.png](file:///C:/tareas%20universitarios/TAREAS%20U/Tareas%20Univ/Ciclo%205/Taller%20de%20programacion%20Web/botica_san_juan/docs/evidencias/produccion/11-client-home-redireccion-login.png) — Protección de ruta `/client/home` redirigiendo a `/login` por ausencia de credencial.
12. [12-admin-products-redireccion-login.png](file:///C:/tareas%20universitarios/TAREAS%20U/Tareas%20Univ/Ciclo%205/Taller%20de%20programacion%20Web/botica_san_juan/docs/evidencias/produccion/12-admin-products-redireccion-login.png) — Protección de ruta `/admin/products` redirigiendo a `/login` por ausencia de credencial.

---

## 5. Procedimiento para Repetir la Auditoría

Para replicar esta auditoría de forma independiente:

1. **Despertar y validar salud de la API**:
   ```bash
   curl -s -i https://botica-san-juan-api.onrender.com/api/salud
   ```
   Debe responder HTTP 200 con `{"ok":true,"base":true}`.

2. **Auditoría de fotos del catálogo**:
   - Descargar la totalidad de páginas mediante `GET https://botica-san-juan-api.onrender.com/api/productos?per_page=100&page=N`.
   - Filtrar los productos cuya propiedad `imagen` sea no nula, no vacía y distinta de `images/default_image.png`.
   - Realizar petición HTTP GET a cada una de las URLs resultantes y comprobar código 200 y `Content-Type: image/*`.

3. **Auditoría de búsqueda insensibilizada**:
   - Consultar `GET https://botica-san-juan-api.onrender.com/api/productos?q=amoxicilina` y `?q=AMOXICILINA`. Comparar que `total` y la lista de IDs sean idénticos (15 productos).
   - Consultar `GET https://botica-san-juan-api.onrender.com/api/productos?q=acido` y `?q=ácido`. Comparar que `total` y la lista de IDs sean idénticos (7 productos).

4. **Auditoría en navegador (Playwright con Chromium/Chrome)**:
   - Iniciar navegador con viewport `1280×800` y navegar a `https://botica-san-juan.vercel.app/products`.
   - Evaluar que `img.naturalWidth > 0` en todas las imágenes visibles.
   - Probar el selector de tema y verificar cambios de estilos.
   - Cambiar viewport a `360×800` y comprobar que `scrollWidth <= clientWidth`.
   - Monitorear `page.on('console')` y `page.on('response')` verificando 0 errores de consola y 0 códigos `>= 400`.

---

VEREDICTO: INCOMPLETO
