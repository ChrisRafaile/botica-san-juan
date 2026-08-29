<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Servicios - Botica San Juan</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <style>
        /* CCS adicional para la pagina servicios */
        .services-section {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1rem;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        .services-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 2rem;
        }
        .service-item {
            flex: 1 1 200px;
            max-width: 220px;
            text-align: center;
        }
        .service-item img {
            width: 80px;
            height: 80px;
            margin-bottom: 1rem;
        }
        .service-item h3 {
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
        }
        .service-item p {
            font-size: 1rem;
            color: #555;
        }
        .info-section {
            text-align: left;
            margin-top: 2rem;
        }
        .info-section h2 {
            font-size: 1.8rem;
            margin-bottom: 1rem;
        }
        .info-section p {
            margin-bottom: 1rem;
            line-height: 1.6;
        }
        .footer {
            background-color: #1e88e5;
            color: #fff;
            padding: 2rem;
            text-align: center;
        }
        .footer-nav {
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            margin-top: 1rem;
        }
        .footer-nav ul {
            list-style: none;
            padding: 0;
        }
        .footer-nav ul li {
            margin-bottom: 0.5rem;
        }
        .footer-nav ul li a {
            text-decoration: none;
            color: #fff;
        }
        .footer-nav ul li a:hover {
            text-decoration: underline;
        }
        .footer-icons {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 1rem;
        }
        .footer-icons img {
            width: 40px;
            height: 40px;
        }
        .footer-content p {
            color: #fff;
        }
    </style>
