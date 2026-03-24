<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\TestResponseRepository;
use App\Services\Auth;
use App\Services\EvaluacionesReportService;
use App\Services\Flash;
use App\Services\PdfImageHelper;
use Dompdf\Dompdf;
use Dompdf\Options;

final class EvaluacionesController
{
    /** Clave de respuestas correctas: POST - TEST Prevención de Violencias (preguntas 1 a 9) */
    private const POST_VIOLENCIAS_CORRECT = [
        1 => 'B',
        2 => 'C',
        3 => 'C',
        4 => 'C',
        5 => 'A',
        6 => 'D',
        7 => 'C',
        8 => 'C',
        9 => 'C',
    ];

    /** Clave de respuestas correctas: POST - TEST Prevención de Suicidios (preguntas 1 a 12) */
    private const POST_SUICIDIOS_CORRECT = [
        1 => 'B',
        2 => 'D',
        3 => 'B',
        4 => 'C',
        5 => 'C',
        6 => 'B',
        7 => 'B',
        8 => 'C',
        9 => 'C',
        10 => 'E',
        11 => 'D',
        12 => 'C',
    ];

    /** Clave de respuestas correctas: POST - TEST Hospitales (preguntas 1 a 20) */
    private const POST_HOSPITALES_CORRECT = [
        1 => 'C',
        2 => 'B',
        3 => 'C',
        4 => 'B',
        5 => 'C',
        6 => 'B',
        7 => 'B',
        8 => 'C',
        9 => 'B',
        10 => 'B',
        11 => 'B',
        12 => 'B',
        13 => 'B',
        14 => 'E',
        15 => 'A',
        16 => 'D',
        17 => 'A',
        18 => 'B',
        19 => 'C',
        20 => 'D',
    ];

    /** Clave de respuestas correctas: POST - TEST Prevención de Adicciones (preguntas 1 a 9) */
    private const POST_ADICCIONES_CORRECT = [
        1 => 'B',
        2 => 'B',
        3 => 'B',
        4 => 'C',
        5 => 'B',
        6 => 'B',
        7 => 'C',
        8 => 'C',
        9 => 'B',
    ];

    /** Clave de respuestas correctas: PRE - TEST Prevención de Adicciones (preguntas 1 a 9) */
    private const PRE_ADICCIONES_CORRECT = [
        1 => 'B',
        2 => 'B',
        3 => 'B',
        4 => 'C',
        5 => 'B',
        6 => 'B',
        7 => 'C',
        8 => 'C',
        9 => 'B',
    ];

    /** Clave de respuestas correctas: PRE - TEST Prevención de Suicidios (preguntas 1 a 12) */
    private const PRE_SUICIDIOS_CORRECT = [
        1 => 'B',
        2 => 'D',
        3 => 'B',
        4 => 'C',
        5 => 'C',
        6 => 'B',
        7 => 'B',
        8 => 'C',
        9 => 'C',
        10 => 'E',
        11 => 'D',
        12 => 'C',
    ];

    /** Clave de respuestas correctas: PRE - TEST Prevención de Violencias (preguntas 1 a 9) */
    private const PRE_VIOLENCIAS_CORRECT = [
        1 => 'B',
        2 => 'C',
        3 => 'C',
        4 => 'C',
        5 => 'A',
        6 => 'D',
        7 => 'C',
        8 => 'C',
        9 => 'C',
    ];

    /** Clave de respuestas correctas: PRE - TEST Hospitales (preguntas 1 a 20) */
    private const PRE_HOSPITALES_CORRECT = [
        1 => 'C',
        2 => 'C',
        3 => 'B',
        4 => 'C',
        5 => 'C',
        6 => 'D',
        7 => 'C',
        8 => 'C',
        9 => 'C',
        10 => 'B',
        11 => 'C',
        12 => 'D',
        13 => 'B',
        14 => 'E',
        15 => 'D',
        16 => 'A',
        17 => 'A',
        18 => 'C',
        19 => 'B',
        20 => 'C',
    ];

    /** @return array<string, array{name: string, color: string}> */
    public static function getTestsList(): array
    {
        return [
            'violencias' => [
                'name' => 'Prevención de Violencias',
                'color' => 'primary',
            ],
            'suicidios' => [
                'name' => 'Prevención de Suicidios',
                'color' => 'danger',
            ],
            'adicciones' => [
                'name' => 'Prevención de Adicciones',
                'color' => 'warning',
            ],
            'hospitales' => [
                'name' => 'Hospitales',
                'color' => 'success',
            ],
        ];
    }

    /**
     * Temáticas visibles según rol (visitantes no autenticados: todas).
     * Admin y coordinación: todas las temáticas.
     * Psicólogo: violencias, suicidios, adicciones.
     * Médico: hospitales.
     * Abogado: ninguna (no aplica PRE/POST).
     */
    public static function getTestsListForUser(?array $user): array
    {
        $full = self::getTestsList();
        if ($user === null) {
            return $full;
        }

        $roles = array_map('strtolower', $user['roles'] ?? []);
        if (in_array('admin', $roles, true)) {
            return $full;
        }
        if (in_array('coordinador', $roles, true) || in_array('coordinadora', $roles, true)) {
            return $full;
        }
        if (in_array('abogado', $roles, true)) {
            return [];
        }

        $keys = [];
        if (in_array('psicologo', $roles, true)) {
            array_push($keys, 'violencias', 'suicidios', 'adicciones');
        }
        if (in_array('medico', $roles, true)) {
            $keys[] = 'hospitales';
        }
        $keys = array_unique($keys);
        if ($keys === []) {
            return [];
        }

        $out = [];
        foreach ($keys as $k) {
            if (isset($full[$k])) {
                $out[$k] = $full[$k];
            }
        }

        return $out;
    }

    /** Admin y coordinación ven el menú completo de temáticas (sin filtrar por rol operativo). */
    public static function userHasFullEvaluacionesTestMenu(?array $user): bool
    {
        if ($user === null) {
            return true;
        }
        $roles = array_map('strtolower', $user['roles'] ?? []);

        return in_array('admin', $roles, true)
            || in_array('coordinador', $roles, true)
            || in_array('coordinadora', $roles, true);
    }

    public static function userMayAccessTestKey(?array $user, string $testKey): bool
    {
        $allowed = self::getTestsListForUser($user);

        return isset($allowed[$testKey]);
    }

    /** Perfil abogado (sin admin): no aplica módulo PRE/POST. */
    public static function userIsBlockedFromEvaluaciones(?array $user): bool
    {
        if ($user === null) {
            return false;
        }
        $roles = array_map('strtolower', $user['roles'] ?? []);
        if (in_array('admin', $roles, true)) {
            return false;
        }

        return in_array('abogado', $roles, true)
            || in_array('especialista', $roles, true);
    }

    /** Mostrar enlace "Evaluaciones · Test" en el menú lateral. */
    public static function userMaySeeEvaluacionesNav(?array $user): bool
    {
        if ($user === null) {
            return true;
        }
        if (self::userIsBlockedFromEvaluaciones($user)) {
            return false;
        }

        return self::getTestsListForUser($user) !== [];
    }

    /**
     * Letra de la opción correcta según temática y fase (misma clave que al guardar el test).
     */
    public static function correctLetterForQuestion(string $testKey, string $phase, int $questionNumber): ?string
    {
        $phase = strtolower($phase);
        $map = match ($testKey) {
            'violencias' => $phase === 'pre' ? self::PRE_VIOLENCIAS_CORRECT : self::POST_VIOLENCIAS_CORRECT,
            'suicidios' => $phase === 'pre' ? self::PRE_SUICIDIOS_CORRECT : self::POST_SUICIDIOS_CORRECT,
            'adicciones' => $phase === 'pre' ? self::PRE_ADICCIONES_CORRECT : self::POST_ADICCIONES_CORRECT,
            'hospitales' => $phase === 'pre' ? self::PRE_HOSPITALES_CORRECT : self::POST_HOSPITALES_CORRECT,
            default => [],
        };
        if ($map === []) {
            return null;
        }

        return $map[$questionNumber] ?? null;
    }

