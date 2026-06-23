<?php
$x = file_get_contents(dirname(__DIR__, 2) . '/storage/templates/document_raw.xml');
$pos = stripos($x, 'Elaborado por');
echo substr($x, $pos, 4500);
