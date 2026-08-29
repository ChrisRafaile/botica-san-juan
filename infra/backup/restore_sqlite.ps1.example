param(
  [Parameter(Mandatory = $true)][string]$BackupFile,
  [string]$ProjectPath = (Resolve-Path (Join-Path $PSScriptRoot '..\..\botica-san-juan-backend')).Path
)

$ErrorActionPreference = 'Stop'

$dbPath = Join-Path $ProjectPath 'database\database.sqlite'
if (-not (Test-Path $BackupFile)) {
  throw "No se encontro backup: $BackupFile"
}

if (-not (Test-Path $dbPath)) {
  throw "No se encontro base destino: $dbPath"
}

$preRestorePath = "$dbPath.pre-restore.$(Get-Date -Format 'yyyyMMdd-HHmmss').bak"
Copy-Item -Path $dbPath -Destination $preRestorePath -Force
Copy-Item -Path $BackupFile -Destination $dbPath -Force

Write-Host "RESTORE_OK restored_from=$BackupFile safety_copy=$preRestorePath"
