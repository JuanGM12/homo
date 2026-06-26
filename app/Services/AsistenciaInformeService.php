<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AsistenciaRepository;
use PhpOffice\PhpWord\TemplateProcessor;

final class AsistenciaInformeService
{
    private const TYPE_ASESORIA = 'Asesoría';
    private const TYPE_ASISTENCIA_TECNICA = 'Asistencia técnica';
    private const TYPE_ACTIVIDAD = 'Actividad';

    /** @var list<string> */
    private const AOAT_TOPIC_FIELDS = [
        'prev_suicidio',
        'prev_violencias',
        'prev_adicciones',
        'salud_mental',
        'mesa_salud_mental',
        'ppmsmypa',
        'safer',
        'temas_hospital',
        'actividad_social',
    ];

    /** @var array<string, string> */
    private const AOAT_TOPIC_LABELS = [
        'prev_suicidio' => 'Suicidio',
        'prev_violencias' => 'Violencias',
        'prev_adicciones' => 'Adicciones',
        'salud_mental' => 'Salud Mental',
        'mesa_salud_mental' => 'Mesa salud mental',
        'ppmsmypa' => 'PPMSMYPA',
        'safer' => 'SAFER',
        'temas_hospital' => 'Temas hospital',
        'actividad_social' => 'Actividad social',
    ];

    private AsistenciaRepository $repo;

    public function __construct(?AsistenciaRepository $repo = null)
    {
        $this->repo = $repo ?? new AsistenciaRepository();
    }

    /**
     * @param list<array<string, mixed>> $aoatRecords
     * @param list<array<string, mixed>> $asistenciaActivities
     * @param array<string, mixed> $user
     */
    public function buildDocx(
        array $aoatRecords,
        array $asistenciaActivities,
        array $user,
        string $subregion,
        string $municipality,
        string $fromDate,
        string $toDate,
        bool $isConsolidadoAdmin,
    ): string {
        $stats = $this->computeStats($aoatRecords, $asistenciaActivities);
        $now = new \DateTimeImmutable('now', new \DateTimeZone('America/Bogota'));

        $elaborado = $this->resolveElaborado($user, $isConsolidadoAdmin);

        $templatePath = dirname(__DIR__, 2) . '/storage/templates/informe_final_gestion.docx';
        if (!is_file($templatePath)) {
            throw new \RuntimeException('Plantilla de informe no encontrada.');
        }

        $processor = new TemplateProcessor($templatePath);
        $processor->setValue('SUBREGION', $subregion);
        $processor->setValue('MUNICIPIO', $municipality);
        $processor->setValue('TOTAL_ASESORIAS', (string) $stats['total_asesorias']);
        $processor->setValue('TOTAL_ASISTENCIAS', (string) $stats['total_asistencias']);
        $processor->setValue('TEMATICAS_AOAT', $this->formatBulletList($stats['tematicas_aoat']));
        $processor->setValue('NUM_PERSONAS', (string) $stats['poblacion_impactada']);
        $processor->setValue('TOTAL_REGISTROS_ASISTENCIA', (string) $stats['total_registros_asistencia']);
        $processor->setValue('CARGOS_IMPACTO', $this->formatCargoList($stats['by_cargo']));
        $processor->setValue('TEMATICAS_IMPACTO', $this->formatBulletList($stats['tematicas_impacto']));
        $processor->setValue('RESUMEN_EJECUTIVO_HINT', $this->buildResumenHint($municipality, $fromDate, $toDate));
        $processor->setValue('ELABORADO_NOMBRE', $elaborado['nombre']);
        $processor->setValue('ELABORADO_CARGO', $elaborado['cargo']);
        $processor->setValue('ELABORADO_DOCUMENTO', $elaborado['documento']);
        $processor->setValue(
            'FECHA_GENERACION',
            'Generado el ' . $now->format('d/m/Y') . ' a las ' . $now->format('H:i') . ' (hora Colombia).'
        );
        $processor->setValue(
            'NOTA_PLATAFORMA',
            $this->buildNotaPlataforma($fromDate, $toDate, $stats)
        );
        $processor->setValue('DESGLOSE_METRICAS', $this->buildDesgloseLine($stats));
        $processor->setValue('MARCO_NORMATIVO', self::buildMarcoNormativoText());

        $tmpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'informe_' . bin2hex(random_bytes(12)) . '.docx';
        try {
            $processor->saveAs($tmpPath);
            $content = file_get_contents($tmpPath);

            return $content !== false ? $content : '';
        } finally {
            @unlink($tmpPath);
        }
    }

