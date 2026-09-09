$ErrorActionPreference = 'Stop'
$base = 'http://127.0.0.1:8083/api'

Write-Host 'SMOKE: LOGIN'
$loginBody = @{ dni='12345678'; password='123456' } | ConvertTo-Json
$login = Invoke-RestMethod -Method Post -Uri "$base/login" -ContentType 'application/json' -Body $loginBody
$token = $login.token
$headers = @{ Authorization = "Bearer $token" }
Write-Host "OK login user=$($login.user.dni) role=$($login.user.rol)"

Write-Host 'SMOKE: USERS'
$suffix = Get-Random -Minimum 10000 -Maximum 99999
$newUserPayload = @{ nombre="Smoke User $suffix"; dni="9$suffix"; email="smoke.$suffix@test.local"; telefono='900111222'; rol='cliente'; password='12345678' } | ConvertTo-Json
$newUser = Invoke-RestMethod -Method Post -Uri "$base/usuarios" -Headers $headers -ContentType 'application/json' -Body $newUserPayload
$usersPage = Invoke-RestMethod -Method Get -Uri "$base/usuarios?paginate=1&page=1&per_page=10&q=$suffix&rol=cliente" -Headers $headers
$updated = Invoke-RestMethod -Method Put -Uri "$base/usuarios/$($newUser.id)" -Headers $headers -ContentType 'application/json' -Body (@{ nombre="Smoke User Edited $suffix" } | ConvertTo-Json)
Invoke-RestMethod -Method Delete -Uri "$base/usuarios/$($newUser.id)" -Headers $headers | Out-Null
Write-Host "OK users create=$($newUser.id) paged_total=$($usersPage.total) updated=$($updated.nombre) deleted=true"

Write-Host 'SMOKE: ORDERS'
$testOrder = Invoke-RestMethod -Method Post -Uri "$base/pedidos" -Headers $headers -ContentType 'application/json' -Body (@{ usuario_id=$login.user.id; fecha_pedido=(Get-Date).ToString('yyyy-MM-dd HH:mm:ss'); total=19.9; estado='pendiente' } | ConvertTo-Json)
Invoke-RestMethod -Method Put -Uri "$base/pedidos/$($testOrder.id)" -Headers $headers -ContentType 'application/json' -Body (@{ estado='enviado' } | ConvertTo-Json) | Out-Null
$orderAfterUpdate = Invoke-RestMethod -Method Get -Uri "$base/pedidos/$($testOrder.id)" -Headers $headers
Invoke-RestMethod -Method Delete -Uri "$base/pedidos/$($testOrder.id)" -Headers $headers | Out-Null
Write-Host "OK orders create=$($testOrder.id) status=$($orderAfterUpdate.estado) deleted=true"

Write-Host 'SMOKE: INVENTORY'
$inventoryPaged = Invoke-RestMethod -Method Get -Uri "$base/productos?page=1&per_page=1"
$product = $inventoryPaged.data[0]
$fullProduct = Invoke-RestMethod -Method Get -Uri "$base/productos/$($product.id)"
$originalStock = [int]$fullProduct.stock
$newStock = $originalStock + 1
$putBodyUp = @{ nombre=$fullProduct.nombre; concentracion=$fullProduct.concentracion; adicional=$fullProduct.adicional; laboratorio=$fullProduct.laboratorio; presentacion=$fullProduct.presentacion; tipo=$fullProduct.tipo; stock=$newStock; precio=$fullProduct.precio } | ConvertTo-Json
Invoke-RestMethod -Method Put -Uri "$base/productos/$($product.id)" -ContentType 'application/json' -Body $putBodyUp | Out-Null
$afterIncrease = Invoke-RestMethod -Method Get -Uri "$base/productos/$($product.id)"
$putBodyRestore = @{ nombre=$fullProduct.nombre; concentracion=$fullProduct.concentracion; adicional=$fullProduct.adicional; laboratorio=$fullProduct.laboratorio; presentacion=$fullProduct.presentacion; tipo=$fullProduct.tipo; stock=$originalStock; precio=$fullProduct.precio } | ConvertTo-Json
Invoke-RestMethod -Method Put -Uri "$base/productos/$($product.id)" -ContentType 'application/json' -Body $putBodyRestore | Out-Null
$afterRestore = Invoke-RestMethod -Method Get -Uri "$base/productos/$($product.id)"
Write-Host "OK inventory id=$($product.id) before=$originalStock plus1=$($afterIncrease.stock) restored=$($afterRestore.stock)"

$ordersPaged = Invoke-RestMethod -Method Get -Uri "$base/pedidos?paginate=1&page=1&per_page=5&status=all" -Headers $headers
$invPagedCheck = Invoke-RestMethod -Method Get -Uri "$base/productos?page=1&per_page=5&stock_status=normal"
Write-Host "OK paged orders_total=$($ordersPaged.total) inventory_total=$($invPagedCheck.total)"

Write-Host 'SMOKE_DONE'
