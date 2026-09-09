$ErrorActionPreference = 'Stop'
$base = 'http://127.0.0.1:8083/api'

function Get-JsonErrorBody {
  param([System.Management.Automation.ErrorRecord]$ErrorRecord)

  if (-not $ErrorRecord.Exception.Response) {
    return $null
  }

  try {
    $reader = New-Object System.IO.StreamReader($ErrorRecord.Exception.Response.GetResponseStream())
    $content = $reader.ReadToEnd()
    $reader.Close()
    return $content
  } catch {
    return $null
  }
}

function Assert-NotEmpty {
  param(
    [Parameter(Mandatory = $true)]$Value,
    [Parameter(Mandatory = $true)][string]$Message
  )

  if ($null -eq $Value -or $Value -eq '' -or ($Value -is [System.Collections.ICollection] -and $Value.Count -eq 0)) {
    throw $Message
  }
}

Write-Host 'SMOKE FASE 3: LOGIN'
$loginBody = @{ dni = '12345678'; password = '123456' } | ConvertTo-Json
$jsonHeaders = @{ Accept = 'application/json' }
$login = Invoke-RestMethod -Method Post -Uri "$base/login" -ContentType 'application/json' -Headers $jsonHeaders -Body $loginBody
$token = $login.token

Assert-NotEmpty -Value $token -Message 'FALLO: login no devolvio token JSON.'

$headers = @{ Authorization = "Bearer $token"; Accept = 'application/json' }
Write-Host "OK login user=$($login.user.dni) role=$($login.user.rol)"

Write-Host 'SMOKE FASE 3: LISTAR DOCUMENTOS'
$docsPage = Invoke-RestMethod -Method Get -Uri "$base/facturacion/documentos?per_page=5" -Headers $headers
$docs = @($docsPage.data)

if ($docs.Count -eq 0) {
  Write-Host 'No hay documentos, generando desde pedidos...'
  $generateResult = Invoke-RestMethod -Method Post -Uri "$base/facturacion/generar-desde-pedidos" -Headers $headers -ContentType 'application/json' -Body '{}'
  Write-Host "OK generar desde pedidos created=$($generateResult.created)"
  $docsPage = Invoke-RestMethod -Method Get -Uri "$base/facturacion/documentos?per_page=5" -Headers $headers
  $docs = @($docsPage.data)
}

Assert-NotEmpty -Value $docs -Message 'FALLO: no se pudieron obtener documentos de facturacion.'
$doc = $docs[0]
Assert-NotEmpty -Value $doc.id -Message 'FALLO: el documento no tiene id.'

Write-Host "OK documentos count=$($docs.Count) first_id=$($doc.id) first_number=$($doc.number)"

Write-Host 'SMOKE FASE 3: ENVIAR A SUNAT (SIMULADO)'
$sunatResult = Invoke-RestMethod -Method Post -Uri "$base/facturacion/documentos/$($doc.id)/enviar-sunat" -Headers $headers -ContentType 'application/json' -Body '{}'

Assert-NotEmpty -Value $sunatResult.documento -Message 'FALLO: el endpoint SUNAT no devolvio el documento actualizado.'
Assert-NotEmpty -Value $sunatResult.documento.sunatStatus -Message 'FALLO: el documento actualizado no incluyo estado SUNAT.'

Write-Host "OK sunat id=$($doc.id) estado=$($sunatResult.documento.sunatStatus) codigo=$($sunatResult.documento.sunatCode)"

Write-Host 'SMOKE FASE 3: DESCARGAR XML'
try {
  $xml = Invoke-WebRequest -Method Get -Uri "$base/facturacion/documentos/$($doc.id)/xml" -Headers $headers
  if ($xml.StatusCode -ne 200) {
    throw "FALLO: la descarga XML retorno status $($xml.StatusCode)."
  }

  $contentType = [string]$xml.Headers['Content-Type']
  if ($contentType -notmatch 'application/xml') {
    throw "FALLO: la descarga XML no devolvio content-type XML, sino: $contentType"
  }

  if ([string]::IsNullOrWhiteSpace($xml.Content)) {
    throw 'FALLO: la descarga XML vino vacia.'
  }

  Write-Host "OK xml id=$($doc.id) content_type=$contentType size=$($xml.Content.Length)"
} catch {
  $errorBody = Get-JsonErrorBody -ErrorRecord $_
  if ($errorBody) {
    Write-Host "Detalle inesperado XML: $errorBody"
  }
  throw
}

Write-Host 'SMOKE FASE 3 COMPLETADO'
