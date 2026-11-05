<#
.SYNOPSIS
    Simulacro de restauracion sobre PostgreSQL, con verificacion que puede fallar.

.DESCRIPTION
    POR QUE EXISTE ESTE Y NO BASTA run_restore_drill.ps1

    Hay dos motivos, y el segundo importa mas.

    1. El otro simulacro opera sobre database.sqlite, que es la base de
       contingencia del mostrador. PRODUCCION CORRE POSTGRESQL. Un simulacro
       que nunca toca el motor real no demuestra que se pueda restaurar la
       base que de verdad importa.

    2. El otro simulacro NO PUEDE FALLAR. Copia la base, restaura la copia
       encima del original y comprueba que los tres hashes coinciden. Si la
       restauracion no hiciera absolutamente nada, los hashes coincidirian
       igual. Un simulacro que no puede fallar no verifica nada: solo
       tranquiliza.

    Aqui la secuencia es la de un desastre de verdad:

        1. Respaldar el origen.
        2. Restaurar en la base de ensayo.
        3. ROMPER la base de ensayo a proposito: borrar filas.
        4. Restaurar otra vez desde el respaldo.
        5. Comprobar que las filas VOLVIERON.

    El paso 3 es el que convierte esto en una prueba. Sin el, el paso 5 se
    cumple solo.

.NOTES
    NUNCA TOCA PRODUCCION. Trabaja contra la base local y restaura en una base
    de ensayo aparte, que se recrea en cada corrida. La base de produccion vive
    en Neon y sus credenciales no estan aqui.
#>
param(
    [string]$DbHost   = $(if ($env:DB_HOST) { $env:DB_HOST } else { '127.0.0.1' }),
    [int]   $Port     = $(if ($env:DB_PORT) { [int]$env:DB_PORT } else { 5432 }),
    [string]$Origen   = 'botica_san_juan',
    [string]$Ensayo   = 'botica_staging',
    [string]$Usuario  = $(if ($env:DB_USERNAME) { $env:DB_USERNAME } else { 'postgres' }),
    [string]$RepoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
)

$ErrorActionPreference = 'Stop'

if ($Ensayo -eq $Origen) {
    throw "La base de ensayo no puede ser la de origen ($Origen). Este simulacro BORRA filas en la de ensayo."
}

$marca    = Get-Date -Format 'yyyyMMdd-HHmmss'
$destino  = Join-Path $RepoRoot "botica-san-juan-backend\logs\backup-evidence\pg-$marca"
New-Item -ItemType Directory -Path $destino -Force | Out-Null

$archivo = Join-Path $destino "$Origen.dump"

function Consultar([string]$bd, [string]$sql) {
    $r = & psql --host=$DbHost --port=$Port --username=$Usuario --dbname=$bd --no-align --tuples-only --command=$sql 2>&1
    if ($LASTEXITCODE -ne 0) { throw "psql fallo en [$bd]: $r" }
    return ($r | Out-String).Trim()
}

Write-Host "== Simulacro de restauracion PostgreSQL =="
Write-Host "   origen: $Origen   ensayo: $Ensayo"

# --- 1. Respaldo del origen -------------------------------------------------
$t0 = Get-Date
& pg_dump --host=$DbHost --port=$Port --username=$Usuario --dbname=$Origen --format=custom --file=$archivo
if ($LASTEXITCODE -ne 0) { throw "pg_dump fallo con codigo $LASTEXITCODE" }
$tRespaldo = ((Get-Date) - $t0).TotalSeconds

$hash   = (Get-FileHash -Path $archivo -Algorithm SHA256).Hash
$pesoMB = [math]::Round((Get-Item $archivo).Length / 1MB, 2)

$productosOrigen = [int](Consultar $Origen 'select count(*) from productos;')
Write-Host ("   respaldo: {0} MB en {1} s · {2} productos" -f $pesoMB, [math]::Round($tRespaldo,1), $productosOrigen)

# --- 2. Restauracion en la base de ensayo -----------------------------------
$t1 = Get-Date
& psql --host=$DbHost --port=$Port --username=$Usuario --dbname=postgres --command="drop database if exists $Ensayo;" | Out-Null
& psql --host=$DbHost --port=$Port --username=$Usuario --dbname=postgres --command="create database $Ensayo;" | Out-Null
& pg_restore --host=$DbHost --port=$Port --username=$Usuario --dbname=$Ensayo --no-owner --no-privileges $archivo 2>&1 | Out-Null
$tRestauracion1 = ((Get-Date) - $t1).TotalSeconds

$productosTrasPrimera = [int](Consultar $Ensayo 'select count(*) from productos;')

