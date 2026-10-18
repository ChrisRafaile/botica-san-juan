#!/usr/bin/env bash
# =============================================================================
# fusion-duplicados-neon.sh
# Ejecución SEGURA de `php artisan catalogo:fusionar-duplicados` contra Neon.
#
#   export DATABASE_URL='postgresql://USUARIO:CLAVE@ep-XXXX.REGION.aws.neon.tech/BD?sslmode=require'
#   scripts/fusion-duplicados-neon.sh                 # flujo completo, interactivo
#   scripts/fusion-duplicados-neon.sh --solo-simular  # respaldo + conteos + simulación, SIN aplicar
#   scripts/fusion-duplicados-neon.sh --verificar DIR # solo comprueba invariantes de una corrida previa
#
# GARANTÍAS
#   * La cadena de conexión SOLO viaja por la variable DATABASE_URL: no se lee
#     de ningún archivo, no se escribe en ningún archivo ni en el log y no se
#     pasa por argv (no aparece en `ps`).
#   * Rechaza el endpoint `-pooler` (PgBouncer rompe sentencias preparadas).
#   * Todas las lecturas de psql van en modo solo-lectura. La ÚNICA escritura
#     sobre la base es `artisan ... --aplicar`, y va tras dos confirmaciones
#     tipeadas.
#   * La decisión de "¿ya se aplicó?" se toma SIEMPRE mirando la base, nunca la
#     salida de artisan (ver "IDEMPOTENCIA").
#   * Nunca se usa --sumar-stock.
#
# IDEMPOTENCIA (el caso del informe: --aplicar falló al escribir el archivo de
# salida pero la transacción ya estaba confirmada)
#   La salida de artisan va a un archivo ABIERTO ANTES de lanzarlo (si no se
#   puede abrir, artisan ni arranca). Aun así, el resultado de la corrida se
#   decide por las invariantes en la base: si artisan devuelve error pero la base
#   ya está fusionada, el script lo detecta y NO repite; si la base sigue sin
#   fusionar, nada se aplicó (la transacción es atómica) y se puede reintentar.
#   Re-ejecutar el script sobre una base ya fusionada termina en "nada que hacer".
#
# VARIABLES OPCIONALES
#   FUSION_DIR            carpeta de la corrida (def.: infra/backup/salida/fusion-<sello>)
#   FUSION_BACKEND_DIR    carpeta de Laravel (def.: botica-san-juan-backend)
#   FUSION_ARTISAN_CMD    def.: "php artisan"
#   FUSION_RESTORE_TEST_URL  BD DESECHABLE y VACÍA donde probar pg_restore del respaldo
#   FUSION_MODO_PRUEBA=1  SOLO para pruebas locales: permite hosts que no son Neon
# =============================================================================
set -Eeuo pipefail
umask 077

REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKEND_DIR="${FUSION_BACKEND_DIR:-$REPO/botica-san-juan-backend}"
SELLO="$(date -u +%Y%m%d-%H%M%S)"
MODO="completo"
VERIFICAR_DIR=""

die()  { printf '\n[ABORTA] %s\n' "$*" >&2; exit 1; }
info() { printf '[%s] %s\n' "$(date -u +%H:%M:%S)" "$*"; }
ok()   { printf '   OK   %s\n' "$*"; }
fallo() { printf '   FALLO %s\n' "$*"; FALLOS=$((FALLOS + 1)); }
FALLOS=0

while [ $# -gt 0 ]; do
  case "$1" in
    --solo-simular) MODO="simular" ;;
    --verificar)    MODO="verificar"; shift; VERIFICAR_DIR="${1:-}"; [ -n "$VERIFICAR_DIR" ] || die "--verificar necesita la carpeta de la corrida" ;;
    -h|--help)      sed -n '2,45p' "${BASH_SOURCE[0]}"; exit 0 ;;
    *)              die "Argumento desconocido: $1" ;;
  esac
  shift
done

# ----------------------------------------------------------------------------
# 0. Guardas sobre la conexión (antes de tocar nada)
# ----------------------------------------------------------------------------
[ -n "${DATABASE_URL:-}" ] || die "Falta DATABASE_URL en el entorno (endpoint DIRECTO de Neon)."

URL_RE='^postgres(ql)?://(([^:@/]*)(:([^@/]*))?@)?([^/:?@]+)(:([0-9]+))?/([^?]+)(\?(.*))?$'
[[ "$DATABASE_URL" =~ $URL_RE ]] || die "DATABASE_URL no tiene la forma postgresql://usuario:clave@host/bd?sslmode=require"
urldecode() { local t="$1"; printf '%b' "${t//%/\\x}"; }
CX_USER="$(urldecode "${BASH_REMATCH[3]:-}")"
CX_PASS="$(urldecode "${BASH_REMATCH[5]:-}")"
CX_HOST="${BASH_REMATCH[6]}"
CX_PORT="${BASH_REMATCH[8]:-5432}"
CX_NAME="$(urldecode "${BASH_REMATCH[9]}")"
CX_QUERY="${BASH_REMATCH[11]:-}"

