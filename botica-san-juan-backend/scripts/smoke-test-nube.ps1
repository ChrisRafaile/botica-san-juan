# Smoke test del entorno desplegado (Sesion 16).
#
#   powershell -File scripts/smoke-test-nube.ps1
#
# Comprueba contra las URLs PUBLICAS que la version 1 esta en pie y sirviendo el
# catalogo depurado. No usa credenciales: el acceso con las cuentas de
# demostracion lo verifica una persona, porque escribir contrasenas en un sitio
# desplegado desde un script deja la contrasena en el historial del terminal.
#
# Lo que si se comprueba del lado de seguridad es lo contrario: que las rutas de
# gestion RECHACEN a quien no presenta token. Eso no necesita credenciales y es
# la mitad que de verdad importa.

$api    = 'https://botica-san-juan-api.onrender.com'
$portal = 'https://botica-san-juan.vercel.app'

$fallos = 0

function Comprobar($descripcion, $esperado, $obtenido) {
  $ok = "$esperado" -eq "$obtenido"
  $marca = if ($ok) { 'OK ' } else { 'FALLO' }
  Write-Output ("  [{0}] {1,-52} esperado: {2,-10} obtenido: {3}" -f $marca, $descripcion, $esperado, $obtenido)
  if (-not $ok) { $script:fallos++ }
}

function CodigoDe($url, $metodo = 'GET', $cuerpo = $null) {
  try {
    if ($cuerpo) {
      $r = Invoke-WebRequest -Uri $url -Method $metodo -Body $cuerpo -ContentType 'application/json' -UseBasicParsing -TimeoutSec 60
    } else {
      $r = Invoke-WebRequest -Uri $url -Method $metodo -UseBasicParsing -TimeoutSec 60
    }
    return $r.StatusCode
  } catch {
    if ($_.Exception.Response) { return $_.Exception.Response.StatusCode.value__ }
    return "sin respuesta"
  }
}

Write-Output "SMOKE TEST - ENTORNO DESPLEGADO"
Write-Output "Fecha: $(Get-Date -Format 'yyyy-MM-dd HH:mm')"
Write-Output ("=" * 92)

# --- a) La URL publica carga y el catalogo responde --------------------------
Write-Output ''
Write-Output 'a) Disponibilidad y catalogo'

Comprobar 'Portal en Vercel responde' 200 (CodigoDe $portal)
Comprobar 'API: GET /api/salud' 200 (CodigoDe "$api/api/salud")

$salud = (Invoke-WebRequest -Uri "$api/api/salud" -UseBasicParsing -TimeoutSec 60).Content | ConvertFrom-Json
Comprobar 'API: la base responde' 'True' $salud.base

$cat = (Invoke-WebRequest -Uri "$api/api/productos?per_page=1&orden=nombre" -UseBasicParsing -TimeoutSec 60).Content | ConvertFrom-Json
Comprobar 'Catalogo depurado: 884 productos' 884 $cat.total
Comprobar 'Primer producto por nombre' 'A FOLIC' $cat.data[0].nombre
Comprobar 'El servidor calcula el stock vendible' 'True' ($null -ne $cat.data[0].stock_disponible)

$fac = (Invoke-WebRequest -Uri "$api/api/productos/facetas" -UseBasicParsing -TimeoutSec 60).Content | ConvertFrom-Json
Comprobar 'Facetas: total coincide con el catalogo' 884 $fac.total

# --- b) Codigo nuevo efectivamente desplegado -------------------------------
Write-Output ''
Write-Output 'b) El contenedor sirve el codigo de este avance'

# 405 significa que la ruta NO existe y la URI cae en /api/carrito/{carrito}
# del apiResource: es la senal de que el contenedor es anterior a este avance.
Comprobar 'POST /api/carrito/cotizar existe' 200 (CodigoDe "$api/api/carrito/cotizar" 'POST' '{"items":[]}')
Comprobar 'GET /api/test retirado de produccion' 404 (CodigoDe "$api/api/test")

# --- c) Control de acceso sin usar credenciales -----------------------------
Write-Output ''
Write-Output 'c) Las rutas de gestion rechazan a quien no presenta token'

Comprobar 'GET /api/pedidos sin token' 401 (CodigoDe "$api/api/pedidos")
Comprobar 'GET /api/usuarios sin token' 401 (CodigoDe "$api/api/usuarios")
Comprobar 'GET /api/tablero sin token' 401 (CodigoDe "$api/api/tablero")
Comprobar 'GET /api/reportes/ventas sin token' 401 (CodigoDe "$api/api/reportes/ventas")
Comprobar 'El catalogo publico sigue accesible' 200 (CodigoDe "$api/api/productos?per_page=1")

# --- d) Tiempos de respuesta ------------------------------------------------
Write-Output ''
Write-Output 'd) Tiempo de respuesta del catalogo (RNF03: menos de 2 000 ms)'

$ms = @()
for ($i = 0; $i -lt 6; $i++) {
  $t = Measure-Command { try { Invoke-WebRequest -Uri "$api/api/productos?per_page=24&orden=nombre" -UseBasicParsing -TimeoutSec 60 | Out-Null } catch {} }
  $ms += $t.TotalMilliseconds
}
# Se descarta la primera: en el plan gratuito de Render el servicio se duerme y
# la peticion que lo despierta mide el arranque, no el endpoint.
$utiles = ($ms | Select-Object -Skip 1 | Sort-Object)
Write-Output ("  mediana {0:N0} ms   max {1:N0} ms   (primera, con arranque en frio: {2:N0} ms)" -f `
    $utiles[[math]::Floor($utiles.Count / 2)], $utiles[-1], $ms[0])

Write-Output ''
Write-Output ("=" * 92)
if ($fallos -eq 0) {
  Write-Output 'RESULTADO: todas las comprobaciones pasaron.'
} else {
  Write-Output "RESULTADO: $fallos comprobacion(es) FALLARON."
}
Write-Output ''
Write-Output 'PENDIENTE DE VERIFICACION MANUAL: acceso con las cuentas de demostracion'
Write-Output '(DNI 10000001 administrador y 10000002 cliente). No se automatiza aqui para no'
Write-Output 'dejar contrasenas en el historial del terminal ni en este archivo de evidencia.'
exit $fallos
