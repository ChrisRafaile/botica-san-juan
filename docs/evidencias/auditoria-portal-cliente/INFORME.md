# Informe de Auditoría QA y Diseño — Portal del Cliente, Flujo de Compra e Imágenes

- **Proyecto**: Botica San Juan (`botica-san-juan-frontend` + `botica-san-juan-backend`)
- **Fecha de ejecución**: 15 de noviembre de 2026
- **Entorno auditado**:
  - Frontend SPA (Vue 3 + Vite + Pinia + Tailwind CSS v4): `http://localhost:5173`
  - Backend API (Laravel 12 + Sanctum + PostgreSQL): `http://127.0.0.1:8083`
- **Herramienta de verificación**: Navegador Chromium real controlado mediante Playwright MCP (inspección de DOM, estilos calculados, `PerformanceObserver` para CLS, emulación de `prefers-color-scheme`, pruebas de teclado y red).
- **Modalidad**: Auditoría estrictamente de lectura sobre el código fuente (cero archivos de código modificados, cero commits, cero secretos expuestos).

---

## 1. Procedimiento seguido para reproducir la auditoría

1. **Verificación previa de servicios y resolución de cuentas de demostración**:
   - Se comprobó que el frontend (`http://localhost:5173`) y el backend (`http://127.0.0.1:8083`) respondían con HTTP `200`.
   - Se consultaron los seeders en `botica-san-juan-backend/database/seeders/UsuarioSeeder.php` y `PedidoSeeder.php` para identificar las cuentas de prueba de **Cliente** (`Cliente Prueba`, `rol = cliente`, `id = 3`; y cuenta secundaria `id = 1` para pruebas cruzadas de autorización) y de **Administrador** (`Administrador`, `rol = administrador`, `id = 2`), sin registrar cuentas nuevas ni exponer credenciales en este informe ni en las capturas.

2. **Recorrido completo como Cliente (`Cliente Prueba`)**:
   - **Paso 1 — Catálogo público (`/products`)**:
     - Verificación del selector de tema en sus tres estados (`Claro`, `Oscuro`, `Automático`) y persistencia en `localStorage` (`botica:tema`) tras recargar.
     - Emulación en vivo de `prefers-color-scheme: light` y `prefers-color-scheme: dark` sin recargar la página.
     - Comprobación del orden por defecto (`orden=destacados`, 884 productos totales) verificando que las 24 tarjetas de la primera página corresponden a productos con stock vendible FEFO (`stock_disponible > 0`) y fotografía real (`storage/productos/*.webp`, `600×600 px`, atributos `width="400" height="400"`).
     - Pruebas de búsqueda insensible a mayúsculas y tildes:
       - `amoxicilina` vs `AMOXICILINA` → ambos devuelven exactamente 15 resultados con los mismos IDs (`[69, 74, 76, 77, 835, 79, 68, 70, 71, 73, 75, 78, 80, 81, 82]`).
       - `acido` vs `ácido` → ambos devuelven exactamente 7 resultados con los mismos IDs (`[27, 28, 79, 78, 80, 81, 82]`).
   - **Paso 2 — Selección y carrito (`/products` → `/cart`)**:
     - Adición de productos disponibles al carrito (`AB-BRONCOL NF 600` y `A C SAL 1000`), revisión de líneas, subtotales, desglose de IGV e imágenes en `/cart`, y apertura del modal «Continuar al checkout».
   - **Paso 3 — Checkout e ida y vuelta (`/cart` ↔ `/checkout/pago` ↔ `/products`)**:
     - Confirmación de modalidad de entrega («Retiro en tienda») y pulsación de «Ir a pagar» para ingresar a `/checkout/pago?pedido=222`.
     - Verificación de los enlaces de salida en `/checkout/pago`: «← Volver al carrito» (`/cart`), «Seguir comprando» (`/products`) e «Ir a mi portal» (`/client/home`).
     - Prueba de retorno a `/cart` para editar productos y adición de un producto más desde `/products` para evaluar la reversibilidad y continuidad del pedido pendiente.
   - **Paso 4 — Portal del cliente (`/client/home`)**:
     - Verificación de métricas (KPIs), «Recomendados y más buscados», «Pedidos en curso», «Últimos comprobantes», botón «Agregar» (con actualización inmediata del contador `[data-testid="contador-carrito-cliente"]`), colapso/expansión de barra lateral (`botica:cliente:lateral-colapsada`), campana de pagos pendientes y cajón móvil (`360 px`) con trampa de foco (`Tab` / `Shift+Tab`) y cierre con `Escape`.
   - **Paso 5 — Historial de pedidos (`/client/pedidos`)**:
     - Filtrado por estado (`Todos`, `Pendientes`, `Confirmados`, `Entregados`, `Anulados`), despliegue de detalle de productos del pedido, verificación de fotografías en líneas de pedido, enlace «Reanudar pago» y botón «Volver a pedir».
   - **Paso 6 — Comprobantes electrónicos (`/client/comprobantes`)**:
     - Búsqueda y filtrado por tipo (`Todos`, `Boletas`, `Facturas`), apertura del modal «Vista previa» (y cierre con `Escape`), y verificación de descarga autenticada de PDF (`GET /api/facturacion/documentos/44/pdf` → `HTTP 200`, `application/pdf`, `30 091 bytes`).
   - **Paso 7 — Perfil y preferencias (`/client/perfil` y redirección `/profile`)**:
     - Comprobación de redirección automática `/profile` → `/client/perfil`.
     - Edición y guardado de `direccion_entrega` (`Av. Las Flores de Primavera 456, San Juan de Lurigancho`) y recarga completa para confirmar su persistencia desde el backend.
     - Prueba del `SelectorTema` en modo segmentado (`Claro`, `Oscuro`, `Automático`) y del diálogo de confirmación de cierre de sesión (`¿Cerrar sesión?`).

