<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    outputAndExit(["error" => ["⚠️ Método no permitido. Usa POST para subir archivos."]]);
}

if (!isset($_FILES['pdfFiles']) || !is_array($_FILES['pdfFiles']['error']) || count($_FILES['pdfFiles']['error']) === 0) {
    $maxUploadPHP = ini_get('max_file_uploads');
    outputAndExit([
        "error" => [
            "❌ No se subieron archivos o se excedió el límite del servidor.",
            "💡 Revisa que no estés enviando más de {$maxUploadPHP} archivos, que es el máximo permitido por el servidor PHP."
        ]
    ]);
}

$results = [];

foreach ($_FILES['pdfFiles']['tmp_name'] as $index => $uploadedFile) {
    $messages = [];

    // ✅ Sanitizar nombre del archivo
    $originalName = basename($_FILES['pdfFiles']['name'][$index]);
    $originalName = preg_replace('/[^a-zA-Z0-9_\-.]/', '_', $originalName);

    // Verificar errores de subida
    if ($_FILES['pdfFiles']['error'][$index] !== UPLOAD_ERR_OK) {
        $messages[] = "❌ No se pudo subir el archivo: {$originalName}. Error: {$_FILES['pdfFiles']['error'][$index]}";
        $results[$originalName] = $messages;
        continue;
    }

    // Verificar tamaño (máx. 3MB)
    $maxSize = 3 * 1024 * 1024;
    if ($_FILES['pdfFiles']['size'][$index] > $maxSize) {
        $messages[] = "❌ El archivo {$originalName} excede el tamaño máximo permitido de 3 MB.";
        $results[$originalName] = $messages;
        continue;
    } else {
        $messages[] = "✅ Tamaño del archivo adecuado.";
    }

    $messages[] = round(($_FILES['pdfFiles']['size'][$index] / 1024), 2) . " KB";

    // Verificar tipo MIME real
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $uploadedFile);
    finfo_close($finfo);

    if (!in_array($mime, ['application/pdf', 'application/x-pdf'])) {
        $messages[] = "❌ El archivo no es un PDF válido. Tipo detectado: {$mime}";
        $results[$originalName] = $messages;
        continue;
    } else {
        $messages[] = "✅ El archivo es un PDF válido.";
    }

    // Usar rutas seguras
    $escapedPath = escapeshellarg($uploadedFile);

    // Comprobar si está protegido con contraseña
    $pdfinfo = shell_exec("pdfinfo $escapedPath");
    if (strpos($pdfinfo, 'Encrypted: yes') !== false) {
        $messages[] = "❌ El PDF está protegido con contraseña.";
        $results[$originalName] = $messages;
        continue;
    } else {
        $messages[] = "✅ El PDF no tiene contraseña.";
    }

    // Obtener trailer para validar AcroForm, JS, incrustaciones
    $trailer = shell_exec("mutool show $escapedPath trailer");

    $messages[] = (strpos($trailer, '/AcroForm') !== false)
        ? "❌ Contiene formularios (AcroForm)."
        : "✅ No contiene formularios.";

    $messages[] = (strpos($trailer, '/EmbeddedFiles') !== false || strpos($trailer, '/FileAttachment') !== false)
        ? "❌ Contiene archivos incrustados."
        : "✅ No contiene objetos incrustados.";

    $messages[] = (preg_match('/\/(JavaScript|JS)/', $trailer))
        ? "❌ Contiene JavaScript."
        : "✅ No contiene JavaScript.";

    // Verificar imágenes
    $pdfimages = shell_exec("pdfimages -list $escapedPath");
    if (!$pdfimages) {
        $messages[] = "❌ No se pudo analizar imágenes del PDF.";
        $results[$originalName] = $messages;
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
                $messages[] = "❌ Imagen no compatible: Color: $color | Bits por componente: $bpc";
            }
        }
    }

    if ($totalImages === 0) {
        $messages[] = "⚠️ No se encontraron imágenes en el PDF.";
    } else {
        $messages[] = $validDPI
            ? "✅ Todas las imágenes cumplen con 300 DPI o más."
            : "❌ Hay imágenes con menos de 300 DPI.";

        $messages[] = ($validGray8 === $totalImages)
            ? "✅ Todas las imágenes están en escala de grises a 8 bits ($validGray8 de $totalImages)."
            : "❌ Solo $validGray8 de $totalImages imágenes están en escala de grises a 8 bits.";
    }

    // Guardar resultados por archivo
    $results[$originalName] = [
        'resumen' => $messages,
        'detalles' => [
            'tamaño' => round($_FILES['pdfFiles']['size'][$index] / 1024, 2) . " KB",
            'tamaño_valido' => ($_FILES['pdfFiles']['size'][$index] <= $maxSize) ? "✅ Tamaño adecuado." : "❌ Excede el tamaño máximo.",
            'pdf_valido' => (in_array($mime, ['application/pdf', 'application/x-pdf'])) ? "✅ Es un PDF válido." : "❌ No es un PDF válido.",
            'sin_contraseña' => (strpos($pdfinfo, 'Encrypted: yes') === false) ? "✅ No tiene contraseña." : "❌ Tiene contraseña.",
            'sin_formularios' => (strpos($trailer, '/AcroForm') === false) ? "✅ No contiene formularios." : "❌ Contiene formularios.",
            'sin_objetos_incrustados' => (strpos($trailer, '/EmbeddedFiles') === false && strpos($trailer, '/FileAttachment') === false) ? "✅ No contiene objetos incrustados." : "❌ Contiene objetos incrustados.",
            'sin_javascript' => (preg_match('/\/(JavaScript|JS)/', $trailer) === 0) ? "✅ No contiene JavaScript." : "❌ Contiene JavaScript.",
            'imagenes' => $totalImages > 0 ? "✅ Se encontraron imágenes en el PDF." : "⚠️ No se encontraron imágenes.",
            'dpi_imagenes' => ($totalImages === 0) ? "⚠️ No aplica." : ($validDPI ? "✅ Todas las imágenes cumplen con 300 DPI o más." : "❌ Algunas imágenes tienen menos de 300 DPI."),
            'imagenes_grayscale' => ($totalImages === 0) ? "⚠️ No aplica." : ($validGray8 === $totalImages ? "✅ Todas las imágenes están en escala de grises a 8 bits." : "❌ Solo $validGray8 de $totalImages imágenes están en escala de grises a 8 bits.")
        ]
    ];
}

// ✅ Mostrar los resultados como JSON
outputAndExit($results);

// -------------------
// Función auxiliar
function outputAndExit(array $messages) {
    header('Content-Type: application/json');
    echo json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}
?>