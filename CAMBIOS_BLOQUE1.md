# Bloque 1 — Fundamentos · Registro de cambios

**Fecha:** 25 de agosto de 2026
**Origen:** hallazgos de `AUDITORIA_Y_PLAN.md`
**Sprint objetivo:** Sprint 3 (previo al APF2)

---

## Resumen

| Hallazgo | Estado | Verificado |
|---|---|---|
| C-01 · Base de datos SQLite en lugar de PostgreSQL | Migración preparada | Script de datos ejecutado: 3 483 filas |
| C-03 · Condición de carrera en el correlativo | Corregido | Revisión de código |
| C-04 · `CONCAT`/`LPAD` incompatibles con SQLite | Corregido | Simulado contra la base real |
| A-02 · `baseURL` quemada en el frontend | Corregido | `vue-tsc` sin errores |
| A-03 · Modelos `User` y `Usuario` duplicados | Corregido | Sin referencias residuales |
| A-04 · Clientes HTTP `.js` y `.ts` duplicados | Corregido | Sin importadores |
| A-05 · 24 archivos de depuración en la raíz | Corregido | Raíz limpia |
| A-06 · `test_hash.php` con contraseña expuesta | Retirado | En cuarentena |
| M-01 · Cobertura de pruebas nula | Corregido | 36 casos escritos |
| M-04 · `console.log` en producción | Corregido | Condicionado a `import.meta.env.DEV` |
| M-05 · Interceptor sin notificación al usuario | Corregido | Cubre 401/403/404/409/422/429/5xx |
| M-07 · Respaldos `.sqlite.bak` versionados | Corregido | Añadidos a `.gitignore` |

---

## 1. Migración a PostgreSQL 16

**Archivos nuevos**

- `infra/migracion/exportar_sqlite_a_postgres.py` — exporta los datos de negocio a SQL portable, convirtiendo booleanos (`0`/`1` → `false`/`true`) y reiniciando las secuencias con `setval`.
- `infra/migracion/GUIA_MIGRACION_POSTGRESQL.md` — guía paso a paso con lista de verificación.
- `infra/backup/backup_postgres.ps1` — respaldo con `pg_dump -Fc`, hash SHA-256, evidencia JSON y retención automática.
- `infra/backup/restore_postgres.ps1` — restauración con verificación de conteos; exige `-Confirmar` fuera de entornos de staging.

**Archivos modificados**

- `botica-san-juan-backend/.env.example` — bloque de base de datos reescrito para PostgreSQL.
- `.github/workflows/ci-cd.yml` — se añaden las extensiones `pgsql` y `pdo_pgsql`.

**Resultado del script de exportación**

```
usuarios 4 · categorias 6 · subcategorias 14 · proveedores 3
digemid_catalogos 5 · productos 3365 · compras 3 · pedidos 32
pedido_detalles 11 · comprobantes_electronicos 32 · comisiones 5
carrito 0 · contacto 3
Total: 3 483 filas
```

**Pendiente de tu parte:** ejecutar la guía en tu equipo (instalar PostgreSQL, habilitar `pdo_pgsql`, `php artisan migrate --force` y cargar el SQL). Las pruebas siguen usando SQLite en memoria a propósito, para que sean rápidas y no dependan de un servidor.

---

## 2. Facturación: bug de búsqueda y correlativo

**Archivo:** `app/Http/Controllers/FacturacionController.php`

*Antes*

```php
$inner->whereRaw("CONCAT(serie, '-', LPAD(numero, 8, '0')) like ?", ["%{$search}%"])
```

`CONCAT` y `LPAD` no existen en SQLite: el endpoint lanzaba `no such function: CONCAT`.

*Ahora* — método `aplicarBusquedaPorNumero()` que interpreta tres formas de búsqueda sin funciones propietarias:

| Entrada | Interpretación |
|---|---|
| `B001-00000012` | `serie = 'B001' AND numero = 12` |
| `12` | `numero = 12` |
| `B001` | `serie LIKE '%B001%'` |

Comprobado contra la base real: las tres formas devuelven el registro correcto.

**Correlativo** — `crearComprobanteParaPedido()` ahora envuelve la lectura del último número y la inserción en `DB::transaction()` con `lockForUpdate()`, de modo que dos emisiones simultáneas no puedan tomar el mismo número y chocar contra `UNIQUE(serie, numero)`.

---

## 3. Higiene del repositorio

