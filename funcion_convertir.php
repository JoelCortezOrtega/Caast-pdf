<?php
function convertirPDFparaVUCEM($inputPath, $outputPath) {
    // Escapamos las rutas para seguridad
    $inputEscaped = escapeshellarg($inputPath);
    $outputEscaped = escapeshellarg($outputPath);

    // Comando para convertir PDF a gris 8 bits, 300 DPI y calidad 80%
    // Usamos 'magick' que es compatible con ImageMagick 7
    $cmd = "magick convert -density 300 $inputEscaped -colorspace Gray -depth 8 -quality 80 $outputEscaped 2>&1";

    exec($cmd, $outputLines, $returnVar);

    if ($returnVar !== 0) {
        $errorMsg = implode("\n", $outputLines);
        return "❌ Error durante la conversión: " . $errorMsg;
    }

    $sizeMB = round(filesize($outputPath) / 1024 / 1024, 2);

    if ($sizeMB > 3) {
        return "⚠️ Archivo convertido, pero supera los 3 MB ($sizeMB MB).";
    }

    return "✅ PDF convertido correctamente a formato VUCEM ($sizeMB MB).";
}
?>

