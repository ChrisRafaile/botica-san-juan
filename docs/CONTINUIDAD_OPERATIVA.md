# Politica de Continuidad Operativa - Botica San Juan

## Objetivo

Definir como responder ante incidentes para mantener operacion, proteger datos y recuperar servicio en tiempos aceptables.

## Alcance

- Backend API Laravel.
- Frontend web.
- Base de datos SQLite (entorno actual).
- Pipeline de smoke, health y evidencia operativa.

## Roles y guardia

- Responsable primario (on-call): Administrador tecnico del backend.
- Responsable secundario: Soporte de operaciones.
- Escalamiento de negocio: Responsable funcional de farmacia.

## Matriz de severidad

1. Sev-1: Caida total de API o base no disponible.
2. Sev-2: Funcion critica degradada (login, pedidos, facturacion).
3. Sev-3: Error parcial con workaround.

## SLA internos y objetivos

- Deteccion: < 5 minutos (health checks).
- Contencion Sev-1: < 15 minutos.
- Restauracion Sev-1: < 60 minutos.
- RPO objetivo: <= 24 horas.
- RTO objetivo: <= 60 minutos.

## Runbook minimo por incidente

1. Confirmar estado con health checks (`/api/health`, `/api/health/db`).
2. Registrar incidente con timestamp y request id.
3. Ejecutar smoke fase 6 para validar seguridad/readiness.
4. Si hay corrupcion de datos, ejecutar restore drill en staging y luego restore controlado en produccion.
5. Exportar bundle de logs para trazabilidad y postmortem.
6. Cerrar incidente con resumen de causa raiz y acciones preventivas.

## Simulacro operativo mensual

Ejecutar:

```powershell
pwsh -File .\infra\monitoring\run_incident_simulation.ps1 -Environment staging -ApiBase http://127.0.0.1:8083/api
```

Evidencia esperada:

- `botica-san-juan-backend/logs/incident-drills/<timestamp>/summary.json`
- `botica-san-juan-backend/logs/backup-evidence/<timestamp>/summary.json`
- `botica-san-juan-backend/logs/exports/logs-export-<timestamp>.zip`

## Criterio de cierre de simulacro

- Todos los checks en `summary.json` en estado `ok`.
- Restore consistente por hash.
- Bundle de logs generado.
- Acciones de mejora registradas en checklist.