    /**
     * Detalle de respuestas de un intento (PRE o POST) para seguimiento / errores.
     */
    public function showDetail(Request $request): Response
    {
        $user = Auth::user();
        if ($user === null) {
            return Response::redirect('/login');
        }

        $id = (int) $request->input('id', 0);
        if ($id <= 0) {
            Flash::set([
                'type' => 'error',
                'title' => 'Solicitud no válida',
                'message' => 'Indica un registro de evaluación válido.',
            ]);

            return Response::redirect('/evaluaciones');
        }

        $repo = new TestResponseRepository();
        $response = $repo->findById($id);
        if ($response === null) {
            return Response::view('errors/404', ['pageTitle' => 'No encontrado'], 404);
        }

        $canSeeAll = Auth::canViewAllModuleRecords($user);
        $responseDoc = trim((string) ($response['document_number'] ?? ''));
        $userDoc = trim((string) ($user['document_number'] ?? ''));
        if (!$canSeeAll && ($responseDoc === '' || $responseDoc !== $userDoc)) {
            return Response::view('errors/403', ['pageTitle' => 'Acceso denegado'], 403);
        }

        if (self::userIsBlockedFromEvaluaciones($user)) {
            return Response::view('errors/403', ['pageTitle' => 'Acceso denegado'], 403);
        }

        $testKey = (string) ($response['test_key'] ?? '');
        if (!self::userMayAccessTestKey($user, $testKey)) {
            return Response::view('errors/403', ['pageTitle' => 'Acceso denegado'], 403);
        }

        $phase = (string) ($response['phase'] ?? '');

        $answers = $repo->findAnswersByResponseId($id);
        $answerRows = [];
        foreach ($answers as $a) {
            $q = (int) ($a['question_number'] ?? 0);
            $selected = strtoupper((string) ($a['selected_option'] ?? ''));
            $correct = self::correctLetterForQuestion($testKey, $phase, $q);
            $answerRows[] = [
                'question_number' => $q,
                'selected' => $selected,
                'correct' => $correct,
                'is_correct' => (int) ($a['is_correct'] ?? 0),
            ];
        }

        $tests = self::getTestsList();

        return Response::view('evaluaciones/detalle', [
            'pageTitle' => 'Detalle de evaluación',
            'response' => $response,
            'answerRows' => $answerRows,
            'tests' => $tests,
        ]);
    }

    public function index(Request $request): Response
    {
        $user = Auth::user();
        if ($user !== null && self::userIsBlockedFromEvaluaciones($user)) {
            Flash::set([
                'type' => 'info',
                'title' => 'Sin acceso',
                'message' => 'Los tests PRE/POST no están disponibles para tu perfil.',
            ]);

            return Response::redirect('/');
        }

        $testsForUi = self::getTestsListForUser($user);
        if (
            $user !== null
            && $testsForUi === []
            && !self::userHasFullEvaluacionesTestMenu($user)
        ) {
            Flash::set([
                'type' => 'info',
                'title' => 'Sin temáticas asignadas',
                'message' => 'Tu perfil no tiene temáticas de evaluación PRE/POST asignadas.',
            ]);

            return Response::redirect('/');
        }

        $testsFull = self::getTestsList();

        $canSeeAll = $user !== null && Auth::canViewAllModuleRecords($user);

        $filters = $this->collectEvaluacionFiltersFromRequest($request, $user, $canSeeAll);
        $filters = $this->applyEvaluacionSearchScope($filters, $user);

        $records = [];
        $comparisonRows = [];
        $impactSummary = ['global' => null, 'by_municipality' => []];
        $exportQuery = '';

        if ($user) {
            if (!$canSeeAll && empty($user['document_number'])) {
                $records = [];
            } else {
                $repo = new TestResponseRepository();
                // Vista comparativa PRE/POST: no filtrar por fase; traer ambas para agrupar por persona
                $searchFilters = $filters;
                unset($searchFilters['phase']);
                $searchFilters['limit'] = 8000;
                $records = $repo->search($searchFilters);
                $comparisonRows = EvaluacionesReportService::buildComparisonRows($records, $testsFull);
                $impactSummary = EvaluacionesReportService::summarizeByMunicipality($comparisonRows);
            }

            $exportFilters = $filters;
            unset($exportFilters['phase']);
            $exportQuery = http_build_query(array_filter(
                $exportFilters,
                static fn (mixed $v): bool => $v !== null && $v !== ''
            ));
        }

        return Response::view('evaluaciones/index', [
            'pageTitle' => 'Evaluaciones - Test',
            'tests' => $testsForUi,
            'filters' => $filters,
            'records' => $records,
            'comparisonRows' => $comparisonRows,
            'impactSummary' => $impactSummary,
            'exportQuery' => $exportQuery,
            'currentUser' => $user,
            'canSeeAll' => (bool) $canSeeAll,
        ]);
    }

