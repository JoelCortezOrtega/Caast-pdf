<?php

$pdfPath = $_FILES['pdfFile']['tmp_name'];

// 1. Verificar contraseña
$info = shell_exec("pdfinfo " . escapeshellarg($pdfPath));
echo (strpos($info, 'Encrypted: yes') !== false)
    ? "❌ PDF con contraseña.\n"
    : "✅ Sin contraseña.\n";

// 2. Obtener trailer con mutool
$trailer = shell_exec("mutool show " . escapeshellarg($pdfPath) . " trailer");

// 3. Verificar formularios
echo (strpos($trailer, '/AcroForm') !== false)
    ? "❌ Contiene formularios (AcroForm).\n"
    : "✅ No contiene formularios.\n";

// 4. Verificar objetos incrustados
if (strpos($trailer, '/EmbeddedFiles') !== false || strpos($trailer, '/FileAttachment') !== false) {
    echo "❌ Contiene archivos incrustados.\n";
} else {
    echo "✅ No contiene objetos incrustados.\n";
}

// 5. Verificar JavaScript
echo (preg_match('/\/(JavaScript|JS)/', $trailer))
    ? "❌ Contiene JavaScript.\n"
    : "✅ No contiene JavaScript.\n";
?>