3. **Pruebas de seguridad y control de acceso (IDOR y rol Administrador)**:
   - Con sesión activa de `Cliente Prueba` (`usuario_id = 3`), se intentó acceder por navegador y por API a rutas de administración (`/admin/home`, `/admin/products`, `/admin/inventory`, `/admin/orders`, `GET /api/tablero`, `GET /api/pedidos`, `GET /api/facturacion/documentos`, `POST /api/productos`) y a recursos de otro cliente (`usuario_id = 1`: `/checkout/pago?pedido=32`, `GET /api/pedidos/32/mi-pedido`, `GET /api/pedidos/usuario/1`, `GET /api/comprobantes/39`, `GET /api/facturacion/documentos/39/pdf`).

4. **Recorrido como Administrador (`Administrador`)**:
   - Inicio de sesión como administrador y auditoría de `/admin/products`, modal `ProductModal` («Editar Producto» sobre un producto con fotografía real `AB-BRONCOL NF 600`), `/admin/inventory` y `/admin/orders` (incluyendo el modal de detalle del pedido `#222`), en los tres temas (`Claro`, `Oscuro`, `Automático`) y los tres anchos (`360 px`, `768 px`, `1280 px`).

---

## 2. Tabla de hallazgos

| ID | Severidad | Pantalla / Ruta | Modo de tema | Ancho | Pasos para reproducir | Resultado esperado | Resultado obtenido | Captura de referencia |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **H-01** | **bloqueante** | `/cart` ↔ `/checkout/pago` ↔ `/products` | Claro / Oscuro / Automático | `360 px`, `768 px`, `1280 px` | 1. Agregar 2 productos en `/products` e ir a `/cart`.<br>2. Pulsar «Continuar al checkout» → «Ir a pagar» (entra a `/checkout/pago?pedido=222`).<br>3. Pulsar «← Volver al carrito» para editar productos, o ir a «Seguir comprando» (`/products`), agregar 1 producto adicional y volver a `/cart`. | Poder volver de `/checkout/pago` a `/cart` conservando los productos del pedido en curso para editarlos, o agregar un producto más en `/products` y regresar al pago integrándolo al pedido pendiente sin perder el estado ni duplicar pedidos. El enlace debe indicar «Volver al carrito / editar productos». | `carrito.confirmar()` ejecuta `POST /api/pedidos/confirmar` e invoca inmediatamente `vaciar()` (`src/stores/carrito.ts#L323`). Al volver de `/checkout/pago` a `/cart`, el carrito aparece vacío («Tu carrito está vacío») y sin botón para reanudar el pago del pedido `#222`. Al agregar otro producto en `/products`, `/cart` contiene solo el producto nuevo desconectado de `#222`; pulsar «Ir a pagar» crea un **segundo pedido duplicado** y descuenta stock otra vez. | `13-checkout-pago-claro-1280.png`, `16-checkout-volver-carrito-vacio.png`, `17-cart-producto-adicional-desconectado.png` |
| **H-02** | **mayor** | `/client/home` y `/client/pedidos` | Claro / Oscuro / Automático | `360 px`, `768 px`, `1280 px` | 1. Abrir `/client/home` e inspeccionar las tarjetas de «Recomendados y más buscados» (incluyendo `AB-BRONCOL NF 600`, que tiene foto `storage/productos/00141.webp`).<br>2. Ir a `/client/pedidos`, abrir el detalle del pedido `#222` e inspeccionar las miniaturas de sus productos. | `ImagenConRespaldo` debe mostrar la fotografía real (`<img>`) de cada producto que tenga imagen válida y usar el icono SVG solo como respaldo. Además, los recomendados de `/client/home` deben priorizar productos con foto real. | `ClientHomeView.vue#L315` y `ClientPedidosView.vue#L386` pasan `:src="..."` en vez de `:ruta="..."` a `<ImagenConRespaldo>` (cuya prop se llama `ruta`). Como `props.ruta` queda `undefined`, `mostrarImagen` es siempre `false` y `src` cae como atributo inválido en `<div src="storage/productos/00141.webp">`. Se pintan **0 etiquetas `<img>`** en ambas vistas. Además, `GET /api/catalogo/destacados` ordena por `nombre` en vez de `ordenDestacado()`. | `18-client-home-claro-1280.png`, `23-client-pedidos-claro-1280.png` |
| **H-03** | **mayor** | `/cart` (con productos) | Claro / Oscuro / Automático | `360 px` | 1. Agregar productos al carrito (`AB-BRONCOL NF 600` y `A C SAL 1000`).<br>2. Abrir `/cart` con ancho de ventana `360 × 800 px`. | `scrollWidth <= clientWidth` (`360 px`), sin barra horizontal ni elementos cortados. | `scrollWidth = 495 px` vs `clientWidth = 360 px` (desborde horizontal de `135 px`). En `CartView.vue#L168-L170`, `<ul class="space-y-4">` es hijo directo de un contenedor `grid` sin `min-w-0`; los textos con `truncate` (`white-space: nowrap`) fuerzan el ancho de las tarjetas a `479 px`, cortando el botón de eliminar y los precios fuera de la pantalla móvil. | `11-cart-responsivo-360.png` |
| **H-04** | **mayor** | `/admin/products` y `ProductModal` | Claro / Oscuro / Automático | `360 px` | 1. Iniciar sesión como administrador y abrir `/admin/products` a `360 × 800 px`. | `scrollWidth <= clientWidth` (`360 px`), con los botones superiores adaptados al ancho móvil. | `scrollWidth = 430 px` vs `clientWidth = 360 px` (desborde horizontal de `70 px`). En `AdminProductsView.vue#L3-L30`, `<div class="flex justify-between items-center mb-6">` y `<div class="flex gap-3">` (`Carga Masiva` + `Nuevo Producto`) no tienen `flex-wrap`, desbordando el botón «Nuevo Producto» hasta `right = 430 px`. | `36-admin-products-responsivo-360.png`, `39-admin-product-modal-responsivo-360.png` |
| **H-05** | **mayor** | Cabecera pública (`Header.vue`) en `/products` y `/cart` | Claro / Oscuro / Automático | `768 px` | 1. Con sesión de cliente iniciada, abrir `/products` o `/cart` a `768 × 900 px`. | Todos los controles de la barra superior (`SelectorTema`, carrito y menú de usuario) deben ser visibles dentro de los `768 px`. | En `768 px` (`md:`) se muestran los 6 enlaces de navegación junto al logo y las acciones de usuario, alcanzando `right = 854 px`. Como `<header>` es `fixed top-0 w-full`, el icono de carrito (`right = 790 px`) queda parcialmente cortado y el avatar/menú del cliente (`right = 842 px`) queda oculto fuera del borde derecho. | `07-products-responsivo-768.png` |
| **H-06** | **mayor** | `/checkout/pago` | Automático (`sistema`) | `360 px`, `768 px`, `1280 px` | 1. Fijar `botica:tema = 'sistema'`.<br>2. Cargar directamente `/checkout/pago?pedido=221` con `prefers-color-scheme: light`.<br>3. Cambiar `prefers-color-scheme` a `dark` en vivo sin recargar. | La vista debe contar con selector de tema y reaccionar en vivo al cambio de `prefers-color-scheme` sin necesidad de recargar la página. | `CheckoutPagoView.vue` no incluye `<SelectorTema />` ni invoca `useTheme()`. El listener de `matchMedia('(prefers-color-scheme: dark)')` no se registra en esta ruta, por lo que al cambiar el esquema del sistema en vivo la interfaz permanece en modo claro (`darkAfter = false`) hasta recargar. | `13-checkout-pago-claro-1280.png`, `14-checkout-pago-oscuro-1280.png` |
| **H-07** | **mayor** | `/client/home` y `/admin/products` | Claro / Oscuro / Automático | `1280 px` | 1. Medir el Cumulative Layout Shift (CLS) con `PerformanceObserver` durante la carga inicial de `/client/home` y `/admin/products`. | `CLS ≤ 0.015` en todas las vistas evaluadas (`/products`, `/client/home`, `/admin/products`, `/admin/inventory`). | En `/client/home` el CLS es **`0.01723`** (> `0.015`) porque el esqueleto reserva 4 tarjetas (1 fila) y al cargar se pintan 8 recomendados (2 filas), empujando la sección inferior. En `/admin/products` el CLS es **`0.02366`** (> `0.015`) porque el aviso `<p v-if="umbralesSospechosos">` se inserta sin espacio reservado y desplaza la tabla `90.25 px` hacia abajo. | `18-client-home-claro-1280.png`, `34-admin-products-claro-1280.png` |
| **H-08** | **mayor** | `/login` y `/register` | Claro / Oscuro / Automático | `360 px`, `768 px`, `1280 px` | 1. Abrir `/login` con la consola del navegador y el monitor de red activos. | Cero errores en consola (`console.error`) y cero respuestas HTTP de error (`status >= 400`) al cargar la pantalla de acceso. | `AuthView.vue` monta 2 instancias de `<BotonGoogle />` (acceso y registro); cada una dispara `GET /api/auth/google/redirect` en `onMounted`, recibiendo `503 Service Unavailable` y registrando 4 errores en la consola del navegador en cada visita a `/login`. | `18-client-home-claro-1280.png` |
| **H-09** | **menor** | `Footer.vue` (`/products`, `/cart`) y tarjetas diferidas en `/products` | Claro / Oscuro | `360 px`, `768 px`, `1280 px` | 1. Medir el contraste de `<text fill="white">plin</text>` sobre `#00D4B8` en `Footer.vue`.<br>2. En `/products`, alternar en vivo de `Claro` a `Oscuro` sin hacer scroll y medir el contraste de las tarjetas 9–24 (`.tarjeta-diferida`). | Contraste mínimo `4.5:1` en texto normal y actualización inmediata en todas las tarjetas. | La insignia SVG `plin` del pie de página tiene contraste **`1.89:1`** (`#FFFFFF` sobre `#00D4B8`, < `4.5:1`). En `/products`, las tarjetas fuera de pantalla con `content-visibility: auto` mantienen el color calculado del tema anterior (`1.09:1`) hasta que entran en el viewport al hacer scroll. | `01-products-claro-1280.png`, `02-products-oscuro-1280.png` |
| **H-10** | **menor** | `Header.vue`, `ClientLayout.vue`, `BoticaAvatar.vue` | Claro / Oscuro / Automático | `360 px`, `768 px`, `1280 px` | 1. Inspeccionar los atributos HTML `width` y `height` de las etiquetas `<img>` del logo y de los avatares de usuario. | Todas las etiquetas `<img>` deben incluir atributos explícitos `width` y `height` (además de sus clases CSS). | Los `<img>` del logotipo (`logo botica.jpg`) en `Header.vue` y `ClientLayout.vue` y las fotos de perfil en `BoticaAvatar.vue` tienen `width = null` y `height = null`. | `01-products-claro-1280.png`, `30-client-perfil-claro-1280.png` |
| **H-11** | **menor** | `/admin/*` (`AdminTopbar.vue`) | Claro / Oscuro / Automático | `360 px` | 1. Abrir `/admin/products` a `360 × 800 px` e intentar elegir el tema `Sistema` (`Automático`) en la barra superior. | Poder elegir cualquiera de los tres modos (`Claro`, `Oscuro`, `Sistema`) también en móvil. | En `< 640 px` (`sm:hidden`, `AdminTopbar.vue#L178-L189`), el selector de 3 botones se oculta y se reemplaza por un botón binario que solo alterna `claro ↔ oscuro`, impidiendo seleccionar `sistema`. | `36-admin-products-responsivo-360.png` |
| **H-12** | **menor** | `/admin/products` y `/admin/inventory` | Claro / Oscuro / Automático | `360 px`, `768 px`, `1280 px` | 1. Abrir `/admin/products` o `/admin/inventory` en la página 1 sin filtros aplicados. | Visualizar productos con sus fotografías reales desde la primera página o disponer de un orden consistente con el catálogo. | A diferencia de `/products` (`orden=destacados`), ambas tablas consultan `/api/productos` sin priorizar registros con fotografía, por lo que las 10 filas iniciales corresponden a productos sin imagen propia y muestran solo el SVG de respaldo hasta filtrar o buscar. | `34-admin-products-claro-1280.png`, `40-admin-inventory-claro-1280.png` |

