param(
  [string]$ApiBase = 'https://api.tu-dominio.com/api',
  [string]$WebhookUrl = ''
)

$ErrorActionPreference = 'Stop'

function Test-Health {
  param([string]$Url)

  $response = Invoke-WebRequest -Uri $Url -Method GET -SkipHttpErrorCheck
  if ([int]$response.StatusCode -ne 200) {
    return $false
  }

  try {
    $json = $response.Content | ConvertFrom-Json
    return ($json.status -eq 'ok')
  } catch {
    return $false
  }
}

$appOk = Test-Health -Url "$ApiBase/health"
$dbOk = Test-Health -Url "$ApiBase/health/db"

if ($appOk -and $dbOk) {
  Write-Host 'HEALTH OK'
  exit 0
}

$message = "ALERTA healthcheck: app_ok=$appOk db_ok=$dbOk api=$ApiBase"
Write-Error $message

if ($WebhookUrl) {
  try {
    Invoke-RestMethod -Method POST -Uri $WebhookUrl -ContentType 'application/json' -Body (@{ text = $message } | ConvertTo-Json)
  } catch {
    Write-Error "No se pudo notificar al webhook: $($_.Exception.Message)"
  }
}

exit 1
