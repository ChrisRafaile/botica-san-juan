<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

include 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['id'])) {
    $producto_id = $_POST['id'];
    $usuario_id = $_SESSION['usuario_id'];

    // Elimina el producto del carrito del usuario en la base de datos
    $sql = "DELETE FROM carrito WHERE producto_id = ? AND usuario_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $producto_id, $usuario_id);

    if ($stmt->execute()) {
        header("Location: ../cart.php");
        exit();
    } else {
        echo "Error al eliminar el producto del carrito.";
    }

    $stmt->close();
}
$conn->close();
?>