---

## 3. Resumen de evaluación por criterio de aceptación

| # | Criterio de aceptación | Estado | Evidencia / Observaciones |
| :--- | :--- | :--- | :--- |
| **1** | **Flujo de compra reversible (`/products` → `/cart` → `/checkout/pago`)** | **NO CUMPLE** | **H-01 (`bloqueante`)**: `carrito.confirmar()` vacía el carrito apenas crea el pedido. Volver desde `/checkout/pago` a `/cart` muestra un carrito vacío, no permite editar el pedido pendiente ni ofrece enlace para retomar el pago, y agregar un producto más genera un pedido duplicado independiente. |
| **2** | **Portal del cliente completo (`/client/home`, `/client/pedidos`, `/client/comprobantes`, `/client/perfil`)** | **PARCIAL (con fallos mayores)** | La navegación lateral colapsable (`botica:cliente:lateral-colapsada`), el cajón móvil con foco atrapado y cierre con `Escape`, la campana de pagos pendientes, el filtro de pedidos, el botón «Volver a pedir», la vista previa y descarga autenticada de PDF (`HTTP 200`, `application/pdf`), la redirección `/profile` → `/client/perfil`, la persistencia de `direccion_entrega` y el diálogo de confirmación de salida funcionan. Sin embargo, fallan las fotos de productos en `/client/home` y `/client/pedidos` (**H-02**) y el CLS en `/client/home` (**H-07**). |
| **3** | **Sistema de temas en tres estados (`Claro`, `Oscuro`, `Automático`)** | **PARCIAL (con fallos mayores)** | Funciona y persiste en `localStorage` (`botica:tema`) en `/products`, `/cart`, `/client/*` y `/admin/*` (escritorio). Falla en `/checkout/pago` (**H-06**: no tiene `SelectorTema` ni reacciona en vivo a `prefers-color-scheme`) y en `/admin/*` a `360 px` (**H-11**: oculta la opción `Sistema`). |
| **4** | **Contraste WCAG AA (4.5:1 texto normal, 3:1 texto grande y bordes)** | **PARCIAL (con fallos menores)** | Los tokens semánticos cumplen WCAG AA en claro y oscuro en las vistas principales. Se detectaron dos desviaciones puntuales (**H-09**): insignia SVG `plin` en `Footer.vue` (`1.89:1`) y color calculado obsoleto (`1.09:1`) en tarjetas diferidas (`content-visibility: auto`) de `/products` al conmutar el tema en vivo sin hacer scroll. |
| **5** | **Responsivo (`360 px`, `768 px`, `1280 px`) y CLS (`≤ 0.015`)** | **NO CUMPLE** | Desborde horizontal en `/cart` con productos a `360 px` (`495 px > 360 px`, **H-03**), desborde horizontal en `/admin/products` a `360 px` (`430 px > 360 px`, **H-04**), recorte de acciones de usuario en `Header.vue` a `768 px` (`right = 854 px > 768 px`, **H-05**), y CLS por encima de `0.015` en `/client/home` (`0.01723`) y `/admin/products` (`0.02366`, **H-07**). En `/products` (`0.0001`) y `/admin/inventory` (`0.0000`) el CLS sí cumple. |
| **6** | **Imágenes de productos (sin rotas, dimensión reservada, prioridad en `/products`)** | **NO CUMPLE** | En `/products`, `/cart`, `ProductModal` y el modal de detalle de `/admin/orders` las imágenes reales (`storage/productos/*.webp`) cargan con `naturalWidth > 0`. Sin embargo, en `/client/home` y `/client/pedidos` **nunca se muestra ninguna foto de producto** (**H-02**, `:src` en vez de `:ruta` en `ImagenConRespaldo`), y los logos/avatares carecen de atributos `width`/`height` (**H-10**). |
| **7** | **Consola y red limpias** | **NO CUMPLE** | Al entrar a `/login` o `/register`, dos instancias de `<BotonGoogle />` disparan `GET /api/auth/google/redirect`, que responde `503 Service Unavailable` y registra 4 errores en la consola del navegador (**H-08**). |
| **8** | **Seguridad básica (aislamiento de cliente y protección de rutas Admin)** | **CUMPLE** | Verificado empíricamente:<br>• Navegar como cliente a `/admin/home`, `/admin/products`, `/admin/inventory` o `/admin/orders` redirige a `/`.<br>• Llamar como cliente a `GET /api/tablero`, `GET /api/pedidos`, `GET /api/facturacion/documentos` o `POST /api/productos` devuelve `HTTP 403`.<br>• Navegar a `/checkout/pago?pedido=32` (pedido de otro usuario) recibe `HTTP 403` en `GET /api/pedidos/32/mi-pedido` y redirige a `/`.<br>• Llamar a `GET /api/pedidos/usuario/1`, `GET /api/comprobantes/39` o `GET /api/facturacion/documentos/39/pdf` (recursos de otro usuario) devuelve `HTTP 403`. |

