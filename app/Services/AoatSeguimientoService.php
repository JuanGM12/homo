<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AoatMetaRuleRepository;

/**
 * Seguimiento territorial de AoAT: metas (Asesoría + Asistencia técnica) por municipio y mes;
 * para abogados el conteo mensual usa SAFER y política pública (Mesa / PPMSMYPA) sobre el mismo tipo de actividades.
 * Los municipios "asignados" se infieren de los registros historicos del profesional.
 */
final class AoatSeguimientoService
{
    /** @var list<array<string, mixed>> */
    private array $metaRules;

    /** Cuentan para la meta operativa */
    public const META_ACTIVITY_TYPES = ['Asesoría', 'Asistencia técnica'];

    /** Registro "Actividad" (exclusivo frente a A+AT en seguimiento) */
    public const STANDALONE_ACTIVITY_TYPE = 'Actividad';

    public function __construct(?AoatMetaRuleRepository $metaRuleRepo = null)
    {
        $repo = $metaRuleRepo ?? new AoatMetaRuleRepository();
        $this->metaRules = $repo->allActive();
    }

    /**
     * @param array<int, array<string, mixed>> $records
     * @param array<string, mixed> $filters include_global_monthly_targets: bool (solo servidor; define bloque de metas globales mensuales)
     * @return array{
     *   vista: 'meta'|'actividad',
     *   months: list<array{num:int,label:string,key:string}>,
     *   rows: list<array<string, mixed>>,
     *   legend: array<string, string>,
     *   global_targets: list<array<string, mixed>>,
     *   meta_month_subtitle: string
     * }
     */
    public function buildMatrix(array $records, array $filters): array
    {
        $year = max(2020, (int) ($filters['year'] ?? (int) date('Y')));
        $period = (string) ($filters['period'] ?? 'ene_jun');
        $vista = trim((string) ($filters['vista'] ?? 'meta')) === 'actividad' ? 'actividad' : 'meta';
        $professionalUserId = max(0, (int) ($filters['professional_user_id'] ?? 0));
        $filterMonth = (int) ($filters['filter_month'] ?? 0);
        $stateFilter = trim((string) ($filters['state'] ?? ''));
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
        if ($stateFilter !== '') {
            $prepared = array_values(array_filter($prepared, static function (array $row) use ($stateFilter): bool {
                return (string) ($row['state'] ?? '') === $stateFilter;
            }));
        }

        $territory = $this->distinctTerritories($prepared, $roleFilter, $subregionFilter, $municipalityFilters, $professionalUserId);

        $monthsMeta = [];
        foreach ($monthRange as $m) {
            $monthsMeta[] = [
                'num' => $m,
                'key' => 'm' . $m,
                'label' => $this->monthShortLabel($m),
            ];
        }

        $includeGlobalMonthly = $vista === 'meta' && !empty($filters['include_global_monthly_targets']);
        $globalTargets = $includeGlobalMonthly ? $this->buildGlobalTargets($prepared, $year, $monthRange) : [];
        $rows = [];

        foreach ($territory as $t) {
            $uid = (int) $t['user_id'];
            $sub = (string) $t['subregion'];
            $mun = (string) $t['municipality'];
            $prole = (string) $t['professional_role'];

            $monthCounts = [];
            $monthCells = [];
            $consolidado = 0;
            $expectedTotalAcc = 0;
            $hasExpectedTotal = false;
            $metaLabels = [];
            $metaScope = null;
            $displayMonthlyTarget = null;

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
                    continue;
                }

                $rule = $this->resolveRuleForRoleMonth($prole, $year, $m);
                $brk = $this->metaBreakdownInMonth($prepared, $uid, $sub, $mun, $year, $m, $prole);
                $c = $brk['total'];
                $monthCounts['m' . $m] = $c;
                $consolidado += $c;

                $target = $rule !== null && (string) ($rule['scope'] ?? '') === 'per_territory'
                    ? (int) ($rule['target_value'] ?? 0)
                    : null;

                if ($target !== null) {
                    $expectedTotalAcc += $target;
                    $hasExpectedTotal = true;
                    if ($displayMonthlyTarget === null) {
                        $displayMonthlyTarget = $target;
                    }
                }

                if ($rule !== null) {
                    $metaLabels[] = $this->ruleShortLabel($rule);
                    if ($metaScope === null) {
                        $metaScope = (string) ($rule['scope'] ?? '');
                    }
                }

                $monthCells['m' . $m] = [
                    'count' => $c,
                    'asesoria' => $brk['asesoria'],
                    'asistencia_tecnica' => $brk['asistencia_tecnica'],
                    'abogado_safer' => $brk['abogado_safer'] ?? null,
                    'abogado_politica' => $brk['abogado_politica'] ?? null,
                    'tier' => $this->monthCellTier($year, $m, $target, $c),
                    'target' => $target,
                ];
            }

