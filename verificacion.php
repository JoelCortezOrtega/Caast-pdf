<?php
ini_set('upload_max_filesize', '100M');
ini_set('post_max_size', '120M');
ini_set('max_execution_time', '300');
ini_set('max_input_time', '300');
ini_set('memory_limit', '512M');

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

// Contraseña opcional para PDFs protegidos
$providedPassword = isset($_POST['pdfPassword']) ? $_POST['pdfPassword'] : '';

$results = [];

foreach ($_FILES['pdfFiles']['tmp_name'] as $index => $uploadedFile) {
    $messages = [];

    // ✅ Sanitizar nombre del archivo
    $originalName = basename($_FILES['pdfFiles']['name'][$index]);
    $originalName = preg_replace('/[^a-zA-Z0-9_\-.]/', '_', $originalName);

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

    // 🛡️ Detección mejorada de contraseña / encriptación
    $pdfinfo = shell_exec("pdfinfo $escapedPath 2>&1");
    if (preg_match('/Encrypted:\s*yes/i', $pdfinfo)) {
        if (preg_match('/(owner|print:|copy:|change:)/i', $pdfinfo)) {
            $messages[] = "⚠️ El PDF tiene restricciones (no requiere contraseña para abrir).";
        } else {
            $messages[] = "❌ El PDF está protegido con contraseña.";
            $results[$originalName] = ['resumen' => $messages];
            continue;
        }
    } else {
        $messages[] = "✅ El PDF no tiene contraseña ni restricciones.";
    }

    // 🔍 Obtener trailer y catálogo raíz
    $trailer = shell_exec("mutool show $escapedPath trailer");
    $root = shell_exec("mutool show $escapedPath trailer /Root 2>/dev/null");

    // 🧾 Detección extendida de formularios (versión avanzada)
    $containsFormularios = false;

    // 1️⃣ Escaneo clásico
    if (strpos($trailer, '/AcroForm') !== false || strpos($root, '/AcroForm') !== false) {
        $containsFormularios = true;
    }

    // 2️⃣ Escaneo página por página (Annots / Widget)
    for ($p = 1; $p <= 5; $p++) {
        $annots = shell_exec("mutool show $escapedPath $p 2>/dev/null | grep -E '/(Annots|Widget)'");
        if ($annots) {
            $containsFormularios = true;
            break;
        }
    }

    // 3️⃣ Escaneo global binario (detección avanzada)
    $pdfRawScan = shell_exec("strings $escapedPath | grep -E '/(AcroForm|NeedAppearances|Subtype /Widget|FT /Btn|FT /Tx|FT /Ch|FT /Sig|XFA)'");
    if ($pdfRawScan) {
        $containsFormularios = true;
    }

    // 4️⃣ Detección específica de XFA (Adobe LiveCycle)
    $containsXFA = (strpos($pdfRawScan, '/XFA') !== false);

    // ✅ Resultado final
    if ($containsXFA) {
        $messages[] = "❌ Contiene formularios XFA (Adobe LiveCycle).";
    } elseif ($containsFormularios) {
        $messages[] = "❌ Contiene formularios interactivos (campos o checkboxes).";
    } else {
        $messages[] = "✅ No contiene formularios.";
    }

    // 📎 Archivos incrustados
    $containsObjetosIncrustados = (strpos($trailer, '/EmbeddedFiles') !== false || strpos($trailer, '/FileAttachment') !== false);
    $messages[] = $containsObjetosIncrustados ? "❌ Contiene archivos incrustados." : "✅ No contiene objetos incrustados.";

    // 💻 JavaScript embebido
    $containsJS = (preg_match('/\/(JavaScript|JS)/', $trailer) || preg_match('/\/(JavaScript|JS)/', $root));
    $messages[] = $containsJS ? "❌ Contiene JavaScript." : "✅ No contiene JavaScript.";

    // 🗒️ Anotaciones
    $hasAnnots = shell_exec("mutool show $escapedPath 1 2>&1 | grep '/Annots'");
    $messages[] = $hasAnnots ? "⚠️ El PDF contiene anotaciones o comentarios." : "✅ No contiene anotaciones.";

    // 🔗 Enlaces externos
    $links = shell_exec("strings $escapedPath | grep -E 'https?://'");
    $messages[] = $links ? "⚠️ El PDF contiene enlaces externos (URLs)." : "✅ No contiene enlaces externos.";

    // 🧬 Metadatos
    $metadata = shell_exec("exiftool -s -s -s $escapedPath");
    $messages[] = (strlen(trim($metadata)) > 0) ? "⚠️ El PDF contiene metadatos incrustados." : "✅ No contiene metadatos visibles.";

    // 🔤 Texto embebido u OCR
    $textContent = shell_exec("pdftotext $escapedPath - | tr -d '\\n\\r '");
    $messages[] = (strlen($textContent) > 50) ? "⚠️ El PDF contiene texto embebido (posible OCR o capa de texto)." : "✅ El PDF parece ser imagen pura (sin texto embebido).";

    // 📄 Páginas en blanco
    $pageImages = shell_exec("pdftoppm -jpeg -f 1 -l 3 $escapedPath /tmp/page_check 2>/dev/null && identify -format '%[fx:mean]\\n' /tmp/page_check*.jpg");
    $blankPages = 0;
    if ($pageImages) {
        $means = array_filter(explode("\n", trim($pageImages)), 'strlen');
        foreach ($means as $m) {
            if ((float)$m > 0.98) $blankPages++;
        }
        $messages[] = $blankPages > 0 ? "⚠️ Se detectaron {$blankPages} páginas en blanco." : "✅ No se detectaron páginas en blanco.";
        shell_exec("rm -f /tmp/page_check*.jpg");
    }

    // 🖼️ Análisis de imágenes
    $pdfimages = shell_exec("pdfimages -list $escapedPath");
    $totalImages = 0;
    $validDPI = true;
    $validGray8 = 0;

    if (!$pdfimages) {
        $messages[] = "❌ No se pudo analizar imágenes del PDF.";
        $results[$originalName] = ['resumen' => $messages];
        //continue;
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
        if ($x_dpi == 0 || $y_dpi == 0 || $x_dpi < 300 || $y_dpi < 300) $validDPI = false;
        if ($color === 'gray' && $bpc === 8) $validGray8++;
    }

    // ✅ Cálculo global después del análisis de imágenes
    $allGray8 = ($totalImages > 0 && $validGray8 === $totalImages);

    // Guardar resultados por archivo
    $results[$originalName] = [
        'resumen' => $messages,
        'detalles' => [
            'tamaño' => round($_FILES['pdfFiles']['size'][$index] / 1024, 2) . " KB",
            'pdf_valido' => (in_array($mime, ['application/pdf', 'application/x-pdf'])) ? "✅ Es un PDF válido." : "❌ No es un PDF válido.",
            'sin_contraseña' => preg_match('/Encrypted:\s*yes/i', $pdfinfo) ? "⚠️ Contiene restricciones o contraseña." : "✅ No tiene contraseña.",
            'sin_formularios' => $containsFormularios ? "❌ Contiene formularios." : "✅ No contiene formularios.",
            'sin_objetos_incrustados' => $containsObjetosIncrustados ? "❌ Contiene objetos incrustados." : "✅ No contiene objetos incrustados.",
            'sin_javascript' => $containsJS ? "❌ Contiene JavaScript." : "✅ No contiene JavaScript.",
            'imagenes' => $totalImages > 0 ? "✅ Se encontraron imágenes en el PDF." : "⚠️ No se encontraron imágenes.",
            'dpi_imagenes' => ($totalImages === 0) ? "⚠️ No aplica." : ($validDPI ? "✅ Todas las imágenes cumplen con 300 DPI o más." : "❌ Algunas imágenes tienen menos de 300 DPI."),
            'imagenes_grayscale' => ($totalImages === 0)
                ? "⚠️ No aplica."
                : ($allGray8
                    ? "✅ Todas las imágenes están en escala de grises a 8 bits."
                    : "❌ Solo $validGray8 de $totalImages imágenes están en escala de grises a 8 bits."
                )
        ]
    ];

    // ✅ Subir siempre al servidor
    $uploadDir = __DIR__ . '/uploads/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $destino = $uploadDir . $originalName;
    if (move_uploaded_file($uploadedFile, $destino)) {
        $results[$originalName]['resumen'][] = "✅ El archivo ha sido subido al servidor.";
    } else {
        $results[$originalName]['resumen'][] = "❌ Hubo un error al subir el archivo al servidor.";
    }

    // ⚠️ Si hubo errores de validación, agregarlos al resumen para que el usuario los revise
    foreach ($messages as $msg) {
        if (strpos($msg, "❌") === 0 || strpos($msg, "⚠️") === 0) {
            $results[$originalName]['resumen'][] = "⚠️ Revisar: " . $msg;
        }
    }
}

outputAndExit($results);

function outputAndExit(array $messages) {
    header('Content-Type: application/json');
    echo json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}
?>