---

## 4. Índice de capturas de evidencia (`docs/evidencias/auditoria-portal-cliente/`)

1. `01-products-claro-1280.png` — Catálogo `/products` en tema Claro (`1280 px`), primeros 24 productos disponibles con fotografía real.
2. `02-products-oscuro-1280.png` — Catálogo `/products` en tema Oscuro (`1280 px`).
3. `03-products-auto-light-1280.png` — Catálogo `/products` en modo Automático con `prefers-color-scheme: light`.
4. `04-products-auto-dark-1280.png` — Catálogo `/products` en modo Automático con `prefers-color-scheme: dark` (cambio en vivo sin recargar).
5. `05-products-busqueda-amoxicilina-1280.png` — Búsqueda `amoxicilina` / `AMOXICILINA` (15 resultados idénticos).
6. `06-products-busqueda-acido-1280.png` — Búsqueda `acido` / `ácido` insensible a tildes (7 resultados idénticos).
7. `07-products-responsivo-768.png` — Catálogo `/products` a `768 px`.
8. `08-products-responsivo-360.png` — Catálogo `/products` a `360 px`.
9. `09-cart-claro-1280.png` — Carrito `/cart` con productos en tema Claro (`1280 px`).
10. `10-cart-oscuro-1280.png` — Carrito `/cart` con productos en tema Oscuro (`1280 px`).
11. `11-cart-responsivo-360.png` — Carrito `/cart` con productos a `360 px` (evidencia del desborde horizontal **H-03**).
12. `12-cart-modal-checkout-1280.png` — Modal de confirmación de datos y entrega en `/cart` (`1280 px`).
13. `13-checkout-pago-claro-1280.png` — Pasarela `/checkout/pago?pedido=222` en tema Claro (`1280 px`).
14. `14-checkout-pago-oscuro-1280.png` — Pasarela `/checkout/pago?pedido=222` en tema Oscuro (`1280 px`).
15. `15-checkout-pago-responsivo-360.png` — Pasarela `/checkout/pago?pedido=222` a `360 px`.
16. `16-checkout-volver-carrito-vacio.png` — Evidencia **H-01**: al pulsar «← Volver al carrito» desde `/checkout/pago`, `/cart` aparece vacío y sin enlace para retomar el pago.
17. `17-cart-producto-adicional-desconectado.png` — Evidencia **H-01**: al agregar un producto más desde `/products` y volver a `/cart`, solo figura el producto nuevo desconectado del pedido pendiente `#222`.
18. `18-client-home-claro-1280.png` — Portal `/client/home` en tema Claro (`1280 px`; evidencia **H-02** de ausencia de fotos en recomendados).
19. `19-client-home-oscuro-1280.png` — Portal `/client/home` en tema Oscuro (`1280 px`).
20. `20-client-home-colapsada-1280.png` — Portal `/client/home` con barra lateral colapsada (`80 px`).
21. `21-client-home-responsivo-360.png` — Portal `/client/home` a `360 px`.
22. `22-client-home-cajon-movil-360.png` — Cajón de navegación móvil abierto en `/client/home` a `360 px`.
23. `23-client-pedidos-claro-1280.png` — Vista `/client/pedidos` en tema Claro (`1280 px`; evidencia **H-02** en detalle de productos del pedido).
24. `24-client-pedidos-oscuro-1280.png` — Vista `/client/pedidos` en tema Oscuro (`1280 px`).
25. `25-client-pedidos-responsivo-360.png` — Vista `/client/pedidos` a `360 px`.
26. `26-client-comprobantes-claro-1280.png` — Vista `/client/comprobantes` en tema Claro (`1280 px`).
27. `27-client-comprobantes-oscuro-1280.png` — Vista `/client/comprobantes` en tema Oscuro (`1280 px`).
28. `28-client-comprobantes-vista-previa-1280.png` — Modal «Vista previa» de comprobante electrónico en `/client/comprobantes` (`1280 px`).
29. `29-client-comprobantes-responsivo-360.png` — Vista `/client/comprobantes` a `360 px`.
30. `30-client-perfil-claro-1280.png` — Vista `/client/perfil` en tema Claro (`1280 px`).
31. `31-client-perfil-oscuro-1280.png` — Vista `/client/perfil` en tema Oscuro (`1280 px`).
32. `32-client-perfil-responsivo-360.png` — Vista `/client/perfil` a `360 px`.
33. `33-client-dialogo-cerrar-sesion-1280.png` — Diálogo de confirmación `¿Cerrar sesión?` en el portal del cliente (`1280 px`).
34. `34-admin-products-claro-1280.png` — Vista `/admin/products` en tema Claro (`1280 px`).
35. `35-admin-products-oscuro-1280.png` — Vista `/admin/products` en tema Oscuro (`1280 px`).
36. `36-admin-products-responsivo-360.png` — Vista `/admin/products` a `360 px` (evidencia del desborde horizontal **H-04**).
37. `37-admin-product-modal-claro-1280.png` — Modal `ProductModal` («Editar Producto» con foto `AB-BRONCOL NF 600`) en tema Claro (`1280 px`).
38. `38-admin-product-modal-oscuro-1280.png` — Modal `ProductModal` en tema Oscuro (`1280 px`).
39. `39-admin-product-modal-responsivo-360.png` — Modal `ProductModal` a `360 px`.
40. `40-admin-inventory-claro-1280.png` — Vista `/admin/inventory` en tema Claro (`1280 px`).
41. `41-admin-inventory-oscuro-1280.png` — Vista `/admin/inventory` en tema Oscuro (`1280 px`).
42. `42-admin-inventory-responsivo-360.png` — Vista `/admin/inventory` a `360 px`.
43. `43-admin-orders-claro-1280.png` — Vista `/admin/orders` en tema Claro (`1280 px`).
44. `44-admin-orders-detalle-modal-1280.png` — Modal de detalle de pedido `#222` en `/admin/orders` mostrando la miniatura del producto (`1280 px`).
45. `45-admin-orders-oscuro-1280.png` — Vista `/admin/orders` en tema Oscuro (`1280 px`).
46. `46-admin-orders-responsivo-360.png` — Vista `/admin/orders` a `360 px`.

