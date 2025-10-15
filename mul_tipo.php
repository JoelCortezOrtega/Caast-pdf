<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['pdfFiles'])) {
        $uploadDir = __DIR__ . '/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileCount = count($_FILES['pdfFiles']['name']);
        $maxSize = 3 * 1024 * 1024; // 3 MB en bytes
        $success = 0;
        $failed = 0;

        for ($i = 0; $i < $fileCount; $i++) {
            $error = $_FILES['pdfFiles']['error'][$i];
            $tmpFile = $_FILES['pdfFiles']['tmp_name'][$i];
            $originalName = $_FILES['pdfFiles']['name'][$i];
            $fileSize = $_FILES['pdfFiles']['size'][$i];

            // Verificar si hubo error al subir
            if ($error !== UPLOAD_ERR_OK) {
                $failed++;
                echo "⚠️ Error al subir el archivo: " . htmlspecialchars($originalName) . "<br>";
                continue;
            }

            // Verificar tamaño máximo
            if ($fileSize > $maxSize) {
                $failed++;
                echo "🚫 El archivo " . htmlspecialchars($originalName) . " supera los 3 MB.<br>";
                continue;
            }

            // Verificar tipo MIME real
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $fileMime = finfo_file($finfo, $tmpFile);
            finfo_close($finfo);

            if (in_array($fileMime, ['application/pdf', 'application/x-pdf'])) {
                // Sanitizar nombre
                $safeName = preg_replace('/[^A-Za-z0-9_\.-]/', '_', $originalName);
                $destPath = $uploadDir . $safeName;

                // Mover archivo
                if (move_uploaded_file($tmpFile, $destPath)) {
                    $success++;
                    echo "✅ Archivo subido: " . htmlspecialchars($safeName) . "<br>";
                } else {
                    $failed++;
                    echo "❌ Error al mover el archivo: " . htmlspecialchars($originalName) . "<br>";
                }
            } else {
                $failed++;
                echo "⚠️ El archivo " . htmlspecialchars($originalName) . " no es un PDF válido.<br>";
            }
        }

        echo "<hr>";
        echo "📊 Resultado: $success archivos subidos, $failed fallidos.";
    } else {
        echo "⚠️ No se recibieron archivos.";
    }
}
?>
