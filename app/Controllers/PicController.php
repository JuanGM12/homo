<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\PicRepository;
use App\Services\Auth;
use App\Services\Flash;
use App\Services\PdfImageHelper;
use App\Services\PdfService;

final class PicController
{
    private const INDEX_PAGE_SIZE = 20;

    private PicRepository $repository;

    public function __construct()
    {
        $this->repository = new PicRepository();
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

        $canViewAll = $this->userCanViewAllPicRecords($user);
        $isSpecialist = $this->userIsSpecialist($user);
        $isAuditView = $canViewAll || $isSpecialist;
        $search = trim((string) $request->input('q', ''));
        $stateFilter = trim((string) $request->input('state', ''));
        $fromDate = trim((string) $request->input('from_date', ''));
        $toDate = trim((string) $request->input('to_date', ''));
        $sort = trim((string) $request->input('sort', 'created_at'));
        $dir = strtolower(trim((string) $request->input('dir', 'desc')));
        $currentPage = max(1, (int) $request->input('page', 1));

        if ($canViewAll) {
            $records = $this->repository->findForAudit([]);
        } elseif ($isSpecialist) {
            $records = $this->repository->findForAudit($this->resolveAuditRoles($user));
        } else {
            $records = $this->repository->findForUser((int) $user['id']);
        }

        if (!$canViewAll && !$isSpecialist) {
            $records = Auth::scopeRowsToOwnerUser($records, (int) $user['id']);
        }

        $records = $this->hydrateProfessionalRoles($records);
        $roleOptions = $this->extractRoleOptions($records);
        $roleFilter = trim((string) $request->input('role', ''));

        $records = $this->applyIndexFilters($records, $search, $stateFilter, $roleFilter, $fromDate, $toDate);
        $records = $this->sortRecords($records, $sort, $dir);
        $pagination = $this->paginateRecords($records, $currentPage, self::INDEX_PAGE_SIZE);
        $paginatedRecords = $pagination['items'];

        if ((string) $request->input('partial', '') === 'results') {
            $html = $this->renderResultsPartial($paginatedRecords, $pagination, $isAuditView, $user);

            return Response::json(['html' => $html]);
        }

        return Response::view('pic/index', [
            'pageTitle' => 'Seguimiento PIC',
            'records' => $paginatedRecords,
            'pagination' => $pagination,
            'isAuditView' => $isAuditView,
            'canCreateOwnRecord' => $this->userCanCreateOwnRecord($user),
            'roleOptions' => $roleOptions,
        ]);
    }

    public function create(Request $request): Response
    {
        $user = Auth::user();
        if (!$user) {
            return Response::redirect('/login');
        }
        if (!$this->userCanCreateOwnRecord($user)) {
            return Response::view('errors/403', ['pageTitle' => 'Acceso denegado'], 403);
        }

        $professional = [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
        ];

        return Response::view('pic/form', [
            'pageTitle' => 'Nuevo registro Seguimiento PIC',
            'mode' => 'create',
            'record' => null,
            'professional' => $professional,
        ]);
    }

