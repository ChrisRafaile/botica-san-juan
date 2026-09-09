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

Write-Host 'SMOKE FASE 2: LOGIN'
$loginBody = @{ dni='12345678'; password='123456' } | ConvertTo-Json
$jsonHeaders = @{ Accept = 'application/json' }
$login = Invoke-RestMethod -Method Post -Uri "$base/login" -ContentType 'application/json' -Headers $jsonHeaders -Body $loginBody
$token = $login.token

if ([string]::IsNullOrWhiteSpace($token)) {
  throw 'FALLO: login no devolvio token JSON. Revisa que el endpoint y puerto sean correctos.'
}

$headers = @{ Authorization = "Bearer $token"; Accept = 'application/json' }
Write-Host "OK login user=$($login.user.dni) role=$($login.user.rol)"

Write-Host 'SMOKE FASE 2: IMPORTAR CATALOGO DIGEMID'
$csvPath = Join-Path $PSScriptRoot 'digemid_catalogo_ejemplo.csv'
if (-not (Test-Path $csvPath)) {
  throw "No existe archivo CSV de ejemplo: $csvPath"
}

$import = Invoke-RestMethod -Method Post -Uri "$base/digemid-catalogo/import" -Headers $headers -Form @{
  file = Get-Item $csvPath
  overwrite = '1'
}
Write-Host "OK import created=$($import.created) updated=$($import.updated) skipped=$($import.skipped)"

Write-Host 'SMOKE FASE 2: CREAR PRODUCTO VALIDO (PRECIO REGULADO)'
$suffix = Get-Random -Minimum 1000 -Maximum 9999
$validPayload = @{
  nombre = "Producto Smoke DIGEMID Valido $suffix"
  concentracion = '500 mg'
  adicional = 'Prueba automatizada'
  laboratorio = 'Smoke Labs'
  presentacion = 'Tabletas'
  tipo = 'medicamentos'
  stock = 10
  precio = 11.50
  codigo_digemid = 'DIG-0001'
} | ConvertTo-Json

try {
  $validProduct = Invoke-RestMethod -Method Post -Uri "$base/productos" -ContentType 'application/json' -Headers $headers -Body $validPayload
  Write-Host "OK producto valido id=$($validProduct.id) codigo=$($validProduct.codigo_digemid)"

  Write-Host 'SMOKE FASE 2: BLOQUEO POR PRECIO EXCEDIDO'
  $invalidPayload = @{
  nombre = "Producto Smoke DIGEMID Invalido $suffix"
  concentracion = '500 mg'
  adicional = 'Prueba automatizada'
  laboratorio = 'Smoke Labs'
  presentacion = 'Tabletas'
  tipo = 'medicamentos'
  stock = 10
  precio = 99.90
  codigo_digemid = 'DIG-0001'
  } | ConvertTo-Json

  $blocked = $false
  try {
    Invoke-RestMethod -Method Post -Uri "$base/productos" -ContentType 'application/json' -Headers $headers -Body $invalidPayload | Out-Null
  } catch {
    $errorBody = Get-JsonErrorBody -ErrorRecord $_
    $errorMessage = ''
    if ($_.ErrorDetails -and $_.ErrorDetails.Message) {
      $errorMessage = [string]$_.ErrorDetails.Message
    }
    if ([string]::IsNullOrWhiteSpace($errorMessage)) {
      $errorMessage = [string]$_.Exception.Message
    }

    if (($errorBody -and $errorBody -match 'maximo regulado DIGEMID') -or ($errorMessage -match 'maximo regulado DIGEMID')) {
      $blocked = $true
      Write-Host 'OK bloqueo validado: el backend rechazo el precio por encima del maximo regulado.'
    } else {
      Write-Host "Detalle inesperado del error: $errorBody"
      Write-Host "Mensaje de error: $errorMessage"
      throw
    }
  }

  if (-not $blocked) {
    throw 'FALLO: el backend permitio registrar un producto con precio regulado excedido.'
  }

  Write-Host 'SMOKE FASE 2 COMPLETADO'
} finally {
  if ($null -ne $validProduct -and $validProduct.id) {
    Write-Host 'SMOKE FASE 2: LIMPIEZA'
    Invoke-RestMethod -Method Delete -Uri "$base/productos/$($validProduct.id)" -Headers $headers | Out-Null
    Write-Host "OK cleanup deleted_product_id=$($validProduct.id)"
  }
}
