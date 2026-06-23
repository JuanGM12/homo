<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Services\AsistenciaInformeService;

$service = new AsistenciaInformeService();
$user = [
    'name' => 'Usuario Prueba',
    'document_number' => '123456789',
    'roles' => ['psicologo'],
    'role' => 'psicologo',
];

$content = $service->buildDocx(
    [],
    $user,
    'SUROESTE',
    'LA PINTADA',
    '2026-01-01',
    '2026-06-30',
    false
);

$out = dirname(__DIR__, 2) . '/storage/templates/test_informe_output.docx';
file_put_contents($out, $content);
echo 'OK bytes=' . strlen($content) . ' path=' . $out . PHP_EOL;

$z = new ZipArchive();
$z->open($out);
$xml = $z->getFromName('word/document.xml');
$z->close();
echo (str_contains($xml, 'LA PINTADA') ? 'Municipio OK' : 'Municipio MISSING') . PHP_EOL;
echo (str_contains($xml, 'Usuario Prueba') ? 'Nombre OK' : 'Nombre MISSING') . PHP_EOL;
