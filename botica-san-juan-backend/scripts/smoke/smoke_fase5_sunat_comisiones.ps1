[CmdletBinding()]
param(
  [ValidateSet('aceptada', 'rechazada')]
  [string]$ForceSunatStatus = ''
)

$ErrorActionPreference = 'Stop'

$base = if ($env:BOTICA_API_BASE) { $env:BOTICA_API_BASE } else { 'http://127.0.0.1:8083/api' }
$jsonHeaders = @{ Accept = 'application/json' }

$loginBody = @{ dni='12345678'; password='123456' } | ConvertTo-Json
$login = Invoke-RestMethod -Method Post -Uri "$base/login" -ContentType 'application/json' -Headers $jsonHeaders -Body $loginBody
$token = $login.token
if (-not $token) { $token = $login.access_token }
if (-not $token) { throw 'FALLO: login no devolvio token.' }

$headers = @{ Authorization = "Bearer $token"; Accept = 'application/json' }

Write-Host 'SMOKE FASE 5: LISTAR DOCUMENTOS'
$docsPage = Invoke-RestMethod -Method Get -Uri "$base/facturacion/documentos?per_page=10" -Headers $headers
$docs = @($docsPage.data)

if ($docs.Count -eq 0) {
  Write-Host 'No hay documentos. Generando desde pedidos...'
  Invoke-RestMethod -Method Post -Uri "$base/facturacion/generar-desde-pedidos" -Headers $headers -ContentType 'application/json' -Body '{}' | Out-Null
  $docsPage = Invoke-RestMethod -Method Get -Uri "$base/facturacion/documentos?per_page=10" -Headers $headers
  $docs = @($docsPage.data)
}

if ($docs.Count -eq 0) { throw 'FALLO: no existen comprobantes para validar fase 5.' }
$doc = $docs[0]

Write-Host "SMOKE FASE 5: ENVIAR SUNAT id=$($doc.id)"
$sunatBody = @{}
if ($ForceSunatStatus) {
  $sunatBody.force_status = $ForceSunatStatus
}
$sunat = Invoke-RestMethod -Method Post -Uri "$base/facturacion/documentos/$($doc.id)/enviar-sunat" -Headers $headers -ContentType 'application/json' -Body ($sunatBody | ConvertTo-Json)
if (-not $sunat.documento.sunatStatus) { throw 'FALLO: no se devolvio sunatStatus.' }
if (-not $sunat.documento.sunatTicket) { Write-Host 'Advertencia: sin ticket SUNAT (revisar modo/API).' }

Write-Host 'SMOKE FASE 5: REGISTRAR COMISION'
$suffix = Get-Random -Minimum 1000 -Maximum 9999
$comision = Invoke-RestMethod -Method Post -Uri "$base/facturacion/documentos/$($doc.id)/comision" -Headers $headers -ContentType 'application/json' -Body (@{
  tipo_agente = 'medico'
  agente_nombre = "Dr Smoke $suffix"
  porcentaje = 5
} | ConvertTo-Json)

if (-not $comision.comision.id) { throw 'FALLO: no se pudo crear comision.' }
$comisionId = $comision.comision.id

Write-Host "SMOKE FASE 5: LIQUIDAR COMISION id=$comisionId"
$liq = Invoke-RestMethod -Method Post -Uri "$base/facturacion/comisiones/$comisionId/liquidar" -Headers $headers -ContentType 'application/json' -Body '{}'
if ($liq.comision.estado -ne 'liquidada') { throw 'FALLO: comision no quedo liquidada.' }

Write-Host "SMOKE FASE 5 OK: doc=$($doc.number) sunat=$($sunat.documento.sunatStatus) comision=$comisionId"
