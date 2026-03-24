<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\TrainingPlanRepository;
use App\Services\Auth;
use App\Services\Flash;

final class PlaneacionController
{
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
        $stateFilter = trim((string) $request->input('state', ''));
        $fromDate = trim((string) $request->input('from_date', ''));
        $toDate = trim((string) $request->input('to_date', ''));

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

        if ($search !== '' || $stateFilter !== '' || $fromDate !== '' || $toDate !== '') {
            $records = array_values(array_filter($records, static function (array $row) use ($search, $stateFilter, $fromDate, $toDate): bool {
                if ($stateFilter !== '') {
                    $isEditable = !empty($row['editable']);
                    $state = $isEditable ? 'Editable' : 'Aprobada';
                    if ($state !== $stateFilter) {
                        return false;
                    }
                }

                $created = (string) ($row['created_at'] ?? '');
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
                    (string) ($row['subregion'] ?? ''),
                    (string) ($row['municipality'] ?? ''),
                    (string) ($row['plan_year'] ?? ''),
                ]);

                return stripos($haystack, $search) !== false;
            }));
        }

        return Response::view('planeacion/index', [
            'pageTitle' => 'Planeaci?n anual de capacitaciones',
            'records' => $records,
            'isAuditView' => $isAuditView,
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

        $primaryRole = (string) ($user['role'] ?? ($professional['roles'][0] ?? ''));

        return Response::view('planeacion/form', [
            'pageTitle' => 'Nueva planeaci?n anual',
            'mode' => 'create',
            'plan' => null,
            'professional' => $professional,
            'role' => $primaryRole,
            'planYear' => (int) date('Y'),
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
            $errors[] = 'Debes seleccionar la subregi?n.';
        }

        if ($municipality === '') {
            $errors[] = 'Debes seleccionar el municipio.';
        }

        [$payload, $monthErrors] = $this->buildPlanPayloadFromRequest($request);
        $errors = array_merge($errors, $monthErrors);

        if (!empty($errors)) {
            Flash::set([
                'type' => 'error',
                'title' => 'Revisa la planeaci?n',
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
            'title' => 'Planeaci?n registrada',
            'message' => 'La planeaci?n anual de capacitaciones se ha guardado correctamente.',
        ]);

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

        if ($records === []) {
            Flash::set([
                'type' => 'info',
                'title' => 'Sin registros para exportar',
                'message' => 'A?n no tienes planeaciones anuales registradas para exportar.',
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

        $lines = [];
        $lines[] = implode(';', [
            'A?o',
            'Profesional',
            'Rol',
            'Subregi?n',
            'Municipio',
            'Mes',
            'Temas / m?dulos',
            'Poblaci?n objetivo',
        ]);

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
                $population = (string) ($monthData['population'] ?? '');

                $topicsText = is_array($topics) ? implode(' | ', $topics) : '';

                $row = [
                    (string) $plan['plan_year'],
                    (string) $plan['professional_name'],
                    (string) $plan['professional_role'],
                    (string) $plan['subregion'],
                    (string) $plan['municipality'],
                    $label,
                    $topicsText,
                    $population,
                ];

                $lines[] = implode(';', array_map(static function (string $value): string {
                    $escaped = str_replace('"', '""', $value);
                    return '"' . $escaped . '"';
                }, $row));
            }
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
            return Response::redirect('/planeacion');
        }

        $plan = $this->repository->findById($id);
        if (!$plan || (int) $plan['user_id'] !== (int) $user['id']) {
            Flash::set([
                'type' => 'error',
                'title' => 'No autorizado',
                'message' => 'No puedes editar esta planeaci?n.',
            ]);

            return Response::redirect('/planeacion');
        }

        if (empty($plan['editable'])) {
            Flash::set([
                'type' => 'info',
                'title' => 'Edici?n no permitida',
                'message' => 'Esta planeaci?n ya fue aprobada por el especialista y no puede modificarse.',
            ]);

            return Response::redirect('/planeacion');
        }

        $professional = [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
        ];

        return Response::view('planeacion/form', [
            'pageTitle' => 'Editar planeaci?n anual',
            'mode' => 'edit',
            'plan' => $plan,
            'professional' => $professional,
            'role' => (string) ($user['role'] ?? (($user['roles'] ?? [])[0] ?? '')),
            'planYear' => (int) ($plan['plan_year'] ?? date('Y')),
        ]);
    }

    public function update(Request $request): Response
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
            return Response::redirect('/planeacion');
        }

        $plan = $this->repository->findById($id);
        if (!$plan || (int) $plan['user_id'] !== (int) $user['id']) {
            Flash::set([
                'type' => 'error',
                'title' => 'No autorizado',
                'message' => 'No puedes editar esta planeaci?n.',
            ]);

            return Response::redirect('/planeacion');
        }

        if (empty($plan['editable'])) {
            Flash::set([
                'type' => 'info',
                'title' => 'Edici?n no permitida',
                'message' => 'Esta planeaci?n ya fue aprobada por el especialista y no puede modificarse.',
            ]);

            return Response::redirect('/planeacion');
        }

        $subregion = trim((string) $request->input('subregion'));
        $municipality = trim((string) $request->input('municipality'));
        $planYear = (int) ($request->input('plan_year') ?? ($plan['plan_year'] ?? date('Y')));

        $errors = [];

        if ($subregion === '') {
            $errors[] = 'Debes seleccionar la subregi?n.';
        }

        if ($municipality === '') {
            $errors[] = 'Debes seleccionar el municipio.';
        }

        [$payload, $monthErrors] = $this->buildPlanPayloadFromRequest($request);
        $errors = array_merge($errors, $monthErrors);

        if (!empty($errors)) {
            Flash::set([
                'type' => 'error',
                'title' => 'Revisa la planeaci?n',
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
            'title' => 'Planeaci?n actualizada',
            'message' => 'La planeaci?n anual de capacitaciones se ha actualizado correctamente.',
        ]);

        return Response::redirect('/planeacion');
    }

    private function userCanAccessModule(array $user): bool
    {
        $roles = $user['roles'] ?? [];
        $allowed = ['abogado', 'Medico', 'medico', 'psicologo', 'admin', 'especialista', 'coordinadora', 'coordinador'];

        return (bool) array_intersect($roles, $allowed);
    }

    /**
     * Construye el payload de meses a partir del Request y devuelve
     * [payload, erroresDeValidaci?n].
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
                    $errors[] = "Si vas a diligenciar {$label}, debes seleccionar al menos un tema y definir la poblaci?n objetivo.";
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
            $errors[] = 'Debes diligenciar al menos un mes con temas y poblaci?n objetivo para guardar la planeaci?n.';
        }

        return [$payload, $errors];
    }
}

