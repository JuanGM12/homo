<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\EncuestaOpinionAoatRepository;
use App\Repositories\UserRepository;
use App\Services\Auth;
use App\Services\Flash;
use App\Services\PdfImageHelper;
use App\Services\PdfService;
use App\Support\MunicipalityListRequest;

final class EncuestaOpinionAoatController
{
    /** Roles operativos con acceso al módulo de consulta/exportación. */
    private const ROLES_CONSULTA_EXPORT = [
        'admin',
        'coordinadora',
        'coordinador',
        'especialista',
        'medico',
        'psicologo',
        'abogado',
        'profesional social',
        'profesional_social',
    ];

    private const MIN_ALLOWED_DATE = '2026-01-01';
    private const FORM_OLD_INPUT_KEY = 'encuesta_opinion_aoat.form_old_input';
    private const INDEX_PAGE_SIZE = 20;

    public function form(Request $request): Response
    {
        $userRepo = new UserRepository();
        $advisors = $userRepo->findNonAdminAdvisors();
        $oldInput = $_SESSION[self::FORM_OLD_INPUT_KEY] ?? null;
        unset($_SESSION[self::FORM_OLD_INPUT_KEY]);
        $selectedAdvisorId = (int) ($oldInput['advisor_user_id'] ?? $request->input('advisor_id', 0));
        $selectedAdvisor = null;

        foreach ($advisors as $advisor) {
            if ((int) ($advisor['id'] ?? 0) === $selectedAdvisorId) {
                $selectedAdvisor = $advisor;
                break;
            }
        }

        $currentUser = Auth::user();
        $shareAdvisor = null;
        if ($currentUser !== null) {
            foreach ($advisors as $advisor) {
                if ((int) ($advisor['id'] ?? 0) === (int) ($currentUser['id'] ?? 0)) {
                    $shareAdvisor = $advisor;
                    break;
                }
            }
        }

        $shareLink = null;
        $qrImageUrl = null;
        if ($shareAdvisor !== null) {
            $shareLink = $this->buildPublicSurveyLink((int) $shareAdvisor['id']);
            $qrImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=280x280&data=' . rawurlencode($shareLink);
        }

        return Response::view('encuesta_opinion_aoat/form', [
            'pageTitle' => 'Encuesta de Opinión - AoAT',
            'advisors' => $advisors,
            'selectedAdvisor' => $selectedAdvisor,
            'shareAdvisor' => $shareAdvisor,
            'shareLink' => $shareLink,
            'qrImageUrl' => $qrImageUrl,
            'oldInput' => is_array($oldInput) ? $oldInput : [],
        ]);
    }

