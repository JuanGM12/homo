<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Enunciados y textos de opciones de los tests (misma fuente que los formularios PRE/POST).
 */
final class EvaluacionesQuestionCatalog
{
    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    /** @return array<string, mixed> */
    private static function all(): array
    {
        if (self::$cache === null) {
            /** @var array<string, mixed> $data */
            $data = require dirname(__DIR__) . '/Data/EvaluacionesQuestionsData.php';
            self::$cache = $data;
        }

        return self::$cache;
    }

    /**
     * @return array{text: string, options: array<string, string>}|null
     */
    public static function getQuestion(string $testKey, int $questionNumber, ?string $phase = null): ?array
    {
        $all = self::all();
        $bank = $all[$testKey] ?? null;
        if (!is_array($bank)) {
            return null;
        }

        if (
            $testKey === 'hospitales'
            && isset($bank['pre'], $bank['post'])
            && is_array($bank['pre'])
            && is_array($bank['post'])
        ) {
            $p = strtolower((string) ($phase ?? 'pre'));
            $subBank = $p === 'post' ? $bank['post'] : $bank['pre'];
            $row = $subBank[$questionNumber] ?? null;
        } else {
            $row = $bank[$questionNumber] ?? null;
        }

        if (!is_array($row) || !isset($row['text'], $row['options']) || !is_array($row['options'])) {
            return null;
        }

        return [
            'text' => (string) $row['text'],
            'options' => $row['options'],
        ];
    }

    /** Texto introductorio del caso (solo adicciones). */
    public static function getAdiccionesContext(): ?string
    {
        $all = self::all();
        $ctx = $all['adicciones_context'] ?? null;

        return is_string($ctx) && $ctx !== '' ? $ctx : null;
    }
}
