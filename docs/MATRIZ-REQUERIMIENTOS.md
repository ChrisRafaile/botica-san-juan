# Matriz de requerimientos · RF/RNF → estado → evidencia

Punto 33 de la rúbrica: *«Resultados finales: requerimientos implementados y
demostración del sistema funcionando»*.

Los requerimientos salen de las **Tablas 7 y 8 del APF1**, tal como se
declararon en la semana 5. No se han reescrito para que encajen con lo
construido: si algo quedó corto, la fila lo dice.

**Estado del repositorio:** 379 pruebas de backend (1 068 aserciones), 116 de
frontend, 54 migraciones, sistema desplegado en Render y Vercel.

---

## Cómo leer el estado

| Estado | Qué significa |
|---|---|
| **Implementado** | Funciona en producción y hay evidencia que lo respalda |
| **Parcial** | Lo esencial funciona, pero algo de lo prometido no está. La fila dice qué falta |
| **Pendiente** | No se construyó. La fila dice por qué |

No hay fila «implementado» cuya evidencia sea solo «existe el código». La
evidencia es una prueba, una captura sobre el sistema en ejecución, una ruta
que responde o un documento.

---

## Requerimientos funcionales (Tabla 7 del APF1)

| ID | Requerimiento | Pri. | Estado | Evidencia |
|---|---|---|---|---|
| **RF01** | Autenticar con credenciales cifradas, con segundo factor para administrador | Alta | **Parcial** | Autenticación con bcrypt y Sanctum: `AutorizacionRolTest`. El segundo factor está construido y se exige en el login cuando la cuenta lo tiene activo (`UsuarioController` línea 267, `mfaSetup`/`mfaEnable`), pero **no está activado en la cuenta de administrador de producción**: la capacidad existe, la exigencia efectiva no |
| **RF02** | Gestionar usuarios y asignar rol que determine lo accesible | Alta | **Implementado** | `AutorizacionRolTest` (401 sin sesión, 403 con rol cliente), middleware `admin`, `PER-01`–`PER-13` |
| **RF03** | Registrar, actualizar y consultar productos con categoría, laboratorio y presentación | Alta | **Implementado** | `ProductoApiTest`, `/api/productos`, `M01`–`M03` |
| **RF04** | Carga masiva de productos desde CSV o Excel | Media | **Implementado** | `AdminBulkUploadView.vue`, `POST /api/productos/bulk` |
| **RF05** | Buscar por nombre, principio activo o código de barras en menos de dos segundos | Alta | **Implementado** (tiempo: ver RNF03) | `/api/productos?buscar=`, `/api/catalogo?buscar=`, `E8-tiempos-respuesta-api.txt`. El límite de dos segundos no se ha medido bajo carga |
| **RF06** | Registrar ventas asociando cada línea a lote y vencimiento, sugiriendo el más próximo a vencer | Alta | **Implementado** | FEFO en el POS: `TrazabilidadLotesTest`, `E7-venta-pos-aplicada.txt`, `UI-01`–`UI-05` |
| **RF07** | Actualizar el inventario automáticamente al confirmar una venta | Alta | **Implementado** | `ConfirmacionVentaTest`, `E7-venta-pos-verificacion-externa.txt` |
| **RF08** | Venta fraccionada por unidad, blíster o caja con precios diferenciados | Media | **Implementado** | Campos `unidad_base`, `venta_fraccionada`, `precio_blister`, `precio_caja`; `unidad_venta` en `pedido_detalles` |
| **RF09** | Alertas de stock bajo el mínimo y de productos próximos a vencer | Alta | **Implementado** | Tablero de alertas, `stock_minimo` y `stock_reposicion`, `/admin/inventory` |
| **RF10** | Registrar proveedores y órdenes de compra, actualizando inventario al recibir | Media | **Implementado** | `/api/proveedores`, `/api/compras`, `AdminSupplyView.vue` |
| **RF11** | Importar y conciliar el catálogo DIGEMID | Media | **Implementado** | `DigemidCatalogoController`, importador y normalizador de principio activo, `I01`–`I03` |
| **RF12** | Emitir boletas y facturas electrónicas y enviarlas a SUNAT registrando la respuesta | Alta | **Implementado** | `FacturacionController`, XML y CDR, `docs/FACTURACION-SUNAT.md`, `SOA-01`–`SOA-03` |
| **RF13** | Tablero con indicadores de ventas y alertas de inventario | Alta | **Implementado** | `/admin/indicadores` y `/admin/home`, `IndicadoresTest`, `K01`–`K03` |
| **RF14** | Exportar reportes gerenciales en CSV | Baja | **Implementado** | `ReporteContableController::csv`, `/api/reportes/registro-ventas/csv` |
| **RF15** | Registrar en bitácora toda modificación de precios, inventario y facturación | Alta | **Implementado** | `EventoOperacion` con tres niveles, middleware `AuditCriticalChanges` en seis grupos de rutas, **visor en `/admin/eventos`**, `BitacoraDeOperacionTest` (16 pruebas), `EVT-01`–`EVT-04` |

**13 implementados, 2 parciales, 0 pendientes.**

---

## Requerimientos no funcionales (Tabla 8 del APF1)

La Tabla 8 declaró, para cada RNF, **cómo debía verificarse**. Esa columna es
la que decide el estado: un RNF cuya verificación no se ejecutó no está
verificado, por mucho que el código parezca correcto.

