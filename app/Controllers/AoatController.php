<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\AoatRepository;
use App\Services\Auth;
use App\Services\Flash;
use App\Services\Mailer;

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
        $records = $repo->findForUser((int) $user['id']);

        return Response::view('aoat/index', [
            'pageTitle' => 'AoAT - Mis registros',
            'records' => $records,
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

        if (
            $professionalName === '' ||
            $professionalLastName === '' ||
            $profession === '' ||
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
        if (!$this->userCanAccessAoat($user)) {
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
        if (!$this->userCanAccessAoat($user)) {
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

        $repo = new AoatRepository();

        // Cargamos por rol según los nombres que estamos usando en la base de datos
        $psychRows = $repo->findByRoleAndDateRange('psicologo', $fromDate, $toDate);
        $socialRows = $repo->findByRoleAndDateRange('profesional social', $fromDate, $toDate);
        $medicRows = $repo->findByRoleAndDateRange('medico', $fromDate, $toDate);
        $lawRows = $repo->findByRoleAndDateRange('abogado', $fromDate, $toDate);

        $htmlSections = [];
        $htmlSections[] = $this->buildReportTableHtml('Psicólogos', $psychRows);
        $htmlSections[] = $this->buildReportTableHtml('Profesionales Sociales', $socialRows);
        $htmlSections[] = $this->buildReportTableHtml('Médicos', $medicRows);
        $htmlSections[] = $this->buildReportTableHtml('Abogados', $lawRows);

        $htmlBody = sprintf(
            '<p>Reporte semanal de AoAT desde el %s hasta el %s.</p>%s<p>Este reporte fue generado automáticamente desde la plataforma Acción en Territorio.</p>',
            htmlspecialchars($fromDate, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($toDate, ENT_QUOTES, 'UTF-8'),
            implode('', $htmlSections)
        );

        $mailer = new Mailer();
        $subject = sprintf('Reporte semanal AoAT (%s a %s)', $fromDate, $toDate);
        $mailer->sendAoatWeeklyReport($htmlBody, $subject);

        Flash::set([
            'type' => 'success',
            'title' => 'Reporte enviado',
            'message' => 'El reporte semanal se ha generado y enviado al correo de la coordinación (si la configuración de correo es correcta).',
        ]);

        return Response::redirect('/aoat/reportes');
    }

    private function userCanAccessAoat(array $user): bool
    {
        $roles = $user['roles'] ?? [];
        $allowed = ['abogado', 'medico', 'psicologo', 'profesional social', 'profesional_social', 'admin'];
        return (bool) array_intersect($allowed, $roles);
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

    private function buildReportTableHtml(string $title, array $rows): string
    {
        if ($rows === []) {
            return sprintf('<h2 style="font-size:16px;margin-top:24px;">%s</h2><p>No se encontraron registros en el rango de fechas.</p>', htmlspecialchars($title, ENT_QUOTES, 'UTF-8'));
        }

        $header = sprintf('<h2 style="font-size:16px;margin-top:24px;">%s</h2>', htmlspecialchars($title, ENT_QUOTES, 'UTF-8'));

        $table = '<table border="1" cellpadding="4" cellspacing="0" style="border-collapse:collapse;font-size:12px;margin-bottom:16px;"><thead><tr>'
            . '<th>Fecha de la actividad</th>'
            . '<th>Profesional</th>'
            . '<th>Actividad que realizó</th>'
            . '<th>ROL</th>'
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
                    // dejamos el valor original
                }
            }

            $professionalFullName = trim((string) ($row['professional_name'] ?? '') . ' ' . (string) ($row['professional_last_name'] ?? ''));
            $activityType = (string) ($row['activity_type_json'] ?? '');

            $role = (string) ($row['professional_role'] ?? '');
            $roleLabel = ucwords(str_replace('_', ' ', $role));

            $actions = $this->buildActionsSummary($role, $payload);

            $subregion = (string) ($row['subregion'] ?? '');
            $municipality = (string) ($row['municipality'] ?? '');

            $table .= sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                htmlspecialchars($formattedDate, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($professionalFullName, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($activityType, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($actions, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($subregion, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($municipality, ENT_QUOTES, 'UTF-8')
            );
        }

        $table .= '</tbody></table>';

        return $header . $table;
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

