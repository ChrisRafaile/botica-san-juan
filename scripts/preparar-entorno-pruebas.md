# Preparar el entorno y correr `php artisan test`

Guia para dejar la suite del backend (`botica-san-juan-backend`) corriendo en verde.
La suite usa **PostgreSQL** (`phpunit.xml` fija `DB_CONNECTION=pgsql` y
`DB_DATABASE=botica_san_juan_test`), no SQLite.

Leyenda: **[VERIFICADO]** = lo ejecute en el entorno Linux de la sesion (PHP 8.3.6,
PostgreSQL 16.15, Composer). **[TEORICO]** = adaptacion a Windows/PowerShell que no
pude ejecutar aqui.

## Resultado verificado en Linux

`php artisan test` -> **150 pruebas, 458 aserciones, 0 fallos, exit code 0** (~4 s)
con PHP 8.3.6 y PostgreSQL 16.15. Incluye `CorrelativoComprobanteTest` (11 pruebas) y
`CheckoutPortalTest` en el estado del arbol en ese momento.

## 0. Requisitos

| Pieza | Version | Nota |
|---|---|---|
| PHP | 8.4.x recomendado | `composer.json` pide `^8.4` y `config.platform.php = 8.4.1`. Con 8.3 la suite paso, pero `composer check-platform-reqs` falla (`symfony/clock` pide >= 8.4.1). Ver "Riesgo PHP 8.3". |
| Extensiones | `pdo_pgsql`, `pgsql`, `mbstring`, `openssl`, `dom`, `xml`, `fileinfo` | **[VERIFICADO]** `php -m` |
| PostgreSQL | 16 | Mismo motor que produccion; el codigo usa `GREATEST`, `FILTER (WHERE)`, `ILIKE`, `FOR UPDATE`. |
| Composer | 2.x | |

## 1. Windows + PowerShell (ruta larga) **[TEORICO salvo donde se indique]**

La ruta del proyecto trunca nombres al extraer los ZIP de Composer (ver
`docs/ENTORNO-ruta-larga-windows.md`). Hay que montar una unidad corta antes de
`composer install`:

```powershell
# Una vez por sesion de Windows
subst X: "C:\tareas universitarios\TAREAS U\Tareas Univ\Ciclo 5\Taller de programacion Web\botica_san_juan\botica-san-juan-backend"
cd X:\
```