    public function store(Request $request): Response
    {
        $errors = $this->validateForm($request);
        if ($errors !== []) {
            $_SESSION[self::FORM_OLD_INPUT_KEY] = $this->captureOldInput($request);
            Flash::set([
                'type' => 'error',
                'title' => 'Revisa el formulario',
                'message' => implode("\n", $errors),
            ]);

            return Response::redirect($this->buildSurveyRedirectUrl((int) $request->input('advisor_user_id', 0)));
        }

        $advisorUserId = (int) $request->input('advisor_user_id');
        $userRepo = new UserRepository();
        $user = $userRepo->find($advisorUserId);
        $advisorName = $user ? (string) $user['name'] : 'Asesor';

        $activityDate = trim((string) $request->input('activity_date', ''));
        $data = [
            'advisor_user_id' => $advisorUserId,
            'advisor_name' => $advisorName,
            'actividad' => trim((string) $request->input('actividad', '')),
            'lugar' => trim((string) $request->input('lugar', '')),
            'activity_date' => $activityDate,
            'subregion' => trim((string) $request->input('subregion', '')),
            'municipality' => trim((string) $request->input('municipality', '')),
            'score_objetivos' => (int) $request->input('score_objetivos', 0),
            'score_claridad' => (int) $request->input('score_claridad', 0),
            'score_pertinencia' => (int) $request->input('score_pertinencia', 0),
            'score_ayudas' => (int) $request->input('score_ayudas', 0),
            'score_relacion' => (int) $request->input('score_relacion', 0),
            'score_puntualidad' => (int) $request->input('score_puntualidad', 0),
            'comments' => trim((string) $request->input('comments', '')) ?: null,
        ];

        try {
            $repo = new EncuestaOpinionAoatRepository();
            $repo->create($data);
        } catch (\PDOException $e) {
            $_SESSION[self::FORM_OLD_INPUT_KEY] = $this->captureOldInput($request);
            Flash::set([
                'type' => 'error',
                'title' => 'No se pudo guardar',
                'message' => 'Ocurrió un problema al registrar la encuesta. Intenta nuevamente.',
            ]);

            return Response::redirect($this->buildSurveyRedirectUrl($advisorUserId));
        }

        unset($_SESSION[self::FORM_OLD_INPUT_KEY]);

        Flash::set([
            'type' => 'success',
            'title' => 'Encuesta registrada',
            'message' => 'Tu opinión ha sido recibida por el Programa de Promoción y Prevención. La información será tratada únicamente con fines estadísticos.',
        ]);

        return Response::redirect($this->buildSurveyRedirectUrl($advisorUserId));
    }

    public function index(Request $request): Response
    {
        $user = Auth::user();
        if (!$user) {
            return Response::redirect('/login');
        }
        if (!$this->userCanConsultExport($user)) {
            return Response::view('errors/403', ['pageTitle' => 'Acceso denegado'], 403);
        }

        $sort = trim((string) $request->input('sort', 'created_at'));
        $dir = strtolower(trim((string) $request->input('dir', 'desc')));
        $currentPage = max(1, (int) $request->input('page', 1));

        $municipalities = MunicipalityListRequest::parse($request);
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'from_date' => trim((string) $request->input('from_date', '')),
            'to_date' => trim((string) $request->input('to_date', '')),
            'subregion' => trim((string) $request->input('subregion', '')),
            'municipalities' => $municipalities,
            'advisor' => trim((string) $request->input('advisor', '')),
        ];

        $repo = new EncuestaOpinionAoatRepository();
        $records = $this->scopeRecordsForUser($repo->findAll(), $user);
        $records = $this->withAverages($records);

        $advisors = array_values(array_unique(array_column($records, 'advisor_name')));
        sort($advisors);

        $records = $this->applyEncuestaFilters($records, $filters);
        $records = $this->sortEncuestaRecords($records, $sort, $dir);
        $pagination = $this->paginateEncuestaRecords($records, $currentPage, self::INDEX_PAGE_SIZE);

        if ((string) $request->input('partial', '') === 'results') {
            $html = $this->renderEncuestaResultsPartial($pagination['items'], $pagination);

            return Response::json(['html' => $html]);
        }