---

## 5. Veredicto final

**Veredicto: NO APTO para desplegar a producción**

El sistema presenta **1 hallazgo bloqueante (H-01)** y **7 hallazgos mayores (H-02 a H-08)** que incumplen criterios de aceptación obligatorios del encargo:
1. El flujo de compra entre `/cart`, `/checkout/pago` y `/products` **no es reversible** (**H-01**): iniciar el pago vacía el carrito de inmediato, dejando al usuario sin posibilidad de editar los productos de su pedido en `/cart` y provocando la creación de pedidos duplicados si agrega un producto más desde `/products`.
2. Las fotografías de productos **nunca se renderizan** en `/client/home` («Recomendados y más buscados») ni en el detalle de pedidos de `/client/pedidos` (**H-02**) por el uso de `:src` en lugar de `:ruta` sobre `ImagenConRespaldo.vue`.
3. Existen desbordes horizontales y recortes de controles en `/cart` a `360 px` (**H-03**), `/admin/products` a `360 px` (**H-04**) y `Header.vue` a `768 px` (**H-05**), además de CLS superior a `0.015` en `/client/home` y `/admin/products` (**H-07**), ausencia de soporte en vivo para el tema `Automático` en `/checkout/pago` (**H-06**) y errores HTTP `503` en consola al cargar `/login` (**H-08**).

