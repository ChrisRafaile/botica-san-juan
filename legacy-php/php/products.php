<?php
include '../php/config.php';

$sql = "SELECT * FROM productos";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Productos</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background: #f7f7f7;
            padding: 20px;
        }
        .productos {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        .producto {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            text-align: center; /* Center align text */
        }
        .producto img {
            max-width: 100%;
            height: auto;
            border-radius: 10px;
        }
        .producto h2 {
            margin: 10px 0;
            font-size: 18px;
            color: #333;
        }
        .producto p {
            margin: 5px 0;
            color: #666;
        }
        .producto .add-to-cart {
            display: inline-block;
            padding: 10px 20px;
            background-color: #1e88e5;
            color: #fff;
            border-radius: 5px;
            text-decoration: none;
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
            width: 24px;
            height: 24px;
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
            <a href="../php/view_cart.php" class="cart-button">
                <img src="../images/cart-icon.png" alt="Carrito">
                Carrito
            </a>
        </div>
    </header>
    <main>
        <h1>Productos</h1>
        <div class="productos">
            <?php while ($producto = $result->fetch_assoc()): ?>
                <div class="producto">
                    <h2><?php echo htmlspecialchars($producto['nombre']); ?></h2>
                    <p><?php echo htmlspecialchars($producto['concentracion']); ?></p>
                    <p><?php echo htmlspecialchars($producto['adicional']); ?></p>
                    <p>Laboratorio: <?php echo htmlspecialchars($producto['laboratorio']); ?></p>
                    <p>Presentación: <?php echo htmlspecialchars($producto['presentacion']); ?></p>
                    <p>Tipo: <?php echo htmlspecialchars($producto['tipo']); ?></p>
                    <p>Stock: <?php echo htmlspecialchars($producto['stock']); ?></p>
                    <p>Precio: S/<?php echo number_format($producto['precio'], 2); ?></p>
                    <img src="../images/default_image.png" alt="default.png" class="icon">
                    <form action="../php/add_to_cart.php" method="post">
                        <input type="hidden" name="producto_id" value="<?php echo $producto['id']; ?>">
                        <input type="number" name="cantidad" value="1" min="1">
                        <button type="submit" class="add-to-cart">Añadir al carrito</button>
                    </form>
                </div>
            <?php endwhile; ?>
        </div>
    </main>
    <footer class="footer">
        <div class="footer-content">
            <img src="../images/logo botica.jpg" alt="Botica San Juan" class="footer-logo">
            <nav class="footer-nav">
                <ul>
                    <li><a href="../about.php">Sobre Botica San Juan</a></li>
                    <li><a href="../services.php">Nuestros Servicios</a></li>
                    <li><a href="../contact.php">Contáctanos</a></li>
                </ul>
                <ul>
                    <li><a href="../terms.php">Términos y Condiciones</a></li>
                    <li><a href="../privacy.php">Políticas de Privacidad</a></li>
                    <li><a href="../reclamaciones.php">Libro de Reclamaciones</a></li>
                    <img src="../images/LibroReclamaciones.jpg" alt="LibroReclamaciones" class="footer-logo">
                </ul>
                <ul>
                    <li><a href="../faq.php">Preguntas Frecuentes</a></li>
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
