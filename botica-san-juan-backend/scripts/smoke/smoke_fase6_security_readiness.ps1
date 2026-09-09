[CmdletBinding()]
param(
  [string]$BaseUrl = 'http://127.0.0.1:8083/api',
  [string]$AdminDni = '12345678',
  [string]$ClientDni = '87654321',
  [string]$EvidenceFile = ''
)

$ErrorActionPreference = 'Stop'

$adminSecret = if ($env:BOTICA_ADMIN_SECRET) { $env:BOTICA_ADMIN_SECRET } else { '123456' }
$clientSecret = if ($env:BOTICA_CLIENT_SECRET) { $env:BOTICA_CLIENT_SECRET } else { '123456' }

function Invoke-ApiRequest {
  param(
    [Parameter(Mandatory = $true)][string]$Method,
    [Parameter(Mandatory = $true)][string]$Uri,
    [hashtable]$Headers,
    $Body = $null
  )

  $requestArgs = @{
    Method = $Method
    Uri = $Uri
    SkipHttpErrorCheck = $true
    Headers = ($Headers ?? @{ Accept = 'application/json' })
  }

  if ($null -ne $Body -and $Method -in @('POST', 'PUT', 'PATCH')) {
    $requestArgs['ContentType'] = 'application/json'
    $requestArgs['Body'] = ($Body | ConvertTo-Json -Depth 10)
  }

  $response = Invoke-WebRequest @requestArgs

  $json = $null
  if (-not [string]::IsNullOrWhiteSpace($response.Content)) {
    try {
      $json = $response.Content | ConvertFrom-Json
    } catch {
      $json = $null
    }
  }

  return [PSCustomObject]@{
    StatusCode = [int]$response.StatusCode
    Json = $json
    Raw = $response.Content
    Headers = $response.Headers
  }
}

function Assert-Status {
  param(
    [Parameter(Mandatory = $true)]$Response,
    [Parameter(Mandatory = $true)][int]$Expected,
    [Parameter(Mandatory = $true)][string]$Message
  )

  if ($Response.StatusCode -ne $Expected) {
    throw "$Message (esperado=$Expected, recibido=$($Response.StatusCode))"
  }
}

if ([string]::IsNullOrWhiteSpace($EvidenceFile)) {
  $timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
  $EvidenceFile = Join-Path $PSScriptRoot "smoke_fase6_security_readiness.$timestamp.json"
}

$evidence = [ordered]@{
  started_at = (Get-Date).ToString('o')
  base_url = $BaseUrl
  checks = @()
}

function Add-CheckResult {
  param(
    [string]$Name,
    [string]$Result,
    [int]$StatusCode = 0,
    [string]$RequestId = ''
  )

  $item = [ordered]@{
    name = $Name
    result = $Result
    status_code = $StatusCode
    request_id = $RequestId
    timestamp = (Get-Date).ToString('o')
  }

  $evidence.checks += $item
}

Write-Host 'SMOKE FASE 6: LOGIN ADMIN'
$adminLogin = Invoke-ApiRequest -Method 'POST' -Uri "$BaseUrl/login" -Body @{
  dni = $AdminDni
  password = $adminSecret
}
Assert-Status -Response $adminLogin -Expected 200 -Message 'FALLO login admin'
Add-CheckResult -Name 'admin_login' -Result 'ok' -StatusCode $adminLogin.StatusCode -RequestId ([string]$adminLogin.Headers['X-Request-Id'])

$adminToken = $adminLogin.Json.token
if (-not $adminToken) {
  throw 'FALLO: login admin sin token.'
}
$adminHeaders = @{ Authorization = "Bearer $adminToken"; Accept = 'application/json' }
Write-Host "OK admin login dni=$AdminDni"

Write-Host 'SMOKE FASE 6: HEALTH ENDPOINTS'
$health = Invoke-ApiRequest -Method 'GET' -Uri "$BaseUrl/health"
Assert-Status -Response $health -Expected 200 -Message 'FALLO health app'
if ($health.Json.status -ne 'ok') {
  throw 'FALLO: /health no devolvio status=ok.'
}
Add-CheckResult -Name 'health_app' -Result 'ok' -StatusCode $health.StatusCode -RequestId ([string]$health.Headers['X-Request-Id'])

$healthDb = Invoke-ApiRequest -Method 'GET' -Uri "$BaseUrl/health/db"
Assert-Status -Response $healthDb -Expected 200 -Message 'FALLO health db'
if ($healthDb.Json.database -ne 'connected') {
  throw 'FALLO: /health/db no devolvio database=connected.'
}
Write-Host 'OK health app+db'
Add-CheckResult -Name 'health_db' -Result 'ok' -StatusCode $healthDb.StatusCode -RequestId ([string]$healthDb.Headers['X-Request-Id'])

