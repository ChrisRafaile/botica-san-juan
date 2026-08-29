param(
  [string]$RepoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path,
  [string]$ApiBase = 'http://127.0.0.1:8083/api',
  [string]$Environment = 'staging'
)

$ErrorActionPreference = 'Stop'

$backendPath = Join-Path $RepoRoot 'botica-san-juan-backend'
$healthScript = Join-Path $RepoRoot 'infra\monitoring\healthcheck_monitor.ps1'
$opsRunner = Join-Path $RepoRoot 'infra\monitoring\run_daily_health_smoke.ps1'
$backupDrill = Join-Path $RepoRoot 'infra\backup\run_restore_drill.ps1'
$logExport = Join-Path $RepoRoot 'infra\monitoring\export_logs_bundle.ps1'

$timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$evidenceDir = Join-Path $backendPath "logs\incident-drills\$timestamp"
New-Item -ItemType Directory -Path $evidenceDir -Force | Out-Null

$summary = [ordered]@{
  environment = $Environment
  started_at = (Get-Date).ToString('o')
  api_base = $ApiBase
  checks = @()
}

function Add-Result {
  param([string]$Name, [string]$Status, [string]$Log, [string]$Error = '')
  $summary.checks += [ordered]@{
    name = $Name
    status = $Status
    log = $Log
    error = $Error
    timestamp = (Get-Date).ToString('o')
  }
}

$failed = $false

$healthLog = Join-Path $evidenceDir 'healthcheck.log'
try {
  pwsh -File $healthScript -ApiBase $ApiBase *>&1 | Tee-Object -FilePath $healthLog
  if ($LASTEXITCODE -ne 0) { throw "exit code $LASTEXITCODE" }
  Add-Result -Name 'healthcheck' -Status 'ok' -Log $healthLog
} catch {
  $failed = $true
  Add-Result -Name 'healthcheck' -Status 'failed' -Log $healthLog -Error $_.Exception.Message
}

$opsLog = Join-Path $evidenceDir 'ops-runner.log'
try {
  pwsh -File $opsRunner -ApiBase $ApiBase *>&1 | Tee-Object -FilePath $opsLog
  if ($LASTEXITCODE -ne 0) { throw "exit code $LASTEXITCODE" }
  Add-Result -Name 'ops_runner' -Status 'ok' -Log $opsLog
} catch {
  $failed = $true
  Add-Result -Name 'ops_runner' -Status 'failed' -Log $opsLog -Error $_.Exception.Message
}

$backupLog = Join-Path $evidenceDir 'backup_drill.log'
try {
  pwsh -File $backupDrill -RepoRoot $RepoRoot -Environment $Environment *>&1 | Tee-Object -FilePath $backupLog
  if ($LASTEXITCODE -ne 0) { throw "exit code $LASTEXITCODE" }
  Add-Result -Name 'backup_restore_drill' -Status 'ok' -Log $backupLog
} catch {
  $failed = $true
  Add-Result -Name 'backup_restore_drill' -Status 'failed' -Log $backupLog -Error $_.Exception.Message
}

$exportLog = Join-Path $evidenceDir 'log_export.log'
try {
  pwsh -File $logExport -RepoRoot $RepoRoot -Days 1 *>&1 | Tee-Object -FilePath $exportLog
  if ($LASTEXITCODE -ne 0) { throw "exit code $LASTEXITCODE" }
  Add-Result -Name 'log_export' -Status 'ok' -Log $exportLog
} catch {
  $failed = $true
  Add-Result -Name 'log_export' -Status 'failed' -Log $exportLog -Error $_.Exception.Message
}

$summary.finished_at = (Get-Date).ToString('o')
$summary.result = if ($failed) { 'failed' } else { 'ok' }

$summaryPath = Join-Path $evidenceDir 'summary.json'
$summary | ConvertTo-Json -Depth 10 | Set-Content -Path $summaryPath -Encoding UTF8

Write-Host "INCIDENT_DRILL_SUMMARY=$summaryPath"
if ($failed) { exit 1 }
exit 0