    /**
     * @param list<array<string, mixed>> $aoatRecords
     * @param list<array<string, mixed>> $asistenciaActivities
     * @param array<string, mixed> $filtrosAplicados
     * @return array<string, mixed>
     */
    public function buildPreviewPayload(
        array $aoatRecords,
        array $asistenciaActivities,
        array $filtrosAplicados,
    ): array {
        $stats = $this->computeStats($aoatRecords, $asistenciaActivities);
        $activeTab = (string) ($filtrosAplicados['tab'] ?? 'aoat');
        $asistenciaLookups = $this->buildAsistenciaLookups($asistenciaActivities);
        $aoatLookups = $this->buildAoatLookups($aoatRecords);

        $registrosAoat = [];
        foreach ($aoatRecords as $aoat) {
            $linked = $this->findLinkedAsistencia($aoat, $asistenciaLookups);
            $actividadId = $linked !== null ? (int) ($linked['id'] ?? 0) : 0;
            $payload = is_array($aoat['payload_decoded'] ?? null) ? $aoat['payload_decoded'] : [];
            $activityType = trim((string) ($aoat['activity_type'] ?? $payload['activity_type'] ?? ''));

            $registrosAoat[] = [
                'fuente' => 'aoat',
                'code' => trim((string) ($aoat['aoat_number'] ?? $payload['aoat_number'] ?? '')),
                'fecha' => (string) ($aoat['activity_date'] ?? ''),
                'tipo' => $activityType,
                'tematica' => $this->formatAoatTematicaLine($payload),
                'cualificaciones' => $this->extractTopicsFromAoatPayload($payload),
                'asistentes' => $actividadId > 0 ? $this->repo->countAsistentesByActividad($actividadId) : 0,
                'estado' => (string) ($aoat['state'] ?? ''),
                'asesor' => trim((string) ($aoat['professional_display'] ?? '')),
                'listado_vinculado' => $linked !== null ? (string) ($linked['code'] ?? '') : '',
            ];
        }

        $listadosAsistencia = [];
        foreach ($asistenciaActivities as $activity) {
            $actividadId = (int) ($activity['id'] ?? 0);
            $linkedAoat = $this->findLinkedAoatForAsistencia($activity, $aoatLookups);
            $aoatPayload = $linkedAoat !== null && is_array($linkedAoat['payload_decoded'] ?? null)
                ? $linkedAoat['payload_decoded']
                : [];

            $listadosAsistencia[] = [
                'fuente' => 'asistencia',
                'code' => (string) ($activity['code'] ?? ''),
                'fecha' => (string) ($activity['activity_date'] ?? ''),
                'lugar' => (string) ($activity['lugar'] ?? ''),
                'tipo' => (string) ($activity['tipo'] ?? 'aoat'),
                'tematica' => $this->formatListadoTematicaLine($activity, $aoatPayload),
                'asistentes' => $actividadId > 0 ? $this->repo->countAsistentesByActividad($actividadId) : 0,
                'estado' => (string) ($activity['status'] ?? ''),
                'asesor' => (string) ($activity['advisor_name'] ?? ''),
                'aoat_vinculado' => $linkedAoat !== null
                    ? trim((string) ($linkedAoat['aoat_number'] ?? $aoatPayload['aoat_number'] ?? ''))
                    : '',
            ];
        }

        $tabCount = $activeTab === 'actividad'
            ? $stats['total_asistencias'] + $stats['total_actividades']
            : $stats['total_asesorias'];

        return [
            'filtros_aplicados' => $filtrosAplicados,
            'tab_activa' => $activeTab,
            'totales' => [
                'registros_aoat' => count($aoatRecords),
                'listados_asistencia' => count($asistenciaActivities),
                'listados_aoat' => $stats['total_asesorias'],
                'listados_actividad' => $stats['total_asistencias'] + $stats['total_actividades'],
                'asesorias_aoat' => $stats['total_asesorias'],
                'asistencias_tecnicas_aoat' => $stats['total_asistencias'],
                'actividades_aoat' => $stats['total_actividades'],
                'registros_asistencia' => $stats['total_registros_asistencia'],
                'poblacion_impactada' => $stats['poblacion_impactada'],
                'personas_unicas' => $stats['unique_persons'],
                'listados_tab_activa' => $tabCount,
            ],
            'tematicas_aoat' => $stats['tematicas_aoat'],
            'tematicas_impacto' => $stats['tematicas_impacto'],
            'cargos' => $stats['by_cargo'],
            'registros_aoat' => $registrosAoat,
            'listados_asistencia' => $listadosAsistencia,
            'actividades' => $registrosAoat,
            'desglose' => $this->buildDesgloseLine($stats),
        ];
    }

