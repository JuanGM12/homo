<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Request;

/**
 * Normaliza listas de municipios desde GET/POST (uno o varios valores).
 * Acepta municipality como string, array, o claves municipality[] en formularios.
 */
final class MunicipalityListRequest
{
    /**
     * @return list<string> valores únicos no vacíos
     */
    public static function parse(Request $request, string $key = 'municipality'): array
    {
        $raw = $request->input($key, []);
        if (is_string($raw)) {
            $t = trim($raw);
            if ($t === '') {
                return [];
            }

            return [$t];
        }
        if (!is_array($raw)) {
            return [];
        }
        $seen = [];
        foreach ($raw as $v) {
            $s = trim((string) $v);
            if ($s !== '') {
                $seen[$s] = true;
            }
        }

        return array_keys($seen);
    }
}