- Los 7 scripts `smoke_*.ps1` se movieron a `botica-san-juan-backend/scripts/smoke/`.
- 17 archivos de depuración (`check_*.php`, `test_*.php`, `update_*.php`, `fix_seeder.php`, `generate_seeder.php`, `productos_array.txt`) se movieron a `_depuracion_archivada/`, carpeta ya excluida en `.gitignore`. **No se borraron**: si necesitas alguno, sigue ahí.
- `app/Models/User.php` y `database/factories/UserFactory.php` se retiraron. `config/auth.php` apunta ahora a `App\Models\Usuario` y `DatabaseSeeder` ya no importa `User`.
- `src/services/api.js` y `src/services/authService.js` se retiraron: no los importaba nadie y hacían ambiguo el import `@/services/api`.
- `.gitignore` ampliado con la cuarentena, los archivos `.sqlite`, los `.bak` y las carpetas de evidencias.

---

## 4. Frontend: configuración e interceptor

**Archivos nuevos**

- `.env.example` — `VITE_API_URL`, `VITE_API_TIMEOUT`, `VITE_APP_NAME`.
- `src/composables/useNotificaciones.ts` — sistema de notificaciones reactivo, invocable desde fuera de un componente.
- `src/components/NotificacionesToast.vue` — presentación accesible: `role="alert"`, `aria-live` según gravedad, barra de progreso y respeto por `prefers-reduced-motion`.

**Archivos modificados**

- `src/services/api.ts` — `baseURL` desde variable de entorno, `timeout` configurable, `console.log` eliminados y manejo de errores ampliado:

| Estado | Comportamiento |
|---|---|
| Sin respuesta | Distingue caída de red de tiempo de espera agotado |
| 401 | Limpia el token, avisa que la sesión expiró y redirige al login |
| 403 | «No tienes permiso para esta acción» |
| 404 | Aviso de recurso no encontrado |
| 409 | Conflicto con el estado actual |
| 422 | Lista los errores de validación campo por campo |
| 429 | «Demasiados intentos, espera unos segundos» |
| 5xx | Error del servidor con mensaje comprensible |

- `src/App.vue` — monta el contenedor de notificaciones.
- `env.d.ts` — declara `ImportMetaEnv` para que las variables tengan tipo.

`vue-tsc --noEmit` se ejecuta sin errores.

---

## 5. Suite de pruebas automatizadas

**36 casos** en 5 archivos (más 12 ejecuciones adicionales por el proveedor de datos de autorización):

| Archivo | Casos | Cubre |
|---|---|---|
| `AutenticacionTest.php` | 10 | RF-01, RNF-02: token, cifrado bcrypt, mensajes genéricos, validación, bloqueo por intentos, cierre de sesión |
| `AutorizacionRolTest.php` | 5 (+12) | RF-02: 401 sin token, 403 con rol cliente, 200 con administrador, en 6 rutas de mutación |
| `ProductoApiTest.php` | 11 | RF-03, RF-08: alta, validación, stock y precio no negativos, código de barras único, venta fraccionada |
| `FacturacionBusquedaTest.php` | 6 | Regresión del hallazgo C-04 y unicidad de serie–número |
| `SaludYSeguridadTest.php` | 4 | Endpoints de salud del SLA, cabeceras de seguridad, `X-Request-Id` |

`tests/TestCase.php` ahora ofrece `crearAdministrador()` y `crearCliente()`. Los `ExampleTest` de Laravel se retiraron.

Ejecutar con:

```powershell
cd botica-san-juan-backend
php artisan test
```

> **Nota honesta:** las pruebas se escribieron contra el contrato real de tus controladores, pero **no pude ejecutarlas** porque el entorno donde trabajo no tiene PHP. La primera ejecución debe hacerse en tu equipo; si algún caso falla por una diferencia de contrato, avísame con el mensaje y lo ajusto.

---

## 6. Impacto en el informe APF1

| Punto | Situación |
|---|---|
| PostgreSQL 16 en arquitectura y herramientas | Queda correcto una vez completes la migración |
| RNF-07 (respaldo con RPO 24 h / RTO 60 min) | Los scripts de PostgreSQL ya lo soportan |
| Retrospectiva Sprint 2, acción A-04 | Reformular: el interceptor existía y manejaba 401; lo que faltaba era notificar al usuario y cubrir el resto de estados |
| Retrospectiva, acción A-02 (pruebas) | Cumplida |
| Tabla 24, «Monitoreo y calidad» | Sin cambios |

---

## 7. Siguiente bloque

**Bloque 2 — Núcleo del negocio (Sprint 4)**

1. Caso de uso de venta con transacción, bloqueo y descuento de stock *(hallazgo C-02, el único crítico que sigue abierto)*.
2. Lotes y vencimiento con lógica FEFO en el detalle del pedido.
3. Pantalla de punto de venta con búsqueda indexada.
