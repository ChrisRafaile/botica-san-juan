param(
  [string]$RepoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path,
  [string]$OutputDir = '',
  [int]$Days = 2
)

$ErrorActionPreference = 'Stop'

if ($OutputDir -eq '') {
  $OutputDir = Join-Path $RepoRoot 'botica-san-juan-backend\logs\exports'
}

$backendLogDir = Join-Path $RepoRoot 'botica-san-juan-backend\storage\logs'
$opsEvidence = Join-Path $RepoRoot 'botica-san-juan-backend\logs\ops-evidence'
$smokeEvidence = Join-Path $RepoRoot 'botica-san-juan-backend\logs\smoke-evidence'
$backupEvidence = Join-Path $RepoRoot 'botica-san-juan-backend\logs\backup-evidence'

New-Item -ItemType Directory -Path $OutputDir -Force | Out-Null

$timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$workDir = Join-Path $OutputDir "bundle-$timestamp"
New-Item -ItemType Directory -Path $workDir -Force | Out-Null

$minDate = (Get-Date).AddDays(-1 * [Math]::Abs($Days))

function Copy-LatestTree {
  param(
    [string]$Source,
    [string]$Destination
  )

  if (Test-Path $Source) {
    New-Item -ItemType Directory -Path $Destination -Force | Out-Null
    Get-ChildItem -Path $Source -Recurse -File |
      Where-Object { $_.LastWriteTime -ge $minDate } |
      ForEach-Object {
        $relative = $_.FullName.Substring($Source.Length).TrimStart('\\')
        $target = Join-Path $Destination $relative
        New-Item -ItemType Directory -Path (Split-Path -Parent $target) -Force | Out-Null
        Copy-Item -Path $_.FullName -Destination $target -Force
      }
  }
}

Copy-LatestTree -Source $backendLogDir -Destination (Join-Path $workDir 'storage-logs')
Copy-LatestTree -Source $opsEvidence -Destination (Join-Path $workDir 'ops-evidence')
Copy-LatestTree -Source $smokeEvidence -Destination (Join-Path $workDir 'smoke-evidence')
Copy-LatestTree -Source $backupEvidence -Destination (Join-Path $workDir 'backup-evidence')

$manifest = [ordered]@{
  generated_at = (Get-Date).ToString('o')
  range_days = $Days
  sources = @($backendLogDir, $opsEvidence, $smokeEvidence, $backupEvidence)
}

$manifestPath = Join-Path $workDir 'manifest.json'
$manifest | ConvertTo-Json -Depth 5 | Set-Content -Path $manifestPath -Encoding UTF8

$zipPath = Join-Path $OutputDir "logs-export-$timestamp.zip"
Compress-Archive -Path (Join-Path $workDir '*') -DestinationPath $zipPath -Force

Write-Host "LOG_EXPORT_OK path=$zipPath manifest=$manifestPath"
