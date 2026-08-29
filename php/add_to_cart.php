<?php
session_start();
include 'config.php';

if (isset($_POST['producto_id']) && isset($_POST['cantidad'])) {
    $producto_id = $_POST['producto_id'];
    $cantidad = $_POST['cantidad'];
    $usuario_id = $_SESSION['usuario_id'];

    $sql = "INSERT INTO carrito (usuario_id, producto_id, cantidad) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE cantidad = cantidad + VALUES(cantidad)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $usuario_id, $producto_id, $cantidad);
    $stmt->execute();
    $stmt->close();

    header("Location: ../cart.php");
    exit();
}
?>
