<?php
$servername = getenv('BOTICA_DB_HOST') ?: 'localhost';
$username = getenv('BOTICA_DB_USER') ?: 'root';
$password = getenv('BOTICA_DB_PASSWORD') ?: '';
$dbname = getenv('BOTICA_DB_NAME') ?: 'botica_san_juan';

// Crea conexión
$conn = new mysqli($servername, $username, $password, $dbname);

// Verifica conexión
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}
echo "Conexión exitosa";
?>
