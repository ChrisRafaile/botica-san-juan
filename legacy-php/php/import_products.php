<?php
require '../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$servername = getenv('BOTICA_DB_HOST') ?: 'localhost';
$username = getenv('BOTICA_DB_USER') ?: 'root';
$password = getenv('BOTICA_DB_PASSWORD') ?: '';
$dbname = getenv('BOTICA_DB_NAME') ?: 'botica_san_juan';

// Crea la conexión
$conn = new mysqli($servername, $username, $password, $dbname);

// Verifica la conexión
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Carga el archivo Excel
$inputFileName = '../reporte_productos.xlsx';
$spreadsheet = IOFactory::load($inputFileName);

// Obtener la primera hoja
$sheet = $spreadsheet->getSheet(0);

// Obtener el número máximo de filas
$highestRow = $sheet->getHighestRow(); 

// Recorre todas las filas
for ($row = 2; $row <= $highestRow; $row++) {
    $nombre = $sheet->getCell('B' . $row)->getValue();
    $concentracion = $sheet->getCell('C' . $row)->getValue();
    $adicional = $sheet->getCell('D' . $row)->getValue();
    $laboratorio = $sheet->getCell('E' . $row)->getValue();
    $presentacion = $sheet->getCell('F' . $row)->getValue();
    $tipo = $sheet->getCell('G' . $row)->getValue();
    $stock = (int)$sheet->getCell('H' . $row)->getValue();
    $precio = (float)$sheet->getCell('I' . $row)->getValue();

    if (empty($nombre) || empty($concentracion) || empty($laboratorio) || empty($presentacion) || empty($tipo)) {
        echo "Datos incompletos o inválidos en la fila: " . $row . "<br>";
        continue;
    }

    // Preparar y ejecutar la sentencia SQL
    $stmt = $conn->prepare("INSERT INTO productos (nombre, concentracion, adicional, laboratorio, presentacion, tipo, stock, precio, imagen) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $imagen = 'images/default_image.png';
    $stmt->bind_param("ssssssids", $nombre, $concentracion, $adicional, $laboratorio, $presentacion, $tipo, $stock, $precio, $imagen);

    if ($stmt->execute()) {
        echo "Fila $row importada correctamente.<br>";
    } else {
        echo "Error al importar la fila $row: " . $stmt->error . "<br>";
    }

    $stmt->close();
}

$conn->close();
?>