            if ($vista === 'actividad') {
                $monthlyTarget = null;
                $metaLabel = null;
                $metaScope = null;
                $expectedTotal = null;
                $debe = null;
            } else {
                $monthlyTarget = $displayMonthlyTarget;
                $metaLabel = $this->buildMetaLabel($metaLabels, $monthlyTarget, $metaScope);
                $expectedTotal = $hasExpectedTotal ? $expectedTotalAcc : null;
                $debe = $expectedTotal !== null ? $consolidado - $expectedTotal : null;
                if ($this->isProfesionalSocialRole($prole)) {
                    $metaLabel = 'Solo conteo A+AT (sin meta)';
                    $monthlyTarget = null;
                    $expectedTotal = null;
                    $debe = null;
                }
            }

            $rows[] = [
                'user_id' => $uid,
                'advisor_name' => trim((string) $t['advisor_name']),
                'professional_role' => $prole,
                'meta_breakdown_mode' => $this->isAbogadoRole($prole) ? 'abogado' : 'standard',
                'professional_role_label' => $this->roleLabel($prole),
                'subregion' => $sub,
                'municipality' => $mun,
                'months' => $monthCounts,
                'month_cells' => $monthCells,
                'meta_mensual' => $monthlyTarget,
                'meta_label' => $metaLabel,
                'meta_scope' => $metaScope,
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

        $abogadoRoleFilter = $roleFilter !== '' && $this->isAbogadoRole($roleFilter);

        $legend = $vista === 'actividad'
            ? [
                'meta' => 'Vista de actividades: solo se cuentan registros AoAT con tipo "Actividad". No aplica meta ni saldo.',
                'territory' => 'Cada fila sigue basada en territorios donde el profesional tiene historial AoAT (misma grilla que la vista de metas).',
            ]
            : [
                'meta' => $abogadoRoleFilter
                    ? 'Abogado: solo cuentan AoAT tipo Asesoría o Asistencia técnica. El total mensual frente a la meta son registros con respuesta útil en SAFER y/o en política pública (Mesa Municipal de Salud Mental o PPMSMYPA): al menos uno de esos dos bloques distinto de solo «No aplica». El detalle muestra S = SAFER y P = política pública (un mismo registro puede sumar en ambos).'
                    : ($includeGlobalMonthly
                        ? 'Solo Asesoria y Asistencia tecnica cuentan para la meta y el saldo (profesional social: mismo conteo A+AT que psicologia, sin meta numerica ni saldo). Psicologia y Derecho cambian meta por tramo del ano; Medicina usa meta global mensual entre todos.'
                        : 'Solo Asesoria y Asistencia tecnica cuentan para la meta y el saldo (profesional social: mismo conteo A+AT que psicologia, sin meta numerica ni saldo). Psicologia y Derecho cambian meta por tramo del ano.'),
                'territory' => 'Cada fila (subregión + municipio + profesional) surge de registros AoAT existentes. Un cero en rojo es deficit en un mes ya iniciado; un mes futuro en calendario se muestra distinto aunque el total sea 0.',
            ];

        $metaMonthSubtitle = $vista === 'meta'
            ? ($abogadoRoleFilter ? 'S · P' : 'A+AT')
            : 'Act.';

        return [
            'vista' => $vista,
            'months' => $monthsMeta,
            'rows' => $rows,
            'legend' => $legend,
            'global_targets' => $globalTargets,
            'meta_month_subtitle' => $metaMonthSubtitle,
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
                'abogado_safer_hit' => self::abogadoSaferHit($payload),
                'abogado_politica_hit' => self::abogadoPoliticaHit($payload),
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $prepared
     * @param list<string> $municipalityFilters
     * @return list<array<string, mixed>>
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
            if ($roleFilter !== '' && !$this->matchesSeguimientoRoleFilter($pr, $roleFilter)) {
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
     * Cuenta Asesoria y Asistencia tecnica por separado (roles estándar), o SAFER / política pública para abogados.
     *
     * @param list<array<string, mixed>> $prepared
     * @return array{
     *   asesoria:int,
     *   asistencia_tecnica:int,
     *   total:int,
     *   abogado_safer?:int|null,
     *   abogado_politica?:int|null
     * }
     */
    private function metaBreakdownInMonth(
        array $prepared,
        int $userId,
        string $subregion,
        string $municipality,
        int $year,
        int $month,
        string $professionalRole
    ): array {
        if ($this->isAbogadoRole($professionalRole)) {
            return $this->metaBreakdownAbogadoInMonth($prepared, $userId, $subregion, $municipality, $year, $month);
        }

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
            $d = (string) ($r['activity_date'] ?? '');
            if ($d === '' || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $mch)) {
                continue;
            }
            if ((int) $mch[1] !== $year || (int) $mch[2] !== $month) {
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
            'abogado_safer' => null,
            'abogado_politica' => null,
        ];
    }

    /**
     * Abogado: total mensual (meta) = AoAT meta con hit SAFER y/o política pública (sin duplicar fila en el total).
     *
     * @param list<array<string, mixed>> $prepared
     * @return array{asesoria:int,asistencia_tecnica:int,total:int,abogado_safer:int,abogado_politica:int}
     */
    private function metaBreakdownAbogadoInMonth(
        array $prepared,
        int $userId,
        string $subregion,
        string $municipality,
        int $year,
        int $month
    ): array {
        $safer = 0;
        $politica = 0;
        $union = 0;

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
            $d = (string) ($r['activity_date'] ?? '');
            if ($d === '' || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $mch)) {
                continue;
            }
            if ((int) $mch[1] !== $year || (int) $mch[2] !== $month) {
                continue;
            }

            $hitS = !empty($r['abogado_safer_hit']);
            $hitP = !empty($r['abogado_politica_hit']);
            if (!$hitS && !$hitP) {
                continue;
            }

            $union++;
            if ($hitS) {
                $safer++;
            }
            if ($hitP) {
                $politica++;
            }
        }

        return [
            'asesoria' => 0,
            'asistencia_tecnica' => 0,
            'abogado_safer' => $safer,
            'abogado_politica' => $politica,
            'total' => $union,
        ];
    }

