<?php

declare(strict_types=1);

namespace App\Services;

use App\Controllers\AsistenciaController;
use App\Repositories\AoatRepository;
use App\Repositories\AsistenciaRepository;
use App\Repositories\UserRepository;

final class BoletinService
{
    public const PROGRAM_NAME = 'Equipo de Promoción y Prevención';
    public const PROGRAM_SHORT = 'Acción en Territorio';

    /** @var array<string, string> */
    public const ROLE_OPTIONS = [
        'medico' => 'Médico',
        'psicologo' => 'Psicólogo',
        'politologo' => 'Politólogo',
        'abogado' => 'Abogado',
        'profesional social' => 'Profesional social',
    ];

    /** @var array<string, string> */
    public const ACTIVITY_TYPE_OPTIONS = [
        'Asesoría' => 'Asesoría',
        'Asistencia técnica' => 'Asistencia técnica (AT)',
        'Actividad' => 'Actividad',
    ];

    /** @var array<string, string> */
    public const TOPIC_OPTIONS = [
        'prev_suicidio' => 'Prevención del suicidio',
        'prev_violencias' => 'Prevención de violencias',
        'prev_adicciones' => 'Prevención de adicciones',
        'salud_mental' => 'Salud mental',
        'politica_publica_psicologo' => 'Política pública (psicólogo)',
        'mesa_salud_mental' => 'Mesa de salud mental',
        'ppmsmypa' => 'PPMSMYPA',
        'safer' => 'SAFER',
        'temas_hospital' => 'Temas hospital',
        'actividad_social' => 'Actividad social',
    ];

    /** @var list<array{key:string,label:string,from:int,to:?int}> */
    private const AGE_BANDS = [
        ['key' => '0_5', 'label' => '0 a 5', 'from' => 0, 'to' => 5],
        ['key' => '6_11', 'label' => '6 a 11', 'from' => 6, 'to' => 11],
        ['key' => '12_17', 'label' => '12 a 17', 'from' => 12, 'to' => 17],
        ['key' => '18_28', 'label' => '18 a 28', 'from' => 18, 'to' => 28],
        ['key' => '29_59', 'label' => '29 a 59', 'from' => 29, 'to' => 59],
        ['key' => '60_mas', 'label' => '60 y más', 'from' => 60, 'to' => null],
    ];

    private AoatRepository $aoatRepo;
    private AsistenciaRepository $asistenciaRepo;
    private UserRepository $userRepo;

