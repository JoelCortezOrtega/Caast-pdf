<?php
$pdf = 'documento.pdf';

// 1. Extraer texto por página
exec("pdftotext -layout -nopgbrk '$pdf' -", $output);
$text = implode("\n", $output);

// Si no hay texto en absoluto, podría ser escaneado u OCR aplicado
if (trim($text) === '') {
    echo "⚠️ El PDF no contiene texto (posiblemente un escaneo sin OCR).\n";
} else {
    echo "✅ El PDF contiene texto.\n";
}

// 2. Detectar páginas en blanco
// Convertimos cada página en imagen y medimos cuántos píxeles no blancos hay
$tempDir = sys_get_temp_dir() . '/pdf_check_' . uniqid();
mkdir($tempDir);
exec("pdftoppm -png '$pdf' $tempDir/page");

foreach (glob("$tempDir/*.png") as $page) {
    $im = imagecreatefrompng($page);
    $width = imagesx($im);
    $height = imagesy($im);
    $nonWhite = 0;
    
    for ($x = 0; $x < $width; $x += 10) { // muestreo cada 10 píxeles
        for ($y = 0; $y < $height; $y += 10) {
            $rgb = imagecolorat($im, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            if ($r < 250 || $g < 250 || $b < 250) {
                $nonWhite++;
            }
        }
    }
    imagedestroy($im);
    
    if ($nonWhite < 50) {
        echo "⚠️ Página en blanco detectada: $page\n";
    }
}

exec("rm -rf '$tempDir'");
