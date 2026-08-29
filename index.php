<?php
session_start();
include 'php/config.php';

// Obtiene información del usuario si está conectado
$usuario = null;
if (isset($_SESSION['usuario_id'])) {
    $usuario_id = $_SESSION['usuario_id'];
    $sql = "SELECT nombre, foto_perfil FROM usuarios WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $usuario = $result->fetch_assoc();
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Botica San Juan</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css">
    <style>
        .user-info {
            display: flex;
            align-items: center;
            margin-left: auto;
        }
        .profile-pic {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
        }
        .btn-logout {
            background-color: #e53935;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            cursor: pointer;
        }
        .btn-logout:hover {
            background-color: #c62828;
        }
    </style>
</head>
<body>
    <header data-aos="fade-down">
        <div class="navbar">
            <img src="images/logo botica.jpg" alt="Logo de la Botica San Juan" class="logo">
            <nav>
                <ul>
                    <li><a href="index.php">Inicio</a></li>
                    <li><a href="products.php" target="_blank">Productos</a></li>
                    <li><a href="services.php" target="_blank">Servicios</a></li>
                    <li><a href="about.php" target="_blank">Nosotros</a></li>
                    <li><a href="cart.php">Carrito de Compras</a></li>
                    <li><a href="contact.php" target="_blank">Contacto</a></li>
                </ul>
            </nav>
            <?php if ($usuario): ?>
                <div class="user-info">
                    <img src="uploads/<?php echo htmlspecialchars($usuario['foto_perfil']); ?>" alt="Foto de Perfil" class="profile-pic">
                    <a href="php/profile.php"><?php echo htmlspecialchars($usuario['nombre']); ?></a>
                    <a href="php/logout.php" class="btn-logout">Cerrar Sesión</a>
                </div>
            <?php else: ?>
                <a href="php/register.php" class="btn-register">Regístrate</a>
                <a href="php/login.php" class="btn-login">Login</a>
            <?php endif; ?>
        </div>
    </header>
    <section class="hero" data-aos="fade-up">
        <div class="hero-text">
            <h1>Acceso rápido a medicamentos de calidad</h1>
            <p>Productos farmacéuticos y cuidado personal a tu alcance con entrega confiable.</p>
            <div class="hero-buttons">
                <a href="products.php" class="btn btn-primary">Compra Ahora</a>
                <a href="#contact" class="btn btn-secondary">Contacto</a>
            </div>
        </div>
        <img src="images/medicamentos.jpg" alt="Medicamentos" class="hero-image" data-aos="zoom-in">
    </section>
    <main class="container">
        <section class="info-section about-hero" data-aos="fade-right">
            <h2>Soluciones de salud a tu puerta</h2>
            <p style="text-align: center;">
                Amplia gama de productos y entrega rápida para tu bienestar.
            </p>
            <p style="text-align: center;">
                En Botica San Juan, entendemos la importancia de acceder rápidamente a medicamentos y productos de cuidado personal. Ofrecemos una extensa variedad en colaboración con los principales laboratorios nacionales e internacionales, garantizando calidad y precios competitivos. Nuestro servicio de delivery es confiable y eficiente, asegurando que recibas lo que necesitas sin demoras. Además, nuestro equipo está comprometido con brindarte una atención al cliente excepcional para resolver todas tus inquietudes.
            </p>
        </section>
        <section class="about-us products-highlight" data-aos="fade-left">
            <div class="about-text about-info">
                <h2>Nosotros</h2>
                <p>La Botica San Juan es una empresa peruana especializada en la comercialización de productos farmacéuticos, establecemos colaboraciones estratégicas con los principales laboratorios nacionales e internacionales que cumplen rigurosamente con las normativas del país. Demostrando un compromiso inquebrantable hacia las necesidades de nuestros clientes y ofreciendo precios altamente competitivos en el mercado.</p>
            </div>
            <img src="images/frontal.jpg" alt="Nosotros" class="about-image about-image-section section-image" data-aos="zoom-in">
        </section>
        <section class="products-section testimonials" data-aos="fade-up">
            <div class="section-content">
                <h2>Variedad y calidad en un solo lugar</h2>
                <h3>Encuentra todo lo que necesitas para tu salud con seguridad y confianza.</h3>
                <p>Botica San Juan ofrece una extensa gama de productos farmacéuticos y de cuidado personal, cumpliendo con las normativas más estrictas. Nos aliamos con laboratorios renombrados para garantizar la calidad a precios competitivos. Nuestro objetivo es brindar soluciones efectivas a tus necesidades médicas.</p>
            </div>
            <img src="images/1.jpg" alt="Productos" class="section-image products-highlight" data-aos="zoom-in">
        </section>
        <section class="delivery-section delivery-info" data-aos="fade-up">
            <div class="section-content">
                <h2>Entrega rápida y confiable</h3>
                <h3>Recibe tus medicamentos en tiempo récord sin salir de casa.</h3>
                <p>En Botica San Juan, nos comprometemos a ofrecerte un servicio de delivery rápido y seguro. Nuestro equipo se asegura de que tus productos lleguen a tu puerta en el menor tiempo posible, manteniendo siempre la calidad y seguridad.</p>
            </div>
            <img src="images/2.jpg" alt="Delivery" class="section-image delivery-info" data-aos="zoom-in">
        </section>
        <section class="customer-service-section contact-info" data-aos="fade-up">
            <div class="section-content">
                <h2>Atención al cliente excepcional</h2>
                <h3>Siempre a tu disposición para resolver tus dudas.</h3>
                <p>En Botica San Juan, nos destacamos por nuestro compromiso con el servicio al cliente. Nuestro equipo está siempre listo para brindarte la mejor orientación y apoyo, asegurando que encuentres exactamente lo que necesitas de manera rápida y sencilla.</p>
            </div>
            <img src="images/medicamentos.jpg" alt="Atención al Cliente" class="section-image contact-info" data-aos="zoom-in">
        </section>
        <section class="benefits-section" data-aos="fade-up">
            <h2>Beneficios de elegir Botica San Juan</h2>
            <div class="benefits-grid">
                <div class="benefit" data-aos="zoom-in">
                    <i class="fas fa-medkit"></i>
                    <h3>Variedad y calidad en un solo lugar</h3>
                    <p>Encuentra todo lo que necesitas para tu salud con seguridad y confianza.</p>
                </div>
                <div class="benefit" data-aos="zoom-in">
                    <i class="fas fa-shipping-fast"></i>
                    <h3>Entrega rápida y confiable</h3>
                    <p>Recibe tus medicamentos en tiempo récord sin salir de casa.</p>
                </div>
                <div class="benefit" data-aos="zoom-in">
                    <i class="fas fa-headset"></i>
                    <h3>Atención al cliente excepcional</h3>
                    <p>Siempre a tu disposición para resolver tus dudas.</p>
                </div>
                <div class="benefit" data-aos="zoom-in">
                    <i class="fas fa-dollar-sign"></i>
                    <h3>Precios altamente competitivos</h3>
                    <p>Ahorra sin comprometer la calidad de los productos.</p>
                </div>
            </div>
        </section>        
        <section class="testimonials-section" data-aos="fade-up">
            <h2>Palabras de clientes</h2>
            <div class="testimonials-container">
                <div class="testimonial" data-aos="zoom-in">
                    <img src="images/maria lopez.webp" alt="Maria Lopez">
                    <div class="testimonial-content">
                        <div class="stars">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                        <p>“Siempre tiene los medicamentos que necesito a precios muy competitivos. Además, el personal es muy amable y siempre está dispuesto a ayudar.”</p>
                        <p class="testimonial-name">Maria Lopez</p>
                    </div>
                </div>
                <div class="testimonial" data-aos="zoom-in">
                    <img src="images/carlos.webp" alt="Carlos Fernandez">
                    <div class="testimonial-content">
                        <div class="stars">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star-half-alt"></i>
                        </div>
                        <p>La calidad del servicio en La Botica San Juan es excepcional. Siempre encuentro lo que busco y me siento seguro sabiendo que cumplen con todas las normativas.</p>
                        <p class="testimonial-name">Carlos Fernandez</p>
                    </div>
                </div>
                <div class="testimonial" data-aos="zoom-in">
                    <img src="images/luis.webp" alt="Luis Sanchez">
                    <div class="testimonial-content">
                        <div class="stars">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                        <p>Desde que descubrí La Botica San Juan, no he vuelto a otra farmacia. La atención al cliente es insuperable y los precios son los mejores del mercado.</p>
                        <p class="testimonial-name">Luis Sanchez</p>
                    </div>
                </div>
            </div>
        </section>        
        <section class="cta-section" data-aos="fade-up">
            <h2>Cuida tu salud hoy mismo</h2>
            <p>Accede a productos farmacéuticos de calidad con un solo clic.</p>
            <a href="products.php" class="btn btn-primary">Comprar ahora</a>
            <a href="contact.php" class="btn btn-secondary">Contacto</a>
        </section>
    </main>
    <footer data-aos="fade-up">
        <div class="footer-content">
            <img src="images/logo botica.jpg" alt="Botica San Juan" class="footer-logo">
            <nav>
                <ul>
                    <li><a href="about.php" target="_blank">About Us</a></li>
                    <li><a href="contact.php" target="_blank">Contact Us</a></li>
                    <li><a href="terms.php">Terms of Service</a></li>
                </ul>
            </nav>
        </div>
        <p>© 2024 Boticas San Juan</p>
    </footer>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
    <script>
        AOS.init({
            duration: 1000,
        });
    </script>
</body>
</html>