---

## 6. Re-verificación (commit `c9d5d06` — rama `fix/auditoria-portal-cliente`)

Se ejecutó una re-verificación en navegador Chromium real (solo lectura) sobre los mismos servidores tras aplicarse el commit `c9d5d06`, repitiendo las mediciones exactas para los hallazgos **H-01** a **H-08**.

### 6.1. Tabla de re-verificación (`H-01` a `H-08`)

| Hallazgo | Severidad original | Estado | Medición y verificación en navegador real | Captura de referencia |
| :--- | :--- | :--- | :--- | :--- |
| **H-01** | **bloqueante** | **CORREGIDO** | 1. Se agregaron 2 productos en `/products` y se confirmó el pedido en `/cart`, navegando a `/checkout/pago?pedido=223`.<br>2. En `/checkout/pago` se muestra el aviso `[data-testid="aviso-pedido-registrado"]`: *«El pedido #223 ya está registrado y te espera aquí: puedes salir y retomar el pago desde tu carrito o desde "Mis pedidos". Lo que agregues ahora al carrito irá en un pedido aparte.»*<br>3. Al pulsar «← Ver mi carrito» (`/cart`), con el carrito vacío se muestra arriba la sección `[data-testid="pedidos-pendientes-carrito"]` (*«Tienes 3 pedidos esperando pago. Ya están registrados y no se pierden. Lo que tengas ahora en el carrito irá en un pedido aparte.»*) listando `Pedido #223 · S/ 240.00` y su botón **«Reanudar pago»**.<br>4. Al pulsar «Reanudar pago» en `#223`, navega de vuelta al mismo pedido (`/checkout/pago?pedido=223`).<br>5. Al pulsar «Seguir comprando» (`/products`), añadir otro producto y regresar a `/cart`, la sección `[data-testid="pedidos-pendientes-carrito"]` con el aviso y «Reanudar pago» sigue visible encima del producto agregado (`cartItemsCount = 1`). | `50-re-verificacion-checkout-pago-claro-1280.png`, `52-re-verificacion-cart-vacio-pedidos-pendientes-1280.png`, `53-re-verificacion-cart-con-producto-y-aviso-pendiente-1280.png` |
| **H-02** | **mayor** | **CORREGIDO** | • En `/client/home` («Recomendados y más buscados»), `ClientHomeView.vue` pasa `:ruta="producto.imagen"` a `<ImagenConRespaldo>` y `CatalogoPublicoController::destacados` aplica `ordenDestacado()`. Se renderizan **8 etiquetas `<img>` reales** (`/storage/productos/00141.webp`, `/storage/productos/12590.webp`, `/storage/productos/07421.webp`, `/storage/productos/30916.webp`, etc.), todas con `complete = true` y `naturalWidth = 600` (`naturalHeight = 600`).<br>• En `/client/pedidos`, `ClientPedidosView.vue` pasa `:ruta="item.producto.imagen"` en las líneas de detalle del pedido, pintando **3 etiquetas `<img>` reales** en los pedidos desplegados con `complete = true` y `naturalWidth = 600`. | `54-re-verificacion-client-home-fotos-1280.png`, `55-re-verificacion-client-pedidos-fotos-1280.png` |
| **H-03** | **mayor** | **CORREGIDO** | En `/cart` con productos a `360 × 800 px`, `<ul class="min-w-0 space-y-4">` encoge dentro del contenedor grid: **`scrollWidth = 352 px <= clientWidth = 360 px`** (`overflowPx = -8 px`, sin barra horizontal), y las tarjetas quedan en `left = 16 px`, `right = 336 px` (`width = 320 px`). | `49-re-verificacion-cart-360.png` |
| **H-04** | **mayor** | **CORREGIDO** | En `/admin/products` a `360 × 800 px`, la cabecera superior y los botones `Carga Masiva` (`right = 192 px`) y `Nuevo Producto` (`right = 209 px`) usan `flex-wrap`: **`scrollWidth = 352 px <= clientWidth = 360 px`** (`overflowPx = -8 px`, sin desborde horizontal). | `57-re-verificacion-admin-products-360.png` |
| **H-05** | **mayor** | **CORREGIDO** | En `/products` a `768 × 900 px` con sesión de cliente activa, `Header.vue` oculta los enlaces centrales hasta `lg:flex`: **`scrollWidth = 760 px <= clientWidth = 768 px`** y todos los controles de la barra caben sin recorte (`maxInteractiveRight = 736 px <= 768 px`: `SelectorTema` en `512–548 px`, carrito en `560–600 px`, avatar en `612–684 px`, menú móvil en `696–736 px`). | `48-re-verificacion-header-768.png` |
| **H-06** | **mayor** | **CORREGIDO** | `CheckoutPagoView.vue` incluye `<SelectorTema />` en la barra superior (`hasSelectorTema = true`) y `App.vue` inicializa `useTheme()` globalmente. Con `botica:tema = 'sistema'` en `/checkout/pago?pedido=223`, al conmutar en vivo `prefers-color-scheme` de `light` a `dark` sin recargar la página, `document.documentElement.classList.contains('dark')` cambia inmediatamente de `false` a `true`. | `50-re-verificacion-checkout-pago-claro-1280.png`, `51-re-verificacion-checkout-pago-auto-dark-1280.png` |
| **H-07** | **mayor** | **PARCIAL** | • **`/client/home` (`1280 px`) — CORREGIDO**: el esqueleto reserva ahora 8 tarjetas (`v-for="n in 8"`), obteniendo **`CLS = 0.0000`** (`≤ 0.015`).<br>• **`/admin/products` (`1280 px`) — PARCIAL**: `AdminProductsView.vue` persiste `umbralesSospechosos` en `localStorage['botica:admin:umbrales-sospechosos']`. A partir de la **segunda visita / recarga** (`claveUmbrales = "1"`), el CLS baja a **`0.0000`** (`≤ 0.015`). Sin embargo, en la **primera visita con almacenamiento limpio** (cuando la clave aún no existe), `leerUmbralesRecordados()` inicia en `false` y, al resolver `GET /api/productos/resumen`, el aviso `<p v-if="umbralesSospechosos">` se inserta desplazando la tabla y produciendo **`CLS = 0.02366`** (`> 0.015`). | `54-re-verificacion-client-home-fotos-1280.png`, `56-re-verificacion-admin-products-cls-1280.png` |
| **H-08** | **mayor** | **PERSISTE** | Al abrir `/login` con almacenamiento limpio se siguen disparando **2 peticiones** `GET /api/auth/google/redirect` que devuelven **`503 Service Unavailable`** y generan **4 errores en consola** (`2 × Failed to load resource: 503` + `2 × 🔴 [ERROR]: Error HTTP {status: 503}`).<br>**Causa raíz**: en `src/components/BotonGoogle.vue#L40-L50`, `let sondeo: Promise<boolean> | null = null` se declaró dentro de `<script setup lang="ts">`. En Vue 3, `<script setup>` se compila dentro de `setup()` por cada instancia, no en ámbito de módulo; por ello las 2 instancias de `<BotonGoogle />` montadas en `AuthView.vue` tienen variables `sondeo` separadas y disparan ambas peticiones. Además, `src/services/api.ts` sigue ejecutando `logger.error('Error HTTP', ...)` en respuestas `503` aunque lleven la cabecera `X-Silenciar-Aviso: 1`. | `47-re-verificacion-login-consola-limpia.png` |

