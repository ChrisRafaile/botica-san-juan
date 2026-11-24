# Informe Final de Auditoría QA — Evidencias Visuales y Telemetría

- **Fecha de ejecución:** 23 de noviembre de 2026
- **API auditada:** `http://127.0.0.1:8083`
- **Portal web auditado:** `http://localhost:5173`
- **Motor de automatización:** `playwright-core` con Google Chrome (`1280x800`, tema claro forzado)
- **Transporte de correo:** `MAIL_MAILER=log`. `CORREO_ENVIADO` en EF-02 acredita el despacho al transporte local, **no** una entrega por Resend.
- **Reproducción:** `php artisan serve --port=8083`, `vite --port 5173`, cuentas de demostración del seeder `UsuariosDemostracionSeeder` y Chrome a 1280x800 recorriendo las rutas de la matriz. Cada corrida registra un pedido local.

---

## 1. Resumen Ejecutivo

Se ejecutó el recorrido completo de auditoría QA sobre las once evidencias solicitadas (`EF-01` a `EF-11`), autenticando las cuentas de demostración (`DNI 10000001` para administración y `DNI 10000002` para cliente) exclusivamente en memoria desde `botica-san-juan-backend/.env` y a través del formulario interactivo de `/login`, sin almacenar ni imprimir credenciales.

Cada imagen fue inspeccionada visualmente para verificar:
1. **Privacidad y anonimización:** Ausencia total de datos personales reales y de números RUC expuestos en cabeceras, pies de página, tarjetas o comprobantes.
2. **Estabilidad visual:** Ausencia de indicadores de carga pendientes (*spinners* `.animate-spin` o esqueletos `.animate-pulse`), imágenes rotas o errores en pantalla.
3. **Presentación:** Renderizado uniforme en **tema claro** con resolución de ventana `1280x800`.

---

## 2. Matriz de Revisión de Capturas (`EF-01` a `EF-11`)

