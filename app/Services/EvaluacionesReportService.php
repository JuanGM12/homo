<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Agrupa respuestas PRE/POST por persona y temática, calcula «Resultado impacto» y resúmenes por municipio.
 */
final class EvaluacionesReportService
{
    public const IMPACT_MEJORIA = 'mejoria';
    public const IMPACT_SIN_CAMBIOS = 'sin_cambios';
    public const IMPACT_SIN_MEJORIA = 'sin_mejoria';
    public const IMPACT_PENDIENTE_POST = 'pendiente_post';
    public const IMPACT_PENDIENTE_PRE = 'pendiente_pre';

    /**
     * @param array<int, array<string, mixed>> $records
     * @param array<string, array{name: string, color: string}> $testsList
     * @return array<int, array<string, mixed>>
     */
    public static function buildComparisonRows(array $records, array $testsList): array
    {
        /** @var array<string, array{pre: array|null, post: array|null}> $groups */
        $groups = [];

        foreach ($records as $row) {
            $tk = (string) ($row['test_key'] ?? '');
            $doc = (string) ($row['document_number'] ?? '');
            if ($tk === '' || $doc === '') {
                continue;
            }
            $key = $tk . '|' . $doc;
            if (!isset($groups[$key])) {
                $groups[$key] = ['pre' => null, 'post' => null];
            }
            $phase = (string) ($row['phase'] ?? '');
            if ($phase === 'pre') {
                $groups[$key]['pre'] = $row;
            } elseif ($phase === 'post') {
                $groups[$key]['post'] = $row;
            }
        }

        $out = [];
        foreach ($groups as $key => $pair) {
            $pre = $pair['pre'];
            $post = $pair['post'];
            [$tk, $doc] = explode('|', $key, 2);
            $testName = (string) ($testsList[$tk]['name'] ?? $tk);

            $baseRow = $pre ?? $post;
            if ($baseRow === null) {
                continue;
            }

            $preA = $pre ?? [];
            $postA = $post ?? [];

            $firstName = (string) (($preA['first_name'] ?? '') !== '' ? $preA['first_name'] : ($postA['first_name'] ?? ''));
            $lastName = (string) (($preA['last_name'] ?? '') !== '' ? $preA['last_name'] : ($postA['last_name'] ?? ''));
            $subregion = (string) (($preA['subregion'] ?? '') !== '' ? $preA['subregion'] : ($postA['subregion'] ?? ''));
            $municipality = (string) (($preA['municipality'] ?? '') !== '' ? $preA['municipality'] : ($postA['municipality'] ?? ''));

            $preScore = $pre !== null ? (float) ($pre['score_percent'] ?? 0) : null;
            $postScore = $post !== null ? (float) ($post['score_percent'] ?? 0) : null;

            $impact = self::classifyImpact($preScore, $postScore);

            $out[] = [
                'test_key' => $tk,
                'test_name' => $testName,
                'document_number' => $doc,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'subregion' => $subregion,
                'municipality' => $municipality,
                'pre' => $pre,
                'post' => $post,
                'pre_score' => $preScore,
                'post_score' => $postScore,
                'pre_at' => $preA['created_at'] ?? null,
                'post_at' => $postA['created_at'] ?? null,
                'impact' => $impact['code'],
                'impact_label' => $impact['label'],
                'impact_badge_class' => $impact['badge_class'],
                'delta' => $impact['delta'],
            ];
        }

        usort($out, static function (array $a, array $b): int {
            $ma = (string) ($a['municipality'] ?? '');
            $mb = (string) ($b['municipality'] ?? '');
            if ($ma !== $mb) {
                return strcmp($ma, $mb);
            }
            $ta = (string) ($a['test_name'] ?? '');
            $tb = (string) ($b['test_name'] ?? '');
            if ($ta !== $tb) {
                return strcmp($ta, $tb);
            }
            return strcmp((string) ($a['document_number'] ?? ''), (string) ($b['document_number'] ?? ''));
        });

        return $out;
    }

