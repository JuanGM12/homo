<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\PicRepository;
use App\Services\Auth;
use App\Services\Flash;

final class PicController
{
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

        return Response::view('pic/index', [
            'pageTitle' => 'Seguimiento PIC',
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
        if (!$this->userCanAccessModule($user)) {
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
        if (!$this->userCanAccessModule($user)) {
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
        if (!$this->userCanAccessModule($user)) {
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
                'message' => 'Aún no tienes registros de Seguimiento PIC para exportar.',
            ]);
            return Response::redirect('/pic');
        }

        $lines = [];
        $lines[] = implode(';', [
            'Fecha registro',
            'Nombre',
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
