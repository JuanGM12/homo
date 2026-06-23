<?php
$x = file_get_contents(dirname(__DIR__, 2) . '/storage/templates/document_raw.xml');
$pos = stripos($x, 'SUBREGION');
echo substr($x, $pos, 1200) . PHP_EOL . '---' . PHP_EOL;
$pos2 = stripos($x, 'ASESORIAS REALIZADAS');
echo substr($x, $pos2 - 400, 1600) . PHP_EOL;
