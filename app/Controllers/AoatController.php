<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\AoatRepository;
use App\Services\Auth;
use App\Services\Flash;
use App\Services\Mailer;
use App\Services\PdfImageHelper;
use App\Services\PdfService;

final class AoatController
{
    private const INDEX_PAGE_SIZE = 20;
    private const FORM_OLD_INPUT_KEY = 'aoat_old_input';

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

        $canViewAll = Auth::canViewAllModuleRecords($user);
        $isAuditView = $canViewAll;

        $search = trim((string) $request->input('q', ''));
        $stateFilter = trim((string) $request->input('state', ''));
        $fromDate = trim((string) $request->input('from_date', ''));
        $toDate = trim((string) $request->input('to_date', ''));
        $sort = trim((string) $request->input('sort', 'activity_date'));
        $dir = strtolower(trim((string) $request->input('dir', 'desc')));
        $currentPage = max(1, (int) $request->input('page', 1));

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

        if (!$canViewAll) {
            $records = Auth::scopeRowsToOwnerUser($records, (int) $user['id']);
        }

        $records = $this->attachActivityDateToRecords($records);

        // Filtros en memoria (suficiente para el volumen esperado)
        if ($search !== '' || $stateFilter !== '' || $fromDate !== '' || $toDate !== '') {
            $records = array_values(array_filter($records, static function (array $row) use ($search, $stateFilter, $fromDate, $toDate): bool {
                if ($stateFilter !== '' && (string) ($row['state'] ?? '') !== $stateFilter) {
                    return false;
                }

                // Filtro por rango de fechas usando la fecha de la actividad.
                $activityDate = trim((string) ($row['activity_date'] ?? ''));

                if ($fromDate !== '' && $activityDate !== '' && $activityDate < $fromDate) {
                    return false;
                }

                if ($toDate !== '' && $activityDate !== '' && $activityDate > $toDate) {
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

        $records = $this->sortRecords($records, $sort, $dir);

        $pagination = $this->paginateRecords($records, $currentPage, self::INDEX_PAGE_SIZE);
        $paginatedRecords = $pagination['items'];

        // Respuesta parcial para AJAX: resultados del listado
        if ((string) $request->input('partial', '') === 'rows') {
            $html = $this->renderAoatResultsPartial($paginatedRecords, $pagination, $isAuditView, $user);

            return Response::json(['html' => $html]);
        }

        return Response::view('aoat/index', [
            'pageTitle' => 'AoAT - Mis registros',
            'records' => $paginatedRecords,
            'pagination' => $pagination,
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
            'oldInput' => $this->consumeOldInput('create'),
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
        $missingRequiredFields = $this->findMissingAoatBaseFields(
            $aoatNumber,
            $activityDate,
            $activityType,
            $activityWith,
            $subregion,
            $municipality
        );
        if ($missingRequiredFields !== []) {
            $this->flashOldInput('create', 0, $request);
            Flash::set([
                'type' => 'error',
                'title' => 'Campos obligatorios incompletos',
                'message' => 'Completa estos campos: ' . implode(', ', $missingRequiredFields) . '.',
            ]);

            return Response::redirect('/aoat/nueva');
        }

        if (!$this->isActivityDateYear2026OrLater($activityDate)) {
            $this->flashOldInput('create', 0, $request);
            Flash::set([
                'type' => 'error',
                'title' => 'Fecha de la actividad no válida',
                'message' => 'La fecha de la actividad no puede ser anterior al 1 de enero de 2026.',
            ]);

            return Response::redirect('/aoat/nueva');
        }

        $qualError = $this->validateAoatQualificationSections($request, $user);
        if ($qualError !== null) {
            $this->flashOldInput('create', 0, $request);
            Flash::set([
                'type' => 'error',
                'title' => 'Cualificación incompleta',
                'message' => $qualError,
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
            $this->clearOldInput();
        } catch (\PDOException $e) {
            $this->flashOldInput('create', 0, $request);
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
        $singleId = (int) $request->input('id', 0);
        $format = strtolower(trim((string) $request->input('format', '')));

        if ($singleId > 0) {
            $record = $repo->findById($singleId);
            if ($record === null) {
                Flash::set([
                    'type' => 'error',
                    'title' => 'Registro no encontrado',
                    'message' => 'La AoAT que intentas exportar no existe.',
                ]);

                return Response::redirect('/aoat');
            }

            if (!$this->canExportSingleAoat($user, $record)) {
                Flash::set([
                    'type' => 'error',
                    'title' => 'Exportación no permitida',
                    'message' => 'Solo puedes exportar individualmente AoAT aprobadas, excepto si tu rol es admin, coordinador o especialista.',
                ]);

                return Response::redirect('/aoat');
            }

            if (!in_array($format, ['pdf', 'xls', 'excel'], true)) {
                Flash::set([
                    'type' => 'info',
                    'title' => 'Formato no válido',
                    'message' => 'Selecciona un formato de exportación válido (PDF o Excel).',
                ]);

                return Response::redirect('/aoat');
            }

            if ($format === 'pdf') {
                return $this->exportSingleAoatPdf($record);
            }

            return $this->exportSingleAoatExcel($record);
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

            $records = $repo->findForAudit($auditRoles);
        } else {
            $records = $repo->findForUser((int) $user['id']);
        }

        if (!$canViewAll) {
            $records = Auth::scopeRowsToOwnerUser($records, (int) $user['id']);
        }

        $records = $this->attachActivityDateToRecords($records);

        if ($search !== '' || $stateFilter !== '' || $fromDate !== '' || $toDate !== '') {
            $records = array_values(array_filter($records, static function (array $row) use ($search, $stateFilter, $fromDate, $toDate): bool {
                if ($stateFilter !== '' && (string) ($row['state'] ?? '') !== $stateFilter) {
                    return false;
                }

                $activityDate = trim((string) ($row['activity_date'] ?? ''));

                if ($fromDate !== '' && $activityDate !== '' && $activityDate < $fromDate) {
                    return false;
                }

                if ($toDate !== '' && $activityDate !== '' && $activityDate > $toDate) {
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
            'Fecha actividad',
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
                (string) ($row['activity_date'] ?? ''),
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

        $canEditApprovedNumberOnly = $this->canEditApprovedOrRealizadoAoatNumber($record);

        if (($record['state'] ?? '') === 'Aprobada' && !$canEditApprovedNumberOnly) {
            Flash::set([
                'type' => 'info',
                'title' => 'Edición no permitida',
                'message' => 'Este registro ya fue aprobado por el especialista y no puede modificarse.',
            ]);

            return Response::redirect('/aoat');
        }

        if (($record['state'] ?? '') === 'Realizado' && !$canEditApprovedNumberOnly) {
            Flash::set([
                'type' => 'info',
                'title' => 'En revisión del especialista',
                'message' => 'Este registro está en estado "Realizado" a la espera de aprobación. No puedes editarlo hasta que el especialista lo apruebe o lo devuelva nuevamente.',
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
            'oldInput' => $this->consumeOldInput('edit', (int) $record['id']),
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

        $canEditApprovedNumberOnly = $this->canEditApprovedOrRealizadoAoatNumber($record);

        if (($record['state'] ?? '') === 'Aprobada' && !$canEditApprovedNumberOnly) {
            Flash::set([
                'type' => 'info',
                'title' => 'Edición no permitida',
                'message' => 'Este registro ya fue aprobado por el especialista y no puede modificarse.',
            ]);

            return Response::redirect('/aoat');
        }

        if (($record['state'] ?? '') === 'Realizado' && !$canEditApprovedNumberOnly) {
            Flash::set([
                'type' => 'info',
                'title' => 'En revisión del especialista',
                'message' => 'No puedes editar un registro en estado "Realizado" hasta que el especialista lo apruebe o lo devuelva.',
            ]);

            return Response::redirect('/aoat');
        }

        if ($canEditApprovedNumberOnly) {
            $aoatNumber = trim((string) $request->input('aoat_number', ''));
            if ($aoatNumber === '') {
                $this->flashOldInput('edit', $id, $request);
                Flash::set([
                    'type' => 'error',
                    'title' => 'Campo obligatorio',
                    'message' => 'Debes indicar el Número de la AoAT o actividad.',
                ]);

                return Response::redirect('/aoat/editar?id=' . $id);
            }

            $payload = $this->decodePayload($record);
            $payload['aoat_number'] = $aoatNumber;

            try {
                $repo->update($id, [
                    'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                ]);
                $this->clearOldInput();
            } catch (\PDOException $e) {
                $this->flashOldInput('edit', $id, $request);
                Flash::set([
                    'type' => 'error',
                    'title' => 'No fue posible actualizar la AoAT',
                    'message' => 'Ocurrió un problema al actualizar el número de la AoAT. Intenta nuevamente en unos minutos.',
                ]);

                return Response::redirect('/aoat/editar?id=' . $id);
            }

            Flash::set([
                'type' => 'success',
                'title' => 'Número AoAT actualizado',
                'message' => 'El número de la AoAT o actividad se actualizó correctamente.',
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

        $missingRequiredFields = $this->findMissingAoatBaseFields(
            $aoatNumber,
            $activityDate,
            $activityType,
            $activityWith,
            $subregion,
            $municipality
        );
        if ($missingRequiredFields !== []) {
            $this->flashOldInput('edit', $id, $request);
            Flash::set([
                'type' => 'error',
                'title' => 'Campos obligatorios incompletos',
                'message' => 'Completa estos campos: ' . implode(', ', $missingRequiredFields) . '.',
            ]);

            return Response::redirect('/aoat/editar?id=' . $id);
        }

        if (!$this->isActivityDateYear2026OrLater($activityDate)) {
            $this->flashOldInput('edit', $id, $request);
            Flash::set([
                'type' => 'error',
                'title' => 'Fecha de la actividad no válida',
                'message' => 'La fecha de la actividad no puede ser anterior al 1 de enero de 2026.',
            ]);

            return Response::redirect('/aoat/editar?id=' . $id);
        }

        $qualError = $this->validateAoatQualificationSections($request, $user);
        if ($qualError !== null) {
            $this->flashOldInput('edit', $id, $request);
            Flash::set([
                'type' => 'error',
                'title' => 'Cualificación incompleta',
                'message' => $qualError,
            ]);

            return Response::redirect('/aoat/editar?id=' . $id);
        }

        $payload = $this->buildPayload($request);

        try {
            $updateData = [
                'subregion' => $subregion,
                'municipality' => $municipality,
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ];
            $wasReturned = (string) ($record['state'] ?? '') === 'Devuelta';
            if ($wasReturned) {
                $updateData['state'] = 'Realizado';
            }

            $repo->update($id, $updateData);
            $this->clearOldInput();
        } catch (\PDOException $e) {
            $this->flashOldInput('edit', $id, $request);
            Flash::set([
                'type' => 'error',
                'title' => 'No fue posible actualizar la AoAT',
                'message' => 'Ocurrió un problema al actualizar la asesoría/asistencia técnica. Intenta nuevamente en unos minutos.',
            ]);

            return Response::redirect('/aoat/editar?id=' . $id);
        }

        $wasReturned = (string) ($record['state'] ?? '') === 'Devuelta';
        Flash::set([
            'type' => 'success',
            'title' => $wasReturned ? 'Cambios guardados y AoAT marcada como realizada' : 'AoAT actualizada',
            'message' => $wasReturned
                ? 'Los ajustes se guardaron correctamente y la AoAT pasó a estado "Realizado" para revisión del especialista.'
                : 'El registro de AoAT se ha actualizado correctamente.',
        ]);

        return Response::redirect('/aoat');
    }

    /**
     * Cambio de estado por parte de especialistas / coordinación.
     * Transiciones:
     *  - Asignada → Aprobada | Devuelta (Devuelta envía correo al profesional, fuera de la petición HTTP).
     *  - Realizado → Aprobada (tras ajustes del profesional).
     */
    public function updateState(Request $request): Response
    {
        $user = Auth::user();
        if ($user === null) {
            return Response::redirect('/login');
        }

        $roles = $user['roles'] ?? [];
        $canAudit = in_array('especialista', $roles, true)
            || in_array('coordinadora', $roles, true)
            || in_array('coordinador', $roles, true)
            || in_array('admin', $roles, true);
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

        // No auditar el propio registro
        if ((int) ($record['user_id'] ?? 0) === (int) ($user['id'] ?? 0)) {
            Flash::set([
                'type' => 'error',
                'title' => 'Acción no permitida',
                'message' => 'No puedes auditar tus propios registros de AoAT.',
            ]);

            return Response::redirect('/aoat');
        }

        $current = (string) ($record['state'] ?? '');

        if ($newState === 'Devuelta') {
            if ($current !== 'Asignada') {
                Flash::set([
                    'type' => 'error',
                    'title' => 'Estado no válido',
                    'message' => 'Solo se puede devolver una AoAT que esté en estado "Asignada".',
                ]);

                return Response::redirect('/aoat');
            }

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

            $toEmail = (string) ($record['professional_email'] ?? '');
            $toName = trim((string) (($record['professional_name'] ?? '') . ' ' . ($record['professional_last_name'] ?? '')));
            $payload = [];
            if (isset($record['payload'])) {
                $decodedPayload = json_decode((string) $record['payload'], true);
                if (is_array($decodedPayload)) {
                    $payload = $decodedPayload;
                }
            }

            Mailer::scheduleAoatReturnedNotification(
                $toEmail,
                $toName,
                $observation,
                $motive,
                $id,
                (string) ($record['subregion'] ?? ''),
                (string) ($record['municipality'] ?? ''),
                (string) ($payload['activity_date'] ?? '')
            );

            Flash::set([
                'type' => 'success',
                'title' => 'AoAT devuelta',
                'message' => 'El estado se actualizó a "Devuelta". El profesional recibirá un correo con la notificación en breve.',
            ]);

            return Response::redirect('/aoat');
        }

        // Aprobada
        if ($newState !== 'Aprobada') {
            return Response::redirect('/aoat');
        }

        if ($current === 'Asignada') {
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

            return Response::redirect('/aoat');
        }

        if ($current === 'Realizado') {
            $repo->update($id, [
                'state' => 'Aprobada',
                'audit_observation' => null,
                'audit_motive' => null,
            ]);

            Flash::set([
                'type' => 'success',
                'title' => 'AoAT aprobada',
                'message' => 'Se aprobó el registro tras la revisión de los ajustes realizados por el profesional.',
            ]);

            return Response::redirect('/aoat');
        }

        Flash::set([
            'type' => 'error',
            'title' => 'Estado no válido',
            'message' => 'No puedes aprobar desde el estado actual. Si fue devuelta, el profesional debe marcarla como "Realizado" antes.',
        ]);

        return Response::redirect('/aoat');
    }

    /**
     * El profesional marca su AoAT como "Realizado" tras los ajustes solicitados (solo desde "Devuelta").
     */
    public function markAsRealizado(Request $request): Response
    {
        $user = Auth::user();
        if ($user === null) {
            return Response::redirect('/login');
        }

        $id = (int) $request->input('id', 0);
        if ($id <= 0) {
            return Response::redirect('/aoat');
        }

        $repo = new AoatRepository();
        $record = $repo->findById($id);

        if ($record === null || (int) $record['user_id'] !== (int) ($user['id'] ?? 0)) {
            Flash::set([
                'type' => 'error',
                'title' => 'No autorizado',
                'message' => 'No puedes modificar este registro.',
            ]);

            return Response::redirect('/aoat');
        }

        if (($record['state'] ?? '') !== 'Devuelta') {
            Flash::set([
                'type' => 'error',
                'title' => 'Estado no válido',
                'message' => 'Solo puedes marcar como "Realizado" cuando la AoAT está en estado "Devuelta".',
            ]);

            return Response::redirect('/aoat');
        }

        $repo->update($id, ['state' => 'Realizado']);

        Flash::set([
            'type' => 'success',
            'title' => 'Estado actualizado',
            'message' => 'Marcaste la AoAT como "Realizado". El especialista podrá revisarla y aprobarla.',
        ]);

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
     * Seguimiento AoAT: la fecha de la actividad no puede ser anterior a 2026.
     */
    private function isActivityDateYear2026OrLater(string $dateYmd): bool
    {
        if ($dateYmd === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateYmd)) {
            return false;
        }

        return $dateYmd >= '2026-01-01';
    }

    /**
     * Renderiza resultados del listado de AoAT (tabla, vací­o y paginación) para AJAX.
     *
     * @param array<int, array<string, mixed>> $records
     * @param array<string, mixed> $pagination
     */
    private function renderAoatResultsPartial(array $records, array $pagination, bool $isAuditView, array $user): string
    {
        $isAuditViewLocal = $isAuditView;
        $currentUser = $user;
        $paginationData = $pagination;

        ob_start();
        /** @var array<int, array<string, mixed>> $records */
        $isAudit = $isAuditViewLocal;
        $currentUserLocal = $currentUser;
        $pagination = $paginationData;
        require __DIR__ . '/../Views/aoat/_results.php';
        return (string) ob_get_clean();
    }

    /**
     * @param array<int, array<string, mixed>> $records
     * @return array{
     *   items: array<int, array<string, mixed>>,
     *   current_page: int,
     *   total_pages: int,
     *   total_items: int,
     *   per_page: int,
     *   from: int,
     *   to: int
     * }
     */
    private function paginateRecords(array $records, int $page, int $perPage): array
    {
        $totalItems = count($records);
        $totalPages = max(1, (int) ceil($totalItems / $perPage));
        $currentPage = min(max(1, $page), $totalPages);
        $offset = ($currentPage - 1) * $perPage;
        $items = array_slice($records, $offset, $perPage);
        $from = $totalItems === 0 ? 0 : $offset + 1;
        $to = $totalItems === 0 ? 0 : min($offset + $perPage, $totalItems);

        return [
            'items' => $items,
            'current_page' => $currentPage,
            'total_pages' => $totalPages,
            'total_items' => $totalItems,
            'per_page' => $perPage,
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $records
     * @return array<int, array<string, mixed>>
     */
    private function sortRecords(array $records, string $sort, string $dir): array
    {
        $allowedSorts = ['activity_date', 'professional', 'subregion', 'municipality', 'state'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'activity_date';
        }

        $dir = $dir === 'asc' ? 'asc' : 'desc';

        usort($records, function (array $a, array $b) use ($sort, $dir): int {
            $valueA = $this->extractSortValue($a, $sort);
            $valueB = $this->extractSortValue($b, $sort);

            if ($valueA === $valueB) {
                $fallbackA = (string) ($a['activity_date'] ?? ($a['created_at'] ?? ''));
                $fallbackB = (string) ($b['activity_date'] ?? ($b['created_at'] ?? ''));
                $cmpFallback = $fallbackA <=> $fallbackB;
                if ($cmpFallback !== 0) {
                    return $dir === 'asc' ? $cmpFallback : -$cmpFallback;
                }

                return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
            }

            $cmp = $valueA <=> $valueB;
            return $dir === 'asc' ? $cmp : -$cmp;
        });

        return $records;
    }

    private function extractSortValue(array $row, string $sort): string
    {
        if ($sort === 'professional') {
            return $this->normalizeSortText(
                trim((string) (($row['professional_name'] ?? '') . ' ' . ($row['professional_last_name'] ?? '')))
            );
        }

        return match ($sort) {
            'activity_date' => (string) ($row['activity_date'] ?? ''),
            'subregion' => $this->normalizeSortText((string) ($row['subregion'] ?? '')),
            'municipality' => $this->normalizeSortText((string) ($row['municipality'] ?? '')),
            'state' => $this->normalizeSortText((string) ($row['state'] ?? '')),
            default => '',
        };
    }

    /**
     * @param array<int, array<string, mixed>> $records
     * @return array<int, array<string, mixed>>
     */
    private function attachActivityDateToRecords(array $records): array
    {
        foreach ($records as &$record) {
            $record['activity_date'] = $this->extractActivityDateFromRecord($record);
        }
        unset($record);

        return $records;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function extractActivityDateFromRecord(array $record): string
    {
        $payload = $this->decodeAoatPayload($record);
        $rawDate = trim((string) ($payload['activity_date'] ?? ''));
        if ($rawDate === '') {
            return '';
        }

        try {
            return (new \DateTimeImmutable($rawDate))->format('Y-m-d');
        } catch (\Exception) {
            return substr($rawDate, 0, 10);
        }
    }

    /**
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    private function decodeAoatPayload(array $record): array
    {
        if (!isset($record['payload']) || $record['payload'] === null) {
            return [];
        }

        $decoded = json_decode((string) $record['payload'], true);

        return is_array($decoded) ? $decoded : [];
    }

    private function normalizeSortText(string $value): string
    {
        $normalized = trim(mb_strtolower($value, 'UTF-8'));
        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized) ?: $normalized;

        return preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
    }

    /**
     * Rol principal del profesional (misma lógica que app/Views/aoat/form.php).
     */
    private function primaryProfessionalRole(array $user): string
    {
        $r = strtolower(trim((string) ($user['role'] ?? '')));
        if ($r === 'profesional_social') {
            return 'profesional social';
        }

        return $r;
    }

    /**
     * @return array<int, string>
     */
    private function inputStringArray(Request $request, string $key): array
    {
        $v = $request->input($key);
        if (is_array($v)) {
            return array_values(array_filter(array_map('trim', $v), static fn (string $s): bool => $s !== ''));
        }
        if (is_string($v) && trim($v) !== '') {
            return [trim($v)];
        }

        return [];
    }

    /**
     * Valida que cada bloque de cualificación del formulario AoAT tenga al menos una respuesta (según perfil).
     * Debe alinearse con las secciones mostradas en cada rama de form.php.
     */
    private function validateAoatQualificationSections(Request $request, array $user): ?string
    {
        $role = $this->primaryProfessionalRole($user);

        if ($role === 'abogado') {
            if ($this->inputStringArray($request, 'mesa_salud_mental') === []) {
                return 'Debes marcar al menos una opción en «Actualización de la Mesa Municipal de Salud Mental y Prevención de las Adicciones».';
            }
            if ($this->inputStringArray($request, 'ppmsmypa') === []) {
                return 'Debes marcar al menos una opción en «Actualización de la Política Pública Municipal de Salud y Prevención de las Adicciones (PPMSMYPA)».';
            }
            if ($this->inputStringArray($request, 'safer') === []) {
                return 'Debes marcar al menos una opción en «SAFER».';
            }

            return null;
        }

        if ($role === 'medico') {
            if ($this->inputStringArray($request, 'temas_hospital') === []) {
                return 'Debes seleccionar al menos un tema dictado en el Hospital del municipio visitado.';
            }

            return null;
        }

        if ($role === 'psicologo') {
            if ($this->inputStringArray($request, 'prev_suicidio') === []) {
                return 'Debes marcar al menos una opción en «Cualificación temas en prevención del suicidio».';
            }
            if ($this->inputStringArray($request, 'prev_violencias') === []) {
                return 'Debes marcar al menos una opción en «Cualificación temas en prevención de Violencias».';
            }
            if ($this->inputStringArray($request, 'prev_adicciones') === []) {
                return 'Debes marcar al menos una opción en «Cualificación temas en prevención de Adicciones».';
            }
            if ($this->inputStringArray($request, 'salud_mental') === []) {
                return 'Debes marcar al menos una opción en «Cualificación temas de Salud Mental».';
            }

            $allowedProyectos = [
                'Competencias Parentales',
                'Familias que se Cuidan',
                'La Aventura de Crecer',
                'Veredas que se Cuidan',
                'Dispositivos comunitarios',
                'Presentación del programa salud para el alma',
                'No aplica',
            ];
            $proyecto = trim((string) $request->input('proyecto', ''));
            if ($proyecto === '' || !in_array($proyecto, $allowedProyectos, true)) {
                return 'Debes seleccionar una opción en «Proyectos».';
            }

            return null;
        }

        if ($role === 'profesional social') {
            if ($this->inputStringArray($request, 'actividad_social') === []) {
                return 'Debes marcar al menos una opción en «Seleccione la actividad realizada».';
            }

            return null;
        }

        // Otros perfiles (p. ej. admin): no muestran bloques de cualificación en el formulario.
        return null;
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

    private function decodePayload(array $record): array
    {
        $payload = json_decode((string) ($record['payload'] ?? ''), true);

        return is_array($payload) ? $payload : [];
    }

    private function canEditApprovedOrRealizadoAoatNumber(array $record): bool
    {
        $state = (string) ($record['state'] ?? '');
        if (!in_array($state, ['Aprobada', 'Realizado'], true)) {
            return false;
        }

        $payload = $this->decodePayload($record);
        $aoatNumber = trim((string) ($payload['aoat_number'] ?? ''));

        return $aoatNumber === '0';
    }

    /**
     * @return string[]
     */
    private function findMissingAoatBaseFields(
        string $aoatNumber,
        string $activityDate,
        string $activityType,
        string $activityWith,
        string $subregion,
        string $municipality
    ): array {
        $missing = [];

        if ($aoatNumber === '') {
            $missing[] = 'Número de la AoAT o actividad';
        }
        if ($activityDate === '') {
            $missing[] = 'Fecha de la actividad';
        }
        if ($activityType === '') {
            $missing[] = 'Actividad que realizó';
        }
        if ($activityWith === '') {
            $missing[] = 'Con quién realizó la actividad';
        }
        if ($subregion === '') {
            $missing[] = 'Subregión que visitó';
        }
        if ($municipality === '') {
            $missing[] = 'Municipio visitado';
        }

        return $missing;
    }

    private function flashOldInput(string $mode, int $recordId, Request $request): void
    {
        $_SESSION[self::FORM_OLD_INPUT_KEY] = [
            'mode' => $mode,
            'record_id' => $recordId,
            'data' => $this->buildFormInput($request),
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

    private function buildFormInput(Request $request): array
    {
        $input = $_POST;
        unset($input['id']);

        return is_array($input) ? $input : [];
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

        $logoHomoSrc = $forPdf ? PdfImageHelper::imageDataUri($logoHomo) : 'cid:logo_homo';
        $logoAntioquiaSrc = $forPdf ? PdfImageHelper::imageDataUri($logoAntioquia) : 'cid:logo_antioquia';

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

    /**
     * @param array<int,array{title:string,rows:array}> $reportData
     */
    private function renderWeeklyReportPdf(string $fromDate, string $toDate, array $reportData): string
    {
        $html = $this->buildWeeklyReportHtml($fromDate, $toDate, $reportData, true);

        return PdfService::renderHtml(
            $html,
            'L',
            sprintf('Reporte semanal AoAT %s a %s', $fromDate, $toDate)
        );
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

    /**
     * @param array<string,mixed> $record
     */
    private function canExportSingleAoat(array $user, array $record): bool
    {
        if (Auth::canViewAllModuleRecords($user)) {
            return true;
        }

        $ownerId = (int) ($record['user_id'] ?? 0);
        $currentUserId = (int) ($user['id'] ?? 0);

        return $ownerId === $currentUserId && (string) ($record['state'] ?? '') === 'Aprobada';
    }

    /**
     * @param array<string,mixed> $record
     */
    private function exportSingleAoatPdf(array $record): Response
    {
        $html = $this->buildSingleAoatExportHtml($record, true);
        $pdfBinary = PdfService::renderHtml(
            $html,
            'P',
            'AoAT individual'
        );

        $filename = 'aoat_' . (int) ($record['id'] ?? 0) . '_' . date('Ymd_His') . '.pdf';

        return new Response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * @param array<string,mixed> $record
     */
    private function exportSingleAoatExcel(array $record): Response
    {
        $html = $this->buildSingleAoatExportHtml($record, false);
        $filename = 'aoat_' . (int) ($record['id'] ?? 0) . '_' . date('Ymd_His') . '.xls';

        return new Response($html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * @param array<string,mixed> $record
     */
    private function buildSingleAoatExportHtml(array $record, bool $forPdf): string
    {
        $payload = [];
        if (isset($record['payload']) && $record['payload'] !== null) {
            $decoded = json_decode((string) $record['payload'], true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        $logoHomo = dirname(__DIR__, 2) . '/public/assets/img/logoHomo.png';
        $logoAntioquia = dirname(__DIR__, 2) . '/public/assets/img/logoAntioquia.png';
        $logoHomoSrc = $forPdf ? PdfImageHelper::imageDataUri($logoHomo) : $this->buildExcelImageTag($logoHomo, 'HOMO');
        $logoAntioquiaSrc = $forPdf ? PdfImageHelper::imageDataUri($logoAntioquia) : $this->buildExcelImageTag($logoAntioquia, 'Gobernación de Antioquia');

        $professionalName = trim((string) (($record['professional_name'] ?? '') . ' ' . ($record['professional_last_name'] ?? '')));
        $roleLabel = ucwords(str_replace('_', ' ', (string) ($record['professional_role'] ?? '')));
        $activityDate = $this->formatExportDate((string) ($payload['activity_date'] ?? ''));
        $createdAt = $this->formatExportDateTime((string) ($record['created_at'] ?? ''));

        $summaryRows = [
            'ID AoAT' => (string) ($record['id'] ?? ''),
            'Fecha de registro' => $createdAt,
            'Fecha AoAT' => $activityDate,
            'Profesional' => $professionalName,
            'Rol profesional' => $roleLabel,
            'Subregión' => (string) ($record['subregion'] ?? ''),
            'Municipio' => (string) ($record['municipality'] ?? ''),
            'Estado AoAT' => (string) ($record['state'] ?? ''),
            'Motivo de devolución' => (string) ($record['audit_motive'] ?? ''),
            'Observación de devolución' => (string) ($record['audit_observation'] ?? ''),
        ];

        $questionRows = [];
        foreach ($payload as $key => $value) {
            $label = $this->aoatPayloadLabel((string) $key);
            if (is_array($value)) {
                $cleanValues = array_values(array_filter(array_map(static fn ($item): string => trim((string) $item), $value), static fn (string $item): bool => $item !== ''));
                if ($cleanValues === []) {
                    continue;
                }
                $questionRows[$label] = implode(', ', $cleanValues);
                continue;
            }

            $text = trim((string) $value);
            if ($text === '') {
                continue;
            }
            $questionRows[$label] = $text;
        }

        $renderLogo = static function (string $srcOrHtml, string $alt, bool $isPdf): string {
            if ($isPdf) {
                return $srcOrHtml !== ''
                    ? '<img src="' . htmlspecialchars($srcOrHtml, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '" style="height:54px;width:auto;">'
                    : '';
            }

            return $srcOrHtml;
        };

        $html = '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>AoAT individual</title>';
        $html .= '<style>
            body{font-family:DejaVu Sans,Arial,sans-serif;color:#284d46;font-size:12px;margin:0;padding:0;background:#f7faf8;}
            .page{padding:20px;}
            .sheet{background:#ffffff;border:1px solid #d9e8e3;border-radius:16px;padding:18px;}
            .header{border:1px solid #c7ddd6;border-radius:14px;background:linear-gradient(135deg,#e4f0ec 0%,#f4efe5 100%);padding:14px 16px;}
            .header-table{width:100%;border-collapse:collapse;}
            .header-table td{vertical-align:middle;}
            .title{margin:0;font-size:20px;font-weight:700;color:#4160a4;}
            .subtitle{margin:4px 0 0;font-size:12px;color:#35645b;}
            .summary-card{margin-top:16px;}
            .section-title{margin:18px 0 8px;font-size:14px;font-weight:700;color:#4160a4;}
            table{width:100%;border-collapse:collapse;}
            th,td{border:1px solid #d8e8f5;padding:8px 10px;vertical-align:top;text-align:left;}
            th{width:32%;background:#dfece7;color:#24564e;font-weight:700;}
            td{background:#ffffff;color:#2f4d48;}
            .answers th{background:#f4eee7;}
            .footer{margin-top:16px;font-size:10px;color:#64748b;text-align:center;}
        </style></head><body><div class="page"><div class="sheet">';
        $html .= '<div class="header"><table class="header-table"><tr>';
        $html .= '<td style="width:28%;">' . $renderLogo($logoAntioquiaSrc, 'Gobernación de Antioquia', $forPdf) . '</td>';
        $html .= '<td style="width:44%;text-align:center;"><p class="title">Registro individual de AoAT</p><p class="subtitle">Equipo de Promoción y Prevención · Acción en Territorio</p></td>';
        $html .= '<td style="width:28%;text-align:right;">' . $renderLogo($logoHomoSrc, 'HOMO', $forPdf) . '</td>';
        $html .= '</tr></table></div>';

        $html .= '<div class="summary-card"><p class="section-title">Resumen general</p><table>';
        foreach ($summaryRows as $label => $value) {
            $displayValue = trim((string) $value) !== '' ? (string) $value : 'No registrado';
            $html .= '<tr><th>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</th><td>' . nl2br(htmlspecialchars($displayValue, ENT_QUOTES, 'UTF-8')) . '</td></tr>';
        }
        $html .= '</table></div>';

        $html .= '<div class="answers"><p class="section-title">Respuestas del formulario</p><table>';
        if ($questionRows === []) {
            $html .= '<tr><td colspan="2">No se encontraron respuestas registradas para esta AoAT.</td></tr>';
        } else {
            foreach ($questionRows as $label => $value) {
                $html .= '<tr><th>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</th><td>' . nl2br(htmlspecialchars($value, ENT_QUOTES, 'UTF-8')) . '</td></tr>';
            }
        }
        $html .= '</table></div>';

        $html .= '<p class="footer">Documento generado automáticamente desde la plataforma Equipo de Promoción y Prevención.</p>';
        $html .= '</div></div></body></html>';

        return $html;
    }

    private function aoatPayloadLabel(string $key): string
    {
        $labels = [
            'proyecto' => 'Proyecto',
            'aoat_number' => 'Número de la AoAT o actividad',
            'activity_date' => 'Fecha de la actividad',
            'activity_type' => 'Actividad que realizó',
            'activity_with' => 'Con quién realizó la actividad',
            'subregion' => 'Subregión que visitó',
            'municipality' => 'Municipio visitado',
            'prev_suicidio' => 'Cualificación temas en prevención del suicidio',
            'prev_violencias' => 'Cualificación temas en prevención de violencias',
            'prev_adicciones' => 'Cualificación temas en prevención de adicciones',
            'salud_mental' => 'Cualificación temas de salud mental',
            'mesa_salud_mental' => 'Mesa Municipal de Salud Mental y Prevención de las Adicciones',
            'ppmsmypa' => 'Política Pública Municipal de Salud y Prevención de las Adicciones (PPMSMYPA)',
            'safer' => 'SAFER',
            'temas_hospital' => 'Temas dictados en el hospital',
            'actividad_social' => 'Actividades realizadas (Profesional social)',
            'otro_caso' => 'Otro caso identificado',
        ];

        return $labels[$key] ?? ucwords(str_replace('_', ' ', $key));
    }

    private function formatExportDate(string $date): string
    {
        $date = trim($date);
        if ($date === '') {
            return '';
        }

        try {
            return (new \DateTimeImmutable($date))->format('d/m/Y');
        } catch (\Exception) {
            return $date;
        }
    }

    private function formatExportDateTime(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        try {
            return (new \DateTimeImmutable($value))->format('d/m/Y H:i');
        } catch (\Exception) {
            return $value;
        }
    }

    private function buildExcelImageTag(string $path, string $alt): string
    {
        $src = PdfImageHelper::imageDataUri($path);
        if ($src === '') {
            return '<span style="font-size:12px;font-weight:700;color:#35645b;">' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '</span>';
        }

        return '<img src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '" style="height:54px;width:auto;">';
    }
}

