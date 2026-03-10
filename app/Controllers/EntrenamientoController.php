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
        $isSpecialist = in_array('especialista', $roles, true);
        $isCoordinator = in_array('coordinadora', $roles, true) || in_array('coordinador', $roles, true);
        $isAdmin = in_array('admin', $roles, true);

        $isAuditView = $isSpecialist || $isCoordinator || $isAdmin;

        if ($isAuditView) {
            $records = $this->repository->findForAudit();
        } else {
            $records = $this->repository->findForUser((int) $user['id']);
        }

        return Response::view('entrenamiento/index', [
            'pageTitle' => 'Plan de Entrenamiento',
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
        if (!$this->userCanAccessModule($user)) {
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
        if (!$this->userCanAccessModule($user)) {
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
        if (!$this->userCanAccessModule($user)) {
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

        $roles = $user['roles'] ?? [];
        $isSpecialist = in_array('especialista', $roles, true);
        $isCoordinator = in_array('coordinadora', $roles, true) || in_array('coordinador', $roles, true);
        $isAdmin = in_array('admin', $roles, true);

        $isAuditView = $isSpecialist || $isCoordinator || $isAdmin;

        if ($isAuditView) {
            $records = $this->repository->findForAudit();
        } else {
            $records = $this->repository->findForUser((int) $user['id']);
        }
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

    private function collectArrayInput(mixed $input): array
    {
        if (!is_array($input)) {
            return [];
        }
        return array_values(array_filter(array_map('strval', $input)));
    }
}
