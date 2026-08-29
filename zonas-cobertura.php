<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Zonas de Cobertura - Botica San Juan</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .coverage-section {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1rem;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        .coverage-header {
            margin-bottom: 2rem;
        }
        .coverage-header h1 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        .coverage-form {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
        }
        .coverage-map {
            margin-top: 2rem;
        }
        .coverage-map img {
            width: 100%;
            max-width: 600px;
            height: auto;
            border-radius: 10px;
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
                    <li><a href="products.php">Productos</a></li>
                    <li><a href="services.php">Servicios</a></li>
                    <li><a href="about.php">Nosotros</a></li>
                    <li><a href="cart.php">Carrito de Compras</a></li>
                    <li><a href="contact.php">Contacto</a></li>
                </ul>
            </nav>
            <a href="buy.php" class="btn btn-buy">BUY</a>
        </div>
    </header>
    
    <main>
        <section class="coverage-section">
            <div class="coverage-header">
                <h1>Nuestras Zonas de Cobertura</h1>
                <p>Selecciona en qué distrito te encuentras y te mostraremos si estás dentro de nuestra zona de cobertura.</p>
            </div>
            <div class="coverage-form">
                <select id="departamentos" onchange="updateProvincias()">
                    <option value="">Departamentos</option>
                    <option value="lima">Lima</option>
                </select>
                <select id="provincias" onchange="updateDistritos()">
                    <option value="">Provincias</option>
                </select>
                <select id="distritos" onchange="updateMap()">
                    <option value="">Distritos</option>
                </select>
            </div>
            <div class="coverage-map">
                <img id="map" src="images/pais.jpg" alt="Mapa de Cobertura">
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
    
    <script>
        function updateProvincias() {
            const provincias = document.getElementById('provincias');
            const departamentos = document.getElementById('departamentos').value;
            
            provincias.innerHTML = '<option value="">Provincias</option>';
            
            if (departamentos === 'lima') {
                provincias.innerHTML += '<option value="limametropolitana">Lima Metropolitana y Callao</option>';
            }
        }
        
        function updateDistritos() {
            const distritos = document.getElementById('distritos');
            const provincias = document.getElementById('provincias').value;
            
            distritos.innerHTML = '<option value="">Distritos</option>';
            
            if (provincias === 'limametropolitana') {
                distritos.innerHTML += '<option value="sjl">San Juan de Lurigancho</option>';
                distritos.innerHTML += '<option value="surco">Surco</option>';
                distritos.innerHTML += '<option value="sanborja">San Borja</option>';
                distritos.innerHTML += '<option value="cercado">Cercado</option>';
                distritos.innerHTML += '<option value="callao">Callao</option>';
            }
        }
        
        function updateMap() {
            const map = document.getElementById('map');
            const distritos = document.getElementById('distritos').value;
            
            if (distritos === 'sjl') {
                map.src = 'images/SJL.jpg';
                map.alt = 'San Juan de Lurigancho';
            } else if (distritos === 'surco') {
                map.src = 'images/SURCO.jpg';
                map.alt = 'Surco';
            } else if (distritos === 'sanborja') {
                map.src = 'images/SANBORJA.jpg';
                map.alt = 'San Borja';
            } else if (distritos === 'cercado') {
                map.src = 'images/CERCADO.jpg';
                map.alt = 'Cercado';
            } else if (distritos === 'callao') {
                map.src = 'images/CALLAO.jpg';
                map.alt = 'Callao';
            } else {
                map.src = 'images/pais.jpg';
                map.alt = 'Mapa de Cobertura';
            }
        }
    </script>
</body>
</html>
