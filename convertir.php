<?php
header('Content-Type: application/json');
require_once 'funcion_convertir.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'mensaje' => 'Método no permitido.']);
    exit;
}

if (empty($_POST['archivo'])) {
    echo json_encode(['success' => false, 'mensaje' => 'No se recibió el nombre del archivo.']);
    exit;
}

$nombreArchivo = basename($_POST['archivo']);
$rutaEntrada = __DIR__ . '/uploads/' . $nombreArchivo;
$rutaSalida = __DIR__ . '/convertidos/' . pathinfo($nombreArchivo, PATHINFO_FILENAME) . '_vucem.pdf';

if (!file_exists($rutaEntrada)) {
    echo json_encode(['success' => false, 'mensaje' => 'El archivo no existe.']);
    exit;
}

if (!is_dir(dirname($rutaSalida))) {
    mkdir(dirname($rutaSalida), 0777, true);
}

$resultado = convertirPDFparaVUCEM($rutaEntrada, $rutaSalida);

// Si la conversión fue exitosa
if (strpos($resultado, '✅') !== false && file_exists($rutaSalida)) {
    // Borrar el archivo original
    if (file_exists($rutaEntrada)) unlink($rutaEntrada);

    // Devuelve solo el nombre del archivo convertido
    echo json_encode([
        'success' => true,
        'mensaje' => $resultado,
        'archivo_convertido' => basename($rutaSalida)
    ]);
} else {
    echo json_encode([
        'success' => false,
        'mensaje' => $resultado
    ]);
}
?>