    /**
     * @param list<array<string, mixed>> $aoatRecords
     * @param list<array<string, mixed>> $asistenciaActivities
     * @return array{
     *     total_asesorias: int,
     *     total_asistencias: int,
     *     total_actividades: int,
     *     total_registros_asistencia: int,
     *     tematicas_aoat: list<string>,
     *     tematicas_impacto: list<string>,
     *     poblacion_impactada: int,
     *     unique_persons: int,
     *     by_cargo: array<string, int>
     * }
     */
    public function computeStats(array $aoatRecords, array $asistenciaActivities): array
    {
        $totalAsesorias = 0;
        $totalAsistencias = 0;
        $totalActividades = 0;

        foreach ($aoatRecords as $aoat) {
            $payload = is_array($aoat['payload_decoded'] ?? null) ? $aoat['payload_decoded'] : [];
            $activityType = trim((string) ($aoat['activity_type'] ?? $payload['activity_type'] ?? ''));

            if ($activityType === self::TYPE_ASESORIA) {
                ++$totalAsesorias;
            } elseif ($activityType === self::TYPE_ASISTENCIA_TECNICA) {
                ++$totalAsistencias;
            } elseif ($activityType === self::TYPE_ACTIVIDAD) {
                ++$totalActividades;
            }
        }

        $tematicasAoat = $this->collectTematicasAoatOrdered($aoatRecords, $asistenciaActivities);
        $tematicasImpacto = $this->collectTematicasImpactoOrdered($aoatRecords, $asistenciaActivities, $tematicasAoat);

        $actividadIds = array_values(array_filter(
            array_map(static fn (array $activity): int => (int) ($activity['id'] ?? 0), $asistenciaActivities),
            static fn (int $id): bool => $id > 0
        ));

        $agg = $this->repo->aggregateAsistentesForActivities($actividadIds);

        return [
            'total_asesorias' => $totalAsesorias,
            'total_asistencias' => $totalAsistencias,
            'total_actividades' => $totalActividades,
            'total_registros_asistencia' => $agg['total_registros'],
            'tematicas_aoat' => $tematicasAoat,
            'tematicas_impacto' => $tematicasImpacto,
            'poblacion_impactada' => $agg['total_registros'],
            'unique_persons' => $agg['unique_persons'],
            'by_cargo' => $agg['by_cargo'],
        ];
    }

    /**
     * Temáticas generales AoAT: cualificaciones del registro AoAT en orden cronológico del listado.
     *
     * @param list<array<string, mixed>> $aoatRecords
     * @param list<array<string, mixed>> $asistenciaActivities
     * @return list<string>
     */
    private function collectTematicasAoatOrdered(array $aoatRecords, array $asistenciaActivities): array
    {
        $ordered = [];
        $seen = [];
        $aoatLookups = $this->buildAoatLookups($aoatRecords);
        $usedAoatIds = [];

        foreach ($asistenciaActivities as $activity) {
            $linkedAoat = $this->findLinkedAoatForAsistencia($activity, $aoatLookups);
            if ($linkedAoat !== null) {
                $usedAoatIds[(int) ($linkedAoat['id'] ?? 0)] = true;
                $payload = is_array($linkedAoat['payload_decoded'] ?? null) ? $linkedAoat['payload_decoded'] : [];
                $this->appendUniqueTopics($ordered, $seen, $this->extractQualificationTopics($payload));
            } else {
                $this->appendUniqueTopics($ordered, $seen, $this->extractListadoTipos($activity));
            }
        }

        foreach ($aoatRecords as $aoat) {
            $aoatId = (int) ($aoat['id'] ?? 0);
            if ($aoatId > 0 && isset($usedAoatIds[$aoatId])) {
                continue;
            }
            $payload = is_array($aoat['payload_decoded'] ?? null) ? $aoat['payload_decoded'] : [];
            $this->appendUniqueTopics($ordered, $seen, $this->extractQualificationTopics($payload));
        }

        return $ordered;
    }

