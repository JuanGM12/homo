<?php
require dirname(__DIR__, 2) . '/vendor/autoload.php';
use App\Services\AsistenciaInformeService;
$s = new AsistenciaInformeService();
$c = $s->buildDocx([], ['name'=>'Admin','document_number'=>'1','roles'=>['admin']], 'X','Y','2026-01-01','2026-02-01', true);
$out = dirname(__DIR__,2).'/storage/templates/test_consolidado.docx';
file_put_contents($out, $c);
$z=new ZipArchive();$z->open($out);$x=$z->getFromName('word/document.xml');$z->close();
echo str_contains($x,'Consolidado')?'consolidado OK':'consolidado FAIL';