### 6.2. Nuevas capturas de la re-verificación (`docs/evidencias/auditoria-portal-cliente/`)

47. `47-re-verificacion-login-consola-limpia.png` — Vista `/login` durante la re-verificación de **H-08** (`1280 px`).
48. `48-re-verificacion-header-768.png` — Cabecera `Header.vue` en `/products` a `768 px` con sesión de cliente activa (**H-05 corregido**, `maxInteractiveRight = 736 px`).
49. `49-re-verificacion-cart-360.png` — Vista `/cart` con productos a `360 px` (**H-03 corregido**, `scrollWidth = 352 px <= 360 px`).
50. `50-re-verificacion-checkout-pago-claro-1280.png` — Pasarela `/checkout/pago?pedido=223` en tema Claro con `<SelectorTema />` y aviso `[data-testid="aviso-pedido-registrado"]` (**H-01** y **H-06 corregidos**).
51. `51-re-verificacion-checkout-pago-auto-dark-1280.png` — Pasarela `/checkout/pago?pedido=223` en tema `Automático` tras conmutar `prefers-color-scheme: dark` en vivo sin recargar (**H-06 corregido**).
52. `52-re-verificacion-cart-vacio-pedidos-pendientes-1280.png` — Vista `/cart` vacía tras volver de `/checkout/pago`, mostrando `[data-testid="pedidos-pendientes-carrito"]` y botón «Reanudar pago» para `#223` (**H-01 corregido**).
53. `53-re-verificacion-cart-con-producto-y-aviso-pendiente-1280.png` — Vista `/cart` tras agregar un producto adicional desde `/products`, conservando visible el bloque de pedidos pendientes con «Reanudar pago» (**H-01 corregido**).
54. `54-re-verificacion-client-home-fotos-1280.png` — Vista `/client/home` mostrando las 8 fotografías reales `<img>` en «Recomendados y más buscados» y `CLS = 0.0000` (**H-02** y **H-07 en `/client/home` corregidos**).
55. `55-re-verificacion-client-pedidos-fotos-1280.png` — Vista `/client/pedidos` con el detalle de pedido desplegado mostrando las miniaturas reales `<img>` (**H-02 corregido**).
56. `56-re-verificacion-admin-products-cls-1280.png` — Vista `/admin/products` a `1280 px` durante la medición de CLS (**H-07 parcial**).
57. `57-re-verificacion-admin-products-360.png` — Vista `/admin/products` a `360 px` sin desborde horizontal (**H-04 corregido**, `scrollWidth = 352 px <= 360 px`).

