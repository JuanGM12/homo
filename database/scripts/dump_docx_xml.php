<?php
$z = new ZipArchive();
$z->open(dirname(__DIR__, 2) . '/INFORME FINAL DE GESTIÓN_v2.docx');
file_put_contents(dirname(__DIR__, 2) . '/storage/templates/document_raw.xml', $z->getFromName('word/document.xml'));
echo "written\n";
