<?php
include 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = $conn->real_escape_string($_POST['firstname']);
    $email = $conn->real_escape_string($_POST['email']);

    $sql = "INSERT INTO contactos (nombre, email) VALUES ('$nombre', '$email')";

    if ($conn->query($sql) === TRUE) {
        echo "Nuevo registro creado exitosamente";
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }

    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Contacto</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <h1>Contacto</h1>
    <form method="POST" action="contact.php">
        <label for="firstname">Nombre:</label>
        <input type="text" id="firstname" name="firstname" required><br>

        <label for="email">Email:</label>
        <input type="email" id="email" name="email" required><br>

        <button type="submit">Enviar</button>
    </form>
</body>
</html>