case "$DATABASE_URL" in
  *-pooler*) die "El endpoint es '-pooler' (PgBouncer). Rompe sentencias preparadas y migraciones (SQLSTATE 25P02). Usa el endpoint DIRECTO." ;;
esac

if [ "${FUSION_MODO_PRUEBA:-0}" != "1" ]; then
  [[ "$CX_HOST" == *.neon.tech ]] || die "El host '$CX_HOST' no es de Neon (*.neon.tech). Si es una prueba local usa FUSION_MODO_PRUEBA=1."
  [[ "$CX_QUERY" == *sslmode=require* || "$CX_QUERY" == *sslmode=verify-* ]] || die "Añade ?sslmode=require a DATABASE_URL."
  [ -t 0 ] || die "Se necesita un terminal interactivo (las confirmaciones se tipean)."
else
  printf '\n*** FUSION_MODO_PRUEBA=1: guardas de host/SSL/terminal desactivadas. NO usar contra producción. ***\n'
fi

for bin in psql pg_dump pg_restore sha256sum; do
  command -v "$bin" >/dev/null 2>&1 || die "No encuentro '$bin' en el PATH."
done
read -r -a ARTISAN <<<"${FUSION_ARTISAN_CMD:-php artisan}"
command -v "${ARTISAN[0]}" >/dev/null 2>&1 || die "No encuentro '${ARTISAN[0]}'."
[ -d "$BACKEND_DIR" ] || die "No existe la carpeta del backend: $BACKEND_DIR"

# La URL se descompone en variables PG* (libpq NO expande una URL puesta en
# PGDATABASE) y a Laravel le llega por DB_URL: nada de esto va en argv.
unset PGSERVICE PGPASSFILE PGSSLROOTCERT 2>/dev/null || true
export PGHOST="$CX_HOST" PGPORT="$CX_PORT" PGUSER="$CX_USER" PGDATABASE="$CX_NAME"
if [ -n "$CX_PASS" ]; then export PGPASSWORD="$CX_PASS"; else unset PGPASSWORD; fi
SSLMODE="$(sed -n 's/.*sslmode=\([a-z-]*\).*/\1/p' <<<"$CX_QUERY")"
export PGSSLMODE="${SSLMODE:-require}"
export PGCONNECT_TIMEOUT=20
export PGAPPNAME="fusion-duplicados"
export PGOPTIONS="-c default_transaction_read_only=on -c statement_timeout=120000"

psql_q() { psql -X -A -t -v ON_ERROR_STOP=1 "$@"; }

# Neon suspende el cómputo en reposo: la primera conexión puede tardar o fallar.
conectado=0
for intento in 1 2 3 4; do
  if psql_q -c 'SELECT 1' >/dev/null 2>&1; then conectado=1; break; fi
  info "Sin conexión (intento $intento/4); Neon puede estar despertando el cómputo..."
  sleep $((intento * 4))
done
[ "$conectado" = "1" ] || die "No se pudo conectar a $CX_HOST (revisa usuario, clave y que el endpoint sea el directo)."

IDENT="$(psql_q -F'|' -c "SELECT current_database(), current_user, inet_server_addr()::text, version(), current_setting('server_version_num')::int, pg_is_in_recovery()")"
IFS='|' read -r BD_REAL USUARIO _IP VERSION SRV_NUM EN_RECOVERY <<<"$IDENT"
[ "$EN_RECOVERY" = "f" ] || die "La base está en recuperación/réplica de solo lectura."
[ "$SRV_NUM" -ge 150000 ] || die "PostgreSQL < 15: el índice con NULLS NOT DISTINCT de la migración no existe."
[ "$BD_REAL" = "$CX_NAME" ] || die "La base real ($BD_REAL) no coincide con la de la URL ($CX_NAME)."

printf '\nDestino   : %s / base %s / usuario %s\n' "$CX_HOST" "$BD_REAL" "$USUARIO"
printf 'Servidor  : %s\n' "$VERSION"
printf 'pg_dump   : %s\n\n' "$(pg_dump --version)"

