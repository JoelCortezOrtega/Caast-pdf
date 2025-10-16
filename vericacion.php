<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit("⚠️ Método no permitido. Usa POST para subir archivos.\n");
}

if (!isset($_FILES['pdfFiles']) || !is_array($_FILES['pdfFiles']['error']) || count($_FILES['pdfFiles']['error']) === 0) {
    exit("⚠️ No se subieron archivos o hubo un error al subirlos.\n");
}

$results = [];

foreach ($_FILES['pdfFiles']['tmp_name'] as $index => $uploadedFile) {
    $originalName = $_FILES['pdfFiles']['name'][$index];

    // Verificar errores de subida
    if ($_FILES['pdfFiles']['error'][$index] !== UPLOAD_ERR_OK) {
        $results[] = "❌ No se pudo subir el archivo: {$originalName}. Error: {$_FILES['pdfFiles']['error'][$index]}";
        continue;
    }

    // Verificar tamaño (máx. 3MB)
    $maxSize = 3 * 1024 * 1024;
    if (filesize($uploadedFile) > $maxSize) {
        $results[] = "❌ El archivo {$originalName} excede el tamaño máximo permitido de 3 MB.";
        continue;
    } else {
        $results[] = "✅ Tamaño del archivo {$originalName} adecuado.";
    }

    // Verificar tipo MIME
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $uploadedFile);
    finfo_close($finfo);

    if (!in_array($mime, ['application/pdf', 'application/x-pdf'])) {
        $results[] = "❌ El archivo {$originalName} no es un PDF válido. Tipo detectado: {$mime}";
        continue;
    } else {
        $results[] = "✅ El archivo {$originalName} es un PDF válido.";
    }

    // Usar rutas seguras
    $escapedPath = escapeshellarg($uploadedFile);

    // Comprobar si está protegido con contraseña
    $pdfinfo = shell_exec("pdfinfo $escapedPath");
    if (strpos($pdfinfo, 'Encrypted: yes') !== false) {
        $results[] = "❌ El PDF {$originalName} está protegido con contraseña.";
        continue;
    } else {
        $results[] = "✅ El PDF {$originalName} no tiene contraseña.";
    }

    // Obtener trailer para validar AcroForm, JS, incrustaciones
    $trailer = shell_exec("mutool show $escapedPath trailer");

    $results[] = (strpos($trailer, '/AcroForm') !== false)
        ? "❌ El PDF {$originalName} contiene formularios (AcroForm)."
        : "✅ El PDF {$originalName} no contiene formularios.";

    $results[] = (strpos($trailer, '/EmbeddedFiles') !== false || strpos($trailer, '/FileAttachment') !== false)
        ? "❌ El PDF {$originalName} contiene archivos incrustados."
        : "✅ El PDF {$originalName} no contiene objetos incrustados.";

    $results[] = (preg_match('/\/(JavaScript|JS)/', $trailer))
        ? "❌ El PDF {$originalName} contiene JavaScript."
        : "✅ El PDF {$originalName} no contiene JavaScript.";

    // Verificar imágenes: resolución y escala de grises
    $pdfimages = shell_exec("pdfimages -list $escapedPath");
    if (!$pdfimages) {
        $results[] = "❌ No se pudo analizar imágenes del PDF {$originalName}.";
        continue;
    }

    $lines = explode("\n", $pdfimages);
    $totalImages = 0;
    $validDPI = true;
    $validGray8 = 0;

    foreach ($lines as $line) {
        if (preg_match('/^\s*\d+\s+\d+\s+image\s+\d+\s+\d+\s+(\w+)\s+(\d+)\s+(\d+)\s+(\d+)/', $line, $match)) {
            $color = strtolower($match[1]);
            $bpc = (int)$match[2];
            $x_dpi = (int)$match[3];
            $y_dpi = (int)$match[4];
            $totalImages++;

            if ($x_dpi < 300 || $y_dpi < 300) {
                $validDPI = false;
            }

            if ($color === 'gray' && $bpc === 8) {
                $validGray8++;
            } else {
                $results[] = "❌ Imagen no compatible en {$originalName}: Color: $color | Bits por componente: $bpc";
            }
        }
    }

    if ($totalImages === 0) {
        $results[] = "⚠️ No se encontraron imágenes en el PDF {$originalName}.";
    } else {
        $results[] = $validDPI
            ? "✅ Todas las imágenes del PDF {$originalName} cumplen con 300 DPI o más."
            : "❌ Rechazado: hay imágenes con menos de 300 DPI en {$originalName}.";

        $results[] = ($validGray8 === $totalImages)
            ? "✅ Todas las imágenes del PDF {$originalName} están en escala de grises a 8 bits ($validGray8 de $totalImages)."
            : "❌ Solo $validGray8 de $totalImages imágenes en {$originalName} están en escala de grises a 8 bits.";
    }
}

// Mostrar los resultados
outputAndExit($results);

// -------------------
// Función auxiliar
function outputAndExit(array $messages) {
    foreach ($messages as $msg) {
        echo $msg . "\n";
    }
    exit;
}
?>
