param(
  [string]$RepoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path,
  [string]$Environment = 'staging'
)

$ErrorActionPreference = 'Stop'

$backendPath = Join-Path $RepoRoot 'botica-san-juan-backend'
$dbPath = Join-Path $backendPath 'database\database.sqlite'
$backupScript = Join-Path $RepoRoot 'infra\backup\backup_sqlite.ps1'
$restoreScript = Join-Path $RepoRoot 'infra\backup\restore_sqlite.ps1'

if (-not (Test-Path $dbPath)) { throw "No se encontro DB sqlite: $dbPath" }
if (-not (Test-Path $backupScript)) { throw "No se encontro script backup: $backupScript" }
if (-not (Test-Path $restoreScript)) { throw "No se encontro script restore: $restoreScript" }

$timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$evidenceDir = Join-Path $backendPath "logs\backup-evidence\$timestamp"
New-Item -ItemType Directory -Path $evidenceDir -Force | Out-Null

$beforeHash = (Get-FileHash -Path $dbPath -Algorithm SHA256).Hash
$backupOutput = pwsh -File $backupScript -ProjectPath $backendPath -BackupDir $evidenceDir
if ($LASTEXITCODE -ne 0) { throw "Backup fallo con exit code $LASTEXITCODE" }

$backupFile = Get-ChildItem -Path $evidenceDir -Filter 'database.*.sqlite' | Sort-Object LastWriteTime -Descending | Select-Object -First 1
if (-not $backupFile) { throw 'No se genero archivo de backup.' }

$backupHash = (Get-FileHash -Path $backupFile.FullName -Algorithm SHA256).Hash

$restoreOutput = pwsh -File $restoreScript -ProjectPath $backendPath -BackupFile $backupFile.FullName
if ($LASTEXITCODE -ne 0) { throw "Restore fallo con exit code $LASTEXITCODE" }

$afterHash = (Get-FileHash -Path $dbPath -Algorithm SHA256).Hash
$consistency = ($afterHash -eq $backupHash)

$summary = [ordered]@{
  environment = $Environment
  started_at = (Get-Date).ToString('o')
  db_path = $dbPath
  backup_file = $backupFile.FullName
  backup_sha256 = $backupHash
  db_sha256_before = $beforeHash
  db_sha256_after = $afterHash
  restore_consistent = $consistency
  backup_output = ($backupOutput -join "`n")
  restore_output = ($restoreOutput -join "`n")
  finished_at = (Get-Date).ToString('o')
}

$summaryPath = Join-Path $evidenceDir 'summary.json'
$summary | ConvertTo-Json -Depth 10 | Set-Content -Path $summaryPath -Encoding UTF8

Write-Host "BACKUP_DRILL_EVIDENCE=$summaryPath"
if (-not $consistency) {
  Write-Error 'Restore inconsistente: hash final de DB no coincide con backup.'
  exit 1
}

Write-Host 'BACKUP_DRILL_OK'
exit 0
