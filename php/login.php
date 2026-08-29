<?php
session_start();
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    include 'config.php';

    $email = $_POST['email'];
    $password = $_POST['password'];
    $dni = $_POST['dni'];

    $sql = "SELECT * FROM usuarios WHERE email = ? AND dni = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $email, $dni);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if (password_verify($password, $row['password'])) {
            $_SESSION['usuario_id'] = $row['id'];
            $_SESSION['nombre_usuario'] = $row['nombre']; // Establece el nombre de usuario en la sesión
            $_SESSION['foto_perfil'] = $row['foto_perfil']; // Establece la foto de perfil en la sesión

            if (isset($_SESSION['redirect_to_cart']) && $_SESSION['redirect_to_cart']) {
                unset($_SESSION['redirect_to_cart']);
                header("Location: ../cart.php");
            } else {
                header("Location: ../index.php");
            }
            exit();
        } else {
            echo "Contraseña incorrecta";
        }
    } else {
        echo "No se encontró el usuario";
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
    <title>Login - Botica San Juan</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background: linear-gradient(135deg, #6f86d6, #48c6ef);
            font-family: 'Roboto', sans-serif;
        }
        .login-container {
            display: flex;
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            max-width: 800px;
            width: 100%;
        }
        .login-image {
            margin-right: 2rem;
        }
        .login-image img {
            max-width: 300px;
            border-radius: 10px;
        }
        .login-form {
            display: flex;
            flex-direction: column;
            justify-content: center;
            flex: 1;
        }
        .login-form h2 {
            margin-bottom: 1rem;
            color: #333;
        }
        .login-form label {
            margin-bottom: 0.5rem;
            color: #333;
        }
        .login-form input {
            padding: 0.5rem;
            margin-bottom: 1rem;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 1rem;
            width: 100%;
        }
        .login-form button {
            padding: 0.5rem;
            border: none;
            border-radius: 5px;
            background-color: #1e88e5;
            color: white;
            font-size: 1rem;
            cursor: pointer;
        }
        .login-form button:hover {
            background-color: #1565c0;
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
            color: #333;
            width: 100%;
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
    <div class="login-container">
        <div class="login-image">
            <img src="../images/doctors.jpg" alt="Doctors">
        </div>
        <div class="login-form">
            <h2>BOTICA SAN JUAN</h2>
            <form action="login.php" method="POST">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>

                <label for="password">Contraseña:</label>
                <input type="password" id="password" name="password" required>

                <label for="dni">DNI:</label>
                <input type="text" id="dni" name="dni" required>

                <button type="submit">INICIAR SESIÓN</button>
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
