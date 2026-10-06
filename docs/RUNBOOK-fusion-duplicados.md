# Runbook — Fusión de duplicados del catálogo

Procedimiento para eliminar los productos duplicados por reimportación del
catálogo. **Borra filas de forma irreversible**, así que el respaldo no es
opcional y el orden de los pasos tampoco.

Ya ejecutado en **local** el 5 de octubre de 2026: 3 361 → 884 productos,
17 086 → 4 524 unidades. Queda pendiente **producción (Neon)**.

---

## Qué hace y por qué

El catálogo se importó cuatro veces. Resultado: 869 grupos de filas idénticas
en nombre, concentración, presentación, laboratorio, tipo, adicional y código
DIGEMID.

**No se suman los stocks.** De los 869 grupos, 858 tenían el stock idéntico en
todas sus copias (8,8,8,8 · 10,10,10,10). Eso es la misma mercancía física
reimportada, no cuatro entregas: sumarla habría llevado el inventario de 4 434
a 16 975 unidades y el punto de venta habría prometido existencias que no están
en el anaquel.

**El superviviente se elige por actividad.** Si exactamente una fila del grupo
tiene movimientos de stock, ventas o incidencias, esa es la que se conserva —
tenga el id que tenga — porque es la que se ha estado vendiendo y su stock es el
real. Las copias conservan el valor de importación sin tocar. Se midió: en los
11 grupos con stock discordante, la fila con actividad era siempre la de menos
stock, y en uno de ellos (AMOXICILINA PHARMAGEN) **no** era la de id menor.

Si ninguna fila tiene actividad, se conserva la de id menor. Si más de una la
tiene, el grupo se omite y se lista para decidir a mano.

El **precio** del superviviente se toma del id mayor del grupo: la importación
más reciente trae el precio vigente.

---

## Requisitos previos

- `pg_dump` y `psql` de PostgreSQL 16 (vienen con la instalación del servidor).
- Acceso a la base de producción en Neon.
- **Usar el endpoint DIRECTO de Neon, nunca el `-pooler`.** El agrupador es
  PgBouncer en modo transacción y no conserva las sentencias preparadas de PDO;
  las migraciones fallan con `SQLSTATE[25P02]` señalando la sentencia siguiente.

---

## Paso 1 — Respaldo

**Local (Windows PowerShell):**

```powershell
$dump   = "C:\Archivos de programa\PostgreSQL\16\bin\pg_dump.exe"
$sello  = Get-Date -Format "yyyyMMdd-HHmmss"
$salida = "infra\backup\salida\botica_pre_fusion_$sello.dump"

& $dump -h 127.0.0.1 -p 5432 -U postgres -d botica_san_juan -Fc -f $salida
```

**Producción (Neon):** la cadena de conexión sale del panel de Neon. No la
escribas en el comando ni la dejes en el historial del terminal: pásala por
variable de entorno.

```powershell
# Pega aquí la cadena DIRECTA (sin -pooler) que da el panel de Neon.
# $env:PGPASSWORD evita que la contraseña aparezca en la linea de comando.
$env:PGHOST     = "ep-XXXX.sa-east-1.aws.neon.tech"   # endpoint DIRECTO
$env:PGDATABASE = "botica_san_juan"
$env:PGUSER     = "botica_app"
$env:PGPASSWORD = Read-Host -AsSecureString | ConvertFrom-SecureString -AsPlainText
$env:PGSSLMODE  = "require"

$sello = Get-Date -Format "yyyyMMdd-HHmmss"
& $dump -Fc -f "infra\backup\salida\neon_pre_fusion_$sello.dump"
```

> `infra/backup/salida/` está en `.gitignore`. Un volcado contiene el esquema
> completo, incluidas tablas de credenciales: **nunca se sube al repositorio.**

Comprueba que el archivo pesa algo y que se puede leer antes de seguir:

```powershell
pg_restore --list "infra\backup\salida\neon_pre_fusion_$sello.dump" | Select-Object -First 5
```

---

## Paso 2 — Simular y leer el informe

```bash
php artisan catalogo:fusionar-duplicados
```

No escribe nada. Imprime los grupos encontrados, los diez primeros con detalle,
las filas que se eliminarían, las unidades fantasma que se descartan y los
grupos que quedan ambiguos.

**Lee el bloque de grupos ambiguos antes de continuar.** Si sale alguno, decide
qué hacer con él a mano: ahí dos filas distintas tienen historial y el comando
no adivina cuál es la buena.

---

## Paso 3 — Aplicar

```bash
php artisan catalogo:fusionar-duplicados --aplicar
```

Todo va en una transacción: si algo falla, no queda nada a medias.

> `--sumar-stock` existe pero **no se usa aquí**. Solo tiene sentido si las
> copias correspondieran a entregas físicas distintas, que no es este caso.

---

## Paso 4 — Asentar el índice UNIQUE

```bash
php artisan migrate --force
```

Añade un índice único sobre la identidad del producto, con `NULLS NOT
DISTINCT`: un UNIQUE normal no habría impedido nada donde `concentracion` o
`adicional` son NULL, porque en SQL dos NULL no son iguales.

**Si la migración falla, es señal buena**: quedan duplicados sin fusionar.
Vuelve al paso 2.

---

## Paso 5 — Verificar

```bash
php artisan test
php artisan catalogo:fusionar-duplicados        # debe decir 0 grupos
```

Y contra la base, que es lo que de verdad importa:

```sql
-- No deben quedar grupos repetidos
SELECT COUNT(*) FROM (
  SELECT 1 FROM productos
  GROUP BY nombre, concentracion, presentacion, laboratorio, tipo, adicional, codigo_digemid
  HAVING COUNT(*) > 1
) d;

-- Ninguna venta ni movimiento debe haber quedado huérfano
SELECT COUNT(*) FROM pedido_detalles pd
  LEFT JOIN productos p ON p.id = pd.producto_id WHERE p.id IS NULL;
SELECT COUNT(*) FROM movimientos_stock m
  LEFT JOIN productos p ON p.id = m.producto_id WHERE p.id IS NULL;

-- Inventario
SELECT COUNT(*) AS productos FROM productos;
SELECT COUNT(*) AS lotes, SUM(cantidad_actual) AS unidades FROM lotes;
```

Resultado esperado en local tras la ejecución del 5 oct 2026:

| | antes | después |
|---|---|---|
| productos | 3 361 | 884 |
| lotes | 3 363 | 886 |
| unidades | 17 086 | 4 524 |
| grupos duplicados | 869 | 0 |
| ventas / movimientos / incidencias | 23 / 12 / 2 | 23 / 12 / 2 (sin huérfanos) |

---

## Si hay que volver atrás

```powershell
# Sobre una base VACIA, o con --clean para reemplazar el contenido existente
pg_restore --clean --if-exists -d botica_san_juan "infra\backup\salida\<archivo>.dump"
```

En Neon, lo más seguro no es restaurar encima sino **crear una rama desde el
punto en el tiempo anterior a la ejecución** (Neon guarda historial) y apuntar
ahí la aplicación. Eso deja la base original intacta mientras se comprueba.

---

## Después, en producción

El inventario de producción cambia de golpe. Antes de que el dueño vuelva a
vender conviene:

1. Revisar en el panel que las cifras de stock cuadran con lo que él recuerda
   tener en el anaquel de unos pocos productos conocidos.
2. Si algo no cuadra, el sistema ya tiene **conteo por ciclos**
   (`/admin/inventory/conteo`): contar 20-30 productos al día por rotación
   corrige el inventario sin parar la botica, y de paso va poblando las fechas
   de vencimiento que hoy faltan.