    public function __construct(
        ?AoatRepository $aoatRepo = null,
        ?AsistenciaRepository $asistenciaRepo = null,
        ?UserRepository $userRepo = null
    ) {
        $this->aoatRepo = $aoatRepo ?? new AoatRepository();
        $this->asistenciaRepo = $asistenciaRepo ?? new AsistenciaRepository();
        $this->userRepo = $userRepo ?? new UserRepository();
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    public function build(array $filters, array $user): array
    {
        $canViewAll = Auth::canViewAllModuleRecords($user);
        $scopeUserId = $canViewAll ? 0 : (int) ($user['id'] ?? 0);

        $aoatFilters = $this->aoatFilters($filters, $scopeUserId);
        $aoatRecords = $this->aoatRepo->findForInforme($aoatFilters);
        $topicKey = trim((string) ($filters['tema'] ?? ''));
        if ($topicKey !== '') {
            $aoatRecords = array_values(array_filter(
                $aoatRecords,
                fn (array $row): bool => $this->recordHasTopic($row, $topicKey)
            ));
        }

        $asistenciaFilters = $this->asistenciaFilters($filters, $scopeUserId);
        if (!empty($asistenciaFilters['_empty'])) {
            $asistenciaActivities = [];
            $asistentes = [];
        } else {
            unset($asistenciaFilters['_empty']);
            $asistenciaActivities = $this->asistenciaRepo->findWithFilters($asistenciaFilters);
            $actividadIds = array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $asistenciaActivities);
            $asistentes = $this->asistenciaRepo->findAsistentesByActividadIds($actividadIds);
        }

        $kpis = $this->buildAoatKpis($aoatRecords);
        $byRole = $this->countByRole($aoatRecords);
        $territory = $this->buildTerritoryTable($aoatRecords, $asistenciaActivities, $asistentes);
        $beneficiarios = $this->buildBeneficiarios($asistentes);
        $cutoff = $this->resolveCutoff($filters);

        return [
            'program_name' => self::PROGRAM_NAME,
            'program_short' => self::PROGRAM_SHORT,
            'contrato' => AsistenciaController::FIPC_CONTRATO_NUMERO,
            'cutoff_label' => $cutoff,
            'kpis' => $kpis,
            'by_role' => $byRole,
            'territory' => $territory,
            'beneficiarios' => $beneficiarios,
            'professionals' => $this->userRepo->findNonAdminAdvisors(),
            'totals' => [
                'aoat' => count($aoatRecords),
                'listados' => count($asistenciaActivities),
                'beneficiarios' => $beneficiarios['total'],
                'personas_unicas' => $beneficiarios['unicos'],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function aoatFilters(array $filters, int $scopeUserId): array
    {
        $out = [
            'subregion' => trim((string) ($filters['subregion'] ?? '')),
            'municipalities' => is_array($filters['municipalities'] ?? null) ? $filters['municipalities'] : [],
            'from_date' => trim((string) ($filters['from_date'] ?? '')),
            'to_date' => trim((string) ($filters['to_date'] ?? '')),
            'professional_role' => strtolower(trim((string) ($filters['role'] ?? ''))),
        ];

        $activityType = trim((string) ($filters['activity_type'] ?? ''));
        if ($activityType !== '') {
            $out['activity_type'] = $activityType;
        }

        $professionalId = (int) ($filters['professional_id'] ?? 0);
        if ($scopeUserId > 0) {
            $out['user_id'] = $scopeUserId;
        } elseif ($professionalId > 0) {
            $out['user_id'] = $professionalId;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function asistenciaFilters(array $filters, int $scopeUserId): array
    {
        $out = [
            'subregion' => trim((string) ($filters['subregion'] ?? '')),
            'municipalities' => is_array($filters['municipalities'] ?? null) ? $filters['municipalities'] : [],
            'from_date' => trim((string) ($filters['from_date'] ?? '')),
            'to_date' => trim((string) ($filters['to_date'] ?? '')),
        ];

        $professionalId = (int) ($filters['professional_id'] ?? 0);
        $role = strtolower(trim((string) ($filters['role'] ?? '')));
        if ($scopeUserId > 0) {
            $out['advisor_user_id'] = $scopeUserId;
        } elseif ($professionalId > 0) {
            $out['advisor_user_id'] = $professionalId;
        } elseif ($role !== '') {
            $ids = $this->userRepo->findActiveIdsByRole($role);
            if ($ids === []) {
                $out['_empty'] = true;
            } else {
                $out['advisor_user_ids'] = $ids;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function recordHasTopic(array $row, string $topicKey): bool
    {
        if (!isset(self::TOPIC_OPTIONS[$topicKey])) {
            return true;
        }
        $payload = is_array($row['payload_decoded'] ?? null) ? $row['payload_decoded'] : [];
        $raw = $payload[$topicKey] ?? null;
        if (!is_array($raw) || $raw === []) {
            return false;
        }
        foreach ($raw as $item) {
            $item = trim((string) $item);
            if ($item !== '' && strcasecmp($item, 'No aplica') !== 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array<string, mixed>> $aoatRecords
     * @return array<string, int>
     */
    private function buildAoatKpis(array $aoatRecords): array
    {
        $asesoria = 0;
        $at = 0;
        $actividad = 0;
        foreach ($aoatRecords as $row) {
            $type = $this->normalizeActivityType((string) ($row['activity_type'] ?? ''));
            if ($type === 'Asesoría') {
                $asesoria++;
            } elseif ($type === 'Asistencia técnica') {
                $at++;
            } elseif ($type === 'Actividad') {
                $actividad++;
            }
        }

        return [
            'aoat_total' => count($aoatRecords),
            'asesoria' => $asesoria,
            'at' => $at,
            'actividad' => $actividad,
        ];
    }

    /**
     * @param list<array<string, mixed>> $aoatRecords
     * @return list<array{key:string,label:string,value:int}>
     */
    private function countByRole(array $aoatRecords): array
    {
        $counts = [];
        foreach (self::ROLE_OPTIONS as $key => $label) {
            $counts[$key] = ['key' => $key, 'label' => $label, 'value' => 0];
        }
        foreach ($aoatRecords as $row) {
            $role = $this->normalizeRole((string) ($row['professional_role'] ?? ''));
            if ($role === '') {
                $role = 'otro';
            }
            if (!isset($counts[$role])) {
                $label = self::ROLE_OPTIONS[$role] ?? ucwords(str_replace('_', ' ', $role));
                $counts[$role] = ['key' => $role, 'label' => $label !== '' ? $label : 'Otro', 'value' => 0];
            }
            $counts[$role]['value']++;
        }

        return array_values(array_filter($counts, static fn (array $row): bool => $row['value'] > 0 || isset(self::ROLE_OPTIONS[$row['key']])));
    }

    /**
     * @param list<array<string, mixed>> $aoatRecords
     * @param list<array<string, mixed>> $asistenciaActivities
     * @param list<array<string, mixed>> $asistentes
     * @return list<array<string, mixed>>
     */
    private function buildTerritoryTable(array $aoatRecords, array $asistenciaActivities, array $asistentes): array
    {
        $byActivity = [];
        foreach ($asistentes as $asistente) {
            $aid = (int) ($asistente['actividad_id'] ?? 0);
            if ($aid <= 0) {
                continue;
            }
            $byActivity[$aid] = ($byActivity[$aid] ?? 0) + 1;
        }

        $rows = [];
        foreach ($aoatRecords as $row) {
            $key = trim((string) ($row['subregion'] ?? '')) . '|' . trim((string) ($row['municipality'] ?? ''));
            if (!isset($rows[$key])) {
                $rows[$key] = $this->emptyTerritoryRow($row);
            }
            $type = $this->normalizeActivityType((string) ($row['activity_type'] ?? ''));
            $rows[$key]['aoat']++;
            if ($type === 'Asesoría') {
                $rows[$key]['asesoria']++;
            } elseif ($type === 'Asistencia técnica') {
                $rows[$key]['at']++;
            } elseif ($type === 'Actividad') {
                $rows[$key]['actividad']++;
            }
        }

        foreach ($asistenciaActivities as $activity) {
            $key = trim((string) ($activity['subregion'] ?? '')) . '|' . trim((string) ($activity['municipality'] ?? ''));
            if (!isset($rows[$key])) {
                $rows[$key] = $this->emptyTerritoryRow($activity);
            }
            $aid = (int) ($activity['id'] ?? 0);
            $rows[$key]['listados']++;
            $rows[$key]['beneficiarios'] += $byActivity[$aid] ?? 0;
        }

        $list = array_values($rows);
        usort($list, static function (array $a, array $b): int {
            $cmp = strcasecmp((string) $a['subregion'], (string) $b['subregion']);
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcasecmp((string) $a['municipality'], (string) $b['municipality']);
        });

        return $list;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function emptyTerritoryRow(array $row): array
    {
        return [
            'subregion' => trim((string) ($row['subregion'] ?? '')),
            'municipality' => trim((string) ($row['municipality'] ?? '')),
            'aoat' => 0,
            'asesoria' => 0,
            'at' => 0,
            'actividad' => 0,
            'listados' => 0,
            'beneficiarios' => 0,
        ];
    }

    /**
     * @param list<array<string, mixed>> $asistentes
     * @return array<string, mixed>
     */
    private function buildBeneficiarios(array $asistentes): array
    {
        $sex = ['Hombre' => 0, 'Mujer' => 0, 'Intersexual' => 0, 'Sin dato' => 0];
        $etnia = ['Afrodescendiente' => 0, 'Indígena' => 0, 'Otro' => 0, 'Sin dato' => 0];
        $zona = ['Urbana' => 0, 'Rural' => 0, 'Sin dato' => 0];
        $ages = [];
        foreach (self::AGE_BANDS as $band) {
            $ages[$band['key']] = ['key' => $band['key'], 'label' => $band['label'], 'value' => 0];
        }
        $ages['sin_dato'] = ['key' => 'sin_dato', 'label' => 'Sin dato', 'value' => 0];
        $grupos = [];
        $uniqueDocs = [];

        foreach ($asistentes as $row) {
            $doc = trim((string) ($row['document_number'] ?? ''));
            if ($doc !== '') {
                $uniqueDocs[$doc] = true;
            }

            $sexKey = $this->normalizeSex((string) ($row['sex'] ?? ''));
            $sex[$sexKey] = ($sex[$sexKey] ?? 0) + 1;

            $etniaKey = $this->normalizeEtnia((string) ($row['etnia'] ?? ''));
            $etnia[$etniaKey] = ($etnia[$etniaKey] ?? 0) + 1;

            $zonaKey = $this->normalizeZona((string) ($row['zone'] ?? ''));
            $zona[$zonaKey] = ($zona[$zonaKey] ?? 0) + 1;

            $ageRaw = $row['age'] ?? null;
            $age = is_numeric($ageRaw) ? (int) $ageRaw : null;
            $matched = false;
            if ($age !== null && $age >= 0) {
                foreach (self::AGE_BANDS as $band) {
                    $to = $band['to'];
                    if ($age >= $band['from'] && ($to === null || $age <= $to)) {
                        $ages[$band['key']]['value']++;
                        $matched = true;
                        break;
                    }
                }
            }
            if (!$matched) {
                $ages['sin_dato']['value']++;
            }

            $grupo = $row['grupo_poblacional'] ?? [];
            if (!is_array($grupo)) {
                $grupo = [];
            }
            foreach ($grupo as $item) {
                $label = trim((string) $item);
                if ($label === '') {
                    continue;
                }
                $grupos[$label] = ($grupos[$label] ?? 0) + 1;
            }
        }

        $grupoRows = [];
        foreach ($grupos as $label => $value) {
            $grupoRows[] = ['label' => $label, 'value' => $value];
        }
        usort($grupoRows, static fn (array $a, array $b): int => $b['value'] <=> $a['value']);

        return [
            'total' => count($asistentes),
            'unicos' => count($uniqueDocs),
            'sexo' => $this->toNamedSeries($sex),
            'etnia' => $this->toNamedSeries($etnia),
            'zona' => $this->toNamedSeries($zona),
            'edad' => array_values($ages),
            'grupo_poblacional' => $grupoRows,
        ];
    }

    /**
     * @param array<string, int> $map
     * @return list<array{label:string,value:int}>
     */
    private function toNamedSeries(array $map): array
    {
        $rows = [];
        foreach ($map as $label => $value) {
            $rows[] = ['label' => $label, 'value' => $value];
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function resolveCutoff(array $filters): string
    {
        $from = $this->sanitizeIsoDate(trim((string) ($filters['from_date'] ?? '')));
        $to = $this->sanitizeIsoDate(trim((string) ($filters['to_date'] ?? '')));
        if ($from !== '' && $to !== '') {
            return $this->formatDateLabel($from) . ' a ' . $this->formatDateLabel($to);
        }
        if ($to !== '') {
            return $this->formatDateLabel($to);
        }
        if ($from !== '') {
            return 'desde ' . $this->formatDateLabel($from);
        }

        return (new \DateTimeImmutable('now', new \DateTimeZone('America/Bogota')))->format('d/m/Y');
    }

    public static function sanitizeIsoDate(string $iso): string
    {
        $iso = substr(trim($iso), 0, 10);
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $iso);
        if (!$dt instanceof \DateTimeImmutable || $dt->format('Y-m-d') !== $iso) {
            return '';
        }

        $year = (int) $dt->format('Y');
        if ($year < 2020 || $year > 2100) {
            return '';
        }

        return $iso;
    }

    private function formatDateLabel(string $iso): string
    {
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $iso);
        if ($dt instanceof \DateTimeImmutable) {
            return $dt->format('d/m/Y');
        }

        return $iso;
    }

    private function normalizeActivityType(string $type): string
    {
        $type = trim($type);
        $norm = mb_strtolower($type, 'UTF-8');
        if (str_contains($norm, 'asistencia') || $norm === 'at') {
            return 'Asistencia técnica';
        }
        if (str_contains($norm, 'asesor')) {
            return 'Asesoría';
        }
        if (str_contains($norm, 'actividad')) {
            return 'Actividad';
        }

        return $type;
    }

    private function normalizeRole(string $role): string
    {
        $role = strtolower(trim(str_replace('_', ' ', $role)));
        if ($role === 'profesional_social') {
            return 'profesional social';
        }

        return $role;
    }

    private function normalizeSex(string $sex): string
    {
        $sex = trim($sex);
        if ($sex === 'Masculino') {
            return 'Hombre';
        }
        if ($sex === 'Femenino') {
            return 'Mujer';
        }
        if (in_array($sex, ['Hombre', 'Mujer', 'Intersexual'], true)) {
            return $sex;
        }

        return $sex !== '' ? 'Intersexual' : 'Sin dato';
    }

    private function normalizeEtnia(string $etnia): string
    {
        $etnia = trim($etnia);
        if ($etnia === 'Afrodescendiente' || $etnia === 'Indígena' || $etnia === 'Otro') {
            return $etnia;
        }

        return $etnia !== '' ? 'Otro' : 'Sin dato';
    }

    private function normalizeZona(string $zona): string
    {
        $zona = trim($zona);
        $norm = mb_strtolower($zona, 'UTF-8');
        if (str_starts_with($norm, 'urb')) {
            return 'Urbana';
        }
        if (str_starts_with($norm, 'rur')) {
            return 'Rural';
        }

        return $zona !== '' ? $zona : 'Sin dato';
    }
}
