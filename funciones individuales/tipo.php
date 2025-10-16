<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1️⃣ Verificar que el archivo se haya subido correctamente
    if (isset($_FILES['pdfFile']) && $_FILES['pdfFile']['error'] === UPLOAD_ERR_OK) {
        $uploadedFile = $_FILES['pdfFile']['tmp_name'];
        $originalName = $_FILES['pdfFile']['name'];

        // 2️⃣ Verificar el tipo MIME real del archivo (no solo la extensión)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $fileMime = finfo_file($finfo, $uploadedFile);
        finfo_close($finfo);

        if (in_array($fileMime, ['application/pdf', 'application/x-pdf'])) {
            // 3️⃣ Crear carpeta de destino si no existe
            $uploadDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            // 4️⃣ Sanitizar el nombre del archivo (seguridad)
            $safeName = preg_replace('/[^A-Za-z0-9_\.-]/', '_', $originalName);

            // 5️⃣ Mover el archivo a la carpeta segura
            $destPath = $uploadDir . $safeName;
            if (move_uploaded_file($uploadedFile, $destPath)) {
                echo "✅ El archivo se subió correctamente: " . htmlspecialchars($safeName);
            } else {
                echo "❌ Error al mover el archivo.";
            }
        } else {
            echo "⚠️ El archivo no es un PDF válido. Tipo detectado: {$fileMime}";
        }
    } else {
        echo "⚠️ No se subió ningún archivo o hubo un error al subirlo.";
    }
}
?>

