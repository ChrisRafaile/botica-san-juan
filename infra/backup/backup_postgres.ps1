<#
.SYNOPSIS
    Respaldo de la base PostgreSQL del Sistema Web de Gestion - Botica San Juan.

.DESCRIPTION
    Genera un respaldo comprimido con pg_dump en formato custom (-Fc), calcula su
    hash SHA-256 y escribe un resumen JSON como evidencia operativa.
    Aplica retencion automatica eliminando los respaldos mas antiguos.

.EXAMPLE
    pwsh -File .\infra\backup\backup_postgres.ps1
    pwsh -File .\infra\backup\backup_postgres.ps1 -Database botica_san_juan -RetencionDias 30
#>
[CmdletBinding()]
param(
    [string]$DbHost      = $(if ($env:DB_HOST)     { $env:DB_HOST }     else { '127.0.0.1' }),
    [int]   $Port        = $(if ($env:DB_PORT)     { [int]$env:DB_PORT } else { 5432 }),
    [string]$Database    = $(if ($env:DB_DATABASE) { $env:DB_DATABASE } else { 'botica_san_juan' }),
    [string]$Usuario     = $(if ($env:DB_USERNAME) { $env:DB_USERNAME } else { 'postgres' }),
    [string]$DestinoRaiz = "$PSScriptRoot\..\..\botica-san-juan-backend\logs\backup-evidence",
    [int]   $RetencionDias = 14
)

$ErrorActionPreference = 'Stop'

function Escribir($mensaje, $color = 'Gray') { Write-Host $mensaje -ForegroundColor $color }

# --- Verificacion de herramientas -------------------------------------------
$pgDump = Get-Command pg_dump -ErrorAction SilentlyContinue
if (-not $pgDump) {
    throw "pg_dump no esta en el PATH. Agregue la carpeta bin de PostgreSQL, por ejemplo: C:\Program Files\PostgreSQL\16\bin"
}

if (-not $env:PGPASSWORD) {
    Escribir "AVISO: la variable PGPASSWORD no esta definida; pg_dump pedira la contrasena." 'Yellow'
}

# --- Preparacion de rutas ----------------------------------------------------
$marca   = Get-Date -Format 'yyyyMMdd-HHmmss'
$destino = Join-Path $DestinoRaiz $marca
New-Item -ItemType Directory -Force -Path $destino | Out-Null

$archivo = Join-Path $destino "$Database-$marca.dump"

Escribir "Respaldando $Database desde $DbHost`:$Port ..." 'Cyan'
$inicio = Get-Date

# --- Ejecucion del respaldo --------------------------------------------------
& pg_dump --host=$DbHost --port=$Port --username=$Usuario --dbname=$Database `
          --format=custom --compress=9 --no-owner --no-privileges --file=$archivo

if ($LASTEXITCODE -ne 0) { throw "pg_dump termino con codigo $LASTEXITCODE" }

$duracion = [math]::Round(((Get-Date) - $inicio).TotalSeconds, 2)
$info     = Get-Item $archivo
$hash     = (Get-FileHash -Path $archivo -Algorithm SHA256).Hash

# --- Evidencia operativa -----------------------------------------------------
$resumen = [ordered]@{
    generado_en   = (Get-Date).ToString('s')
    base_datos    = $Database
    servidor      = "$DbHost`:$Port"
    archivo       = $info.Name
    ruta          = $info.FullName
    tamano_bytes  = $info.Length
    tamano_mb     = [math]::Round($info.Length / 1MB, 2)
    sha256        = $hash
    duracion_seg  = $duracion
    estado        = 'ok'
}
$resumen | ConvertTo-Json -Depth 3 | Set-Content -Path (Join-Path $destino 'summary.json') -Encoding UTF8

Escribir "Respaldo completado en $duracion s" 'Green'
Escribir "   Archivo : $($info.Name)  ($($resumen.tamano_mb) MB)"
Escribir "   SHA-256 : $hash"

# --- Retencion ---------------------------------------------------------------
$limite   = (Get-Date).AddDays(-$RetencionDias)
$antiguos = Get-ChildItem -Path $DestinoRaiz -Directory -ErrorAction SilentlyContinue |
            Where-Object { $_.CreationTime -lt $limite }

foreach ($carpeta in $antiguos) {
    Remove-Item -Path $carpeta.FullName -Recurse -Force
    Escribir "   Purgado por retencion: $($carpeta.Name)" 'DarkGray'
}

Escribir "Evidencia en: $destino" 'Cyan'