        return Response::view('encuesta_opinion_aoat/index', [
            'pageTitle' => 'Consultar encuestas de opinión AoAT',
            'records' => $pagination['items'],
            'pagination' => $pagination,
            'filters' => $filters,
            'advisors' => $advisors,
        ]);
    }

    public function export(Request $request): Response
    {
        $user = Auth::user();
        if (!$user) {
            return Response::redirect('/login');
        }
        if (!$this->userCanConsultExport($user)) {
            return Response::view('errors/403', ['pageTitle' => 'Acceso denegado'], 403);
        }

        $municipalities = MunicipalityListRequest::parse($request);
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'from_date' => trim((string) $request->input('from_date', '')),
            'to_date' => trim((string) $request->input('to_date', '')),
            'subregion' => trim((string) $request->input('subregion', '')),
            'municipalities' => $municipalities,
            'advisor' => trim((string) $request->input('advisor', '')),
        ];

        $repo = new EncuestaOpinionAoatRepository();
        $records = $this->scopeRecordsForUser($repo->findAll(), $user);
        $records = $this->withAverages($records);
        $records = $this->applyEncuestaFilters($records, $filters);
        $format = strtolower(trim((string) $request->input('format', 'excel')));

        if ($format === 'pdf') {
            $html = $this->buildPdfExportHtml($records, $filters, $user);
            $pdfBinary = PdfService::renderHtml($html, 'L', 'Encuestas de opinión AoAT');

            return new Response($pdfBinary, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="encuesta_opinion_aoat_' . date('Ymd_His') . '.pdf"',
            ]);
        }

        $html = $this->buildExcelExportHtml($records, $filters, $user);

        return new Response($html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="encuesta_opinion_aoat_' . date('Ymd_His') . '.xls"',
        ]);
    }

    private function applyEncuestaFilters(array $records, array $filters): array
    {
        $q = strtolower($filters['q'] ?? '');
        $sub = strtolower($filters['subregion'] ?? '');
        $munList = $filters['municipalities'] ?? [];
        if (!is_array($munList)) {
            $munList = [];
        }
        $munListLower = array_map(strtolower(...), $munList);
        $adv = strtolower($filters['advisor'] ?? '');
        $from = $filters['from_date'] ?? '';
        $to = $filters['to_date'] ?? '';

        return array_values(array_filter($records, static function (array $row) use ($q, $sub, $munListLower, $adv, $from, $to): bool {
            if ($q !== '') {
                $hay = strtolower(
                    ($row['advisor_name'] ?? '') . ' ' .
                    ($row['actividad'] ?? '') . ' ' .
                    ($row['lugar'] ?? '') . ' ' .
                    ($row['subregion'] ?? '') . ' ' .
                    ($row['municipality'] ?? '')
                );
                if (strpos($hay, $q) === false) {
                    return false;
                }
            }
            if ($sub !== '' && strtolower((string) ($row['subregion'] ?? '')) !== $sub) {
                return false;
            }
            if ($munListLower !== [] && !in_array(strtolower((string) ($row['municipality'] ?? '')), $munListLower, true)) {
                return false;
            }
            if ($adv !== '' && strtolower((string) ($row['advisor_name'] ?? '')) !== $adv) {
                return false;
            }
            $date = substr((string) ($row['created_at'] ?? ''), 0, 10);
            if ($from !== '' && $date < $from) {
                return false;
            }
            if ($to !== '' && $date > $to) {
                return false;
            }

            return true;
        }));
    }

    private function sortEncuestaRecords(array $records, string $sort, string $dir): array
    {
        $allowed = ['created_at', 'advisor_name', 'activity_date', 'subregion', 'municipality', 'promedio'];
        if (!in_array($sort, $allowed, true)) {
            $sort = 'created_at';
        }
        $dir = $dir === 'asc' ? 'asc' : 'desc';

        usort($records, static function (array $a, array $b) use ($sort): int {
            $va = (string) ($a[$sort] ?? '');
            $vb = (string) ($b[$sort] ?? '');

            return strnatcasecmp($va, $vb);
        });

        if ($dir === 'desc') {
            $records = array_reverse($records);
        }

        return $records;
    }

    private function paginateEncuestaRecords(array $records, int $page, int $perPage): array
    {
        $totalItems = count($records);
        $totalPages = max(1, (int) ceil($totalItems / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;
        $items = array_slice($records, $offset, $perPage);
        $from = $totalItems > 0 ? $offset + 1 : 0;
        $to = min($offset + $perPage, $totalItems);

        return [
            'items' => $items,
            'total_items' => $totalItems,
            'total_pages' => $totalPages,
            'current_page' => $page,
            'from' => $from,
            'to' => $to,
        ];
    }

    private function renderEncuestaResultsPartial(array $records, array $pagination): string
    {
        ob_start();
        require dirname(__DIR__) . '/Views/encuesta_opinion_aoat/_results.php';
        $html = ob_get_clean();

        return is_string($html) ? $html : '';
    }

    private function userCanConsultExport(array $user): bool
    {
        $roles = array_map('strtolower', $user['roles'] ?? []);

        return (bool) array_intersect($roles, self::ROLES_CONSULTA_EXPORT);
    }

    private function userCanViewAllRecords(array $user): bool
    {
        $roles = array_map('strtolower', $user['roles'] ?? []);

        return in_array('admin', $roles, true)
            || in_array('coordinadora', $roles, true)
            || in_array('coordinador', $roles, true);
    }

    private function userIsSpecialist(array $user): bool
    {
        return in_array('especialista', array_map('strtolower', $user['roles'] ?? []), true);
    }

    private function scopeRecordsForUser(array $records, array $user): array
    {
        if ($this->userCanViewAllRecords($user)) {
            return $records;
        }

        $userId = (int) ($user['id'] ?? 0);
        if ($userId <= 0) {
            return [];
        }

        if (!$this->userIsSpecialist($user)) {
            return array_values(array_filter($records, static function (array $row) use ($userId): bool {
                return (int) ($row['advisor_user_id'] ?? 0) === $userId;
            }));
        }

        $allowedRoles = $this->resolveManagedRoles($user);

        return array_values(array_filter($records, function (array $row) use ($userId, $allowedRoles): bool {
            $advisorId = (int) ($row['advisor_user_id'] ?? 0);
            if ($advisorId === $userId) {
                return true;
            }

            $advisorRoles = $this->normalizeRoleList((string) ($row['advisor_roles'] ?? ''));
            if ($advisorRoles === []) {
                return false;
            }

            return (bool) array_intersect($advisorRoles, $allowedRoles);
        }));
    }

    /**
     * @return string[]
     */
    private function resolveManagedRoles(array $user): array
    {
        $roles = array_map('strtolower', $user['roles'] ?? []);
        $primaryRole = strtolower(trim((string) ($user['role'] ?? (($roles[0] ?? '') ?: ''))));

        if ($primaryRole === 'psicologo' || in_array('psicologo', $roles, true)) {
            return ['psicologo', 'profesional social', 'profesional_social'];
        }
        if ($primaryRole === 'medico' || in_array('medico', $roles, true)) {
            return ['medico'];
        }
        if ($primaryRole === 'abogado' || in_array('abogado', $roles, true)) {
            return ['abogado'];
        }

        return [];
    }

    /**
     * @return string[]
     */
    private function normalizeRoleList(string $rolesList): array
    {
        $roles = array_filter(array_map(static function (string $role): string {
            return strtolower(trim($role));
        }, explode(',', $rolesList)));

        return array_values(array_unique($roles));
    }

    private function withAverages(array $records): array
    {
        foreach ($records as &$row) {
            $row['promedio'] = round((
                (int) ($row['score_objetivos'] ?? 0) +
                (int) ($row['score_claridad'] ?? 0) +
                (int) ($row['score_pertinencia'] ?? 0) +
                (int) ($row['score_ayudas'] ?? 0) +
                (int) ($row['score_relacion'] ?? 0) +
                (int) ($row['score_puntualidad'] ?? 0)
            ) / 6, 2);
        }
        unset($row);

        return $records;
    }

    private function buildPdfExportHtml(array $records, array $filters, array $user): string
    {
        $base = dirname(__DIR__, 2) . '/public/assets/img';
        $logoAntioquia = PdfImageHelper::imageDataUri($base . '/logoAntioquia.png');
        $logoHomo = PdfImageHelper::imageDataUri($base . '/logoHomo.png');
        $scope = $this->describeExportScope($user);
        $filterSummary = $this->buildFilterSummary($filters);

        $rowsHtml = '';
        foreach ($records as $row) {
            $rowsHtml .= '<tr>'
                . '<td>' . htmlspecialchars((string) ($row['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td>' . htmlspecialchars((string) ($row['advisor_name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td>' . htmlspecialchars((string) ($row['actividad'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td>' . htmlspecialchars((string) ($row['lugar'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td>' . htmlspecialchars((string) ($row['activity_date'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td>' . htmlspecialchars((string) ($row['subregion'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td>' . htmlspecialchars((string) ($row['municipality'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td style="text-align:center;">' . htmlspecialchars(number_format((float) ($row['promedio'] ?? 0), 1), ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td>' . htmlspecialchars((string) ($row['comments'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>'
                . '</tr>';
        }

        if ($rowsHtml === '') {
            $rowsHtml = '<tr><td colspan="9" style="text-align:center;color:#6b7280;">No hay encuestas para exportar con los filtros actuales.</td></tr>';
        }

        return '<html><head><meta charset="UTF-8"><style>'
            . 'body{font-family:dejavusans,sans-serif;color:#243b35;font-size:11px;}'
            . '.head{border-bottom:2px solid #4062aa;padding-bottom:10px;margin-bottom:14px;}'
            . '.logos{width:100%;border-collapse:collapse;}'
            . '.logos td{vertical-align:middle;}'
            . '.logo{text-align:right;}'
            . '.logo img{height:48px;margin-left:10px;}'
            . '.title{font-size:20px;font-weight:800;color:#1d5b4b;margin:0 0 4px;}'
            . '.subtitle{font-size:11px;color:#5f746e;margin:0;}'
            . '.chips{margin:12px 0 10px;}'
            . '.chip{display:inline-block;padding:5px 10px;border-radius:999px;background:#edf6f2;color:#1d5b4b;font-weight:700;margin-right:8px;}'
            . 'table{width:100%;border-collapse:collapse;}'
            . 'th{background:#1d5b4b;color:#fff;padding:8px 6px;font-size:10px;text-align:left;}'
            . 'td{border-bottom:1px solid #d8e4de;padding:7px 6px;font-size:10px;vertical-align:top;}'
            . '.muted{color:#6b7280;font-size:10px;margin-top:8px;}'
            . '</style></head><body>'
            . '<div class="head"><table class="logos"><tr><td>'
            . '<p class="title">Encuestas de Opinión AoAT</p>'
            . '<p class="subtitle">Programa de Promoción y Prevención</p>'
            . '</td><td class="logo"><img src="' . $logoAntioquia . '" alt="Gobernación de Antioquia"><img src="' . $logoHomo . '" alt="Equipo de Promoción y Prevención"></td></tr></table></div>'
            . '<div class="chips"><span class="chip">' . htmlspecialchars($scope, ENT_QUOTES, 'UTF-8') . '</span><span class="chip">' . count($records) . ' registros</span></div>'
            . ($filterSummary !== '' ? '<div class="muted"><strong>Filtros:</strong> ' . htmlspecialchars($filterSummary, ENT_QUOTES, 'UTF-8') . '</div>' : '')
            . '<table><thead><tr><th>Fecha registro</th><th>Asesor</th><th>Actividad</th><th>Lugar</th><th>Fecha actividad</th><th>Subregión</th><th>Municipio</th><th>Promedio</th><th>Comentarios</th></tr></thead><tbody>'
            . $rowsHtml
            . '</tbody></table></body></html>';
    }

    private function buildExcelExportHtml(array $records, array $filters, array $user): string
    {
        $base = dirname(__DIR__, 2) . '/public/assets/img';
        $logoAntioquia = $this->buildExcelImageTag($base . '/logoAntioquia.png', 'Gobernación de Antioquia');
        $logoHomo = $this->buildExcelImageTag($base . '/logoHomo.png', 'Equipo de Promoción y Prevención');
        $scope = $this->describeExportScope($user);
        $filterSummary = $this->buildFilterSummary($filters);

        $rowsHtml = '';
        foreach ($records as $row) {
            $rowsHtml .= '<tr>'
                . '<td>' . htmlspecialchars((string) ($row['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td>' . htmlspecialchars((string) ($row['advisor_name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td>' . htmlspecialchars((string) ($row['actividad'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td>' . htmlspecialchars((string) ($row['lugar'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td>' . htmlspecialchars((string) ($row['activity_date'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td>' . htmlspecialchars((string) ($row['subregion'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td>' . htmlspecialchars((string) ($row['municipality'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td>' . htmlspecialchars((string) ($row['score_objetivos'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td>' . htmlspecialchars((string) ($row['score_claridad'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td>' . htmlspecialchars((string) ($row['score_pertinencia'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td>' . htmlspecialchars((string) ($row['score_ayudas'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td>' . htmlspecialchars((string) ($row['score_relacion'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td>' . htmlspecialchars((string) ($row['score_puntualidad'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td>' . htmlspecialchars(number_format((float) ($row['promedio'] ?? 0), 1), ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td>' . htmlspecialchars((string) ($row['comments'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>'
                . '</tr>';
        }

        if ($rowsHtml === '') {
            $rowsHtml = '<tr><td colspan="15" style="text-align:center;color:#6b7280;">No hay encuestas para exportar con los filtros actuales.</td></tr>';
        }

        return '<html><head><meta charset="UTF-8"><style>'
            . 'body{font-family:Arial,sans-serif;color:#243b35;}'
            . '.head{margin-bottom:14px;}'
            . '.logos{width:100%;border-collapse:collapse;}'
            . '.logos td{vertical-align:middle;}'
            . '.logo{text-align:right;}'
            . '.logo img{height:48px;margin-left:10px;}'
            . '.title{font-size:22px;font-weight:800;color:#1d5b4b;margin:0 0 4px;}'
            . '.subtitle{font-size:12px;color:#5f746e;margin:0;}'
            . '.meta{margin:10px 0 14px;font-size:12px;color:#5f746e;}'
            . 'table{width:100%;border-collapse:collapse;}'
            . 'th{background:#1d5b4b;color:#fff;padding:8px;border:1px solid #c7d6d1;text-align:left;}'
            . 'td{padding:7px;border:1px solid #d9e5df;vertical-align:top;}'
            . '</style></head><body>'
            . '<div class="head"><table class="logos"><tr><td><p class="title">Encuestas de Opinión AoAT</p><p class="subtitle">Programa de Promoción y Prevención</p></td><td class="logo">' . $logoAntioquia . $logoHomo . '</td></tr></table></div>'
            . '<div class="meta"><strong>Alcance:</strong> ' . htmlspecialchars($scope, ENT_QUOTES, 'UTF-8') . '<br><strong>Filtros:</strong> ' . htmlspecialchars($filterSummary !== '' ? $filterSummary : 'Sin filtros adicionales', ENT_QUOTES, 'UTF-8') . '</div>'
            . '<table><thead><tr><th>Fecha registro</th><th>Asesor</th><th>Actividad</th><th>Lugar</th><th>Fecha actividad</th><th>Subregión</th><th>Municipio</th><th>Objetivos</th><th>Claridad</th><th>Pertinencia</th><th>Ayudas</th><th>Relación</th><th>Puntualidad</th><th>Promedio</th><th>Comentarios</th></tr></thead><tbody>'
            . $rowsHtml
            . '</tbody></table></body></html>';
    }

    private function describeExportScope(array $user): string
    {
        if ($this->userCanViewAllRecords($user)) {
            return 'Vista completa de todos los registros';
        }

        if ($this->userIsSpecialist($user)) {
            return 'Registros propios y del personal a cargo';
        }

        return 'Registros propios del profesional';
    }

    private function buildFilterSummary(array $filters): string
    {
        $parts = [];
        $labels = [
            'q' => 'Buscar',
            'advisor' => 'Asesor',
            'subregion' => 'Subregión',
            'from_date' => 'Desde',
            'to_date' => 'Hasta',
        ];

        foreach ($labels as $key => $label) {
            $value = trim((string) ($filters[$key] ?? ''));
            if ($value !== '') {
                $parts[] = $label . ': ' . $value;
            }
        }
        $muns = $filters['municipalities'] ?? [];
        if (is_array($muns) && $muns !== []) {
            $parts[] = 'Municipio(s): ' . implode(', ', $muns);
        }

        return implode(' | ', $parts);
    }

    private function buildExcelImageTag(string $path, string $alt): string
    {
        $src = PdfImageHelper::imageDataUri($path);
        if ($src === '') {
            return '';
        }

        return '<img src="' . $src . '" alt="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '" style="height:48px;width:auto;">';
    }

    private function validateForm(Request $request): array
    {
        $errors = [];
        if ((int) $request->input('advisor_user_id', 0) <= 0) {
            $errors[] = 'Debes seleccionar el nombre del asesor.';
        }
        if (trim((string) $request->input('actividad', '')) === '') {
            $errors[] = 'El campo Actividad es obligatorio.';
        }
        if (trim((string) $request->input('lugar', '')) === '') {
            $errors[] = 'El campo Lugar es obligatorio.';
        }
        $date = trim((string) $request->input('activity_date', ''));
        if ($date === '') {
            $errors[] = 'El campo Fecha es obligatorio.';
        } elseif (!$this->isAllowedPlatformDate($date)) {
            $errors[] = 'La fecha no puede ser anterior al 1 de enero de 2026.';
        }
        if (trim((string) $request->input('subregion', '')) === '') {
            $errors[] = 'Debes seleccionar la subregión de pertenencia.';
        }
        if (trim((string) $request->input('municipality', '')) === '') {
            $errors[] = 'Debes seleccionar el municipio de pertenencia.';
        }
        foreach (['score_objetivos', 'score_claridad', 'score_pertinencia', 'score_ayudas', 'score_relacion', 'score_puntualidad'] as $key) {
            $value = (int) $request->input($key, 0);
            if ($value < 1 || $value > 5) {
                $errors[] = 'Debes marcar una valoración del 1 al 5 en cada ítem de satisfacción.';
                break;
            }
        }

        return $errors;
    }

    private function isAllowedPlatformDate(string $date): bool
    {
        $normalizedDate = trim($date);
        if ($normalizedDate === '') {
            return false;
        }

        return $normalizedDate >= self::MIN_ALLOWED_DATE;
    }

    private function buildPublicSurveyLink(int $advisorId): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');

        return $scheme . '://' . $host . '/encuesta-opinion-aoat?advisor_id=' . $advisorId;
    }

    private function buildSurveyRedirectUrl(int $advisorId): string
    {
        if ($advisorId <= 0) {
            return '/encuesta-opinion-aoat';
        }

        return '/encuesta-opinion-aoat?advisor_id=' . $advisorId;
    }

    private function captureOldInput(Request $request): array
    {
        return [
            'advisor_user_id' => trim((string) $request->input('advisor_user_id', '')),
            'actividad' => trim((string) $request->input('actividad', '')),
            'lugar' => trim((string) $request->input('lugar', '')),
            'activity_date' => trim((string) $request->input('activity_date', '')),
            'subregion' => trim((string) $request->input('subregion', '')),
            'municipality' => trim((string) $request->input('municipality', '')),
            'comments' => trim((string) $request->input('comments', '')),
            'score_objetivos' => trim((string) $request->input('score_objetivos', '')),
            'score_claridad' => trim((string) $request->input('score_claridad', '')),
            'score_pertinencia' => trim((string) $request->input('score_pertinencia', '')),
            'score_ayudas' => trim((string) $request->input('score_ayudas', '')),
            'score_relacion' => trim((string) $request->input('score_relacion', '')),
            'score_puntualidad' => trim((string) $request->input('score_puntualidad', '')),
        ];
    }
}
