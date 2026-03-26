<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\TrainingPlanRepository;
use App\Services\Auth;
use App\Services\Flash;
use App\Services\PdfImageHelper;
use App\Services\PdfService;

final class PlaneacionController
{
    private const INDEX_PAGE_SIZE = 20;
    private const FORM_OLD_INPUT_KEY = 'planeacion.form_old_input';

    private TrainingPlanRepository $repository;

    public function __construct()
    {
        $this->repository = new TrainingPlanRepository();
    }

    public function index(Request $request): Response
    {
        $user = Auth::user();
        if (!$user) {
            return Response::redirect('/login');
        }

        if (!$this->userCanAccessModule($user)) {
            return Response::view('errors/403', ['pageTitle' => 'Acceso denegado'], 403);
        }

        $roles = $user['roles'] ?? [];
        $isSpecialist = in_array('especialista', $roles, true);
        $isCoordinator = in_array('coordinadora', $roles, true) || in_array('coordinador', $roles, true);
        $isAdmin = in_array('admin', $roles, true);

        $canViewAll = Auth::canViewAllModuleRecords($user);
        $isAuditView = $canViewAll;

        $search = trim((string) $request->input('q', ''));
        $fromDate = trim((string) $request->input('from_date', ''));
        $toDate = trim((string) $request->input('to_date', ''));
        $sort = trim((string) $request->input('sort', 'created_at'));
        $dir = strtolower(trim((string) $request->input('dir', 'desc')));
        $currentPage = max(1, (int) $request->input('page', 1));

        if ($isAuditView) {
            $primaryRole = strtolower((string) ($user['role'] ?? (($roles[0] ?? '') ?: '')));
            $auditRoles = [];

            if ($isAdmin || $isCoordinator) {
                $auditRoles = [];
            } elseif ($isSpecialist) {
                if ($primaryRole === 'medico') {
                    $auditRoles = ['medico'];
                } elseif ($primaryRole === 'abogado') {
                    $auditRoles = ['abogado'];
                } elseif ($primaryRole === 'psicologo') {
                    $auditRoles = ['psicologo', 'profesional social', 'profesional_social'];
                }
            }

            $records = $this->repository->findForAudit($auditRoles);
        } else {
            $records = $this->repository->findForUser((int) $user['id']);
        }

        if (!$canViewAll) {
            $records = Auth::scopeRowsToOwnerUser($records, (int) $user['id']);
        }

        $records = $this->applyIndexFilters($records, $search, '', $fromDate, $toDate);

        $records = $this->sortRecords($records, $sort, $dir);
        $pagination = $this->paginateRecords($records, $currentPage, self::INDEX_PAGE_SIZE);
        $paginatedRecords = $pagination['items'];

        if ((string) $request->input('partial', '') === 'results') {
            $html = $this->renderResultsPartial($paginatedRecords, $pagination, $isAuditView, $user);

            return Response::json(['html' => $html]);
        }

        return Response::view('planeacion/index', [
            'pageTitle' => 'Planeación anual de capacitaciones',
            'records' => $paginatedRecords,
            'pagination' => $pagination,
            'isAuditView' => $isAuditView,
            'canCreateOwnRecord' => $this->userCanCreateOwnRecord($user),
        ]);
    }

    public function create(Request $request): Response
    {
        $user = Auth::user();
        if (!$user) {
            return Response::redirect('/login');
        }

        if (!$this->userCanAccessModule($user)) {
            return Response::view('errors/403', ['pageTitle' => 'Acceso denegado'], 403);
        }

        $professional = [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'roles' => $user['roles'] ?? [],
        ];
        $oldInput = $this->consumeOldInput('create');

        $primaryRole = (string) ($user['role'] ?? ($professional['roles'][0] ?? ''));

        return Response::view('planeacion/form', [
            'pageTitle' => 'Nueva planeación anual',
            'mode' => 'create',
            'plan' => null,
            'professional' => $professional,
            'role' => $primaryRole,
            'planYear' => (int) date('Y'),
            'oldInput' => $oldInput,
        ]);
    }

