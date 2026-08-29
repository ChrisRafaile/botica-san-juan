<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../php/login.php");
    exit();
}

include '../php/config.php';

$usuario_id = $_SESSION['usuario_id'];

// Vaciar el carrito del usuario en la base de datos
$sql = "DELETE FROM carrito WHERE usuario_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $usuario_id);

if ($stmt->execute()) {
    header("Location: ../cart.php");
    exit();
} else {
    echo "Error al vaciar el carrito.";
}

$stmt->close();
$conn->close();
?>
