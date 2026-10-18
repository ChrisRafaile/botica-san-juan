# Runbook — Fusión de duplicados del catálogo

Procedimiento para eliminar los productos duplicados por reimportación del
catálogo. **Borra filas de forma irreversible**, así que el respaldo no es
opcional y el orden de los pasos tampoco.

Ya ejecutado en **local** el 5 de octubre de 2026: 3 361 → 884 productos,
17 086 → 4 524 unidades. En **producción (Neon)** se aplicó el 6 de octubre de 2026
durante el despliegue: el registro del contenedor detectó 869 grupos y el catálogo
quedó en 884 productos (evidencia E10). La sección «Ejecución segura en Neon» y
`scripts/fusion-duplicados-neon.sh` quedan para repetirlo ante una nueva
reimportación.

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

---

## Ejecución segura en Neon

Para producción no se corren los pasos 1 a 3 a mano: se usa
[`scripts/fusion-duplicados-neon.sh`](../scripts/fusion-duplicados-neon.sh), que
encadena las guardas, el respaldo, la simulación, la pausa y la verificación, y
se detiene ante cualquier discrepancia. El paso 4 (`migrate`) y el muestreo con
el dueño siguen siendo manuales.

```bash
# La cadena sale del panel de Neon: endpoint DIRECTO (sin -pooler), con sslmode.
# Solo en el entorno de la sesión; el script nunca la guarda ni la imprime.
read -rs DATABASE_URL && export DATABASE_URL     # pega la cadena y Enter
scripts/fusion-duplicados-neon.sh --solo-simular # ensayo: respaldo + conteos + simulación
scripts/fusion-duplicados-neon.sh                # flujo completo
scripts/fusion-duplicados-neon.sh --verificar infra/backup/salida/fusion-<sello>
```

Qué hace el script, en orden:

1. **Guardas.** Exige `DATABASE_URL`; rechaza `-pooler`, hosts que no sean
   `*.neon.tech`, ausencia de `sslmode=require`, réplicas de solo lectura,
   PostgreSQL < 15 y ejecución sin terminal. Todas las lecturas van con
   `default_transaction_read_only=on`.
2. **Conteos antes** (`antes.kv`): productos, grupos duplicados, ambiguos,
   perdedores y filas de `pedido_detalles`, `movimientos_stock`, `lotes`
   (y `incidencias_venta`, `conteo_detalles`, `carrito`) que cuelgan de
   perdedores. Calcula en SQL independiente lo que la fusión **debería** dejar
   (productos, lotes, unidades, suma de `productos.stock`).
3. **Puertas.** Aborta si hay grupos ambiguos, si tras fusionar quedarían grupos
   que violan `productos_identidad_unique` (filas que difieren solo en
   `codigo_digemid`) o si el índice ya existe con duplicados.
4. **Confirmación tipeada** `FUSIONAR <base> EN <host>`.
5. **Respaldo** `pg_dump -Fc`, con comprobación de que no está vacío, de que
   `pg_restore --list` lo lee, de que trae datos de todas las tablas, `sha256`
   y, opcionalmente (`FUSION_RESTORE_TEST_URL`, base **desechable y vacía**), un
   restore de prueba.
6. **Simulación** (`artisan` sin `--aplicar`). Sus cifras deben coincidir con el
   SQL independiente; si no, aborta (puede estar apuntando a otra base).
7. **Pausa** y segunda confirmación `APLICAR`. Se vuelve a medir: si la base
   cambió durante la pausa, aborta.
8. **Aplicar.** La salida de artisan va a un archivo abierto de antemano. **El
   resultado se decide por la base, no por el código de salida**: si artisan
   falla al escribir pero ya hizo commit, el script lo detecta y dice que no se
   repita; si la base no cambió, la transacción se revirtió y es seguro
   reintentar. Re-ejecutar sobre una base ya fusionada termina en «nada que
   hacer».
9. **Invariantes** (`despues.kv`): 0 grupos por la clave de
   `productos_identidad_unique`, 0 referencias huérfanas en las 7 relaciones,
   filas y cantidades de ventas/movimientos/incidencias/`pedido_detalle_lotes`
   intactas, ningún `movimientos_stock.lote_id` pasado a NULL, y unidades en
   lotes y suma de `productos.stock` iguales a lo calculado antes.

Todo queda en `infra/backup/salida/fusion-<sello>/` (ignorada por git, permisos
700/600): `corrida.log`, `antes.kv`, `despues.kv`, salidas de artisan, el
respaldo y `ROLLBACK.txt`.

> El stock total **sí baja** (17 086 → 4 524 en local): es lo esperado, porque
> las copias eran la misma mercancía. «Conservado» significa que el resultado
> coincide con la cifra calculada de antemano, no que el total no cambie.

### Lista de verificación previa

- [ ] Ensayo hecho con `--solo-simular` y leído el informe completo.
- [ ] Acuerdo del dueño y ventana acordada. **App en mantenimiento** (sin
      ventas POS, sin pedidos del portal, sin importaciones) desde antes del
      respaldo hasta cerrar la verificación: el comando lee los grupos *antes*
      de abrir la transacción y no bloquea tablas.
- [ ] Rama/snapshot de Neon creado a mano como segundo respaldo, y consultada
      la **ventana de retención** del plan (el rollback por punto en el tiempo
      depende de ella).
- [ ] `DATABASE_URL` es el endpoint **directo** (sin `-pooler`) y con
      `sslmode=require`.
- [ ] `pg_dump` ≥ versión del servidor (16).
- [ ] Espacio libre en disco ≥ 2 × tamaño de la base.
- [ ] `php artisan migrate:status`: sin migraciones pendientes inesperadas
      (`migrate --force` ejecuta **todas** las pendientes, no solo el índice).
- [ ] Cero grupos ambiguos y cero grupos que violen el índice (el script lo
      comprueba y aborta).

### Lista de verificación posterior

- [ ] El script terminó con «Fusión aplicada y verificada» (o `--verificar`
      sobre su carpeta da todo OK).
- [ ] `php artisan migrate --pretend` solo muestra el índice
      `productos_identidad_unique`; luego `php artisan migrate --force`
      (endpoint directo). Que falle es señal de duplicados residuales: **no**
      repetir `--aplicar` a ciegas, correr `--verificar`.
- [ ] Segunda simulación (`catalogo:fusionar-duplicados`) → «0 grupos».
- [ ] Muestreo con el dueño del stock de unos pocos productos conocidos.
- [ ] `php artisan test` contra una base local, no contra producción.
- [ ] Salir del mantenimiento; archivar la carpeta de la corrida **fuera del
      repositorio** (el volcado incluye tablas de credenciales).

### Rollback

Si el commit no llegó a ocurrir no hay nada que deshacer. Si ocurrió y hay que
volver atrás (el script escribe `ROLLBACK.txt` con la hora UTC y el LSN):

1. **Preferido:** rama de Neon desde la hora previa a la fusión y apuntar la app
   al endpoint directo de esa rama. La base original queda intacta.
2. Restaurar el `.dump` en una base **nueva y vacía**
   (`pg_restore --no-owner --exit-on-error -d <nueva>`), comparar con
   `antes.kv` y apuntar la app allí.
3. Último recurso: `pg_restore --clean --if-exists` sobre la misma base, con la
   app detenida.

Si ya se creó `productos_identidad_unique`, retirarlo antes de restaurar
(`php artisan migrate:rollback --step=1`). Todo lo vendido después de la
fusión se pierde al volver al punto previo: de ahí el mantenimiento.
