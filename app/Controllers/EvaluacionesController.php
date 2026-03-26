<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\TestResponseRepository;
use App\Services\Auth;
use App\Services\EvaluacionesQuestionCatalog;
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
     * TemÃ¡ticas visibles segÃºn rol (visitantes no autenticados: todas).
     * Admin y coordinaciÃ³n: todas las temÃ¡ticas.
     * PsicÃ³logo: violencias, suicidios, adicciones.
     * MÃ©dico: hospitales.
     * Abogado: ninguna (no aplica PRE/POST).
     */
    public static function getTestsListForUser(?array $user): array
    {
        $full = self::getTestsList();
        if ($user === null) {
            return $full;
        }

        $roles = array_map('strtolower', $user['roles'] ?? []);
        $primaryRole = strtolower(trim((string) ($user['role'] ?? '')));
        if (in_array('admin', $roles, true)) {
            return $full;
        }
        if (in_array('coordinador', $roles, true) || in_array('coordinadora', $roles, true)) {
            return $full;
        }
        if (in_array('abogado', $roles, true) && !in_array('especialista', $roles, true)) {
            return [];
        }

        $keys = [];
        $isPsychProfile = in_array('psicologo', $roles, true) || $primaryRole === 'psicologo';
        $isMedicalProfile = in_array('medico', $roles, true) || $primaryRole === 'medico';

        if ($isPsychProfile) {
            array_push($keys, 'violencias', 'suicidios', 'adicciones');
        }
        if ($isMedicalProfile) {
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

    /** Admin y coordinaciÃ³n ven el menÃº completo de temÃ¡ticas (sin filtrar por rol operativo). */
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

    /** Perfil abogado (sin admin): no aplica mÃ³dulo PRE/POST. */
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
            && !in_array('especialista', $roles, true);
    }

    /** Mostrar enlace "Evaluaciones Â· Test" en el menÃº lateral. */
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
     * Letra de la opciÃ³n correcta segÃºn temÃ¡tica y fase (misma clave que al guardar el test).
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
                'title' => 'Solicitud no vÃ¡lida',
                'message' => 'Indica un registro de evaluaciÃ³n vÃ¡lido.',
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
            'pageTitle' => 'Detalle de evaluaciÃ³n',
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
                'message' => 'Los tests PRE/POST no estÃ¡n disponibles para tu perfil.',
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
                'title' => 'Sin temÃ¡ticas asignadas',
                'message' => 'Tu perfil no tiene temÃ¡ticas de evaluaciÃ³n PRE/POST asignadas.',
            ]);

            return Response::redirect('/');
        }

        $testsFull = self::getTestsList();

        $canSeeAll   = $user !== null && Auth::canViewAllModuleRecords($user);
        $sort        = trim((string) $request->input('sort', 'municipality'));
        $dir         = strtolower(trim((string) $request->input('dir', 'asc')));
        $currentPage = max(1, (int) $request->input('page', 1));

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
                $searchFilters = $filters;
                unset($searchFilters['phase'], $searchFilters['impact'], $searchFilters['search']);
                $searchFilters['limit'] = 8000;
                $records = $repo->search($searchFilters);
                $comparisonRows = EvaluacionesReportService::buildComparisonRows($records, $testsFull);
                $comparisonRows = $this->applyComparisonFilters($comparisonRows, $filters);
                $impactSummary  = EvaluacionesReportService::summarizeByMunicipality($comparisonRows);
            }

            $exportFilters = $filters;
            unset($exportFilters['phase']);
            $exportQuery = http_build_query(array_filter(
                $exportFilters,
                static fn (mixed $v): bool => $v !== null && $v !== ''
            ));
        }

        $comparisonRows = $this->sortComparisonRows($comparisonRows, $testsFull, $sort, $dir);
        $pagination     = $this->paginateEvalRows($comparisonRows, $currentPage, 25);

        if ((string) $request->input('partial', '') === 'results') {
            $html = $this->renderResultsPartial($pagination['items'], $pagination, $impactSummary, $testsFull);

            return Response::json(['html' => $html]);
        }

        return Response::view('evaluaciones/index', [
            'pageTitle'      => 'Evaluaciones - Test',
            'tests'          => $testsForUi,
            'filters'        => $filters,
            'records'        => $records,
            'comparisonRows' => $pagination['items'],
            'pagination'     => $pagination,
            'impactSummary'  => $impactSummary,
            'exportQuery'    => $exportQuery,
            'currentUser'    => $user,
            'canSeeAll'      => (bool) $canSeeAll,
        ]);
    }

    /**
     * ExportaciÃ³n CSV (Excel) con los mismos filtros que el listado comparativo.
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
                'message' => 'Los tests PRE/POST no estÃ¡n disponibles para tu perfil.',
            ]);

            return Response::redirect('/');
        }
        if (
            self::getTestsListForUser($user) === []
            && !self::userHasFullEvaluacionesTestMenu($user)
        ) {
            Flash::set([
                'type' => 'info',
                'title' => 'Sin temÃ¡ticas asignadas',
                'message' => 'Tu perfil no tiene temÃ¡ticas de evaluaciÃ³n PRE/POST asignadas.',
            ]);

            return Response::redirect('/');
        }

        $testsFull = self::getTestsList();
        $singleExport = $this->resolveSingleEvaluacionExport($request, $user, $testsFull);
        if ($singleExport !== null) {
            return $this->exportSingleEvaluacionExcel(
                $singleExport['test_key'],
                $singleExport['test_name'],
                $singleExport['pre'],
                $singleExport['post']
            );
        }

        $canSeeAll = Auth::canViewAllModuleRecords($user);
        $filters = $this->collectEvaluacionFiltersFromRequest($request, $user, $canSeeAll);
        $filters = $this->applyEvaluacionSearchScope($filters, $user);
        $searchFilters = $filters;
        unset($searchFilters['phase'], $searchFilters['impact'], $searchFilters['search']);
        $searchFilters['limit'] = 8000;

        $repo = new TestResponseRepository();
        $records = (!$canSeeAll && empty($user['document_number']))
            ? []
            : $repo->search($searchFilters);
        $comparisonRows = EvaluacionesReportService::buildComparisonRows($records, $testsFull);
        $comparisonRows = $this->applyComparisonFilters($comparisonRows, $filters);
        $impactSummary = EvaluacionesReportService::summarizeByMunicipality($comparisonRows);

        $excel = $this->buildEvaluacionesExcelHtml($comparisonRows, $impactSummary, $filters);

        $filename = 'evaluaciones_' . date('Y-m-d_His') . '.xls';

        return new Response($excel, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * ExportaciÃ³n PDF con resumen por municipio y tabla comparativa.
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
                'message' => 'Los tests PRE/POST no estÃ¡n disponibles para tu perfil.',
            ]);

            return Response::redirect('/');
        }
        if (
            self::getTestsListForUser($user) === []
            && !self::userHasFullEvaluacionesTestMenu($user)
        ) {
            Flash::set([
                'type' => 'info',
                'title' => 'Sin temÃ¡ticas asignadas',
                'message' => 'Tu perfil no tiene temÃ¡ticas de evaluaciÃ³n PRE/POST asignadas.',
            ]);

            return Response::redirect('/');
        }

        $testsFull = self::getTestsList();
        $singleExport = $this->resolveSingleEvaluacionExport($request, $user, $testsFull);
        if ($singleExport !== null) {
            return $this->exportSingleEvaluacionPdf(
                $singleExport['test_key'],
                $singleExport['test_name'],
                $singleExport['pre'],
                $singleExport['post']
            );
        }

        $canSeeAll = Auth::canViewAllModuleRecords($user);
        $filters = $this->collectEvaluacionFiltersFromRequest($request, $user, $canSeeAll);
        $filters = $this->applyEvaluacionSearchScope($filters, $user);
        $searchFilters = $filters;
        unset($searchFilters['phase'], $searchFilters['impact'], $searchFilters['search']);
        $searchFilters['limit'] = 8000;

        $repo = new TestResponseRepository();
        $records = (!$canSeeAll && empty($user['document_number']))
            ? []
            : $repo->search($searchFilters);
        $comparisonRows = EvaluacionesReportService::buildComparisonRows($records, $testsFull);
        $impactSummary = EvaluacionesReportService::summarizeByMunicipality($comparisonRows);

        @ini_set('memory_limit', '512M');
        @set_time_limit(180);

        $html = $this->buildEvaluacionesPdfHtml($comparisonRows, $impactSummary, $filters);
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="evaluaciones_' . date('Y-m-d_His') . '.pdf"',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function paginateEvalRows(array $rows, int $page, int $perPage): array
    {
        $total       = count($rows);
        $totalPages  = max(1, (int) ceil($total / $perPage));
        $currentPage = min(max(1, $page), $totalPages);
        $offset      = ($currentPage - 1) * $perPage;
        return [
            'items'        => array_slice($rows, $offset, $perPage),
            'total_items'  => $total,
            'per_page'     => $perPage,
            'current_page' => $currentPage,
            'total_pages'  => $totalPages,
            'from'         => $total === 0 ? 0 : $offset + 1,
            'to'           => min($offset + $perPage, $total),
        ];
    }

    private function sortComparisonRows(array $rows, array $tests, string $sort, string $dir): array
    {
        $allowed = ['test_key', 'document_number', 'persona', 'subregion', 'municipality', 'pre_score', 'post_score', 'delta', 'impact'];
        if (!in_array($sort, $allowed, true)) {
            $sort = 'municipality';
        }
        $asc = $dir !== 'desc';
        usort($rows, function (array $a, array $b) use ($sort, $asc, $tests): int {
            $av = $this->evalSortValue($a, $sort, $tests);
            $bv = $this->evalSortValue($b, $sort, $tests);
            if ($av === $bv) return 0;
            $cmp = $av <=> $bv;
            return $asc ? $cmp : -$cmp;
        });
        return $rows;
    }

    private function evalSortValue(array $row, string $sort, array $tests): string
    {
        if ($sort === 'test_key') {
            $k = (string) ($row['test_key'] ?? '');
            return strtolower((string) ($tests[$k]['name'] ?? $k));
        }
        if ($sort === 'persona') {
            return strtolower(trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? '')));
        }
        if ($sort === 'pre_score' || $sort === 'post_score' || $sort === 'delta') {
            $v = $row[$sort] ?? null;
            return $v !== null ? str_pad((string) (int) ((float) $v * 1000), 10, '0', STR_PAD_LEFT) : '';
        }
        return strtolower(trim((string) ($row[$sort] ?? '')));
    }

    /**
     * @param array<int, array<string, mixed>> $comparisonRows
     * @param array<string, mixed> $pagination
     * @param array<string, mixed> $impactSummary
     * @param array<string, mixed> $tests
     */
    private function renderResultsPartial(
        array $comparisonRows,
        array $pagination,
        array $impactSummary,
        array $tests
    ): string {
        ob_start();
        require dirname(__DIR__) . '/Views/evaluaciones/_results.php';
        $html = ob_get_clean();

        return is_string($html) ? $html : '';
    }

    private function collectEvaluacionFiltersFromRequest(Request $request, ?array $user, bool $canSeeAll): array
    {
        $filters = [
            'test_key' => (string) $request->input('test_key', ''),
            'phase' => (string) $request->input('phase', ''),
            'search' => trim((string) $request->input('search', '')),
            'document_number' => trim((string) $request->input('document_number', '')),
            'impact' => trim((string) $request->input('impact', '')),
            'subregion' => trim((string) $request->input('subregion', '')),
            'municipality' => trim((string) $request->input('municipality', '')),
            'date_from' => (string) $request->input('date_from', ''),
            'date_to' => (string) $request->input('date_to', ''),
        ];

        if (!$canSeeAll && $user && !empty($user['document_number'])) {
            $filters['document_number'] = (string) $user['document_number'];
            $filters['search'] = '';
        }

        return $filters;
    }

    /**
     * Restringe la consulta a las temÃ¡ticas del rol (excepto admin/coordinaciÃ³n).
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
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    private function applyComparisonFilters(array $rows, array $filters): array
    {
        $search = strtolower(trim((string) ($filters['search'] ?? '')));
        $impact = trim((string) ($filters['impact'] ?? ''));

        if ($search === '' && $impact === '') {
            return $rows;
        }

        return array_values(array_filter($rows, static function (array $row) use ($search, $impact): bool {
            if ($impact !== '' && (string) ($row['impact'] ?? '') !== $impact) {
                return false;
            }

            if ($search !== '') {
                $fullName = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
                $haystack = strtolower(implode(' ', [
                    (string) ($row['document_number'] ?? ''),
                    (string) ($row['first_name'] ?? ''),
                    (string) ($row['last_name'] ?? ''),
                    $fullName,
                ]));

                if (!str_contains($haystack, $search)) {
                    return false;
                }
            }

            return true;
        }));
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
        $lines[] = 'Equipo de PromociÃ³n y Prevención - AcciÃ³n en Territorio - Evaluaciones PRE/POST';
        $lines[] = 'Fecha de exportaciÃ³n: ' . date('d/m/Y H:i');
        $metaParts = [];
        if (!empty($filters['test_key']))        $metaParts[] = 'TemÃ¡tica: ' . $filters['test_key'];
        if (!empty($filters['document_number'])) $metaParts[] = 'Documento: ' . $filters['document_number'];
        if (!empty($filters['subregion']))       $metaParts[] = 'SubregiÃ³n: ' . $filters['subregion'];
        if (!empty($filters['municipality']))    $metaParts[] = 'Municipio: ' . $filters['municipality'];
        if (!empty($filters['date_from']))       $metaParts[] = 'Desde: ' . $filters['date_from'];
        if (!empty($filters['date_to']))         $metaParts[] = 'Hasta: ' . $filters['date_to'];
        $lines[] = $metaParts !== [] ? 'Filtros: ' . implode(' | ', $metaParts) : 'Sin filtros aplicados';
        $lines[] = '';

        $g = $impactSummary['global'] ?? null;
        if (is_array($g)) {
            $lines[] = 'Resumen impacto (solo personas con PRE y POST)';
            $lines[] = implode($sep, [
                'Ãmbito',
                'N con PRE+POST',
                'Con mejorÃ­a %',
                'Sin cambios %',
                'Sin mejorÃ­a %',
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
            'TemÃ¡tica',
            'Documento',
            'Nombres',
            'Apellidos',
            'SubregiÃ³n',
            'Municipio',
            'PRE %',
            'Fecha PRE',
            'POST %',
            'Fecha POST',
            'Delta puntos',
            'Resultado impacto',
        ]);

        foreach ($comparisonRows as $r) {
            $prePct = $r['pre_score'] !== null ? number_format((float) $r['pre_score'], 2, ',', '') : 'â€”';
            $postPct = $r['post_score'] !== null ? number_format((float) $r['post_score'], 2, ',', '') : 'â€”';
            $delta = isset($r['delta']) && $r['delta'] !== null
                ? number_format((float) $r['delta'], 2, ',', '')
                : 'â€”';
            $lines[] = implode($sep, [
                (string) ($r['test_name'] ?? ''),
                (string) ($r['document_number'] ?? ''),
                str_replace(["\r", "\n", ';'], [' ', ' ', ','], (string) ($r['first_name'] ?? '')),
                str_replace(["\r", "\n", ';'], [' ', ' ', ','], (string) ($r['last_name'] ?? '')),
                str_replace(["\r", "\n", ';'], [' ', ' ', ','], (string) ($r['subregion'] ?? '')),
                str_replace(["\r", "\n", ';'], [' ', ' ', ','], (string) ($r['municipality'] ?? '')),
                $prePct,
                (string) ($r['pre_at'] ?? 'â€”'),
                $postPct,
                (string) ($r['post_at'] ?? 'â€”'),
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
    private function buildEvaluacionesExcelHtml(array $comparisonRows, array $impactSummary, array $filters): string
    {
        $esc = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

        $filterParts = [];
        if (!empty($filters['test_key'])) {
            $filterParts[] = 'TemÃ¡tica: ' . (string) $filters['test_key'];
        }
        if (!empty($filters['document_number'])) {
            $filterParts[] = 'Documento: ' . (string) $filters['document_number'];
        }
        if (!empty($filters['subregion'])) {
            $filterParts[] = 'SubregiÃ³n: ' . (string) $filters['subregion'];
        }
        if (!empty($filters['municipality'])) {
            $filterParts[] = 'Municipio: ' . (string) $filters['municipality'];
        }
        if (!empty($filters['date_from'])) {
            $filterParts[] = 'Desde: ' . (string) $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $filterParts[] = 'Hasta: ' . (string) $filters['date_to'];
        }

        $summaryRows = '';
        $global = $impactSummary['global'] ?? null;
        if (is_array($global)) {
            $summaryRows .= '<tr style="font-weight:700;background:#e8f1eb">'
                . '<td>' . $esc((string) ($global['municipality'] ?? 'Total (filtro actual)')) . '</td>'
                . '<td>' . (int) ($global['con_ambos'] ?? 0) . '</td>'
                . '<td>' . $esc((string) ($global['pct_mejoria'] ?? '0')) . '%</td>'
                . '<td>' . $esc((string) ($global['pct_sin_cambios'] ?? '0')) . '%</td>'
                . '<td>' . $esc((string) ($global['pct_sin_mejoria'] ?? '0')) . '%</td>'
                . '</tr>';
        }
        foreach ($impactSummary['by_municipality'] ?? [] as $mun) {
            $summaryRows .= '<tr>'
                . '<td>' . $esc((string) ($mun['municipality'] ?? '')) . '</td>'
                . '<td>' . (int) ($mun['con_ambos'] ?? 0) . '</td>'
                . '<td>' . $esc((string) ($mun['pct_mejoria'] ?? '0')) . '%</td>'
                . '<td>' . $esc((string) ($mun['pct_sin_cambios'] ?? '0')) . '%</td>'
                . '<td>' . $esc((string) ($mun['pct_sin_mejoria'] ?? '0')) . '%</td>'
                . '</tr>';
        }

        $detailRows = '';
        foreach ($comparisonRows as $row) {
            $detailRows .= '<tr>'
                . '<td>' . $esc((string) ($row['test_name'] ?? '')) . '</td>'
                . '<td>' . $esc((string) ($row['document_number'] ?? '')) . '</td>'
                . '<td>' . $esc(trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''))) . '</td>'
                . '<td>' . $esc((string) ($row['subregion'] ?? '')) . '</td>'
                . '<td>' . $esc((string) ($row['municipality'] ?? '')) . '</td>'
                . '<td>' . ($row['pre_score'] !== null ? $esc(number_format((float) $row['pre_score'], 1) . '%') : 'Sin PRE') . '</td>'
                . '<td>' . $esc((string) ($row['pre_at'] ?? '')) . '</td>'
                . '<td>' . ($row['post_score'] !== null ? $esc(number_format((float) $row['post_score'], 1) . '%') : 'Sin POST') . '</td>'
                . '<td>' . $esc((string) ($row['post_at'] ?? '')) . '</td>'
                . '<td>' . ($row['delta'] !== null ? $esc(number_format((float) $row['delta'], 1)) : 'â€”') . '</td>'
                . '<td>' . $esc((string) ($row['impact_label'] ?? '')) . '</td>'
                . '</tr>';
        }

        return '<html><head><meta charset="UTF-8"></head><body>'
            . '<table border="0">'
            . '<tr><td colspan="12" style="font-size:16pt;font-weight:700;color:#2a5543">Resultado de evaluaciones PRE y POST</td></tr>'
            . '<tr><td colspan="12">Fecha de exportaciÃ³n: ' . $esc(date('d/m/Y H:i')) . '</td></tr>'
            . '<tr><td colspan="12">Filtros: ' . $esc($filterParts !== [] ? implode(' | ', $filterParts) : 'Sin filtros aplicados') . '</td></tr>'
            . '</table><br>'
            . '<table border="1" cellpadding="6" cellspacing="0">'
            . '<tr style="background:#2a5543;color:#ffffff;font-weight:700"><th>Municipio</th><th>Con PRE+POST</th><th>Con mejorÃ­a</th><th>Sin cambios</th><th>Sin mejorÃ­a</th></tr>'
            . ($summaryRows !== '' ? $summaryRows : '<tr><td colspan="5">Sin datos de impacto.</td></tr>')
            . '</table><br>'
            . '<table border="1" cellpadding="6" cellspacing="0">'
            . '<tr style="background:#2a5543;color:#ffffff;font-weight:700"><th>TemÃ¡tica</th><th>Documento</th><th>Persona</th><th>SubregiÃ³n</th><th>Municipio</th><th>PRE</th><th>Fecha PRE</th><th>POST</th><th>Fecha POST</th><th>Î”</th><th>Resultado impacto</th></tr>'
            . ($detailRows !== '' ? $detailRows : '<tr><td colspan="11">Sin registros.</td></tr>')
            . '</table>'
            . '</body></html>';
    }

    /**
     * @param array<string, array{name: string, color: string}> $testsFull
     * @return array{test_key: string, test_name: string, pre: ?array, post: ?array}|null
     */
    private function resolveSingleEvaluacionExport(Request $request, array $user, array $testsFull): ?array
    {
        $testKey = trim((string) $request->input('test_key', ''));
        $documentNumber = trim((string) $request->input('document_number', ''));
        $single = (string) $request->input('single', '');

        if ($single !== '1' || $testKey === '' || $documentNumber === '') {
            return null;
        }

        if (!isset($testsFull[$testKey]) || !self::userMayAccessTestKey($user, $testKey)) {
            Flash::set([
                'type' => 'error',
                'title' => 'ExportaciÃ³n no permitida',
                'message' => 'No puedes exportar esa temÃ¡tica de evaluaciÃ³n.',
            ]);

            return null;
        }

        if (!Auth::canViewAllModuleRecords($user) && trim((string) ($user['document_number'] ?? '')) !== $documentNumber) {
            Flash::set([
                'type' => 'error',
                'title' => 'ExportaciÃ³n no permitida',
                'message' => 'No puedes exportar evaluaciones de otra persona.',
            ]);

            return null;
        }

        $repo = new TestResponseRepository();
        $pre = $repo->findByPerson($testKey, 'pre', $documentNumber);
        $post = $repo->findByPerson($testKey, 'post', $documentNumber);

        if ($pre === null && $post === null) {
            Flash::set([
                'type' => 'error',
                'title' => 'Sin registros',
                'message' => 'No se encontraron evaluaciones PRE o POST para esa persona.',
            ]);

            return null;
        }

        return [
            'test_key' => $testKey,
            'test_name' => (string) ($testsFull[$testKey]['name'] ?? $testKey),
            'pre' => $pre,
            'post' => $post,
        ];
    }

    private function exportSingleEvaluacionExcel(string $testKey, string $testName, ?array $pre, ?array $post): Response
    {
        $html = $this->buildSingleEvaluacionExcelHtml($testKey, $testName, $pre, $post);
        $doc = (string) (($pre['document_number'] ?? '') !== '' ? $pre['document_number'] : ($post['document_number'] ?? ''));

        return new Response($html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="evaluacion_' . $doc . '_' . $testKey . '.xls"',
        ]);
    }

    private function exportSingleEvaluacionPdf(string $testKey, string $testName, ?array $pre, ?array $post): Response
    {
        $html = $this->buildSingleEvaluacionPdfHtml($testKey, $testName, $pre, $post);
        $doc = (string) (($pre['document_number'] ?? '') !== '' ? $pre['document_number'] : ($post['document_number'] ?? ''));

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="evaluacion_' . $doc . '_' . $testKey . '.pdf"',
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildSingleEvaluacionAnswerRows(string $testKey, string $phase, int $responseId): array
    {
        $repo = new TestResponseRepository();
        $answers = $repo->findAnswersByResponseId($responseId);
        $rows = [];

        foreach ($answers as $answer) {
            $questionNumber = (int) ($answer['question_number'] ?? 0);
            $selectedLetter = strtoupper((string) ($answer['selected_option'] ?? ''));
            $correctLetter = self::correctLetterForQuestion($testKey, $phase, $questionNumber);
            $question = EvaluacionesQuestionCatalog::getQuestion($testKey, $questionNumber);
            $options = is_array($question['options'] ?? null) ? $question['options'] : [];

            $rows[] = [
                'question_number' => $questionNumber,
                'question_text' => (string) ($question['text'] ?? ''),
                'selected_letter' => $selectedLetter,
                'selected_text' => (string) ($options[$selectedLetter] ?? ''),
                'correct_letter' => $correctLetter !== null ? strtoupper((string) $correctLetter) : '',
                'correct_text' => $correctLetter !== null ? (string) ($options[strtoupper((string) $correctLetter)] ?? '') : '',
                'is_correct' => !empty($answer['is_correct']),
            ];
        }

        return $rows;
    }

    private function buildSingleEvaluacionExcelHtml(string $testKey, string $testName, ?array $pre, ?array $post): string
    {
        $esc = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $identity = $pre ?? $post ?? [];
        $impact = EvaluacionesReportService::classifyImpact(
            $pre !== null ? (float) ($pre['score_percent'] ?? 0) : null,
            $post !== null ? (float) ($post['score_percent'] ?? 0) : null
        );

        $buildRows = function (?array $response, string $phase) use ($testKey, $esc): string {
            if ($response === null) {
                return '<tr><td colspan="6">Sin ' . strtoupper($phase) . ' registrado.</td></tr>';
            }

            $rows = $this->buildSingleEvaluacionAnswerRows($testKey, $phase, (int) ($response['id'] ?? 0));
            if ($rows === []) {
                return '<tr><td colspan="6">Sin respuestas registradas.</td></tr>';
            }

            $html = '';
            foreach ($rows as $row) {
                $html .= '<tr>'
                    . '<td>' . (int) ($row['question_number'] ?? 0) . '</td>'
                    . '<td>' . $esc((string) ($row['question_text'] ?? '')) . '</td>'
                    . '<td>' . $esc((string) ($row['selected_letter'] ?? '')) . '</td>'
                    . '<td>' . $esc((string) ($row['selected_text'] ?? '')) . '</td>'
                    . '<td>' . $esc((string) ($row['correct_letter'] ?? '')) . ' ' . $esc((string) ($row['correct_text'] ?? '')) . '</td>'
                    . '<td>' . ($row['is_correct'] ? 'Correcta' : 'Incorrecta') . '</td>'
                    . '</tr>';
            }

            return $html;
        };

        return '<html><head><meta charset="UTF-8"></head><body>'
            . '<table border="0">'
            . '<tr><td colspan="6" style="font-size:16pt;font-weight:700;color:#2a5543">ExportaciÃ³n individual de evaluaciÃ³n</td></tr>'
            . '<tr><td colspan="6">TemÃ¡tica: ' . $esc($testName) . '</td></tr>'
            . '<tr><td colspan="6">Persona: ' . $esc(trim((string) ($identity['first_name'] ?? '') . ' ' . (string) ($identity['last_name'] ?? ''))) . '</td></tr>'
            . '<tr><td colspan="6">Documento: ' . $esc((string) ($identity['document_number'] ?? '')) . ' | SubregiÃ³n: ' . $esc((string) ($identity['subregion'] ?? '')) . ' | Municipio: ' . $esc((string) ($identity['municipality'] ?? '')) . '</td></tr>'
            . '<tr><td colspan="6">Resultado impacto: ' . $esc((string) ($impact['label'] ?? '')) . '</td></tr>'
            . '</table><br>'
            . '<table border="1" cellpadding="6" cellspacing="0">'
            . '<tr style="background:#2a5543;color:#ffffff;font-weight:700"><th colspan="6">PRE - TEST</th></tr>'
            . '<tr style="background:#eef5f0;font-weight:700"><th>#</th><th>Pregunta</th><th>Respuesta</th><th>Texto respuesta</th><th>Correcta</th><th>Estado</th></tr>'
            . $buildRows($pre, 'pre')
            . '</table><br>'
            . '<table border="1" cellpadding="6" cellspacing="0">'
            . '<tr style="background:#2a5543;color:#ffffff;font-weight:700"><th colspan="6">POST - TEST</th></tr>'
            . '<tr style="background:#eef5f0;font-weight:700"><th>#</th><th>Pregunta</th><th>Respuesta</th><th>Texto respuesta</th><th>Correcta</th><th>Estado</th></tr>'
            . $buildRows($post, 'post')
            . '</table>'
            . '</body></html>';
    }

    private function buildSingleEvaluacionPdfHtml(string $testKey, string $testName, ?array $pre, ?array $post): string
    {
        $esc = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $identity = $pre ?? $post ?? [];
        $impact = EvaluacionesReportService::classifyImpact(
            $pre !== null ? (float) ($pre['score_percent'] ?? 0) : null,
            $post !== null ? (float) ($post['score_percent'] ?? 0) : null
        );
        $base = dirname(__DIR__, 2) . '/public/assets/img';
        $logoAntSrc = PdfImageHelper::imageDataUri($base . '/logoAntioquia.png');
        $logoHomoSrc = PdfImageHelper::imageDataUri($base . '/logoHomo.png');

        $renderSection = function (?array $response, string $phaseLabel, string $phaseKey) use ($testKey, $esc): string {
            $html = '<div class="phase-title">' . $esc($phaseLabel) . '</div>';
            if ($response === null) {
                return $html . '<p class="empty-copy">Sin ' . $esc($phaseLabel) . ' registrado.</p>';
            }

            $html .= '<p class="phase-meta">Puntaje: ' . $esc(number_format((float) ($response['score_percent'] ?? 0), 1)) . '% | Fecha: ' . $esc((string) ($response['created_at'] ?? '')) . '</p>';
            $rows = $this->buildSingleEvaluacionAnswerRows($testKey, $phaseKey, (int) ($response['id'] ?? 0));
            if ($rows === []) {
                return $html . '<p class="empty-copy">Sin respuestas registradas.</p>';
            }

            $html .= '<table class="detail"><thead><tr><th>#</th><th>Pregunta</th><th>Respuesta</th><th>Correcta</th><th>Estado</th></tr></thead><tbody>';
            foreach ($rows as $row) {
                $html .= '<tr>'
                    . '<td>' . (int) ($row['question_number'] ?? 0) . '</td>'
                    . '<td>' . $esc((string) ($row['question_text'] ?? '')) . '</td>'
                    . '<td>' . $esc((string) ($row['selected_letter'] ?? '')) . ' ' . $esc((string) ($row['selected_text'] ?? '')) . '</td>'
                    . '<td>' . $esc((string) ($row['correct_letter'] ?? '')) . ' ' . $esc((string) ($row['correct_text'] ?? '')) . '</td>'
                    . '<td>' . ($row['is_correct'] ? 'Correcta' : 'Incorrecta') . '</td>'
                    . '</tr>';
            }
            $html .= '</tbody></table>';

            return $html;
        };

        return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><style>
body{font-family:Arial,Helvetica,sans-serif;color:#223;padding:22px;font-size:11px}
.hdr{width:100%;border-collapse:collapse;margin-bottom:14px}
.hdr td{vertical-align:middle}
.logo{height:52px}
.title{font-size:20px;font-weight:700;color:#2a5543;text-align:center}
.subtitle{text-align:center;color:#4d5d56;font-size:11px}
.summary{background:#eef5f0;border:1px solid #c9ddd1;border-radius:8px;padding:12px;margin-bottom:16px}
.summary p{margin:4px 0}
.phase-title{background:#2a5543;color:#fff;font-weight:700;padding:7px 10px;margin-top:18px}
.phase-meta{margin:8px 0;color:#556}
.detail{width:100%;border-collapse:collapse}
.detail th{background:#2a5543;color:#fff;padding:6px;border:1px solid #c9ddd1;text-align:left;font-size:10px}
.detail td{padding:6px;border:1px solid #d8e2dc;vertical-align:top}
.empty-copy{padding:10px 0;color:#777}
</style></head><body>
<table class="hdr"><tr>
<td style="width:25%">' . ($logoAntSrc !== '' ? '<img src="' . $logoAntSrc . '" class="logo" alt="">' : '') . '</td>
<td style="width:50%"><div class="title">ExportaciÃ³n individual de evaluaciÃ³n</div><div class="subtitle">Equipo de PromociÃ³n y Prevención</div></td>
<td style="width:25%;text-align:right">' . ($logoHomoSrc !== '' ? '<img src="' . $logoHomoSrc . '" class="logo" alt="">' : '') . '</td>
</tr></table>
<div class="summary">
<p><strong>TemÃ¡tica:</strong> ' . $esc($testName) . '</p>
<p><strong>Persona:</strong> ' . $esc(trim((string) ($identity['first_name'] ?? '') . ' ' . (string) ($identity['last_name'] ?? ''))) . '</p>
<p><strong>Documento:</strong> ' . $esc((string) ($identity['document_number'] ?? '')) . '</p>
<p><strong>SubregiÃ³n:</strong> ' . $esc((string) ($identity['subregion'] ?? '')) . ' | <strong>Municipio:</strong> ' . $esc((string) ($identity['municipality'] ?? '')) . '</p>
<p><strong>Resultado impacto:</strong> ' . $esc((string) ($impact['label'] ?? '')) . '</p>
</div>'
            . $renderSection($pre, 'PRE - TEST', 'pre')
            . $renderSection($post, 'POST - TEST', 'post')
            . '</body></html>';
    }

    /**
     * @param array<int, array<string, mixed>> $comparisonRows
     * @param array{global: ?array, by_municipality: array} $impactSummary
     * @param array<string, mixed> $filters
     */
    private function buildEvaluacionesPdfHtml(array $comparisonRows, array $impactSummary, array $filters): string
    {
        $esc = static function (string $value): string {
            return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        };

        $meta = [];
        if (!empty($filters['test_key'])) {
            $meta[] = 'Temática: ' . $esc((string) $filters['test_key']);
        }
        if (!empty($filters['document_number'])) {
            $meta[] = 'Documento: ' . $esc((string) $filters['document_number']);
        }
        if (!empty($filters['subregion'])) {
            $meta[] = 'Subregión: ' . $esc((string) $filters['subregion']);
        }
        if (!empty($filters['municipality'])) {
            $meta[] = 'Municipio: ' . $esc((string) $filters['municipality']);
        }
        if (!empty($filters['date_from'])) {
            $meta[] = 'Desde: ' . $esc((string) $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $meta[] = 'Hasta: ' . $esc((string) $filters['date_to']);
        }

        $summaryRows = '';
        $global = $impactSummary['global'] ?? null;
        if (is_array($global)) {
            $summaryRows .= '<tr class="total-row"><td>' . $esc((string) ($global['municipality'] ?? 'Total (filtro actual)')) . '</td><td class="num">' . (int) ($global['con_ambos'] ?? 0) . '</td><td class="num">' . $esc((string) ($global['pct_mejoria'] ?? '0')) . '%</td><td class="num">' . $esc((string) ($global['pct_sin_cambios'] ?? '0')) . '%</td><td class="num">' . $esc((string) ($global['pct_sin_mejoria'] ?? '0')) . '%</td></tr>';
        }
        foreach ($impactSummary['by_municipality'] ?? [] as $mun) {
            $summaryRows .= '<tr><td>' . $esc((string) ($mun['municipality'] ?? '')) . '</td><td class="num">' . (int) ($mun['con_ambos'] ?? 0) . '</td><td class="num">' . $esc((string) ($mun['pct_mejoria'] ?? '0')) . '%</td><td class="num">' . $esc((string) ($mun['pct_sin_cambios'] ?? '0')) . '%</td><td class="num">' . $esc((string) ($mun['pct_sin_mejoria'] ?? '0')) . '%</td></tr>';
        }

        $detailRows = '';
        foreach ($comparisonRows as $row) {
            $prePct = $row['pre_score'] !== null ? number_format((float) $row['pre_score'], 1) . '%' : 'Sin PRE';
            $postPct = $row['post_score'] !== null ? number_format((float) $row['post_score'], 1) . '%' : 'Sin POST';
            $delta = isset($row['delta']) && $row['delta'] !== null ? number_format((float) $row['delta'], 1) : '?';

            $detailRows .= '<tr>'
                . '<td>' . $esc((string) ($row['test_name'] ?? '')) . '</td>'
                . '<td>' . $esc((string) ($row['document_number'] ?? '')) . '</td>'
                . '<td>' . $esc(trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''))) . '</td>'
                . '<td>' . $esc((string) ($row['subregion'] ?? '')) . '</td>'
                . '<td>' . $esc((string) ($row['municipality'] ?? '')) . '</td>'
                . '<td class="num">' . $esc($prePct) . '</td>'
                . '<td class="num">' . $esc($postPct) . '</td>'
                . '<td class="num">' . $esc($delta) . '</td>'
                . '<td>' . $esc((string) ($row['impact_label'] ?? '')) . '</td>'
                . '</tr>';
        }

        $base = dirname(__DIR__, 2) . '/public/assets/img';
        $logoAntSrc = PdfImageHelper::imageDataUri($base . '/logoAntioquia.png');
        $logoHomoSrc = PdfImageHelper::imageDataUri($base . '/logoHomo.png');
        $logoAnt = $logoAntSrc !== '' ? '<img src="' . $logoAntSrc . '" alt="" class="logo">' : '';
        $logoHomo = $logoHomoSrc !== '' ? '<img src="' . $logoHomoSrc . '" alt="" class="logo">' : '';

        return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Evaluaciones PRE y POST</title><style>'
            . 'body{font-family:Arial,Helvetica,sans-serif;font-size:9px;color:#1f2a24;margin:24px 26px;}'
            . 'table{width:100%;border-collapse:collapse;}'
            . '.header td{vertical-align:middle;}'
            . '.logo{height:38px;}'
            . '.title{text-align:center;font-size:16px;font-weight:700;color:#2a5543;}'
            . '.subtitle{text-align:center;font-size:9px;color:#5d6c65;}'
            . '.meta{margin:10px 0 14px 0;font-size:9px;}'
            . '.meta p{margin:2px 0;}'
            . '.section{margin-top:12px;}'
            . '.section-title{background:#2a5543;color:#fff;font-weight:700;padding:6px 8px;font-size:10px;}'
            . 'th{background:#eef5f0;color:#1f2a24;font-weight:700;border:1px solid #cfdad3;padding:4px 5px;text-align:left;}'
            . 'td{border:1px solid #d9e2dd;padding:4px 5px;}'
            . '.num{text-align:right;}'
            . '.total-row td{background:#edf5ef;font-weight:700;}'
            . '</style></head><body>'
            . '<table class="header"><tr><td style="width:25%">' . $logoAnt . '</td><td style="width:50%"><div class="title">Resultado de evaluaciones PRE y POST</div><div class="subtitle">Equipo de Promoción y Prevención</div></td><td style="width:25%;text-align:right">' . $logoHomo . '</td></tr></table>'
            . '<div class="meta"><p><strong>Fecha de exportación:</strong> ' . $esc(date('d/m/Y H:i')) . '</p><p><strong>Filtros:</strong> ' . ($meta !== [] ? implode(' | ', $meta) : 'Sin filtros aplicados') . '</p><p><strong>Registros exportados:</strong> ' . count($comparisonRows) . '</p></div>'
            . '<div class="section"><div class="section-title">Resultado impacto global por municipio</div><table><thead><tr><th>Municipio</th><th class="num">Con PRE+POST</th><th class="num">Con mejoría</th><th class="num">Sin cambios</th><th class="num">Sin mejoría</th></tr></thead><tbody>' . ($summaryRows !== '' ? $summaryRows : '<tr><td colspan="5">Sin datos de impacto.</td></tr>') . '</tbody></table></div>'
            . '<div class="section"><div class="section-title">Detalle comparativo por persona</div><table><thead><tr><th>Tem?tica</th><th>Documento</th><th>Persona</th><th>Subregión</th><th>Municipio</th><th class="num">PRE</th><th class="num">POST</th><th class="num">?</th><th>Resultado impacto</th></tr></thead><tbody>' . ($detailRows !== '' ? $detailRows : '<tr><td colspan="9">Sin registros.</td></tr>') . '</tbody></table></div>'
            . '</body></html>';
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
                'title' => 'NÃºmero de documento no vÃ¡lido',
                'message' => 'El nÃºmero de documento debe contener solo nÃºmeros.',
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
                'message' => 'Ya existe un PRE - TEST de Prevención de Adicciones para este nÃºmero de documento.',
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
                'message' => 'OcurriÃ³ un problema al registrar tus respuestas. Intenta nuevamente en unos minutos.',
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
                ['ok' => false, 'exists' => false, 'error' => 'ParÃ¡metros no vÃ¡lidos'],
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
                'title' => 'NÃºmero de documento no vÃ¡lido',
                'message' => 'El nÃºmero de documento debe contener solo nÃºmeros.',
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
                'message' => 'Ya existe un PRE - TEST de Hospitales para este nÃºmero de documento.',
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
                'message' => 'OcurriÃ³ un problema al registrar tus respuestas. Intenta nuevamente en unos minutos.',
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
                'title' => 'NÃºmero de documento no vÃ¡lido',
                'message' => 'El nÃºmero de documento debe contener solo nÃºmeros.',
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
                'message' => 'Para diligenciar el POST - TEST debes haber completado primero el PRE - TEST con el mismo nÃºmero de documento.',
            ]);

            return Response::redirect('/evaluaciones/hospitales/post');
        }

        // No permitir mÃ¡s de un POST para la misma persona
        if ($repo->existsForPerson($testKey, $phase, $documentNumber)) {
            Flash::set([
                'type' => 'error',
                'title' => 'Registro duplicado',
                'message' => 'Ya existe un POST - TEST de Hospitales para este nÃºmero de documento.',
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
                'message' => 'OcurriÃ³ un problema al registrar tus respuestas. Intenta nuevamente en unos minutos.',
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

        // Validaciones bÃ¡sicas
        if ($documentNumber === '' || !preg_match('/^[0-9]+$/', $documentNumber)) {
            Flash::set([
                'type' => 'error',
                'title' => 'NÃºmero de documento no vÃ¡lido',
                'message' => 'El nÃºmero de documento debe contener solo nÃºmeros.',
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
                'message' => 'Ya existe un PRE - TEST de Prevención de Violencias para este nÃºmero de documento.',
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
                'message' => 'OcurriÃ³ un problema al registrar tus respuestas. Intenta nuevamente en unos minutos.',
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
                'title' => 'NÃºmero de documento no vÃ¡lido',
                'message' => 'El nÃºmero de documento debe contener solo nÃºmeros.',
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
                'message' => 'Para diligenciar el POST - TEST debes haber completado primero el PRE - TEST con el mismo nÃºmero de documento.',
            ]);

            return Response::redirect('/evaluaciones/violencias/post');
        }

        // No permitir mÃ¡s de un POST para la misma persona
        if ($repo->existsForPerson($testKey, $phase, $documentNumber)) {
            Flash::set([
                'type' => 'error',
                'title' => 'Registro duplicado',
                'message' => 'Ya existe un POST - TEST de Prevención de Violencias para este nÃºmero de documento.',
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
                'message' => 'OcurriÃ³ un problema al registrar tus respuestas. Intenta nuevamente en unos minutos.',
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
                'title' => 'NÃºmero de documento no vÃ¡lido',
                'message' => 'El nÃºmero de documento debe contener solo nÃºmeros.',
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
                'message' => 'Ya existe un PRE - TEST de Prevención de Suicidios para este nÃºmero de documento.',
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
                'message' => 'OcurriÃ³ un problema al registrar tus respuestas. Intenta nuevamente en unos minutos.',
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
                'title' => 'NÃºmero de documento no vÃ¡lido',
                'message' => 'El nÃºmero de documento debe contener solo nÃºmeros.',
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
                'message' => 'Para diligenciar el POST - TEST debes haber completado primero el PRE - TEST con el mismo nÃºmero de documento.',
            ]);

            return Response::redirect('/evaluaciones/suicidios/post');
        }

        // No permitir mÃ¡s de un POST para la misma persona
        if ($repo->existsForPerson($testKey, $phase, $documentNumber)) {
            Flash::set([
                'type' => 'error',
                'title' => 'Registro duplicado',
                'message' => 'Ya existe un POST - TEST de Prevención de Suicidios para este nÃºmero de documento.',
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
                'message' => 'OcurriÃ³ un problema al registrar tus respuestas. Intenta nuevamente en unos minutos.',
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
                'title' => 'NÃºmero de documento no vÃ¡lido',
                'message' => 'El nÃºmero de documento debe contener solo nÃºmeros.',
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
                'message' => 'Para diligenciar el POST - TEST debes haber completado primero el PRE - TEST con el mismo nÃºmero de documento.',
            ]);

            return Response::redirect('/evaluaciones/adicciones/post');
        }

        // No permitir mÃ¡s de un POST para la misma persona
        if ($repo->existsForPerson($testKey, $phase, $documentNumber)) {
            Flash::set([
                'type' => 'error',
                'title' => 'Registro duplicado',
                'message' => 'Ya existe un POST - TEST de Prevención de Adicciones para este nÃºmero de documento.',
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
                'message' => 'OcurriÃ³ un problema al registrar tus respuestas. Intenta nuevamente en unos minutos.',
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
     * Mensaje de confirmaciÃ³n tras guardar un test (incluye nombre, apellidos y documento del evaluado).
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
            'Tu %s de %s ha sido registrado correctamente. Persona evaluada â€” Nombre: %s Â· Apellidos: %s Â· Documento: %s. Respuestas correctas: %d de %d (%.0f%%).',
            $phaseLabel,
            $topicName,
            $fn !== '' ? $fn : 'â€”',
            $ln !== '' ? $ln : 'â€”',
            $doc !== '' ? $doc : 'â€”',
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
                'message' => 'Los tests PRE/POST no estÃ¡n disponibles para tu perfil.',
            ]);

            return Response::redirect('/');
        }
        if (!self::userMayAccessTestKey($user, $testKey)) {
            Flash::set([
                'type' => 'warning',
                'title' => 'TemÃ¡tica no disponible',
                'message' => 'No tienes permiso para acceder a esta temÃ¡tica de evaluaciÃ³n.',
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


