<?php

// Definir el tamaño máximo permitido (en bytes)
$sizeLimit = 3 * 1024 * 1024; // 3 MB, puedes ajustarlo según tu necesidad

// El archivo PDF cargado
$uploadedFile = $_FILES['pdfFile']['tmp_name'];

// Comprobar si el archivo fue cargado correctamente
if (isset($_FILES['pdfFile'])) 
{

    // Obtener el tamaño del archivo
    $fileSize = filesize($uploadedFile);

    if ($fileSize > $sizeLimit) {
        http_response_code(403);
        echo "El archivo excede el tamaño máximo permitido de 3 MB. No está autorizado.";
        exit;
    } else {
        echo "El archivo tiene un tamaño adecuado.";
    }
} 
else 
{
    echo "No se ha cargado ningún archivo.";
}

?>