# ----------------------------------------------------------------------------
# Métricas: una sola consulta, formato clave=valor. Replica en SQL la lógica de
# FusionarDuplicadosCatalogo (grupo por 7 campos con NULL iguales; superviviente
# = única fila con actividad, si no el id menor; >1 viva = ambiguo).
# ----------------------------------------------------------------------------
metricas() {
  psql_q -F= <<'SQL'
WITH act AS (
  SELECT producto_id, count(*) AS n FROM (
    SELECT producto_id FROM movimientos_stock
    UNION ALL SELECT producto_id FROM pedido_detalles
    UNION ALL SELECT producto_id FROM incidencias_venta
  ) x GROUP BY producto_id
),
g AS (
  SELECT p.id,
         count(*)                                         OVER w AS n,
         count(*) FILTER (WHERE COALESCE(a.n,0) > 0)      OVER w AS nvivas,
         min(p.id)                                        OVER w AS minid,
         min(p.id) FILTER (WHERE COALESCE(a.n,0) > 0)     OVER w AS vivaid
  FROM productos p LEFT JOIN act a ON a.producto_id = p.id
  WINDOW w AS (PARTITION BY p.nombre, p.concentracion, p.presentacion, p.laboratorio,
                            p.tipo, p.adicional, p.codigo_digemid)
),
d AS (SELECT id, n, nvivas, minid,
             CASE WHEN nvivas = 1 THEN vivaid ELSE minid END AS surv
      FROM g WHERE n > 1),
m AS (SELECT * FROM d WHERE nvivas <= 1),                    -- se fusionarán
perd AS (SELECT id FROM m WHERE id <> surv),
lp AS (SELECT l.id, l.cantidad_actual,
              (EXISTS (SELECT 1 FROM movimientos_stock ms WHERE ms.lote_id = l.id)
            OR EXISTS (SELECT 1 FROM pedido_detalle_lotes pl WHERE pl.lote_id = l.id)) AS traza
       FROM lotes l WHERE l.producto_id IN (SELECT id FROM perd))
SELECT 'productos', count(*) FROM productos
UNION ALL SELECT 'grupos_dup_comando',     count(*) FROM d WHERE id = minid
UNION ALL SELECT 'grupos_ambiguos',        count(*) FROM d WHERE id = minid AND nvivas > 1
UNION ALL SELECT 'grupos_a_fusionar',      count(*) FROM m WHERE id = minid
UNION ALL SELECT 'perdedores',             count(*) FROM perd
UNION ALL SELECT 'perd_pedido_detalles',   count(*) FROM pedido_detalles   WHERE producto_id IN (SELECT id FROM perd)
UNION ALL SELECT 'perd_movimientos_stock', count(*) FROM movimientos_stock WHERE producto_id IN (SELECT id FROM perd)
UNION ALL SELECT 'perd_incidencias_venta', count(*) FROM incidencias_venta WHERE producto_id IN (SELECT id FROM perd)
UNION ALL SELECT 'perd_conteo_detalles',   count(*) FROM conteo_detalles   WHERE producto_id IN (SELECT id FROM perd)
UNION ALL SELECT 'perd_carrito',           count(*) FROM carrito           WHERE producto_id IN (SELECT id FROM perd)
UNION ALL SELECT 'perd_lotes',             count(*) FROM lp
UNION ALL SELECT 'perd_lotes_con_traza',   count(*) FROM lp WHERE traza
UNION ALL SELECT 'pedido_detalles',        count(*) FROM pedido_detalles
UNION ALL SELECT 'pedido_detalles_cant',   COALESCE(sum(cantidad),0) FROM pedido_detalles
UNION ALL SELECT 'movimientos_stock',      count(*) FROM movimientos_stock
UNION ALL SELECT 'movimientos_lote_null',  count(*) FROM movimientos_stock WHERE lote_id IS NULL
UNION ALL SELECT 'incidencias_venta',      count(*) FROM incidencias_venta
UNION ALL SELECT 'pedido_detalle_lotes',   count(*) FROM pedido_detalle_lotes
UNION ALL SELECT 'lotes',                  count(*) FROM lotes
UNION ALL SELECT 'lotes_unidades',         COALESCE(sum(cantidad_actual),0) FROM lotes
UNION ALL SELECT 'productos_stock_suma',   COALESCE(sum(stock),0) FROM productos
UNION ALL SELECT 'stock_inconsistente',    count(*) FROM productos p
          WHERE p.stock <> COALESCE((SELECT sum(l.cantidad_actual) FROM lotes l WHERE l.producto_id = p.id),0)
UNION ALL SELECT 'grupos_dup_indice',      count(*) FROM (
          SELECT 1 FROM productos
          GROUP BY nombre, concentracion, presentacion, laboratorio, tipo, adicional
          HAVING count(*) > 1) q
UNION ALL SELECT 'grupos_indice_tras_fusion', count(*) FROM (
          SELECT 1 FROM productos WHERE id NOT IN (SELECT id FROM perd)
          GROUP BY nombre, concentracion, presentacion, laboratorio, tipo, adicional
          HAVING count(*) > 1) q
UNION ALL SELECT 'indice_unique_existe',   (to_regclass('public.productos_identidad_unique') IS NOT NULL)::int
-- ESPERADOS tras la fusión (estrategia por omisión: se descartan lotes de copias,
-- los de las copias con trazabilidad se conservan a cero)
UNION ALL SELECT 'esp_productos',          (SELECT count(*) FROM productos) - (SELECT count(*) FROM perd)
UNION ALL SELECT 'esp_lotes',              (SELECT count(*) FROM lotes) - (SELECT count(*) FROM lp WHERE NOT traza)
UNION ALL SELECT 'esp_lotes_unidades',     COALESCE((SELECT sum(cantidad_actual) FROM lotes
                                                      WHERE producto_id NOT IN (SELECT id FROM perd)),0)
UNION ALL SELECT 'esp_productos_stock',
          COALESCE((SELECT sum(stock) FROM productos
                     WHERE id NOT IN (SELECT id FROM m)),0)
        + COALESCE((SELECT sum(l.cantidad_actual) FROM lotes l
                     WHERE l.producto_id IN (SELECT id FROM m WHERE id = surv)),0)
UNION ALL SELECT 'orf_pedido_detalles',    count(*) FROM pedido_detalles x LEFT JOIN productos p ON p.id = x.producto_id WHERE p.id IS NULL
UNION ALL SELECT 'orf_movimientos_stock',  count(*) FROM movimientos_stock x LEFT JOIN productos p ON p.id = x.producto_id WHERE p.id IS NULL
UNION ALL SELECT 'orf_incidencias_venta',  count(*) FROM incidencias_venta x LEFT JOIN productos p ON p.id = x.producto_id WHERE p.id IS NULL
UNION ALL SELECT 'orf_conteo_detalles',    count(*) FROM conteo_detalles x LEFT JOIN productos p ON p.id = x.producto_id WHERE p.id IS NULL
UNION ALL SELECT 'orf_carrito',            count(*) FROM carrito x LEFT JOIN productos p ON p.id = x.producto_id WHERE p.id IS NULL
UNION ALL SELECT 'orf_lotes',              count(*) FROM lotes x LEFT JOIN productos p ON p.id = x.producto_id WHERE p.id IS NULL
UNION ALL SELECT 'orf_pedido_detalle_lotes', count(*) FROM pedido_detalle_lotes x LEFT JOIN lotes l ON l.id = x.lote_id WHERE l.id IS NULL
UNION ALL SELECT 'orf_movimientos_lote',   count(*) FROM movimientos_stock x LEFT JOIN lotes l ON l.id = x.lote_id WHERE x.lote_id IS NOT NULL AND l.id IS NULL;
SQL
}