### 6.3. Regresiones nuevas detectadas

- **Ninguna regresión nueva detectada** en los flujos re-evaluados (`/products`, `/cart`, `/checkout/pago`, `/client/home`, `/client/pedidos`, `/admin/products`). Incluso con el bloque nuevo `[data-testid="pedidos-pendientes-carrito"]` más productos en `/cart` a `360 px`, el ancho total se mantiene en `scrollWidth = 352 px <= 360 px`.

### 6.4. Veredicto final actualizado

**Veredicto actualizado: NO APTO para desplegar a producción**

Aunque el commit `c9d5d06` **resolvió por completo el único hallazgo bloqueante (H-01)** y **5 de los 7 hallazgos mayores (H-02, H-03, H-04, H-05 y H-06)** además del CLS en `/client/home`, la aplicación aún no alcanza la condición de *cero hallazgos mayores* exigida en `AUDITORIA-ARGON.md` por dos puntos puntuales pendientes:
1. **H-08 (`mayor`, PERSISTE)**: En `/login` se siguen realizando 2 peticiones `GET /api/auth/google/redirect` con respuesta `503 Service Unavailable` y 4 errores en consola porque `let sondeo` está declarado dentro de `<script setup>` en `src/components/BotonGoogle.vue` (ámbito por instancia y no de módulo) y porque el cliente HTTP registra `logger.error` en respuestas `503`.
2. **H-07 (`mayor`, PARCIAL)**: Mientras que en `/client/home` (`CLS = 0.0000`) y en visitas recurrentes a `/admin/products` (`CLS = 0.0000`) el salto quedó corregido, en la **primera visita en frío** a `/admin/products` (sin `botica:admin:umbrales-sospechosos` en `localStorage`) el aviso de umbrales sigue insertándose tras la carga con `CLS = 0.02366` (`> 0.015`).

---

## 7. Segunda ronda de correcciones (rama `fix/auditoria-portal-cliente`)

Tras la re-verificación de la sección 6 quedaban dos hallazgos mayores. Esta
sección la escribe el orquestador y no el auditor: la cuota del agente de QA se
agotó antes de poder repetir la pasada, así que se distingue con cuidado qué se
midió en navegador y qué no.

| Hallazgo | Estado | Corrección | Cómo se comprobó |
| :--- | :--- | :--- | :--- |
| **H-08** | **CORREGIDO, medido en navegador** | El sondeo dejó de pedir la URL de ida. Hay un punto propio, `GET /api/auth/google/disponible`, que responde `200` con `{ disponible: bool }`: no genera `state`, no gasta el cupo de `throttle:acceso-google` y no devuelve 503 cuando faltan credenciales. La respuesta se recuerda a nivel de módulo en `services/google.ts` (la variable anterior estaba dentro de `<script setup>`, que se ejecuta una vez por instancia). | Chrome real, contexto limpio, `/login` a 1280 px: **1 petición** (`GET /api/auth/google/disponible -> 200`), **0 respuestas >= 400**, **0 errores de consola**. Captura `58-re-verificacion-login-sondeo-unico-1280.png`. Prueba de backend `test_el_sondeo_responde_200_tanto_si_hay_credenciales_como_si_no`. |
| **H-07** (`/admin/products`, primera visita) | **CORREGIDO en código, SIN medir en navegador** | Cuando el navegador no recuerda todavía el valor, el párrafo del aviso se pinta desde el primer fotograma con `visibility: hidden`, ocupando su sitio. Si el resumen confirma el aviso, se hace visible donde ya estaba; si no, queda un hueco en blanco solo en esa visita. | Tres pruebas en `ImagenesPanelAdmin.spec.ts` (hueco reservado antes de la respuesta, pintado inmediato con valor recordado, nada reservado si se recuerda que no hace falta). **Falta repetir la medición de CLS en frío**: requiere sesión de administrador y no se obtuvo en esta ronda. |

### Cómo repetir la medición pendiente

1. Iniciar sesión como administrador y borrar `botica:admin:umbrales-sospechosos`
   de `localStorage`.
2. Cargar `/admin/products` a 1280 px con un `PerformanceObserver` de
   `layout-shift` (descartando entradas con `hadRecentInput`).
3. El valor esperado es `CLS <= 0,015`; antes de la corrección era `0,02366`.

### Estado tras las dos rondas

- Bloqueante **H-01**: corregido y verificado en navegador (sección 6).
- Mayores **H-02 a H-06** y **H-08**: corregidos y verificados en navegador.
- Mayor **H-07**: `/client/home` verificado (`CLS = 0,0000`); `/admin/products`
  en primera visita corregido y cubierto por pruebas, pendiente de medición.
- Menores **H-09 a H-12**: sin tocar; quedan como deuda anotada.

Suites sobre esta rama: backend 440 pruebas (1325 aserciones), frontend 196
pruebas, `vue-tsc` y `vite build` sin errores.