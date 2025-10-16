<?php
$pdf = $_FILES['pdfFile']['tmp_name'];

// Asegúrate de escapar el nombre del archivo para evitar problemas de seguridad
$comando = 'pdfimages -list ' . escapeshellarg($pdf);

// Ejecutar el comando
$output = shell_exec($comando);

// Verificar si se obtuvo salida
if (!$output) {
    echo "❌ Error: No se pudo ejecutar pdfimages o el archivo no contiene imágenes.";
    exit;
}

// Analizar la salida
$lineas = explode("\n", $output);
$cumple = true;

// Buscar líneas con imágenes (saltar encabezados)
foreach ($lineas as $linea) {
    // Saltar líneas vacías y encabezados
    if (preg_match('/^\s*\d+\s+\d+\s+image.*?(\d+)\s+(\d+)\s*$/', $linea, $coincidencias)) {
        $x_dpi = (int)$coincidencias[1];
        $y_dpi = (int)$coincidencias[2];

        if ($x_dpi < 300 || $y_dpi < 300) {
            $cumple = false;
            break;
        }
    }
}

// Mostrar resultado final
if ($cumple) {
    echo "✅ Cumple con 300 DPI";
} else {
    echo "❌ Rechazado: hay imágenes con menos de 300 DPI";
}
?>