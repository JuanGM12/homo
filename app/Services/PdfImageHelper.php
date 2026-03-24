<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Imágenes embebidas en HTML para Dompdf (data URI), sin depender de URLs remotas.
 */
final class PdfImageHelper
{
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
