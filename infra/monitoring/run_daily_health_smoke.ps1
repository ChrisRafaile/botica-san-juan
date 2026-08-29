param(
  [string]$RepoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path,
  [string]$ApiBase = 'http://127.0.0.1:8083/api',
  [string]$WebhookUrl = ''
)

$ErrorActionPreference = 'Stop'

$backendPath = Join-Path $RepoRoot 'botica-san-juan-backend'
$healthScript = Join-Path $RepoRoot 'infra\monitoring\healthcheck_monitor.ps1'
$smokeScript = Join-Path $backendPath 'smoke_fase6_security_readiness.ps1'

if (-not (Test-Path $healthScript)) { throw "No existe script health: $healthScript" }
if (-not (Test-Path $smokeScript)) { throw "No existe script smoke: $smokeScript" }

$timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$evidenceDir = Join-Path $backendPath "logs\ops-evidence\$timestamp"
New-Item -ItemType Directory -Path $evidenceDir -Force | Out-Null

$summary = [ordered]@{
  started_at = (Get-Date).ToString('o')
  api_base = $ApiBase
  checks = @()
}

function Add-Result {
  param(
    [string]$Name,
    [string]$Status,
    [string]$LogPath,
    [string]$ErrorMessage = ''
  )

  $summary.checks += [ordered]@{
    name = $Name
    status = $Status
    log = $LogPath
    error = $ErrorMessage
    timestamp = (Get-Date).ToString('o')
  }
}

$failed = $false

$healthLog = Join-Path $evidenceDir 'health.log'
try {
  pwsh -File $healthScript -ApiBase $ApiBase -WebhookUrl $WebhookUrl *>&1 | Tee-Object -FilePath $healthLog
  if ($LASTEXITCODE -ne 0) { throw "exit code $LASTEXITCODE" }
  Add-Result -Name 'healthcheck' -Status 'ok' -LogPath $healthLog
} catch {
  $failed = $true
  Add-Result -Name 'healthcheck' -Status 'failed' -LogPath $healthLog -ErrorMessage $_.Exception.Message
}

$smokeLog = Join-Path $evidenceDir 'smoke_fase6.log'
$smokeEvidence = Join-Path $evidenceDir 'smoke_fase6.evidence.json'
try {
  Set-Location $backendPath
  pwsh -File $smokeScript -BaseUrl $ApiBase -EvidenceFile $smokeEvidence *>&1 | Tee-Object -FilePath $smokeLog
  if ($LASTEXITCODE -ne 0) { throw "exit code $LASTEXITCODE" }
  Add-Result -Name 'smoke_fase6' -Status 'ok' -LogPath $smokeLog
} catch {
  $failed = $true
  Add-Result -Name 'smoke_fase6' -Status 'failed' -LogPath $smokeLog -ErrorMessage $_.Exception.Message
}

$summary.finished_at = (Get-Date).ToString('o')
$summary.result = if ($failed) { 'failed' } else { 'ok' }
$summaryPath = Join-Path $evidenceDir 'summary.json'
$summary | ConvertTo-Json -Depth 10 | Set-Content -Path $summaryPath -Encoding UTF8

Write-Host "OPS_EVIDENCE_DIR=$evidenceDir"
Write-Host "OPS_SUMMARY=$summaryPath"

if ($failed) { exit 1 }
exit 0
