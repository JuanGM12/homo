<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Seguimiento territorial de AoAT: metas (Asesoría + Asistencia técnica) por municipio y mes.
 * Los municipios "asignados" se infieren de los registros históricos del profesional (distinct subregión/municipio).
 */
final class AoatSeguimientoService
{
    /** Cuentan para la meta operativa */
    public const META_ACTIVITY_TYPES = ['Asesoría', 'Asistencia técnica'];

    /** Registro «Actividad» (exclusivo frente a A+AT en seguimiento) */
    public const STANDALONE_ACTIVITY_TYPE = 'Actividad';

    /**
     * @param array<int, array<string, mixed>> $records
     * @param array<string, mixed> $filters
     * @return array{
     *   vista: 'meta'|'actividad',
     *   months: list<array{num:int,label:string,key:string}>,
     *   rows: list<array<string, mixed>>,
     *   legend: array<string, string>
     * }
     */
    public function buildMatrix(array $records, array $filters): array
    {
        $year = max(2020, (int) ($filters['year'] ?? (int) date('Y')));
        $period = (string) ($filters['period'] ?? 'ene_jun');
        $vista = trim((string) ($filters['vista'] ?? 'meta')) === 'actividad' ? 'actividad' : 'meta';
        $professionalUserId = max(0, (int) ($filters['professional_user_id'] ?? 0));
        $filterMonth = (int) ($filters['filter_month'] ?? 0);
        $roleFilter = trim((string) ($filters['role'] ?? ''));
        $subregionFilter = trim((string) ($filters['subregion'] ?? ''));
        $municipalityFilters = $filters['municipalities'] ?? [];
        if (!is_array($municipalityFilters)) {
            $municipalityFilters = [];
        }
        $municipalityFilters = array_values(array_unique(array_filter(array_map(static function ($v): string {
            return trim((string) $v);
        }, $municipalityFilters), static fn (string $s): bool => $s !== '')));

        $monthRange = $period === 'jul_dic' ? range(7, 12) : range(1, 6);
        if ($filterMonth >= 1 && $filterMonth <= 12 && in_array($filterMonth, $monthRange, true)) {
            $monthRange = [$filterMonth];
        }

        $prepared = $this->prepareRecords($records);
        $territory = $this->distinctTerritories($prepared, $roleFilter, $subregionFilter, $municipalityFilters, $professionalUserId);

        $monthsMeta = [];
        foreach ($monthRange as $m) {
            $monthsMeta[] = [
                'num' => $m,
                'key' => 'm' . $m,
                'label' => $this->monthShortLabel($m),
            ];
        }

        $rows = [];
        foreach ($territory as $t) {
            $uid = (int) $t['user_id'];
            $sub = (string) $t['subregion'];
            $mun = (string) $t['municipality'];
            $prole = (string) $t['professional_role'];

            if ($vista === 'meta' && $this->isProfesionalSocialRole($prole)) {
                continue;
            }

            $monthCounts = [];
            $monthCells = [];
            $consolidado = 0;

            foreach ($monthRange as $m) {
                if ($vista === 'actividad') {
                    $c = $this->actividadCountInMonth($prepared, $uid, $sub, $mun, $year, $m);
                    $monthCounts['m' . $m] = $c;
                    $consolidado += $c;
                    $monthCells['m' . $m] = [
                        'count' => $c,
                        'asesoria' => 0,
                        'asistencia_tecnica' => 0,
                        'tier' => 'plain',
                        'target' => null,
                    ];
                } else {
                    $brk = $this->metaBreakdownInMonth(
                        $prepared,
                        $uid,
                        $sub,
                        $mun,
                        $year,
                        $m
                    );
                    $c = $brk['total'];
                    $monthCounts['m' . $m] = $c;
                    $consolidado += $c;

                    $target = $this->monthlyTargetForRole($prole);
                    $monthCells['m' . $m] = [
                        'count' => $c,
                        'asesoria' => $brk['asesoria'],
                        'asistencia_tecnica' => $brk['asistencia_tecnica'],
                        'tier' => $this->monthCellTier($year, $m, $target, $c),
                        'target' => $target,
                    ];
                }
            }

            $numMonths = count($monthRange);
            if ($vista === 'actividad') {
                $monthlyTarget = null;
                $expectedTotal = null;
                $debe = null;
            } else {
                $monthlyTarget = $this->monthlyTargetForRole($prole);
                $expectedTotal = $monthlyTarget !== null ? $monthlyTarget * $numMonths : null;
                $debe = $expectedTotal !== null ? $consolidado - $expectedTotal : null;
            }

            $rows[] = [
                'user_id' => $uid,
                'advisor_name' => trim((string) $t['advisor_name']),
                'professional_role' => $prole,
                'professional_role_label' => $this->roleLabel($prole),
                'subregion' => $sub,
                'municipality' => $mun,
                'months' => $monthCounts,
                'month_cells' => $monthCells,
                'meta_mensual' => $monthlyTarget,
                'consolidado_meta' => $consolidado,
                'expected' => $expectedTotal,
                'debe' => $debe,
                'debe_tier' => $debe === null ? 'na' : ($debe >= 0 ? 'ok' : 'bad'),
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            $cmp = strcasecmp((string) ($a['advisor_name'] ?? ''), (string) ($b['advisor_name'] ?? ''));
            if ($cmp !== 0) {
                return $cmp;
            }
            $cmp = strcasecmp((string) ($a['subregion'] ?? ''), (string) ($b['subregion'] ?? ''));
            if ($cmp !== 0) {
                return $cmp;
            }
            $cmp = strcasecmp((string) ($a['municipality'] ?? ''), (string) ($b['municipality'] ?? ''));
            if ($cmp !== 0) {
                return $cmp;
            }

            return ((int) ($a['user_id'] ?? 0)) <=> ((int) ($b['user_id'] ?? 0));
        });

        $rows = $this->filterRowsByPeriodTotal($rows, trim((string) ($filters['total_periodo'] ?? '')));

        $uidCounts = [];
        foreach ($rows as $r) {
            $u = (int) ($r['user_id'] ?? 0);
            $uidCounts[$u] = ($uidCounts[$u] ?? 0) + 1;
        }

        $running = [];
        $groupIdx = -1;
        $lastUid = null;
        foreach ($rows as &$r) {
            $u = (int) ($r['user_id'] ?? 0);
            if ($lastUid === null || $u !== $lastUid) {
                $groupIdx++;
            }
            $lastUid = $u;
            $r['group_band'] = $groupIdx % 2;
            $running[$u] = ($running[$u] ?? 0) + 1;
            $r['prof_line'] = $running[$u];
            $r['prof_total_lines'] = $uidCounts[$u] ?? 1;
        }
        unset($r);

        $legend = $vista === 'actividad'
            ? [
                'meta' => 'Vista de actividades: solo se cuentan registros AoAT con tipo «Actividad». No aplica meta ni saldo DEBE.',
                'territory' => 'Cada fila sigue basada en territorios donde el profesional tiene historial AoAT (misma grilla que la vista de metas).',
            ]
            : [
                'meta' => 'Solo Asesoría y Asistencia técnica cuentan para la meta y el saldo «DEBE». Profesional social no se lista aquí (sin meta A+AT).',
                'territory' => 'Cada fila (subregión + municipio + profesional) surge de registros AoAT existentes: si el profesional ha trabajado ese municipio, aparece aquí. Un cero en rojo es déficit en un mes ya iniciado; un mes futuro en calendario se muestra distinto aunque el total sea 0.',
            ];

        return [
            'vista' => $vista,
            'months' => $monthsMeta,
            'rows' => $rows,
            'legend' => $legend,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $records
     * @return list<array<string, mixed>>
     */
    private function prepareRecords(array $records): array
    {
        $out = [];
        foreach ($records as $row) {
            $payload = $this->decodePayload($row);
            $activityDate = $this->normalizeActivityDate($payload);
            $activityType = trim((string) ($payload['activity_type'] ?? ''));
            $out[] = [
                'id' => (int) ($row['id'] ?? 0),
                'user_id' => (int) ($row['user_id'] ?? 0),
                'state' => (string) ($row['state'] ?? ''),
                'subregion' => trim((string) ($row['subregion'] ?? '')),
                'municipality' => trim((string) ($row['municipality'] ?? '')),
                'professional_role' => trim((string) ($row['professional_role'] ?? '')),
                'professional_name' => trim((string) ($row['professional_name'] ?? '')),
                'professional_last_name' => trim((string) ($row['professional_last_name'] ?? '')),
                'activity_date' => $activityDate,
                'activity_type' => $activityType,
                'is_meta' => in_array($activityType, self::META_ACTIVITY_TYPES, true),
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $prepared
     * @return list<array<string, mixed>>
     */
    /**
     * @param list<string> $municipalityFilters vacío = todos los municipios
     */
    private function distinctTerritories(array $prepared, string $roleFilter, string $subregionFilter, array $municipalityFilters, int $professionalUserId = 0): array
    {
        $map = [];
        foreach ($prepared as $r) {
            if ($r['subregion'] === '' || $r['municipality'] === '') {
                continue;
            }
            $uid = (int) $r['user_id'];
            if ($professionalUserId > 0 && $uid !== $professionalUserId) {
                continue;
            }
            $pr = (string) $r['professional_role'];
            if ($roleFilter !== '' && $this->normalizeRoleToken($pr) !== $this->normalizeRoleToken($roleFilter)) {
                continue;
            }
            if ($subregionFilter !== '' && $r['subregion'] !== $subregionFilter) {
                continue;
            }
            if ($municipalityFilters !== [] && !in_array($r['municipality'], $municipalityFilters, true)) {
                continue;
            }
            $key = $uid . '|' . $r['subregion'] . '|' . $r['municipality'];
            if (!isset($map[$key])) {
                $name = trim($r['professional_name'] . ' ' . $r['professional_last_name']);
                $map[$key] = [
                    'user_id' => $uid,
                    'subregion' => $r['subregion'],
                    'municipality' => $r['municipality'],
                    'professional_role' => $pr,
                    'advisor_name' => $name !== '' ? $name : ('Usuario #' . $uid),
                ];
            }
        }

        return array_values($map);
    }

    /**
     * Cuenta Asesoría y Asistencia técnica por separado (ambas cuentan para la meta).
     *
     * @param list<array<string, mixed>> $prepared
     * @return array{asesoria:int,asistencia_tecnica:int,total:int}
     */
    private function metaBreakdownInMonth(
        array $prepared,
        int $userId,
        string $subregion,
        string $municipality,
        int $year,
        int $month
    ): array {
        $asesoria = 0;
        $asistencia = 0;
        foreach ($prepared as $r) {
            if (!$r['is_meta']) {
                continue;
            }
            if ((int) $r['user_id'] !== $userId) {
                continue;
            }
            if ($r['subregion'] !== $subregion || $r['municipality'] !== $municipality) {
                continue;
            }
            $d = $r['activity_date'];
            if ($d === '') {
                continue;
            }
            if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $mch)) {
                continue;
            }
            $y = (int) $mch[1];
            $mo = (int) $mch[2];
            if ($y !== $year || $mo !== $month) {
                continue;
            }
            $type = (string) ($r['activity_type'] ?? '');
            if ($type === 'Asesoría') {
                $asesoria++;
            } elseif ($type === 'Asistencia técnica') {
                $asistencia++;
            }
        }

        return [
            'asesoria' => $asesoria,
            'asistencia_tecnica' => $asistencia,
            'total' => $asesoria + $asistencia,
        ];
    }

    /**
     * Cuenta registros con activity_type «Actividad» (no mezclar con A+AT).
     *
     * @param list<array<string, mixed>> $prepared
     */
    private function actividadCountInMonth(
        array $prepared,
        int $userId,
        string $subregion,
        string $municipality,
        int $year,
        int $month
    ): int {
        $n = 0;
        foreach ($prepared as $r) {
            if ((string) ($r['activity_type'] ?? '') !== self::STANDALONE_ACTIVITY_TYPE) {
                continue;
            }
            if ((int) $r['user_id'] !== $userId) {
                continue;
            }
            if ($r['subregion'] !== $subregion || $r['municipality'] !== $municipality) {
                continue;
            }
            $d = $r['activity_date'];
            if ($d === '') {
                continue;
            }
            if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $mch)) {
                continue;
            }
            $y = (int) $mch[1];
            $mo = (int) $mch[2];
            if ($y !== $year || $mo !== $month) {
                continue;
            }
            $n++;
        }

        return $n;
    }

    /**
     * Filtra por total del periodo mostrado (columna «Total» / consolidado_meta).
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function filterRowsByPeriodTotal(array $rows, string $token): array
    {
        $token = match ($token) {
            'gt0', 'eq0', 'gt1' => $token,
            default => '',
        };
        if ($token === '') {
            return $rows;
        }

        $out = array_filter($rows, static function (array $r) use ($token): bool {
            $t = (int) ($r['consolidado_meta'] ?? 0);

            return match ($token) {
                'gt0' => $t > 0,
                'eq0' => $t === 0,
                'gt1' => $t > 1,
                default => true,
            };
        });

        return array_values($out);
    }

    private function normalizeRoleToken(string $role): string
    {
        $r = strtolower(trim(str_replace('_', ' ', $role)));

        return preg_replace('/\s+/', ' ', $r) ?? $r;
    }

    private function stripSpanishAccents(string $s): string
    {
        static $map = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u', 'Ñ' => 'n',
        ];

        return strtr($s, $map);
    }

    /**
     * Rol profesional social: sin meta operativa A+AT; no se lista en vista de metas.
     */
    private function isProfesionalSocialRole(string $professionalRole): bool
    {
        $r = $this->stripSpanishAccents($this->normalizeRoleToken($professionalRole));

        return str_contains($r, 'profesional') && str_contains($r, 'social');
    }

    public function monthlyTargetForRole(string $professionalRole): ?int
    {
        if ($this->isProfesionalSocialRole($professionalRole)) {
            return null;
        }
        $r = $this->stripSpanishAccents($this->normalizeRoleToken($professionalRole));
        if ($r === 'psicologo' || $r === 'abogado') {
            return 2;
        }
        if ($r === 'medico') {
            return 1;
        }

        return null;
    }

    private function roleLabel(string $role): string
    {
        $r = $this->stripSpanishAccents($this->normalizeRoleToken($role));
        return match ($r) {
            'profesional social' => 'Profesional social',
            'psicologo' => 'Psicólogo',
            'medico' => 'Médico',
            'abogado' => 'Abogado',
            default => $role,
        };
    }

    private function monthShortLabel(int $m): string
    {
        $labels = [1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic'];

        return $labels[$m] ?? (string) $m;
    }

    /**
     * Color del mes: el rojo «cero» solo aplica a meses ya iniciados; meses futuros usan otro estado.
     */
    private function monthCellTier(int $year, int $month, ?int $target, int $count): string
    {
        if ($target === null) {
            return 'na';
        }
        if ($count >= $target) {
            return 'ok';
        }
        if ($count > 0) {
            return 'warn';
        }
        if ($this->isCalendarMonthStrictlyAfterToday($year, $month)) {
            return 'upcoming';
        }

        return 'bad';
    }

    private function isCalendarMonthStrictlyAfterToday(int $year, int $month): bool
    {
        if ($month < 1 || $month > 12) {
            return false;
        }
        $today = new \DateTimeImmutable('today');
        $cur = ((int) $today->format('Y')) * 12 + (int) $today->format('n');
        $cell = $year * 12 + $month;

        return $cell > $cur;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function decodePayload(array $row): array
    {
        if (!isset($row['payload']) || $row['payload'] === null) {
            return [];
        }
        $decoded = json_decode((string) $row['payload'], true);

        return is_array($decoded) ? $decoded : [];
    }

    private function normalizeActivityDate(array $payload): string
    {
        $raw = trim((string) ($payload['activity_date'] ?? ''));
        if ($raw === '') {
            return '';
        }
        try {
            return (new \DateTimeImmutable($raw))->format('Y-m-d');
        } catch (\Exception) {
            return substr($raw, 0, 10);
        }
    }
}