</head>
<body>
    <header>
        <div class="navbar">
            <img src="images/logo botica.jpg" alt="Logo de la Botica San Juan" class="logo">
            <nav>
                <ul>
                    <li><a href="index.php">Inicio</a></li>
                    <li><a href="products/index.php">Productos</a></li>
                    <li><a href="services.php">Servicios</a></li>
                    <li><a href="about.php">Nosotros</a></li>
                    <li><a href="cart/index.php">Carrito de Compras</a></li>
                    <li><a href="contact.php">Contacto</a></li>
                </ul>
            </nav>
            <a href="buy.php" class="btn btn-buy">Comprar</a>
        </div>
    </header>

    <main>
        <section class="services-section">
            <h1>Nuestros Servicios</h1>
            <div class="services-container">
                <div class="service-item">
                    <img src="images/whatsapp-icon-lineal.svg" alt="WhatsApp">
                    <h3>WhatsApp</h3>
                    <p>Pide tus compras también por WhatsApp. Escríbenos al 946 552 311</p>
                </div>
                <div class="service-item">
                    <img src="images/aliviamed_icon.svg" alt="Aliviamed">
                    <h3>Aliviamed</h3>
                    <p>¡Atiéndete con un médico por teléfono desde dónde estés!</p>
                </div>
                <div class="service-item">
                    <img src="images/celular.svg" alt="SanJuanfono">
                    <h3>SanJuanfono</h3>
                    <p>Realiza tus pedidos llamando al (01) 677 2892</p>
                </div>
                <div class="service-item">
                    <img src="images/Icon_compaertir-es-ganar.png" alt="Comparte y gana">
                    <h3>Comparte y gana</h3>
                    <p>Comparte tu código y gana un cupón de S/ 20 para tu próxima compra.</p>
                </div>
            </div>
            <div class="info-section">
                <h2>Promociones únicas en Botica San Juan</h2>
                <p>Botica San Juan es la farmacia líder para compras por internet. En nuestro ecommerce ofrecemos una amplia variedad de productos a precios bajos para cuidar de ti y de toda tu familia. Conoce nuestras promociones únicas que cambian cada semana y disfruta de ellas los 365 días del año.</p>
                <p>Elige la talla y marca de pañal que necesitas para tu bebé, podemos llevarte el producto que necesitas a la puerta de tu casa gracias a nuestro servicio de Botica San Juan delivery. Escoge entre toallitas húmedas, shampoos, cremas para la cara y más. Es momento de lucir la piel que mereces gracias a las mejores marcas de dermocosmética: Vichy, La Roche Posay, Uriage, Bioderma y más. Encuentra aquí todas las sorpresas de la categoría de dermocosmética.</p>
                <p>Si quieres verte siempre bella, puedes encontrar mascarillas, fragancias, hasta maquillaje como: máscara de pestañas, bases, esmaltes, delineadores, tinte para cejas, lápiz labial y más.</p>
                <p>Si eres amante de los deportes, en Botica San Juan no nos quedamos atrás y te ofrecemos la sección de deportes con las mejores marcas. Encuentra lo que necesitas para equipar tu casa y poder entrenar como si estuvieras en un gimnasio: trotadoras, bancas de entrenamiento, máquinas de abdominales, máquinas de ejercicio multifunción, bicicletas estáticas y más. Además, si prefieres hacer ejercicio al aire libre, porque no animarte por una bicicleta BMX, montañera o urbana.</p>
                <h2>Delivery de Farmacia</h2>
                <p>Desde boticas Botica San Juan, contamos con un servicio de delivery de productos y medicamentos a domicilio al precio bajo, más bajo. Tenemos dos modalidades de envío: servicio express o servicio regular. <a href="zonas-cobertura.php" target="_blank">Descubre nuestras zonas de cobertura</a>.</p>
                <h2>Nuestro programa Agora</h2>
                <p>¿Qué es Agora? Agora es un programa respaldado por el grupo Intercorp que tiene 4 verticales: Agora Club, Agora Pay, Agora Shop y Agora Más.</p>
                <p><strong>Agora CLUB:</strong> Agora Club es el programa de lealtad que te devuelve un porcentaje de las compras (recompensas) que realices en Botica San Juan, Plaza Vea, Promart y Oeschle. Ser parte de Agora Club es muy fácil, simplemente debes descargar el App Agora y registrarte en Agora Club. Con Agora Club, te devolvemos un porcentaje de tu compra la cuál puedes acumular o utilizar en tus próximas compras.</p>
                <p><strong>Agora Shop:</strong> Es la app oficial de delivery de Botica San Juan, Vivanda, Makro, Plaza Vea, Promart y Oeschle. Haciendo las compras a través de la app, un Shopper realizará las compras por tí seleccionando los mejores productos y enviará tu pedido a la dirección solicitada.</p>
                <p><strong>Oh Pay:</strong> Oh Pay es una tarjeta de débito con una cuenta de ahorros digital respaldada por Financiera oh! Es ideal para hacer tus compras en establecimientos como Botica San Juan, Mass, Oeschle y más retailers. Para recargar tu tarjeta puedes hacerlo directamente desde la App o desde nuestra red de tiendas. Recuerda que ahora podrás recibir y enviar dinero a Yape y Plin.</p>
                <p><strong>Agora Ahorramas:</strong> Obtén una de las mejores tasas de intereses del mercado 6.50% TEA Soles y sin comisiones. Agora AhorraMás es una cuenta de ahorros segura y accesible 100% digital con una tasa de interés alta que te ayudará a ahorrar y crecer tu dinero. Es un producto de Financiera Oh! que opera en el marco regulatorio de la SBS.</p>
                <h2>Las novedades de Botica San Juan</h2>
                    <p>En Botica San Juan tenemos novedades para cuidar de tu salud y la de tu familia</p>
                    <p><strong>Marketplace Botica San Juan:</strong> ¿Necesitas un producto para cuidar a tu mascota, accesorios de belleza o algún juguete infantil? Con nuestro Marketplace Botica San Juan descubre un sinfín de productos que cumplan con tus necesidades. Además encuentra marcas exclusivas como The Ordinary, Martiderm, Baby Fees, EBaby, y más.</p>
                    <p><strong>Call Center:</strong> ¿Te resulta difícil encontrar tiempo para visitar una farmacia? ¿No te sientes cómodo comprando por internet? Nuestro servicio Inkafono es la solución que estabas esperando. Con Inkafono podrás hacer tus compras a través de nuestra central telefónica (01) 677 2892 las 24 horas del día. Además, ofrecemos una variedad de métodos de pago, incluyendo efectivo y tarjetas de crédito/débito, para que elijas el que más te convenga. Lo mejor de todo es que tendrás acceso a químicos farmacéuticos capacitados que estarán disponibles para resolver cualquier pregunta o inquietud que puedas tener.</p>
                    <p><strong>Aliviamed:</strong> Si no tienes tiempo para ir a una consulta médica o esta no cuadra con tus horarios, Botica San Juan tiene la solución perfecta: Aliviamed. Con este servicio de consultas médicas telefónicas, podrás ser atendido por un profesional de salud a un módico precio de S/.19. Ellos evaluarán tu condición y te proporcionarán la receta para evitar así la automedicación. Puedes adquirir tu consulta a través de nuestro call center o de forma presencial en boticas.</p>
                    <h2>Descubre nuestras categorías</h2>
                    <p>En Botica San Juan encuentra el producto que tú y tu familia necesita. Descubre todo nuestro catálogo en un solo lugar y lleno de promociones solo para ti. Precios más bajos con Tarjeta Oh y Agora.</p>
                    <p>¿Necesitas pañales para tu bebé, toallitas húmedas, productos de aseo infantil, nutrición como fórmulas o hasta accesorios para lactancia? En nuestro departamento de Mamá y bebé podrás sumergirte en un universo de productos especialmente seleccionados para brindar el máximo confort y bienestar a mamá y a su bebé. Descubre nuestras marcas: Ninet, Huggies, Enfagrow, Babysec, Babylac más.</p>
                    <p>Suplementos, complementos, vitaminas, colágenos y mucho más en nuestra sección de nutrición para todos. Descubre nuestras marcas Vitamins for Life, Pediasure, Ensure, Sunvit, Vitagel, Mason Natural y mucho más.</p>
                    <p>Si necesitas algún medicamento de venta libre encuéntralo en la sección de Farmacia en Botica San Juan, y, si tienes tu receta médica encuentra el producto que necesitas en nuestra sección de Salud. Además, encuentra dispositivos de ortopedia, lo que necesitas para complementar tu botiquín y más.</p>
                    <p>Cuidar la piel es importante tanto en invierno como en verano. En Botica San Juan encuentra productos de dermocosmética como tratamientos faciales, fotoprotectores, tratamientos corporales y mucho más. Conoce nuestras marcas: Eucerin, Uriage, Sebamed, Sesderma, y mucho más.</p>
                    <p>¿Quieres lucir bella siempre? Entonces visita nuestra sección de belleza para encontrar los productos que necesitabas: serums, cosméticos, productos de depilación, cremas hidratantes, bloqueadores y más.</p>
                    <p>Cuida tu aspecto personal gracias a los precios bajos en productos de cuidado personal que tiene Botica San Juan. Encuentra cepillos y pastas dentales, shampoos, acondicionadores, tratamientos para el cabello, productos para el afeitado, y más.</p>
                </div>
            </section>
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
