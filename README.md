<div align="center">

<img src="docs/assets/logo.svg" alt="Botica San Juan" width="92" />

# Botica San Juan

### Sistema Web de Gestión y Control para establecimientos farmacéuticos

Modernización de un sistema legacy en Visual FoxPro hacia una arquitectura web
con control de lotes, trazabilidad de vencimientos y facturación electrónica SUNAT.

<br />

![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?style=flat-square&logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?style=flat-square&logo=php&logoColor=white)
![Vue](https://img.shields.io/badge/Vue-3.5-4FC08D?style=flat-square&logo=vuedotjs&logoColor=white)
![TypeScript](https://img.shields.io/badge/TypeScript-5.9-3178C6?style=flat-square&logo=typescript&logoColor=white)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16-4169E1?style=flat-square&logo=postgresql&logoColor=white)
![Tests](https://img.shields.io/badge/tests-47%20passing-22C55E?style=flat-square)

</div>

---

<img src="docs/assets/icons/target.svg" width="18" align="top" />  **El problema**

La Botica San Juan operaba con dos herramientas que no se comunicaban entre sí:
un sistema de gestión en Visual FoxPro, sin soporte oficial desde 2015, y el
Sistema Facturador SUNAT como aplicativo independiente. Cada comprobante se
digitaba dos veces y la carga a la SUNAT acumulaba hasta **catorce días de retraso**.

A eso se sumaba un inventario poco confiable: el registro de lote y fecha de
vencimiento era opcional, y el stock por tienda figuraba en cero en el maestro
de artículos.

<br />

<img src="docs/assets/icons/package.svg" width="18" align="top" />  **Qué hace este sistema**

| | Módulo | Descripción |
|:---:|---|---|
| <img src="docs/assets/icons/shield.svg" width="16" /> | **Seguridad** | Autenticación con tokens, roles diferenciados, segundo factor TOTP y auditoría de cambios críticos |
| <img src="docs/assets/icons/pill.svg" width="16" /> | **Catálogo** | 3 361 productos con laboratorio, principio activo, código DIGEMID y carga masiva desde CSV o Excel |
| <img src="docs/assets/icons/boxes.svg" width="16" /> | **Inventario** | Control de lotes con lógica FEFO y alertas de vencimiento a 90, 60 y 30 días |
| <img src="docs/assets/icons/scan-barcode.svg" width="16" /> | **Punto de venta** | Búsqueda indexada por nombre, principio activo o código de barras, con venta por unidad, blíster o caja |
| <img src="docs/assets/icons/receipt.svg" width="16" /> | **Facturación** | Boletas y facturas electrónicas con envío a la SUNAT desde la misma pantalla de cobro |
| <img src="docs/assets/icons/truck.svg" width="16" /> | **Abastecimiento** | Proveedores, órdenes de compra y conciliación con el catálogo DIGEMID |
| <img src="docs/assets/icons/chart-line.svg" width="16" /> | **Reportes** | Tablero de indicadores en tiempo real y exportación a CSV |

<br />

<img src="docs/assets/icons/layers.svg" width="18" align="top" />  **Arquitectura**

Arquitectura en capas con API REST sin estado. El desacople entre Frontend y
Backend permite que la misma API atienda al navegador, a la aplicación de
escritorio empaquetada con Tauri y al portal público del cliente.

```
Presentación     Vue 3 · TypeScript · Pinia · Tailwind CSS · Tauri
     │
Aplicación       15 controladores REST · middleware de rol, CORS y auditoría
     │
Dominio          Ventas · Inventario FEFO · Compras · Facturación · SunatClient
     │
Persistencia     Eloquent ORM · 26 migraciones versionadas · PostgreSQL 16
     │
Infraestructura  HTTPS · CI/CD · health checks · respaldos · monitoreo
```

<br />

<img src="docs/assets/icons/folder-tree.svg" width="18" align="top" />  **Estructura del repositorio**

```
botica_san_juan/
├── botica-san-juan-backend/     API REST en Laravel 12
│   ├── app/Http/Controllers/    15 controladores
│   ├── app/Http/Middleware/     Rol · CORS · auditoría · cabeceras de seguridad
│   ├── app/Services/            Integración con la SUNAT
│   ├── database/migrations/     26 migraciones versionadas
│   └── tests/                   47 pruebas automatizadas
├── botica-san-juan-frontend/    SPA en Vue 3 + TypeScript
│   └── src/
│       ├── admin/               Panel administrativo (12 vistas)
│       ├── auth/                Autenticación
│       ├── client/              Portal público
│       └── services/            Cliente HTTP con interceptores
├── infra/                       Infraestructura como código
│   ├── backup/                  Respaldo y restauración de PostgreSQL
│   ├── migracion/               Migración de SQLite a PostgreSQL
│   ├── monitoring/              Health checks e incidentes
│   └── evidencias/              Capturas del sistema en ejecución
├── docs/                        Documentación técnica y operativa
├── scripts/                     Utilitarios de arranque del entorno local
└── legacy-php/                  Versión anterior del sitio, conservada como referencia
```

<br />

<img src="docs/assets/icons/rocket.svg" width="18" align="top" />  **Puesta en marcha**

**Requisitos:** PHP 8.3 o superior con `pdo_pgsql`, Composer, Node 20 o superior y PostgreSQL 16.

```bash
# 1. Base de datos
psql -U postgres -c "CREATE DATABASE botica_san_juan ENCODING 'UTF8';"

# 2. Backend
cd botica-san-juan-backend
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --force
php artisan serve --port=8083

# 3. Frontend
cd ../botica-san-juan-frontend
npm install
cp .env.example .env
npm run dev
```

El sistema queda disponible en `http://localhost:5173` y la API en `http://localhost:8083/api`.

<br />

<img src="docs/assets/icons/flask.svg" width="18" align="top" />  **Pruebas**

```bash
cd botica-san-juan-backend && php artisan test      # 47 pruebas, 112 aserciones
cd botica-san-juan-frontend && npm run type-check   # verificación de tipos
```

Las pruebas cubren autenticación, autorización por rol, gestión de productos,
búsqueda de comprobantes, endpoints de salud y cabeceras de seguridad.

<br />

<img src="docs/assets/icons/git-branch.svg" width="18" align="top" />  **Flujo de trabajo**

El proyecto se gestiona con Scrum en Sprints de dos semanas. El código sigue un
flujo Git simplificado:

| Rama | Propósito |
|---|---|
| `main` | Versión estable desplegada, etiquetada por release |
| `develop` | Integración continua del desarrollo |
| `feature/hu-XX-descripcion` | Una rama por historia de usuario |
| `hotfix/descripcion` | Corrección urgente sobre producción |

Los mensajes de commit siguen la convención
[Conventional Commits](https://www.conventionalcommits.org/es/):
`feat`, `fix`, `refactor`, `test`, `docs`, `chore` y `perf`.

<br />

<img src="docs/assets/icons/book.svg" width="18" align="top" />  **Documentación**

| Documento | Contenido |
|---|---|
| [`AUDITORIA_Y_PLAN.md`](AUDITORIA_Y_PLAN.md) | Auditoría técnica con hallazgos clasificados por severidad |
| [`CAMBIOS_BLOQUE1.md`](docs/CAMBIOS_BLOQUE1.md) | Registro de cambios del bloque de fundamentos |
| [`infra/migracion/GUIA_MIGRACION_POSTGRESQL.md`](infra/migracion/GUIA_MIGRACION_POSTGRESQL.md) | Migración de SQLite a PostgreSQL paso a paso |
| [`GO_LIVE_CHECKLIST.md`](docs/GO_LIVE_CHECKLIST.md) | Verificación previa a producción |
| [`CONTINUIDAD_OPERATIVA.md`](docs/CONTINUIDAD_OPERATIVA.md) | Política de continuidad y recuperación |
| [`DEPLOYMENT.md`](docs/DEPLOYMENT.md) | Guía de despliegue |

<br />

<img src="docs/assets/icons/circle-check.svg" width="18" align="top" />  **Estado del proyecto**

| Componente | Estado |
|---|:---:|
| Base de datos PostgreSQL con 3 479 registros migrados | Operativa |
| API REST con 15 controladores | Operativa |
| Seguridad: roles, doble factor y auditoría | Operativa |
| Panel administrativo con 12 vistas | Operativa |
| Suite de 47 pruebas automatizadas | Operativa |
| Integración con la SUNAT | En desarrollo |
| Punto de venta con descuento de stock | Planificado |

<br />

---

<div align="center">

**Proyecto académico** · Curso Integrador II: Sistemas
Universidad Tecnológica del Perú · Christopher Lincoln Rafaile Naupay

Iconografía de [Lucide](https://lucide.dev), bajo licencia ISC.

</div>
