<?php
$content = file_get_contents('botica_san_juan.sql');
$lines = explode("\n", $content);
foreach ($lines as $line) {
    if (strpos($line, 'INSERT INTO `productos`') === 0) {
        $insertLine = $line;
        break;
    }
}
$values = str_replace('INSERT INTO `productos` VALUES ', '', $insertLine);
$values = str_replace(';', '', $values);
// Now $values is (1,'A',...),(2,...),...
// Use regex to match each record
preg_match_all('/\(([^)]+)\)/', $values, $matches);
$phpArray = [];
foreach ($matches[1] as $record) {
    // Now $record is 1,'A FOLIC','0.5 mg',...
    // Split by comma, but handle quoted strings
    $fields = str_getcsv($record, ',', "'");
    $id = $fields[0];
    $nombre = $fields[1];
    $concentracion = $fields[2];
    $presentacion = $fields[3];
    $laboratorio = $fields[4];
    $tipo = $fields[5];
    $categoria = $fields[6];
    $stock = $fields[7];
    $precio = $fields[8];
    $imagen = $fields[9];
    $phpArray[] = "            ['id' => $id, 'nombre' => '$nombre', 'concentracion' => '$concentracion', 'presentacion' => '$presentacion', 'laboratorio' => '$laboratorio', 'tipo' => '$tipo', 'categoria' => '$categoria', 'stock' => $stock, 'precio' => $precio, 'imagen' => '$imagen'],";
}
file_put_contents('productos_array.txt', implode("\n", $phpArray));
echo "Done\n";
?>