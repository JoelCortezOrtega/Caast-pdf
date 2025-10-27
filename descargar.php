<?php
if (empty($_GET['archivo'])) exit;

$archivo = basename($_GET['archivo']);
$ruta = __DIR__ . '/convertidos/' . $archivo;

if (!file_exists($ruta)) exit;

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $archivo . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($ruta));

readfile($ruta);
unlink($ruta); // Borrar archivo después de descargar
exit;
?>