| Código | Archivo | Ruta / Endpoint | Revisión Visual y Funcional | Datos Reales / RUC | Spinners / Errores UI | Estado |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **EF-01** | `EF-01-salud.png` | `GET /api/salud` | Respuesta HTTP `200 OK` con cuerpo JSON `{"ok":true,"base":true,"hora":"..."}` confirmando proceso activo y conexión a base de datos. | Ninguno | Sin spinners ni errores | APROBADO |
| **EF-02** | `EF-02-bitacora.png` | `/admin/eventos` | Vista «Bitácora de operación» de solo lectura con contadores por nivel (`Información`, `Advertencia`, `Error`), filtros operativos y listado de eventos (`CORREO_ENVIADO`, `IMPORTACION_INVENTARIO`, `PEDIDO_CANCELADO`). | Ninguno (solo cuenta demo «Administrador de demostración») | Sin spinners ni errores | APROBADO |
| **EF-03** | `EF-03-alertas-fefo.png` | `/admin/inventory/alerts` | Vista «Alertas de inventario» en la pestaña «Alertas de vencimiento» con tarjetas de Valor en riesgo (90d), Lotes vencidos, Críticos (≤ 30 días — salida FEFO), filtros por ventana y tabla con semáforo de vencimiento (`417d`, `Sin fecha`). | Ninguno | Sin spinners ni errores | APROBADO |
| **EF-04** | `EF-04-recuperacion.png` | `/forgot-password` | Formulario «¿Olvidaste tu contraseña?» tras enviar el correo inexistente `usuario.inexistente@prueba.test`. Muestra el mensaje neutro anti-enumeración: *«Si existe una cuenta con ese correo, te enviamos un enlace para restablecer la contraseña...»*. | Ninguno (`@prueba.test`) | Sin spinners ni errores | APROBADO |
| **EF-05** | `EF-05-registro.png` | `/register` | Formulario «Crear cuenta» rellenado con datos ficticios (`Lucía Fernández Quispe`, DNI `45678912`, `lucia.ficticia@prueba.test`, teléfono `987654321`, medidor de contraseña en «Muy buena» y casilla de términos marcada), **sin enviar**. | Ninguno (datos ficticios `@prueba.test`) | Sin spinners ni errores | APROBADO |
| **EF-06** | `EF-06-compra-registrada.png` | `/cart` (confirmación) | Pedido registrado localmente con sesión de cliente demo (`DNI 10000002`). Diálogo «Pedido registrado» mostrando `N.º de pedido #234`, modalidad `Recojo en botica`, comprobante `Boleta B001-00000054`, total `S/ 40.00` y acciones para descargar PDF o ver pedidos. | Ninguno | Sin spinners ni errores | APROBADO |
| **EF-07** | `EF-07-catalogo.png` | `/products` | Catálogo público en tema claro mostrando buscador, filtros por tipo, laboratorio y orden, contador `Mostrando 1–24 de 884 productos` y tarjetas con imagen, distintivo de stock, concentración, presentación, laboratorio, precio y botón «Agregar». | Ninguno | Sin spinners ni errores | APROBADO |
| **EF-08** | `EF-08-checkout.png` | `/cart` (diálogo «Finalizar compra») | Checkout con modalidad de entrega, comprobante y forma de pago seleccionados, antes de confirmar. | Ninguno (cuenta demo) | Sin spinners ni errores | APROBADO |
| **EF-09** | `EF-09-pos.png` | `/admin/pos` | Punto de venta de mostrador con indicador `En línea`, buscador `(F2)`, filtros por categoría, cuadrícula de productos, línea activa en «Venta actual» (`AB-BRONCOL NF 1200`, `S/ 40.00`), desglose tributario (`Valor de venta S/ 33.90`, `IGV S/ 6.10`, `Total S/ 40.00`) y botón `Cobrar F4`. | Ninguno (`Cliente eventual`) | Sin spinners ni errores | APROBADO |
| **EF-10** | `EF-10-asistente-importador.png` | `/admin/inventory` (widget) | Widget «Asistente de Botica · Modo gestión e inventario» abierto por el administrador con archivo CSV (`inventario_lotes_fefo.csv`) previsualizado sin escritura en base de datos (`Válidas: 2`, `Duplicados: 0`, `Advertencias: 0`, `Errores: 0`, botones «Confirmar importación» y «Cancelar»). | Ninguno | Sin spinners ni errores | APROBADO |
| **EF-11** | `EF-11-openapi.png` | `GET /docs/v1` | Documentación interactiva OpenAPI 3.1.0 (Scalar) en tema claro mostrando explícitamente la versión **`0.8.0`**, título `API Botica San Juan`, servidor `http://127.0.0.1:8083/api`, esquema Bearer Token y listado de operaciones. | Ninguno | Sin spinners ni errores | APROBADO |

---

## 3. Registro de Consola y Respuestas HTTP (`>= 400`)

Durante la ejecución instrumentada se suscribieron escuchas a `page.on('console')` (niveles `error` y `warning`), `page.on('pageerror')` y `page.on('response')` (códigos de estado `>= 400`).

- **Errores o advertencias de consola (`console.error` / `console.warn` / `pageerror`):** `0` registrados en la corrida final (`[]`).
  - *Nota técnica de sincronización:* En una primera corrida exploratoria se observó un aviso `ERR_CANCELED` en `/api/asistente/estado` cuando se adjuntaba el archivo CSV antes de que finalizara la consulta inicial de estado del widget (debido al `AbortController` compartido en `useAsistente.ts`). Al esperar la resolución de `/api/asistente/estado` antes de adjuntar el archivo CSV, la bitácora de consola quedó en **0 errores y 0 advertencias**.
- **Respuestas HTTP con código `>= 400`:** `0` registradas en los 11 flujos (`[]`). Todas las llamadas a la API (`/api/salud`, `/api/password/olvide`, `/api/productos`, `/api/carrito/cotizar`, `/api/interacciones/evaluar`, `/api/login`, `/api/pedidos/confirmar`, `/api/eventos`, `/api/inventario/vencimientos`, `/api/pos/productos`, `/api/asistente/estado`, `/api/asistente/importaciones/previsualizar` y `/docs/v1`) respondieron con estado `200` o `201`.

> **Límite:** el lote local no tiene vencimientos a menos de 90 días, por lo que EF-03 muestra la vista de alertas con contadores en cero.
