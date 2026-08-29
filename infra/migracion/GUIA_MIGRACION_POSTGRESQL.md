# Guía de migración a PostgreSQL 16

Sistema Web de Gestión y Control — Botica San Juan

Esta guía lleva el proyecto de SQLite a PostgreSQL 16 conservando los **3 483 registros** actuales, entre ellos los 3 365 productos del catálogo.

---

## 1. Instalar PostgreSQL

1. Descargar el instalador de PostgreSQL 16 desde `https://www.postgresql.org/download/windows/`.
2. Durante la instalación, anotar la contraseña del usuario `postgres`.
3. Al finalizar, agregar la carpeta `bin` al PATH del sistema:

```
C:\Program Files\PostgreSQL\16\bin
```

4. Verificar en una terminal nueva:

```powershell
psql --version
pg_dump --version
```

---

## 2. Habilitar la extensión de PHP

En el `php.ini` de Laragon (o de la instalación de PHP que uses), quitar el punto y coma inicial de estas dos líneas:

```ini
extension=pdo_pgsql
extension=pgsql
```

Reiniciar Apache/PHP y comprobar:

```powershell
php -m | Select-String pgsql
```

Deben aparecer `pdo_pgsql` y `pgsql`.

---

## 3. Crear la base de datos

```powershell
psql -U postgres -c "CREATE DATABASE botica_san_juan ENCODING 'UTF8';"
```

Opcionalmente, una base separada para los simulacros de restauración:

```powershell
psql -U postgres -c "CREATE DATABASE botica_staging ENCODING 'UTF8';"
```

---

## 4. Configurar el proyecto

En `botica-san-juan-backend\.env`, reemplazar el bloque de base de datos por:

```ini
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=botica_san_juan
DB_USERNAME=postgres
DB_PASSWORD=tu_contrasena
DB_CHARSET=utf8
DB_SSLMODE=prefer
```

Limpiar la configuración en caché:

```powershell
cd botica-san-juan-backend
php artisan config:clear
php artisan cache:clear
```

---

## 5. Crear el esquema

```powershell
php artisan migrate --force
```

Las 26 migraciones son compatibles con PostgreSQL. Dos notas sobre la traducción que hace Laravel:

| Elemento | En SQLite | En PostgreSQL |
|---|---|---|
| `$table->enum(...)` | `varchar` sin restricción | `varchar` + restricción `CHECK` |
| `$table->boolean(...)` | `tinyint(1)` con 0/1 | `boolean` con `true`/`false` |
| `->after('columna')` | ignorado | ignorado (no produce error) |
| `$table->id()` | `INTEGER PRIMARY KEY` | `bigserial` con secuencia |

Verificar que el esquema se creó:

```powershell
psql -U postgres -d botica_san_juan -c "\dt"
```

---

## 6. Migrar los datos

Desde la raíz del proyecto:

```powershell
python infra\migracion\exportar_sqlite_a_postgres.py
```

El script genera `infra\migracion\datos_postgres.sql` y muestra el detalle de lo exportado:

```
usuarios                        4
categorias                      6
subcategorias                  14
proveedores                     3
digemid_catalogos               5
productos                    3365
compras                         3
pedidos                        32
pedido_detalles                11
comprobantes_electronicos      32
comisiones                      5
carrito                         0
contacto                        3
```

Cargar el archivo:

```powershell
psql -U postgres -d botica_san_juan -f infra\migracion\datos_postgres.sql
```

El archivo se ejecuta dentro de una transacción: si algo falla, no queda nada a medias.

---

## 7. Verificar la migración

```powershell
psql -U postgres -d botica_san_juan -c "SELECT (SELECT COUNT(*) FROM productos) AS productos, (SELECT COUNT(*) FROM pedidos) AS pedidos, (SELECT COUNT(*) FROM comprobantes_electronicos) AS comprobantes, (SELECT COUNT(*) FROM usuarios) AS usuarios;"
```

Resultado esperado:

```
 productos | pedidos | comprobantes | usuarios
-----------+---------+--------------+----------
      3365 |      32 |           32 |        4
```

Comprobar que las secuencias quedaron alineadas (un nuevo registro no debe chocar con un id existente):

```powershell
psql -U postgres -d botica_san_juan -c "SELECT last_value FROM productos_id_seq;"
```

Debe devolver `3365` o el máximo id de la tabla.

Por último, levantar la API y verificar los endpoints de salud:

```powershell
php artisan serve --port=8083
```

```
GET http://127.0.0.1:8083/api/health
GET http://127.0.0.1:8083/api/health/db
```

Ambos deben responder `"status": "ok"`.

---

## 8. Actualizar el respaldo

Los scripts de SQLite quedan obsoletos. A partir de ahora:

```powershell
# Respaldo (formato custom comprimido + hash SHA-256 + evidencia JSON)
$env:PGPASSWORD = "tu_contrasena"
pwsh -File .\infra\backup\backup_postgres.ps1

# Simulacro de restauración sobre la base de staging
pwsh -File .\infra\backup\restore_postgres.ps1 `
     -Archivo .\botica-san-juan-backend\logs\backup-evidence\<marca>\botica_san_juan-<marca>.dump `
     -Database botica_staging
```

El script de restauración exige el parámetro `-Confirmar` si la base destino no contiene `staging`, `test` o `dev` en su nombre, para evitar sobrescribir producción por accidente.

---

## 9. Retirar los archivos de SQLite

Una vez verificada la migración, conservar una copia del archivo `.sqlite` fuera del repositorio como respaldo histórico y eliminar del control de versiones:

```
botica-san-juan-backend/database/database.sqlite
botica-san-juan-backend/database/database.sqlite.pre-restore.*.bak
infra/backup/backup_sqlite.ps1
infra/backup/restore_sqlite.ps1
infra/backup/run_restore_drill.ps1
```

Las pruebas automatizadas **siguen usando SQLite en memoria** (`phpunit.xml` ya lo declara), lo que las mantiene rápidas y sin dependencia de un servidor de base de datos.

---

## 10. Lista de verificación

- [ ] `psql --version` responde
- [ ] `php -m` muestra `pdo_pgsql`
- [ ] Base `botica_san_juan` creada
- [ ] `.env` con `DB_CONNECTION=pgsql`
- [ ] `php artisan migrate --force` sin errores
- [ ] Datos cargados y conteos verificados
- [ ] Secuencias alineadas
- [ ] `/api/health/db` responde `ok`
- [ ] Respaldo ejecutado con evidencia JSON
- [ ] Simulacro de restauración exitoso sobre `botica_staging`
