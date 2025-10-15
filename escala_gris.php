<?php

$pdfPath = $_FILES['pdfFile']['tmp_name'];

// Ejecuta pdfimages -list
$cmd = "pdfimages -list " . escapeshellarg($pdfPath);
$output = shell_exec($cmd);

// Si no hay salida, algo falló
if (!$output) {
    echo "Error al ejecutar pdfimages o el archivo no contiene imágenes.\n";
    exit;
}

// Procesar la salida línea por línea
$lines = explode("\n", $output);
$matches = 0;
$total = 0;

foreach ($lines as $line) {
    // Saltar líneas vacías o encabezados
    if (preg_match('/^\s*\d+\s+\d+\s+image\s+\d+\s+\d+\s+(\w+)\s+\d+\s+(\d+)/', $line, $matchesLine)) {
        $total++;
        $color = strtolower($matchesLine[1]);
        $bpc = intval($matchesLine[2]);

        if ($color === 'gray' && $bpc === 8) {
            $matches++;
        } else {
            echo "❌ Imagen no compatible: Color: $color | Bits por componente: $bpc\n";
        }
    }
}

if ($total === 0) {
    echo "⚠️ No se encontraron imágenes en el PDF.\n";
} elseif ($matches === $total) {
    echo "✅ Todas las imágenes están en escala de grises a 8 bits ($matches de $total).\n";
} else {
    echo "❌ Solo $matches de $total imágenes están en escala de grises a 8 bits.\n";
}

?>