Write-Host 'SMOKE FASE 6: 401 SIN TOKEN (MUTACION ADMIN)'
$unauth = Invoke-ApiRequest -Method 'POST' -Uri "$BaseUrl/categorias" -Body @{
  nombre = "unauth-test-$(Get-Random -Minimum 1000 -Maximum 9999)"
}
Assert-Status -Response $unauth -Expected 401 -Message 'FALLO: mutacion admin sin token no devolvio 401'
Write-Host 'OK 401 validado'
Add-CheckResult -Name 'unauthorized_admin_mutation' -Result 'ok' -StatusCode $unauth.StatusCode -RequestId ([string]$unauth.Headers['X-Request-Id'])

$temporaryClientId = $null
try {
  Write-Host 'SMOKE FASE 6: PREPARAR USUARIO CLIENTE'
  $clientLogin = Invoke-ApiRequest -Method 'POST' -Uri "$BaseUrl/login" -Body @{
    dni = $ClientDni
    password = $clientSecret
  }

  if ($clientLogin.StatusCode -ne 200 -or -not $clientLogin.Json.token) {
    $suffix = Get-Random -Minimum 10000 -Maximum 99999
    $clientDniTemp = "9$suffix"
    $clientDniTemp = $clientDniTemp.Substring(0, 8)

    $createClient = Invoke-ApiRequest -Method 'POST' -Uri "$BaseUrl/usuarios" -Headers $adminHeaders -Body @{
      nombre = "Smoke Client $suffix"
      dni = $clientDniTemp
      email = "smoke.client.$suffix@test.local"
      password = '12345678'
      telefono = '900111333'
      rol = 'cliente'
    }
    Assert-Status -Response $createClient -Expected 201 -Message 'FALLO creando cliente temporal'

    $temporaryClientId = $createClient.Json.id
    $ClientDni = $clientDniTemp
    $clientSecret = '12345678'

    $clientLogin = Invoke-ApiRequest -Method 'POST' -Uri "$BaseUrl/login" -Body @{
      dni = $ClientDni
      password = $clientSecret
    }
    Assert-Status -Response $clientLogin -Expected 200 -Message 'FALLO login cliente temporal'
  }

  $clientToken = $clientLogin.Json.token
  if (-not $clientToken) {
    throw 'FALLO: login cliente sin token.'
  }
  $clientHeaders = @{ Authorization = "Bearer $clientToken"; Accept = 'application/json' }
  Write-Host "OK client login dni=$ClientDni"

  Write-Host 'SMOKE FASE 6: 403 TOKEN CLIENTE EN MUTACION ADMIN'
  $forbidden = Invoke-ApiRequest -Method 'POST' -Uri "$BaseUrl/categorias" -Headers $clientHeaders -Body @{
    nombre = "forbidden-test-$(Get-Random -Minimum 1000 -Maximum 9999)"
  }
  Assert-Status -Response $forbidden -Expected 403 -Message 'FALLO: token cliente en mutacion admin no devolvio 403'
  Write-Host 'OK 403 validado'
  Add-CheckResult -Name 'forbidden_client_admin_mutation' -Result 'ok' -StatusCode $forbidden.StatusCode -RequestId ([string]$forbidden.Headers['X-Request-Id'])

  Write-Host 'SMOKE FASE 6: 429 EN LOGIN POR FUERZA BRUTA'
  $seen429 = $false
  $throttleProbeDni = '99999999'
  for ($i = 1; $i -le 8; $i++) {
    $failedAttempt = Invoke-ApiRequest -Method 'POST' -Uri "$BaseUrl/login" -Body @{
      dni = $throttleProbeDni
      password = 'password_incorrecta'
    }

    if ($failedAttempt.StatusCode -eq 429) {
      $seen429 = $true
      break
    }
  }

  if (-not $seen429) {
    throw 'FALLO: no se obtuvo 429 tras intentos fallidos de login.'
  }
  Write-Host 'OK 429 validado'
  Add-CheckResult -Name 'throttle_login_429' -Result 'ok' -StatusCode 429

  Write-Host 'SMOKE FASE 6 COMPLETADO'
  $evidence.result = 'ok'
} finally {
  if ($temporaryClientId) {
    Write-Host "SMOKE FASE 6: LIMPIEZA cliente temporal id=$temporaryClientId"
    $cleanup = Invoke-ApiRequest -Method 'DELETE' -Uri "$BaseUrl/usuarios/$temporaryClientId" -Headers $adminHeaders
    if ($cleanup.StatusCode -ge 300) {
      Write-Host "WARN cleanup cliente temporal fallo status=$($cleanup.StatusCode)"
    } else {
      Write-Host 'OK cleanup cliente temporal'
    }
  }

  if (-not $evidence.result) {
    $evidence.result = 'partial_or_failed'
  }

  $evidence.finished_at = (Get-Date).ToString('o')
  $evidence | ConvertTo-Json -Depth 10 | Set-Content -Path $EvidenceFile -Encoding UTF8
  Write-Host "EVIDENCE_FILE: $EvidenceFile"
}
