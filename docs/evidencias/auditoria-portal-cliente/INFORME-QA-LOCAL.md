# Informe de Auditoría QA en Navegador — Entorno Local

- **Proyecto**: Botica San Juan (`botica-san-juan-frontend` + `botica-san-juan-backend`)
- **Fecha de ejecución**: 15 de noviembre de 2026
- **Commit auditado**: `3873add`
- **Entorno auditado**:
  - Frontend SPA (Vue 3 + Vite + Pinia + Tailwind CSS v4): `http://localhost:5173`
  - Backend API (Laravel 12 + Sanctum + PostgreSQL): `http://127.0.0.1:8083`
- **Herramienta de verificación**: Navegador Chromium real automatizado mediante Playwright MCP (inspección de DOM, estilos calculados, eventos de teclado y foco, `PerformanceObserver` para medición de CLS, emulación en vivo de `prefers-color-scheme`, monitoreo de consola y red).
- **Modalidad y política de confidencialidad**: Auditoría técnica estrictamente de lectura sobre el código fuente. Cero líneas de código modificadas, cero commits, cero cambios de rama. No se exponen contraseñas, tokens de autenticación ni el RUC del emisor electrónico.

---

## 1. Procedimiento para reproducir la auditoría

1. **Precondiciones**:
   - Backend activo en `http://127.0.0.1:8083` respondiendo a `GET /api/salud` (`HTTP 200`).
   - Frontend activo en `http://localhost:5173` (Vite dev server con proxy reverso a `/api`).
   - Cuentas de demostración locales disponibles según seeders (`UsuarioSeeder.php`):
     - **Cliente**: DNI `87654321` (usuario ID `3`, `Cliente Prueba`).
     - **Administrador**: DNI `12345678` (usuario ID `2`, `Administrador`).
     - *Nota*: La autenticación se realizó exclusivamente a través del formulario oficial `/login`, emulando la interacción de un usuario real, sin inyección de tokens vía tinker ni manipulación de base de datos.

2. **Ejecución de pruebas por punto**:
   - **Punto 1 (Portal Cliente)**: Inicio de sesión con cuenta de cliente. Navegación secuencial por `/client/home`, `/client/pedidos`, `/client/comprobantes` y `/client/perfil` en viewports de 1280×800 px y 360×800 px. Inspección de propiedades DOM de imágenes (`naturalWidth`, `naturalHeight`, `complete`, `src`), cotejo de contadores contra APIs REST (`/api/pedidos/mi-resumen`, `/api/comprobantes/mios`), prueba de colapso de barra lateral en escritorio (80 px y clave `botica:cliente:lateral-colapsada`) y verificación del cajón móvil (apertura, trampa de foco y cierre con tecla Escape).
   - **Punto 2 (Selector de Tema)**: Evaluación en `/products`, `/client/home`, `/checkout/pago` y `/admin/products`. En cada ruta se seleccionó el modo `Automático` (`sistema`), configurando `botica:tema = 'sistema'`. Se emuló en vivo `prefers-color-scheme: light` y `prefers-color-scheme: dark` sin recargar la página, comprobando la alternancia inmediata de la clase `.dark` en `<html>` y el cambio de variables de color de fondo y texto.
   - **Punto 3 (Flujo Reversible)**: Desde `/products`, selección y adición al carrito del producto `AB-BRONCOL NF 600`. Navegación a `/cart`, apertura del modal «Continuar al checkout», confirmación de modalidad de entrega («Retiro en tienda») e ingreso a `/checkout/pago?pedido=...`. Clic en `← Ver mi carrito` retornando a `/cart`. Comprobación del banner de pedidos pendientes con el botón «Reanudar pago». Regreso a `/products`, adición de un segundo producto disponible, retorno a `/cart` y verificación de coexistencia del borrador actual junto al historial de pedidos pendientes de pago.
   - **Punto 4 (Miniaturas en Admin)**: Inicio de sesión con cuenta de administrador. Acceso a `/admin/products`, apertura del modal de edición sobre `AB-BRONCOL NF 600` (`ProductModal`), navegación a `/admin/inventory` y `/admin/orders` (incluyendo modal de detalle de pedido). Comprobación de que las miniaturas son etiquetas `<img>` con imágenes reales cargadas (`naturalWidth: 600 px`) y visualización responsiva a 1280 px y 360 px sin desbordes.
   - **Punto 5 (Medición CLS en frío `/admin/products`)**: Con sesión de administrador, eliminación de la clave `botica:admin:umbrales-sospechosos` de `localStorage`. Registro previo a la navegación de un `PerformanceObserver` para entradas `layout-shift` (descartando `hadRecentInput`). Navegación en frío a `http://localhost:5173/admin/products` a 1280×800 px, espera hasta reposo de red/DOM y cálculo del CLS acumulado. Ejecución de 3 mediciones en frío y 1 medición en caliente.

