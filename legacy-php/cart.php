<?php
session_start();
include 'php/config.php';

$total = 0;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Carrito de Compras</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background: #f7f7f7;
            padding: 20px;
        }
        .cart {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .cart table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .cart table, .cart th, .cart td {
            border: 1px solid #ddd;
        }
        .cart th, .cart td {
            padding: 10px;
            text-align: left;
        }
        .cart th {
            background-color: #f4f4f4;
        }
        .cart img {
            max-width: 100px;
            height: auto;
        }
        .cart-total {
            margin-top: 20px;
            text-align: right;
        }
        .cart-total h3 {
            font-size: 1.5rem;
            margin-bottom: 10px;
        }
        .cart-actions {
            text-align: right;
            margin-top: 20px;
        }
        .cart-actions a, .cart-actions button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #1e88e5;
            color: #fff;
            border-radius: 5px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            margin-left: 10px;
        }
        .cart-actions button:hover {
            background-color: #1565c0;
        }
        .cart-actions .remove-button {
            background-color: #e53935;
        }
        .cart-actions .remove-button:hover {
            background-color: #c62828;
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
    <script>
        function confirmarVaciado() {
            if (confirm("¿Está seguro de que desea vaciar el carrito?")) {
                window.location.href = "cart/vaciar_carrito.php";
            }
        }

        function confirmarPago() {
            if (confirm("¿Desea proceder al pago?")) {
                window.location.href = "cart/checkout.php";
            }
        }
    </script>
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
                    <li><a href="about.php">Nosotros</a></li>
                    <li><a href="contact.php">Contacto</a></li>
                </ul>
            </nav>
            <a href="cart.php" class="cart-button">
                <img src="images/cart-icon.png" alt="Carrito">
                Carrito
            </a>
        </div>
    </header>
    <main class="cart">
        <h1>Carrito de Compras</h1>
        <table>
            <tr>
                <th>Producto</th>
                <th>Cantidad</th>
                <th>Precio</th>
                <th>Total</th>
                <th>Acción</th>
            </tr>
            <?php if(isset($_SESSION['usuario_id'])): ?>
                <?php 
                $usuario_id = $_SESSION['usuario_id'];
                $sql = "SELECT c.producto_id, c.cantidad, p.nombre, p.precio FROM carrito c JOIN productos p ON c.producto_id = p.id WHERE c.usuario_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $usuario_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if($result->num_rows > 0):
                    while($row = $result->fetch_assoc()):
                        $total += $row['precio'] * $row['cantidad'];
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['nombre']); ?></td>
                    <td><?php echo $row['cantidad']; ?></td>
                    <td>S/<?php echo number_format($row['precio'], 2); ?></td>
                    <td>S/<?php echo number_format($row['precio'] * $row['cantidad'], 2); ?></td>
                    <td>
                        <form action="php/eliminar_del_carrito.php" method="post" style="display:inline;">
                            <input type="hidden" name="id" value="<?php echo $row['producto_id']; ?>">
                            <button type="submit" class="remove-button">Eliminar</button>
                        </form>
                    </td>
                </tr>
                <?php 
                    endwhile; 
                    $_SESSION['total'] = $total;
                else: ?>
                <tr>
                    <td colspan="5">El carrito está vacío.</td>
                </tr>
                <?php endif; ?>
            <?php else: ?>
            <tr>
                <td colspan="5">Debes iniciar sesión para ver tu carrito.</td>
            </tr>
            <?php endif; ?>
        </table>
        <div class="cart-total">
            <h3>Total: S/<?php echo number_format($total, 2); ?></h3>
        </div>
        <div class="cart-actions">
            <button onclick="confirmarVaciado()">Vaciar Carrito</button>
            <button onclick="confirmarPago()">Proceder al Pago</button>
        </div>
    </main>
    <footer class="footer">
        <div class="footer-content">
            <img src="images/logo botica.jpg" alt="Botica San Juan" class="footer-logo">
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
                    <img src="images/LibroReclamaciones.jpg" alt="LibroReclamaciones" class="footer-logo">
                </ul>
                <ul>
                    <li><a href="faq.php">Preguntas Frecuentes</a></li>
                    <li><a href="https://wa.me/997551917">WhatsApp</a></li>
                    <li><a href="https://www.facebook.com">Facebook</a></li>
                </ul>
            </nav>
            <div class="footer-icons">
                <img src="images/footer-amex.svg" alt="Amex">
                <img src="images/footer-mastercard.svg" alt="Mastercard">
                <img src="images/footer-oh.svg" alt="Oh">
                <img src="images/footer-visa.svg" alt="Visa">
            </div>
        </div>
        <p>© 2024 Botica San Juan - R.U.C. N° 10075148297 | Todos los derechos reservados</p>
    </footer>
</body>
</html>
