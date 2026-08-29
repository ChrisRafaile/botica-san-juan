# Security Deployment Notes

## Objetivo

Guia practica para completar seguridad de salida comercial cuando aun no hay dominio/nube definitiva.

## Sin dominio aun: que si puedes hacer hoy

1. Mantener seguridad de aplicacion activa:

- throttle para login/register/api
- middleware admin en rutas mutables
- cabeceras de seguridad + HSTS en HTTPS

1. Preparar infraestructura:

- Plantilla Nginx en `infra/nginx/botica.production.conf.example`
- Plantilla fail2ban en `infra/fail2ban/jail.d/botica-nginx.local.example`

1. Staging temporal recomendado:

- VPS temporal + subdominio temporal o tunnel HTTPS
- validar flujo TLS end-to-end y redireccion 80->443

## Cuando ya tengas dominio

1. Configurar DNS (A/AAAA).
2. Emitir certificado TLS (Let's Encrypt).
3. Activar server 443 + HSTS.
4. Forzar redireccion HTTP a HTTPS.
5. Ajustar `CORS_ALLOWED_ORIGINS` a dominio real.

## Anti-ataques (resumen)

1. DDoS: WAF/CDN + Nginx limit_req + fail2ban + throttle app.
2. SQLi: Form Requests + Eloquent sin SQL raw no parametrizado.
3. Credential stuffing: throttle login + bloqueo progresivo + alertas.
4. MITM: TLS obligatorio + HSTS + headers de seguridad.

## Correlacion de incidentes

1. El backend expone `X-Request-Id` en respuestas API.
2. Los logs incluyen `request_id` para rastrear una incidencia de punta a punta.
3. Auditoria critica escribe eventos en `storage/logs/audit.log`.

## Hardening de secretos

1. Escanear secretos potenciales antes de cada release:

```powershell
pwsh -File .\infra\security\scan_secrets.ps1
```

1. Si el escaneo falla, bloquear deploy hasta remediar y rotar credenciales.
2. Gate CI habilitado en:

- `.github/workflows/ci-cd.yml`
- `botica-san-juan-backend/.github/workflows/tests.yml`

## Verificacion operativa (smoke + readiness)

1. Ejecutar smoke de seguridad/readiness:

- Script: `botica-san-juan-backend/smoke_fase6_security_readiness.ps1`
- Cubre: `401` sin token, `403` con cliente en ruta admin, `429` por fuerza bruta, `/api/health`, `/api/health/db`.
- Variables opcionales para no hardcodear credenciales en consola:
  - `BOTICA_ADMIN_SECRET`
  - `BOTICA_CLIENT_SECRET`
- Evidencia: el script genera un JSON con resultados por check y `request_id`.
- Ejemplo:

```powershell
pwsh -File .\botica-san-juan-backend\smoke_fase6_security_readiness.ps1
```

1. Monitoreo basico de salud:

- Plantilla: `infra/monitoring/healthcheck_monitor.ps1.example`
- Script ejecutable: `infra/monitoring/healthcheck_monitor.ps1`
- Uso: ejecutar por tarea programada cada 1-5 minutos.
- Si falla health app/db devuelve codigo `1` y puede notificar por webhook.

1. Runner diario health + smoke:

- Script: `infra/monitoring/run_daily_health_smoke.ps1`
- Registro de tarea programada: `infra/monitoring/register_daily_health_smoke_task.ps1.example`
- Evidencias generadas en: `botica-san-juan-backend/logs/ops-evidence/<timestamp>/summary.json`

## Backup y restore (base SQLite de referencia)

1. Backup:

```powershell
pwsh -File .\infra\backup\backup_sqlite.ps1
```

1. Restore:

```powershell
pwsh -File .\infra\backup\restore_sqlite.ps1 -BackupFile .\infra\backup\database.20260101-120000.sqlite
```

1. Drill automatizado con evidencia JSON:

```powershell
pwsh -File .\infra\backup\run_restore_drill.ps1 -Environment staging
```

1. Registrar evidencia: fecha, hash SHA256 del backup y resultado del restore.

## Observabilidad exportable y simulacro

1. Export de logs para centralizacion externa:

```powershell
pwsh -File .\infra\monitoring\export_logs_bundle.ps1 -Days 2
```

1. Simulacro de incidente (health + smoke + backup/restore + export logs):

```powershell
pwsh -File .\infra\monitoring\run_incident_simulation.ps1 -Environment staging -ApiBase http://127.0.0.1:8083/api
```

1. Politica y runbook: `CONTINUIDAD_OPERATIVA.md`.

## MFA administrativa

1. Setup MFA (admin autenticado): `POST /api/mfa/setup`.
2. Habilitar MFA: `POST /api/mfa/enable` con `{ "code": "123456" }`.
3. Deshabilitar MFA: `POST /api/mfa/disable` con `{ "code": "123456" }`.
4. Login admin con MFA activo requiere `mfa_code` adicional en `/api/login`.

## Evidencia actual de cierre (validacion)

1. Smoke fase 6 OK: `botica-san-juan-backend/logs/smoke-evidence/20260412-215332-phase6`.
1. Bateria full OK: `botica-san-juan-backend/logs/smoke-evidence/20260412-215423-full/summary.json`.
