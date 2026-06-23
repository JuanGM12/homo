<?php

declare(strict_types=1);

/**
 * Genera storage/templates/informe_final_gestion.docx con placeholders PhpWord.
 * Ejecutar: php database/scripts/prepare_informe_template.php
 */

$root = dirname(__DIR__, 2);
$source = $root . '/INFORME FINAL DE GESTIÓN_v2.docx';
$destDir = $root . '/storage/templates';
$dest = $destDir . '/informe_final_gestion.docx';

if (!is_file($source)) {
    fwrite(STDERR, "No se encuentra: {$source}\n");
    exit(1);
}

if (!is_dir($destDir) && !mkdir($destDir, 0755, true) && !is_dir($destDir)) {
    fwrite(STDERR, "No se pudo crear {$destDir}\n");
    exit(1);
}

copy($source, $dest);

$zip = new ZipArchive();
if ($zip->open($dest) !== true) {
    fwrite(STDERR, "No se pudo abrir docx\n");
    exit(1);
}

$xml = $zip->getFromName('word/document.xml');
if ($xml === false) {
    fwrite(STDERR, "document.xml no encontrado\n");
    exit(1);
}

/** Reemplaza el contenido de párrafos entre tags dentro de un bloque XML. */
function replaceParagraphContent(string $xml, string $pattern, string $replacement): string
{
    $result = preg_replace($pattern, $replacement, $xml, 1);

    return is_string($result) ? $result : $xml;
}

// Subregión: celda con "La " + "seleccionada"
$xml = replaceParagraphContent(
    $xml,
    '/(<w:tc>.*?<w:t>SUBREGION<\/w:t>.*?<\/w:tc>\s*<w:tc>.*?<w:p[^>]*>)(.*?)(<\/w:p><\/w:tc>)/su',
    '$1<w:r><w:rPr><w:rFonts w:ascii="Arial Rounded MT Bold" w:hAnsi="Arial Rounded MT Bold"/><w:highlight w:val="yellow"/></w:rPr><w:t>${SUBREGION}</w:t></w:r>$3'
);

// Municipio: celda con "El " + "seleccionado"
$xml = replaceParagraphContent(
    $xml,
    '/(<w:tc>.*?<w:t>MUNICIPIO<\/w:t>.*?<\/w:tc>\s*<w:tc>.*?<w:p[^>]*>)(.*?)(<\/w:p><\/w:tc>)/su',
    '$1<w:r><w:rPr><w:rFonts w:ascii="Arial Rounded MT Bold" w:hAnsi="Arial Rounded MT Bold"/><w:highlight w:val="yellow"/></w:rPr><w:t>${MUNICIPIO}</w:t></w:r>$3'
);

// Fila de valores bajo TOTAL ASESORIAS | TOTAL ASISTENCIAS | TEMATICAS AOAT
$xml = replaceParagraphContent(
    $xml,
    '/(<w:t xml:space="preserve"> ASESORIAS REALIZADAS<\/w:t>.*?<\/w:tr>\s*<w:tr[^>]*>)(.*?)(<\/w:tr>\s*<\/w:tbl>)/su',
    '$1'
    . '<w:tc><w:tcPr><w:tcW w:w="3116" w:type="dxa"/></w:tcPr>'
    . '<w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:highlight w:val="yellow"/></w:rPr><w:t>${TOTAL_ASESORIAS}</w:t></w:r></w:p></w:tc>'
    . '<w:tc><w:tcPr><w:tcW w:w="3117" w:type="dxa"/></w:tcPr>'
    . '<w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:highlight w:val="yellow"/></w:rPr><w:t>${TOTAL_ASISTENCIAS}</w:t></w:r></w:p></w:tc>'
    . '<w:tc><w:tcPr><w:tcW w:w="3117" w:type="dxa"/></w:tcPr>'
    . '<w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:highlight w:val="yellow"/></w:rPr><w:t>${TEMATICAS_AOAT}</w:t></w:r></w:p></w:tc>'
    . '$3'
);

