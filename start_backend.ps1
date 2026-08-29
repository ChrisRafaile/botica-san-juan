[CmdletBinding()]
param(
  [string]$BindHost = '127.0.0.1',
  [int]$Port = 8083
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $MyInvocation.MyCommand.Path
$backendPath = Join-Path $root 'botica-san-juan-backend'

if (-not (Test-Path $backendPath)) {
  throw "No se encontro carpeta backend: $backendPath"
}

Set-Location $backendPath
Write-Host "Iniciando backend en http://$BindHost`:$Port"
php artisan serve --host=$BindHost --port=$Port