declare -A A D   # A = antes, D = después
cargar() { # cargar ARRAY archivo
  local -n destino="$1"; local k v
  # shellcheck disable=SC2034  # destino es una referencia (nameref) a A o D
  while IFS='=' read -r k v; do if [ -n "$k" ]; then destino["$k"]="$v"; fi; done <"$2"
}

# ----------------------------------------------------------------------------
# Modo --verificar: solo invariantes, con el "antes" de una corrida previa
# ----------------------------------------------------------------------------
verificar_invariantes() {
  FALLOS=0
  eq() { # eq descripción valor_real valor_esperado
    if [ "$2" = "$3" ]; then ok "$1 = $2"; else fallo "$1: real=$2 esperado=$3"; fi
  }
  info "Invariantes posteriores"
  eq "productos"                       "${D[productos]}"            "${A[esp_productos]}"
  eq "grupos duplicados (clave del comando)" "${D[grupos_dup_comando]}" "${A[grupos_ambiguos]}"
  eq "grupos duplicados (clave productos_identidad_unique)" "${D[grupos_dup_indice]}" "0"
  eq "pedido_detalles (filas)"         "${D[pedido_detalles]}"      "${A[pedido_detalles]}"
  eq "pedido_detalles (suma cantidad)" "${D[pedido_detalles_cant]}" "${A[pedido_detalles_cant]}"
  eq "movimientos_stock (filas)"       "${D[movimientos_stock]}"    "${A[movimientos_stock]}"
  eq "movimientos_stock con lote NULL (trazabilidad no perdida)" "${D[movimientos_lote_null]}" "${A[movimientos_lote_null]}"
  eq "incidencias_venta (filas)"       "${D[incidencias_venta]}"    "${A[incidencias_venta]}"
  eq "pedido_detalle_lotes (filas)"    "${D[pedido_detalle_lotes]}" "${A[pedido_detalle_lotes]}"
  eq "lotes (filas)"                   "${D[lotes]}"                "${A[esp_lotes]}"
  eq "unidades en lotes (stock total esperado)" "${D[lotes_unidades]}" "${A[esp_lotes_unidades]}"
  eq "suma de productos.stock"         "${D[productos_stock_suma]}" "${A[esp_productos_stock]}"
  local k
  for k in pedido_detalles movimientos_stock incidencias_venta conteo_detalles carrito lotes pedido_detalle_lotes movimientos_lote; do
    eq "referencias huérfanas en $k" "${D[orf_$k]}" "0"
  done
  if [ "${D[stock_inconsistente]}" -le "${A[stock_inconsistente]}" ]; then
    ok "productos con stock != suma de lotes: ${A[stock_inconsistente]} -> ${D[stock_inconsistente]} (no aumenta)"
  else
    fallo "productos con stock != suma de lotes aumentó: ${A[stock_inconsistente]} -> ${D[stock_inconsistente]}"
  fi
  [ "$FALLOS" -eq 0 ]
}

