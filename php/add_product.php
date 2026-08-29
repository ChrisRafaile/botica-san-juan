<?php
include 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = $_POST['nombre'];
    $descripcion = $_POST['descripcion'];
    $precio = $_POST['precio'];
    $stock = $_POST['stock'];
    $imagen = $_POST['imagen'];

    $sql = "INSERT INTO productos (nombre, descripcion, precio, stock, imagen) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssdss", $nombre, $descripcion, $precio, $stock, $imagen);
    $stmt->execute();

    echo "Producto agregado exitosamente";
    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Producto</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <h1>Agregar Producto</h1>
    <form method="POST" action="add_product.php">
        <label for="nombre">Nombre del Producto:</label>
        <input type="text" id="nombre" name="nombre" required><br>

        <label for="descripcion">Descripción:</label>
        <textarea id="descripcion" name="descripcion" required></textarea><br>

        <label for="precio">Precio:</label>
        <input type="number" step="0.01" id="precio" name="precio" required><br>

        <label for="stock">Stock:</label>
        <input type="number" id="stock" name="stock" required><br>

        <label for="imagen">URL de la Imagen:</label>
        <input type="text" id="imagen" name="imagen" required><br>

        <button type="submit">Agregar Producto</button>
    </form>
</body>
</html>
