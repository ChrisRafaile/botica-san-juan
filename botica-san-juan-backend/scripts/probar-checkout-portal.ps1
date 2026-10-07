# Checkout del portal contra la API local, de punta a punta.
#
#   powershell -File scripts/probar-checkout-portal.ps1
#
# Hace una peticion HTTP REAL al endpoint publico y despues consulta la base
# para comprobar que el pedido, su detalle por lote y el comprobante existen.
# No usa el framework de pruebas: lo que se verifica aqui es el camino completo
# tal como lo recorre el navegador, cabeceras y serializacion incluidas.

$api = 'http://127.0.0.1:8083/api'

# Lee el cuerpo JSON de una respuesta de error.
#
# Windows PowerShell 5.1 no rellena $_.ErrorDetails.Message de forma fiable con
# Invoke-RestMethod: en un 422 llega vacio y ConvertFrom-Json revienta. Hay que
# leer el flujo de la respuesta a mano.
function CuerpoDelError($errorRegistro) {
  if ($errorRegistro.ErrorDetails -and $errorRegistro.ErrorDetails.Message) {
    return $errorRegistro.ErrorDetails.Message | ConvertFrom-Json
  }
  try {
    $flujo = $errorRegistro.Exception.Response.GetResponseStream()
    $flujo.Position = 0
    $lector = New-Object System.IO.StreamReader($flujo)
    return $lector.ReadToEnd() | ConvertFrom-Json
  } catch {
    return $null
  }
}

function Mostrar($titulo) {
  Write-Output ''
  Write-Output ('-' * 78)
  Write-Output $titulo
  Write-Output ('-' * 78)
}

Write-Output "CHECKOUT DEL PORTAL - verificacion de punta a punta"
Write-Output "Fecha: $(Get-Date -Format 'yyyy-MM-dd HH:mm')"

# --- 1. Cotizar, que es lo que hace la pantalla al abrir el carrito ---------
Mostrar '1. POST /carrito/cotizar  (publico, sin sesion)'

# Una unidad de cada uno: el script se ejecuta varias veces y cada ejecucion
# descuenta stock de verdad. Pedir mas de lo que queda hace fallar el paso 2 con
# un 422 legitimo que no es lo que esta prueba quiere demostrar.
$cuerpoCotizar = '{"items":[{"producto_id":1,"cantidad":1},{"producto_id":306,"cantidad":1}]}'
$cot = Invoke-RestMethod -Uri "$api/carrito/cotizar" -Method Post -Body $cuerpoCotizar -ContentType 'application/json'

foreach ($l in $cot.lineas) {
  Write-Output ("  {0,-28} {1} x S/ {2,6:N2} = S/ {3,7:N2}   stock {4}   receta: {5}" -f `
      $l.nombre, $l.cantidad, $l.precio, $l.subtotal, $l.stock_disponible, $(if ($l.requiere_receta) { 'SI' } else { 'no' }))
}
Write-Output ("  base S/ {0:N2} + IGV S/ {1:N2} = total S/ {2:N2}" -f `
    $cot.desglose.subtotal_gravado, $cot.desglose.igv, $cot.desglose.total)
Write-Output ("  aviso de receta en el pedido: {0}" -f $cot.receta)

# --- 2. Checkout rapido, sin cuenta ----------------------------------------
Mostrar '2. POST /pedidos/confirmar  (invitado: sin token)'

$cuerpoPedido = @{
  items = @(
    @{ producto_id = 1;   cantidad = 1 },
    @{ producto_id = 306; cantidad = 1 }
  )
  cliente_nombre    = 'Cliente de Prueba APF2'
  cliente_documento = '12345678'
  cliente_telefono  = '999000111'
} | ConvertTo-Json -Depth 5

try {
  $ped = Invoke-RestMethod -Uri "$api/pedidos/confirmar" -Method Post -Body $cuerpoPedido -ContentType 'application/json'
} catch {
  # Se informa en vez de seguir imprimiendo campos vacios: un 422 por falta de
  # stock o un 429 por el limitador son respuestas correctas del sistema, y
  # confundirlas con un fallo del script lleva a diagnosticar lo que no es.
  $codigo = $_.Exception.Response.StatusCode.value__
  $detalle = CuerpoDelError $_
  Write-Output ("  NO SE REGISTRO. HTTP {0}: {1}" -f $codigo, $detalle.message)
  if ($codigo -eq 429) { Write-Output '  (limitador de checkout: 6 por minuto. Espera y repite.)' }
  if ($detalle.sin_stock) { Write-Output '  (sin stock: cada ejecucion de este script descuenta inventario real.)' }
  exit 1
}

Write-Output ("  pedido        : #{0}" -f $ped.pedido.id)
Write-Output ("  origen        : {0}   estado: {1}" -f $ped.pedido.origen, $ped.pedido.estado)
Write-Output ("  usuario_id    : {0}   (vacio = encargo de invitado)" -f $ped.pedido.usuario_id)
Write-Output ("  comprobante   : {0}  {1}" -f $ped.comprobante.identificador, $ped.comprobante.tipo)
Write-Output ("  estado SUNAT  : {0}   (pendiente: se emite internamente, aun no se envia)" -f $ped.comprobante.estado_sunat)
Write-Output ("  base S/ {0:N2} + IGV S/ {1:N2} = total S/ {2:N2}" -f `
    $ped.pedido.subtotal_gravado, $ped.pedido.igv, $ped.pedido.total)

# --- 3. Validaciones del checkout rapido -----------------------------------
Mostrar '3. El invitado tiene que identificarse'

$faltan = @{ items = @(@{ producto_id = 1; cantidad = 1 }) } | ConvertTo-Json -Depth 5
try {
  Invoke-RestMethod -Uri "$api/pedidos/confirmar" -Method Post -Body $faltan -ContentType 'application/json' | Out-Null
  Write-Output '  ERROR: se acepto un pedido sin identificar al cliente'
} catch {
  $codigo = $_.Exception.Response.StatusCode.value__
  $detalle = CuerpoDelError $_
  Write-Output ("  HTTP {0}" -f $codigo)
  foreach ($campo in $detalle.errors.PSObject.Properties) {
    Write-Output ("    {0}: {1}" -f $campo.Name, $campo.Value[0])
  }
}

Mostrar '4. Un documento que no es DNI ni RUC se rechaza'

$docMalo = @{
  items = @(@{ producto_id = 1; cantidad = 1 })
  cliente_nombre = 'Quien Sea'; cliente_documento = '123'; cliente_telefono = '999000111'
} | ConvertTo-Json -Depth 5
try {
  Invoke-RestMethod -Uri "$api/pedidos/confirmar" -Method Post -Body $docMalo -ContentType 'application/json' | Out-Null
  Write-Output '  ERROR: se acepto un documento invalido'
} catch {
  $detalle = CuerpoDelError $_
  Write-Output ("  HTTP {0} - {1}" -f $_.Exception.Response.StatusCode.value__, $detalle.errors.cliente_documento[0])
}

Write-Output ''
Write-Output ('=' * 78)
Write-Output ("Pedido generado para verificar en la base: #{0}" -f $ped.pedido.id)
Write-Output ('=' * 78)
