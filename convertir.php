<?php
header('Content-Type: application/json');

require_once 'funcion_convertir.php'; // Aquí pondrás tu función convertirPDFparaVUCEM()

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'mensaje' => 'Método no permitido.']);
    exit;
}

if (empty($_POST['archivo'])) {
    echo json_encode(['success' => false, 'mensaje' => 'No se recibió el nombre del archivo.']);
    exit;
}

$nombreArchivo = basename($_POST['archivo']); // Evita rutas maliciosas
$rutaEntrada = __DIR__ . '/uploads/' . $nombreArchivo; // Cambia 'uploads' si usas otra carpeta
$rutaSalida = __DIR__ . '/convertidos/' . pathinfo($nombreArchivo, PATHINFO_FILENAME) . '_vucem.pdf';

// Verifica que el archivo original exista
if (!file_exists($rutaEntrada)) {
    echo json_encode(['success' => false, 'mensaje' => 'El archivo no existe en el servidor.']);
    exit;
}

// Crea carpeta de salida si no existe
if (!is_dir(dirname($rutaSalida))) {
    mkdir(dirname($rutaSalida), 0777, true);
}

// Ejecuta la conversión
$resultado = convertirPDFparaVUCEM($rutaEntrada, $rutaSalida);

// Analiza la respuesta de la función
if (strpos($resultado, '✅') !== false && file_exists($rutaSalida)) {
    echo json_encode([
        'success' => true,
        'mensaje' => $resultado,
        'url_convertido' => 'convertidos/' . basename($rutaSalida)
    ]);
} else {
    echo json_encode([
        'success' => false,
        'mensaje' => $resultado
    ]);
}
?>