    /**
     * Exportación CSV (Excel) con los mismos filtros que el listado comparativo.
     */
    public function exportCsv(Request $request): Response
    {
        $user = Auth::user();
        if ($user === null) {
            return Response::redirect('/login');
        }
        if (self::userIsBlockedFromEvaluaciones($user)) {
            Flash::set([
                'type' => 'info',
                'title' => 'Sin acceso',
                'message' => 'Los tests PRE/POST no están disponibles para tu perfil.',
            ]);

            return Response::redirect('/');
        }
        if (
            self::getTestsListForUser($user) === []
            && !self::userHasFullEvaluacionesTestMenu($user)
        ) {
            Flash::set([
                'type' => 'info',
                'title' => 'Sin temáticas asignadas',
                'message' => 'Tu perfil no tiene temáticas de evaluación PRE/POST asignadas.',
            ]);

            return Response::redirect('/');
        }

        $testsFull = self::getTestsList();
        $canSeeAll = Auth::canViewAllModuleRecords($user);
        $filters = $this->collectEvaluacionFiltersFromRequest($request, $user, $canSeeAll);
        $filters = $this->applyEvaluacionSearchScope($filters, $user);
        $searchFilters = $filters;
        unset($searchFilters['phase']);
        $searchFilters['limit'] = 8000;

        $repo = new TestResponseRepository();
        $records = (!$canSeeAll && empty($user['document_number']))
            ? []
            : $repo->search($searchFilters);
        $comparisonRows = EvaluacionesReportService::buildComparisonRows($records, $testsFull);
        $impactSummary = EvaluacionesReportService::summarizeByMunicipality($comparisonRows);

        $csv = $this->buildEvaluacionesCsv($comparisonRows, $impactSummary, $filters);

        $filename = 'evaluaciones_' . date('Y-m-d_His') . '.csv';

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Exportación PDF con resumen por municipio y tabla comparativa.
     */
    public function exportPdf(Request $request): Response
    {
        $user = Auth::user();
        if ($user === null) {
            return Response::redirect('/login');
        }
        if (self::userIsBlockedFromEvaluaciones($user)) {
            Flash::set([
                'type' => 'info',
                'title' => 'Sin acceso',
                'message' => 'Los tests PRE/POST no están disponibles para tu perfil.',
            ]);

            return Response::redirect('/');
        }
        if (
            self::getTestsListForUser($user) === []
            && !self::userHasFullEvaluacionesTestMenu($user)
        ) {
            Flash::set([
                'type' => 'info',
                'title' => 'Sin temáticas asignadas',
                'message' => 'Tu perfil no tiene temáticas de evaluación PRE/POST asignadas.',
            ]);

            return Response::redirect('/');
        }

        $testsFull = self::getTestsList();
        $canSeeAll = Auth::canViewAllModuleRecords($user);
        $filters = $this->collectEvaluacionFiltersFromRequest($request, $user, $canSeeAll);
        $filters = $this->applyEvaluacionSearchScope($filters, $user);
        $searchFilters = $filters;
        unset($searchFilters['phase']);
        $searchFilters['limit'] = 8000;

        $repo = new TestResponseRepository();
        $records = (!$canSeeAll && empty($user['document_number']))
            ? []
            : $repo->search($searchFilters);
        $comparisonRows = EvaluacionesReportService::buildComparisonRows($records, $testsFull);
        $impactSummary = EvaluacionesReportService::summarizeByMunicipality($comparisonRows);

        $html = $this->buildEvaluacionesPdfHtml($comparisonRows, $impactSummary, $filters);

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $filename = 'evaluaciones_' . date('Y-m-d_His') . '.pdf';

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function collectEvaluacionFiltersFromRequest(Request $request, ?array $user, bool $canSeeAll): array
    {
        $filters = [
            'test_key' => (string) $request->input('test_key', ''),
            'phase' => (string) $request->input('phase', ''),
            'document_number' => trim((string) $request->input('document_number', '')),
            'subregion' => trim((string) $request->input('subregion', '')),
            'municipality' => trim((string) $request->input('municipality', '')),
            'date_from' => (string) $request->input('date_from', ''),
            'date_to' => (string) $request->input('date_to', ''),
        ];

        if (!$canSeeAll && $user && !empty($user['document_number'])) {
            $filters['document_number'] = (string) $user['document_number'];
        }

        return $filters;
    }

    /**
     * Restringe la consulta a las temáticas del rol (excepto admin/coordinación).
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function applyEvaluacionSearchScope(array $filters, ?array $user): array
    {
        if ($user === null || self::userHasFullEvaluacionesTestMenu($user)) {
            return $filters;
        }

        $allowedKeys = array_keys(self::getTestsListForUser($user));
        if ($allowedKeys === []) {
            return $filters;
        }

        $tk = trim((string) ($filters['test_key'] ?? ''));
        if ($tk !== '' && !in_array($tk, $allowedKeys, true)) {
            $filters['test_key'] = '';
        }

        if (($filters['test_key'] ?? '') === '') {
            $filters['allowed_test_keys'] = $allowedKeys;
        }

        return $filters;
    }

    /**
     * @param array<int, array<string, mixed>> $comparisonRows
     * @param array{global: ?array, by_municipality: array} $impactSummary
     * @param array<string, mixed> $filters
     */
    private function buildEvaluacionesCsv(array $comparisonRows, array $impactSummary, array $filters): string
    {
        $sep = ';';
        $lines = [];
        $lines[] = "\xEF\xBB\xBF";
        $lines[] = 'Equipo de Promoción y Prevención - Acción en Territorio - Evaluaciones PRE/POST';
        $lines[] = 'Filtros: temática=' . ($filters['test_key'] ?: 'todas')
            . '; documento=' . ($filters['document_number'] ?: '—')
            . '; municipio=' . ($filters['municipality'] ?: '—')
            . '; fechas=' . ($filters['date_from'] ?: '—') . ' / ' . ($filters['date_to'] ?: '—');
        $lines[] = '';

        $g = $impactSummary['global'] ?? null;
        if (is_array($g)) {
            $lines[] = 'Resumen impacto (solo personas con PRE y POST)';
            $lines[] = implode($sep, [
                'Ámbito',
                'N con PRE+POST',
                'Con mejoría %',
                'Sin cambios %',
                'Sin mejoría %',
            ]);
            $lines[] = implode($sep, [
                (string) ($g['municipality'] ?? ''),
                (string) ($g['con_ambos'] ?? '0'),
                (string) ($g['pct_mejoria'] ?? '0'),
                (string) ($g['pct_sin_cambios'] ?? '0'),
                (string) ($g['pct_sin_mejoria'] ?? '0'),
            ]);
            foreach ($impactSummary['by_municipality'] ?? [] as $row) {
                $lines[] = implode($sep, [
                    (string) ($row['municipality'] ?? ''),
                    (string) ($row['con_ambos'] ?? '0'),
                    (string) ($row['pct_mejoria'] ?? '0'),
                    (string) ($row['pct_sin_cambios'] ?? '0'),
                    (string) ($row['pct_sin_mejoria'] ?? '0'),
                ]);
            }
        }
        $lines[] = '';
        $lines[] = implode($sep, [
            'Temática',
            'Documento',
            'Nombres',
            'Apellidos',
            'Subregión',
            'Municipio',
            'PRE %',
            'Fecha PRE',
            'POST %',
            'Fecha POST',
            'Delta puntos',
            'Resultado impacto',
        ]);

        foreach ($comparisonRows as $r) {
            $prePct = $r['pre_score'] !== null ? number_format((float) $r['pre_score'], 2, ',', '') : '—';
            $postPct = $r['post_score'] !== null ? number_format((float) $r['post_score'], 2, ',', '') : '—';
            $delta = isset($r['delta']) && $r['delta'] !== null
                ? number_format((float) $r['delta'], 2, ',', '')
                : '—';
            $lines[] = implode($sep, [
                (string) ($r['test_name'] ?? ''),
                (string) ($r['document_number'] ?? ''),
                str_replace(["\r", "\n", ';'], [' ', ' ', ','], (string) ($r['first_name'] ?? '')),
                str_replace(["\r", "\n", ';'], [' ', ' ', ','], (string) ($r['last_name'] ?? '')),
                str_replace(["\r", "\n", ';'], [' ', ' ', ','], (string) ($r['subregion'] ?? '')),
                str_replace(["\r", "\n", ';'], [' ', ' ', ','], (string) ($r['municipality'] ?? '')),
                $prePct,
                (string) ($r['pre_at'] ?? '—'),
                $postPct,
                (string) ($r['post_at'] ?? '—'),
                $delta,
                (string) ($r['impact_label'] ?? ''),
            ]);
        }

        return implode("\r\n", $lines);
    }

    /**
     * @param array<int, array<string, mixed>> $comparisonRows
     * @param array{global: ?array, by_municipality: array} $impactSummary
     * @param array<string, mixed> $filters
     */
    private function buildEvaluacionesPdfHtml(array $comparisonRows, array $impactSummary, array $filters): string
    {
        $esc = static function (string $s): string {
            return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        };

        $g = $impactSummary['global'] ?? null;
        $summaryRows = '';
        if (is_array($g)) {
            $summaryRows .= '<tr style="background:#e8f5e9;font-weight:bold;"><td>' . $esc((string) ($g['municipality'] ?? '')) . '</td>'
                . '<td class="num">' . $esc((string) ($g['con_ambos'] ?? '0')) . '</td>'
                . '<td class="num">' . $esc((string) ($g['pct_mejoria'] ?? '0')) . '%</td>'
                . '<td class="num">' . $esc((string) ($g['pct_sin_cambios'] ?? '0')) . '%</td>'
                . '<td class="num">' . $esc((string) ($g['pct_sin_mejoria'] ?? '0')) . '%</td></tr>';
            foreach ($impactSummary['by_municipality'] ?? [] as $row) {
                $summaryRows .= '<tr><td>' . $esc((string) ($row['municipality'] ?? '')) . '</td>'
                    . '<td class="num">' . $esc((string) ($row['con_ambos'] ?? '0')) . '</td>'
                    . '<td class="num">' . $esc((string) ($row['pct_mejoria'] ?? '0')) . '%</td>'
                    . '<td class="num">' . $esc((string) ($row['pct_sin_cambios'] ?? '0')) . '%</td>'
                    . '<td class="num">' . $esc((string) ($row['pct_sin_mejoria'] ?? '0')) . '%</td></tr>';
            }
        }

        $detailRows = '';
        foreach ($comparisonRows as $r) {
            $lbl = (string) ($r['impact_label'] ?? '');
            $bg = '#f8f9fa';
            if (($r['impact'] ?? '') === EvaluacionesReportService::IMPACT_MEJORIA) {
                $bg = '#d1e7dd';
            } elseif (($r['impact'] ?? '') === EvaluacionesReportService::IMPACT_SIN_MEJORIA) {
                $bg = '#f8d7da';
            } elseif (($r['impact'] ?? '') === EvaluacionesReportService::IMPACT_SIN_CAMBIOS) {
                $bg = '#e2e3e5';
            }
            $prePct = $r['pre_score'] !== null ? number_format((float) $r['pre_score'], 1) . '%' : '—';
            $postPct = $r['post_score'] !== null ? number_format((float) $r['post_score'], 1) . '%' : '—';
            $delta = isset($r['delta']) && $r['delta'] !== null ? number_format((float) $r['delta'], 1) : '—';
            $detailRows .= '<tr style="background:' . $bg . ';">'
                . '<td>' . $esc((string) ($r['test_name'] ?? '')) . '</td>'
                . '<td>' . $esc((string) ($r['document_number'] ?? '')) . '</td>'
                . '<td>' . $esc(trim((string) ($r['first_name'] ?? '') . ' ' . (string) ($r['last_name'] ?? ''))) . '</td>'
                . '<td>' . $esc((string) ($r['municipality'] ?? '')) . '</td>'
                . '<td class="num">' . $esc($prePct) . '</td>'
                . '<td class="num">' . $esc($postPct) . '</td>'
                . '<td class="num">' . $esc($delta) . '</td>'
                . '<td><strong>' . $esc($lbl) . '</strong></td></tr>';
        }

        $filtroTxt = 'Temática: ' . $esc($filters['test_key'] ?: 'todas')
            . ' · Documento: ' . $esc($filters['document_number'] ?: '—')
            . ' · Subregión: ' . $esc($filters['subregion'] ?: '—')
            . ' · Municipio: ' . $esc($filters['municipality'] ?: '—');

        $base = dirname(__DIR__, 2) . '/public/assets/img';
        $logoAntSrc = PdfImageHelper::imageDataUri($base . '/logoAntioquia.png');
        $logoHomoSrc = PdfImageHelper::imageDataUri($base . '/logoHomo.png');
        $logoAntHtml = $logoAntSrc !== ''
            ? '<img src="' . $esc($logoAntSrc) . '" alt="Gobernación de Antioquia" class="logo-ant">'
            : '';
        $logoHomoHtml = $logoHomoSrc !== ''
            ? '<img src="' . $esc($logoHomoSrc) . '" alt="HOMO" class="logo-homo">'
            : '';

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
            body{font-family:DejaVu Sans,sans-serif;font-size:9px;color:#212529;margin:0;padding:12px 14px;}
            .pdf-header{width:100%;border-collapse:collapse;margin:0 0 10px;}
            .pdf-header td{vertical-align:middle;padding:4px 6px;}
            .pdf-header .hdr-center{text-align:center;}
            .pdf-title{font-size:14px;color:#0f5132;font-weight:700;margin:0 0 2px;}
            .pdf-subtitle{font-size:9px;color:#212529;margin:0;}
            .logo-ant,.logo-homo{height:48px;width:auto;}
            h2{font-size:11px;margin:12px 0 6px;}
            table.data{border-collapse:collapse;width:100%;margin-bottom:10px;}
            table.data th,table.data td{border:1px solid #ccc;padding:3px 5px;text-align:left;}
            table.data th{background:#198754;color:#fff;}
            table.data td.num{text-align:right;}
            .muted{color:#6c757d;font-size:8px;}
            </style></head><body>
            <table class="pdf-header"><tr>
            <td style="width:28%;">' . $logoAntHtml . '</td>
            <td class="hdr-center" style="width:44%;"><p class="pdf-title">Evaluaciones PRE / POST</p><p class="pdf-subtitle">Acción en Territorio</p></td>
            <td style="width:28%;text-align:right;">' . $logoHomoHtml . '</td>
            </tr></table>
            <p class="muted">' . $filtroTxt . ' · Generado: ' . $esc(date('Y-m-d H:i')) . '</p>
            <h2>Resultado impacto por municipio (personas con PRE y POST)</h2>
            <table class="data"><thead><tr><th>Municipio</th><th class="num">N PRE+POST</th><th class="num">Con mejoría</th><th class="num">Sin cambios</th><th class="num">Sin mejoría</th></tr></thead><tbody>'
            . $summaryRows . '</tbody></table>
            <h2>Detalle por persona</h2>
            <table class="data"><thead><tr><th>Temática</th><th>Documento</th><th>Persona</th><th>Municipio</th><th>PRE</th><th>POST</th><th>Δ</th><th>Resultado impacto</th></tr></thead><tbody>'
            . $detailRows . '</tbody></table>
            </body></html>';
    }

    // PRE
    public function preViolencias(Request $request): Response
    {
        if ($request->getMethod() === 'POST') {
            return $this->storePreViolencias($request);
        }

        return $this->renderForm($request, 'violencias', 'pre');
    }

    public function preSuicidios(Request $request): Response
    {
        if ($request->getMethod() === 'POST') {
            return $this->storePreSuicidios($request);
        }

        return $this->renderForm($request, 'suicidios', 'pre');
    }

    public function preAdicciones(Request $request): Response
    {
        if ($request->getMethod() === 'POST') {
            return $this->storePreAdicciones($request);
        }

        return $this->renderForm($request, 'adicciones', 'pre');
    }

    public function preHospitales(Request $request): Response
    {
        if ($request->getMethod() === 'POST') {
            return $this->storePreHospitales($request);
        }

        return $this->renderForm($request, 'hospitales', 'pre');
    }

    // POST
    public function postViolencias(Request $request): Response
    {
        if ($request->getMethod() === 'POST') {
            return $this->storePostViolencias($request);
        }

        return $this->renderForm($request, 'violencias', 'post');
    }

    public function postSuicidios(Request $request): Response
    {
        if ($request->getMethod() === 'POST') {
            return $this->storePostSuicidios($request);
        }

        return $this->renderForm($request, 'suicidios', 'post');
    }

    private function storePreAdicciones(Request $request): Response
    {
        $deny = $this->denyTestAccessResponse('adicciones');
        if ($deny !== null) {
            return $deny;
        }

        $testKey = 'adicciones';
        $phase = 'pre';

        $documentNumber = trim((string) $request->input('document_number', ''));
        $firstName = trim((string) $request->input('first_name', ''));
        $lastName = trim((string) $request->input('last_name', ''));
        $subregion = trim((string) $request->input('subregion', ''));
        $municipality = trim((string) $request->input('municipality', ''));

        if ($documentNumber === '' || !preg_match('/^[0-9]+$/', $documentNumber)) {
            Flash::set([
                'type' => 'error',
                'title' => 'Número de documento no válido',
                'message' => 'El número de documento debe contener solo números.',
            ]);

            return Response::redirect('/evaluaciones/adicciones/pre');
        }

        if ($firstName === '' || $lastName === '' || $subregion === '' || $municipality === '') {
            Flash::set([
                'type' => 'error',
                'title' => 'Campos obligatorios incompletos',
                'message' => 'Por favor completa todos los campos requeridos.',
            ]);

            return Response::redirect('/evaluaciones/adicciones/pre');
        }

        $answers = [];
        $totalQuestions = 9;
        $correctCount = 0;

        for ($i = 1; $i <= $totalQuestions; $i++) {
            $value = (string) $request->input('q' . $i, '');
            if ($value === '') {
                Flash::set([
                    'type' => 'error',
                    'title' => 'Preguntas incompletas',
                    'message' => 'Debes responder todas las preguntas del test.',
                ]);

                return Response::redirect('/evaluaciones/adicciones/pre');
            }

            $correctOption = self::PRE_ADICCIONES_CORRECT[$i] ?? null;
            $isCorrect = $correctOption !== null && strtoupper($value) === $correctOption;
            if ($isCorrect) {
                $correctCount++;
            }

            $answers[] = [
                'question_number' => $i,
                'selected_option' => strtoupper($value),
                'is_correct' => $isCorrect ? 1 : 0,
            ];
        }

        $scorePercent = $totalQuestions > 0 ? round((float) ($correctCount / $totalQuestions) * 100, 2) : 0.0;

        $repo = new TestResponseRepository();

        if ($repo->existsForPerson($testKey, $phase, $documentNumber)) {
            Flash::set([
                'type' => 'error',
                'title' => 'Registro duplicado',
                'message' => 'Ya existe un PRE - TEST de Prevención de Adicciones para este número de documento.',
            ]);

            return Response::redirect('/evaluaciones/adicciones/pre');
        }

        $data = [
            'test_key' => $testKey,
            'phase' => $phase,
            'document_number' => $documentNumber,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'subregion' => $subregion,
            'municipality' => $municipality,
            'profession' => null,
            'total_questions' => $totalQuestions,
            'correct_answers' => $correctCount,
            'score_percent' => $scorePercent,
        ];

        try {
            $repo->create($data, $answers);
        } catch (\PDOException $e) {
            Flash::set([
                'type' => 'error',
                'title' => 'No fue posible guardar el test',
                'message' => 'Ocurrió un problema al registrar tus respuestas. Intenta nuevamente en unos minutos.',
            ]);

            return Response::redirect('/evaluaciones/adicciones/pre');
        }

        Flash::set([
            'type' => 'success',
            'title' => 'PRE - TEST enviado',
            'message' => $this->buildTestSuccessFlashMessage(
                'PRE - TEST',
                'Prevención de Adicciones',
                $correctCount,
                $totalQuestions,
                $scorePercent,
                $firstName,
                $lastName,
                $documentNumber
            ),
        ]);

        return Response::redirect('/evaluaciones/adicciones/pre');
    }

    public function postAdicciones(Request $request): Response
    {
        if ($request->getMethod() === 'POST') {
            return $this->storePostAdicciones($request);
        }

        return $this->renderForm($request, 'adicciones', 'post');
    }

    public function postHospitales(Request $request): Response
    {
        if ($request->getMethod() === 'POST') {
            return $this->storePostHospitales($request);
        }

        return $this->renderForm($request, 'hospitales', 'post');
    }

    public function checkPre(Request $request): Response
    {
        $testKey = (string) $request->input('test_key', '');
        $documentNumber = trim((string) $request->input('document_number', ''));

        $allowedTests = ['violencias', 'suicidios', 'adicciones', 'hospitales'];

        if (
            $testKey === '' ||
            !in_array($testKey, $allowedTests, true) ||
            $documentNumber === '' ||
            !preg_match('/^[0-9]+$/', $documentNumber)
        ) {
            return Response::json(
                ['ok' => false, 'exists' => false, 'error' => 'Parámetros no válidos'],
                400
            );
        }

        $user = Auth::user();
        if ($user !== null && self::userIsBlockedFromEvaluaciones($user)) {
            return Response::json(
                ['ok' => false, 'exists' => false, 'error' => 'Sin permiso'],
                403
            );
        }
        if ($user !== null && !self::userMayAccessTestKey($user, $testKey)) {
            return Response::json(
                ['ok' => false, 'exists' => false, 'error' => 'Sin permiso'],
                403
            );
        }

        $repo = new TestResponseRepository();
        $pre = $repo->findByPerson($testKey, 'pre', $documentNumber);
        $exists = $pre !== null;

        return Response::json([
            'ok' => true,
            'exists' => $exists,
            'pre' => $exists ? [
                'first_name' => (string) ($pre['first_name'] ?? ''),
                'last_name' => (string) ($pre['last_name'] ?? ''),
                'subregion' => (string) ($pre['subregion'] ?? ''),
                'municipality' => (string) ($pre['municipality'] ?? ''),
                'profession' => (string) ($pre['profession'] ?? ''),
            ] : null,
        ]);
    }

    private function storePreHospitales(Request $request): Response
    {
        $deny = $this->denyTestAccessResponse('hospitales');
        if ($deny !== null) {
            return $deny;
        }

        $testKey = 'hospitales';
        $phase = 'pre';

        $documentNumber = trim((string) $request->input('document_number', ''));
        $firstName = trim((string) $request->input('first_name', ''));
        $lastName = trim((string) $request->input('last_name', ''));
        $profession = trim((string) $request->input('profession', ''));
        $subregion = trim((string) $request->input('subregion', ''));
        $municipality = trim((string) $request->input('municipality', ''));

        if ($documentNumber === '' || !preg_match('/^[0-9]+$/', $documentNumber)) {
            Flash::set([
                'type' => 'error',
                'title' => 'Número de documento no válido',
                'message' => 'El número de documento debe contener solo números.',
            ]);

            return Response::redirect('/evaluaciones/hospitales/pre');
        }

        if ($firstName === '' || $lastName === '' || $profession === '' || $subregion === '' || $municipality === '') {
            Flash::set([
                'type' => 'error',
                'title' => 'Campos obligatorios incompletos',
                'message' => 'Por favor completa todos los campos requeridos.',
            ]);

            return Response::redirect('/evaluaciones/hospitales/pre');
        }

        $answers = [];
        $totalQuestions = 20;
        $correctCount = 0;

        for ($i = 1; $i <= $totalQuestions; $i++) {
            $value = (string) $request->input('q' . $i, '');
            if ($value === '') {
                Flash::set([
                    'type' => 'error',
                    'title' => 'Preguntas incompletas',
                    'message' => 'Debes responder todas las preguntas del test.',
                ]);

                return Response::redirect('/evaluaciones/hospitales/pre');
            }

            $correctOption = self::PRE_HOSPITALES_CORRECT[$i] ?? null;
            $isCorrect = $correctOption !== null && strtoupper($value) === $correctOption;
            if ($isCorrect) {
                $correctCount++;
            }

            $answers[] = [
                'question_number' => $i,
                'selected_option' => strtoupper($value),
                'is_correct' => $isCorrect ? 1 : 0,
            ];
        }

        $scorePercent = $totalQuestions > 0 ? round((float) ($correctCount / $totalQuestions) * 100, 2) : 0.0;

        $repo = new TestResponseRepository();

        if ($repo->existsForPerson($testKey, $phase, $documentNumber)) {
            Flash::set([
                'type' => 'error',
                'title' => 'Registro duplicado',
                'message' => 'Ya existe un PRE - TEST de Hospitales para este número de documento.',
            ]);

            return Response::redirect('/evaluaciones/hospitales/pre');
        }

        $data = [
            'test_key' => $testKey,
            'phase' => $phase,
            'document_number' => $documentNumber,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'subregion' => $subregion,
            'municipality' => $municipality,
            'profession' => $profession,
            'total_questions' => $totalQuestions,
            'correct_answers' => $correctCount,
            'score_percent' => $scorePercent,
        ];

        try {
            $repo->create($data, $answers);
        } catch (\PDOException $e) {
            Flash::set([
                'type' => 'error',
                'title' => 'No fue posible guardar el test',
                'message' => 'Ocurrió un problema al registrar tus respuestas. Intenta nuevamente en unos minutos.',
            ]);

            return Response::redirect('/evaluaciones/hospitales/pre');
        }

        Flash::set([
            'type' => 'success',
            'title' => 'PRE - TEST enviado',
            'message' => $this->buildTestSuccessFlashMessage(
                'PRE - TEST',
                'Hospitales',
                $correctCount,
                $totalQuestions,
                $scorePercent,
                $firstName,
                $lastName,
                $documentNumber
            ),
        ]);

        return Response::redirect('/evaluaciones/hospitales/pre');
    }

    private function storePostHospitales(Request $request): Response
    {
        $deny = $this->denyTestAccessResponse('hospitales');
        if ($deny !== null) {
            return $deny;
        }

        $testKey = 'hospitales';
        $phase = 'post';

        $documentNumber = trim((string) $request->input('document_number', ''));

        if ($documentNumber === '' || !preg_match('/^[0-9]+$/', $documentNumber)) {
            Flash::set([
                'type' => 'error',
                'title' => 'Número de documento no válido',
                'message' => 'El número de documento debe contener solo números.',
            ]);

            return Response::redirect('/evaluaciones/hospitales/post');
        }

        $repo = new TestResponseRepository();

        // Debe existir un PRE previo para poder diligenciar el POST
        $pre = $repo->findByPerson($testKey, 'pre', $documentNumber);
        if ($pre === null) {
            Flash::set([
                'type' => 'error',
                'title' => 'PRE - TEST no encontrado',
                'message' => 'Para diligenciar el POST - TEST debes haber completado primero el PRE - TEST con el mismo número de documento.',
            ]);

            return Response::redirect('/evaluaciones/hospitales/post');
        }

        // No permitir más de un POST para la misma persona
        if ($repo->existsForPerson($testKey, $phase, $documentNumber)) {
            Flash::set([
                'type' => 'error',
                'title' => 'Registro duplicado',
                'message' => 'Ya existe un POST - TEST de Hospitales para este número de documento.',
            ]);

            return Response::redirect('/evaluaciones/hospitales/post');
        }

        // Usamos los datos personales del PRE (regla: migrar datos)
        $firstName = (string) ($pre['first_name'] ?? '');
        $lastName = (string) ($pre['last_name'] ?? '');
        $profession = (string) ($pre['profession'] ?? '');
        $subregion = (string) ($pre['subregion'] ?? '');
        $municipality = (string) ($pre['municipality'] ?? '');

        // Recoger respuestas a preguntas (q1..q20) y comparar con la clave correcta
        $answers = [];
        $totalQuestions = 20;
        $correctCount = 0;

        for ($i = 1; $i <= $totalQuestions; $i++) {
            $value = (string) $request->input('q' . $i, '');
            if ($value === '') {
                Flash::set([
                    'type' => 'error',
                    'title' => 'Preguntas incompletas',
                    'message' => 'Debes responder todas las preguntas del test.',
                ]);

                return Response::redirect('/evaluaciones/hospitales/post');
            }

            $correctOption = self::POST_HOSPITALES_CORRECT[$i] ?? null;
            $isCorrect = $correctOption !== null && strtoupper($value) === $correctOption;
            if ($isCorrect) {
                $correctCount++;
            }

            $answers[] = [
                'question_number' => $i,
                'selected_option' => strtoupper($value),
                'is_correct' => $isCorrect ? 1 : 0,
            ];
        }

        $scorePercent = $totalQuestions > 0 ? round((float) ($correctCount / $totalQuestions) * 100, 2) : 0.0;

        $data = [
            'test_key' => $testKey,
            'phase' => $phase,
            'document_number' => $documentNumber,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'subregion' => $subregion,
            'municipality' => $municipality,
            'profession' => $profession,
            'total_questions' => $totalQuestions,
            'correct_answers' => $correctCount,
            'score_percent' => $scorePercent,
        ];

        try {
            $repo->create($data, $answers);
        } catch (\PDOException $e) {
            Flash::set([
                'type' => 'error',
                'title' => 'No fue posible guardar el test',
                'message' => 'Ocurrió un problema al registrar tus respuestas. Intenta nuevamente en unos minutos.',
            ]);

            return Response::redirect('/evaluaciones/hospitales/post');
        }

        Flash::set([
            'type' => 'success',
            'title' => 'POST - TEST enviado',
            'message' => $this->buildTestSuccessFlashMessage(
                'POST - TEST',
                'Hospitales',
                $correctCount,
                $totalQuestions,
                $scorePercent,
                $firstName,
                $lastName,
                $documentNumber
            ),
        ]);

        return Response::redirect('/evaluaciones/hospitales/post');
    }

    private function storePreViolencias(Request $request): Response
    {
        $deny = $this->denyTestAccessResponse('violencias');
        if ($deny !== null) {
            return $deny;
        }

        $testKey = 'violencias';
        $phase = 'pre';

        $documentNumber = trim((string) $request->input('document_number', ''));
        $firstName = trim((string) $request->input('first_name', ''));
        $lastName = trim((string) $request->input('last_name', ''));
        $subregion = trim((string) $request->input('subregion', ''));
        $municipality = trim((string) $request->input('municipality', ''));

        // Validaciones básicas
        if ($documentNumber === '' || !preg_match('/^[0-9]+$/', $documentNumber)) {
            Flash::set([
                'type' => 'error',
                'title' => 'Número de documento no válido',
                'message' => 'El número de documento debe contener solo números.',
            ]);

            return Response::redirect('/evaluaciones/violencias/pre');
        }

        if ($firstName === '' || $lastName === '' || $subregion === '' || $municipality === '') {
            Flash::set([
                'type' => 'error',
                'title' => 'Campos obligatorios incompletos',
                'message' => 'Por favor completa todos los campos requeridos.',
            ]);

            return Response::redirect('/evaluaciones/violencias/pre');
        }

        // Recoger respuestas a preguntas (q1..q9) y comparar con la clave correcta
        $answers = [];
        $totalQuestions = 9;
        $correctCount = 0;

        for ($i = 1; $i <= $totalQuestions; $i++) {
            $value = (string) $request->input('q' . $i, '');
            if ($value === '') {
                Flash::set([
                    'type' => 'error',
                    'title' => 'Preguntas incompletas',
                    'message' => 'Debes responder todas las preguntas del test.',
                ]);

                return Response::redirect('/evaluaciones/violencias/pre');
            }

            $correctOption = self::PRE_VIOLENCIAS_CORRECT[$i] ?? null;
            $isCorrect = $correctOption !== null && strtoupper($value) === $correctOption;
            if ($isCorrect) {
                $correctCount++;
            }

            $answers[] = [
                'question_number' => $i,
                'selected_option' => strtoupper($value),
                'is_correct' => $isCorrect ? 1 : 0,
            ];
        }

        $scorePercent = $totalQuestions > 0 ? round((float) ($correctCount / $totalQuestions) * 100, 2) : 0.0;

        $repo = new TestResponseRepository();

        // Verificar si ya existe PRE para esta persona en este test
        if ($repo->existsForPerson($testKey, $phase, $documentNumber)) {
            Flash::set([
                'type' => 'error',
                'title' => 'Registro duplicado',
                'message' => 'Ya existe un PRE - TEST de Prevención de Violencias para este número de documento.',
            ]);

            return Response::redirect('/evaluaciones/violencias/pre');
        }

        $data = [
            'test_key' => $testKey,
            'phase' => $phase,
            'document_number' => $documentNumber,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'subregion' => $subregion,
            'municipality' => $municipality,
            'profession' => null,
            'total_questions' => $totalQuestions,
            'correct_answers' => $correctCount,
            'score_percent' => $scorePercent,
        ];

        try {
            $repo->create($data, $answers);
        } catch (\PDOException $e) {
            Flash::set([
                'type' => 'error',
                'title' => 'No fue posible guardar el test',
                'message' => 'Ocurrió un problema al registrar tus respuestas. Intenta nuevamente en unos minutos.',
            ]);

            return Response::redirect('/evaluaciones/violencias/pre');
        }

        Flash::set([
            'type' => 'success',
            'title' => 'PRE - TEST enviado',
            'message' => $this->buildTestSuccessFlashMessage(
                'PRE - TEST',
                'Prevención de Violencias',
                $correctCount,
                $totalQuestions,
                $scorePercent,
                $firstName,
                $lastName,
                $documentNumber
            ),
        ]);

        return Response::redirect('/evaluaciones/violencias/pre');
    }

    private function storePostViolencias(Request $request): Response
    {
        $deny = $this->denyTestAccessResponse('violencias');
        if ($deny !== null) {
            return $deny;
        }

        $testKey = 'violencias';
        $phase = 'post';

        $documentNumber = trim((string) $request->input('document_number', ''));

        if ($documentNumber === '' || !preg_match('/^[0-9]+$/', $documentNumber)) {
            Flash::set([
                'type' => 'error',
                'title' => 'Número de documento no válido',
                'message' => 'El número de documento debe contener solo números.',
            ]);

            return Response::redirect('/evaluaciones/violencias/post');
        }

        $repo = new TestResponseRepository();

        // Debe existir un PRE previo para poder diligenciar el POST
        $pre = $repo->findByPerson($testKey, 'pre', $documentNumber);
        if ($pre === null) {
            Flash::set([
                'type' => 'error',
                'title' => 'PRE - TEST no encontrado',
                'message' => 'Para diligenciar el POST - TEST debes haber completado primero el PRE - TEST con el mismo número de documento.',
            ]);

            return Response::redirect('/evaluaciones/violencias/post');
        }

        // No permitir más de un POST para la misma persona
        if ($repo->existsForPerson($testKey, $phase, $documentNumber)) {
            Flash::set([
                'type' => 'error',
                'title' => 'Registro duplicado',
                'message' => 'Ya existe un POST - TEST de Prevención de Violencias para este número de documento.',
            ]);

            return Response::redirect('/evaluaciones/violencias/post');
        }

        // Usamos los datos personales del PRE (regla: migrar datos)
        $firstName = (string) ($pre['first_name'] ?? '');
        $lastName = (string) ($pre['last_name'] ?? '');
        $subregion = (string) ($pre['subregion'] ?? '');
        $municipality = (string) ($pre['municipality'] ?? '');

        // Recoger respuestas a preguntas (q1..q9) y comparar con la clave correcta
        $answers = [];
        $totalQuestions = 9;
        $correctCount = 0;

        for ($i = 1; $i <= $totalQuestions; $i++) {
            $value = (string) $request->input('q' . $i, '');
            if ($value === '') {
                Flash::set([
                    'type' => 'error',
                    'title' => 'Preguntas incompletas',
                    'message' => 'Debes responder todas las preguntas del test.',
                ]);

                return Response::redirect('/evaluaciones/violencias/post');
            }

            $correctOption = self::POST_VIOLENCIAS_CORRECT[$i] ?? null;
            $isCorrect = $correctOption !== null && strtoupper($value) === $correctOption;
            if ($isCorrect) {
                $correctCount++;
            }

            $answers[] = [
                'question_number' => $i,
                'selected_option' => strtoupper($value),
                'is_correct' => $isCorrect ? 1 : 0,
            ];
        }

        $scorePercent = $totalQuestions > 0 ? round((float) ($correctCount / $totalQuestions) * 100, 2) : 0.0;

        $data = [
            'test_key' => $testKey,
            'phase' => $phase,
            'document_number' => $documentNumber,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'subregion' => $subregion,
            'municipality' => $municipality,
            'profession' => null,
            'total_questions' => $totalQuestions,
            'correct_answers' => $correctCount,
            'score_percent' => $scorePercent,
        ];

        try {
            $repo->create($data, $answers);
        } catch (\PDOException $e) {
            Flash::set([
                'type' => 'error',
                'title' => 'No fue posible guardar el test',
                'message' => 'Ocurrió un problema al registrar tus respuestas. Intenta nuevamente en unos minutos.',
            ]);

            return Response::redirect('/evaluaciones/violencias/post');
        }

        Flash::set([
            'type' => 'success',
            'title' => 'POST - TEST enviado',
            'message' => $this->buildTestSuccessFlashMessage(
                'POST - TEST',
                'Prevención de Violencias',
                $correctCount,
                $totalQuestions,
                $scorePercent,
                $firstName,
                $lastName,
                $documentNumber
            ),
        ]);

        return Response::redirect('/evaluaciones/violencias/post');
    }

    private function storePreSuicidios(Request $request): Response
    {
        $deny = $this->denyTestAccessResponse('suicidios');
        if ($deny !== null) {
            return $deny;
        }

        $testKey = 'suicidios';
        $phase = 'pre';

        $documentNumber = trim((string) $request->input('document_number', ''));
        $firstName = trim((string) $request->input('first_name', ''));
        $lastName = trim((string) $request->input('last_name', ''));
        $subregion = trim((string) $request->input('subregion', ''));
        $municipality = trim((string) $request->input('municipality', ''));

        if ($documentNumber === '' || !preg_match('/^[0-9]+$/', $documentNumber)) {
            Flash::set([
                'type' => 'error',
                'title' => 'Número de documento no válido',
                'message' => 'El número de documento debe contener solo números.',
            ]);

            return Response::redirect('/evaluaciones/suicidios/pre');
        }

        if ($firstName === '' || $lastName === '' || $subregion === '' || $municipality === '') {
            Flash::set([
                'type' => 'error',
                'title' => 'Campos obligatorios incompletos',
                'message' => 'Por favor completa todos los campos requeridos.',
            ]);

            return Response::redirect('/evaluaciones/suicidios/pre');
        }

        $answers = [];
        $totalQuestions = 12;
        $correctCount = 0;

        for ($i = 1; $i <= $totalQuestions; $i++) {
            $value = (string) $request->input('q' . $i, '');
            if ($value === '') {
                Flash::set([
                    'type' => 'error',
                    'title' => 'Preguntas incompletas',
                    'message' => 'Debes responder todas las preguntas del test.',
                ]);

                return Response::redirect('/evaluaciones/suicidios/pre');
            }

            $correctOption = self::PRE_SUICIDIOS_CORRECT[$i] ?? null;
            $isCorrect = $correctOption !== null && strtoupper($value) === $correctOption;
            if ($isCorrect) {
                $correctCount++;
            }

            $answers[] = [
                'question_number' => $i,
                'selected_option' => strtoupper($value),
                'is_correct' => $isCorrect ? 1 : 0,
            ];
        }

        $scorePercent = $totalQuestions > 0 ? round((float) ($correctCount / $totalQuestions) * 100, 2) : 0.0;

        $repo = new TestResponseRepository();

        if ($repo->existsForPerson($testKey, $phase, $documentNumber)) {
            Flash::set([
                'type' => 'error',
                'title' => 'Registro duplicado',
                'message' => 'Ya existe un PRE - TEST de Prevención de Suicidios para este número de documento.',
            ]);

            return Response::redirect('/evaluaciones/suicidios/pre');
        }

        $data = [
            'test_key' => $testKey,
            'phase' => $phase,
            'document_number' => $documentNumber,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'subregion' => $subregion,
            'municipality' => $municipality,
            'profession' => null,
            'total_questions' => $totalQuestions,
            'correct_answers' => $correctCount,
            'score_percent' => $scorePercent,
        ];

        try {
            $repo->create($data, $answers);
        } catch (\PDOException $e) {
            Flash::set([
                'type' => 'error',
                'title' => 'No fue posible guardar el test',
                'message' => 'Ocurrió un problema al registrar tus respuestas. Intenta nuevamente en unos minutos.',
            ]);

            return Response::redirect('/evaluaciones/suicidios/pre');
        }

        Flash::set([
            'type' => 'success',
            'title' => 'PRE - TEST enviado',
            'message' => $this->buildTestSuccessFlashMessage(
                'PRE - TEST',
                'Prevención de Suicidios',
                $correctCount,
                $totalQuestions,
                $scorePercent,
                $firstName,
                $lastName,
                $documentNumber
            ),
        ]);

        return Response::redirect('/evaluaciones/suicidios/pre');
    }

    private function storePostSuicidios(Request $request): Response
    {
        $deny = $this->denyTestAccessResponse('suicidios');
        if ($deny !== null) {
            return $deny;
        }

        $testKey = 'suicidios';
        $phase = 'post';

        $documentNumber = trim((string) $request->input('document_number', ''));

        if ($documentNumber === '' || !preg_match('/^[0-9]+$/', $documentNumber)) {
            Flash::set([
                'type' => 'error',
                'title' => 'Número de documento no válido',
                'message' => 'El número de documento debe contener solo números.',
            ]);

            return Response::redirect('/evaluaciones/suicidios/post');
        }

        $repo = new TestResponseRepository();

        // Debe existir un PRE previo para poder diligenciar el POST
        $pre = $repo->findByPerson($testKey, 'pre', $documentNumber);
        if ($pre === null) {
            Flash::set([
                'type' => 'error',
                'title' => 'PRE - TEST no encontrado',
                'message' => 'Para diligenciar el POST - TEST debes haber completado primero el PRE - TEST con el mismo número de documento.',
            ]);

            return Response::redirect('/evaluaciones/suicidios/post');
        }

        // No permitir más de un POST para la misma persona
        if ($repo->existsForPerson($testKey, $phase, $documentNumber)) {
            Flash::set([
                'type' => 'error',
                'title' => 'Registro duplicado',
                'message' => 'Ya existe un POST - TEST de Prevención de Suicidios para este número de documento.',
            ]);

            return Response::redirect('/evaluaciones/suicidios/post');
        }

        // Usamos los datos personales del PRE (regla: migrar datos)
        $firstName = (string) ($pre['first_name'] ?? '');
        $lastName = (string) ($pre['last_name'] ?? '');
        $subregion = (string) ($pre['subregion'] ?? '');
        $municipality = (string) ($pre['municipality'] ?? '');

        // Recoger respuestas a preguntas (q1..q12) y comparar con la clave correcta
        $answers = [];
        $totalQuestions = 12;
        $correctCount = 0;

        for ($i = 1; $i <= $totalQuestions; $i++) {
            $value = (string) $request->input('q' . $i, '');
            if ($value === '') {
                Flash::set([
                    'type' => 'error',
                    'title' => 'Preguntas incompletas',
                    'message' => 'Debes responder todas las preguntas del test.',
                ]);

                return Response::redirect('/evaluaciones/suicidios/post');
            }

            $correctOption = self::POST_SUICIDIOS_CORRECT[$i] ?? null;
            $isCorrect = $correctOption !== null && strtoupper($value) === $correctOption;
            if ($isCorrect) {
                $correctCount++;
            }

            $answers[] = [
                'question_number' => $i,
                'selected_option' => strtoupper($value),
                'is_correct' => $isCorrect ? 1 : 0,
            ];
        }

        $scorePercent = $totalQuestions > 0 ? round((float) ($correctCount / $totalQuestions) * 100, 2) : 0.0;

        $data = [
            'test_key' => $testKey,
            'phase' => $phase,
            'document_number' => $documentNumber,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'subregion' => $subregion,
            'municipality' => $municipality,
            'profession' => null,
            'total_questions' => $totalQuestions,
            'correct_answers' => $correctCount,
            'score_percent' => $scorePercent,
        ];

        try {
            $repo->create($data, $answers);
        } catch (\PDOException $e) {
            Flash::set([
                'type' => 'error',
                'title' => 'No fue posible guardar el test',
                'message' => 'Ocurrió un problema al registrar tus respuestas. Intenta nuevamente en unos minutos.',
            ]);

            return Response::redirect('/evaluaciones/suicidios/post');
        }

        Flash::set([
            'type' => 'success',
            'title' => 'POST - TEST enviado',
            'message' => $this->buildTestSuccessFlashMessage(
                'POST - TEST',
                'Prevención de Suicidios',
                $correctCount,
                $totalQuestions,
                $scorePercent,
                $firstName,
                $lastName,
                $documentNumber
            ),
        ]);

        return Response::redirect('/evaluaciones/suicidios/post');
    }

    private function storePostAdicciones(Request $request): Response
    {
        $deny = $this->denyTestAccessResponse('adicciones');
        if ($deny !== null) {
            return $deny;
        }

        $testKey = 'adicciones';
        $phase = 'post';

        $documentNumber = trim((string) $request->input('document_number', ''));

        if ($documentNumber === '' || !preg_match('/^[0-9]+$/', $documentNumber)) {
            Flash::set([
                'type' => 'error',
                'title' => 'Número de documento no válido',
                'message' => 'El número de documento debe contener solo números.',
            ]);

            return Response::redirect('/evaluaciones/adicciones/post');
        }

        $repo = new TestResponseRepository();

        // Debe existir un PRE previo para poder diligenciar el POST
        $pre = $repo->findByPerson($testKey, 'pre', $documentNumber);
        if ($pre === null) {
            Flash::set([
                'type' => 'error',
                'title' => 'PRE - TEST no encontrado',
                'message' => 'Para diligenciar el POST - TEST debes haber completado primero el PRE - TEST con el mismo número de documento.',
            ]);

            return Response::redirect('/evaluaciones/adicciones/post');
        }

        // No permitir más de un POST para la misma persona
        if ($repo->existsForPerson($testKey, $phase, $documentNumber)) {
            Flash::set([
                'type' => 'error',
                'title' => 'Registro duplicado',
                'message' => 'Ya existe un POST - TEST de Prevención de Adicciones para este número de documento.',
            ]);

            return Response::redirect('/evaluaciones/adicciones/post');
        }

        // Usamos los datos personales del PRE (regla: migrar datos)
        $firstName = (string) ($pre['first_name'] ?? '');
        $lastName = (string) ($pre['last_name'] ?? '');
        $subregion = (string) ($pre['subregion'] ?? '');
        $municipality = (string) ($pre['municipality'] ?? '');

        // Recoger respuestas a preguntas (q1..q9) y comparar con la clave correcta
        $answers = [];
        $totalQuestions = 9;
        $correctCount = 0;

        for ($i = 1; $i <= $totalQuestions; $i++) {
            $value = (string) $request->input('q' . $i, '');
            if ($value === '') {
                Flash::set([
                    'type' => 'error',
                    'title' => 'Preguntas incompletas',
                    'message' => 'Debes responder todas las preguntas del test.',
                ]);

                return Response::redirect('/evaluaciones/adicciones/post');
            }

            $correctOption = self::POST_ADICCIONES_CORRECT[$i] ?? null;
            $isCorrect = $correctOption !== null && strtoupper($value) === $correctOption;
            if ($isCorrect) {
                $correctCount++;
            }

            $answers[] = [
                'question_number' => $i,
                'selected_option' => strtoupper($value),
                'is_correct' => $isCorrect ? 1 : 0,
            ];
        }

        $scorePercent = $totalQuestions > 0 ? round((float) ($correctCount / $totalQuestions) * 100, 2) : 0.0;

        $data = [
            'test_key' => $testKey,
            'phase' => $phase,
            'document_number' => $documentNumber,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'subregion' => $subregion,
            'municipality' => $municipality,
            'profession' => null,
            'total_questions' => $totalQuestions,
            'correct_answers' => $correctCount,
            'score_percent' => $scorePercent,
        ];

        try {
            $repo->create($data, $answers);
        } catch (\PDOException $e) {
            Flash::set([
                'type' => 'error',
                'title' => 'No fue posible guardar el test',
                'message' => 'Ocurrió un problema al registrar tus respuestas. Intenta nuevamente en unos minutos.',
            ]);

            return Response::redirect('/evaluaciones/adicciones/post');
        }

        Flash::set([
            'type' => 'success',
            'title' => 'POST - TEST enviado',
            'message' => $this->buildTestSuccessFlashMessage(
                'POST - TEST',
                'Prevención de Adicciones',
                $correctCount,
                $totalQuestions,
                $scorePercent,
                $firstName,
                $lastName,
                $documentNumber
            ),
        ]);

        return Response::redirect('/evaluaciones/adicciones/post');
    }

    /**
     * Mensaje de confirmación tras guardar un test (incluye nombre, apellidos y documento del evaluado).
     */
    private function buildTestSuccessFlashMessage(
        string $phaseLabel,
        string $topicName,
        int $correctCount,
        int $totalQuestions,
        float $scorePercent,
        string $firstName,
        string $lastName,
        string $documentNumber
    ): string {
        $fn = trim($firstName);
        $ln = trim($lastName);
        $doc = trim($documentNumber);

        return sprintf(
            'Tu %s de %s ha sido registrado correctamente. Persona evaluada — Nombre: %s · Apellidos: %s · Documento: %s. Respuestas correctas: %d de %d (%.0f%%).',
            $phaseLabel,
            $topicName,
            $fn !== '' ? $fn : '—',
            $ln !== '' ? $ln : '—',
            $doc !== '' ? $doc : '—',
            $correctCount,
            $totalQuestions,
            $scorePercent
        );
    }

    private function denyTestAccessResponse(string $testKey): ?Response
    {
        $user = Auth::user();
        if ($user === null) {
            return null;
        }
        if (self::userIsBlockedFromEvaluaciones($user)) {
            Flash::set([
                'type' => 'info',
                'title' => 'Sin acceso',
                'message' => 'Los tests PRE/POST no están disponibles para tu perfil.',
            ]);

            return Response::redirect('/');
        }
        if (!self::userMayAccessTestKey($user, $testKey)) {
            Flash::set([
                'type' => 'warning',
                'title' => 'Temática no disponible',
                'message' => 'No tienes permiso para acceder a esta temática de evaluación.',
            ]);

            return Response::redirect('/evaluaciones');
        }

        return null;
    }

    private function renderForm(Request $request, string $testKey, string $phase): Response
    {
        $deny = $this->denyTestAccessResponse($testKey);
        if ($deny !== null) {
            return $deny;
        }

        $config = $this->configFor($testKey, $phase);

        $currentUser = Auth::user();
        $prefill = [
            'document_number' => '',
            'first_name' => '',
            'last_name' => '',
            'subregion' => '',
            'municipality' => '',
        ];

        if ($currentUser !== null) {
            $prefill['document_number'] = (string) ($currentUser['document_number'] ?? '');
        }

        return Response::view('evaluaciones/form', [
            'pageTitle' => $config['title'],
            'config' => $config,
            'prefill' => $prefill,
        ]);
    }

    private function configFor(string $testKey, string $phase): array
    {
        $topics = [
            'violencias' => 'Prevención de Violencias',
            'suicidios' => 'Prevención de Suicidios',
            'adicciones' => 'Prevención de Adicciones',
            'hospitales' => 'Hospitales',
        ];

        $labelsPhase = [
            'pre' => 'PRE - TEST',
            'post' => 'POST - TEST',
        ];

        return [
            'key' => $testKey,
            'phase' => $phase,
            'title' => sprintf('%s - %s', $labelsPhase[$phase] ?? strtoupper($phase), $topics[$testKey] ?? ''),
            'topic' => $topics[$testKey] ?? '',
            'isHospital' => $testKey === 'hospitales',
        ];
    }
}