| ID | Requerimiento | Verificación declarada | Estado | Evidencia |
|---|---|---|---|---|
| **RNF01** | Las funciones administrativas exigen autenticación y rol de administrador | Prueba automatizada de autorización | **Implementado y verificado** | `AutorizacionRolTest`; `BitacoraDeOperacionTest` añade el caso del visor (401 sin sesión, 403 con rol cliente) |
| **RNF02** | Contraseñas cifradas con bcrypt; bloqueo tras cinco intentos fallidos | Inspección de la base y prueba de límite | **Parcial** | bcrypt verificado (`'password' => 'hashed'`, `DesvincularGoogleTest`). El bloqueo **no es de cuenta**: es un límite de 5 intentos por minuto por IP + DNI (`RateLimiter::for('login')`). Frena la fuerza bruta, pero no bloquea la cuenta como prometía el RNF |
| **RNF03** | Búsqueda en menos de dos segundos con hasta 10 000 registros | Prueba de carga, percentil 95 | **Pendiente** | `E8-tiempos-respuesta-api.txt` mide tiempos de respuesta sin carga concurrente. La prueba de carga con percentil 95 **nunca se ejecutó**, y el catálogo real tiene 884 productos, no 10 000 |
| **RNF04** | Interfaces adaptables a distintos tamaños de pantalla | Matriz de pruebas de compatibilidad | **Parcial** | Diseño adaptable verificado por inspección a 1280×720 y 1440×950 (`M01`, `EVT-01`–`EVT-03`). No existe la **matriz** de compatibilidad que pedía la verificación |
| **RNF05** | Disponibilidad mínima del 99 % en horario de atención | Monitoreo cada cinco minutos | **Pendiente** | `infra/monitoring/healthcheck_monitor.ps1` existe pero no está programado. Y hay un impedimento real: la capa gratuita de Render **duerme el servicio tras quince minutos** sin tráfico, con unos 50 s de arranque. Con esa infraestructura, el 99 % no es alcanzable ni medible honestamente |
| **RNF06** | Toda operación que afecte el inventario se ejecuta en transacción atómica | Prueba de interrupción forzada | **Implementado**, verificación parcial | `DB::transaction` con `lockForUpdate` en venta, sincronización y vinculación de cuentas; `SincronizacionVentasOfflineTest` cubre el rechazo que no arrastra al lote. No se hizo la **interrupción forzada** que pedía la verificación |
| **RNF07** | El código supera el análisis estático sin errores; el esquema evoluciona por migraciones versionadas | Ejecución del pipeline de CI | **Parcial** | 54 migraciones versionadas y reversibles ✓. El pipeline corre pruebas, `vue-tsc` y un escáner de secretos; **no ejecuta análisis estático de PHP** (Pint/PHPStan) ni ESLint. `CI-01`–`CI-03` |
| **RNF08** | Respaldo diario automatizado, RPO 24 h y RTO 60 min | Simulacro de restauración con verificación por hash | **Pendiente** | `infra/backup/` tiene respaldo y restauración para PostgreSQL y SQLite, y `run_restore_drill.ps1`. **El simulacro nunca se ha ejecutado**, así que el RTO de 60 minutos es una estimación, no un dato |

**1 implementado y verificado, 4 parciales, 3 pendientes.**

---

## Lo que esta matriz dice de verdad

**Lo funcional está construido; lo no funcional está construido pero poco
verificado.** Trece de los quince RF funcionan en producción. En cambio, de
ocho RNF solo uno tiene la verificación que el propio APF1 exigía.

No es casualidad: un requerimiento funcional se nota cuando falta —la pantalla
no está— y uno no funcional, no. Un sistema puede pasar meses sin que nadie
compruebe si restaura un respaldo, y parecer que todo va bien.

Tres de los pendientes se cierran con trabajo acotado y están en el plan del
informe final:

| Qué falta | Cierra | Esfuerzo |
|---|---|---|
| Ejecutar `run_restore_drill.ps1` y guardar la salida | RNF08 | Bajo: el script existe |
| Prueba de carga con percentil 95 | RNF03 | Medio |
| Añadir Pint y ESLint al pipeline | RNF07 | Bajo |

**RNF05 no se cierra con trabajo, se cierra con una decisión.** El 99 % de
disponibilidad es incompatible con una instancia que se duerme. Lo honesto en
el informe es decirlo y proponer la alternativa (plan de pago, o un servicio
que mantenga la instancia despierta), no inventar un número.

**RNF02 y RF01 son promesas que conviene reformular**, no fallos. El límite
por minuto protege mejor que un bloqueo de cuenta —que se puede usar para
dejar fuera a un usuario legítimo a propósito—, y el segundo factor está
construido aunque no activado. Las dos cosas se explican mejor como decisiones
razonadas que como incumplimientos, siempre que la matriz las muestre.

---

## Nota sobre la evidencia

Los códigos (`PER-01`, `K03`, `EVT-04`…) remiten a
`docs/evidencias/INDICE.md`, donde cada uno dice qué demuestra y cómo
reproducirlo. Las pruebas se nombran por su clase para que se puedan ejecutar
una a una:

```powershell
php artisan test --filter=BitacoraDeOperacionTest
```
