# Tiempos de respuesta de los endpoints publicos del catalogo.
#
#   pwsh scripts/medir-tiempos-api.ps1
#
# POR QUE SE DESCARTAN LAS DOS PRIMERAS MEDICIONES
#
# La primera peticion de cada ruta paga el arranque de PHP, la conexion a
# PostgreSQL y el primer plan de consulta. Incluirla mide el arranque, no el
# endpoint, y produce una cifra que no se repite en ninguna peticion posterior.
#
# POR QUE SE PUBLICA LA MEDIANA Y EL p95, Y NO EL PROMEDIO
#
# El promedio lo arrastra un unico valor alto y deja de describir lo que le
# ocurre a la mayoria. El RNF03 del proyecto se redacto como percentil 95
# ("respondera en menos de dos segundos"), asi que esa es la cifra que hay que
# comparar contra el umbral.

$rutas = @(
  @{ n = 'catalogo pagina 1 (24 items)'; u = 'http://127.0.0.1:8083/api/productos?per_page=24&orden=nombre' },
  @{ n = 'busqueda "amoxicilina"';       u = 'http://127.0.0.1:8083/api/productos?q=amoxicilina&per_page=24' },
  @{ n = 'filtro por laboratorio';       u = 'http://127.0.0.1:8083/api/productos?laboratorio=MEDIFARMA&per_page=24' },
  @{ n = 'facetas (3 agregados SQL)';    u = 'http://127.0.0.1:8083/api/productos/facetas' },
  @{ n = 'pagina 400 de 442';            u = 'http://127.0.0.1:8083/api/productos?per_page=2&page=400&orden=nombre' },
  @{ n = 'cotizar carrito (3 lineas)';   u = 'http://127.0.0.1:8083/api/carrito/cotizar'; cuerpo = '{"items":[{"producto_id":1,"cantidad":2},{"producto_id":306,"cantidad":1},{"producto_id":2,"cantidad":1}]}' }
)

Write-Output "Tiempos de respuesta - API local - $(Get-Date -Format 'yyyy-MM-dd HH:mm')"
Write-Output "Catalogo de 884 productos. 12 peticiones por ruta, se descartan las 2 primeras."
Write-Output ("-" * 86)

foreach ($r in $rutas) {
  $ms = @()
  for ($i = 0; $i -lt 12; $i++) {
    $t = Measure-Command {
      try {
        if ($r.cuerpo) {
          Invoke-WebRequest -Uri $r.u -Method Post -Body $r.cuerpo -ContentType 'application/json' -UseBasicParsing -TimeoutSec 20 | Out-Null
        } else {
          Invoke-WebRequest -Uri $r.u -UseBasicParsing -TimeoutSec 20 | Out-Null
        }
      } catch { }
    }
    $ms += $t.TotalMilliseconds
  }

  $utiles = $ms | Select-Object -Skip 2
  $ord = $utiles | Sort-Object
  $idx95 = [math]::Min([math]::Floor($ord.Count * 0.95), $ord.Count - 1)

  Write-Output ("{0,-30} mediana {1,7:N1} ms   p95 {2,7:N1} ms   max {3,7:N1} ms   n={4}" -f `
      $r.n, $ord[[math]::Floor($ord.Count / 2)], $ord[$idx95], $ord[-1], $ord.Count)
}

Write-Output ("-" * 86)
Write-Output "Umbral del RNF03: la busqueda de productos responde en menos de 2 000 ms (p95)."
