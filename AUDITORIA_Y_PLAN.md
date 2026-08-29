# Auditoría técnica y plan de refinamiento — Botica San Juan

**Fecha:** 25 de agosto de 2026
**Alcance:** `botica-san-juan-backend` (Laravel 12) · `botica-san-juan-frontend` (Vue 3 + TS) · `infra/`
**Método:** inspección estática del código, consulta directa a la base de datos y verificación de compatibilidad SQL.

---

## 1. Resumen ejecutivo

El proyecto tiene una base sólida y un volumen de trabajo real considerable: 3 365 productos cargados, 26 migraciones aplicadas, 15 controladores REST, control de acceso por rol con MFA y scripts de infraestructura para respaldo y monitoreo. Sobre esa base se detectaron **4 hallazgos críticos**, **6 altos** y **7 medios**.

Dos de los críticos invalidan afirmaciones del informe APF1 y deben resolverse antes del APF2:

1. La base de datos **no es PostgreSQL**, es SQLite.
2. El **descuento automático de stock (RF-07) no está implementado** en ningún controlador.

| Severidad | Cantidad | Naturaleza |
|---|---|---|
| Crítico | 4 | Bloquean la operación real o contradicen el informe |
| Alto | 6 | Riesgo de datos, seguridad o despliegue |
| Medio | 7 | Mantenibilidad y deuda técnica |

---

## 2. Hallazgos críticos

### C-01 · La base de datos es SQLite, no PostgreSQL
**Evidencia:** `.env` y `.env.example` contienen `DB_CONNECTION=sqlite`. El archivo `database/database.sqlite` pesa 876 KB con 24 tablas pobladas. El único rastro de PostgreSQL en todo el repositorio es la configuración por defecto que trae Laravel en `config/database.php`, que no está en uso.

**Impacto:** el informe APF1 declara «PostgreSQL 16» en la arquitectura, el modelo de datos, la tabla de herramientas y el RNF-07. Toda la infraestructura de respaldo (`infra/backup/backup_sqlite.ps1`) está escrita para SQLite. SQLite bloquea la base completa en cada escritura, no soporta concurrencia real y carece de tipos como `ENUM` o `NUMERIC` con precisión garantizada.

**Acción:** migrar a PostgreSQL 16 y adaptar los scripts de respaldo a `pg_dump`/`pg_restore`.

---

### C-02 · El descuento de stock en la venta no existe
**Evidencia:** `PedidoController.php` y `PedidoDetalleController.php` no contienen ni una sola referencia a `stock`, `decrement` o `DB::transaction`.

**Impacto:** el RF-07 («descontar automáticamente el stock al confirmar una venta, dentro de una transacción atómica») y el RNF-05 (integridad transaccional) no están cubiertos. Es la regla de negocio central del sistema y la razón principal por la que se reemplaza el legacy.

**Acción:** implementar el caso de uso de venta con transacción, bloqueo pesimista sobre el producto y validación de stock suficiente.

---

### C-03 · Condición de carrera en el correlativo de comprobantes
**Evidencia:** `FacturacionController.php` línea 185 calcula el siguiente número con `->max('numero')` seguido de `$lastNumber + 1`, fuera de cualquier transacción.

**Impacto:** dos emisiones simultáneas obtienen el mismo correlativo. La tabla tiene `UNIQUE(serie, numero)`, de modo que una fallará con error de base de datos y la venta quedará sin comprobante. En un contexto tributario esto es inaceptable.

**Acción:** envolver la generación en una transacción con `lockForUpdate()` sobre una tabla de series, o delegar el correlativo a una secuencia de PostgreSQL.

---

### C-04 · Consulta SQL incompatible con el motor en uso
**Evidencia:** `FacturacionController.php` línea 31 usa `whereRaw("CONCAT(serie, '-', LPAD(numero, 8, '0')) like ?")`. Verificación directa contra la base:

```
SELECT CONCAT(serie,'-',LPAD(numero,8,'0')) ...  ->  FALLA: no such function: CONCAT
SELECT serie || '-' || printf('%08d', numero) ...  ->  OK: ('B001-00000001',)
```

**Impacto:** la búsqueda de comprobantes por número **lanza una excepción en tiempo de ejecución**. Es un bug activo, no teórico.

**Acción:** reemplazar por una expresión portable o por el operador de concatenación estándar `||` con `LPAD` de PostgreSQL tras la migración.

---

## 3. Hallazgos altos

