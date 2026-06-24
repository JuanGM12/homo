<?php
$z = new ZipArchive();
$z->open(dirname(__DIR__, 2) . '/storage/templates/informe_final_gestion.docx');
$x = $z->getFromName('word/document.xml');
foreach (['SUBREGION','MUNICIPIO','TOTAL_ASESORIAS','TOTAL_ASISTENCIAS','TEMATICAS_AOAT','NUM_PERSONAS','CARGOS_IMPACTO','TEMATICAS_IMPACTO','ELABORADO_NOMBRE','ELABORADO_CARGO','ELABORADO_DOCUMENTO','FECHA_GENERACION','NOTA_PLATAFORMA','DESGLOSE_METRICAS'] as $p) {
    echo $p . ': ' . (str_contains($x, '${' . $p . '}') ? 'OK' : 'MISSING') . PHP_EOL;
}