---

## 2. Tabla de evaluación punto por punto

| # | Punto auditado | Viewport | Estado | Valor medido / Evidencia | Capturas de referencia |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **1** | **Portal cliente completo**<br>• `/client/home`<br>• `/client/pedidos`<br>• `/client/comprobantes`<br>• `/client/perfil` | `1280 px`<br>`360 px` | **CUMPLE** | • **Fotografías**: `AB-BRONCOL NF 600` carga etiqueta `<img>` con `naturalWidth: 600 px`, `complete: true`, `0` fallbacks visuales.<br>• **Contadores y KPIs**: Coinciden exactamente con `/api/pedidos/mi-resumen` (4 pendientes, 1 entregado, S/ 54.50 gastado) y `/api/comprobantes/mios` (1 boleta emitida).<br>• **Barra lateral (1280 px)**: Se colapsa de 256 px a 80 px al pulsar botón, persistiendo `botica:cliente:lateral-colapsada = '1'`.<br>• **Cajón móvil (360 px)**: Se despliega al pulsar menú hamburguesa, atrapa el foco de teclado y se cierra con `Escape`.<br>• **Desborde horizontal**: `scrollWidth = 352 px <= 360 px` en todas las pantallas móviles.<br>• **Consola y Red**: 0 errores en consola, 0 respuestas HTTP >= 400. | `59`, `60`, `61`, `62`, `63`, `64`, `65`, `66`, `67`, `68`, `69`, `70` |
| **2** | **Selector de tema Claro / Oscuro / Automático**<br>• `/products`<br>• `/client/home`<br>• `/checkout/pago`<br>• `/admin/products` | `1280 px` | **CUMPLE** | • En modo `Automático` (`sistema`), la interfaz conmuta **en vivo sin recargar** al alternar `prefers-color-scheme`:<br>  - `light`: `html.classList` sin `.dark`, fondo claro `oklch(0.968...)`.<br>  - `dark`: `html.classList` contiene `.dark`, fondo oscuro `oklch(0.145...)`.<br>• Verificado dinámicamente en las 4 rutas requeridas (incluyendo `/checkout/pago` y `/admin/products`). | `71`, `72`, `73`, `74`, `75`, `76`, `77`, `78` |
| **3** | **Flujo reversible**<br>`/products` ↔ `/cart` ↔ `/checkout/pago` | `1280 px`<br>`360 px` | **CUMPLE** | • Al confirmar pedido y llegar a `/checkout/pago?pedido=224`, el enlace `← Ver mi carrito` permite retornar a `/cart`.<br>• En `/cart` se visualiza el banner «Tienes pedidos esperando pago» con lista de pedidos pendientes (#224, #223, etc.) y botón directo «Reanudar pago».<br>• Al volver a `/products` y agregar un nuevo producto, este se añade al borrador del carrito coexistiendo limpiamente con los pedidos pendientes sin duplicaciones erróneas.<br>• Sin desborde horizontal a 360 px (`scrollWidth = 352 px`). | `79`, `80`, `81`, `82`, `83`, `84`, `85` |
| **4** | **Miniaturas en Admin**<br>• `/admin/products`<br>• `ProductModal`<br>• `/admin/inventory`<br>• `/admin/orders` y detalle | `1280 px`<br>`360 px` | **CUMPLE** | • Las miniaturas de productos que poseen foto (`AB-BRONCOL NF 600`) renderizan etiquetas `<img>` reales con `naturalWidth: 600 px` y `naturalHeight: 600 px`.<br>• En `ProductModal` («Editar Producto»), la previsualización carga la imagen real responsiva sin deformación.<br>• En `/admin/inventory`, la tabla (1280 px) y tarjetas (360 px) muestran la imagen real.<br>• En `/admin/orders` (detalle de pedido), las líneas de productos muestran miniaturas reales cargadas.<br>• Cero desborde a 360 px (`scrollWidth = 352 px`). | `86`, `87`, `88`, `89`, `90`, `91`, `92`, `93`, `94` |
| **5** | **Medición CLS en frío `/admin/products`**<br>(Revisión H-07 de auditoría previa) | `1280 px` | **CUMPLE** | • Procedimiento con `PerformanceObserver` antes de navegación, sin `hadRecentInput` y limpiando `botica:admin:umbrales-sospechosos`:<br>  - **Frío 1**: `CLS = 0,0000`<br>  - **Frío 2**: `CLS = 0,0000`<br>  - **Frío 3**: `CLS = 0,0000`<br>  - **Caliente**: `CLS = 0,0000`<br>• Umbral requerido: `<= 0,015` (todos los valores cumplen holgadamente). | `95` |

---

## 3. Estado de los hallazgos de la auditoría anterior (H-01 a H-08)

| ID anterior | Severidad previa | Descripción original | Estado actual | Verificación en esta auditoría |
| :--- | :--- | :--- | :--- | :--- |
| **H-01** | `bloqueante` | Carrito se vaciaba al confirmar pedido y no permitía retomar el pago ni coexistir con nuevos productos. | **RESUELTO** | `/cart` muestra la sección de pedidos pendientes con botón «Reanudar pago» a `/checkout/pago?pedido=...`. Nuevos productos coexisten como borrador independiente. |
| **H-02** | `mayor` | Ausencia de fotografías reales en `/client/home` y `/client/pedidos` por discrepancia de prop (`:src` vs `:ruta`). | **RESUELTO** | Las imágenes se renderizan mediante `<img>` con `naturalWidth: 600 px` y carga exitosa (`complete: true`). |
| **H-03** | `mayor` | Desborde horizontal en `/cart` a 360 px (`scrollWidth = 495 px`). | **RESUELTO** | `scrollWidth = 352 px` a 360 px de viewport. Tarjetas y textos truncan correctamente sin forzar scroll horizontal. |
| **H-04** | `mayor` | Desborde horizontal en `/admin/products` y `ProductModal` a 360 px (`scrollWidth = 430 px`). | **RESUELTO** | `scrollWidth = 352 px` a 360 px de viewport. Botones de cabecera y campos de formulario se adaptan a columnas móviles. |
| **H-05** | `mayor` | Recorte de controles de usuario en cabecera pública a 768 px (`right = 854 px`). | **RESUELTO** | Barra superior adaptada a la cuadrícula responsive sin desborde ni recorte de acciones. |
| **H-06** | `mayor` | `/checkout/pago` no tenía selector de tema ni reaccionaba a `prefers-color-scheme` en vivo. | **RESUELTO** | El tema dinámico reacciona en vivo en `/checkout/pago` alternando fondo y clases `.dark` sin recargar. |
| **H-07** | `mayor` | CLS en `/admin/products` era `0,02366` (> `0,015`) por inserción de alerta de umbrales sospechosos sin espacio reservado. | **RESUELTO** | CLS medido en frío y caliente es `0,0000`, cumpliendo holgadamente el umbral `<= 0,015`. |
| **H-08** | `mayor` | Error 503 en `/login` al consultar `GET /api/auth/google/redirect` generando 4 errores en consola. | **RESUELTO** | Sonda `GET /api/auth/google/disponible` responde `HTTP 200` con `{ "disponible": false }`. Cero errores en consola al cargar `/login`. |

---

## 4. Clasificación de hallazgos en la presente auditoría

### Hallazgos Bloqueantes (`0`)
*No se encontraron hallazgos bloqueantes.*

### Hallazgos Mayores (`0`)
*No se encontraron hallazgos mayores.*

### Hallazgos Menores / Deuda Técnica (`4`)
1. **H-09 (menor)**: Insignia SVG plin en `Footer.vue` con ratio de contraste `1.89:1` sobre fondo cian. En `/products`, tarjetas diferidas (`content-visibility: auto`) conservan estilo previo hasta entrar al viewport si se alterna tema sin hacer scroll.
2. **H-10 (menor)**: Logotipo y avatares de usuario no declaran atributos explícitos `width` y `height` en el marcado HTML (gestionados exclusivamente por CSS).
3. **H-11 (menor)**: En `/admin/*` con viewport móvil (`< 640 px`), la barra superior oculta el selector segmentado de 3 botones y ofrece un botón binario sol/luna que alterna únicamente Claro/Oscuro sin permitir seleccionar «Sistema» directamente en esa vista.
4. **H-12 (menor)**: En `/admin/products` y `/admin/inventory`, la paginación inicial por defecto lista productos por ID sin priorizar aquellos que cuentan con fotografía, mostrando iconos de respaldo en las primeras filas hasta que se aplica una búsqueda o filtro.

---

## 5. Índice de capturas de evidencia (`docs/evidencias/auditoria-portal-cliente/`)

| Archivo | Descripción | Viewport |
| :--- | :--- | :--- |
| `59-qa-local-client-home-1280.png` | Portal cliente `/client/home` con KPIs, pedidos y productos recomendados con foto real | 1280×800 |
| `60-qa-local-client-home-360.png` | Portal cliente `/client/home` en vista móvil sin desborde horizontal | 360×800 |
| `61-qa-local-client-home-lateral-colapsada-1280.png` | Portal cliente con barra lateral colapsada a 80 px (`botica:cliente:lateral-colapsada`) | 1280×800 |
| `62-qa-local-client-home-cajon-movil-360.png` | Cajón de navegación móvil abierto con trampa de foco accesible | 360×800 |
| `63-qa-local-client-pedidos-1280.png` | Historial `/client/pedidos` con filtros y tarjetas de pedidos | 1280×800 |
| `64-qa-local-client-pedidos-360.png` | Historial `/client/pedidos` en vista móvil | 360×800 |
| `65-qa-local-client-pedidos-detalle-1280.png` | Detalle desplegado de pedido mostrando miniatura de producto real cargada | 1280×800 |
| `66-qa-local-client-comprobantes-1280.png` | Listado `/client/comprobantes` con boletas electrónicas | 1280×800 |
| `67-qa-local-client-comprobantes-360.png` | Listado `/client/comprobantes` en vista móvil | 360×800 |
| `68-qa-local-client-comprobantes-modal-1280.png` | Modal de vista previa de comprobante electrónico emitido | 1280×800 |
| `69-qa-local-client-perfil-1280.png` | Formulario `/client/perfil` con datos del cliente y selector de tema | 1280×800 |
| `70-qa-local-client-perfil-360.png` | Formulario `/client/perfil` en vista móvil | 360×800 |
| `71-qa-local-tema-products-auto-light-1280.png` | Catálogo `/products` en modo Automático con `prefers-color-scheme: light` en vivo | 1280×800 |
| `72-qa-local-tema-products-auto-dark-1280.png` | Catálogo `/products` en modo Automático con `prefers-color-scheme: dark` en vivo | 1280×800 |
| `73-qa-local-tema-client-home-auto-light-1280.png` | Portal `/client/home` en modo Automático (`prefers-color-scheme: light`) | 1280×800 |
| `74-qa-local-tema-client-home-auto-dark-1280.png` | Portal `/client/home` en modo Automático (`prefers-color-scheme: dark`) | 1280×800 |
| `75-qa-local-tema-checkout-pago-auto-light-1280.png` | Pasarela `/checkout/pago` en modo Automático (`prefers-color-scheme: light`) | 1280×800 |
| `76-qa-local-tema-checkout-pago-auto-dark-1280.png` | Pasarela `/checkout/pago` en modo Automático (`prefers-color-scheme: dark`) | 1280×800 |
| `77-qa-local-tema-admin-products-auto-light-1280.png` | Panel `/admin/products` en modo Automático (`prefers-color-scheme: light`) | 1280×800 |
| `78-qa-local-tema-admin-products-auto-dark-1280.png` | Panel `/admin/products` en modo Automático (`prefers-color-scheme: dark`) | 1280×800 |
| `79-qa-local-flujo-products-agregar-1280.png` | Catálogo `/products` agregando producto `AB-BRONCOL NF 600` | 1280×800 |
| `80-qa-local-flujo-cart-con-producto-1280.png` | Carrito `/cart` con producto y desglose de montos | 1280×800 |
| `81-qa-local-flujo-checkout-pago-1280.png` | Pasarela `/checkout/pago?pedido=224` confirmada con enlace para volver | 1280×800 |
| `82-qa-local-flujo-cart-volver-pedido-pendiente-1280.png` | Carrito tras volver del pago mostrando banner de pedidos pendientes y botón de reanudar | 1280×800 |
| `83-qa-local-flujo-cart-producto-adicional-1280.png` | Carrito con producto adicional nuevo coexistiendo con pedidos pendientes | 1280×800 |
| `84-qa-local-flujo-cart-360.png` | Carrito `/cart` a 360 px de ancho sin desborde horizontal | 360×800 |
| `85-qa-local-flujo-checkout-pago-360.png` | Pasarela `/checkout/pago` a 360 px sin desborde horizontal | 360×800 |
| `86-qa-local-admin-products-miniaturas-1280.png` | Panel `/admin/products` con miniatura real cargada de `AB-BRONCOL NF 600` | 1280×800 |
| `87-qa-local-admin-products-360.png` | Panel `/admin/products` en móvil a 360 px sin desborde | 360×800 |
| `88-qa-local-admin-product-modal-1280.png` | Modal de edición de producto (`ProductModal`) con previsualización de foto real | 1280×800 |
| `89-qa-local-admin-product-modal-360.png` | Modal de edición de producto adaptado a pantalla móvil de 360 px | 360×800 |
| `90-qa-local-admin-inventory-miniaturas-1280.png` | Panel `/admin/inventory` mostrando miniatura real en tabla desktop | 1280×800 |
| `91-qa-local-admin-inventory-360.png` | Panel `/admin/inventory` en tarjetas móviles a 360 px con fotos reales | 360×800 |
| `92-qa-local-admin-orders-miniaturas-1280.png` | Panel `/admin/orders` con listado de pedidos | 1280×800 |
| `93-qa-local-admin-orders-detalle-1280.png` | Modal de detalle de pedido en admin con miniatura real cargada en cada ítem | 1280×800 |
| `94-qa-local-admin-orders-360.png` | Panel `/admin/orders` en móvil a 360 px | 360×800 |
| `95-qa-local-admin-products-cls-medicion-1280.png` | Medición de Cumulative Layout Shift (CLS = 0,0000) en `/admin/products` | 1280×800 |

---

VEREDICTO: APTO