| ID | Hallazgo | Evidencia | Acción |
|---|---|---|---|
| A-01 | Solo 1 de 15 controladores usa transacciones (`DigemidCatalogoController`) | Búsqueda de `DB::transaction` en `app/Http/Controllers/` | Envolver toda operación multi-tabla en transacción |
| A-02 | `baseURL` quemada a `http://127.0.0.1:8083/api` | `src/services/api.ts:7` | Mover a `import.meta.env.VITE_API_URL` |
| A-03 | Dos modelos Eloquent sobre la misma tabla `usuarios` (`User` y `Usuario`) | `app/Models/User.php:20` y `app/Models/Usuario.php` | Eliminar `User`, dejar `Usuario` como único modelo de autenticación |
| A-04 | Clientes HTTP duplicados: `api.js` y `api.ts`, `authService.js` y `auth.ts` | `src/services/` | Eliminar las versiones `.js`; el import `@/services/api` es ambiguo |
| A-05 | 24 archivos de depuración versionados en la raíz del backend | `check_*.php`, `test_*.php`, `update_*.php`, `smoke_*.ps1` | Mover los smoke a `scripts/`, eliminar el resto y añadir a `.gitignore` |
| A-06 | `test_hash.php` expone la contraseña `123456` y su hash bcrypt | `test_hash.php` | Eliminar el archivo y forzar cambio de contraseñas débiles |

---

## 4. Hallazgos medios

| ID | Hallazgo | Acción |
|---|---|---|
| M-01 | Cobertura de pruebas nula: solo existen los `ExampleTest` de Laravel | Crear suite de pruebas de funcionalidad |
| M-02 | Validación inline (`$request->validate`) en 11 controladores en vez de Form Requests | Extraer a Form Requests, como ya se hizo en Categoría y Subcategoría |
| M-03 | Vistas monolíticas: `AdminBulkUploadView` 1 656 líneas, `AdminSupplyView` 1 363, `AdminProductsView` 1 102 | Extraer componentes y composables |
| M-04 | `console.log('[DEV] ...')` en el módulo de API de producción | Eliminar o condicionar a `import.meta.env.DEV` |
| M-05 | El interceptor de respuesta registra y maneja 401, pero no notifica al usuario ni cubre 403/422/500 | Añadir notificación y mapeo de errores de validación |
| M-06 | `APP_DEBUG=true` en `.env` | Documentar el `.env` de producción con `APP_DEBUG=false` |
| M-07 | Dos respaldos `.sqlite.bak` versionados en `database/` | Excluir de control de versiones |

---

## 5. Corrección al informe APF1

Tres afirmaciones del documento deben ajustarse para que coincidan con el código:

| Punto del informe | Dice | Realidad | Propuesta |
|---|---|---|---|
| Arquitectura, herramientas, RNF-07 | PostgreSQL 16 | SQLite | Migrar el código a PostgreSQL (recomendado) para que el informe quede correcto |
| Retrospectiva Sprint 2, acción A-04 | «Falta un manejador global de errores en el cliente HTTP» | El interceptor existe y maneja 401 | Reformular: «el interceptor no notifica al usuario ni cubre 403/422/500» |
| Tabla 30, estado del Front-End | «Base construida» en varios módulos | Correcto, pero con vistas monolíticas | Sin cambio; se atiende como deuda técnica M-03 |

---

## 6. Plan de refinamiento priorizado

### Bloque 1 — Fundamentos (Sprint 3, antes del APF2)
1. Migrar de SQLite a PostgreSQL 16 y adaptar los scripts de respaldo. *(C-01)*
2. Corregir la consulta `CONCAT/LPAD` de facturación. *(C-04)*
3. Limpiar la raíz del backend y eliminar `test_hash.php`. *(A-05, A-06)*
4. Unificar el modelo de usuario y los clientes HTTP duplicados. *(A-03, A-04)*
5. Externalizar la `baseURL` a variable de entorno. *(A-02)*
6. Crear la suite de pruebas de funcionalidad del backend. *(M-01)*
7. Completar el interceptor de errores con notificación al usuario. *(M-05)*

### Bloque 2 — Núcleo del negocio (Sprint 4)
8. Implementar el caso de uso de venta: transacción, bloqueo, validación y descuento de stock. *(C-02)*
9. Añadir lotes y vencimiento con lógica FEFO al detalle del pedido.
10. Construir la pantalla de punto de venta con búsqueda indexada.

### Bloque 3 — Cumplimiento tributario (Sprint 5-6)
11. Corregir el correlativo con bloqueo transaccional. *(C-03)*
12. Generar XML UBL 2.1 firmado y completar el envío a la SUNAT, para eliminar la doble digitación detectada en el análisis AS-IS.

### Bloque 4 — Calidad sostenida (transversal)
13. Extraer componentes de las vistas monolíticas. *(M-03)*
14. Migrar la validación a Form Requests. *(M-02)*
15. Reservar el 15 % de cada sprint a deuda técnica, según el compromiso A-01 de la retrospectiva.

---

## 7. Estado actual de los datos

| Tabla | Filas | Observación |
|---|---|---|
| productos | 3 365 | Catálogo real migrado |
| pedidos | 32 | Datos de prueba |
| pedido_detalles | 11 | Menos detalles que pedidos: pedidos sin líneas |
| comprobantes_electronicos | 32 | Coincide con pedidos |
| comisiones | 5 | — |
| usuarios | 4 | La tabla `users` de Laravel está vacía y sin uso |
| categorias / subcategorias | 6 / 14 | — |
| proveedores / compras | 3 / 3 | Datos de prueba |
| digemid_catalogos | 5 | Carga mínima de prueba |
