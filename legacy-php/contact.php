<?php
session_start();
include 'php/config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = $_POST['name'];
    $email = $_POST['email'];
    $telefono = $_POST['phone'];
    $motivo = $_POST['reason'];
    $mensaje = $_POST['message'];
    
    $sql = "INSERT INTO contacto (nombre, email, telefono, motivo, mensaje, fecha) VALUES (?, ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $nombre, $email, $telefono, $motivo, $mensaje);
    
    if ($stmt->execute()) {
        $success_message = "Su consulta ha sido enviada con éxito.";
    } else {
        $error_message = "Hubo un error al enviar su consulta. Por favor, inténtelo de nuevo.";
    }

    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Contacto - Botica San Juan</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <script src="https://kit.fontawesome.com/a076d05399.js"></script> 
</head>
<body>
    <header>
        <div class="navbar">
            <img src="images/logo botica.jpg" alt="Logo de la Botica San Juan" class="logo">
            <nav>
                <ul>
                    <li><a href="index.php">Inicio</a></li>
                    <li><a href="products.php">Productos</a></li>
                    <li><a href="services.php">Servicios</a></li>
                    <li><a href="about.php" target="_blank">Nosotros</a></li>
                    <li><a href="cart/index.php">Carrito de Compras</a></li>
                    <li><a href="contact.php" target="_blank">Contacto</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main>
        <section class="contact-section">
            <div class="contact-container">
                <div class="contact-image">
                    <img src="images/pharmacist.webp" alt="Atención al Cliente Botica San Juan">
                </div>
                <div class="contact-form">
                    <h1>¿TIENES ALGUNA DUDA?</h1>
                    <p>Te asesoramos y atendemos todas tus consultas</p>
                    <p>Nuestro staff de Botica San Juan está a su disposición para atenderlo en las consultas que usted refiera, así podrá tener un mejor asesoramiento y experiencia de compra. Para ello, debe completar el siguiente formulario.</p>
                    <?php if (isset($success_message)): ?>
                        <p class="success-message"><?php echo $success_message; ?></p>
                    <?php endif; ?>
                    <?php if (isset($error_message)): ?>
                        <p class="error-message"><?php echo $error_message; ?></p>
                    <?php endif; ?>
                    <form method="POST" action="contact.php">
                        <label for="name">Nombres y Apellidos:</label>
                        <input type="text" id="name" name="name" required>

                        <label for="email">Correo electrónico:</label>
                        <input type="email" id="email" name="email" required>

                        <label for="phone">Teléfono de contacto:</label>
                        <input type="tel" id="phone" name="phone" required>

                        <label for="reason">Motivo de la consulta:</label>
                        <select id="reason" name="reason" required>
                            <option value="">-- Seleccionar --</option>
                            <option value="stock">Deseo comprar más stock</option>
                            <option value="order-status">Consulta el estado de tu pedido</option>
                            <option value="product-info">Consulta de nuestro producto</option>
                            <option value="other">Otros</option>
                        </select>

                        <label for="message">Mensaje/Consulta:</label>
                        <textarea id="message" name="message" rows="4" required></textarea>

                        <div class="terms">
                            <input type="checkbox" id="terms" name="terms" required>
                            <label for="terms">Acepto Términos y condiciones / Política de privacidad.</label>
                        </div>

                        <button type="submit">ENVIAR</button>
                    </form>
                </div>
            </div>
        </section>
    </main>

    <footer>
        <div class="footer-content">
            <img src="images/logo botica.jpg" alt="Botica San Juan" class="footer-logo">
            <nav>
                <ul>
                    <li><a href="about.php">About Us</a></li>
                    <li><a href="contact.php">Contact Us</a></li>
                    <li><a href="terms.php">Terms of Service</a></li>
                </ul>
            </nav>
        </div>
        <p>© 2024 BoticasSanJuan</p>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            form.addEventListener('submit', function(event) {
                // Valida el nombre completo
                const name = document.getElementById('name').value.trim();
                if (name.split(' ').length < 2) {
                    alert('Por favor, ingresa tu nombre completo.');
                    event.preventDefault();
                    return;
                }

                // Valida el correo electrónico
                const email = document.getElementById('email').value;
                const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailPattern.test(email)) {
                    alert('Por favor, ingresa un correo electrónico válido.');
                    event.preventDefault();
                    return;
                }

                // Valida el teléfono
                const phone = document.getElementById('phone').value;
                const phonePattern = /^[0-9]{9}$/; // el número de teléfono debe tener 9 dígitos 
                if (!phonePattern.test(phone)) {
                    alert('Por favor, ingresa un número de teléfono válido de 10 dígitos.');
                    event.preventDefault();
                    return;
                }

                // Valida el motivo de la consulta
                const reason = document.getElementById('reason').value;
                if (reason === "") {
                    alert('Por favor, selecciona el motivo de la consulta.');
                    event.preventDefault();
                    return;
                }

                // Valida el mensaje/consulta
                const message = document.getElementById('message').value.trim();
                if (message.length < 10) {
                    alert('Por favor, ingresa un mensaje o consulta con al menos 10 caracteres.');
                    event.preventDefault();
                    return;
                }

                // Valida la aceptación de términos y condiciones
                const terms = document.getElementById('terms').checked;
                if (!terms) {
                    alert('Por favor, acepta los términos y condiciones.');
                    event.preventDefault();
                    return;
                }

                // Si todas las validaciones pasan, permite el envío del formulario
                alert('Formulario enviado con éxito.');
            });
        });
    </script>
</body>
</html>
