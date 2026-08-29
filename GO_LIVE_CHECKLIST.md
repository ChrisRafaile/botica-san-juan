# Go Live Checklist - Botica San Juan

Estado general: base funcional para operacion inicial, pendiente hardening para salida comercial.

## Como usar este checklist

1. Completar primero Bloqueante (rojo).
2. Luego Importante (amarillo).
3. Finalmente Deseable (verde).
4. Marcar cada item con [x] cuando cumpla criterio de aceptacion.

## BLOQUEANTE (Rojo) - Debe estar antes de salir a produccion

1. [x] Autorizacion backend por rol en rutas mutables
Criterio de aceptacion:

- POST/PUT/PATCH/DELETE de modulos admin requieren auth y rol administrador.
- Usuario cliente recibe 403 en mutaciones.
Validacion:
- Probar con token admin (200) y token cliente (403).

1. [x] Hardening de secretos y variables de entorno
Criterio de aceptacion:

- APP_DEBUG=false en produccion.
- APP_KEY y tokens (SUNAT, DB, correo) no hardcodeados ni versionados.
- .env de produccion fuera de repositorio.
Validacion:
- Revisar que no existan secretos en git history reciente ni en archivos tracked.

Estado actual:

- Script de escaneo de secretos disponible: `infra/security/scan_secrets.ps1`.
- Gate CI habilitado en `.github/workflows/ci-cd.yml` y `botica-san-juan-backend/.github/workflows/tests.yml`.
- Evidencia local de escaneo limpio: `botica-san-juan-backend/logs/security-evidence/secrets-scan-20260412-224834.txt`.
- Remediado: credenciales hardcodeadas legacy movidas a variables de entorno en `php/config.php`, `php/import_products.php` y `php/test_connection.php`.

1. [x] CORS de produccion controlado
Criterio de aceptacion:

- Solo dominios permitidos (frontend real, panel admin real).
- Sin comodines abiertos en produccion.
Validacion:
- Requests desde dominio no permitido fallan por CORS.

1. [x] Politica de backup y restauracion probada
Criterio de aceptacion:

- Backup automatico diario de base de datos.
- Restauracion probada en entorno de staging.
- Definidos RPO/RTO.
Validacion:
- Ejecucion de restore exitosa con evidencia de prueba.

Estado actual:

- Scripts base de backup/restore disponibles para SQLite:
  - `infra/backup/backup_sqlite.ps1`
  - `infra/backup/restore_sqlite.ps1`
  - `infra/backup/run_restore_drill.ps1`
- Guia operativa: `infra/backup/README.md`.
- Evidencia de restore en staging: `botica-san-juan-backend/logs/backup-evidence/20260412-220952/summary.json` (hash consistente y restore_ok).

1. [x] Auditoria de cambios criticos
Criterio de aceptacion:

- Trazabilidad de cambios en inventario, precios y facturacion (quien, que, cuando).
- No eliminacion silenciosa de datos criticos.
Validacion:
- Consultar log/auditoria y verificar eventos reales.

Estado actual:

- Middleware `audit.critical` activo en mutaciones admin.
- Registra actor, ruta, metodo, codigo HTTP, IP, duracion y payload filtrado.

## IMPORTANTE (Amarillo) - Debe estar en las primeras semanas

1. [x] Rate limiting por endpoint sensible
Criterio de aceptacion:

- Login con limite de intentos por IP/usuario.
- Endpoints sensibles con throttling configurable.
Validacion:
- Simular rafaga y confirmar respuestas 429.

1. [x] Observabilidad minima operacional
Criterio de aceptacion:

- Logs estructurados por modulo.
- Canal centralizado o exportable (archivo rotado/servicio externo).
- Correlacion de errores backend/frontend.
Validacion:
- Trazar una incidencia de punta a punta con timestamp.

Estado actual:

- Canal dedicado de auditoria activa en `storage/logs/audit.log` (rotacion diaria).
- Eventos criticos registran actor, ruta, metodo, ip, duracion y resultado.
- Correlacion habilitada con `X-Request-Id` en respuesta y contexto de logs.
- Export centralizable habilitado con `infra/monitoring/export_logs_bundle.ps1`.
- Evidencia de export generado: `botica-san-juan-backend/logs/exports/logs-export-20260412-221027.zip`.

1. [x] Health checks y readiness
Criterio de aceptacion:

- Endpoint de salud para app y base de datos.
- Alertas basicas por caida.
Validacion:
- Monitoreo detecta servicio caido en menos de 5 min.

Estado actual:

- Endpoints implementados: `/api/health` y `/api/health/db`.
- Automatizacion diaria lista: `infra/monitoring/run_daily_health_smoke.ps1`.
- Registro de tarea programada disponible: `infra/monitoring/register_daily_health_smoke_task.ps1.example`.
- Evidencia OK documentada: `botica-san-juan-backend/logs/smoke-evidence/20260412-215332-phase6`.

1. [x] Pruebas minimas de regresion
Criterio de aceptacion:

- Smoke automatizado para login, pedidos, inventario, facturacion, reportes.
- Flujo critico admin validado en cada deploy.
Validacion:
- Pipeline o script de smoke con resultado OK documentado.

Estado actual:

