<?php
$x = file_get_contents(dirname(__DIR__, 2) . '/storage/templates/document_raw.xml');
$pos = stripos($x, 'ASESORIAS REALIZADAS');
echo substr($x, $pos, 3500) . PHP_EOL;
