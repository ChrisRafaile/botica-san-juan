<#
.SYNOPSIS
    Restauracion de un respaldo PostgreSQL del Sistema Web de Gestion - Botica San Juan.

.DESCRIPTION
    Restaura un archivo .dump generado por backup_postgres.ps1 sobre una base destino.
    Por seguridad exige el parametro -Confirmar cuando la base destino no contiene
    la palabra 'staging' o 'test' en su nombre.

.EXAMPLE
    pwsh -File .\infra\backup\restore_postgres.ps1 -Archivo .\logs\backup-evidence\20260825-150000\botica_san_juan-20260825-150000.dump -Database botica_staging
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string]$Archivo,

    [string]$DbHost   = $(if ($env:DB_HOST)     { $env:DB_HOST }      else { '127.0.0.1' }),
    [int]   $Port     = $(if ($env:DB_PORT)     { [int]$env:DB_PORT } else { 5432 }),
    [string]$Database = 'botica_staging',
    [string]$Usuario  = $(if ($env:DB_USERNAME) { $env:DB_USERNAME }  else { 'postgres' }),
    [switch]$Confirmar
)

$ErrorActionPreference = 'Stop'
function Escribir($m, $c = 'Gray') { Write-Host $m -ForegroundColor $c }

if (-not (Test-Path $Archivo)) { throw "No se encontro el archivo de respaldo: $Archivo" }
if (-not (Get-Command pg_restore -ErrorAction SilentlyContinue)) {
    throw "pg_restore no esta en el PATH. Agregue la carpeta bin de PostgreSQL."
}

$esEntornoSeguro = $Database -match 'staging|test|dev'
if (-not $esEntornoSeguro -and -not $Confirmar) {
    throw "La base '$Database' parece de produccion. Vuelva a ejecutar con -Confirmar para continuar."
}

Escribir "Restaurando '$Archivo'" 'Cyan'
Escribir "   Destino: $DbHost`:$Port/$Database"

$inicio = Get-Date

# La base destino se recrea para garantizar una restauracion limpia y reproducible.
& psql --host=$DbHost --port=$Port --username=$Usuario --dbname=postgres `
       --command="DROP DATABASE IF EXISTS `"$Database`";"
& psql --host=$DbHost --port=$Port --username=$Usuario --dbname=postgres `
       --command="CREATE DATABASE `"$Database`" ENCODING 'UTF8';"

& pg_restore --host=$DbHost --port=$Port --username=$Usuario --dbname=$Database `
             --no-owner --no-privileges --exit-on-error $Archivo

if ($LASTEXITCODE -ne 0) { throw "pg_restore termino con codigo $LASTEXITCODE" }

$duracion = [math]::Round(((Get-Date) - $inicio).TotalSeconds, 2)

# --- Verificacion de consistencia -------------------------------------------
$consulta = @"
SELECT 'productos=' || (SELECT COUNT(*) FROM productos)
    || ' pedidos='  || (SELECT COUNT(*) FROM pedidos)
    || ' comprobantes=' || (SELECT COUNT(*) FROM comprobantes_electronicos)
    || ' usuarios=' || (SELECT COUNT(*) FROM usuarios);
"@
$conteos = & psql --host=$DbHost --port=$Port --username=$Usuario --dbname=$Database `
                  --tuples-only --no-align --command=$consulta

Escribir "Restauracion completada en $duracion s" 'Green'
Escribir "   Conteos: $conteos" 'Green'

$evidencia = Join-Path (Split-Path $Archivo -Parent) 'restore-summary.json'
[ordered]@{
    restaurado_en = (Get-Date).ToString('s')
    archivo       = (Resolve-Path $Archivo).Path
    base_destino  = $Database
    duracion_seg  = $duracion
    conteos       = $conteos
    restore_ok    = $true
} | ConvertTo-Json -Depth 3 | Set-Content -Path $evidencia -Encoding UTF8

Escribir "Evidencia en: $evidencia" 'Cyan'
