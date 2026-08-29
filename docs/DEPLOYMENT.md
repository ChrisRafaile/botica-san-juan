# Deployment Guide

This repository is now prepared for the final migration phases:

- Phase 4: Fracciones + Unidades Multiples
- Phase 5: SUNAT backend + Comisiones

## Features Included

- Phase 4:
- Configuracion de productos para venta fraccionada (unidad/blister/caja).
- Factores de conversion en pedido_detalle para mantener trazabilidad en unidades.
- Precios por presentacion (unidad, blister, caja).

- Phase 5:
- Envio de comprobantes a SUNAT mediante cliente configurable (modo simulado o API real).
- Persistencia de ticket/respuesta SUNAT por comprobante.
- Registro y liquidacion de comisiones por comprobante.

## SUNAT Configuration

Define these keys in `botica-san-juan-backend/.env`:

```env
SUNAT_MODE=simulado
SUNAT_BASE_URL=
SUNAT_TOKEN=
SUNAT_TIMEOUT=20
```

- `SUNAT_MODE=simulado`: usa respuesta mock para ambientes de desarrollo.
- `SUNAT_MODE=api`: envia al endpoint configurado en `SUNAT_BASE_URL`.
- Para demo deterministica en modo simulado, puedes forzar estado en el endpoint usando `force_status=aceptada|rechazada`.

## Production Checklist

1. Configure backend environment variables in `botica-san-juan-backend/.env`.
2. Set SUNAT keys (`SUNAT_MODE`, `SUNAT_BASE_URL`, `SUNAT_TOKEN`) if you are enabling real API mode.
3. Run backend migrations:

```bash
cd botica-san-juan-backend
php artisan migrate --force
```

1. Build the frontend for production:

```bash
cd botica-san-juan-frontend
corepack enable
pnpm install --frozen-lockfile
pnpm build
```

1. Warm up Laravel caches in production:

```bash
cd botica-san-juan-backend
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

1. Validate the same smoke flows used during development:

- `botica-san-juan-backend/smoke_fase2_digemid.ps1`
- `botica-san-juan-backend/smoke_fase3_facturacion.ps1`
- `botica-san-juan-backend/smoke_fase3_reportes.ps1`
- `botica-san-juan-backend/smoke_fase5_sunat_comisiones.ps1`
- Demo con SUNAT aceptada forzada:

```powershell
cd botica-san-juan-backend
.\smoke_fase5_sunat_comisiones.ps1 -ForceSunatStatus aceptada
```

## Local Dev Startup Scripts

En la raiz del proyecto se incluyen:

- `start_backend.ps1`: levanta Laravel en `127.0.0.1:8083`.
- `start_frontend.ps1`: levanta Vite en `127.0.0.1:5173`.
- `start_dev.ps1`: abre dos terminales y levanta backend+frontend automaticamente.

Uso rapido:

```powershell
cd botica_san_juan
.\start_dev.ps1
```

## Recommended Deployment Model

Use one of these layouts:

- Single server: Laravel backend and built Vue frontend behind Nginx.
- Split deployment: backend API on one host, frontend static build on another host or CDN.

## Verification

After deployment, verify:

- Login still works.
- DIGEMID import and overprice blocking still work.
- Billing document generation, SUNAT send, XML and PDF download still work.
- Commission registration and liquidation flows still work.
- Frontend production build completes without type errors.

If you want, the next step after this is to add containerization manifests for a one-command deployment.
