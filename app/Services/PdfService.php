<?php

declare(strict_types=1);

namespace App\Services;

use Mpdf\Mpdf;
use Mpdf\Output\Destination;

final class PdfService
{
    public static function renderHtml(
        string $html,
        string $orientation = 'P',
        string $title = 'Documento PDF'
    ): string {
        $encoding = mb_detect_encoding($html, ['UTF-8', 'Windows-1252', 'ISO-8859-1'], true);
        if ($encoding !== false && $encoding !== 'UTF-8') {
            $html = mb_convert_encoding($html, 'UTF-8', $encoding);
        }

        $cleanHtml = iconv('UTF-8', 'UTF-8//IGNORE', $html);
        if ($cleanHtml !== false) {
            $html = $cleanHtml;
        }

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
        $mpdf->shrink_tables_to_fit = 1;
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }
}