    /**
     * @param list<array<string, mixed>> $aoatRecords
     * @param list<array<string, mixed>> $asistenciaActivities
     * @param list<string> $tematicasAoat
     * @return list<string>
     */
    private function collectTematicasImpactoOrdered(
        array $aoatRecords,
        array $asistenciaActivities,
        array $tematicasAoat,
    ): array {
        $ordered = $tematicasAoat;
        $seen = array_fill_keys($tematicasAoat, true);

        foreach ($asistenciaActivities as $activity) {
            $this->appendUniqueTopics($ordered, $seen, $this->extractListadoTipos($activity));
        }

        foreach ($aoatRecords as $aoat) {
            $payload = is_array($aoat['payload_decoded'] ?? null) ? $aoat['payload_decoded'] : [];
            $proyecto = trim((string) ($payload['proyecto'] ?? ''));
            if ($proyecto !== '' && strcasecmp($proyecto, 'No aplica') !== 0) {
                $this->appendUniqueTopics($ordered, $seen, ['Proyecto: ' . $proyecto]);
            }
        }

        return $ordered;
    }

    /**
     * @param list<string> $ordered
     * @param array<string, true> $seen
     * @param list<string> $topics
     */
    private function appendUniqueTopics(array &$ordered, array &$seen, array $topics): void
    {
        foreach ($topics as $topic) {
            $topic = trim($topic);
            if ($topic === '' || isset($seen[$topic])) {
                continue;
            }
            $seen[$topic] = true;
            $ordered[] = $topic;
        }
    }

