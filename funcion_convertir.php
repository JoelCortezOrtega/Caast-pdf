<?php
/* function convertirPDFparaVUCEM($inputPath, $outputPath) {
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
        return "✅ Archivo convertido, pero supera los 3 MB ($sizeMB MB).";
    }

    return "✅ PDF convertido correctamente a formato VUCEM ($sizeMB MB).";
} */
/* 

function convertirPDFparaVUCEM($inputPath, $outputPath) {
    $inputEscaped = escapeshellarg($inputPath);
    $outputEscaped = escapeshellarg($outputPath);

    // 🔧 Aseguramos que Ghostscript esté disponible para PHP
    putenv('PATH=' . getenv('PATH') . ';C:\Program Files\gs\gs10.06.0\bin');

    // 🔹 Ruta completa al ejecutable Ghostscript
    $ghostscriptPath = 'C:\\Program Files\\gs\\gs10.06.0\\bin\\gswin64c.exe';

    // 🔹 Armamos el comando completo (con comillas dobles para rutas con espacios)
    $cmd = "\"$ghostscriptPath\" -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 "
         . "-dNOPAUSE -dQUIET -dBATCH "
         . "-sColorConversionStrategy=Gray -dProcessColorModel=/DeviceGray "
         . "-dDetectDuplicateImages=true -dDownsampleColorImages=true "
         . "-dDownsampleGrayImages=true -dDownsampleMonoImages=true "
         . "-dColorImageResolution=150 -dGrayImageResolution=150 -dMonoImageResolution=150 "
         . "-dPDFSETTINGS=/ebook "
         . "-sOutputFile=$outputEscaped $inputEscaped 2>&1";

    // 🔹 Ejecutamos el comando
    exec($cmd, $outputLines, $returnVar);

    // 🔹 Validación y manejo de errores
    if ($returnVar !== 0) {
        $errorMsg = implode("\n", $outputLines);
        return "❌ Error durante la conversión con Ghostscript:\n" . $errorMsg . "\nComando ejecutado:\n$cmd";
    }

    // 🔹 Calculamos el tamaño final del archivo
    $sizeMB = round(filesize($outputPath) / 1024 / 1024, 2);
    if ($sizeMB > 3) {
        return "✅ Archivo convertido, pero supera los 3 MB ($sizeMB MB).";
    }

    return "✅ PDF convertido correctamente a formato VUCEM ($sizeMB MB).";
} 
    

*/
/*
function convertirPDFparaVUCEM($inputPath, $outputPath) {
    $inputEscaped = escapeshellarg($inputPath);
    $outputEscaped = escapeshellarg($outputPath);

    // 🔧 Asegurar ruta de Ghostscript
    putenv('PATH=' . getenv('PATH') . ';C:\Program Files\gs\gs10.06.0\bin');
    $ghostscriptPath = 'C:\\Program Files\\gs\\gs10.06.0\\bin\\gswin64c.exe';

    // 🧠 Usamos 'pdfimage8' para forzar rasterización a escala de grises 8 bits
    // con resolución controlada y compresión eficiente
    $cmd = "\"$ghostscriptPath\" -sDEVICE=pdfwrite "
        . "-dCompatibilityLevel=1.4 "
        . "-dNOPAUSE -dQUIET -dBATCH "
        . "-sColorConversionStrategy=Gray -dProcessColorModel=/DeviceGray "
        . "-dDownsampleGrayImages=true -dGrayImageResolution=300 "
        . "-dAutoRotatePages=/None "
        . "-dCompressFonts=true "
        . "-dPDFSETTINGS=/prepress "
        . "-sOutputFile=$outputEscaped "
        . "$inputEscaped 2>&1";

    exec($cmd, $outputLines, $returnVar);

    if ($returnVar !== 0) {
        $errorMsg = implode("\n", $outputLines);
        return "❌ Error durante la conversión rasterizada:\n$errorMsg\nComando ejecutado:\n$cmd";
    }

    $sizeMB = round(filesize($outputPath) / 1024 / 1024, 2);

    if ($sizeMB > 3) {
        return "✅ Archivo convertido, pero supera los 3 MB ($sizeMB MB).";
    }

    return "✅ PDF convertido correctamente con imágenes detectables ($sizeMB MB).";
}
    */

function convertirPDFparaVUCEM($inputPath, $outputPath) {
    $inputEscaped = escapeshellarg($inputPath);
    $outputEscaped = escapeshellarg($outputPath);

    // 🔧 Asegurar ruta de Ghostscript
    putenv('PATH=' . getenv('PATH') . ';C:\Program Files\gs\gs10.06.0\bin');
    $ghostscriptPath = 'C:\\Program Files\\gs\\gs10.06.0\\bin\\gswin64c.exe';

    // 🔹 Detectar imágenes en el PDF usando pdfimages
    $pdfimagesPath = 'pdfimages'; // debe estar en PATH
    $cmdDetect = "$pdfimagesPath -list $inputEscaped 2>&1";
    exec($cmdDetect, $outputDetect, $returnDetect);

    $tieneImagenes = false;

    if ($returnDetect === 0) {
        foreach ($outputDetect as $linea) {
            if (preg_match('/\d+\s+\d+\s+\d+\s+\d+\s+\d+\s+\w+/', $linea)) {
                $tieneImagenes = true;
                break;
            }
        }
    }

    // 🔹 Elegir modo según si tiene imágenes
    if ($tieneImagenes) {
        $modo = 'vectorial';
        $cmd = "\"$ghostscriptPath\" -sDEVICE=pdfwrite "
            . "-dCompatibilityLevel=1.4 "
            . "-dNOPAUSE -dQUIET -dBATCH "
            . "-sColorConversionStrategy=Gray -dProcessColorModel=/DeviceGray "
            . "-dDownsampleGrayImages=true -dGrayImageResolution=300 "
            . "-dAutoRotatePages=/None "
            . "-dCompressFonts=true "
            . "-dPDFSETTINGS=/prepress "
            . "-sOutputFile=$outputEscaped "
            . "$inputEscaped 2>&1";
    } else {
        $modo = 'raster';
        $cmd = "\"$ghostscriptPath\" -sDEVICE=pdfimage8 "
            . "-r300 "
            . "-dBATCH -dNOPAUSE -dQUIET "
            . "-sColorConversionStrategy=Gray -dProcessColorModel=/DeviceGray "
            . "-sOutputFile=$outputEscaped "
            . "$inputEscaped 2>&1";
    }

    exec($cmd, $outputLines, $returnVar);

    if ($returnVar !== 0) {
        $errorMsg = implode("\n", $outputLines);
        return "❌ Error durante la conversión ($modo):\n$errorMsg\nComando ejecutado:\n$cmd";
    }

    $sizeMB = round(filesize($outputPath) / 1024 / 1024, 2);

    if ($sizeMB > 3) {
        return "✅ Archivo convertido ($modo), pero supera los 3 MB ($sizeMB MB).";
    }

    return "✅ PDF convertido correctamente ($modo) con imágenes detectables ($sizeMB MB).";
}


?>