    public function store(Request $request): Response
    {
        $user = Auth::user();
        if (!$user) {
            return Response::redirect('/login');
        }

        if (!$this->userCanAccessModule($user)) {
            return Response::view('errors/403', ['pageTitle' => 'Acceso denegado'], 403);
        }

        $subregion = trim((string) $request->input('subregion'));
        $municipality = trim((string) $request->input('municipality'));
        $planYear = (int) ($request->input('plan_year') ?? date('Y'));

        $errors = [];

        if ($subregion === '') {
            $errors[] = 'Debes seleccionar la subregión.';
        }

        if ($municipality === '') {
            $errors[] = 'Debes seleccionar el municipio.';
        }

        [$payload, $monthErrors] = $this->buildPlanPayloadFromRequest($request);
        $errors = array_merge($errors, $monthErrors);

        if (!empty($errors)) {
            $this->flashOldInput('create', 0, $request);
            Flash::set([
                'type' => 'error',
                'title' => 'Revisa la planeación',
                'message' => implode("\n", $errors),
            ]);

            return Response::redirect('/planeacion/nueva');
        }

        $this->repository->create([
            'user_id' => (int) $user['id'],
            'professional_name' => (string) $user['name'],
            'professional_email' => (string) $user['email'],
            'professional_role' => (string) (($user['roles'] ?? [])[0] ?? ''),
            'subregion' => $subregion,
            'municipality' => $municipality,
            'plan_year' => $planYear,
            'editable' => 1,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);

        Flash::set([
            'type' => 'success',
            'title' => 'Planeación registrada',
            'message' => 'La planeación anual de capacitaciones se ha guardado correctamente.',
        ]);
        $this->clearOldInput();

        return Response::redirect('/planeacion');
    }

    public function export(Request $request): Response
    {
        $user = Auth::user();
        if (!$user) {
            return Response::redirect('/login');
        }

        if (!$this->userCanAccessModule($user)) {
            return Response::view('errors/403', ['pageTitle' => 'Acceso denegado'], 403);
        }

        $roles = $user['roles'] ?? [];
        $isSpecialist = in_array('especialista', $roles, true);
        $isCoordinator = in_array('coordinadora', $roles, true) || in_array('coordinador', $roles, true);
        $isAdmin = in_array('admin', $roles, true);

        $canViewAll = Auth::canViewAllModuleRecords($user);
        $isAuditView = $canViewAll;

        if ($isAuditView) {
            $primaryRole = strtolower((string) ($user['role'] ?? (($roles[0] ?? '') ?: '')));
            $auditRoles = [];

            if ($isAdmin || $isCoordinator) {
                $auditRoles = [];
            } elseif ($isSpecialist) {
                if ($primaryRole === 'medico') {
                    $auditRoles = ['medico'];
                } elseif ($primaryRole === 'abogado') {
                    $auditRoles = ['abogado'];
                } elseif ($primaryRole === 'psicologo') {
                    $auditRoles = ['psicologo', 'profesional social', 'profesional_social'];
                }
            }

            $records = $this->repository->findForAudit($auditRoles);
        } else {
            $records = $this->repository->findForUser((int) $user['id']);
        }

        if (!$canViewAll) {
            $records = Auth::scopeRowsToOwnerUser($records, (int) $user['id']);
        }

        $search = trim((string) $request->input('q', ''));
        $fromDate = trim((string) $request->input('from_date', ''));
        $toDate = trim((string) $request->input('to_date', ''));
        $sort = trim((string) $request->input('sort', 'created_at'));
        $dir = strtolower(trim((string) $request->input('dir', 'desc')));
        $format = strtolower(trim((string) $request->input('format', 'excel')));

        $records = $this->applyIndexFilters($records, $search, '', $fromDate, $toDate);
        $records = $this->sortRecords($records, $sort, $dir);

        if ($records === []) {
            Flash::set([
                'type' => 'info',
                'title' => 'Sin registros para exportar',
                'message' => 'Aún no tienes planeaciones anuales registradas para exportar.',
            ]);

            return Response::redirect('/planeacion');
        }

        $months = [
            'enero' => 'Enero',
            'febrero' => 'Febrero',
            'marzo' => 'Marzo',
            'abril' => 'Abril',
            'mayo' => 'Mayo',
            'junio' => 'Junio',
            'julio' => 'Julio',
            'agosto' => 'Agosto',
            'septiembre' => 'Septiembre',
            'octubre' => 'Octubre',
            'noviembre' => 'Noviembre',
            'diciembre' => 'Diciembre',
        ];

        if ($format === 'pdf') {
            @ini_set('memory_limit', '768M');
            @set_time_limit(240);

            $exportRows = $this->buildExportRows($records, $months, false);
            $html = $this->buildUltraCompactPdfHtml($exportRows, [
                'q' => $search,
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ]);

            try {
                $pdfBinary = PdfService::renderHtml(
                    $html,
                    'P',
                    'Planeación anual de capacitaciones'
                );

                return new Response(
                    $pdfBinary,
                    200,
                    [
                        'Content-Type' => 'application/pdf',
                        'Content-Disposition' => 'attachment; filename="planeacion_capacitaciones_' . date('Ymd_His') . '.pdf"',
                    ]
                );
            } catch (\Throwable $exception) {
                error_log('[Planeacion PDF] ' . $exception->getMessage());

                Flash::set([
                    'type' => 'error',
                    'title' => 'No fue posible exportar el PDF',
                    'message' => 'La exportación fue demasiado pesada para el servidor. Intenté una versión más liviana; por favor intenta nuevamente.',
                ]);

                return Response::redirect('/planeacion');
            }
        }

        $lines = [];
        $lines[] = implode(';', [
            'Año',
            'Profesional',
            'Rol',
            'Subregión',
            'Municipio',
            'Mes',
            'Temas / módulos',
            'Población objetivo',
        ]);

        foreach ($this->buildExportRows($records, $months) as $exportRow) {
            $row = [
                (string) ($exportRow['year'] ?? ''),
                (string) ($exportRow['professional'] ?? ''),
                (string) ($exportRow['role'] ?? ''),
                (string) ($exportRow['subregion'] ?? ''),
                (string) ($exportRow['municipality'] ?? ''),
                (string) ($exportRow['month'] ?? ''),
                (string) ($exportRow['topics_text'] ?? ''),
                (string) ($exportRow['population'] ?? ''),
            ];

            $lines[] = implode(';', array_map(static function (string $value): string {
                $escaped = str_replace('"', '""', $value);
                return '"' . $escaped . '"';
            }, $row));
        }

        $csvContent = "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n";

        $filename = sprintf('planeacion_capacitaciones_%s.csv', date('Ymd_His'));

        return new Response(
            $csvContent,
            200,
            [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]
        );
    }

    /**
     * @param array<int, array<string, mixed>> $records
     * @param array<string, string> $months
     * @param array<string, string> $filters
     */
    private function buildPdfHtml(array $records, array $months, array $filters): string
    {
        $esc = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

        $base = dirname(__DIR__, 2) . '/public/assets/img';
        $logoAntioquia = PdfImageHelper::imageDataUri($base . '/logoAntioquia.png');
        $logoHomo = PdfImageHelper::imageDataUri($base . '/logoHomo.png');

        $filterLabels = [];
        if (($filters['q'] ?? '') !== '') {
            $filterLabels[] = 'Buscar: ' . (string) $filters['q'];
        }
        if (($filters['state'] ?? '') !== '') {
            $filterLabels[] = 'Estado: ' . (string) $filters['state'];
        }
        if (($filters['from_date'] ?? '') !== '') {
            $filterLabels[] = 'Desde: ' . (string) $filters['from_date'];
        }
        if (($filters['to_date'] ?? '') !== '') {
            $filterLabels[] = 'Hasta: ' . (string) $filters['to_date'];
        }

        $rowsHtml = '';
        foreach ($records as $plan) {
            $payload = [];
            if (!empty($plan['payload'])) {
                $decoded = json_decode((string) $plan['payload'], true);
                if (is_array($decoded)) {
                    $payload = $decoded;
                }
            }

            foreach ($months as $key => $label) {
                $monthData = $payload[$key] ?? ['topics' => [], 'population' => ''];
                $topics = $monthData['topics'] ?? [];
                $population = trim((string) ($monthData['population'] ?? ''));
                if (!is_array($topics)) {
                    $topics = [];
                }

                if ($topics === [] && $population === '') {
                    continue;
                }

                $rowsHtml .= '<tr>'
                    . '<td>' . $esc((string) ($plan['plan_year'] ?? '')) . '</td>'
                    . '<td>' . $esc((string) ($plan['professional_name'] ?? '')) . '</td>'
                    . '<td>' . $esc((string) ($plan['professional_role'] ?? '')) . '</td>'
                    . '<td>' . $esc((string) ($plan['subregion'] ?? '')) . '</td>'
                    . '<td>' . $esc((string) ($plan['municipality'] ?? '')) . '</td>'
                    . '<td>' . $esc($label) . '</td>'
                    . '<td>' . $esc(implode(' | ', array_map('strval', $topics))) . '</td>'
                    . '<td>' . $esc($population) . '</td>'
                    . '</tr>';
            }
        }

        $headerLogos = '<td style="width:28%;">' . ($logoAntioquia !== '' ? '<img src="' . $esc($logoAntioquia) . '" alt="Gobernación de Antioquia" style="height:46px;width:auto;">' : '') . '</td>'
            . '<td style="width:44%;text-align:center;"><div class="title">Planeación anual de capacitaciones</div><div class="subtitle">Equipo de Promoción y Prevención</div></td>'
            . '<td style="width:28%;text-align:right;">' . ($logoHomo !== '' ? '<img src="' . $esc($logoHomo) . '" alt="HOMO" style="height:46px;width:auto;">' : '') . '</td>';

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Planeación anual</title><style>'
            . 'body{font-family:Arial,sans-serif;color:#203246;font-size:11px;margin:18px;}'
            . '.header{width:100%;border-collapse:collapse;margin-bottom:14px;}'
            . '.title{font-size:20px;font-weight:700;color:#214f43;margin-bottom:4px;}'
            . '.subtitle{font-size:11px;color:#58708b;}'
            . '.meta{margin:0 0 14px;padding:12px 14px;background:#f4f8fc;border:1px solid #d8e3ef;border-radius:10px;}'
            . '.meta p{margin:0 0 4px;}'
            . 'table.report{width:100%;border-collapse:collapse;}'
            . 'table.report th{background:#2f6b57;color:#fff;border:1px solid #d7e1ec;padding:7px 6px;text-align:left;font-size:10px;}'
            . 'table.report td{border:1px solid #d7e1ec;padding:6px;vertical-align:top;}'
            . 'table.report tr:nth-child(even) td{background:#fbfdff;}'
            . '.footer{margin-top:12px;font-size:10px;color:#64748b;text-align:right;}'
            . '</style></head><body>'
            . '<table class="header"><tr>' . $headerLogos . '</tr></table>'
            . '<div class="meta"><p><strong>Fecha de exportación:</strong> ' . $esc(date('d/m/Y H:i')) . '</p>'
            . '<p><strong>Registros exportados:</strong> ' . $esc((string) count($records)) . '</p>'
            . '<p><strong>Filtros:</strong> ' . $esc($filterLabels !== [] ? implode(' | ', $filterLabels) : 'Sin filtros aplicados') . '</p></div>'
            . '<table class="report"><thead><tr><th>Año</th><th>Profesional</th><th>Rol</th><th>Subregión</th><th>Municipio</th><th>Mes</th><th>Temas / módulos</th><th>Población objetivo</th></tr></thead><tbody>'
            . $rowsHtml
            . '</tbody></table><p class="footer">Documento generado automáticamente desde la plataforma Equipo de Promoción y Prevención.</p></body></html>';
    }

    /**
     * @param array<int, array<string, string>> $exportRows
     * @param array<string, string> $filters
     */
    private function buildCompactPdfHtml(array $exportRows, array $filters): string
    {
        $esc = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

        $base = dirname(__DIR__, 2) . '/public/assets/img';
        $logoAntioquia = PdfImageHelper::imageDataUri($base . '/logoAntioquia.png');
        $logoHomo = PdfImageHelper::imageDataUri($base . '/logoHomo.png');

        $filterLabels = [];
        if (($filters['q'] ?? '') !== '') {
            $filterLabels[] = 'Buscar: ' . (string) $filters['q'];
        }
        if (($filters['state'] ?? '') !== '') {
            $filterLabels[] = 'Estado: ' . (string) $filters['state'];
        }
        if (($filters['from_date'] ?? '') !== '') {
            $filterLabels[] = 'Desde: ' . (string) $filters['from_date'];
        }
        if (($filters['to_date'] ?? '') !== '') {
            $filterLabels[] = 'Hasta: ' . (string) $filters['to_date'];
        }

        $groupedRows = [];
        foreach ($exportRows as $row) {
            $groupKey = implode('|', [
                (string) ($row['year'] ?? ''),
                (string) ($row['professional'] ?? ''),
                (string) ($row['role'] ?? ''),
                (string) ($row['subregion'] ?? ''),
                (string) ($row['municipality'] ?? ''),
            ]);

            if (!isset($groupedRows[$groupKey])) {
                $groupedRows[$groupKey] = [
                    'year' => (string) ($row['year'] ?? ''),
                    'professional' => (string) ($row['professional'] ?? ''),
                    'role' => (string) ($row['role'] ?? ''),
                    'subregion' => (string) ($row['subregion'] ?? ''),
                    'municipality' => (string) ($row['municipality'] ?? ''),
                    'months' => [],
                ];
            }

            $groupedRows[$groupKey]['months'][] = [
                'month' => (string) ($row['month'] ?? ''),
                'topics' => (string) ($row['topics_text'] ?? ''),
                'population' => (string) ($row['population'] ?? ''),
            ];
        }

        $recordsHtml = '';
        foreach ($groupedRows as $group) {
            $monthsHtml = '';
            foreach ($group['months'] as $monthRow) {
                $monthsHtml .= '<div class="month-row">'
                    . '<div class="month-name">' . $esc((string) $monthRow['month']) . '</div>'
                    . '<div class="month-detail"><strong>Temas:</strong> ' . $esc((string) $monthRow['topics']) . '</div>'
                    . '<div class="month-detail"><strong>Población objetivo:</strong> ' . $esc((string) $monthRow['population']) . '</div>'
                    . '</div>';
            }

            $recordsHtml .= '<div class="record-card">'
                . '<div class="record-title">' . $esc((string) $group['professional']) . '</div>'
                . '<div class="record-meta">'
                . '<strong>Año:</strong> ' . $esc((string) $group['year'])
                . ' | <strong>Rol:</strong> ' . $esc((string) $group['role'])
                . ' | <strong>Subregión:</strong> ' . $esc((string) $group['subregion'])
                . ' | <strong>Municipio:</strong> ' . $esc((string) $group['municipality'])
                . '</div>'
                . $monthsHtml
                . '</div>';
        }

        $headerLogos = '<td style="width:28%;">' . ($logoAntioquia !== '' ? '<img src="' . $esc($logoAntioquia) . '" alt="Gobernación de Antioquia" style="height:44px;width:auto;">' : '') . '</td>'
            . '<td style="width:44%;text-align:center;"><div class="title">Planeación anual de capacitaciones</div><div class="subtitle">Equipo de Promoción y Prevención</div></td>'
            . '<td style="width:28%;text-align:right;">' . ($logoHomo !== '' ? '<img src="' . $esc($logoHomo) . '" alt="HOMO" style="height:44px;width:auto;">' : '') . '</td>';

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Planeación anual</title><style>'
            . 'body{font-family:Arial,sans-serif;color:#203246;font-size:10px;margin:18px;}'
            . '.header{width:100%;border-collapse:collapse;margin-bottom:12px;}'
            . '.title{font-size:18px;font-weight:700;color:#214f43;margin-bottom:3px;}'
            . '.subtitle{font-size:10px;color:#58708b;}'
            . '.meta{margin:0 0 12px;padding:10px 12px;background:#f4f8fc;border:1px solid #d8e3ef;}'
            . '.meta p{margin:0 0 3px;}'
            . '.record-card{margin:0 0 10px;padding:10px;border:1px solid #d7e1ec;page-break-inside:avoid;}'
            . '.record-title{font-size:12px;font-weight:700;color:#214f43;margin-bottom:4px;}'
            . '.record-meta{font-size:10px;color:#425466;margin-bottom:6px;}'
            . '.month-row{padding:6px 0;border-top:1px solid #e5edf5;}'
            . '.month-name{font-weight:700;color:#2f6b57;margin-bottom:2px;}'
            . '.month-detail{margin-bottom:2px;line-height:1.35;}'
            . '.footer{margin-top:12px;font-size:9px;color:#64748b;text-align:right;}'
            . '</style></head><body>'
            . '<table class="header"><tr>' . $headerLogos . '</tr></table>'
            . '<div class="meta"><p><strong>Fecha de exportación:</strong> ' . $esc(date('d/m/Y H:i')) . '</p>'
            . '<p><strong>Registros exportados:</strong> ' . $esc((string) count($groupedRows)) . '</p>'
            . '<p><strong>Filtros:</strong> ' . $esc($filterLabels !== [] ? implode(' | ', $filterLabels) : 'Sin filtros aplicados') . '</p></div>'
            . $recordsHtml
            . '<p class="footer">Documento generado automáticamente desde la plataforma Equipo de Promoción y Prevención.</p></body></html>';
    }

    /**
     * @param array<int, array<string, mixed>> $records
     * @param array<string, string> $months
     * @return array<int, array<string, string>>
     */
    private function buildExportRows(array $records, array $months, bool $includeEmptyMonths = true): array
    {
        $rows = [];

        foreach ($records as $plan) {
            $payload = [];
            if (!empty($plan['payload'])) {
                $decoded = json_decode((string) $plan['payload'], true);
                if (is_array($decoded)) {
                    $payload = $decoded;
                }
            }

            foreach ($months as $key => $label) {
                $monthData = $payload[$key] ?? ['topics' => [], 'population' => ''];
                $topics = $monthData['topics'] ?? [];
                $population = trim((string) ($monthData['population'] ?? ''));

                if (!is_array($topics)) {
                    $topics = [];
                }

                $topicsText = implode(' | ', array_map('strval', $topics));
                if (!$includeEmptyMonths && $topicsText === '' && $population === '') {
                    continue;
                }

                $rows[] = [
                    'year' => (string) ($plan['plan_year'] ?? ''),
                    'professional' => (string) ($plan['professional_name'] ?? ''),
                    'role' => (string) ($plan['professional_role'] ?? ''),
                    'subregion' => (string) ($plan['subregion'] ?? ''),
                    'municipality' => (string) ($plan['municipality'] ?? ''),
                    'month' => (string) $label,
                    'topics_text' => $topicsText,
                    'population' => $population,
                ];
            }
        }

        return $rows;
    }

    /**
     * @param array<int, array<string, string>> $exportRows
     * @param array<string, string> $filters
     */
    private function buildUltraCompactPdfHtml(array $exportRows, array $filters): string
    {
        $esc = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

        $base = dirname(__DIR__, 2) . '/public/assets/img';
        $logoAntioquia = PdfImageHelper::imageDataUri($base . '/logoAntioquia.png');
        $logoHomo = PdfImageHelper::imageDataUri($base . '/logoHomo.png');

        $filterLabels = [];
        if (($filters['q'] ?? '') !== '') {
            $filterLabels[] = 'Buscar: ' . (string) $filters['q'];
        }
        if (($filters['from_date'] ?? '') !== '') {
            $filterLabels[] = 'Desde: ' . (string) $filters['from_date'];
        }
        if (($filters['to_date'] ?? '') !== '') {
            $filterLabels[] = 'Hasta: ' . (string) $filters['to_date'];
        }

        $rowsHtml = '';
        foreach ($exportRows as $row) {
            $rowsHtml .= '<tr>'
                . '<td>' . $esc((string) ($row['year'] ?? '')) . '</td>'
                . '<td>' . $esc((string) ($row['professional'] ?? '')) . '</td>'
                . '<td>' . $esc((string) ($row['role'] ?? '')) . '</td>'
                . '<td>' . $esc((string) ($row['subregion'] ?? '')) . '</td>'
                . '<td>' . $esc((string) ($row['municipality'] ?? '')) . '</td>'
                . '<td>' . $esc((string) ($row['month'] ?? '')) . '</td>'
                . '<td>' . $esc((string) ($row['topics_text'] ?? '')) . '</td>'
                . '<td>' . $esc((string) ($row['population'] ?? '')) . '</td>'
                . '</tr>';
        }

        $headerLogos = '<td style="width:22%;">' . ($logoAntioquia !== '' ? '<img src="' . $esc($logoAntioquia) . '" alt="Gobernación de Antioquia" style="height:34px;width:auto;">' : '') . '</td>'
            . '<td style="width:56%;text-align:center;"><div class="title">Planeación anual de capacitaciones</div><div class="subtitle">Equipo de Promoción y Prevención</div></td>'
            . '<td style="width:22%;text-align:right;">' . ($logoHomo !== '' ? '<img src="' . $esc($logoHomo) . '" alt="HOMO" style="height:34px;width:auto;">' : '') . '</td>';

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Planeación anual</title><style>'
            . 'body{font-family:Arial,sans-serif;color:#203246;font-size:9px;margin:10px;}'
            . '.header{width:100%;border-collapse:collapse;margin-bottom:8px;}'
            . '.title{font-size:15px;font-weight:700;color:#214f43;}'
            . '.subtitle{font-size:9px;color:#58708b;}'
            . '.meta{margin:0 0 8px;padding:6px 8px;background:#f4f8fc;border:1px solid #d8e3ef;font-size:9px;}'
            . '.meta p{margin:0 0 2px;}'
            . '.report{width:100%;border-collapse:collapse;table-layout:fixed;}'
            . '.report th{background:#2f6b57;color:#ffffff;border:1px solid #d7e1ec;padding:4px;text-align:left;font-size:8px;}'
            . '.report td{border:1px solid #d7e1ec;padding:4px;vertical-align:top;font-size:8px;word-wrap:break-word;}'
            . '.footer{margin-top:8px;font-size:8px;color:#64748b;text-align:right;}'
            . '</style></head><body>'
            . '<table class="header"><tr>' . $headerLogos . '</tr></table>'
            . '<div class="meta"><p><strong>Fecha de exportación:</strong> ' . $esc(date('d/m/Y H:i')) . '</p>'
            . '<p><strong>Filas exportadas:</strong> ' . $esc((string) count($exportRows)) . '</p>'
            . '<p><strong>Filtros:</strong> ' . $esc($filterLabels !== [] ? implode(' | ', $filterLabels) : 'Sin filtros aplicados') . '</p></div>'
            . '<table class="report"><thead><tr>'
            . '<th style="width:6%;">Año</th>'
            . '<th style="width:18%;">Profesional</th>'
            . '<th style="width:10%;">Rol</th>'
            . '<th style="width:12%;">Subregión</th>'
            . '<th style="width:12%;">Municipio</th>'
            . '<th style="width:8%;">Mes</th>'
            . '<th style="width:18%;">Temas</th>'
            . '<th style="width:16%;">Población objetivo</th>'
            . '</tr></thead><tbody>' . $rowsHtml . '</tbody></table>'
            . '<p class="footer">Documento generado automáticamente desde la plataforma Equipo de Promoción y Prevención.</p></body></html>';
    }

    public function edit(Request $request): Response
    {
        $user = Auth::user();
        if (!$user) {
            return Response::redirect('/login');
        }

        if (!$this->userCanCreateOwnRecord($user)) {
            return Response::view('errors/403', ['pageTitle' => 'Acceso denegado'], 403);
        }

        $id = (int) $request->input('id', 0);
        if ($id <= 0) {
            return Response::redirect('/planeacion');
        }

        $plan = $this->repository->findById($id);
        if (!$plan || (int) $plan['user_id'] !== (int) $user['id']) {
            Flash::set([
                'type' => 'error',
                'title' => 'No autorizado',
                'message' => 'No puedes editar esta planeación.',
            ]);

            return Response::redirect('/planeacion');
        }

        if (empty($plan['editable'])) {
            Flash::set([
                'type' => 'info',
                'title' => 'Edición no permitida',
                'message' => 'Esta planeación ya fue aprobada por el especialista y no puede modificarse.',
            ]);

            return Response::redirect('/planeacion');
        }

        $professional = [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
        ];
        $oldInput = $this->consumeOldInput('edit', $id);

        return Response::view('planeacion/form', [
            'pageTitle' => 'Editar planeación anual',
            'mode' => 'edit',
            'plan' => $plan,
            'professional' => $professional,
            'role' => (string) ($user['role'] ?? (($user['roles'] ?? [])[0] ?? '')),
            'planYear' => (int) ($plan['plan_year'] ?? date('Y')),
            'oldInput' => $oldInput,
        ]);
    }

    public function update(Request $request): Response
    {
        $user = Auth::user();
        if (!$user) {
            return Response::redirect('/login');
        }

        if (!$this->userCanCreateOwnRecord($user)) {
            return Response::view('errors/403', ['pageTitle' => 'Acceso denegado'], 403);
        }

        $id = (int) $request->input('id', 0);
        if ($id <= 0) {
            return Response::redirect('/planeacion');
        }

        $plan = $this->repository->findById($id);
        if (!$plan || (int) $plan['user_id'] !== (int) $user['id']) {
            Flash::set([
                'type' => 'error',
                'title' => 'No autorizado',
                'message' => 'No puedes editar esta planeación.',
            ]);

            return Response::redirect('/planeacion');
        }

        if (empty($plan['editable'])) {
            Flash::set([
                'type' => 'info',
                'title' => 'Edición no permitida',
                'message' => 'Esta planeación ya fue aprobada por el especialista y no puede modificarse.',
            ]);

            return Response::redirect('/planeacion');
        }

        $subregion = trim((string) $request->input('subregion'));
        $municipality = trim((string) $request->input('municipality'));
        $planYear = (int) ($request->input('plan_year') ?? ($plan['plan_year'] ?? date('Y')));

        $errors = [];

        if ($subregion === '') {
            $errors[] = 'Debes seleccionar la subregión.';
        }

        if ($municipality === '') {
            $errors[] = 'Debes seleccionar el municipio.';
        }

        [$payload, $monthErrors] = $this->buildPlanPayloadFromRequest($request);
        $errors = array_merge($errors, $monthErrors);

        if (!empty($errors)) {
            $this->flashOldInput('edit', $id, $request);
            Flash::set([
                'type' => 'error',
                'title' => 'Revisa la planeación',
                'message' => implode("\n", $errors),
            ]);

            return Response::redirect('/planeacion/editar?id=' . $id);
        }

        $this->repository->update($id, [
            'subregion' => $subregion,
            'municipality' => $municipality,
            'plan_year' => $planYear,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);

        Flash::set([
            'type' => 'success',
            'title' => 'Planeación actualizada',
            'message' => 'La planeación anual de capacitaciones se ha actualizado correctamente.',
        ]);
        $this->clearOldInput();

        return Response::redirect('/planeacion');
    }

    private function userCanAccessModule(array $user): bool
    {
        $roles = $user['roles'] ?? [];
        $allowed = ['abogado', 'Medico', 'medico', 'psicologo', 'admin', 'especialista', 'coordinadora', 'coordinador'];

        return (bool) array_intersect($roles, $allowed);
    }

    private function userCanCreateOwnRecord(array $user): bool
    {
        $roles = $user['roles'] ?? [];
        $allowed = ['abogado', 'Medico', 'medico', 'psicologo', 'especialista'];

        return (bool) array_intersect($roles, $allowed);
    }

    /**
     * @param array<int, array<string, mixed>> $records
     * @return array<string, mixed>
     */
    private function paginateRecords(array $records, int $page, int $perPage): array
    {
        $totalItems = count($records);
        $totalPages = max(1, (int) ceil($totalItems / $perPage));
        $currentPage = min(max(1, $page), $totalPages);
        $offset = ($currentPage - 1) * $perPage;

        return [
            'items' => array_slice($records, $offset, $perPage),
            'total_items' => $totalItems,
            'per_page' => $perPage,
            'current_page' => $currentPage,
            'total_pages' => $totalPages,
            'from' => $totalItems === 0 ? 0 : $offset + 1,
            'to' => min($offset + $perPage, $totalItems),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $records
     * @return array<int, array<string, mixed>>
     */
    private function sortRecords(array $records, string $sort, string $dir): array
    {
        $allowedSorts = ['plan_year', 'professional_name', 'subregion', 'municipality', 'state', 'created_at'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }

        $direction = $dir === 'asc' ? 'asc' : 'desc';

        usort($records, function (array $left, array $right) use ($sort, $direction): int {
            $leftValue = $this->extractSortValue($left, $sort);
            $rightValue = $this->extractSortValue($right, $sort);

            if ($leftValue === $rightValue) {
                return 0;
            }

            $comparison = $leftValue <=> $rightValue;

            return $direction === 'asc' ? $comparison : -$comparison;
        });

        return $records;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function extractSortValue(array $row, string $sort): int|string
    {
        if ($sort === 'state') {
            return !empty($row['editable']) ? 'editable' : 'aprobada';
        }

        if ($sort === 'plan_year') {
            return (int) ($row['plan_year'] ?? 0);
        }

        return strtolower(trim((string) ($row[$sort] ?? '')));
    }

    /**
     * @param array<int, array<string, mixed>> $records
     * @return array<int, array<string, mixed>>
     */
    private function applyIndexFilters(
        array $records,
        string $search,
        string $stateFilter,
        string $fromDate,
        string $toDate
    ): array {
        if ($search === '' && $stateFilter === '' && $fromDate === '' && $toDate === '') {
            return $records;
        }

        return array_values(array_filter($records, static function (array $row) use ($search, $stateFilter, $fromDate, $toDate): bool {
            if ($stateFilter !== '') {
                $isEditable = !empty($row['editable']);
                $state = $isEditable ? 'Editable' : 'Aprobada';
                if ($state !== $stateFilter) {
                    return false;
                }
            }

            $created = trim((string) ($row['created_at'] ?? ''));
            $createdDate = $created !== '' ? substr($created, 0, 10) : '';

            if ($fromDate !== '' && $createdDate !== '' && $createdDate < $fromDate) {
                return false;
            }

            if ($toDate !== '' && $createdDate !== '' && $createdDate > $toDate) {
                return false;
            }

            if ($search === '') {
                return true;
            }

            $haystack = implode(' ', [
                (string) ($row['professional_name'] ?? ''),
                (string) ($row['professional_role'] ?? ''),
                (string) ($row['subregion'] ?? ''),
                (string) ($row['municipality'] ?? ''),
                (string) ($row['plan_year'] ?? ''),
            ]);

            return stripos($haystack, $search) !== false;
        }));
    }

    /**
     * @param array<int, array<string, mixed>> $records
     * @param array<string, mixed> $pagination
     * @param array<string, mixed> $user
     */
    private function renderResultsPartial(array $records, array $pagination, bool $isAuditView, array $user): string
    {
        $isAuditViewLocal = $isAuditView;
        $currentUser = $user;

        ob_start();
        require dirname(__DIR__) . '/Views/planeacion/_results.php';
        $html = ob_get_clean();

        return is_string($html) ? $html : '';
    }

    private function flashOldInput(string $mode, int $recordId, Request $request): void
    {
        $_SESSION[self::FORM_OLD_INPUT_KEY] = [
            'mode' => $mode,
            'record_id' => $recordId,
            'data' => $_POST,
        ];
    }

    private function consumeOldInput(string $mode, int $recordId = 0): array
    {
        $flash = $_SESSION[self::FORM_OLD_INPUT_KEY] ?? null;
        if (!is_array($flash)) {
            return [];
        }

        unset($_SESSION[self::FORM_OLD_INPUT_KEY]);

        if (($flash['mode'] ?? null) !== $mode) {
            return [];
        }

        if ((int) ($flash['record_id'] ?? 0) !== $recordId) {
            return [];
        }

        $data = $flash['data'] ?? null;

        return is_array($data) ? $data : [];
    }

    private function clearOldInput(): void
    {
        unset($_SESSION[self::FORM_OLD_INPUT_KEY]);
    }

    /**
     * Construye el payload de meses a partir del Request y devuelve
     * [payload, erroresDeValidación].
     *
     * @return array{0: array<string, array<string, mixed>>, 1: string[]}
     */
    private function buildPlanPayloadFromRequest(Request $request): array
    {
        $months = [
            'enero' => 'Enero',
            'febrero' => 'Febrero',
            'marzo' => 'Marzo',
            'abril' => 'Abril',
            'mayo' => 'Mayo',
            'junio' => 'Junio',
            'julio' => 'Julio',
            'agosto' => 'Agosto',
            'septiembre' => 'Septiembre',
            'octubre' => 'Octubre',
            'noviembre' => 'Noviembre',
            'diciembre' => 'Diciembre',
        ];

        $payload = [];
        $errors = [];
        $filledMonths = 0;

        foreach ($months as $key => $label) {
            /** @var array<int, string>|string $rawTopics */
            $rawTopics = $request->input($key . '_temas', []);
            $topics = is_array($rawTopics) ? array_values(array_filter(array_map('strval', $rawTopics))) : [];

            $population = trim((string) $request->input($key . '_poblacion', ''));

            $hasAnyData = !empty($topics) || $population !== '';

            if ($hasAnyData) {
                if (empty($topics) || $population === '') {
                    $errors[] = "Si vas a diligenciar {$label}, debes seleccionar al menos un tema y definir la población objetivo.";
                } else {
                    $filledMonths++;
                }

                $payload[$key] = [
                    'label' => $label,
                    'topics' => $topics,
                    'population' => $population,
                ];
            }
        }

        if ($filledMonths === 0) {
            $errors[] = 'Debes diligenciar al menos un mes con temas y población objetivo para guardar la planeación.';
        }

        return [$payload, $errors];
    }
}

