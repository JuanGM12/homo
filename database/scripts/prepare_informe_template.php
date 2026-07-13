<?php

declare(strict_types=1);

/**
 * Genera storage/templates/informe_final_gestion.docx con placeholders PhpWord.
 * Ejecutar: php database/scripts/prepare_informe_template.php
 */

$root = dirname(__DIR__, 2);
$source = $root . '/INFORME FINAL DE GESTIÓN_v2 (5).docx';
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

// Subregión: celda vacía a la derecha del rótulo.
$xml = replaceParagraphContent(
    $xml,
    '/(<w:tc>.*?<w:t>SUBREGION<\/w:t>.*?<\/w:tc>\s*<w:tc>.*?<w:p[^>]*>)(.*?)(<\/w:p><\/w:tc>)/su',
    '$1<w:r><w:rPr><w:rFonts w:ascii="Arial Rounded MT Bold" w:hAnsi="Arial Rounded MT Bold"/><w:highlight w:val="yellow"/></w:rPr><w:t>${SUBREGION}</w:t></w:r>$3'
);

// Municipio: celda vacía a la derecha del rótulo.
$xml = replaceParagraphContent(
    $xml,
    '/(<w:tc>.*?<w:t>MUNICIPIO<\/w:t>.*?<\/w:tc>\s*<w:tc>.*?<w:p[^>]*>)(.*?)(<\/w:p><\/w:tc>)/su',
    '$1<w:r><w:rPr><w:rFonts w:ascii="Arial Rounded MT Bold" w:hAnsi="Arial Rounded MT Bold"/><w:highlight w:val="yellow"/></w:rPr><w:t>${MUNICIPIO}</w:t></w:r>$3'
);

// Fila de valores bajo TOTAL ASESORIAS | TOTAL ASISTENCIAS | TEMATICAS AOAT.
$xml = replaceParagraphContent(
    $xml,
    '/(<w:t>TOTAL ASESORIAS REALIZADAS<\/w:t>.*?<w:t>TOTAL ASISTENCIAS TECNICAS REALIZADAS<\/w:t>.*?<w:t>TEMATICAS GENERALES ABORDADAS EN LAS AOAT<\/w:t>.*?<\/w:tr>\s*<w:tr[^>]*>)(.*?)(<\/w:tr>\s*<\/w:tbl>)/su',
    '$1'
    . '<w:tc><w:tcPr><w:tcW w:w="3116" w:type="dxa"/></w:tcPr>'
    . '<w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:highlight w:val="yellow"/></w:rPr><w:t>${TOTAL_ASESORIAS}</w:t></w:r></w:p></w:tc>'
    . '<w:tc><w:tcPr><w:tcW w:w="3117" w:type="dxa"/></w:tcPr>'
    . '<w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:highlight w:val="yellow"/></w:rPr><w:t>${TOTAL_ASISTENCIAS}</w:t></w:r></w:p></w:tc>'
    . '<w:tc><w:tcPr><w:tcW w:w="3117" w:type="dxa"/></w:tcPr>'
    . '<w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:highlight w:val="yellow"/></w:rPr><w:t>${TEMATICAS_AOAT}</w:t></w:r></w:p></w:tc>'
    . '$3'
);

// Población impactada: fila de datos.
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

// Elaborado por: fila de valores debajo de NOMBRE | CARGO | DOCUMENTO DE IDENTIDAD | FIRMA.
$xml = replaceParagraphContent(
    $xml,
    '/(<w:t>NOMBRE<\/w:t>.*?<w:t>CARGO<\/w:t>.*?<w:t>DOCUMENTO DE IDENTIDAD<\/w:t>.*?<w:t>FIRMA<\/w:t>.*?<\/w:tr>\s*<w:tr[^>]*>)(.*?)(<\/w:tr>\s*<\/w:tbl>)/su',
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

/** Reemplaza texto dentro de un párrafo sin cruzar límites </w:p>. */
function replaceParagraphText(string $xml, string $search, string $replace): string
{
    $pattern = '/(<w:p[^>]*>(?:(?!<\/w:p>).)*)'
        . preg_quote($search, '/')
        . '((?:(?!<\/w:p>).)*<\/w:p>)/';
    $result = preg_replace($pattern, '$1' . $replace . '$2', $xml, 1);

    return is_string($result) ? $result : $xml;
}

/** Elimina un párrafo completo que contiene un texto. */
function removeParagraphContaining(string $xml, string $needle): string
{
    $pattern = '/<w:p[^>]*>(?:(?!<\/w:p>).)*'
        . preg_quote($needle, '/')
        . '(?:(?!<\/w:p>).)*<\/w:p>\s*/';
    $result = preg_replace($pattern, '', $xml, 1);

    return is_string($result) ? $result : $xml;
}

// Marco normativo: placeholder único con listado completo desde el servicio.
$xml = replaceParagraphText($xml, 'Ley 1616 de 2013 (Salud Mental).', '${MARCO_NORMATIVO}');
foreach ([
    'Ley 1566 de 2012.',
    'Resolución 518 de 2015.',
    'Resolución 3280 de 2018.',
    'Plan Decenal de Salud Pública vigente.',
    'Ordenanza 041 de 2022 (Política Pública Departamental de Salud Mental y Prevención de las Adicciones).',
] as $legacyMarcoLine) {
    $xml = removeParagraphContaining($xml, $legacyMarcoLine);
}

// Nota de generación antes de "Elaborado por".
if (!str_contains($xml, '${FECHA_GENERACION}')) {
    $xml = str_replace(
        '<w:t>Elaborado por:</w:t>',
        '<w:t>${FECHA_GENERACION}</w:t></w:r></w:p><w:p><w:r><w:t>${DESGLOSE_METRICAS}</w:t></w:r></w:p><w:p><w:r><w:t>${NOTA_PLATAFORMA}</w:t></w:r></w:p><w:p><w:r><w:t>Elaborado por:</w:t>',
        $xml
    );
}

$zip->deleteName('word/document.xml');
$zip->addFromString('word/document.xml', $xml);
$zip->close();

echo "Plantilla generada: {$dest}\n";