- Scripts de smoke disponibles para login/admin, digemid, facturacion, reportes y SUNAT/comisiones.
- Nuevo script de seguridad/readiness: `botica-san-juan-backend/smoke_fase6_security_readiness.ps1` (valida 401/403/429 + `/api/health` + `/api/health/db`).
- El script genera evidencia JSON por corrida (archivo de salida automatica o parametrizable).
- Corrida full OK documentada: `botica-san-juan-backend/logs/smoke-evidence/20260412-215423-full/summary.json`.

## DESEABLE (Verde) - Mejora competitiva y escalabilidad

1. [x] MFA para cuentas administrativas
Criterio de aceptacion:

- Segundo factor opcional/obligatorio para admins.
Validacion:
- Login admin requiere 2do factor en politica activa.

Estado actual:

- Implementado TOTP para admins (`/api/mfa/setup`, `/api/mfa/enable`, `/api/mfa/disable`).
- Login admin con MFA activo requiere `mfa_code` en `/api/login`.
- Evidencia de setup MFA: `botica-san-juan-backend/logs/mfa-evidence/mfa-setup-20260413-000021.json`.

1. [x] Reporteria avanzada y exportes gerenciales
Criterio de aceptacion:

- KPIs de ventas, stock, margen, rotacion y quiebre.
- Exportes listos para contador/gerencia.
Validacion:
- Reportes mensuales emitidos sin trabajo manual adicional.

Estado actual:

- Endpoint gerencial JSON: `/api/reportes/gerencial`.
- Export CSV gerencial: `/api/reportes/gerencial/export/csv`.
- Evidencia de corrida real:
  - `botica-san-juan-backend/logs/reportes-evidence/gerencial-20260412-235713.json`
  - `botica-san-juan-backend/logs/reportes-evidence/gerencial-20260412-235713.csv`

1. [x] Politica de continuidad operativa documentada
Criterio de aceptacion:

- Manual de incidentes y recuperacion.
- Responsable de guardia y escalamiento definido.
Validacion:
- Simulacro de incidente ejecutado y cerrado.

Estado actual:

- Politica y runbook documentados en `CONTINUIDAD_OPERATIVA.md`.
- Simulacro integral automatizado: `infra/monitoring/run_incident_simulation.ps1`.
- Evidencia de simulacro cerrado OK: `botica-san-juan-backend/logs/incident-drills/20260412-235339/summary.json`.

## Atributos de calidad (objetivos medibles)

1. Seguridad

- 100% endpoints mutables protegidos por auth+rol.
- 0 secretos en repositorio.

1. Confiabilidad

- Disponibilidad objetivo >= 99.5% mensual.
- Restore probado al menos 1 vez por mes.

1. Rendimiento

- P95 de endpoints criticos < 800 ms en horario normal.
- Generacion de PDF estable bajo carga esperada.

1. Mantenibilidad

- Checklist de deploy estandar.
- Convenciones de logs y manejo de errores unificadas.

1. Trazabilidad

- Eventos criticos auditables por usuario y fecha.

## Metodo optimo sugerido para seguridad y autenticacion

Recomendacion para este stack (Laravel + Vue):

1. Autenticacion con Sanctum (ya existente) + expiracion/rotacion de tokens.
2. Autorizacion con middleware/policies por rol (administrador/cliente) en backend.
3. Rate limit y bloqueo progresivo para login.
4. Auditoria de acciones sensibles.
5. MFA para admins en etapa 2.

## Matriz de amenazas y mitigaciones

1. DDoS / Saturacion de trafico

- Control de aplicacion: `throttle` por endpoint y por login.
- Control de infraestructura: CDN/WAF (Cloudflare o equivalente), rate limit en reverse proxy, fail2ban.
- Criterio de aceptacion: trafico anomalo no tumba el servicio principal.

1. Inyeccion SQL

- Control de aplicacion: Eloquent/Query Builder con binding parametrizado (sin concatenar SQL de usuario).
- Control de datos: validacion estricta por tipo/longitud/formato en Controllers y Form Requests.
- Criterio de aceptacion: payloads maliciosos no alteran consultas ni exponen datos.

Estado actual:

- Form Requests aplicados en auth, categorias y subcategorias.
- Pendiente: estandarizar validacion en los demas modulos mutables.

1. Fuerza Bruta / Credential Stuffing

- Control de aplicacion: limite de intentos por IP+usuario (`throttle:login`), bloqueo temporal incremental.
- Control operativo: monitoreo de intentos fallidos y alerta automatica.
- Criterio de aceptacion: multiples intentos fallidos disparan 429 y alerta.

Estado actual:

- Implementado: throttle de login + bloqueo incremental + logs de intentos fallidos/bloqueo.
- Pendiente: alerta automatica externa (email/webhook/observabilidad).

1. MITM (Man-in-the-Middle)

- Control de transporte: HTTPS obligatorio en produccion + HSTS.
- Control de cabeceras: X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy.
- Criterio de aceptacion: todo acceso productivo ocurre por TLS valido y cabeceras de seguridad activas.

## Cierre por etapas

1. Semana 1-2: completar todo Bloqueante.
2. Semana 3-4: completar Importante.
3. Semana 5-8: completar Deseable segun presupuesto.

## Siguiente paso recomendado (accionable inmediato)

1. Ejecutar y evidenciar restore en staging para cerrar backup/restore.
2. Integrar `infra/security/scan_secrets.ps1` en CI como gate de release.
3. Centralizar logs (ELK/CloudWatch/Loki) para cierre completo de observabilidad.
