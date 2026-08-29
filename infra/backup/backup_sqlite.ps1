param(
  [string]$ProjectPath = (Resolve-Path (Join-Path $PSScriptRoot '..\..\botica-san-juan-backend')).Path,
  [string]$BackupDir = (Resolve-Path (Join-Path $PSScriptRoot '.')).Path
)

$ErrorActionPreference = 'Stop'

$dbPath = Join-Path $ProjectPath 'database\database.sqlite'
if (-not (Test-Path $dbPath)) {
  throw "No se encontro base sqlite: $dbPath"
}

if (-not (Test-Path $BackupDir)) {
  New-Item -ItemType Directory -Path $BackupDir | Out-Null
}

$timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$backupPath = Join-Path $BackupDir "database.$timestamp.sqlite"
Copy-Item -Path $dbPath -Destination $backupPath -Force

$hash = (Get-FileHash -Path $backupPath -Algorithm SHA256).Hash
Write-Host "BACKUP_OK path=$backupPath sha256=$hash"
