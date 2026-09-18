<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\AoatPeriodRepository;
use App\Repositories\EntrenamientoPlanRepository;
use App\Services\Auth;
use App\Services\Flash;
use App\Services\PdfImageHelper;
use App\Services\PdfService;
use App\Services\QualificationCatalog;
use App\Support\MunicipalityListRequest;
use App\Support\UserMunicipalities;
use DateTimeImmutable;

final class EntrenamientoController
{
    private const INDEX_PAGE_SIZE = 20;

    /** Límite de filas en PDF para generación rápida y uso de memoria acotado. */
    private const PDF_EXPORT_MAX_ROWS = 800;

    private EntrenamientoPlanRepository $repository;

    public function __construct()
    {
        $this->repository = new EntrenamientoPlanRepository();
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
        $canViewAll = Auth::canViewAllModuleRecords($user);
        $isAuditView = $canViewAll;
        $search = trim((string) $request->input('q', ''));
        $stateFilter = trim((string) $request->input('state', ''));
        $periodRepo = new AoatPeriodRepository();
        $periodOptions = $periodRepo->all();
        $activePeriod = $periodRepo->active();
        $periodFilter = $this->resolvePeriodFilter($request, $activePeriod);
        $fromDate = trim((string) $request->input('from_date', ''));
        $toDate = trim((string) $request->input('to_date', ''));
        $subregionFilter = trim((string) $request->input('subregion', ''));
        $municipalityFilters = MunicipalityListRequest::parse($request);
        $sort = trim((string) $request->input('sort', 'created_at'));
        $dir = strtolower(trim((string) $request->input('dir', 'desc')));
        $currentPage = max(1, (int) $request->input('page', 1));

        if ($isAuditView) {
            $records = $this->repository->findForAudit($this->resolveAuditRoles($user));
        } else {
            $records = $this->repository->findForUser((int) $user['id']);
        }

        if (!$canViewAll) {
            $records = Auth::scopeRowsToOwnerUser($records, (int) $user['id']);
        }

        $records = $this->applyIndexFilters($records, $search, $stateFilter, $fromDate, $toDate, $subregionFilter, $municipalityFilters, $periodFilter);
        $records = $this->sortRecords($records, $sort, $dir);
        $pagination = $this->paginateRecords($records, $currentPage, self::INDEX_PAGE_SIZE);
        $paginatedRecords = $pagination['items'];

        if ((string) $request->input('partial', '') === 'results') {
            $html = $this->renderResultsPartial($paginatedRecords, $pagination, $isAuditView, $user);

            return Response::json(['html' => $html]);
        }

        return Response::view('entrenamiento/index', [
            'pageTitle' => 'Plan de Entrenamiento',
            'records' => $paginatedRecords,
            'pagination' => $pagination,
            'isAuditView' => $isAuditView,
            'canCreateOwnRecord' => $this->userCanCreateOwnRecord($user),
            'periodOptions' => $periodOptions,
            'activePeriod' => $activePeriod,
            'filterPeriod' => $periodFilter,
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

        return Response::view('entrenamiento/form', [
            'pageTitle' => 'Nuevo plan de entrenamiento',
            'mode' => 'create',
            'plan' => null,
            'professional' => $this->professionalFromUser($user),
            'allowedMunicipalities' => UserMunicipalities::assignedTo((int) $user['id']),
            'readOnly' => false,
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

        $subregion = trim((string) $request->input('subregion'));
        $municipality = trim((string) $request->input('municipality'));
        $role = $this->qualificationRoleForUser($user);

        $errors = $this->validatePlanForm($request, (int) $user['id'], $subregion, $municipality, $role);

        if (!empty($errors)) {
            Flash::set([
                'type' => 'error',
                'title' => 'Revisa el formulario',
                'message' => implode("\n", $errors),
            ]);
            return Response::redirect('/entrenamiento/nuevo');
        }

        $activePeriod = (new AoatPeriodRepository())->active();
        $this->repository->create([
            'user_id' => (int) $user['id'],
            'period_id' => $activePeriod !== null ? (int) ($activePeriod['id'] ?? 0) : null,
            'professional_name' => (string) $user['name'],
            'professional_email' => (string) $user['email'],
            'subregion' => $subregion,
            'municipality' => $municipality,
            'editable' => 1,
            'payload' => json_encode($this->buildPlanPayload($request, $role), JSON_UNESCAPED_UNICODE),
        ]);

        Flash::set([
            'type' => 'success',
            'title' => 'Plan registrado',
            'message' => 'El plan de entrenamiento se ha guardado correctamente.',
        ]);
        return Response::redirect('/entrenamiento');
    }

    public function edit(Request $request): Response
    {
        $user = Auth::user();
        if (!$user) {
            return Response::redirect('/login');
        }
        if (!$this->userCanAccessModule($user)) {
            return Response::view('errors/403', ['pageTitle' => 'Acceso denegado'], 403);
        }

        $id = (int) $request->input('id', 0);
        if ($id <= 0) {
            return Response::redirect('/entrenamiento');
        }

        $plan = $this->repository->findById($id);
        if ($plan === null) {
            Flash::set([
                'type' => 'error',
                'title' => 'No encontrado',
                'message' => 'El plan indicado no existe.',
            ]);
            return Response::redirect('/entrenamiento');
        }

        $isOwner = (int) ($plan['user_id'] ?? 0) === (int) $user['id'];
        $isCoordinator = $this->userIsCoordinator($user);
        if (!$isOwner && !$isCoordinator) {
            Flash::set([
                'type' => 'error',
                'title' => 'No autorizado',
                'message' => 'No puedes consultar este registro.',
            ]);
            return Response::redirect('/entrenamiento');
        }

        $readOnly = $isCoordinator || empty($plan['editable']) || !$this->isRecordInActivePeriod($plan);
        $professional = $isOwner && !$isCoordinator
            ? $this->professionalFromUser($user)
            : $this->professionalFromPlan($plan, $user);

        return Response::view('entrenamiento/form', [
            'pageTitle' => $readOnly ? 'Consultar plan de entrenamiento' : 'Editar plan de entrenamiento',
            'mode' => 'edit',
            'plan' => $plan,
            'professional' => $professional,
            'allowedMunicipalities' => $isCoordinator ? [] : UserMunicipalities::assignedTo((int) $user['id']),
            'readOnly' => $readOnly,
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
            return Response::redirect('/entrenamiento');
        }

        $plan = $this->repository->findById($id);
        if (!$plan || (int) $plan['user_id'] !== (int) $user['id']) {
            Flash::set([
                'type' => 'error',
                'title' => 'No autorizado',
                'message' => 'No puedes editar este registro.',
            ]);
            return Response::redirect('/entrenamiento');
        }

        if (empty($plan['editable'])) {
            Flash::set([
                'type' => 'info',
                'title' => 'Edición no permitida',
                'message' => 'Este plan ya fue aprobado por el especialista y no puede modificarse.',
            ]);
            return Response::redirect('/entrenamiento');
        }

        if (!$this->isRecordInActivePeriod($plan)) {
            Flash::set([
                'type' => 'info',
                'title' => 'Periodo cerrado',
                'message' => 'Este plan pertenece a un periodo anterior y solo puede consultarse en modo lectura.',
            ]);
            return Response::redirect('/entrenamiento/editar?id=' . $id);
        }

        $subregion = trim((string) $request->input('subregion'));
        $municipality = trim((string) $request->input('municipality'));
        $role = $this->qualificationRoleForUser($user);
        $errors = $this->validatePlanForm($request, (int) $user['id'], $subregion, $municipality, $role);

        if (!empty($errors)) {
            Flash::set([
                'type' => 'error',
                'title' => 'Revisa el formulario',
                'message' => implode("\n", $errors),
            ]);
            return Response::redirect('/entrenamiento/editar?id=' . $id);
        }

        $this->repository->update($id, [
            'subregion' => $subregion,
            'municipality' => $municipality,
            'payload' => json_encode($this->buildPlanPayload($request, $role), JSON_UNESCAPED_UNICODE),
        ]);

        Flash::set([
            'type' => 'success',
            'title' => 'Plan actualizado',
            'message' => 'El plan de entrenamiento se ha actualizado correctamente.',
        ]);
        return Response::redirect('/entrenamiento');
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

        $records = $this->plansForExport($request, $user);

        if ($records === []) {
            Flash::set([
                'type' => 'info',
                'title' => 'Sin registros para exportar',
                'message' => 'Aún no tienes planes de entrenamiento para exportar.',
            ]);
            return Response::redirect('/entrenamiento');
        }

        $lines = [];
        $lines[] = implode(';', [
            'Fecha registro',
            'Periodo',
            'Nombre',
            'Subregión',
            'Municipio',
            'Cualificación',
            'Otro caso',
            'Tema propuesto 1',
            'Tema propuesto 2',
            'Tema propuesto 3',
            'Tema propuesto 4',
            'Justificación',
        ]);

        foreach ($records as $plan) {
            $payload = [];
            if (!empty($plan['payload'])) {
                $decoded = json_decode((string) $plan['payload'], true);
                if (is_array($decoded)) {
                    $payload = $decoded;
                }
            }
            $role = QualificationCatalog::inferRoleFromPayload($payload);
            $payload = QualificationCatalog::hydrateLegacyPayload($payload, $role !== '' ? $role : 'psicologo');
            $qualificationParts = [];
            foreach (QualificationCatalog::displaySections($payload, $role) as $section) {
                $values = $section['values'] ?? [];
                if ($values === []) {
                    continue;
                }
                $qualificationParts[] = $section['title'] . ': ' . implode(' | ', $values);
            }
            $row = [
                (string) ($plan['created_at'] ?? ''),
                (string) ($plan['period_name'] ?? ''),
                (string) ($plan['professional_name'] ?? ''),
                (string) ($plan['subregion'] ?? ''),
                (string) ($plan['municipality'] ?? ''),
                implode(' || ', $qualificationParts),
                (string) ($payload['otro_caso'] ?? ''),
                (string) ($payload['tema_propuesto_1'] ?? ''),
                (string) ($payload['tema_propuesto_2'] ?? ''),
                (string) ($payload['tema_propuesto_3'] ?? ''),
                (string) ($payload['tema_propuesto_4'] ?? ''),
                (string) ($payload['justificacion_temas'] ?? ''),
            ];
            $lines[] = implode(';', array_map(static function (string $v): string {
                return '"' . str_replace('"', '""', $v) . '"';
            }, $row));
        }

        $csvContent = "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n";
        $filename = 'plan_entrenamiento_' . date('Ymd_His') . '.csv';

        return new Response($csvContent, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function destroy(Request $request): Response
    {
        $user = Auth::user();
        if (!$user) {
            return Response::redirect('/login');
        }
        if (!Auth::isAdmin($user)) {
            return Response::view('errors/403', ['pageTitle' => 'Acceso denegado'], 403);
        }
        if (!$this->userCanAccessModule($user)) {
            return Response::view('errors/403', ['pageTitle' => 'Acceso denegado'], 403);
        }

        $id = (int) $request->input('id', 0);
        if ($id <= 0) {
            Flash::set([
                'type' => 'error',
                'title' => 'Solicitud no válida',
                'message' => 'No se indicó un plan de entrenamiento válido.',
            ]);

            return Response::redirect('/entrenamiento');
        }

        $record = $this->repository->findById($id);
        if ($record === null) {
            Flash::set([
                'type' => 'error',
                'title' => 'No encontrado',
                'message' => 'El plan indicado no existe.',
            ]);

            return Response::redirect('/entrenamiento');
        }

        $this->repository->deleteById($id);

        Flash::set([
            'type' => 'success',
            'title' => 'Registro eliminado',
            'message' => 'El plan de entrenamiento se eliminó del sistema.',
        ]);

        return Response::redirect('/entrenamiento');
    }

    public function exportPdf(Request $request): Response
    {
        $user = Auth::user();
        if (!$user) {
            return Response::redirect('/login');
        }
        if (!$this->userCanAccessModule($user)) {
            return Response::view('errors/403', ['pageTitle' => 'Acceso denegado'], 403);
        }

        $records = $this->plansForExport($request, $user);

        if ($records === []) {
            Flash::set([
                'type' => 'info',
                'title' => 'Sin registros para exportar',
                'message' => 'No hay planes que coincidan con los filtros actuales.',
            ]);

            return Response::redirect('/entrenamiento');
        }

        $totalFiltered = count($records);
        $truncated = false;
        if ($totalFiltered > self::PDF_EXPORT_MAX_ROWS) {
            $records = array_slice($records, 0, self::PDF_EXPORT_MAX_ROWS);
            $truncated = true;
        }

        @ini_set('memory_limit', '256M');
        @set_time_limit(120);

        $html = $this->buildEntrenamientoListPdfHtml($request, $records, $totalFiltered, $truncated);
        $pdfBinary = PdfService::renderHtml($html, 'L', 'Plan de entrenamiento');

        return new Response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="plan_entrenamiento_' . date('Y-m-d_His') . '.pdf"',
        ]);
    }

    /**
     * Planes visibles para el usuario, con los mismos filtros y orden que el listado / Excel.
     *
     * @return array<int, array<string, mixed>>
     */
    private function plansForExport(Request $request, array $user): array
    {
        $canViewAll = Auth::canViewAllModuleRecords($user);
        $isAuditView = $canViewAll;

        if ($isAuditView) {
            $records = $this->repository->findForAudit($this->resolveAuditRoles($user));
        } else {
            $records = $this->repository->findForUser((int) $user['id']);
        }

        if (!$canViewAll) {
            $records = Auth::scopeRowsToOwnerUser($records, (int) $user['id']);
        }

        $search = trim((string) $request->input('q', ''));
        $stateFilter = trim((string) $request->input('state', ''));
        $periodRepo = new AoatPeriodRepository();
        $periodFilter = $this->resolvePeriodFilter($request, $periodRepo->active());
        $fromDate = trim((string) $request->input('from_date', ''));
        $toDate = trim((string) $request->input('to_date', ''));
        $subregionFilter = trim((string) $request->input('subregion', ''));
        $municipalityFilters = MunicipalityListRequest::parse($request);
        $sort = trim((string) $request->input('sort', 'created_at'));
        $dir = strtolower(trim((string) $request->input('dir', 'desc')));

        $records = $this->applyIndexFilters($records, $search, $stateFilter, $fromDate, $toDate, $subregionFilter, $municipalityFilters, $periodFilter);

        return $this->sortRecords($records, $sort, $dir);
    }

    /**
     * PDF resumido (listado): sin payload JSON extenso, solo columnas de seguimiento.
     *
     * @param array<int, array<string, mixed>> $records
     */
    private function buildEntrenamientoListPdfHtml(
        Request $request,
        array $records,
        int $totalFiltered,
        bool $truncated
    ): string {
        $esc = static function (string $value): string {
            return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        };

        $meta = [];
        $q = trim((string) $request->input('q', ''));
        if ($q !== '') {
            $meta[] = 'Búsqueda: ' . $esc($q);
        }
        $state = trim((string) $request->input('state', ''));
        if ($state !== '') {
            $meta[] = 'Estado: ' . $esc($state);
        }
        $fromDate = trim((string) $request->input('from_date', ''));
        if ($fromDate !== '') {
            $meta[] = 'Desde: ' . $esc($fromDate);
        }
        $toDate = trim((string) $request->input('to_date', ''));
        if ($toDate !== '') {
            $meta[] = 'Hasta: ' . $esc($toDate);
        }
        $subregion = trim((string) $request->input('subregion', ''));
        if ($subregion !== '') {
            $meta[] = 'Subregión: ' . $esc($subregion);
        }
        $muns = MunicipalityListRequest::parse($request);
        if ($muns !== []) {
            $meta[] = 'Municipio(s): ' . $esc(implode(', ', $muns));
        }
        $periodFilter = trim((string) $request->input('period_id', ''));
        if ($periodFilter !== '' && $periodFilter !== 'all') {
            $periodLabel = $esc($periodFilter);
            foreach ((new AoatPeriodRepository())->all() as $period) {
                if ((string) (int) ($period['id'] ?? 0) === $periodFilter) {
                    $periodLabel = $esc((string) ($period['name'] ?? $periodFilter));
                    break;
                }
            }
            $meta[] = 'Periodo: ' . $periodLabel;
        }

        $rows = '';
        foreach ($records as $plan) {
            $createdRaw = trim((string) ($plan['created_at'] ?? ''));
            $createdFmt = $createdRaw;
            try {
                $createdFmt = (new DateTimeImmutable($createdRaw))->format('d/m/Y H:i');
            } catch (\Throwable) {
            }

            $name = (string) ($plan['professional_name'] ?? '');
            $email = (string) ($plan['professional_email'] ?? '');
            $stateLabel = !empty($plan['editable']) ? 'Editable' : 'Aprobado';

            $rows .= '<tr>'
                . '<td>' . $esc($createdFmt) . '</td>'
                . '<td>' . $esc((string) ($plan['period_name'] ?? 'Sin periodo')) . '</td>'
                . '<td><strong>' . $esc($name) . '</strong><br><span class="sub">' . $esc($email) . '</span></td>'
                . '<td>' . $esc((string) ($plan['subregion'] ?? '')) . '</td>'
                . '<td>' . $esc((string) ($plan['municipality'] ?? '')) . '</td>'
                . '<td>' . $esc($stateLabel) . '</td>'
                . '</tr>';
        }

        $base = dirname(__DIR__, 2) . '/public/assets/img';
        $logoAntSrc = PdfImageHelper::imageDataUri($base . '/logoAntioquia.png');
        $logoHomoSrc = PdfImageHelper::imageDataUri($base . '/logoHomo.png');
        $logoAnt = $logoAntSrc !== '' ? '<img src="' . $logoAntSrc . '" alt="" class="logo">' : '';
        $logoHomo = $logoHomoSrc !== '' ? '<img src="' . $logoHomoSrc . '" alt="" class="logo">' : '';

        $truncNote = $truncated
            ? '<p><strong>Nota:</strong> Se incluyen las primeras ' . self::PDF_EXPORT_MAX_ROWS . ' filas de '
                . $totalFiltered . ' resultados. Ajuste filtros para un archivo más pequeño.</p>'
            : '';

        $exportedCount = count($records);

        return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Plan de entrenamiento</title><style>'
            . 'body{font-family:DejaVu Sans,Arial,Helvetica,sans-serif;font-size:8.5pt;color:#1f2a24;margin:18px 22px;}'
            . 'table{width:100%;border-collapse:collapse;}'
            . '.header td{vertical-align:middle;}'
            . '.logo{height:36px;}'
            . '.title{text-align:center;font-size:14pt;font-weight:700;color:#2a5543;}'
            . '.subtitle{text-align:center;font-size:8.5pt;color:#4a6fa8;margin-top:2px;}'
            . '.meta{margin:8px 0 12px 0;font-size:8pt;}'
            . '.meta p{margin:2px 0;}'
            . '.section-title{background:#2a5543;color:#fff;font-weight:700;padding:5px 8px;font-size:9pt;margin-top:4px;}'
            . 'th{background:#eef5f0;color:#1f2a24;font-weight:700;border:1px solid #cfdad3;padding:4px 5px;text-align:left;}'
            . 'td{border:1px solid #d9e2dd;padding:4px 5px;vertical-align:top;}'
            . '.sub{font-size:7.5pt;color:#5d6c65;}'
            . '</style></head><body>'
            . '<table class="header"><tr><td style="width:25%">' . $logoAnt . '</td><td style="width:50%">'
            . '<div class="title">Seguimiento de entrenamiento</div>'
            . '<div class="subtitle">Equipo de Promoción y Prevención · Acción en Territorio</div>'
            . '</td><td style="width:25%;text-align:right">' . $logoHomo . '</td></tr></table>'
            . '<div class="meta">'
            . '<p><strong>Fecha de exportación:</strong> ' . $esc(date('d/m/Y H:i')) . '</p>'
            . '<p><strong>Filtros:</strong> ' . ($meta !== [] ? implode(' | ', $meta) : 'Sin filtros aplicados') . '</p>'
            . '<p><strong>Registros en este PDF:</strong> ' . $exportedCount . ($totalFiltered !== $exportedCount ? ' (de ' . $totalFiltered . ' con filtros)' : '') . '</p>'
            . $truncNote
            . '</div>'
            . '<div class="section-title">Planes de entrenamiento</div>'
            . '<table><thead><tr>'
            . '<th style="width:11%">Fecha registro</th>'
            . '<th style="width:10%">Periodo</th>'
            . '<th style="width:27%">Profesional</th>'
            . '<th style="width:18%">Subregión</th>'
            . '<th style="width:18%">Municipio</th>'
            . '<th style="width:10%">Estado</th>'
            . '</tr></thead><tbody>'
            . $rows
            . '</tbody></table>'
            . '</body></html>';
    }

    private function userIsCoordinator(array $user): bool
    {
        $roles = array_map('strtolower', $user['roles'] ?? []);

        return in_array('coordinadora', $roles, true) || in_array('coordinador', $roles, true);
    }

    private function userCanAccessModule(array $user): bool
    {
        $roles = $user['roles'] ?? [];
        $allowed = ['psicologo', 'abogado', 'medico', 'politologo', 'profesional social', 'profesional_social', 'admin', 'especialista', 'coordinadora', 'coordinador'];
        return (bool) array_intersect($roles, $allowed);
    }

    private function userCanCreateOwnRecord(array $user): bool
    {
        if ($this->userIsCoordinator($user)) {
            return false;
        }

        $roles = $user['roles'] ?? [];
        $allowed = ['psicologo', 'abogado', 'medico', 'politologo', 'profesional social', 'profesional_social', 'especialista'];

        return (bool) array_intersect($roles, $allowed);
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
        if ($primaryRole === 'politologo' || in_array('politologo', $roles, true)) {
            return ['politologo'];
        }
        if ($primaryRole === 'abogado' || in_array('abogado', $roles, true)) {
            return ['abogado'];
        }

        return [];
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
        $allowedSorts = ['created_at', 'professional_name', 'subregion', 'municipality', 'state', 'period'];
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
        if ($sort === 'period') {
            return strtolower(trim((string) ($row['period_name'] ?? '')));
        }

        return strtolower(trim((string) ($row[$sort] ?? '')));
    }

    /**
     * @param array<int, array<string, mixed>> $records
     * @return array<int, array<string, mixed>>
     */
    /**
     * @param list<string> $municipalityFilters
     */
    private function applyIndexFilters(
        array $records,
        string $search,
        string $stateFilter,
        string $fromDate,
        string $toDate,
        string $subregionFilter = '',
        array $municipalityFilters = [],
        string $periodFilter = 'all'
    ): array {
        if ($search === '' && $stateFilter === '' && $fromDate === '' && $toDate === '' && $subregionFilter === '' && $municipalityFilters === [] && $periodFilter === 'all') {
            return $records;
        }

        return array_values(array_filter($records, static function (array $row) use ($search, $stateFilter, $fromDate, $toDate, $subregionFilter, $municipalityFilters, $periodFilter): bool {
            if ($periodFilter !== 'all' && (int) ($row['period_id'] ?? 0) !== (int) $periodFilter) {
                return false;
            }

            if ($stateFilter !== '') {
                $state = !empty($row['editable']) ? 'Editable' : 'Aprobado';
                if ($state !== $stateFilter) {
                    return false;
                }
            }

            if ($subregionFilter !== '' && (string) ($row['subregion'] ?? '') !== $subregionFilter) {
                return false;
            }

            if ($municipalityFilters !== [] && !in_array((string) ($row['municipality'] ?? ''), $municipalityFilters, true)) {
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
                (string) ($row['subregion'] ?? ''),
                (string) ($row['municipality'] ?? ''),
                (string) ($row['professional_email'] ?? ''),
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
        require dirname(__DIR__) . '/Views/entrenamiento/_results.php';
        $html = ob_get_clean();

        return is_string($html) ? $html : '';
    }

    private function professionalFromPlan(array $plan, array $fallbackUser): array
    {
        $payload = [];
        if (!empty($plan['payload'])) {
            $decoded = json_decode((string) $plan['payload'], true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }
        $role = QualificationCatalog::inferRoleFromPayload($payload);
        if ($role === '') {
            $role = $this->qualificationRoleForUser($fallbackUser);
        }

        return [
            'id' => $plan['user_id'] ?? 0,
            'name' => (string) ($plan['professional_name'] ?? $fallbackUser['name'] ?? ''),
            'last_name' => '',
            'email' => (string) ($plan['professional_email'] ?? $fallbackUser['email'] ?? ''),
            'profession' => '',
            'role' => $role,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function professionalFromUser(array $user): array
    {
        return [
            'id' => $user['id'] ?? 0,
            'name' => (string) ($user['name'] ?? ''),
            'last_name' => (string) ($user['last_name'] ?? ''),
            'email' => (string) ($user['email'] ?? ''),
            'profession' => (string) ($user['profession'] ?? ''),
            'role' => $this->qualificationRoleForUser($user),
        ];
    }

    private function qualificationRoleForUser(array $user): string
    {
        $primary = QualificationCatalog::normalizeRole((string) ($user['role'] ?? ''));
        if (QualificationCatalog::roleConfig($primary) !== null) {
            return $primary;
        }

        foreach (($user['roles'] ?? []) as $role) {
            $normalized = QualificationCatalog::normalizeRole((string) $role);
            if (QualificationCatalog::roleConfig($normalized) !== null) {
                return $normalized;
            }
        }

        return $primary;
    }

    /**
     * @return list<string>
     */
    private function validatePlanForm(Request $request, int $userId, string $subregion, string $municipality, string $role): array
    {
        $errors = [];
        if ($subregion === '') {
            $errors[] = 'Debes seleccionar la subregión.';
        }
        if ($municipality === '') {
            $errors[] = 'Debes seleccionar el municipio.';
        }
        if ($subregion !== '' && $municipality !== '' && !UserMunicipalities::canUse($userId, $subregion, $municipality)) {
            $errors[] = 'Solo puedes registrar planes en los municipios que tienes asignados.';
        }

        $qualError = QualificationCatalog::validateFromRequest($role, $request);
        if ($qualError !== null) {
            $errors[] = $qualError;
        }

        return $errors;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPlanPayload(Request $request, string $role): array
    {
        $payload = QualificationCatalog::collectPosted($role, $request);
        $payload['tema_propuesto_1'] = trim((string) $request->input('tema_propuesto_1'));
        $payload['tema_propuesto_2'] = trim((string) $request->input('tema_propuesto_2'));
        $payload['tema_propuesto_3'] = trim((string) $request->input('tema_propuesto_3'));
        $payload['tema_propuesto_4'] = trim((string) $request->input('tema_propuesto_4'));
        $payload['justificacion_temas'] = trim((string) $request->input('justificacion_temas'));

        return $payload;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function isRecordInActivePeriod(array $record): bool
    {
        return (new AoatPeriodRepository())->isActivePeriodId(
            isset($record['period_id']) ? (int) $record['period_id'] : 0
        );
    }

    /**
     * @param array<string, mixed>|null $activePeriod
     */
    private function resolvePeriodFilter(Request $request, ?array $activePeriod): string
    {
        $raw = trim((string) $request->input('period_id', ''));
        if ($raw === 'all') {
            return 'all';
        }
        if ($raw !== '' && ctype_digit($raw) && (int) $raw > 0) {
            return (string) (int) $raw;
        }

        $activeId = (int) ($activePeriod['id'] ?? 0);

        return $activeId > 0 ? (string) $activeId : 'all';
    }
}
