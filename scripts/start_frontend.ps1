[CmdletBinding()]
param(
  [string]$BindHost = '127.0.0.1',
  [int]$Port = 5173
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $MyInvocation.MyCommand.Path
$frontendPath = Join-Path $root 'botica-san-juan-frontend'

if (-not (Test-Path $frontendPath)) {
  throw "No se encontro carpeta frontend: $frontendPath"
}

Set-Location $frontendPath
if (-not (Get-Command pnpm -ErrorAction SilentlyContinue)) {
  corepack enable | Out-Null
}

Write-Host "Iniciando frontend en http://$BindHost`:$Port"
pnpm dev --host $BindHost --port $Port
