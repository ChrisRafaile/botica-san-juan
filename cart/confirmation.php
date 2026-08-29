<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../php/login.php");
    exit();
}

include '../php/config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario_id = $_SESSION['usuario_id'];
    $address = $_POST['address'];
    $card_number = $_POST['card_number'];
    $expiry_date = $_POST['expiry_date'];
    $cvv = $_POST['cvv'];
    $total = isset($_SESSION['total']) ? $_SESSION['total'] : 0; // Obtiene el total desde la sesión

    // Crea el pedido en la base de datos
    $sql = "INSERT INTO pedidos (usuario_id, total, address, card_number, expiry_date, cvv) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("idssss", $usuario_id, $total, $address, $card_number, $expiry_date, $cvv);
    $stmt->execute();
    $pedido_id = $stmt->insert_id;
    $stmt->close();

    // Obtiene los productos del carrito
    $sql = "SELECT c.producto_id, c.cantidad, p.nombre, p.precio FROM carrito c JOIN productos p ON c.producto_id = p.id WHERE c.usuario_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $productos = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Inserta detalles del pedido
    foreach ($productos as $producto) {
        $sql = "INSERT INTO pedido_detalles (pedido_id, producto_id, cantidad, precio) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiid", $pedido_id, $producto['producto_id'], $producto['cantidad'], $producto['precio']);
        $stmt->execute();
        $stmt->close();
    }

    // Vaciar el carrito
    $sql = "DELETE FROM carrito WHERE usuario_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $stmt->close();

    // Muestra la página de confirmación con los detalles del pedido
    echo "
    <!DOCTYPE html>
    <html lang='es'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Confirmación de Pago - Botica San Juan</title>
        <link rel='stylesheet' href='../css/style.css'>
        <style>
            body {
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                background: linear-gradient(135deg, #6f86d6, #48c6ef);
                font-family: 'Roboto', sans-serif;
            }
            .confirmation-container {
                background: white;
                padding: 2rem;
                border-radius: 10px;
                box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
                max-width: 600px;
                width: 100%;
                text-align: center;
            }
            .confirmation-container h2 {
                margin-bottom: 1rem;
                color: #333;
            }
            .confirmation-container p {
                margin-bottom: 0.5rem;
                color: #333;
            }
            .confirmation-container ul {
                list-style: none;
                padding: 0;
                margin-bottom: 1rem;
                text-align: left;
            }
            .confirmation-container ul li {
                margin-bottom: 0.5rem;
                color: #333;
                padding: 0.5rem;
                border-bottom: 1px solid #ddd;
            }
            .btn {
                padding: 0.5rem 1rem;
                border: none;
                border-radius: 5px;
                background-color: #1e88e5;
                color: white;
                font-size: 1rem;
                cursor: pointer;
                text-decoration: none;
                display: inline-block;
                margin-top: 1rem;
            }
            .btn:hover {
                background-color: #1565c0;
            }
        </style>
    </head>
    <body>
        <div class='confirmation-container'>
            <h2>Confirmación de Pago</h2>
            <p>Gracias, {$_SESSION['nombre_usuario']}. Tu pago ha sido procesado con éxito.</p>
            <p><strong>Dirección de envío:</strong> $address</p>
            <p><strong>Total pagado:</strong> S/" . number_format($total, 2) . "</p>
            <h3>Detalles del Pedido:</h3>
            <ul>";
            foreach ($productos as $producto) {
                echo "<li><strong>Producto:</strong> {$producto['nombre']}<br>
                      <strong>Cantidad:</strong> {$producto['cantidad']}<br>
                      <strong>Precio:</strong> S/" . number_format($producto['precio'], 2) . "</li>";
            }
            echo "</ul>
            <a href='../index.php' class='btn'>Volver al Inicio</a>
        </div>
    </body>
    </html>";
}
?>