Despues de sincronizar (git pull) conviene borrar un `vendor\` viejo o incompleto:

```powershell
Remove-Item -Recurse -Force X:\vendor
```

## 2. Crear la base de pruebas

PostgreSQL debe estar corriendo (servicio de Windows o instalador de EDB). Ajusta
usuario/puerto a tu instalacion.

```powershell
# Pide la contrasena de 'postgres' si la tiene
psql -h 127.0.0.1 -U postgres -c "CREATE DATABASE botica_san_juan_test;"
```

**[VERIFICADO en Linux]** el equivalente `psql -h 127.0.0.1 -U postgres -c "create database botica_san_juan_test"`
respondio `CREATE DATABASE`. Las migraciones las aplica la propia suite
(`RefreshDatabase`), no hay que migrar a mano. Usa una base **local**: nunca apuntes
la suite a Neon/produccion, porque `RefreshDatabase` borra y recrea tablas.

## 3. Variables de entorno

```powershell
cd X:\
Copy-Item .env.example .env
php artisan key:generate
```

Edita `.env` y deja la conexion local (el `DB_DATABASE` lo pisa `phpunit.xml`):

```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_USERNAME=postgres
DB_PASSWORD=<tu contrasena local, o vacio si usas trust>
```

Hallazgo **[VERIFICADO]**: sin `.env`, `php artisan test` marca TODAS las pruebas
como warning (`!`) con `file_get_contents(.../.env): Failed to open stream`, aunque
`vendor/bin/phpunit` directo las pasa. No es un fallo del codigo; es `.env` ausente.
`.env` esta en `.gitignore`. (No hay `.env.testing` en el repo; no hace falta.)

Alternativa sin tocar `.env`, solo para la sesion de PowerShell (**[TEORICO]**):

```powershell
$env:APP_KEY = "base64:..."   # el valor que genere key:generate
$env:DB_HOST = "127.0.0.1"; $env:DB_USERNAME = "postgres"; $env:DB_PASSWORD = "..."
```

## 4. Instalar dependencias

```powershell
cd X:\
composer install --prefer-dist --no-interaction --no-progress
```

- Si el proxy/red corta por timeout: `$env:COMPOSER_PROCESS_TIMEOUT = 3000` y reintenta.
- Si tu PHP es 8.3: agrega `--ignore-platform-req=php` (ver riesgo abajo).
- **[VERIFICADO en Linux]** `composer install --prefer-dist --ignore-platform-req=php`
  con `COMPOSER_PROCESS_TIMEOUT=3000` completo y dejo `vendor/` entero. Aviso benigno:
  "Ambiguous class resolution" de `League\Flysystem\Local\*` y `App\Providers\AppServiceProvider` de pint.
- Verifica que no haya archivos truncados (solo Windows, **[TEORICO]**):
  `Get-ChildItem X:\vendor\phpunit\phpunit\src\Event\Events\Test\TestDouble -Name | Select-String "Su$"` no debe devolver nada.

## 5. Correr la suite

```powershell
cd X:\
php artisan test
# o una sola clase / prueba:
php artisan test --filter=CorrelativoComprobanteTest
php artisan test --filter="un ruc emite factura f001"
```

Para ver el mensaje completo de un fallo/warning (la consola recorta con "…"):

```powershell
$env:COLUMNS = 250; php artisan test
# o saltarse el formato de Collision:
php vendor/bin/phpunit
```

Salida esperada: `Tests: 150 passed (458 assertions)` (el numero crecera si se anaden pruebas).
Los avisos "Metadata in doc-comments is deprecated" (PHPUnit 11) son inofensivos.

Al terminar: `subst X: /D`.

## 6. Como interpretar un fallo

| Sintoma | Causa tipica | Que hacer |
|---|---|---|
| `SQLSTATE[08006] connection refused` / `could not translate host` | PostgreSQL apagado o credenciales mal en `.env` | Levanta el servicio, revisa `DB_HOST/PORT/USERNAME/PASSWORD`. Entorno. |
| `database "botica_san_juan_test" does not exist` | Falto el paso 2 | `CREATE DATABASE`. Entorno. |
| Todas las pruebas con `!` y `file_get_contents(.env)` | Falta `.env` | Paso 3. Entorno. |
| `UnknownSubscriberException` / clase PHPUnit inexistente | Nombres truncados en `vendor/` (Windows) | `subst`, borrar `vendor\`, reinstalar. Entorno. |
| `Your Composer dependencies require a PHP version ">= 8.4.1"` | PHP < 8.4 en tiempo de ejecucion | Actualizar PHP o ver riesgo abajo. Entorno. |
| `relation "..." does not exist`, `column ... does not exist`, `duplicate key` al migrar | Migracion nueva/rota o base sucia | `php artisan migrate:fresh --env=testing` contra la base de pruebas; si persiste es de migracion/datos. |
| `Failed asserting that ...` / 4xx-5xx inesperado en una sola prueba | Bug real del codigo o prueba desactualizada | Leer la asercion y el stack; reproducir con `--filter`. Codigo. |
| Fallos solo en facturacion/series/correlativo | Trabajo en curso en `ComprobanteService`/migraciones del correlativo | Pueden ser transitorios; reintentar tras sincronizar el ultimo commit. |

Regla rapida: si `php vendor/bin/phpunit` pasa pero `php artisan test` no, es entorno
(`.env`, consola). Si falla igual en ambos, mira el nombre de la prueba y la excepcion.

## Riesgo PHP 8.3 (si no puedes tener 8.4)

**[VERIFICADO]** con PHP 8.3.6 la suite completa paso (150/150) usando
`--ignore-platform-req=php`. Riesgos: `composer.lock` fue resuelto para 8.4.1
(`symfony/clock` >= 8.4.1 y posiblemente otros componentes Symfony 8); solo se ejecutaron
las rutas que cubren las pruebas, asi que una incompatibilidad en un camino no probado
podria aparecer en produccion si esta corre 8.4 y local 8.3 (o al reves). Para
resultados fiables, usa 8.4.

## CI (revision de `.github`)

Los workflows activos de GitHub son los de la **raiz** `.github/workflows/`:

- `ci-cd.yml`: jobs `secrets-scan` (pwsh `infra/security/scan_secrets.ps1`),
  `backend-tests` (PHP **8.3**, `composer install`, `cp .env.example .env`,
  `key:generate`, `php artisan test`) y `frontend-build` (Node 22, pnpm).
- Hay otro conjunto bajo `botica-san-juan-backend/.github/workflows/` (`tests.yml` con
  matriz PHP 8.2/8.3/8.4, `issues.yml`, `pull-requests.yml`, `update-changelog.yml`).
  GitHub **no** ejecuta workflows de subcarpetas; es un resto de la plantilla Laravel.

**Problema detectado (no corregido, fuera del alcance):** `ci-cd.yml` instala la
extension `pdo_pgsql` pero **no declara un servicio PostgreSQL** ni crea
`botica_san_juan_test`; el comentario dice que las pruebas usan SQLite, lo cual ya no es
cierto (`phpunit.xml` usa pgsql). Con `.env.example` (`DB_HOST=127.0.0.1`,
`DB_USERNAME=postgres`) el job `backend-tests` fallara por conexion rechazada. Ademas
usa PHP 8.3 contra un lock que pide 8.4.1 (`composer install` pasa por
`config.platform`, pero el runtime no cumple). Correccion sugerida: bloque
`services: postgres: image: postgres:16` con `POSTGRES_DB=botica_san_juan_test`, puerto 5432
y `php-version: '8.4'`. **[TEORICO]**: no se ejecuto GitHub Actions.