    /**
     * @return array{code: string, label: string, badge_class: string, delta: ?float}
     */
    public static function classifyImpact(?float $preScore, ?float $postScore): array
    {
        if ($preScore === null && $postScore === null) {
            return [
                'code' => self::IMPACT_PENDIENTE_PRE,
                'label' => 'Sin diligenciar',
                'badge_class' => 'secondary',
                'delta' => null,
            ];
        }
        if ($preScore !== null && $postScore === null) {
            return [
                'code' => self::IMPACT_PENDIENTE_POST,
                'label' => 'Pendiente POST',
                'badge_class' => 'warning',
                'delta' => null,
            ];
        }
        if ($preScore === null && $postScore !== null) {
            return [
                'code' => self::IMPACT_PENDIENTE_PRE,
                'label' => 'Sin PRE',
                'badge_class' => 'secondary',
                'delta' => null,
            ];
        }

        $delta = round($postScore - $preScore, 2);

        if ($delta > 0.01) {
            return [
                'code' => self::IMPACT_MEJORIA,
                'label' => 'Con mejoría',
                'badge_class' => 'success',
                'delta' => $delta,
            ];
        }
        if ($delta < -0.01) {
            return [
                'code' => self::IMPACT_SIN_MEJORIA,
                'label' => 'Sin mejoría',
                'badge_class' => 'danger',
                'delta' => $delta,
            ];
        }

        return [
            'code' => self::IMPACT_SIN_CAMBIOS,
            'label' => 'Sin cambios',
            'badge_class' => 'secondary',
            'delta' => $delta,
        ];
    }

    /**
     * Resumen por municipio: solo filas con PRE y POST (para porcentajes de impacto).
     *
     * @param array<int, array<string, mixed>> $comparisonRows
     * @return array<string, array{municipality: string, con_ambos: int, mejoria: int, sin_cambios: int, sin_mejoria: int, pct_mejoria: float, pct_sin_cambios: float, pct_sin_mejoria: float}>
     */
    public static function summarizeByMunicipality(array $comparisonRows): array
    {
        // Totales globales
        $totals = ['con_ambos' => 0, 'mejoria' => 0, 'sin_cambios' => 0, 'sin_mejoria' => 0];
        $byMun = [];

        foreach ($comparisonRows as $row) {
            $mun = trim((string) ($row['municipality'] ?? ''));
            if ($mun === '') {
                $mun = 'Sin municipio';
            }
            if (!isset($byMun[$mun])) {
                $byMun[$mun] = [
                    'municipality' => $mun,
                    'con_ambos' => 0,
                    'mejoria' => 0,
                    'sin_cambios' => 0,
                    'sin_mejoria' => 0,
                    'pct_mejoria' => 0.0,
                    'pct_sin_cambios' => 0.0,
                    'pct_sin_mejoria' => 0.0,
                ];
            }

            $impact = (string) ($row['impact'] ?? '');
            if (!in_array($impact, [self::IMPACT_MEJORIA, self::IMPACT_SIN_CAMBIOS, self::IMPACT_SIN_MEJORIA], true)) {
                continue;
            }

            $byMun[$mun]['con_ambos']++;
            if ($impact === self::IMPACT_MEJORIA) {
                $byMun[$mun]['mejoria']++;
                $totals['mejoria']++;
            } elseif ($impact === self::IMPACT_SIN_CAMBIOS) {
                $byMun[$mun]['sin_cambios']++;
                $totals['sin_cambios']++;
            } else {
                $byMun[$mun]['sin_mejoria']++;
                $totals['sin_mejoria']++;
            }
            $totals['con_ambos']++;
        }

        foreach ($byMun as $k => $block) {
            $n = (int) $block['con_ambos'];
            if ($n > 0) {
                $byMun[$k]['pct_mejoria'] = round($block['mejoria'] / $n * 100, 1);
                $byMun[$k]['pct_sin_cambios'] = round($block['sin_cambios'] / $n * 100, 1);
                $byMun[$k]['pct_sin_mejoria'] = round($block['sin_mejoria'] / $n * 100, 1);
            }
        }

        $global = [
            'municipality' => 'Total (filtro actual)',
            'con_ambos' => $totals['con_ambos'],
            'mejoria' => $totals['mejoria'],
            'sin_cambios' => $totals['sin_cambios'],
            'sin_mejoria' => $totals['sin_mejoria'],
            'pct_mejoria' => $totals['con_ambos'] > 0 ? round($totals['mejoria'] / $totals['con_ambos'] * 100, 1) : 0.0,
            'pct_sin_cambios' => $totals['con_ambos'] > 0 ? round($totals['sin_cambios'] / $totals['con_ambos'] * 100, 1) : 0.0,
            'pct_sin_mejoria' => $totals['con_ambos'] > 0 ? round($totals['sin_mejoria'] / $totals['con_ambos'] * 100, 1) : 0.0,
        ];

        $list = array_values($byMun);
        usort($list, static function (array $a, array $b): int {
            return strcmp((string) ($a['municipality'] ?? ''), (string) ($b['municipality'] ?? ''));
        });

        return [
            'global' => $global,
            'by_municipality' => $list,
        ];
    }
}
