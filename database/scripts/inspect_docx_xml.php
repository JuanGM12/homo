<?php
$x = file_get_contents(dirname(__DIR__, 2) . '/storage/templates/document_raw.xml');
$terms = ['seleccionada', 'seleccionado', 'SUBREGION', 'ASESORIAS', 'NUMERO DE PERSONAS', 'NOMBRE', 'PROFESIONAL', 'FFFF00', '4472C4', 'D9E2F3', 'FFF2CC'];
foreach ($terms as $t) {
    $pos = stripos($x, $t);
    echo $t . ': ' . ($pos !== false ? 'found @' . $pos : 'not found') . PHP_EOL;
    if ($pos !== false) {
        echo '  ...' . substr($x, max(0, $pos - 80), 200) . '...' . PHP_EOL;
    }
}
