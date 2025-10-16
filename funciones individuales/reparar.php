<?php

$pdfOriginal = '/ruta/al/archivo.pdf';
$pdfSinPassword = '/tmp/pdf_sin_password.pdf';
$pdfLimpioFinal = '/ruta/al/archivo_limpio.pdf';

// 1. Verificar si tiene contraseña
$info = shell_exec("pdfinfo " . escapeshellarg($pdfOriginal));
$tienePassword = strpos($info, 'Encrypted: yes') !== false;

// 2. Quitar contraseña si existe
if ($tienePassword) {
    echo "🔒 El PDF está protegido. Intentando eliminar contraseña...\n";

    // Asume contraseña vacía o conocida. Puedes modificar según tu caso.
    $cmd = "qpdf --password='' --decrypt " . escapeshellarg($pdfOriginal) . " " . escapeshellarg($pdfSinPassword);
    shell_exec($cmd);

    if (!file_exists($pdfSinPassword)) {
        die("❌ No se pudo quitar la contraseña.\n");
    }

    echo "✅ Contraseña eliminada.\n";
    $pdfActual = $pdfSinPassword;
} else {
    echo "✅ El PDF no tiene contraseña.\n";
    $pdfActual = $pdfOriginal;
}

// 3. Inspeccionar trailer con mutool
$trailer = shell_exec("mutool show " . escapeshellarg($pdfActual) . " trailer");

// 4. Verificar elementos
$tieneFormularios = strpos($trailer, '/AcroForm') !== false;
$tieneAdjuntos = strpos($trailer, '/EmbeddedFiles') !== false || strpos($trailer, '/FileAttachment') !== false;
$tieneJS = preg_match('/\/(JavaScript|JS)/', $trailer);

if (!$tieneFormularios && !$tieneAdjuntos && !$tieneJS) {
    echo "✅ El PDF no contiene formularios, archivos adjuntos ni JavaScript.\n";
    // Si también se limpió la contraseña, copiarlo como final
    if ($pdfActual !== $pdfOriginal) {
        copy($pdfActual, $pdfLimpioFinal);
        echo "📄 PDF limpio generado en: $pdfLimpioFinal\n";
    } else {
        echo "📄 El archivo original ya está limpio.\n";
    }
    exit;
}

echo "⚠️ Se encontraron elementos no deseados. Iniciando limpieza...\n";

// 5. Limpieza con Ghostscript (reimprime el PDF, eliminando acciones, JS, etc.)
$gsCmd = "gs -sDEVICE=pdfwrite -dNOPAUSE -dBATCH -dSAFER -dQUIET " .
         "-dPDFSETTINGS=/prepress " .
         "-sOutputFile=" . escapeshellarg($pdfLimpioFinal) . " " .
         escapeshellarg($pdfActual);
shell_exec($gsCmd);

// 6. Verificación final
if (file_exists($pdfLimpioFinal)) {
    echo "✅ PDF limpiado exitosamente: $pdfLimpioFinal\n";

    // Verificar que ahora esté limpio
    $finalTrailer = shell_exec("mutool show " . escapeshellarg($pdfLimpioFinal) . " trailer");

    if (strpos($finalTrailer, '/AcroForm') === false &&
        strpos($finalTrailer, '/EmbeddedFiles') === false &&
        strpos($finalTrailer, '/FileAttachment') === false &&
        !preg_match('/\/(JavaScript|JS)/', $finalTrailer)) {
        echo "🧼 Confirmado: El PDF final no contiene contenido activo.\n";
    } else {
        echo "❌ Atención: El PDF todavía contiene algunos elementos activos.\n";
    }

} else {
    echo "❌ Error: No se pudo generar el PDF limpio.\n";
}
?>