// Población impactada — fila de datos (NUMERO DE PERSONAS | CARGO | TEMATICA GENERAL)
$xml = replaceParagraphContent(
    $xml,
    '/(<w:t>NUMERO DE PERSONAS<\/w:t>.*?<w:t>CARGO<\/w:t>.*?<w:t>TEMATICA GENERAL<\/w:t>.*?<\/w:tr>\s*<w:tr[^>]*>)(.*?)(<\/w:tr>\s*<\/w:tbl>)/su',
    '$1'
    . '<w:tc><w:tcPr><w:tcW w:w="3116" w:type="dxa"/></w:tcPr>'
    . '<w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:highlight w:val="yellow"/></w:rPr><w:t>${NUM_PERSONAS}</w:t></w:r></w:p></w:tc>'
    . '<w:tc><w:tcPr><w:tcW w:w="3117" w:type="dxa"/></w:tcPr>'
    . '<w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:highlight w:val="yellow"/></w:rPr><w:t>${CARGOS_IMPACTO}</w:t></w:r></w:p></w:tc>'
    . '<w:tc><w:tcPr><w:tcW w:w="3117" w:type="dxa"/></w:tcPr>'
    . '<w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:highlight w:val="yellow"/></w:rPr><w:t>${TEMATICAS_IMPACTO}</w:t></w:r></w:p></w:tc>'
    . '$3'
);

// Resumen ejecutivo hint (amarillo)
$xml = str_replace(
    '>Del municipio acompañado<',
    '>${RESUMEN_EJECUTIVO_HINT}<',
    $xml
);
$xml = str_replace(
    '>Del municipio acompa' . "\xC3\xB1" . 'ado<',
    '>${RESUMEN_EJECUTIVO_HINT}<',
    $xml
);

// Elaborado por — fila de valores (debajo de NOMBRE | CARGO/ROL | DOCUMENTO | FIRMA)
$xml = replaceParagraphContent(
    $xml,
    '/(<w:t xml:space="preserve"> PROFESIONAL<\/w:t>.*?<w:t>FIRMA<\/w:t>.*?<\/w:tr>\s*<w:tr[^>]*>)(.*?)(<\/w:tr>\s*<\/w:tbl>)/su',
    '$1'
    . '<w:tc><w:tcPr><w:tcW w:w="2337" w:type="dxa"/><w:vAlign w:val="center"/></w:tcPr>'
    . '<w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:highlight w:val="yellow"/></w:rPr><w:t>${ELABORADO_NOMBRE}</w:t></w:r></w:p></w:tc>'
    . '<w:tc><w:tcPr><w:tcW w:w="2337" w:type="dxa"/><w:vAlign w:val="center"/></w:tcPr>'
    . '<w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:highlight w:val="yellow"/></w:rPr><w:t>${ELABORADO_CARGO}</w:t></w:r></w:p></w:tc>'
    . '<w:tc><w:tcPr><w:tcW w:w="2338" w:type="dxa"/><w:vAlign w:val="center"/></w:tcPr>'
    . '<w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:highlight w:val="yellow"/></w:rPr><w:t>${ELABORADO_DOCUMENTO}</w:t></w:r></w:p></w:tc>'
    . '<w:tc><w:tcPr><w:tcW w:w="2338" w:type="dxa"/><w:vAlign w:val="center"/></w:tcPr>'
    . '<w:p><w:pPr><w:jc w:val="center"/></w:pPr></w:p></w:tc>'
    . '$3'
);

// Nota de generación antes de "Elaborado por"
if (!str_contains($xml, '${FECHA_GENERACION}')) {
    $xml = str_replace(
        '<w:t>Elaborado por:</w:t>',
        '<w:t>${FECHA_GENERACION}</w:t></w:r></w:p><w:p><w:r><w:t>${NOTA_PLATAFORMA}</w:t></w:r></w:p><w:p><w:r><w:t>Elaborado por:</w:t>',
        $xml
    );
}

$zip->deleteName('word/document.xml');
$zip->addFromString('word/document.xml', $xml);
$zip->close();

echo "Plantilla generada: {$dest}\n";
