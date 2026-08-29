<?php
include 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = $_POST['nombre'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $dni = $_POST['dni'];
    $foto_perfil = 'default.jpg';

    $sql = "INSERT INTO usuarios (nombre, email, password, dni, foto_perfil) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $nombre, $email, $password, $dni, $foto_perfil);

    if ($stmt->execute()) {
        echo "<script>
                alert('Registro de usuario realizado correctamente');
                window.location.href = '../index.php';
              </script>";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - Botica San Juan</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(to right, #8E2DE2, #4A00E0);
        }
        .container {
            display: flex;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 800px;
            width: 100%;
        }
        .left {
            background-color: #4A00E0;
            padding: 20px;
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            flex: 1;
        }
        .left img {
            max-width: 100%;
            height: auto;
        }
        .right {
            flex: 1;
            padding: 20px;
        }
        h2 {
            margin-bottom: 10px;
            font-size: 22px;
            font-weight: 700;
            color: #333;
        }
        form label {
            display: block;
            margin-bottom: 5px;
            color: #666;
        }
        form input {
            width: 100%;
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        form button {
            background: linear-gradient(to right, #8E2DE2, #4A00E0);
            border: none;
            padding: 10px;
            color: white;
            font-size: 16px;
            cursor: pointer;
            border-radius: 5px;
            width: 100%;
        }
        form button:hover {
            background: linear-gradient(to right, #4A00E0, #8E2DE2);
        }
        .social-login {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 10px;
        }
        .social-login button {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            background: white;
            width: 100%;
            color: #333;
        }
        .social-login button img {
            margin-right: 10px;
            width: 20px;
            height: 20px;
        }
        .social-login button:hover {
            background-color: #f1f1f1;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="left">
            <img src="../images/UsuarioLogo.jpg" alt="Imagen de usuario">
        </div>
        <div class="right">
            <h2>Registro en Botica San Juan</h2>
            <form action="register.php" method="POST">
                <label for="nombre">Nombre:</label>
                <input type="text" id="nombre" name="nombre" required>

                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>

                <label for="password">Contraseña:</label>
                <input type="password" id="password" name="password" required>

                <label for="confirm-password">Confirmar Contraseña:</label>
                <input type="password" id="confirm-password" name="confirm-password" required>

                <label for="dni">DNI:</label>
                <input type="text" id="dni" name="dni" required>

                <button type="submit">Registrar</button>
            </form>
            <div class="social-login">
                <button onclick="window.location.href='https://accounts.google.com/signin'">
                    <img src="../images/google-logo.svg" alt="Google">
                    Continuar con Google
                </button>
                <button onclick="window.location.href='https://login.microsoftonline.com/'">
                    <img src="../images/microsoft-logo.svg" alt="Microsoft">
                    Continuar con una cuenta de Microsoft
                </button>
                <button onclick="window.location.href='https://appleid.apple.com/auth/authorize'">
                    <img src="../images/apple-logo.svg" alt="Apple">
                    Continuar con Apple
                </button>
            </div>
        </div>
    </div>
</body>
</html>