    public function store(Request $request): Response
    {
        $user = Auth::user();
        if (!$user) {
            return Response::redirect('/login');
        }
        if (!$this->userCanCreateOwnRecord($user)) {
            return Response::view('errors/403', ['pageTitle' => 'Acceso denegado'], 403);
        }

        $errors = $this->validatePicForm($request);
        if (!empty($errors)) {
            Flash::set([
                'type' => 'error',
                'title' => 'Revisa el formulario',
                'message' => implode("\n", $errors),
            ]);
            return Response::redirect('/pic/nuevo');
        }

        $payload = $this->buildPayload($request);
        $this->repository->create([
            'user_id' => (int) $user['id'],
            'professional_name' => (string) $user['name'],
            'professional_email' => (string) $user['email'],
            'subregion' => trim((string) $request->input('subregion')),
            'municipality' => trim((string) $request->input('municipality')),
            'editable' => 1,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);

        Flash::set([
            'type' => 'success',
            'title' => 'Registro guardado',
            'message' => 'El registro de Seguimiento PIC se ha guardado correctamente.',
        ]);
        return Response::redirect('/pic');
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
            return Response::redirect('/pic');
        }

        $record = $this->repository->findById($id);
        if (!$record || (int) $record['user_id'] !== (int) $user['id']) {
            Flash::set([
                'type' => 'error',
                'title' => 'No autorizado',
                'message' => 'No puedes editar este registro.',
            ]);
            return Response::redirect('/pic');
        }

        if (empty($record['editable'])) {
            Flash::set([
                'type' => 'info',
                'title' => 'Edición no permitida',
                'message' => 'Este registro ya fue aprobado por el especialista y no puede modificarse.',
            ]);
            return Response::redirect('/pic');
        }

        $professional = [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
        ];

        return Response::view('pic/form', [
            'pageTitle' => 'Editar registro Seguimiento PIC',
            'mode' => 'edit',
            'record' => $record,
            'professional' => $professional,
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
            return Response::redirect('/pic');
        }

        $record = $this->repository->findById($id);
        if (!$record || (int) $record['user_id'] !== (int) $user['id']) {
            Flash::set([
                'type' => 'error',
                'title' => 'No autorizado',
                'message' => 'No puedes editar este registro.',
            ]);
            return Response::redirect('/pic');
        }

        if (empty($record['editable'])) {
            Flash::set([
                'type' => 'info',
                'title' => 'Edición no permitida',
                'message' => 'Este registro ya fue aprobado por el especialista y no puede modificarse.',
            ]);
            return Response::redirect('/pic');
        }

        $errors = $this->validatePicForm($request);
        if (!empty($errors)) {
            Flash::set([
                'type' => 'error',
                'title' => 'Revisa el formulario',
                'message' => implode("\n", $errors),
            ]);
            return Response::redirect('/pic/editar?id=' . $id);
        }

        $payload = $this->buildPayload($request);
        $this->repository->update($id, [
            'subregion' => trim((string) $request->input('subregion')),
            'municipality' => trim((string) $request->input('municipality')),
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);

        Flash::set([
            'type' => 'success',
            'title' => 'Registro actualizado',
            'message' => 'El registro de Seguimiento PIC se ha actualizado correctamente.',
        ]);
        return Response::redirect('/pic');
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

        $canViewAll = $this->userCanViewAllPicRecords($user);
        $isSpecialist = $this->userIsSpecialist($user);

        if ($canViewAll) {
            $records = $this->repository->findForAudit([]);
        } elseif ($isSpecialist) {
            $records = $this->repository->findForAudit($this->resolveAuditRoles($user));
        } else {
            $records = $this->repository->findForUser((int) $user['id']);
        }

        if (!$canViewAll && !$isSpecialist) {
            $records = Auth::scopeRowsToOwnerUser($records, (int) $user['id']);
        }

        $records = $this->hydrateProfessionalRoles($records);
        $search = trim((string) $request->input('q', ''));
        $stateFilter = trim((string) $request->input('state', ''));
        $roleFilter = trim((string) $request->input('role', ''));
        $fromDate = trim((string) $request->input('from_date', ''));
        $toDate = trim((string) $request->input('to_date', ''));
        $sort = trim((string) $request->input('sort', 'created_at'));
        $dir = strtolower(trim((string) $request->input('dir', 'desc')));
        $format = strtolower(trim((string) $request->input('format', 'excel')));

        $records = $this->applyIndexFilters($records, $search, $stateFilter, $roleFilter, $fromDate, $toDate);
        $records = $this->sortRecords($records, $sort, $dir);

        if ($records === []) {
            Flash::set([
                'type' => 'info',
                'title' => 'Sin registros para exportar',
                'message' => 'Aún no tienes registros de Seguimiento PIC para exportar.',
            ]);
            return Response::redirect('/pic');
        }

        if ($format === 'pdf') {
            $html = $this->buildPdfExportHtml($records, [
                'q' => $search,
                'state' => $stateFilter,
                'role' => $roleFilter,
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ]);

            $pdfBinary = PdfService::renderHtml($html, 'L', 'Seguimiento PIC');

            return new Response($pdfBinary, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="seguimiento_pic_' . date('Ymd_His') . '.pdf"',
            ]);
        }

        $lines = [];
        $lines[] = implode(';', [
            'Fecha registro',
            'Nombre',
            'Rol',
            'Subregión',
            'Municipio',
            'Zona orientación escolar',
            'Personas zona orientación escolar',
            'Centro de escucha',
            'Personas centro de escucha',
            'Zona orientación universitaria',
            'Personas zona orientación universitaria',
            'Redes comunitarias activas',
            'Personas red comunitaria',
        ]);

        foreach ($records as $row) {
            $payload = [];
            if (!empty($row['payload'])) {
                $decoded = json_decode((string) $row['payload'], true);
                if (is_array($decoded)) {
                    $payload = $decoded;
                }
            }
            $lines[] = implode(';', array_map(static function (string $v): string {
                return '"' . str_replace('"', '""', $v) . '"';
            }, [
                (string) ($row['created_at'] ?? ''),
                (string) ($row['professional_name'] ?? ''),
                (string) ($row['professional_role'] ?? ''),
                (string) ($row['subregion'] ?? ''),
                (string) ($row['municipality'] ?? ''),
                (string) ($payload['zona_orientacion_escolar'] ?? ''),
                (string) ($payload['personas_zona_orientacion_escolar'] ?? ''),
                (string) ($payload['centro_escucha'] ?? ''),
                (string) ($payload['personas_centro_escucha'] ?? ''),
                (string) ($payload['zona_orientacion_universitaria'] ?? ''),
                (string) ($payload['personas_zona_orientacion_universitaria'] ?? ''),
                (string) ($payload['redes_comunitarias_activas'] ?? ''),
                (string) ($payload['personas_red_comunitaria'] ?? ''),
            ]));
        }

        $csvContent = "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n";
        $filename = 'seguimiento_pic_' . date('Ymd_His') . '.csv';

        return new Response($csvContent, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function userCanAccessModule(array $user): bool
    {
        $roles = $user['roles'] ?? [];
        $allowed = ['medico', 'psicologo', 'profesional social', 'profesional_social', 'admin', 'especialista', 'coordinadora', 'coordinador'];
        return (bool) array_intersect($roles, $allowed);
    }

    private function userCanCreateOwnRecord(array $user): bool
    {
        $roles = $user['roles'] ?? [];
        $allowed = ['medico', 'psicologo', 'profesional social', 'profesional_social', 'especialista'];

        return (bool) array_intersect($roles, $allowed);
    }

    private function userCanViewAllPicRecords(array $user): bool
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

    /**
     * @return string[]
     */
    private function resolveAuditRoles(array $user): array
    {
        $roles = array_map('strtolower', $user['roles'] ?? []);
        if (in_array('admin', $roles, true) || in_array('coordinadora', $roles, true) || in_array('coordinador', $roles, true)) {
            return [];
        }

        if (!in_array('especialista', $roles, true)) {
            return [];
        }

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
     * @param array<int, array<string, mixed>> $records
     * @return array<int, array<string, mixed>>
     */
    private function hydrateProfessionalRoles(array $records): array
    {
        foreach ($records as &$row) {
            $row['professional_role'] = $this->normalizeProfessionalRole((string) ($row['professional_role'] ?? ''));
        }
        unset($row);

        return $records;
    }

    /**
     * @param array<int, array<string, mixed>> $records
     * @return array<int, string>
     */
    private function extractRoleOptions(array $records): array
    {
        $options = [];

        foreach ($records as $row) {
            $role = trim((string) ($row['professional_role'] ?? ''));
            if ($role === '') {
                continue;
            }

            $options[$role] = $role;
        }

        ksort($options);

        return array_values($options);
    }

    private function normalizeProfessionalRole(string $rolesList): string
    {
        $normalized = strtolower(trim($rolesList));
        if ($normalized === '') {
            return '';
        }

        $parts = array_values(array_filter(array_map('trim', explode(',', $normalized)), static fn (string $part): bool => $part !== ''));
        $priority = ['psicologo', 'medico', 'abogado', 'profesional social', 'profesional_social'];

        foreach ($priority as $role) {
            if (in_array($role, $parts, true)) {
                return $role === 'profesional_social' ? 'profesional social' : $role;
            }
        }

        return $parts[0] ?? $normalized;
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
        $allowedSorts = ['created_at', 'professional_name', 'professional_role', 'subregion', 'municipality', 'state'];
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
    private function extractSortValue(array $row, string $sort): string
    {
        if ($sort === 'state') {
            return !empty($row['editable']) ? 'editable' : 'aprobado';
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
        string $roleFilter,
        string $fromDate,
        string $toDate
    ): array {
        if ($search === '' && $stateFilter === '' && $roleFilter === '' && $fromDate === '' && $toDate === '') {
            return $records;
        }

        return array_values(array_filter($records, static function (array $row) use ($search, $stateFilter, $roleFilter, $fromDate, $toDate): bool {
            if ($stateFilter !== '') {
                $state = !empty($row['editable']) ? 'Editable' : 'Aprobado';
                if ($state !== $stateFilter) {
                    return false;
                }
            }

            if ($roleFilter !== '' && (string) ($row['professional_role'] ?? '') !== $roleFilter) {
                return false;
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
                (string) ($row['professional_email'] ?? ''),
                (string) ($row['professional_role'] ?? ''),
                (string) ($row['subregion'] ?? ''),
                (string) ($row['municipality'] ?? ''),
            ]);

            return stripos($haystack, $search) !== false;
        }));
    }

    /**
     * @param array<int, array<string, mixed>> $records
     * @param array<string, string> $filters
     */
    private function buildPdfExportHtml(array $records, array $filters): string
    {
        $esc = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $base = dirname(__DIR__, 2) . '/public/assets/img';
        $logoAntioquia = PdfImageHelper::imageDataUri($base . '/logoAntioquia.png');
        $logoHomo = PdfImageHelper::imageDataUri($base . '/logoHomo.png');

        $filterLabels = [];
        foreach ([
            'q' => 'Buscar',
            'state' => 'Estado',
            'role' => 'Rol',
            'from_date' => 'Desde',
            'to_date' => 'Hasta',
        ] as $key => $label) {
            if (($filters[$key] ?? '') !== '') {
                $filterLabels[] = $label . ': ' . (string) $filters[$key];
            }
        }

        $rowsHtml = '';
        foreach ($records as $row) {
            $payload = [];
            if (!empty($row['payload'])) {
                $decoded = json_decode((string) $row['payload'], true);
                if (is_array($decoded)) {
                    $payload = $decoded;
                }
            }

            $rowsHtml .= '<tr>'
                . '<td>' . $esc((string) ($row['created_at'] ?? '')) . '</td>'
                . '<td>' . $esc((string) ($row['professional_name'] ?? '')) . '</td>'
                . '<td>' . $esc(ucwords(str_replace('_', ' ', (string) ($row['professional_role'] ?? '')))) . '</td>'
                . '<td>' . $esc((string) ($row['subregion'] ?? '')) . '</td>'
                . '<td>' . $esc((string) ($row['municipality'] ?? '')) . '</td>'
                . '<td>' . $esc(!empty($row['editable']) ? 'Editable' : 'Aprobado') . '</td>'
                . '<td>' . $esc((string) ($payload['zona_orientacion_escolar'] ?? '')) . '</td>'
                . '<td>' . $esc((string) ($payload['personas_zona_orientacion_escolar'] ?? '')) . '</td>'
                . '<td>' . $esc((string) ($payload['centro_escucha'] ?? '')) . '</td>'
                . '<td>' . $esc((string) ($payload['personas_centro_escucha'] ?? '')) . '</td>'
                . '<td>' . $esc((string) ($payload['zona_orientacion_universitaria'] ?? '')) . '</td>'
                . '<td>' . $esc((string) ($payload['personas_zona_orientacion_universitaria'] ?? '')) . '</td>'
                . '<td>' . $esc((string) ($payload['redes_comunitarias_activas'] ?? '')) . '</td>'
                . '<td>' . $esc((string) ($payload['personas_red_comunitaria'] ?? '')) . '</td>'
                . '</tr>';
        }

        $headerLogos = '<td style="width:22%;">' . ($logoAntioquia !== '' ? '<img src="' . $esc($logoAntioquia) . '" alt="Gobernación de Antioquia" style="height:34px;width:auto;">' : '') . '</td>'
            . '<td style="width:56%;text-align:center;"><div class="title">Seguimiento PIC</div><div class="subtitle">Equipo de Promoción y Prevención</div></td>'
            . '<td style="width:22%;text-align:right;">' . ($logoHomo !== '' ? '<img src="' . $esc($logoHomo) . '" alt="HOMO" style="height:34px;width:auto;">' : '') . '</td>';

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Seguimiento PIC</title><style>'
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
            . '<p><strong>Registros exportados:</strong> ' . $esc((string) count($records)) . '</p>'
            . '<p><strong>Filtros:</strong> ' . $esc($filterLabels !== [] ? implode(' | ', $filterLabels) : 'Sin filtros aplicados') . '</p></div>'
            . '<table class="report"><thead><tr>'
            . '<th>Fecha registro</th><th>Profesional</th><th>Rol</th><th>Subregión</th><th>Municipio</th><th>Estado</th>'
            . '<th>Zona orientación escolar</th><th>Personas zona OE</th><th>Centro de escucha</th><th>Personas centro</th>'
            . '<th>Zona orientación universitaria</th><th>Personas zona OU</th><th>Redes comunitarias</th><th>Personas red</th>'
            . '</tr></thead><tbody>' . $rowsHtml . '</tbody></table>'
            . '<p class="footer">Documento generado automáticamente desde la plataforma Equipo de Promoción y Prevención.</p></body></html>';
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
        require dirname(__DIR__) . '/Views/pic/_results.php';
        $html = ob_get_clean();

        return is_string($html) ? $html : '';
    }

    private function validatePicForm(Request $request): array
    {
        $errors = [];
        $subregion = trim((string) $request->input('subregion'));
        $municipality = trim((string) $request->input('municipality'));

        if ($subregion === '') {
            $errors[] = 'Debes seleccionar la subregión.';
        }
        if ($municipality === '') {
            $errors[] = 'Debes seleccionar el municipio.';
        }

        $zonaEscolar = trim((string) $request->input('zona_orientacion_escolar'));
        if ($zonaEscolar !== 'Si' && $zonaEscolar !== 'No') {
            $errors[] = 'Debes indicar si el municipio cuenta con Zona de orientación Escolar (Sí/No).';
        }
        if ($zonaEscolar === 'Si') {
            $n = trim((string) $request->input('personas_zona_orientacion_escolar'));
            if ($n === '' || !ctype_digit($n) || (int) $n < 0) {
                $errors[] = 'Indica cuántas personas fueron atendidas en la zona de orientación escolar (número).';
            }
        }

        $centroEscucha = trim((string) $request->input('centro_escucha'));
        if ($centroEscucha !== 'Si' && $centroEscucha !== 'No') {
            $errors[] = 'Debes indicar si el municipio cuenta con Centro de escucha (Sí/No).';
        }
        if ($centroEscucha === 'Si') {
            $n = trim((string) $request->input('personas_centro_escucha'));
            if ($n === '' || !ctype_digit($n) || (int) $n < 0) {
                $errors[] = 'Indica cuántas personas fueron atendidas en el centro de escucha (número).';
            }
        }

        $zonaUni = trim((string) $request->input('zona_orientacion_universitaria'));
        if ($zonaUni !== 'Si' && $zonaUni !== 'No') {
            $errors[] = 'Debes indicar si el municipio cuenta con Zona de orientación Universitaria (Sí/No).';
        }
        if ($zonaUni === 'Si') {
            $n = trim((string) $request->input('personas_zona_orientacion_universitaria'));
            if ($n === '' || !ctype_digit($n) || (int) $n < 0) {
                $errors[] = 'Indica cuántas personas fueron atendidas en la Zona de orientación Universitaria (número).';
            }
        }

        $redes = trim((string) $request->input('redes_comunitarias_activas'));
        if ($redes !== 'Si' && $redes !== 'No') {
            $errors[] = 'Debes indicar si el municipio cuenta con Redes Comunitarias activas (Sí/No).';
        }
        if ($redes === 'Si') {
            $n = trim((string) $request->input('personas_red_comunitaria'));
            if ($n === '' || !ctype_digit($n) || (int) $n < 0) {
                $errors[] = 'Indica con cuántas personas está conformada la red comunitaria (número).';
            }
        }

        return $errors;
    }

    private function buildPayload(Request $request): array
    {
        return [
            'zona_orientacion_escolar' => trim((string) $request->input('zona_orientacion_escolar')),
            'personas_zona_orientacion_escolar' => trim((string) $request->input('personas_zona_orientacion_escolar')),
            'centro_escucha' => trim((string) $request->input('centro_escucha')),
            'personas_centro_escucha' => trim((string) $request->input('personas_centro_escucha')),
            'zona_orientacion_universitaria' => trim((string) $request->input('zona_orientacion_universitaria')),
            'personas_zona_orientacion_universitaria' => trim((string) $request->input('personas_zona_orientacion_universitaria')),
            'redes_comunitarias_activas' => trim((string) $request->input('redes_comunitarias_activas')),
            'personas_red_comunitaria' => trim((string) $request->input('personas_red_comunitaria')),
        ];
    }
}
