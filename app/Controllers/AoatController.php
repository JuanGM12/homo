<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\AoatRepository;
use App\Services\Auth;
use App\Services\Flash;
use App\Services\Mailer;
use Dompdf\Dompdf;
use Dompdf\Options;

final class AoatController
{
    public function index(Request $request): Response
    {
        $user = Auth::user();
        if ($user === null) {
            return Response::redirect('/login');
        }
        if (!$this->userCanAccessAoat($user)) {
            return Response::view('errors/403', ['pageTitle' => 'Acceso denegado'], 403);
        }

        $repo = new AoatRepository();
        $roles = $user['roles'] ?? [];

        $isSpecialist = in_array('especialista', $roles, true);
        $isCoordinator = in_array('coordinadora', $roles, true) || in_array('coordinador', $roles, true);
        $isAdmin = in_array('admin', $roles, true);

        $isAuditView = $isSpecialist || $isCoordinator || $isAdmin;

        $search = trim((string) $request->input('q', ''));
        $stateFilter = trim((string) $request->input('state', ''));
        $fromDate = trim((string) $request->input('from_date', ''));
        $toDate = trim((string) $request->input('to_date', ''));

        if ($isAuditView) {
            // Vista de auditoría: especialistas, coordinadora y admin.
            // Regla de asignación según perfil principal del usuario.
            $primaryRole = strtolower((string) ($user['role'] ?? (($roles[0] ?? '') ?: '')));
            $auditRoles = [];

            if ($isAdmin || $isCoordinator) {
                // Admin y coordinadora pueden ver todos los registros.
                $auditRoles = [];
            } elseif ($isSpecialist) {
                if ($primaryRole === 'medico') {
                    $auditRoles = ['medico'];
                } elseif ($primaryRole === 'abogado') {
                    $auditRoles = ['abogado'];
                } elseif ($primaryRole === 'psicologo') {
                    // Psicólogo especialista audita a Psicólogos y Profesional Social
                    $auditRoles = ['psicologo', 'profesional social', 'profesional_social'];
                }
            }

            $records = $repo->findForAudit($auditRoles);
        } else {
            // Vista de profesional: solo sus propios registros.
            $records = $repo->findForUser((int) $user['id']);
        }

        // Filtros en memoria (suficiente para el volumen esperado)
        if ($search !== '' || $stateFilter !== '' || $fromDate !== '' || $toDate !== '') {
            $records = array_values(array_filter($records, static function (array $row) use ($search, $stateFilter, $fromDate, $toDate): bool {
                if ($stateFilter !== '' && (string) ($row['state'] ?? '') !== $stateFilter) {
                    return false;
                }

                // Filtro por rango de fechas (usamos created_at, formato YYYY-MM-DD HH:MM:SS)
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
                    (string) ($row['professional_name'] ?? ''),
                    (string) ($row['professional_last_name'] ?? ''),
                    (string) ($row['subregion'] ?? ''),
                    (string) ($row['municipality'] ?? ''),
                    (string) ($row['id'] ?? ''),
                ]);

                return stripos($haystack, $search) !== false;
            }));
        }

        // Respuesta parcial para AJAX: solo filas de la tabla
        if ((string) $request->input('partial', '') === 'rows') {
            $html = $this->renderAoatRowsPartial($records, $isAuditView, $user);

            return Response::json(['html' => $html]);
        }

        return Response::view('aoat/index', [
            'pageTitle' => 'AoAT - Mis registros',
            'records' => $records,
            'isAuditView' => $isAuditView,
        ]);
    }

    public function create(Request $request): Response
    {
        $user = Auth::user();
        if ($user === null) {
            return Response::redirect('/login');
        }
        if (!$this->userCanAccessAoat($user)) {
            return Response::view('errors/403', ['pageTitle' => 'Acceso denegado'], 403);
        }

        // Datos del profesional que diligencia la AoAT (no editables en el formulario)
        $professional = [
            'id' => (int) $user['id'],
            'name' => (string) ($user['name'] ?? ''),
            'last_name' => (string) ($user['last_name'] ?? ''),
            'email' => (string) ($user['email'] ?? ''),
            'role' => (string) ($user['role'] ?? ''),
            'profession' => (string) ($user['profession'] ?? ''),
        ];

        return Response::view('aoat/form', [
            'pageTitle' => 'Registrar AoAT',
            'mode' => 'create',
            'record' => null,
            'professional' => $professional,
        ]);
    }

    public function store(Request $request): Response
    {
        $user = Auth::user();
        if ($user === null) {
            return Response::redirect('/login');
        }
        if (!$this->userCanAccessAoat($user)) {
            return Response::view('errors/403', ['pageTitle' => 'Acceso denegado'], 403);
        }

        // Datos del profesional desde la sesión (no provienen del formulario)
        $professionalName = trim((string) ($user['name'] ?? ''));
        $professionalLastName = trim((string) ($user['last_name'] ?? ''));
        $profession = trim((string) ($user['profession'] ?? ''));
        $professionalEmail = (string) ($user['email'] ?? '');
        $professionalRole = (string) (($user['role'] ?? '') ?: 'profesional');

        // Datos propios del registro de la AoAT
        $aoatNumber = trim((string) $request->input('aoat_number', ''));
        $activityDate = trim((string) $request->input('activity_date', ''));
        $activityType = trim((string) $request->input('activity_type', ''));
        $activityWith = trim((string) $request->input('activity_with', ''));
        $subregion = trim((string) $request->input('subregion', ''));
        $municipality = trim((string) $request->input('municipality', ''));

        // Validamos únicamente los campos que la persona diligencia en el formulario.
        // Los datos del profesional provienen de la sesión y, si por alguna razón están incompletos,
        // no deben bloquear el registro de la AoAT.
        if (
            $aoatNumber === '' ||
            $activityDate === '' ||
            $activityType === '' ||
            $activityWith === '' ||
            $subregion === '' ||
            $municipality === ''
        ) {
            Flash::set([
                'type' => 'error',
                'title' => 'Campos obligatorios incompletos',
                'message' => 'Por favor completa todos los campos requeridos.',
            ]);

            return Response::redirect('/aoat/nueva');
        }

        // Por ahora capturamos todo el resto de campos del formulario en una estructura flexible (payload)
        $payload = $this->buildPayload($request);

        $repo = new AoatRepository();

        $data = [
            'user_id' => (int) $user['id'],
            'professional_name' => $professionalName,
            'professional_last_name' => $professionalLastName,
            'professional_email' => $professionalEmail,
            'professional_role' => $professionalRole,
            'profession' => $profession,
            'subregion' => $subregion,
            'municipality' => $municipality,
            // Regla: todos los registros inician en "Asignada" y el profesional no puede cambiarlo
            'state' => 'Asignada',
            'audit_observation' => null,
            'audit_motive' => null,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ];

        try {
            $repo->create($data);
        } catch (\PDOException $e) {
            Flash::set([
                'type' => 'error',
                'title' => 'No fue posible guardar la AoAT',
                'message' => 'Ocurrió un problema al registrar la asesoría/asistencia técnica. Intenta nuevamente en unos minutos.',
            ]);

            return Response::redirect('/aoat/nueva');
        }

        Flash::set([
            'type' => 'success',
            'title' => 'AoAT registrada',
            'message' => 'Tu registro de AoAT se ha guardado correctamente.',
        ]);

        return Response::redirect('/aoat');
    }

    public function reportForm(Request $request): Response
    {
        $user = Auth::user();
        if ($user === null) {
            return Response::redirect('/login');
        }
        if (!$this->userCanGenerateWeeklyReport($user)) {
            return Response::view('errors/403', ['pageTitle' => 'Acceso denegado'], 403);
        }

        // Valores sugeridos: última semana (se puede ajustar en el formulario)
        $today = new \DateTimeImmutable('today');
        $oneWeekAgo = $today->modify('-7 days');

        return Response::view('aoat/report', [
            'pageTitle' => 'Reporte semanal AoAT',
            'defaultFrom' => $oneWeekAgo->format('Y-m-d'),
            'defaultTo' => $today->format('Y-m-d'),
        ]);
    }

    public function sendWeeklyReport(Request $request): Response
    {
        $user = Auth::user();
        if ($user === null) {
            return Response::redirect('/login');
        }
        if (!$this->userCanGenerateWeeklyReport($user)) {
            return Response::view('errors/403', ['pageTitle' => 'Acceso denegado'], 403);
        }

        $fromDate = trim((string) $request->input('from_date', ''));
        $toDate = trim((string) $request->input('to_date', ''));

        if ($fromDate === '' || $toDate === '') {
            Flash::set([
                'type' => 'error',
                'title' => 'Rango de fechas requerido',
                'message' => 'Debes seleccionar la fecha inicial y final para generar el reporte.',
            ]);

            return Response::redirect('/aoat/reportes');
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
            Flash::set([
                'type' => 'error',
                'title' => 'Formato de fecha no válido',
                'message' => 'Las fechas deben tener el formato YYYY-MM-DD.',
            ]);

            return Response::redirect('/aoat/reportes');
        }

        if ($fromDate > $toDate) {
            Flash::set([
                'type' => 'error',
                'title' => 'Rango de fechas inválido',
                'message' => 'La fecha inicial no puede ser mayor que la fecha final.',
            ]);

            return Response::redirect('/aoat/reportes');
        }

        $action = trim((string) $request->input('action', 'email'));
        $reportData = $this->buildWeeklyReportData($fromDate, $toDate);
        $subject = sprintf('Reporte semanal AoAT (%s a %s)', $fromDate, $toDate);

        if ($action === 'pdf') {
            $pdfBinary = $this->renderWeeklyReportPdf($fromDate, $toDate, $reportData);
            $filename = 'reporte_semanal_aoat_' . $fromDate . '_a_' . $toDate . '.pdf';

            return new Response($pdfBinary, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        }

        $htmlForEmail = $this->buildWeeklyReportHtml($fromDate, $toDate, $reportData, false);
        $pdfBinary = $this->renderWeeklyReportPdf($fromDate, $toDate, $reportData);

        $mailer = new Mailer();
        $mailer->sendAoatWeeklyReport(
            $htmlForEmail,
            $subject,
            $pdfBinary,
            'reporte_semanal_aoat_' . $fromDate . '_a_' . $toDate . '.pdf'
        );

        Flash::set([
            'type' => 'success',
            'title' => 'Reporte enviado',
            'message' => 'El reporte semanal fue enviado al correo de coordinación con el PDF adjunto.',
        ]);

        return Response::redirect('/aoat/reportes');
    }

    public function export(Request $request): Response
    {
        $user = Auth::user();
        if ($user === null) {
            return Response::redirect('/login');
        }
        if (!$this->userCanAccessAoat($user)) {
            return Response::view('errors/403', ['pageTitle' => 'Acceso denegado'], 403);
        }

        $repo = new AoatRepository();
        $roles = $user['roles'] ?? [];

        $isSpecialist = in_array('especialista', $roles, true);
        $isCoordinator = in_array('coordinadora', $roles, true);
        $isAdmin = in_array('admin', $roles, true);

        $isAuditView = $isSpecialist || $isCoordinator || $isAdmin;

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

            $records = $repo->findForAudit($auditRoles);
        } else {
            $records = $repo->findForUser((int) $user['id']);
        }

        if ($search !== '' || $stateFilter !== '' || $fromDate !== '' || $toDate !== '') {
            $records = array_values(array_filter($records, static function (array $row) use ($search, $stateFilter, $fromDate, $toDate): bool {
                if ($stateFilter !== '' && (string) ($row['state'] ?? '') !== $stateFilter) {
                    return false;
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
                    (string) ($row['professional_name'] ?? ''),
                    (string) ($row['professional_last_name'] ?? ''),
                    (string) ($row['subregion'] ?? ''),
                    (string) ($row['municipality'] ?? ''),
                    (string) ($row['id'] ?? ''),
                ]);

                return stripos($haystack, $search) !== false;
            }));
        }

        if ($records === []) {
            Flash::set([
                'type' => 'info',
                'title' => 'Sin registros para exportar',
                'message' => 'No hay registros de AoAT para exportar con los filtros actuales.',
            ]);

            return Response::redirect('/aoat');
        }

        $lines = [];
        $lines[] = implode(';', [
            'ID',
            'Fecha registro',
            'Profesional',
            'Subregión',
            'Municipio',
            'Estado AoAT',
            'Motivo auditoría',
            'Observación auditoría',
        ]);

        foreach ($records as $row) {
            $professionalFullName = trim(((string) ($row['professional_name'] ?? '')) . ' ' . ((string) ($row['professional_last_name'] ?? '')));
            $line = [
                (string) ($row['id'] ?? ''),
                (string) ($row['created_at'] ?? ''),
                $professionalFullName,
                (string) ($row['subregion'] ?? ''),
                (string) ($row['municipality'] ?? ''),
                (string) ($row['state'] ?? ''),
                (string) ($row['audit_motive'] ?? ''),
                (string) ($row['audit_observation'] ?? ''),
            ];

            $lines[] = implode(';', array_map(static function (string $value): string {
                $escaped = str_replace('"', '""', $value);
                return '"' . $escaped . '"';
            }, $line));
        }

        $csvContent = "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n";
        $filename = 'aoat_registros_' . date('Ymd_His') . '.csv';

        return new Response($csvContent, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function edit(Request $request): Response
    {
        $user = Auth::user();
        if ($user === null) {
            return Response::redirect('/login');
        }
        if (!$this->userCanAccessAoat($user)) {
            return Response::view('errors/403', ['pageTitle' => 'Acceso denegado'], 403);
        }

        $id = (int) $request->input('id', 0);
        if ($id <= 0) {
            return Response::redirect('/aoat');
        }

        $repo = new AoatRepository();
        $record = $repo->findById($id);

        if ($record === null || (int) $record['user_id'] !== (int) $user['id']) {
            Flash::set([
                'type' => 'error',
                'title' => 'No autorizado',
                'message' => 'No puedes editar este registro de AoAT.',
            ]);

            return Response::redirect('/aoat');
        }

        if (($record['state'] ?? '') === 'Aprobada') {
            Flash::set([
                'type' => 'info',
                'title' => 'Edición no permitida',
                'message' => 'Este registro ya fue aprobado por el especialista y no puede modificarse.',
            ]);

            return Response::redirect('/aoat');
        }

        $professional = [
            'id' => (int) $user['id'],
            'name' => (string) ($user['name'] ?? ''),
            'last_name' => (string) ($user['last_name'] ?? ''),
            'email' => (string) ($user['email'] ?? ''),
            'role' => (string) ($user['role'] ?? ''),
            'profession' => (string) ($user['profession'] ?? ''),
        ];

        return Response::view('aoat/form', [
            'pageTitle' => 'Editar AoAT',
            'mode' => 'edit',
            'record' => $record,
            'professional' => $professional,
        ]);
    }

    public function update(Request $request): Response
    {
        $user = Auth::user();
        if ($user === null) {
            return Response::redirect('/login');
        }
        if (!$this->userCanAccessAoat($user)) {
            return Response::view('errors/403', ['pageTitle' => 'Acceso denegado'], 403);
        }

        $id = (int) $request->input('id', 0);
        if ($id <= 0) {
            return Response::redirect('/aoat');
        }

        $repo = new AoatRepository();
        $record = $repo->findById($id);

        if ($record === null || (int) $record['user_id'] !== (int) $user['id']) {
            Flash::set([
                'type' => 'error',
                'title' => 'No autorizado',
                'message' => 'No puedes editar este registro de AoAT.',
            ]);

            return Response::redirect('/aoat');
        }

        if (($record['state'] ?? '') === 'Aprobada') {
            Flash::set([
                'type' => 'info',
                'title' => 'Edición no permitida',
                'message' => 'Este registro ya fue aprobado por el especialista y no puede modificarse.',
            ]);

            return Response::redirect('/aoat');
        }

        // Datos propios del registro de la AoAT
        $aoatNumber = trim((string) $request->input('aoat_number', ''));
        $activityDate = trim((string) $request->input('activity_date', ''));
        $activityType = trim((string) $request->input('activity_type', ''));
        $activityWith = trim((string) $request->input('activity_with', ''));
        $subregion = trim((string) $request->input('subregion', ''));
        $municipality = trim((string) $request->input('municipality', ''));

        if (
            $aoatNumber === '' ||
            $activityDate === '' ||
            $activityType === '' ||
            $activityWith === '' ||
            $subregion === '' ||
            $municipality === ''
        ) {
            Flash::set([
                'type' => 'error',
                'title' => 'Campos obligatorios incompletos',
                'message' => 'Por favor completa todos los campos requeridos.',
            ]);

            return Response::redirect('/aoat/editar?id=' . $id);
        }

        $payload = $this->buildPayload($request);

        try {
            $repo->update($id, [
                'subregion' => $subregion,
                'municipality' => $municipality,
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\PDOException $e) {
            Flash::set([
                'type' => 'error',
                'title' => 'No fue posible actualizar la AoAT',
                'message' => 'Ocurrió un problema al actualizar la asesoría/asistencia técnica. Intenta nuevamente en unos minutos.',
            ]);

            return Response::redirect('/aoat/editar?id=' . $id);
        }

        Flash::set([
            'type' => 'success',
            'title' => 'AoAT actualizada',
            'message' => 'El registro de AoAT se ha actualizado correctamente.',
        ]);

        return Response::redirect('/aoat');
    }

    /**
     * Cambio de estado por parte de especialistas / coordinación.
     * Solo permite transiciones:
     *  - Asignada -> Aprobada
     *  - Asignada -> Devuelta (requiere motivo y observación, y envía correo).
     */
    public function updateState(Request $request): Response
    {
        $user = Auth::user();
        if ($user === null) {
            return Response::redirect('/login');
        }

        $roles = $user['roles'] ?? [];
        $canAudit = in_array('especialista', $roles, true) || in_array('coordinadora', $roles, true) || in_array('admin', $roles, true);
        if (!$canAudit) {
            return Response::view('errors/403', ['pageTitle' => 'Acceso denegado'], 403);
        }

        $id = (int) $request->input('id', 0);
        $newState = (string) $request->input('state', '');
        $observation = trim((string) $request->input('observation', ''));
        $motive = trim((string) $request->input('motive', ''));

        if ($id <= 0 || !in_array($newState, ['Aprobada', 'Devuelta'], true)) {
            Flash::set([
                'type' => 'error',
                'title' => 'Datos no válidos',
                'message' => 'No fue posible actualizar el estado de la AoAT.',
            ]);

            return Response::redirect('/aoat');
        }

        $repo = new AoatRepository();
        $record = $repo->findById($id);
        if ($record === null) {
            Flash::set([
                'type' => 'error',
                'title' => 'Registro no encontrado',
                'message' => 'La AoAT indicada no existe.',
            ]);

            return Response::redirect('/aoat');
        }

        if (($record['state'] ?? '') !== 'Asignada') {
            Flash::set([
                'type' => 'error',
                'title' => 'Estado no válido',
                'message' => 'Solo puedes cambiar registros en estado "Asignada".',
            ]);

            return Response::redirect('/aoat');
        }

        if ($newState === 'Devuelta') {
            $allowedMotives = ['Sin Cargar en AoAT', 'Sin cargar en Drive'];
            if ($observation === '' || !in_array($motive, $allowedMotives, true)) {
                Flash::set([
                    'type' => 'error',
                    'title' => 'Información requerida',
                    'message' => 'Para devolver una AoAT debes indicar el motivo y una observación.',
                ]);

                return Response::redirect('/aoat');
            }

            $repo->update($id, [
                'state' => 'Devuelta',
                'audit_observation' => $observation,
                'audit_motive' => $motive,
            ]);

            // Notificar al profesional
            $toEmail = (string) ($record['professional_email'] ?? '');
            $toName = trim((string) (($record['professional_name'] ?? '') . ' ' . ($record['professional_last_name'] ?? '')));
            $mailer = new Mailer();
            $mailer->sendAoatReturnedNotification($toEmail, $toName, $observation, $motive, $id);

            Flash::set([
                'type' => 'success',
                'title' => 'AoAT devuelta',
                'message' => 'El estado de la AoAT se actualizó a "Devuelta" y se notificó al profesional.',
            ]);
        } else {
            // Aprobada
            $repo->update($id, [
                'state' => 'Aprobada',
                'audit_observation' => null,
                'audit_motive' => null,
            ]);

            Flash::set([
                'type' => 'success',
                'title' => 'AoAT aprobada',
                'message' => 'La AoAT ha sido aprobada. El registro queda cerrado para nuevas modificaciones.',
            ]);
        }

        return Response::redirect('/aoat');
    }

    private function userCanAccessAoat(array $user): bool
    {
        $roles = $user['roles'] ?? [];
        $allowed = ['abogado', 'medico', 'psicologo', 'profesional social', 'profesional_social', 'admin', 'especialista', 'coordinadora', 'coordinador'];
        return (bool) array_intersect($allowed, $roles);
    }

    private function userCanGenerateWeeklyReport(array $user): bool
    {
        $roles = $user['roles'] ?? [];
        return in_array('admin', $roles, true)
            || in_array('coordinadora', $roles, true)
            || in_array('coordinador', $roles, true);
    }

    /**
     * Renderiza solo las filas de la tabla de AoAT (para AJAX).
     *
     * @param array<int, array<string, mixed>> $records
     */
    private function renderAoatRowsPartial(array $records, bool $isAuditView, array $user): string
    {
        $isAuditViewLocal = $isAuditView;
        $currentUser = $user;

        ob_start();
        /** @var array<int, array<string, mixed>> $records */
        $isAudit = $isAuditViewLocal;
        $currentUserLocal = $currentUser;
        require __DIR__ . '/../Views/aoat/_rows.php';
        return (string) ob_get_clean();
    }

    private function buildPayload(Request $request): array
    {
        // Estructura base para luego incluir las preguntas específicas por rol
        $payload = $_POST;

        unset(
            $payload['professional_name'],
            $payload['professional_last_name'],
            $payload['profession'],
            $payload['subregion'],
            $payload['municipality']
        );

        return $payload;
    }

    /**
     * @return array<int,array{title:string,rows:array}>
     */
    private function buildWeeklyReportData(string $fromDate, string $toDate): array
    {
        $repo = new AoatRepository();

        return [
            [
                'title' => 'Psicólogos',
                'rows' => $repo->findByRoleAndDateRange('psicologo', $fromDate, $toDate),
            ],
            [
                'title' => 'Profesionales Sociales',
                'rows' => $repo->findByRoleAndDateRange('profesional social', $fromDate, $toDate),
            ],
            [
                'title' => 'Médicos',
                'rows' => $repo->findByRoleAndDateRange('medico', $fromDate, $toDate),
            ],
            [
                'title' => 'Abogados',
                'rows' => $repo->findByRoleAndDateRange('abogado', $fromDate, $toDate),
            ],
        ];
    }

    /**
     * @param array<int,array{title:string,rows:array}> $reportData
     */
    private function buildWeeklyReportHtml(string $fromDate, string $toDate, array $reportData, bool $forPdf): string
    {
        $logoHomo = dirname(__DIR__, 2) . '/public/assets/img/logoHomo.png';
        $logoAntioquia = dirname(__DIR__, 2) . '/public/assets/img/logoAntioquia.png';

        $logoHomoSrc = $forPdf ? $this->toImageDataUri($logoHomo) : 'cid:logo_homo';
        $logoAntioquiaSrc = $forPdf ? $this->toImageDataUri($logoAntioquia) : 'cid:logo_antioquia';

        $totalRows = 0;
        foreach ($reportData as $section) {
            $totalRows += count($section['rows'] ?? []);
        }

        $html = '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Reporte semanal AoAT</title>';
        $html .= '<style>
            body{font-family:DejaVu Sans,Arial,sans-serif;color:#2a5543;font-size:12px;margin:0;padding:0;background:#ffffff;}
            .page{padding:18px 20px;}
            .header{border:1px solid #9ac6eb;border-radius:10px;padding:12px;background:#e5d9cc;}
            .header-top{width:100%;border-collapse:collapse;}
            .header-top td{vertical-align:middle;}
            .logo-homo{height:54px;}
            .logo-ant{height:54px;}
            .title{font-size:18px;font-weight:700;color:#4160a4;margin:4px 0 2px;}
            .subtitle{font-size:12px;color:#2a5543;margin:0;}
            .meta{margin-top:10px;font-size:11px;color:#2a5543;}
            .summary{margin-top:12px;padding:8px 10px;border:1px solid #9ac6eb;border-radius:8px;background:#fff;}
            .summary strong{color:#4160a4;}
            .section{margin-top:16px;}
            .section h2{font-size:14px;color:#4160a4;margin:0 0 8px;padding:6px 8px;background:#9ac6eb;border-radius:6px;}
            table{width:100%;border-collapse:collapse;}
            th,td{border:1px solid #d8e8f5;padding:5px 6px;vertical-align:top;}
            th{background:#f4eee7;color:#2a5543;font-weight:700;font-size:11px;}
            td{font-size:11px;color:#2a5543;}
            .empty{padding:10px;border:1px dashed #9ac6eb;border-radius:6px;background:#fff;color:#4160a4;}
            .footer{margin-top:14px;font-size:10px;color:#5b6d8f;}
        </style></head><body><div class="page">';

        $html .= '<div class="header"><table class="header-top"><tr>';
        $html .= '<td style="width:30%;"><img src="' . htmlspecialchars($logoAntioquiaSrc, ENT_QUOTES, 'UTF-8') . '" alt="Gobernación de Antioquia" class="logo-ant"></td>';
        $html .= '<td style="width:40%;text-align:center;"><p class="title">Reporte semanal AoAT</p><p class="subtitle">Acción en Territorio · Equipo de Promoción y Prevención</p></td>';
        $html .= '<td style="width:30%;text-align:right;"><img src="' . htmlspecialchars($logoHomoSrc, ENT_QUOTES, 'UTF-8') . '" alt="HOMO" class="logo-homo"></td>';
        $html .= '</tr></table>';
        $html .= '<p class="meta">Rango de fechas: <strong>' . htmlspecialchars($fromDate, ENT_QUOTES, 'UTF-8') . '</strong> a <strong>' . htmlspecialchars($toDate, ENT_QUOTES, 'UTF-8') . '</strong></p>';
        $html .= '<div class="summary">Total de registros AoAT en el periodo: <strong>' . $totalRows . '</strong></div></div>';

        foreach ($reportData as $section) {
            $title = (string) ($section['title'] ?? '');
            $rows = $section['rows'] ?? [];
            $html .= '<div class="section"><h2>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h2>';

            if ($rows === []) {
                $html .= '<div class="empty">No se encontraron registros en el rango de fechas.</div></div>';
                continue;
            }

            $html .= '<table><thead><tr>'
                . '<th>Fecha actividad</th>'
                . '<th>Profesional</th>'
                . '<th>Actividad</th>'
                . '<th>Rol</th>'
                . '<th>Acciones</th>'
                . '<th>Subregión</th>'
                . '<th>Municipio</th>'
                . '</tr></thead><tbody>';

            foreach ($rows as $row) {
                $payload = [];
                if (isset($row['payload']) && $row['payload'] !== null) {
                    $decoded = json_decode((string) $row['payload'], true);
                    if (is_array($decoded)) {
                        $payload = $decoded;
                    }
                }

                $activityDate = (string) ($row['activity_date_json'] ?? '');
                $formattedDate = $activityDate;
                if ($activityDate !== '') {
                    try {
                        $dt = new \DateTimeImmutable($activityDate);
                        $formattedDate = $dt->format('d/m/Y');
                    } catch (\Exception) {
                    }
                }

                $professionalFullName = trim((string) ($row['professional_name'] ?? '') . ' ' . (string) ($row['professional_last_name'] ?? ''));
                $activityType = (string) ($row['activity_type_json'] ?? '');
                $role = (string) ($row['professional_role'] ?? '');
                $roleLabel = ucwords(str_replace('_', ' ', $role));
                $actions = $this->buildActionsSummary($role, $payload);
                $subregion = (string) ($row['subregion'] ?? '');
                $municipality = (string) ($row['municipality'] ?? '');

                $html .= '<tr>'
                    . '<td>' . htmlspecialchars($formattedDate, ENT_QUOTES, 'UTF-8') . '</td>'
                    . '<td>' . htmlspecialchars($professionalFullName, ENT_QUOTES, 'UTF-8') . '</td>'
                    . '<td>' . htmlspecialchars($activityType, ENT_QUOTES, 'UTF-8') . '</td>'
                    . '<td>' . htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') . '</td>'
                    . '<td>' . htmlspecialchars($actions, ENT_QUOTES, 'UTF-8') . '</td>'
                    . '<td>' . htmlspecialchars($subregion, ENT_QUOTES, 'UTF-8') . '</td>'
                    . '<td>' . htmlspecialchars($municipality, ENT_QUOTES, 'UTF-8') . '</td>'
                    . '</tr>';
            }

            $html .= '</tbody></table></div>';
        }

        $html .= '<p class="footer">Documento generado automáticamente desde la plataforma Acción en Territorio.</p>';
        $html .= '</div></body></html>';

        return $html;
    }

    private function toImageDataUri(string $path): string
    {
        if (!is_readable($path)) {
            return '';
        }

        $binary = file_get_contents($path);
        if ($binary === false) {
            return '';
        }

        $mime = 'image/png';
        if (function_exists('mime_content_type')) {
            $detected = mime_content_type($path);
            if (is_string($detected) && $detected !== '') {
                $mime = $detected;
            }
        }

        return 'data:' . $mime . ';base64,' . base64_encode($binary);
    }

    /**
     * @param array<int,array{title:string,rows:array}> $reportData
     */
    private function renderWeeklyReportPdf(string $fromDate, string $toDate, array $reportData): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $html = $this->buildWeeklyReportHtml($fromDate, $toDate, $reportData, true);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return $dompdf->output();
    }

    private function buildActionsSummary(string $role, array $payload): string
    {
        $role = strtolower($role);
        $parts = [];

        if ($role === 'psicologo') {
            if (!empty($payload['prev_suicidio']) && is_array($payload['prev_suicidio'])) {
                $parts[] = 'Prevención suicidio: ' . implode(', ', $payload['prev_suicidio']);
            }
            if (!empty($payload['prev_violencias']) && is_array($payload['prev_violencias'])) {
                $parts[] = 'Prevención violencias: ' . implode(', ', $payload['prev_violencias']);
            }
            if (!empty($payload['prev_adicciones']) && is_array($payload['prev_adicciones'])) {
                $parts[] = 'Prevención adicciones: ' . implode(', ', $payload['prev_adicciones']);
            }
            if (!empty($payload['salud_mental']) && is_array($payload['salud_mental'])) {
                $parts[] = 'Salud mental: ' . implode(', ', $payload['salud_mental']);
            }
            if (!empty($payload['proyecto']) && is_string($payload['proyecto'])) {
                $parts[] = 'Proyecto: ' . $payload['proyecto'];
            }
        } elseif ($role === 'medico') {
            if (!empty($payload['temas_hospital']) && is_array($payload['temas_hospital'])) {
                $parts[] = implode(', ', $payload['temas_hospital']);
            }
        } elseif ($role === 'abogado') {
            if (!empty($payload['mesa_salud_mental']) && is_array($payload['mesa_salud_mental'])) {
                $parts[] = 'Mesa salud mental: ' . implode(', ', $payload['mesa_salud_mental']);
            }
            if (!empty($payload['ppmsmypa']) && is_array($payload['ppmsmypa'])) {
                $parts[] = 'PPMSMYPA: ' . implode(', ', $payload['ppmsmypa']);
            }
            if (!empty($payload['safer']) && is_array($payload['safer'])) {
                $parts[] = 'SAFER: ' . implode(', ', $payload['safer']);
            }
        } elseif ($role === 'profesional social' || $role === 'profesional_social') {
            if (!empty($payload['actividad_social']) && is_array($payload['actividad_social'])) {
                $parts[] = implode(', ', $payload['actividad_social']);
            }
        }

        return implode(' | ', $parts);
    }
}

