<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AsistenciaRepository;
use PhpOffice\PhpWord\TemplateProcessor;

final class AsistenciaInformeService
{
    private AsistenciaRepository $repo;

    public function __construct(?AsistenciaRepository $repo = null)
    {
        $this->repo = $repo ?? new AsistenciaRepository();
    }

    /**
     * @param list<array<string, mixed>> $activities
     * @param array<string, mixed> $user
     */
    public function buildDocx(
        array $activities,
        array $user,
        string $subregion,
        string $municipality,
        string $fromDate,
        string $toDate,
        bool $isConsolidadoAdmin,
    ): string {
        $stats = $this->computeStats($activities);
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
        $processor->setValue('NUM_PERSONAS', (string) $stats['unique_persons']);
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
     * @param list<array<string, mixed>> $activities
     * @param array<string, mixed> $filtrosAplicados
     * @return array<string, mixed>
     */
    public function buildPreviewPayload(array $activities, array $filtrosAplicados): array
    {
        $stats = $this->computeStats($activities);
        $activeTab = (string) ($filtrosAplicados['tab'] ?? 'aoat');

        $actividadesDetalle = [];
        foreach ($activities as $activity) {
            $tipos = $activity['actividad_tipos'] ?? [];
            $tematica = '';
            if (is_array($tipos) && $tipos !== []) {
                $tematica = implode('; ', array_map(static fn (mixed $t): string => trim((string) $t), $tipos));
            }
            $actividadId = (int) ($activity['id'] ?? 0);
            $actividadesDetalle[] = [
                'code' => (string) ($activity['code'] ?? ''),
                'fecha' => (string) ($activity['activity_date'] ?? ''),
                'tipo' => (string) ($activity['tipo'] ?? 'aoat'),
                'tematica' => $tematica,
                'asistentes' => $actividadId > 0 ? $this->repo->countAsistentesByActividad($actividadId) : 0,
                'estado' => (string) ($activity['status'] ?? ''),
                'asesor' => (string) ($activity['advisor_name'] ?? ''),
            ];
        }

        return [
            'filtros_aplicados' => $filtrosAplicados,
            'tab_activa' => $activeTab,
            'totales' => [
                'listados_aoat' => $stats['total_asesorias'],
                'listados_actividad' => $stats['total_asistencias'],
                'registros_asistencia' => $stats['total_registros_asistencia'],
                'personas_unicas' => $stats['unique_persons'],
                'listados_tab_activa' => $activeTab === 'actividad'
                    ? $stats['total_asistencias']
                    : $stats['total_asesorias'],
            ],
            'tematicas_aoat' => $stats['tematicas_aoat'],
            'tematicas_impacto' => $stats['tematicas_impacto'],
            'cargos' => $stats['by_cargo'],
            'actividades' => $actividadesDetalle,
            'desglose' => $this->buildDesgloseLine($stats),
        ];
    }

    /**
     * @param list<array<string, mixed>> $activities
     * @return array{
     *     total_asesorias: int,
     *     total_asistencias: int,
     *     total_registros_asistencia: int,
     *     tematicas_aoat: list<string>,
     *     tematicas_impacto: list<string>,
     *     unique_persons: int,
     *     by_cargo: array<string, int>
     * }
     */
    public function computeStats(array $activities): array
    {
        $totalAsesorias = 0;
        $totalAsistencias = 0;
        $tematicasAoat = [];
        $tematicasImpacto = [];
        $actividadIds = [];

        foreach ($activities as $activity) {
            $actividadIds[] = (int) ($activity['id'] ?? 0);
            $tipo = strtolower(trim((string) ($activity['tipo'] ?? 'aoat')));
            if ($tipo === 'actividad') {
                ++$totalAsistencias;
            } else {
                ++$totalAsesorias;
            }

            $tipos = $activity['actividad_tipos'] ?? [];
            if (!is_array($tipos)) {
                $tipos = [];
            }
            foreach ($tipos as $tema) {
                $tema = trim((string) $tema);
                if ($tema === '') {
                    continue;
                }
                $tematicasImpacto[$tema] = true;
                if ($tipo !== 'actividad') {
                    $tematicasAoat[$tema] = true;
                }
            }
        }

        $agg = $this->repo->aggregateAsistentesForActivities($actividadIds);
        $tematicasAoatList = array_keys($tematicasAoat);
        $tematicasImpactoList = array_keys($tematicasImpacto);
        sort($tematicasAoatList, SORT_NATURAL | SORT_FLAG_CASE);
        sort($tematicasImpactoList, SORT_NATURAL | SORT_FLAG_CASE);

        return [
            'total_asesorias' => $totalAsesorias,
            'total_asistencias' => $totalAsistencias,
            'total_registros_asistencia' => $agg['total_registros'],
            'tematicas_aoat' => $tematicasAoatList,
            'tematicas_impacto' => $tematicasImpactoList,
            'unique_persons' => $agg['unique_persons'],
            'by_cargo' => $agg['by_cargo'],
        ];
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
     *     total_registros_asistencia: int,
     *     unique_persons: int
     * } $stats
     */
    private function buildDesgloseLine(array $stats): string
    {
        return 'Listados AoAT: '
            . $stats['total_asesorias']
            . ' · Asistencias técnicas: '
            . $stats['total_asistencias']
            . ' · Registros de asistencia: '
            . $stats['total_registros_asistencia']
            . ' · Personas únicas: '
            . $stats['unique_persons'];
    }

    /**
     * @param array{
     *     total_asesorias: int,
     *     total_asistencias: int,
     *     total_registros_asistencia: int,
     *     unique_persons: int
     * } $stats
     */
    private function buildNotaPlataforma(string $fromDate, string $toDate, array $stats): string
    {
        return 'Datos generados automáticamente desde la Plataforma Acción en Territorio. '
            . 'Período de datos: ' . $fromDate . ' a ' . $toDate . '. '
            . $this->buildDesgloseLine($stats) . '. '
            . 'Los campos resaltados en color azul deben ser diligenciados por el profesional. '
            . 'Nota: «Personas únicas» cuenta documentos distintos; «Registros de asistencia» es la suma de la columna Asistentes del listado.';
    }
}
