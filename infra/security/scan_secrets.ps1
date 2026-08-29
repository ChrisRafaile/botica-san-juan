param(
  [string]$RepoPath = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
)

$ErrorActionPreference = 'Stop'

$patterns = @(
  'AKIA[0-9A-Z]{16}',
  '-----BEGIN (RSA|EC|OPENSSH|DSA) PRIVATE KEY-----',
  '(?i)\b(api[_-]?key|secret|password)\b\s*[:=]\s*["''][^"'']{8,}["'']',
  '(?i)\b(SUNAT_TOKEN|DB_PASSWORD|MAIL_PASSWORD|AWS_SECRET_ACCESS_KEY)\b\s*=\s*(?!\s*$|\s*""\s*$|\s*null\s*$|\s*tu_)[^\s#]+'
)

$allowPathPatterns = @(
  '\\smoke_.*\.ps1:',
  '\\test_.*\.(php|js):',
  '\\botica-san-juan-frontend\\src\\auth\\',
  '\\botica-san-juan-frontend\\src\\services\\auth\.ts:'
)

$includeGlob = @('*.php', '*.env', '*.env.*', '*.yml', '*.yaml', '*.json', '*.ps1', '*.sh', '*.js', '*.ts', '*.vue', '*.md', '*.ini')
$excludeGlob = @('!.git/**', '!vendor/**', '!node_modules/**', '!**/dist/**', '!**/build/**', '!**/composer.phar')

Write-Host "Escaneando posibles secretos en: $RepoPath"

$found = @()
if (Get-Command rg -ErrorAction SilentlyContinue) {
  foreach ($pattern in $patterns) {
    $args = @('--hidden', '-n', '-P')
    foreach ($glob in $excludeGlob) { $args += @('--glob', $glob) }
    foreach ($glob in $includeGlob) { $args += @('--glob', $glob) }
    $args += @($pattern, $RepoPath)

    $result = & rg @args
    if ($LASTEXITCODE -eq 0 -and $result) {
      $found += $result
    }
  }
} else {
  Write-Host 'rg no disponible, usando Select-String como fallback local.'
  $files = Get-ChildItem -Path $RepoPath -Recurse -File -ErrorAction SilentlyContinue |
    Where-Object {
      $_.FullName -notmatch '\\.git\\' -and
      $_.FullName -notmatch '\\vendor\\' -and
      $_.FullName -notmatch '\\node_modules\\' -and
      $_.FullName -notmatch '\\dist\\' -and
      $_.FullName -notmatch '\\build\\' -and
      $_.Name -ne 'composer.phar'
    } |
    Where-Object {
      $ext = $_.Extension.ToLowerInvariant()
      $ext -in @('.php', '.env', '.yml', '.yaml', '.json', '.ps1', '.sh', '.js', '.ts', '.vue', '.md', '.ini') -or $_.Name -like '.env*'
    }

  foreach ($pattern in $patterns) {
    $matches = $files | Select-String -Pattern $pattern -AllMatches -ErrorAction SilentlyContinue
    foreach ($match in $matches) {
      $found += "{0}:{1}:{2}" -f $match.Path, $match.LineNumber, $match.Line.Trim()
    }
  }
}

$found = $found | Sort-Object -Unique
$filtered = @()
foreach ($entry in $found) {
  $isAllowed = $false
  foreach ($allowPattern in $allowPathPatterns) {
    if ($entry -match $allowPattern) {
      $isAllowed = $true
      break
    }
  }

  if (-not $isAllowed) {
    $filtered += $entry
  }
}

if ($filtered.Count -eq 0) {
  Write-Host 'OK: no se detectaron secretos bloqueantes (solo coincidencias permitidas de testing).'
  exit 0
}

Write-Host 'ALERTA: se detectaron potenciales secretos.'
$filtered | ForEach-Object { Write-Host $_ }
exit 1
