$ErrorActionPreference = 'Stop'

$base = if ($env:BOTICA_API_BASE) { $env:BOTICA_API_BASE } else { 'http://127.0.0.1:8083/api' }
$jsonHeaders = @{ Accept = 'application/json' }

$loginBody = @{
  dni = '12345678'
  password = '123456'
} | ConvertTo-Json

$login = Invoke-RestMethod -Method Post -Uri "$base/login" -ContentType 'application/json' -Headers $jsonHeaders -Body $loginBody
$token = $login.token
if (-not $token) { $token = $login.access_token }
if (-not $token) { throw 'Login did not return an API token.' }

$headers = @{ Authorization = "Bearer $token"; Accept = 'application/json' }

$report = Invoke-RestMethod -Method Get -Uri "$base/reportes/ventas?period=month" -Headers $headers

if (-not $report.summary) { throw 'Report response is missing summary.' }
if (-not ($report.PSObject.Properties.Name -contains 'daily_rows')) { throw 'Report response is missing daily_rows.' }
if (-not ($report.PSObject.Properties.Name -contains 'top_products')) { throw 'Report response is missing top_products.' }
if (-not ($report.PSObject.Properties.Name -contains 'status_breakdown')) { throw 'Report response is missing status_breakdown.' }

if ($report.summary.orders -lt 0) { throw 'Report summary orders must be zero or greater.' }
if ($report.summary.sales -lt 0) { throw 'Report summary sales must be zero or greater.' }

Write-Host "Fase 3 reportes OK: $($report.summary.orders) pedidos, S/ $($report.summary.sales) ventas"
