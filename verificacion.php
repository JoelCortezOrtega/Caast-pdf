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

    // Validar nomenclatura de nombre VUCEM (RFC_Tipo_Fecha.pdf)
    if (!preg_match('/^[A-Z0-9]{12,13}_[A-Za-z]+_\d{8}\.pdf$/', $originalName)) {
        $messages[] = "⚠️ El nombre del archivo no cumple con la nomenclatura esperada (RFC_Tipo_Fecha.pdf).";
    } else {
        $messages[] = "✅ Nombre de archivo con nomenclatura válida.";
    }

    // Verificar errores de subida
    if ($_FILES['pdfFiles']['error'][$index] !== UPLOAD_ERR_OK) {
        $messages[] = "❌ No se pudo subir el archivo: {$originalName}. Error: {$_FILES['pdfFiles']['error'][$index]}";
        $results[$originalName] = ['resumen' => $messages];
        continue;
    }

    // Verificar tamaño (máx. 3MB)
    $maxSize = 3 * 1024 * 1024;
    if ($_FILES['pdfFiles']['size'][$index] > $maxSize) {
        $messages[] = "❌ El archivo {$originalName} excede el tamaño máximo permitido de 3 MB.";
        $results[$originalName] = ['resumen' => $messages];
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
        $results[$originalName] = ['resumen' => $messages];
        continue;
    } else {
        $messages[] = "✅ El archivo es un PDF válido.";
    }

    $escapedPath = escapeshellarg($uploadedFile);

    // Comprobar si está protegido con contraseña
    $pdfinfo = shell_exec("pdfinfo $escapedPath 2>&1");
    if (strpos($pdfinfo, 'Encrypted: yes') !== false) {
        $messages[] = "❌ El PDF está protegido con contraseña.";
        $results[$originalName] = ['resumen' => $messages];
        continue;
    } else {
        $messages[] = "✅ El PDF no tiene contraseña.";
    }

    // Verificar versión del PDF
    if (preg_match('/PDF version:\s*([0-9.]+)/', $pdfinfo, $m)) {
        $version = floatval($m[1]);
        if ($version < 1.4) {
            $messages[] = "⚠️ Versión PDF antigua ($version). Se recomienda 1.4 o superior.";
        } else {
            $messages[] = "✅ Versión PDF compatible ($version).";
        }
    }

    // Obtener trailer para validar AcroForm, JS, incrustaciones
    $trailer = shell_exec("mutool show $escapedPath trailer");

    $containsFormularios = (strpos($trailer, '/AcroForm') !== false);
    $messages[] = $containsFormularios ? "❌ Contiene formularios (AcroForm)." : "✅ No contiene formularios.";

    $containsObjetosIncrustados = (strpos($trailer, '/EmbeddedFiles') !== false || strpos($trailer, '/FileAttachment') !== false);
    $messages[] = $containsObjetosIncrustados ? "❌ Contiene archivos incrustados." : "✅ No contiene objetos incrustados.";

    $containsJS = (preg_match('/\/(JavaScript|JS)/', $trailer));
    $messages[] = $containsJS ? "❌ Contiene JavaScript." : "✅ No contiene JavaScript.";

    // Verificar anotaciones o comentarios
    $hasAnnots = shell_exec("mutool show $escapedPath 1 2>&1 | grep '/Annots'");
    $messages[] = $hasAnnots ? "⚠️ El PDF contiene anotaciones o comentarios." : "✅ No contiene anotaciones.";

    // Verificar enlaces externos
    $links = shell_exec("strings $escapedPath | grep -E 'https?://'");
    $messages[] = $links ? "⚠️ El PDF contiene enlaces externos (URLs)." : "✅ No contiene enlaces externos.";

    // Verificar metadatos ocultos
    $metadata = shell_exec("exiftool -s -s -s $escapedPath");
    $messages[] = (strlen(trim($metadata)) > 0) ? "⚠️ El PDF contiene metadatos incrustados." : "✅ No contiene metadatos visibles.";

    // Verificar OCR o texto oculto bajo imagen
    $textContent = shell_exec("pdftotext $escapedPath - | tr -d '\\n\\r '");
    $messages[] = (strlen($textContent) > 50) ? "⚠️ El PDF contiene texto embebido (posible OCR o capa de texto)." : "✅ El PDF parece ser imagen pura (sin texto embebido).";

    // Verificar páginas en blanco
    $pageImages = shell_exec("pdftoppm -jpeg -f 1 -l 3 $escapedPath /tmp/page_check 2>/dev/null && identify -format '%[fx:mean]\\n' /tmp/page_check*.jpg");
    $blankPages = 0;
    if ($pageImages) {
        $means = array_filter(explode("\n", trim($pageImages)), 'strlen');
        foreach ($means as $m) {
            if ((float)$m > 0.95) $blankPages++;
        }
        $messages[] = $blankPages > 0 ? "⚠️ Se detectaron {$blankPages} páginas en blanco." : "✅ No se detectaron páginas en blanco.";
        shell_exec("rm -f /tmp/page_check*.jpg");
    }

    // Verificar tamaño y orientación de página
    if (preg_match('/Page size:\s*([\d.]+)\s*x\s*([\d.]+)/', $pdfinfo, $s)) {
        $w = floatval($s[1]);
        $h = floatval($s[2]);
        if (abs($w - 595) < 5 && abs($h - 842) < 5) {
            $messages[] = "✅ Tamaño de página estándar A4.";
        } elseif (abs($w - 612) < 5 && abs($h - 792) < 5) {
            $messages[] = "✅ Tamaño de página estándar Carta.";
        } else {
            $messages[] = "⚠️ Tamaño de página no estándar: {$w}x{$h} pt.";
        }
        $orientation = ($w > $h) ? "Horizontal" : "Vertical";
        $messages[] = "📄 Orientación detectada: {$orientation}.";
    }

    // Verificar imágenes
    $pdfimages = shell_exec("pdfimages -list $escapedPath");
    $totalImages = 0;
    $validDPI = true;
    $validGray8 = 0;

    if (!$pdfimages) {
        $messages[] = "❌ No se pudo analizar imágenes del PDF.";
        $results[$originalName] = ['resumen' => $messages];
        continue;
    }

    $lines = explode("\n", $pdfimages);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, 'page') === 0 || strpos($line, '-----') === 0) continue;

        $parts = preg_split('/\s+/', $line);
        if (count($parts) < 13) continue;

        $color = strtolower($parts[5]);
        $bpc = (int)$parts[7];
        $x_dpi = (int)$parts[11];
        $y_dpi = (int)$parts[12];

        $totalImages++;
        if ($x_dpi < 300 || $y_dpi < 300) $validDPI = false;
        if ($color === 'gray' && $bpc === 8) $validGray8++;
    }

    // Guardar resultados por archivo
    $results[$originalName] = [
        'resumen' => $messages,
        'detalles' => [
            'tamaño' => round($_FILES['pdfFiles']['size'][$index] / 1024, 2) . " KB",
            'tamaño_valido' => ($_FILES['pdfFiles']['size'][$index] <= $maxSize) ? "✅ Tamaño adecuado." : "❌ Excede el tamaño máximo.",
            'pdf_valido' => (in_array($mime, ['application/pdf', 'application/x-pdf'])) ? "✅ Es un PDF válido." : "❌ No es un PDF válido.",
            'sin_contraseña' => (strpos($pdfinfo, 'Encrypted: yes') === false) ? "✅ No tiene contraseña." : "❌ Tiene contraseña.",
            'sin_formularios' => $containsFormularios ? "❌ Contiene formularios." : "✅ No contiene formularios.",
            'sin_objetos_incrustados' => $containsObjetosIncrustados ? "❌ Contiene objetos incrustados." : "✅ No contiene objetos incrustados.",
            'sin_javascript' => $containsJS ? "❌ Contiene JavaScript." : "✅ No contiene JavaScript.",
            'imagenes' => $totalImages > 0 ? "✅ Se encontraron imágenes en el PDF." : "⚠️ No se encontraron imágenes.",
            'dpi_imagenes' => ($totalImages === 0) ? "⚠️ No aplica." : ($validDPI ? "✅ Todas las imágenes cumplen con 300 DPI o más." : "❌ Algunas imágenes tienen menos de 300 DPI."),
            'imagenes_grayscale' => ($totalImages === 0) ? "⚠️ No aplica." : ($validGray8 === $totalImages ? "✅ Todas las imágenes están en escala de grises a 8 bits." : "❌ Solo $validGray8 de $totalImages imágenes están en escala de grises a 8 bits.")
        ]
    ];
}

outputAndExit($results);

function outputAndExit(array $messages) {
    header('Content-Type: application/json');
    echo json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}
?>