    /**
     * @param array<string, mixed> $activity
     * @return list<string>
     */
    private function extractListadoTipos(array $activity): array
    {
        $tipos = $activity['actividad_tipos'] ?? [];
        if (!is_array($tipos)) {
            return [];
        }

        $result = [];
        foreach ($tipos as $tema) {
            $tema = trim((string) $tema);
            if ($tema !== '') {
                $result[] = $tema;
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $payload
     * @return list<string>
     */
    private function extractQualificationTopics(array $payload): array
    {
        $topics = $this->extractTopicsFromAoatPayload($payload);
        if ($topics !== []) {
            return $topics;
        }

        $proyecto = trim((string) ($payload['proyecto'] ?? ''));
        if ($proyecto !== '' && strcasecmp($proyecto, 'No aplica') !== 0) {
            return ['Proyecto: ' . $proyecto];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $activity
     * @param array<string, mixed> $aoatPayload
     */
    private function formatListadoTematicaLine(array $activity, array $aoatPayload): string
    {
        $qualifications = $this->extractQualificationTopics($aoatPayload);
        if ($qualifications !== []) {
            return implode('; ', $qualifications);
        }

        $tipos = $this->extractListadoTipos($activity);

        return $tipos !== [] ? implode('; ', $tipos) : '';
    }

    /**
     * @param list<array<string, mixed>> $aoatRecords
     * @return array{by_code: array<string, array<string, mixed>>, by_composite: array<string, list<array<string, mixed>>>}
     */
    private function buildAoatLookups(array $aoatRecords): array
    {
        $byCode = [];
        $byComposite = [];

        foreach ($aoatRecords as $aoat) {
            $payload = is_array($aoat['payload_decoded'] ?? null) ? $aoat['payload_decoded'] : [];
            $number = trim((string) ($aoat['aoat_number'] ?? $payload['aoat_number'] ?? ''));
            if ($number !== '' && $number !== '0') {
                $byCode[$number] = $aoat;
            }

            $key = $this->compositeKey(
                (int) ($aoat['user_id'] ?? 0),
                trim((string) ($aoat['municipality'] ?? '')),
                (string) ($aoat['activity_date'] ?? '')
            );
            if (!isset($byComposite[$key])) {
                $byComposite[$key] = [];
            }
            $byComposite[$key][] = $aoat;
        }

        return ['by_code' => $byCode, 'by_composite' => $byComposite];
    }

    /**
     * @param array<string, mixed> $activity
     * @param array{by_code: array<string, array<string, mixed>>, by_composite: array<string, list<array<string, mixed>>>} $aoatLookups
     * @return array<string, mixed>|null
     */
    private function findLinkedAoatForAsistencia(array $activity, array $aoatLookups): ?array
    {
        $code = trim((string) ($activity['code'] ?? ''));
        if ($code !== '' && isset($aoatLookups['by_code'][$code])) {
            return $aoatLookups['by_code'][$code];
        }

        $key = $this->compositeKey(
            (int) ($activity['advisor_user_id'] ?? 0),
            trim((string) ($activity['municipality'] ?? '')),
            substr((string) ($activity['activity_date'] ?? ''), 0, 10)
        );
        $candidates = $aoatLookups['by_composite'][$key] ?? [];

        return $candidates[0] ?? null;
    }

    /**
     * @param list<array<string, mixed>> $asistenciaActivities
     * @return array{by_code: array<string, array<string, mixed>>, by_composite: array<string, list<array<string, mixed>>>}
     */
    private function buildAsistenciaLookups(array $asistenciaActivities): array
    {
        $byCode = [];
        $byComposite = [];

        foreach ($asistenciaActivities as $activity) {
            $code = trim((string) ($activity['code'] ?? ''));
            if ($code !== '') {
                $byCode[$code] = $activity;
            }

            $key = $this->compositeKey(
                (int) ($activity['advisor_user_id'] ?? 0),
                trim((string) ($activity['municipality'] ?? '')),
                substr((string) ($activity['activity_date'] ?? ''), 0, 10)
            );
            if (!isset($byComposite[$key])) {
                $byComposite[$key] = [];
            }
            $byComposite[$key][] = $activity;
        }

        return ['by_code' => $byCode, 'by_composite' => $byComposite];
    }

    /**
     * @param array<string, mixed> $aoat
     * @param array{by_code: array<string, array<string, mixed>>, by_composite: array<string, list<array<string, mixed>>>} $lookups
     * @return array<string, mixed>|null
     */
    private function findLinkedAsistencia(array $aoat, array $lookups): ?array
    {
        $payload = is_array($aoat['payload_decoded'] ?? null) ? $aoat['payload_decoded'] : [];
        $number = trim((string) ($aoat['aoat_number'] ?? $payload['aoat_number'] ?? ''));
        if ($number !== '' && $number !== '0' && isset($lookups['by_code'][$number])) {
            return $lookups['by_code'][$number];
        }

        $key = $this->compositeKey(
            (int) ($aoat['user_id'] ?? 0),
            trim((string) ($aoat['municipality'] ?? '')),
            (string) ($aoat['activity_date'] ?? '')
        );
        $candidates = $lookups['by_composite'][$key] ?? [];

        return $candidates[0] ?? null;
    }

    private function compositeKey(int $userId, string $municipality, string $activityDate): string
    {
        return $userId . '|' . mb_strtoupper($municipality, 'UTF-8') . '|' . $activityDate;
    }

    /**
     * @param array<string, mixed> $payload
     * @return list<string>
     */
    private function extractTopicsFromAoatPayload(array $payload): array
    {
        $topics = [];

        foreach (self::AOAT_TOPIC_FIELDS as $field) {
            $label = self::AOAT_TOPIC_LABELS[$field] ?? $field;
            $raw = $payload[$field] ?? null;
            if (!is_array($raw)) {
                continue;
            }
            foreach ($raw as $item) {
                $item = trim((string) $item);
                if ($item === '' || strcasecmp($item, 'No aplica') === 0) {
                    continue;
                }
                $topics[] = $label . ' - ' . $item;
            }
        }

        return $topics;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function formatAoatTematicaLine(array $payload): string
    {
        $topics = $this->extractTopicsFromAoatPayload($payload);
        if ($topics !== []) {
            return implode('; ', $topics);
        }

        $proyecto = trim((string) ($payload['proyecto'] ?? ''));
        if ($proyecto !== '' && strcasecmp($proyecto, 'No aplica') !== 0) {
            return 'Proyecto: ' . $proyecto;
        }

        $actividad = trim((string) ($payload['activity_performed'] ?? $payload['actividad_que_realizo'] ?? ''));
        if ($actividad !== '') {
            return $actividad;
        }

        return '';
    }

    /**
     * @param array<string, mixed> $user
     * @return array{nombre: string, cargo: string, documento: string}
     */
    private function resolveElaborado(array $user, bool $isConsolidadoAdmin): array
    {
        if ($isConsolidadoAdmin) {
            return [
                'nombre' => 'Consolidado — Plataforma',
                'cargo' => 'Coordinación / Administración',
                'documento' => trim((string) ($user['document_number'] ?? '')),
            ];
        }

        $nombre = trim((string) ($user['name'] ?? ''));
        if ($nombre === '') {
            $nombre = trim(
                (string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? '')
            );
        }

        return [
            'nombre' => $nombre !== '' ? $nombre : 'Profesional',
            'cargo' => self::roleLabelFromUser($user),
            'documento' => trim((string) ($user['document_number'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $user
     */
    public static function roleLabelFromUser(array $user): string
    {
        $roles = array_map('strtolower', $user['roles'] ?? []);
        $primary = strtolower(trim((string) ($user['role'] ?? '')));

        if (in_array('psicologo', $roles, true) || $primary === 'psicologo') {
            return 'Psicólogo';
        }
        if (in_array('medico', $roles, true) || $primary === 'medico') {
            return 'Médico';
        }
        if (in_array('abogado', $roles, true) || $primary === 'abogado') {
            return 'Abogado';
        }
        if (
            in_array('profesional social', $roles, true)
            || in_array('profesional_social', $roles, true)
            || in_array('trabajador social', $roles, true)
            || $primary === 'profesional social'
            || $primary === 'profesional_social'
            || $primary === 'trabajador social'
        ) {
            return 'Profesional social';
        }
        if (in_array('admin', $roles, true) || in_array('coordinador', $roles, true) || in_array('coordinadora', $roles, true)) {
            return 'Coordinación';
        }

        return 'Profesional';
    }

    /**
     * @param list<string> $items
     */
    private function formatBulletList(array $items): string
    {
        if ($items === []) {
            return '';
        }

        return implode("\n", array_map(static fn (string $item): string => '• ' . $item, $items));
    }

    /**
     * @param array<string, int> $byCargo
     */
    private function formatCargoList(array $byCargo): string
    {
        if ($byCargo === []) {
            return '';
        }

        $lines = [];
        foreach ($byCargo as $cargo => $count) {
            $lines[] = $cargo . ' (' . $count . ')';
        }

        return implode("\n", $lines);
    }

    private function buildResumenHint(string $municipality, string $fromDate, string $toDate): string
    {
        return 'Resumen de gestión del municipio de '
            . $municipality
            . ' correspondiente al período '
            . $fromDate
            . ' — '
            . $toDate
            . '.';
    }

    /**
     * @param array{
     *     total_asesorias: int,
     *     total_asistencias: int,
     *     total_actividades?: int,
     *     total_registros_asistencia: int,
     *     poblacion_impactada: int,
     *     unique_persons: int
     * } $stats
     */
    private function buildDesgloseLine(array $stats): string
    {
        $actividades = (int) ($stats['total_actividades'] ?? 0);

        return 'Registros AoAT — Asesorías: '
            . $stats['total_asesorias']
            . ' · Asist. técnicas: '
            . $stats['total_asistencias']
            . ($actividades > 0 ? ' · Actividades: ' . $actividades : '')
            . ' · Población impactada: '
            . ($stats['poblacion_impactada'] ?? $stats['total_registros_asistencia'])
            . ' · Personas únicas: '
            . $stats['unique_persons'];
    }

    /**
     * @param array{
     *     total_asesorias: int,
     *     total_asistencias: int,
     *     total_actividades?: int,
     *     total_registros_asistencia: int,
     *     poblacion_impactada: int,
     *     unique_persons: int
     * } $stats
     */
    private function buildNotaPlataforma(string $fromDate, string $toDate, array $stats): string
    {
        return 'Datos generados automáticamente desde la Plataforma Acción en Territorio. '
            . 'Período de datos: ' . $fromDate . ' a ' . $toDate . '. '
            . $this->buildDesgloseLine($stats) . '. '
            . 'Los campos resaltados en color azul deben ser diligenciados por el profesional. '
            . 'Nota: las asesorías, asistencias técnicas y temáticas generales provienen del Registro AoAT; '
            . 'la población impactada y los cargos del Listado de asistencia en el mismo alcance territorial y fechas. '
            . '«Población impactada» es la suma de asistentes registrados; «Personas únicas» cuenta documentos distintos.';
    }

    public static function buildMarcoNormativoText(): string
    {
        $items = [
            'Ley 1616 de 2013 (Salud Mental).',
            'Ley 1566 de 2012.',
            'Ley 2460 Ley de Salud Mental.',
            'Ley 729 de 2001.',
            'Ley 2518 de 2025.',
            'Resolución 518 de 2015.',
            'Resolución 3280 de 2018.',
            'Resolución 347 de 2026.',
            'Resolución 2100 de 2025.',
            'Plan Decenal de Salud Pública vigente.',
            'Ordenanza 041 de 2022 (Política Pública Departamental de Salud Mental y Prevención de las Adicciones).',
        ];

        return implode("\n", $items);
    }
}
