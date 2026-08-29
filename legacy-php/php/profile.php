<?php
session_start();
include 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];

// Obtiene la información del usuario
$sql = "SELECT nombre, email, dni, foto_perfil FROM usuarios WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Perfil de Usuario</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background: #f7f7f7;
            padding: 20px;
        }
        .profile-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        .profile-container h1 {
            margin-bottom: 20px;
        }
        .profile-container img {
            border-radius: 50%;
            margin-bottom: 20px;
        }
        .profile-container p {
            margin: 5px 0;
            color: #666;
        }
        .profile-container form {
            margin-top: 20px;
        }
        .profile-container input[type="file"] {
            display: block;
            margin: 0 auto 10px auto;
        }
        .profile-container button {
            background-color: #1e88e5;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
        }
        .profile-container button:hover {
            background-color: #1565c0;
        }
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #2196F3;
            padding: 10px;
        }
        .navbar ul {
            list-style: none;
            display: flex;
            margin: 0;
            padding: 0;
        }
        .navbar ul li {
            margin: 0 10px;
        }
        .navbar ul li a {
            color: #fff;
            text-decoration: none;
        }
	.cart-button {
            display: flex;
            align-items: center;
            color: #fff;
            text-decoration: none;
        }
        .cart-button img {
            width: 24px; /* Ajustar el tamaño del icono del carrito */
            height: 24px; /* Ajustar el tamaño del icono del carrito */
            margin-right: 5px;
        }

    </style>
</head>
<body>
    <header>
        <div class="navbar">
            <img src="../images/logo botica.jpg" alt="Logo de la Botica San Juan" class="logo">
            <nav>
                <ul>
                    <li><a href="../index.php">Inicio</a></li>
                    <li><a href="../products.php">Productos</a></li>
                    <li><a href="../services.php">Servicios</a></li>
                    <li><a href="../about.php">Nosotros</a></li>
                    <li><a href="../contact.php">Contacto</a></li>
                </ul>
            </nav>
            <a href="view_cart.php" class="cart-button">
                <img src="../images/cart-icon.png" alt="Carrito">
                Carrito
            </a>
        </div>
    </header>
    <main class="profile-container">
        <h1>Perfil de Usuario</h1>
        <img src="../uploads/<?php echo htmlspecialchars($user['foto_perfil']); ?>" alt="Foto de Perfil" width="150" height="150">
        <p>Nombre: <?php echo htmlspecialchars($user['nombre']); ?></p>
        <p>Email: <?php echo htmlspecialchars($user['email']); ?></p>
        <p>DNI: <?php echo htmlspecialchars($user['dni']); ?></p>
        <h2>Actualizar Foto de Perfil</h2>
        <form action="upload_photo.php" method="post" enctype="multipart/form-data">
            <input type="file" name="foto_perfil" required>
            <button type="submit">Subir Foto</button>
        </form>
    </main>
    <footer class="footer">
        <div class="footer-content">
            <img src="../images/logo botica.jpg" alt="Botica San Juan" class="footer-logo">
            <nav class="footer-nav">
                <ul>
                    <li><a href="about.php">Sobre Botica San Juan</a></li>
                    <li><a href="services.php">Nuestros Servicios</a></li>
                    <li><a href="contact.php">Contáctanos</a></li>
                </ul>
                <ul>
                    <li><a href="terms.php">Términos y Condiciones</a></li>
                    <li><a href="privacy.php">Políticas de Privacidad</a></li>
                    <li><a href="reclamaciones.php">Libro de Reclamaciones</a></li>
                    <img src="../images/LibroReclamaciones.jpg" alt="LibroReclamaciones" class="footer-logo">
                </ul>
                <ul>
                    <li><a href="faq.php">Preguntas Frecuentes</a></li>
                    <li><a href="https://wa.me/997551917">WhatsApp</a></li>
                    <li><a href="https://www.facebook.com">Facebook</a></li>
                </ul>
            </nav>
            <div class="footer-icons">
                <img src="../images/footer-amex.svg" alt="Amex">
                <img src="../images/footer-mastercard.svg" alt="Mastercard">
                <img src="../images/footer-oh.svg" alt="Oh">
                <img src="../images/footer-visa.svg" alt="Visa">
            </div>
        </div>
        <p>© 2024 Botica San Juan - R.U.C. N° 10075148297 | Todos los derechos reservados</p>
    </footer>
</body>
</html>
