<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\EntrenamientoPlanRepository;
use App\Services\Auth;
use App\Services\Flash;

final class EntrenamientoController
{
    private const INDEX_PAGE_SIZE = 20;

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
        $fromDate = trim((string) $request->input('from_date', ''));
        $toDate = trim((string) $request->input('to_date', ''));
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

        $records = $this->applyIndexFilters($records, $search, $stateFilter, $fromDate, $toDate);
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

        return Response::view('entrenamiento/form', [
            'pageTitle' => 'Nuevo plan de entrenamiento',
            'mode' => 'create',
            'plan' => null,
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

        $subregion = trim((string) $request->input('subregion'));
        $municipality = trim((string) $request->input('municipality'));

        $errors = [];
        if ($subregion === '') {
            $errors[] = 'Debes seleccionar la subregión.';
        }
        if ($municipality === '') {
            $errors[] = 'Debes seleccionar el municipio.';
        }

        $suicidio = $this->collectArrayInput($request->input('suicidio'));
        $violencias = $this->collectArrayInput($request->input('violencias'));
        $adicciones = $this->collectArrayInput($request->input('adicciones'));
        $otrosTemas = $this->collectArrayInput($request->input('otros_temas_salud_mental'));

        if (empty($suicidio)) {
            $errors[] = 'Debes seleccionar al menos una opción en SUICIDIO.';
        }
        if (empty($violencias)) {
            $errors[] = 'Debes seleccionar al menos una opción en VIOLENCIAS.';
        }
        if (empty($adicciones)) {
            $errors[] = 'Debes seleccionar al menos una opción en ADICCIONES.';
        }
        if (empty($otrosTemas)) {
            $errors[] = 'Debes seleccionar al menos una opción en OTROS TEMAS DE INTERÉS EN SALUD MENTAL.';
        }

        if (!empty($errors)) {
            Flash::set([
                'type' => 'error',
                'title' => 'Revisa el formulario',
                'message' => implode("\n", $errors),
            ]);
            return Response::redirect('/entrenamiento/nuevo');
        }

        $payload = [
            'suicidio' => $suicidio,
            'violencias' => $violencias,
            'adicciones' => $adicciones,
            'otros_temas_salud_mental' => $otrosTemas,
            'tema_propuesto_1' => trim((string) $request->input('tema_propuesto_1')),
            'tema_propuesto_2' => trim((string) $request->input('tema_propuesto_2')),
            'tema_propuesto_3' => trim((string) $request->input('tema_propuesto_3')),
            'tema_propuesto_4' => trim((string) $request->input('tema_propuesto_4')),
            'justificacion_temas' => trim((string) $request->input('justificacion_temas')),
        ];

        $this->repository->create([
            'user_id' => (int) $user['id'],
            'professional_name' => (string) $user['name'],
            'professional_email' => (string) $user['email'],
            'subregion' => $subregion,
            'municipality' => $municipality,
            'editable' => 1,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
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

        $professional = [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
        ];

        return Response::view('entrenamiento/form', [
            'pageTitle' => 'Editar plan de entrenamiento',
            'mode' => 'edit',
            'plan' => $plan,
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

        $subregion = trim((string) $request->input('subregion'));
        $municipality = trim((string) $request->input('municipality'));

        $errors = [];
        if ($subregion === '') {
            $errors[] = 'Debes seleccionar la subregión.';
        }
        if ($municipality === '') {
            $errors[] = 'Debes seleccionar el municipio.';
        }

        $suicidio = $this->collectArrayInput($request->input('suicidio'));
        $violencias = $this->collectArrayInput($request->input('violencias'));
        $adicciones = $this->collectArrayInput($request->input('adicciones'));
        $otrosTemas = $this->collectArrayInput($request->input('otros_temas_salud_mental'));

        if (empty($suicidio)) {
            $errors[] = 'Debes seleccionar al menos una opción en SUICIDIO.';
        }
        if (empty($violencias)) {
            $errors[] = 'Debes seleccionar al menos una opción en VIOLENCIAS.';
        }
        if (empty($adicciones)) {
            $errors[] = 'Debes seleccionar al menos una opción en ADICCIONES.';
        }
        if (empty($otrosTemas)) {
            $errors[] = 'Debes seleccionar al menos una opción en OTROS TEMAS DE INTERÉS EN SALUD MENTAL.';
        }

        if (!empty($errors)) {
            Flash::set([
                'type' => 'error',
                'title' => 'Revisa el formulario',
                'message' => implode("\n", $errors),
            ]);
            return Response::redirect('/entrenamiento/editar?id=' . $id);
        }

        $payload = [
            'suicidio' => $suicidio,
            'violencias' => $violencias,
            'adicciones' => $adicciones,
            'otros_temas_salud_mental' => $otrosTemas,
            'tema_propuesto_1' => trim((string) $request->input('tema_propuesto_1')),
            'tema_propuesto_2' => trim((string) $request->input('tema_propuesto_2')),
            'tema_propuesto_3' => trim((string) $request->input('tema_propuesto_3')),
            'tema_propuesto_4' => trim((string) $request->input('tema_propuesto_4')),
            'justificacion_temas' => trim((string) $request->input('justificacion_temas')),
        ];

        $this->repository->update($id, [
            'subregion' => $subregion,
            'municipality' => $municipality,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
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
        $fromDate = trim((string) $request->input('from_date', ''));
        $toDate = trim((string) $request->input('to_date', ''));
        $sort = trim((string) $request->input('sort', 'created_at'));
        $dir = strtolower(trim((string) $request->input('dir', 'desc')));

        $records = $this->applyIndexFilters($records, $search, $stateFilter, $fromDate, $toDate);
        $records = $this->sortRecords($records, $sort, $dir);

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
            'Nombre',
            'Subregión',
            'Municipio',
            'Suicidio',
            'Violencias',
            'Adicciones',
            'Otros temas salud mental',
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
            $row = [
                (string) ($plan['created_at'] ?? ''),
                (string) ($plan['professional_name'] ?? ''),
                (string) ($plan['subregion'] ?? ''),
                (string) ($plan['municipality'] ?? ''),
                is_array($payload['suicidio'] ?? null) ? implode(' | ', $payload['suicidio']) : '',
                is_array($payload['violencias'] ?? null) ? implode(' | ', $payload['violencias']) : '',
                is_array($payload['adicciones'] ?? null) ? implode(' | ', $payload['adicciones']) : '',
                is_array($payload['otros_temas_salud_mental'] ?? null) ? implode(' | ', $payload['otros_temas_salud_mental']) : '',
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

    private function userCanAccessModule(array $user): bool
    {
        $roles = $user['roles'] ?? [];
        $allowed = ['psicologo', 'admin', 'especialista', 'coordinadora', 'coordinador'];
        return (bool) array_intersect($roles, $allowed);
    }

    private function userCanCreateOwnRecord(array $user): bool
    {
        $roles = $user['roles'] ?? [];
        $allowed = ['psicologo', 'especialista'];

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
        $allowedSorts = ['created_at', 'professional_name', 'subregion', 'municipality', 'state'];
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
        string $fromDate,
        string $toDate
    ): array {
        if ($search === '' && $stateFilter === '' && $fromDate === '' && $toDate === '') {
            return $records;
        }

        return array_values(array_filter($records, static function (array $row) use ($search, $stateFilter, $fromDate, $toDate): bool {
            if ($stateFilter !== '') {
                $state = !empty($row['editable']) ? 'Editable' : 'Aprobado';
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

    private function collectArrayInput(mixed $input): array
    {
        if (!is_array($input)) {
            return [];
        }
        return array_values(array_filter(array_map('strval', $input)));
    }
}