# --- 3. Romper la base de ensayo a proposito --------------------------------
# Este es el paso que convierte el simulacro en una prueba.
#
# SE BORRA digemid_catalogos Y NO productos, Y NO ES ARBITRARIO.
#
# El primer intento borraba productos, y el motor lo rechazo:
#
#     update o delete en «lotes» viola la llave foranea
#     «pedido_detalle_lotes_lote_id_foreign»
#
# Es decir: la integridad referencial del esquema IMPIDE perder un producto
# que ya figura en una venta. Eso es exactamente lo que debe hacer, y de paso
# demuestra el RNF06 mejor que cualquier captura. Asi que el dano se simula
# sobre una tabla hoja —el padron DIGEMID, sin dependientes—, que ademas es
# informacion de cumplimiento cuya perdida importa de verdad.
$catalogoAntes = [int](Consultar $Ensayo 'select count(*) from digemid_catalogos;')
Consultar $Ensayo 'delete from digemid_catalogos;' | Out-Null
$catalogoRoto = [int](Consultar $Ensayo 'select count(*) from digemid_catalogos;')
$seRompio = ($catalogoRoto -lt $catalogoAntes)

Write-Host ("   dano simulado: padron DIGEMID {0} -> {1} filas" -f $catalogoAntes, $catalogoRoto)

if (-not $seRompio) {
    throw 'El dano simulado no borro nada: el simulacro no puede verificar la recuperacion.'
}

# --- 4. Recuperacion desde el respaldo --------------------------------------
$t2 = Get-Date
& psql --host=$DbHost --port=$Port --username=$Usuario --dbname=postgres --command="drop database if exists $Ensayo;" | Out-Null
& psql --host=$DbHost --port=$Port --username=$Usuario --dbname=postgres --command="create database $Ensayo;" | Out-Null
& pg_restore --host=$DbHost --port=$Port --username=$Usuario --dbname=$Ensayo --no-owner --no-privileges $archivo 2>&1 | Out-Null
$tRecuperacion = ((Get-Date) - $t2).TotalSeconds

$productosRecuperados = [int](Consultar $Ensayo 'select count(*) from productos;')
$catalogoRecuperado   = [int](Consultar $Ensayo 'select count(*) from digemid_catalogos;')
$pedidosRecuperados   = [int](Consultar $Ensayo 'select count(*) from pedidos;')
$usuariosRecuperados  = [int](Consultar $Ensayo 'select count(*) from usuarios;')

# --- 5. Veredicto -----------------------------------------------------------
$recuperado = ($productosRecuperados -eq $productosOrigen) -and ($catalogoRecuperado -eq $catalogoAntes)
$rtoSeg     = $tRespaldo + $tRecuperacion

$resumen = [ordered]@{
    ejecutado_en            = (Get-Date).ToString('o')
    motor                   = 'PostgreSQL ' + (Consultar $Ensayo 'show server_version;')
    base_origen             = $Origen
    base_ensayo             = $Ensayo
    archivo_respaldo        = $archivo
    respaldo_sha256         = $hash
    respaldo_mb             = $pesoMB
    segundos_respaldo       = [math]::Round($tRespaldo, 2)
    segundos_restauracion   = [math]::Round($tRestauracion1, 2)
    productos_origen        = $productosOrigen
    productos_tras_restaurar = $productosTrasPrimera
    tabla_danada            = 'digemid_catalogos'
    filas_antes_del_dano    = $catalogoAntes
    filas_tras_el_dano      = $catalogoRoto
    filas_borradas          = $catalogoAntes - $catalogoRoto
    filas_recuperadas       = $catalogoRecuperado
    segundos_recuperacion   = [math]::Round($tRecuperacion, 2)
    productos_recuperados   = $productosRecuperados
    pedidos_recuperados     = $pedidosRecuperados
    usuarios_recuperados    = $usuariosRecuperados
    recuperacion_correcta   = $recuperado
    rto_medido_segundos     = [math]::Round($rtoSeg, 2)
    rto_comprometido_min    = 60
    rto_cumplido            = ($rtoSeg -lt 3600)
}

$ruta = Join-Path $destino 'simulacro-postgres.json'
$resumen | ConvertTo-Json -Depth 5 | Set-Content -Path $ruta -Encoding UTF8

Write-Host ("   recuperacion: {0} productos y {1} filas de padron en {2} s" -f $productosRecuperados, $catalogoRecuperado, [math]::Round($tRecuperacion,1))
Write-Host "DRILL_EVIDENCIA=$ruta"

if (-not $recuperado) {
    Write-Error "Recuperacion INCORRECTA: productos $productosRecuperados/$productosOrigen, padron DIGEMID $catalogoRecuperado/$catalogoAntes."
    exit 1
}

Write-Host ("DRILL_OK · RTO medido {0} s (comprometido: 3600 s)" -f [math]::Round($rtoSeg,1)) -ForegroundColor Green
exit 0
