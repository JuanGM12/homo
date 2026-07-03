<?php

declare(strict_types=1);

namespace App\Services;

use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

final class PdfService
{
    /**
     * @param bool $optimizedListPdf Si true, ajustes para tablas largas (menos trabajo de maquetación en mPDF).
     */
    public static function renderHtml(
        string $html,
        string $orientation = 'P',
        string $title = 'Documento PDF',
        bool $optimizedListPdf = false
    ): string {
        self::relaxPcreLimits();
        $html = self::sanitizeChunk($html);

        $mpdf = self::createMpdf($orientation, $title, $optimizedListPdf);
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    /**
     * Genera un PDF escribiendo el CSS de cabecera y luego cada bloque de cuerpo por separado.
     *
     * Evita el error de mPDF «El tamaño del código HTML es mayor que pcre.backtrack_limit»
     * al no procesar todo el HTML en una sola pasada de expresiones regulares.
     *
     * @param list<string> $bodyChunks Bloques de cuerpo HTML (sin <html>/<head>/<body>).
     */
    public static function renderHtmlSections(
        array $bodyChunks,
        string $css,
        string $orientation = 'P',
        string $title = 'Documento PDF',
        bool $optimizedListPdf = false,
        bool $pageBreakBetween = true
    ): string {
        self::relaxPcreLimits();

        $mpdf = self::createMpdf($orientation, $title, $optimizedListPdf);

        $css = trim($css);
        if ($css !== '') {
            $mpdf->WriteHTML(self::sanitizeChunk($css), HTMLParserMode::HEADER_CSS);
        }

        $first = true;
        foreach ($bodyChunks as $chunk) {
            $chunk = self::sanitizeChunk((string) $chunk);
            if ($chunk === '') {
                continue;
            }
            if (!$first && $pageBreakBetween) {
                $mpdf->WriteHTML('<pagebreak />', HTMLParserMode::HTML_BODY);
            }
            $mpdf->WriteHTML($chunk, HTMLParserMode::HTML_BODY);
            $first = false;
        }

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    private static function sanitizeChunk(string $html): string
    {
        $encoding = mb_detect_encoding($html, ['UTF-8', 'Windows-1252', 'ISO-8859-1'], true);
        if ($encoding !== false && $encoding !== 'UTF-8') {
            $html = mb_convert_encoding($html, 'UTF-8', $encoding);
        }

        $cleanHtml = iconv('UTF-8', 'UTF-8//IGNORE', $html);

        return $cleanHtml !== false ? $cleanHtml : $html;
    }

    /**
     * Sube los límites de PCRE para que mPDF pueda procesar HTML extenso sin abortar.
     */
    private static function relaxPcreLimits(): void
    {
        $backtrack = (int) ini_get('pcre.backtrack_limit');
        if ($backtrack < 50000000) {
            @ini_set('pcre.backtrack_limit', '50000000');
        }
        $recursion = (int) ini_get('pcre.recursion_limit');
        if ($recursion < 50000000) {
            @ini_set('pcre.recursion_limit', '50000000');
        }
    }

    private static function createMpdf(string $orientation, string $title, bool $optimizedListPdf): Mpdf
    {
        $tempDir = dirname(__DIR__, 2) . '/storage/mpdf';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => strtoupper($orientation) === 'L' ? 'L' : 'P',
            'tempDir' => $tempDir,
            'default_font' => 'dejavusans',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 10,
            'margin_bottom' => 10,
        ]);

        $mpdf->SetTitle($title);
        $mpdf->simpleTables = true;
        $mpdf->packTableData = true;
        // shrink_tables_to_fit en tablas muy grandes fuerza varios pasos de cálculo; en listados largos es más rápido desactivarlo.
        $mpdf->shrink_tables_to_fit = $optimizedListPdf ? 0 : 1;
        if ($optimizedListPdf) {
            $mpdf->useSubstitutions = false;
            $mpdf->table_error_report = false;
        }

        return $mpdf;
    }
}
