<?php
session_start();
include 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    $_SESSION['redirect_to_cart'] = true;
    header("Location: login.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];

$sql = "SELECT p.nombre, p.precio, c.cantidad FROM carrito c INNER JOIN productos p ON c.producto_id = p.id WHERE c.usuario_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Carrito de Compras</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background: #f7f7f7;
            padding: 20px;
        }
        .carrito {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .carrito h1 {
            margin-bottom: 20px;
            color: #333;
        }
        .carrito-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .carrito-item p {
            margin: 0;
            color: #666;
        }
        .carrito-total {
            text-align: right;
            font-size: 20px;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="carrito">
        <h1>Carrito de Compras</h1>
        <?php
        $total = 0;
        while ($item = $result->fetch_assoc()): 
            $total += $item['precio'] * $item['cantidad'];
        ?>
            <div class="carrito-item">
                <p><?php echo htmlspecialchars($item['nombre']); ?></p>
                <p><?php echo htmlspecialchars($item['cantidad']); ?> x S/<?php echo number_format($item['precio'], 2); ?></p>
            </div>
        <?php endwhile; ?>
        <div class="carrito-total">
            <p>Total: S/<?php echo number_format($total, 2); ?></p>
        </div>
    </div>
</body>
</html>
