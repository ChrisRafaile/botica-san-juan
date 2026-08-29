[CmdletBinding()]
param(
  [string]$BackendHost = '127.0.0.1',
  [int]$BackendPort = 8083,
  [string]$FrontendHost = '127.0.0.1',
  [int]$FrontendPort = 5173
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $MyInvocation.MyCommand.Path
$backendScript = Join-Path $root 'start_backend.ps1'
$frontendScript = Join-Path $root 'start_frontend.ps1'

if (-not (Test-Path $backendScript)) { throw "No existe: $backendScript" }
if (-not (Test-Path $frontendScript)) { throw "No existe: $frontendScript" }

Start-Process pwsh -ArgumentList @(
  '-NoExit',
  '-ExecutionPolicy', 'Bypass',
  '-File', $backendScript,
  '-BindHost', $BackendHost,
  '-Port', $BackendPort
)

Start-Process pwsh -ArgumentList @(
  '-NoExit',
  '-ExecutionPolicy', 'Bypass',
  '-File', $frontendScript,
  '-BindHost', $FrontendHost,
  '-Port', $FrontendPort
)

Write-Host "Backend y frontend iniciados en terminales separadas."
Write-Host "Backend:  http://$BackendHost`:$BackendPort"
Write-Host "Frontend: http://$FrontendHost`:$FrontendPort"