if [ "$MODO" = "verificar" ]; then
  [ -f "$VERIFICAR_DIR/antes.kv" ] || die "No existe $VERIFICAR_DIR/antes.kv"
  cargar A "$VERIFICAR_DIR/antes.kv"
  metricas >"$VERIFICAR_DIR/despues.kv"; cargar D "$VERIFICAR_DIR/despues.kv"
  if verificar_invariantes; then info "Todas las invariantes se cumplen."; exit 0; fi
  die "$FALLOS invariantes fallan. NO sigas con la migración; revisa el plan de rollback en $VERIFICAR_DIR/ROLLBACK.txt"
fi

# ----------------------------------------------------------------------------
# 1. Carpeta de la corrida, espacio en disco y plan de rollback
# ----------------------------------------------------------------------------
RUN_DIR="${FUSION_DIR:-$REPO/infra/backup/salida/fusion-$SELLO}"
mkdir -p "$RUN_DIR"; chmod 700 "$RUN_DIR"
if ! : >"$RUN_DIR/.escritura"; then die "No puedo escribir en $RUN_DIR"; fi
rm -f "$RUN_DIR/.escritura"
exec > >(tee -a "$RUN_DIR/corrida.log") 2>&1      # el log nunca contiene la URL
info "Carpeta de la corrida: $RUN_DIR"

TAM_BD="$(psql_q -c 'SELECT pg_database_size(current_database())')"
LIBRE="$(df -Pk "$RUN_DIR" | awk 'NR==2{print $4*1024}')"
[ "$LIBRE" -gt $((TAM_BD * 2 + 50000000)) ] || die "Poco espacio en disco ($LIBRE B libres; la BD pesa $TAM_BD B)."

HORA_UTC="$(psql_q -c "SELECT to_char(now() AT TIME ZONE 'UTC','YYYY-MM-DD\"T\"HH24:MI:SS\"Z\"')")"
LSN="$(psql_q -c 'SELECT pg_current_wal_lsn()')"

cat >"$RUN_DIR/ROLLBACK.txt" <<EOF
PLAN DE ROLLBACK - fusión de duplicados ($BD_REAL @ $CX_HOST)
Punto de restauración (UTC): $HORA_UTC   LSN: $LSN
Respaldo lógico: $RUN_DIR/respaldo_pre_fusion.dump   (sha256 en respaldo.sha256)

La fusión es UNA transacción: si artisan falla ANTES del commit no hay nada que
deshacer. Solo hace falta rollback si el commit ocurrió y luego se detecta un
problema (stock que no cuadra, ventas mal atribuidas, invariantes que fallan).

OPCIÓN A (preferida) - Branch de Neon al instante previo
  1. Consola de Neon > Branches > Create branch > "Past data" > $HORA_UTC
     (dentro de la ventana de retención del plan; compruébala ANTES de aplicar).
  2. Verificar en la rama: SELECT count(*) FROM productos;  -> ver antes.kv
  3. Apuntar DATABASE_URL / DB_URL de la app (Render) al endpoint DIRECTO de la rama.
  Deja la base original intacta para analizarla.

OPCIÓN B - Restaurar el respaldo en una base NUEVA (nunca encima de producción)
  1. Crear una base/rama nueva y VACÍA en Neon (endpoint directo).
  2. pg_restore --no-owner --no-privileges --exit-on-error -d "\$NUEVA_URL" \\
         "$RUN_DIR/respaldo_pre_fusion.dump"
  3. Comparar con antes.kv y apuntar la app a la nueva base.

