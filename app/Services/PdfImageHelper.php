<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Imágenes embebidas en HTML para Dompdf (data URI), sin depender de URLs remotas.
 */
final class PdfImageHelper
{
    /**
     * Marca institucional solo texto para exportaciones «Excel» (.html servido como .xls).
     * Microsoft Excel no incorpora bien &lt;img src="data:image/...;base64,..."&gt;: suele crear
     * vínculos a objeto externos y muestra «No se puede mostrar la imagen vinculada».
     */
    public static function institutionBrandTextForExcelHtml(string $label): string
    {
        return '<span style="display:inline-block;font-weight:700;color:#35645b;font-size:11px;line-height:1.25;">'
            . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
    }

    public static function imageDataUri(string $path): string
    {
        if (!is_readable($path)) {
            return '';
        }

        $binary = file_get_contents($path);
        if ($binary === false) {
            return '';
        }

        $mime = 'image/png';
        if (function_exists('mime_content_type')) {
            $detected = mime_content_type($path);
            if (is_string($detected) && $detected !== '') {
                $mime = $detected;
            }
        }

        return 'data:' . $mime . ';base64,' . base64_encode($binary);
    }
}