    private function isAbogadoRole(string $professionalRole): bool
    {
        return $this->stripSpanishAccents($this->normalizeRoleToken($professionalRole)) === 'abogado';
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function abogadoSaferHit(array $payload): bool
    {
        return self::abogadoCheckboxGroupHasNonNoAplica($payload['safer'] ?? null);
    }

    /**
     * Política pública: responde Mesa Municipal y/o PPMSMYPA (basta una con valor distinto de solo «No aplica»).
     *
     * @param array<string, mixed> $payload
     */
    private static function abogadoPoliticaHit(array $payload): bool
    {
        return self::abogadoCheckboxGroupHasNonNoAplica($payload['mesa_salud_mental'] ?? null)
            || self::abogadoCheckboxGroupHasNonNoAplica($payload['ppmsmypa'] ?? null);
    }

    /** @param mixed $raw payload checkbox group */
    private static function abogadoCheckboxGroupHasNonNoAplica(mixed $raw): bool
    {
        if (!is_array($raw) || $raw === []) {
            return false;
        }
        $vals = [];
        foreach ($raw as $item) {
            $t = trim((string) $item);
            if ($t !== '') {
                $vals[] = mb_strtolower($t, 'UTF-8');
            }
        }
        foreach ($vals as $v) {
            if ($v !== 'no aplica') {
                return true;
            }
        }

        return false;
    }

    /**
     * Cuenta registros con activity_type "Actividad".
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
            $d = (string) ($r['activity_date'] ?? '');
            if ($d === '' || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $mch)) {
                continue;
            }
            if ((int) $mch[1] !== $year || (int) $mch[2] !== $month) {
                continue;
            }
            $n++;
        }

        return $n;
    }

    /**
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

    private function isProfesionalSocialRole(string $professionalRole): bool
    {
        $r = $this->stripSpanishAccents($this->normalizeRoleToken($professionalRole));

        return str_contains($r, 'profesional') && str_contains($r, 'social');
    }

    /**
     * Filtro «Psicólogo» incluye profesionales sociales del mismo grupo operativo.
     */
    private function matchesSeguimientoRoleFilter(string $professionalRole, string $roleFilter): bool
    {
        $pr = $this->normalizeRoleToken($professionalRole);
        $fl = $this->normalizeRoleToken($roleFilter);
        if ($pr === $fl) {
            return true;
        }
        $flPlain = $this->stripSpanishAccents($fl);

        return $flPlain === 'psicologo' && $this->isProfesionalSocialRole($professionalRole);
    }

    public function monthlyTargetForRole(string $professionalRole): ?int
    {
        return $this->monthlyTargetForRoleMonth($professionalRole, (int) date('Y'), (int) date('n'));
    }

    public function monthlyTargetForRoleMonth(string $professionalRole, int $year, int $month): ?int
    {
        $rule = $this->resolveRuleForRoleMonth($professionalRole, $year, $month);
        if ($rule === null || (string) ($rule['scope'] ?? '') !== 'per_territory') {
            return null;
        }

        return (int) ($rule['target_value'] ?? 0);
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
     * @return array<string, mixed>|null
     */
    private function resolveRuleForRoleMonth(string $professionalRole, int $year, int $month): ?array
    {
        $roleKey = $this->stripSpanishAccents($this->normalizeRoleToken($professionalRole));
        $fallback = null;

        foreach ($this->metaRules as $rule) {
            if ((string) ($rule['role_key'] ?? '') !== $roleKey) {
                continue;
            }
            $from = (int) ($rule['month_from'] ?? 1);
            $to = (int) ($rule['month_to'] ?? 12);
            if ($month < $from || $month > $to) {
                continue;
            }
            $ruleYear = $rule['rule_year'] ?? null;
            if ($ruleYear === null || $ruleYear === '') {
                $fallback = $rule;
                continue;
            }
            if ((int) $ruleYear === $year) {
                return $rule;
            }
        }

        return $fallback;
    }

    /**
     * @param list<string> $labels
     */
    private function buildMetaLabel(array $labels, ?int $monthlyTarget, ?string $scope): ?string
    {
        $labels = array_values(array_unique(array_filter($labels, static fn ($v): bool => trim((string) $v) !== '')));

        if ($scope === 'global_monthly' && $labels !== []) {
            return $labels[0];
        }
        if ($monthlyTarget !== null && count($labels) <= 1) {
            return 'Meta ' . $monthlyTarget . '/mes';
        }
        if ($labels !== []) {
            return implode(' · ', $labels);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $rule
     */
    private function ruleShortLabel(array $rule): string
    {
        $target = (int) ($rule['target_value'] ?? 0);
        $scope = (string) ($rule['scope'] ?? 'per_territory');
        $from = (int) ($rule['month_from'] ?? 1);
        $to = (int) ($rule['month_to'] ?? 12);
        $range = $from === $to
            ? $this->monthShortLabel($from)
            : $this->monthShortLabel($from) . '-' . $this->monthShortLabel($to);

        if ($scope === 'global_monthly') {
            return 'Meta global ' . $target . '/mes (' . $range . ')';
        }

        return 'Meta ' . $target . '/mes (' . $range . ')';
    }

    /**
     * @param list<array<string, mixed>> $prepared
     * @param list<int> $monthRange
     * @return list<array<string, mixed>>
     */
    private function buildGlobalTargets(array $prepared, int $year, array $monthRange): array
    {
        $summaries = [];
        $roleKeys = [];

        foreach ($this->metaRules as $rule) {
            if ((string) ($rule['scope'] ?? '') === 'global_monthly') {
                $roleKeys[(string) ($rule['role_key'] ?? '')] = true;
            }
        }

        foreach (array_keys($roleKeys) as $roleKey) {
            $months = [];
            foreach ($monthRange as $month) {
                $rule = $this->resolveRuleForRoleMonth($roleKey, $year, $month);
                if ($rule === null || (string) ($rule['scope'] ?? '') !== 'global_monthly') {
                    continue;
                }

                $count = $this->metaCountByRoleInMonth($prepared, $roleKey, $year, $month);
                $target = (int) ($rule['target_value'] ?? 0);
                $months[] = [
                    'month' => $month,
                    'label' => $this->monthShortLabel($month),
                    'count' => $count,
                    'target' => $target,
                    'saldo' => $count - $target,
                    'tier' => $count >= $target ? 'ok' : ($count > 0 ? 'warn' : 'bad'),
                ];
            }

            if ($months === []) {
                continue;
            }

            $summaries[] = [
                'role_key' => $roleKey,
                'role_label' => $this->roleLabel($roleKey),
                'scope' => 'global_monthly',
                'title' => 'Meta global mensual de ' . $this->roleLabel($roleKey),
                'description' => 'Esta meta no se mide por municipio. Se calcula entre todos los registros del rol en el mes.',
                'months' => $months,
            ];
        }

        return $summaries;
    }

    /**
     * @param list<array<string, mixed>> $prepared
     */
    private function metaCountByRoleInMonth(array $prepared, string $roleKey, int $year, int $month): int
    {
        $count = 0;
        foreach ($prepared as $r) {
            if (!$r['is_meta']) {
                continue;
            }
            $currentRole = $this->stripSpanishAccents($this->normalizeRoleToken((string) ($r['professional_role'] ?? '')));
            if ($currentRole !== $roleKey) {
                continue;
            }
            $d = (string) ($r['activity_date'] ?? '');
            if ($d === '' || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $mch)) {
                continue;
            }
            if ((int) $mch[1] !== $year || (int) $mch[2] !== $month) {
                continue;
            }
            $count++;
        }

        return $count;
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