OPCIÓN C (último recurso, destructiva) - pg_restore --clean --if-exists sobre la
  misma base. Solo con la app detenida y confirmación del dueño.

ADVERTENCIA: cualquier venta/ingreso hecho DESPUÉS de la fusión se pierde al
volver al punto previo. Por eso la app debe estar en mantenimiento (sin ventas
ni importaciones) desde antes del respaldo hasta cerrar la verificación.
Si ya se creó productos_identidad_unique: php artisan migrate:rollback --step=1
(o DROP INDEX productos_identidad_unique) antes de restaurar.
EOF

# ----------------------------------------------------------------------------
# 2. Conteos ANTES y puertas de seguridad
# ----------------------------------------------------------------------------
info "Conteos antes"
metricas >"$RUN_DIR/antes.kv"; cargar A "$RUN_DIR/antes.kv"
printf '   productos %s | grupos duplicados %s (ambiguos %s) | perdedores %s\n' \
  "${A[productos]}" "${A[grupos_dup_comando]}" "${A[grupos_ambiguos]}" "${A[perdedores]}"
printf '   referencias a perdedores: pedido_detalles %s | movimientos_stock %s | incidencias_venta %s | conteo_detalles %s | carrito %s\n' \
  "${A[perd_pedido_detalles]}" "${A[perd_movimientos_stock]}" "${A[perd_incidencias_venta]}" "${A[perd_conteo_detalles]}" "${A[perd_carrito]}"
printf '   lotes de perdedores %s (con trazabilidad, se conservan a cero: %s)\n' "${A[perd_lotes]}" "${A[perd_lotes_con_traza]}"
printf '   lotes %s (%s unidades) | productos.stock suma %s | stock inconsistente %s\n' \
  "${A[lotes]}" "${A[lotes_unidades]}" "${A[productos_stock_suma]}" "${A[stock_inconsistente]}"
printf '   ESPERADO tras fusionar: productos %s | lotes %s | unidades %s\n' \
  "${A[esp_productos]}" "${A[esp_lotes]}" "${A[esp_lotes_unidades]}"

if [ "${A[grupos_a_fusionar]}" = "0" ] && [ "${A[grupos_ambiguos]}" = "0" ]; then
  if [ "${A[grupos_dup_indice]}" = "0" ]; then
    info "No hay duplicados: la fusión ya está aplicada. Nada que hacer (idempotente)."
    [ "${A[indice_unique_existe]}" = "1" ] || info "Falta solo el índice: php artisan migrate --force (ver runbook, paso 4)."
    exit 0
  fi
fi
[ "${A[grupos_ambiguos]}" = "0" ] || die "Hay ${A[grupos_ambiguos]} grupos ambiguos (más de una fila con actividad). Decídelos a mano; el comando los omitiría y el índice UNIQUE fallaría."
[ "${A[grupos_indice_tras_fusion]}" = "0" ] || die "Tras la fusión quedarían ${A[grupos_indice_tras_fusion]} grupos que violan productos_identidad_unique (filas que difieren solo en codigo_digemid). La migración fallaría con la fusión ya confirmada. Resuélvelos antes."
[ "${A[indice_unique_existe]}" = "0" ] || die "productos_identidad_unique ya existe pero hay duplicados: estado inesperado."

OTRAS="$(psql_q -c "SELECT count(*) FROM pg_stat_activity WHERE datname = current_database() AND pid <> pg_backend_pid() AND usename = current_user")"
printf '   Otras conexiones de este usuario ahora mismo: %s (la app debe estar en MANTENIMIENTO)\n' "$OTRAS"

# ----------------------------------------------------------------------------
# 3. Confirmación tipeada #1
# ----------------------------------------------------------------------------
cat <<EOF

 Vas a operar sobre la base  $BD_REAL  en  $CX_HOST
 Esto BORRARÁ ${A[perdedores]} filas de productos de forma irreversible (salvo rollback).
 Antes: tienes el plan en $RUN_DIR/ROLLBACK.txt. La app debe estar en mantenimiento.

EOF
ESPERADA="FUSIONAR $BD_REAL EN $CX_HOST"
read -r -p "Para continuar, escribe exactamente  $ESPERADA : " RESP
[ "$RESP" = "$ESPERADA" ] || die "Confirmación incorrecta. No se hizo nada."

# ----------------------------------------------------------------------------
# 4. Respaldo (pg_dump -Fc) y verificación
# ----------------------------------------------------------------------------
DUMP="$RUN_DIR/respaldo_pre_fusion.dump"
info "pg_dump -Fc (puede tardar)..."
pg_dump -Fc --no-owner -f "$DUMP" || die "pg_dump falló."
[ -s "$DUMP" ] || die "El respaldo está vacío."
TAM_DUMP="$(wc -c <"$DUMP")"
[ "$TAM_DUMP" -gt 1024 ] || die "El respaldo es sospechosamente pequeño ($TAM_DUMP B)."
pg_restore --list "$DUMP" >"$RUN_DIR/respaldo.lista" || die "pg_restore --list no puede leer el respaldo."
grep -q 'TABLE DATA public productos ' "$RUN_DIR/respaldo.lista" || die "El respaldo no contiene datos de productos."
TABLAS_BD="$(psql_q -c "SELECT count(*) FROM pg_tables WHERE schemaname='public'")"
TABLAS_DUMP="$(grep -c 'TABLE DATA public ' "$RUN_DIR/respaldo.lista" || true)"
[ "$TABLAS_DUMP" -ge "$TABLAS_BD" ] || die "El respaldo trae datos de $TABLAS_DUMP tablas y la base tiene $TABLAS_BD."
sha256sum "$DUMP" >"$RUN_DIR/respaldo.sha256"
chmod 600 "$DUMP"
ok "respaldo $TAM_DUMP bytes, $TABLAS_DUMP tablas con datos, legible con pg_restore --list"
info "El volcado contiene TODO el esquema (incluidas credenciales): no lo subas al repositorio."

if [ -n "${FUSION_RESTORE_TEST_URL:-}" ]; then
  [ "$FUSION_RESTORE_TEST_URL" != "$DATABASE_URL" ] || die "FUSION_RESTORE_TEST_URL no puede ser la base de producción."
  # Base desechable: se usa su propia URL (visible en argv; no es producción) y sin solo-lectura.
  (
    # subshell: que ninguna credencial de producción viaje a la base desechable
    unset PGPASSWORD PGHOST PGPORT PGUSER PGDATABASE PGSSLMODE; export PGOPTIONS=''
    N="$(PGOPTIONS='' psql_q "$FUSION_RESTORE_TEST_URL" -c "SELECT count(*) FROM pg_tables WHERE schemaname='public'")"
    [ "$N" = "0" ] || die "La base de prueba de restauración no está vacía ($N tablas)."
    PGOPTIONS='' pg_restore --no-owner --exit-on-error -d "$FUSION_RESTORE_TEST_URL" "$DUMP" || die "El restore de prueba falló."
    NP="$(PGOPTIONS='' psql_q "$FUSION_RESTORE_TEST_URL" -c 'SELECT count(*) FROM productos')"
    [ "$NP" = "${A[productos]}" ] || die "Restore de prueba: productos=$NP, esperado ${A[productos]}."
  ) || die "Falló la prueba de restauración."
  ok "restore de prueba correcto (productos coinciden)"
fi

# ----------------------------------------------------------------------------
# 5. Simulación (artisan sin --aplicar: ejecuta en transacción y hace ROLLBACK)
# ----------------------------------------------------------------------------
export DB_CONNECTION=pgsql
export DB_URL="$DATABASE_URL"
unset DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD 2>/dev/null || true

SIM="$RUN_DIR/artisan_simulacion.txt"
APL="$RUN_DIR/artisan_aplicar.txt"
info "Simulación: ${ARTISAN[*]} catalogo:fusionar-duplicados"
set +e
( cd "$BACKEND_DIR" && "${ARTISAN[@]}" catalogo:fusionar-duplicados --no-interaction ) >"$SIM" 2>&1
RC=$?
set -e
sed -n '1,45p' "$SIM"
[ "$RC" -eq 0 ] || die "La simulación terminó con código $RC (ver $SIM). No se aplicó nada."

num() { grep -E "^ +$1 " "$SIM" | awk '{print $NF}' | head -1; }
S_GRUPOS="$(num 'Grupos duplicados encontrados')"
S_AMBI="$(num 'Grupos ambiguos omitidos')"
S_ELIM="$(num 'Filas que se eliminarán')"
[ "$S_GRUPOS" = "${A[grupos_dup_comando]}" ] && [ "$S_AMBI" = "${A[grupos_ambiguos]}" ] && [ "$S_ELIM" = "${A[perdedores]}" ] \
  || die "La simulación (grupos=$S_GRUPOS ambiguos=$S_AMBI filas=$S_ELIM) no coincide con el SQL independiente (grupos=${A[grupos_dup_comando]} ambiguos=${A[grupos_ambiguos]} filas=${A[perdedores]}). Puede que artisan apunte a OTRA base. No se aplica."
grep -q 'no se escribió nada' "$SIM" || die "La simulación no confirma que revirtió."
grep -q 'Se esperaba eliminar' "$SIM" && die "La simulación advierte de filas eliminadas distintas de las esperadas."
ok "la simulación coincide con el recuento independiente en SQL"

metricas >"$RUN_DIR/tras_simulacion.kv"; cargar D "$RUN_DIR/tras_simulacion.kv"
[ "${D[productos]}" = "${A[productos]}" ] && [ "${D[lotes]}" = "${A[lotes]}" ] || die "La simulación dejó rastro en la base (revisa: debería haber hecho rollback)."

if [ "$MODO" = "simular" ]; then
  info "--solo-simular: fin. No se aplicó nada. Respaldo y conteos en $RUN_DIR"
  exit 0
fi

# ----------------------------------------------------------------------------
# 6. Pausa + confirmación #2 (y re-medición: si la base cambió, se aborta)
# ----------------------------------------------------------------------------
cat <<EOF

 PAUSA. Revisa $SIM y $RUN_DIR/antes.kv.
 La siguiente orden ESCRIBE (commit) en $CX_HOST / $BD_REAL.
EOF
read -r -p "Escribe APLICAR para ejecutar --aplicar (cualquier otra cosa cancela): " RESP2
[ "$RESP2" = "APLICAR" ] || die "Cancelado. Solo se hizo respaldo y simulación (rollback)."

metricas >"$RUN_DIR/antes_aplicar.kv"
if ! diff -q <(grep -v '^$' "$RUN_DIR/antes.kv") <(grep -v '^$' "$RUN_DIR/antes_aplicar.kv") >/dev/null; then
  diff "$RUN_DIR/antes.kv" "$RUN_DIR/antes_aplicar.kv" || true
  die "La base cambió durante la pausa (hay actividad). Pon la app en mantenimiento y repite desde el inicio."
fi

# ----------------------------------------------------------------------------
# 7. Aplicar (salida a archivo abierto de antemano; el éxito lo decide la BD)
# ----------------------------------------------------------------------------
: >"$APL" || die "No puedo crear $APL; no se aplica nada."
echo "INICIO_APLICAR $(date -u +%FT%TZ)" >"$RUN_DIR/estado.txt"
info "APLICANDO: ${ARTISAN[*]} catalogo:fusionar-duplicados --aplicar"
set +e
( cd "$BACKEND_DIR" && "${ARTISAN[@]}" catalogo:fusionar-duplicados --aplicar --no-interaction ) >"$APL" 2>&1
RC=$?
set -e
echo "FIN_ARTISAN rc=$RC $(date -u +%FT%TZ)" >>"$RUN_DIR/estado.txt"
sed -n '1,80p' "$APL" || true
[ "$RC" -eq 0 ] || info "artisan devolvió $RC: se decide por el estado real de la base, NO por este código."

# ----------------------------------------------------------------------------
# 8. Conteos DESPUÉS e invariantes (sobre la base, no sobre la salida)
# ----------------------------------------------------------------------------
metricas >"$RUN_DIR/despues.kv"; D=(); cargar D "$RUN_DIR/despues.kv"
printf '\n   productos %s -> %s | lotes %s -> %s | unidades %s -> %s\n' \
  "${A[productos]}" "${D[productos]}" "${A[lotes]}" "${D[lotes]}" "${A[lotes_unidades]}" "${D[lotes_unidades]}"

if [ "${D[productos]}" = "${A[productos]}" ] && [ "${D[grupos_dup_comando]}" = "${A[grupos_dup_comando]}" ]; then
  echo "BASE_SIN_CAMBIOS" >>"$RUN_DIR/estado.txt"
  die "La base NO cambió (rc=$RC): la transacción se revirtió, nada quedó a medias. Es seguro reintentar tras leer $APL."
fi

if verificar_invariantes; then
  echo "APLICADO_Y_VERIFICADO $(date -u +%FT%TZ)" >>"$RUN_DIR/estado.txt"
  info "Fusión aplicada y verificada."
  [ "$RC" -eq 0 ] || info "Aviso: artisan devolvió $RC pero la base está correcta. NO repitas --aplicar."
  cat <<EOF

 SIGUIENTE (manual, ver runbook "Ejecución segura en Neon"):
   1. php artisan migrate --pretend   (revisa que solo salga el índice UNIQUE)
   2. php artisan migrate --force     (con DB_URL del endpoint directo)
   3. Muestrea el stock de unos productos conocidos con el dueño.
   4. Saca la app de mantenimiento.
 Rollback: $RUN_DIR/ROLLBACK.txt
EOF
  exit 0
fi
echo "APLICADO_CON_INVARIANTES_FALLIDAS" >>"$RUN_DIR/estado.txt"
die "El commit YA OCURRIÓ pero $FALLOS invariantes fallan. NO ejecutes migrate ni repitas --aplicar. Sigue $RUN_DIR/ROLLBACK.txt."
