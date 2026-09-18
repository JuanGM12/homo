<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Database\Connection;
use App\Repositories\CronogramaRepository;
use App\Services\Auth;
use App\Services\PdfImageHelper;
use App\Services\PdfService;
use App\Services\QualificationCatalog;
use App\Support\UserMunicipalities;
use PDO;

final class CronogramaController
{
    private const ACCESS_ROLES = [
        'medico', 'psicologo', 'politologo', 'abogado',
        'profesional social', 'profesional_social',
        'especialista', 'coordinadora', 'coordinador', 'admin',
    ];

    private const FIELD_ROLES = [
        'medico', 'psicologo', 'politologo', 'abogado',
        'profesional social', 'profesional_social', 'especialista',
    ];

    private const ACTIVITY_TYPES = [
        'AT' => 'AT (Asistencia técnica)',
        'AS' => 'AS (Asesoría)',
        'Actividad' => 'Actividad',
    ];

    private CronogramaRepository $repository;

    public function __construct()
    {
        $this->repository = new CronogramaRepository();
    }

    public function index(Request $request): Response
    {
        $user = $this->requireUser();
        if ($user instanceof Response) {
            return $user;
        }
        if (!$this->userCanAccess($user)) {
            return Response::view('errors/403', ['pageTitle' => 'Acceso denegado'], 403);
        }

        $canViewAll = Auth::canViewAllModuleRecords($user);
        $role = $this->qualificationRoleForUser($user);
        $topicOptions = QualificationCatalog::topicOptionsForRole($role);
        if ($topicOptions === []) {
            $topicOptions = QualificationCatalog::allTopicOptions();
        }

        $professionals = [];
        if ($canViewAll) {
            $professionals = $this->loadProfessionals(Auth::dashboardProfessionalRoleScope($user));
        }

        return Response::view('cronograma/index', [
            'pageTitle' => 'Cronograma',
            'canViewAll' => $canViewAll,
            'isAdmin' => Auth::isAdmin($user),
            'activityTypes' => self::ACTIVITY_TYPES,
            'topicOptions' => $topicOptions,
            'hourOptions' => $this->hourOptions(),
            'professionals' => $professionals,
            'allowedMunicipalities' => UserMunicipalities::assignedTo((int) $user['id']),
            'currentUserId' => (int) $user['id'],
        ]);
    }

    public function actividades(Request $request): Response
    {
        $user = $this->requireUser();
        if ($user instanceof Response) {
            return $user;
        }
        if (!$this->userCanAccess($user)) {
            return Response::json(['error' => 'Sin permiso'], 403);
        }

        $mes = (string) $request->input('mes', date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
            $mes = date('Y-m');
        }
        $from = $mes . '-01';
        $to = $mes . '-' . str_pad((string) (int) date('t', strtotime($from)), 2, '0', STR_PAD_LEFT);

        $rows = $this->repository->listByRange($from, $to, $this->listFilters($request, $user));

        return Response::json(['actividades' => $this->presentRows($rows), 'mes' => $mes]);
    }

    public function dia(Request $request): Response
    {
        $user = $this->requireUser();
        if ($user instanceof Response) {
            return $user;
        }
        if (!$this->userCanAccess($user)) {
            return Response::json(['error' => 'Sin permiso'], 403);
        }

        $fecha = (string) $request->input('fecha', '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return Response::json(['actividades' => []]);
        }

        $rows = $this->repository->listByDay($fecha, $this->listFilters($request, $user));

        return Response::json(['actividades' => $this->presentRows($rows)]);
    }

    public function detalle(Request $request): Response
    {
        $user = $this->requireUser();
        if ($user instanceof Response) {
            return $user;
        }
        if (!$this->userCanAccess($user)) {
            return Response::json(['error' => 'Sin permiso'], 403);
        }

        $id = (int) $request->input('id', 0);
        $row = $this->repository->findById($id);
        if ($row === null) {
            return Response::json(['error' => 'No encontrada'], 404);
        }
        if (!$this->userCanSeeRow($user, $row)) {
            return Response::json(['error' => 'Sin permiso'], 403);
        }

        return Response::json($this->presentRow($row));
    }

    public function guardar(Request $request): Response
    {
        $user = $this->requireUser();
        if ($user instanceof Response) {
            return $user;
        }
        if (!$this->userCanAccess($user)) {
            return Response::json(['ok' => false, 'mensaje' => 'Sin permiso'], 403);
        }

        $id = (int) $request->input('id', 0);
        $payload = $this->collectPayload($request, $user);
        $errors = $this->validatePayload($payload, (int) $user['id']);
        if ($errors !== []) {
            return Response::json(['ok' => false, 'mensaje' => implode(' ', $errors)]);
        }

        if ($id > 0) {
            $existing = $this->repository->findById($id);
            if ($existing === null || !$this->userCanEditRow($user, $existing)) {
                return Response::json(['ok' => false, 'mensaje' => 'No puedes editar esta actividad']);
            }
            $this->repository->update($id, $payload);

            return Response::json(['ok' => true, 'id' => $id]);
        }

        $newId = $this->repository->create($payload);

        return Response::json(['ok' => $newId > 0, 'id' => $newId, 'mensaje' => $newId > 0 ? null : 'No se pudo guardar']);
    }

    public function eliminar(Request $request): Response
    {
        $user = $this->requireUser();
        if ($user instanceof Response) {
            return $user;
        }
        if (!$this->userCanAccess($user)) {
            return Response::json(['ok' => false, 'mensaje' => 'Sin permiso'], 403);
        }

        $id = (int) $request->input('id', 0);
        $row = $this->repository->findById($id);
        if ($row === null) {
            return Response::json(['ok' => false, 'mensaje' => 'No encontrada']);
        }
        if (!$this->userCanEditRow($user, $row) && !Auth::isAdmin($user)) {
            return Response::json(['ok' => false, 'mensaje' => 'Sin permiso']);
        }

        $this->repository->deleteById($id);

        return Response::json(['ok' => true]);
    }

    public function exportCsv(Request $request): Response
    {
        $user = $this->requireUser();
        if ($user instanceof Response) {
            return $user;
        }
        if (!$this->userCanAccess($user)) {
            return Response::view('errors/403', ['pageTitle' => 'Acceso denegado'], 403);
        }

        [$from, $to, $tituloRango] = $this->resolveExportRange($request);
        $rows = $this->repository->listByRange($from, $to, $this->listFilters($request, $user));

        $lines = [];
        $lines[] = implode(';', ['Fecha', 'Hora inicio', 'Hora fin', 'Tipo', 'Tema', 'Población atendida', 'Subregión', 'Municipio', 'Profesional', 'Rol']);
        foreach ($rows as $row) {
            $presented = $this->presentRow($row);
            $lines[] = implode(';', array_map(static function (string $v): string {
                return '"' . str_replace('"', '""', $v) . '"';
            }, [
                (string) $presented['fecha'],
                (string) $presented['hora_inicio'],
                (string) $presented['hora_fin'],
                (string) $presented['activity_type_label'],
                (string) $presented['tema_label'],
                (string) $presented['poblacion_atendida'],
                (string) $presented['subregion'],
                (string) $presented['municipality'],
                (string) $presented['professional_name'],
                (string) $presented['professional_role'],
            ]));
        }

        $csv = "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n";
        $filename = 'cronograma_' . $from . '_' . $to . '.csv';

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function exportPdf(Request $request): Response
    {
        $user = $this->requireUser();
        if ($user instanceof Response) {
            return $user;
        }
        if (!$this->userCanAccess($user)) {
            return Response::view('errors/403', ['pageTitle' => 'Acceso denegado'], 403);
        }

        [$from, $to, $tituloRango] = $this->resolveExportRange($request);
        $rows = $this->repository->listByRange($from, $to, $this->listFilters($request, $user));
        $porFecha = [];
        foreach ($rows as $row) {
            $presented = $this->presentRow($row);
            $fecha = (string) $presented['fecha'];
            if ($fecha === '') {
                continue;
            }
            $porFecha[$fecha][] = $presented;
        }
        ksort($porFecha);

        $base = dirname(__DIR__, 2) . '/public/assets/img';
        ob_start();
        $actividadesPorFecha = $porFecha;
        $tituloRangoLocal = $tituloRango;
        $logoAntioquia = PdfImageHelper::imageDataUri($base . '/logoAntioquia.png');
        $logoHomo = PdfImageHelper::imageDataUri($base . '/logoHomo.png');
        require dirname(__DIR__) . '/Views/cronograma/export_pdf.php';
        $html = (string) ob_get_clean();

        $pdfBinary = PdfService::renderHtml($html, 'P', 'Cronograma');
        $filename = 'cronograma_' . $from . '_' . $to . '.pdf';

        return new Response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * @return array<string, mixed>|Response
     */
    private function requireUser(): array|Response
    {
        $user = Auth::user();
        if (!$user) {
            return Response::redirect('/login');
        }

        return $user;
    }

    private function userCanAccess(array $user): bool
    {
        return (bool) array_intersect($user['roles'] ?? [], self::ACCESS_ROLES);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function userCanSeeRow(array $user, array $row): bool
    {
        if (Auth::canViewAllModuleRecords($user)) {
            $scope = Auth::dashboardProfessionalRoleScope($user);
            if ($scope === null) {
                return true;
            }
            if ($scope === []) {
                return false;
            }

            return in_array((string) ($row['professional_role'] ?? ''), $scope, true);
        }

        return (int) ($row['user_id'] ?? 0) === (int) $user['id'];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function userCanEditRow(array $user, array $row): bool
    {
        if (Auth::isAdmin($user)) {
            return true;
        }

        return (int) ($row['user_id'] ?? 0) === (int) $user['id'];
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function listFilters(Request $request, array $user): array
    {
        $filters = [];
        if (!Auth::canViewAllModuleRecords($user)) {
            $filters['owner_id'] = (int) $user['id'];

            return $filters;
        }

        $scope = Auth::dashboardProfessionalRoleScope($user);
        if (is_array($scope)) {
            if ($scope === []) {
                $filters['owner_id'] = -1;
            } else {
                $filters['roles'] = $scope;
            }
        }

        $municipality = trim((string) $request->input('municipality', ''));
        if ($municipality !== '') {
            $filters['municipality'] = $municipality;
        }
        $subregion = trim((string) $request->input('subregion', ''));
        if ($subregion !== '') {
            $filters['subregion'] = $subregion;
        }
        $role = trim((string) $request->input('role', ''));
        if ($role !== '') {
            $filters['role'] = QualificationCatalog::normalizeRole($role);
        }
        $userId = (int) $request->input('usuario_id', 0);
        if ($userId > 0) {
            $filters['user_id'] = $userId;
        }

        return $filters;
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function collectPayload(Request $request, array $user): array
    {
        $tema = trim((string) $request->input('tema', ''));
        $temaOtro = trim((string) $request->input('tema_otro', ''));

        return [
            'user_id' => (int) $user['id'],
            'professional_name' => (string) ($user['name'] ?? ''),
            'professional_email' => (string) ($user['email'] ?? ''),
            'professional_role' => $this->qualificationRoleForUser($user),
            'subregion' => trim((string) $request->input('subregion', '')),
            'municipality' => trim((string) $request->input('municipality', '')),
            'activity_date' => trim((string) $request->input('fecha', '')),
            'hora_inicio' => $this->normalizeTime((string) $request->input('hora_inicio', '')),
            'hora_fin' => $this->normalizeTime((string) $request->input('hora_fin', '')),
            'activity_type' => trim((string) $request->input('activity_type', '')),
            'tema' => $tema,
            'tema_otro' => $tema === 'Otro' ? $temaOtro : '',
            'poblacion_atendida' => trim((string) $request->input('poblacion_atendida', '')),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return list<string>
     */
    private function validatePayload(array $payload, int $userId): array
    {
        $errors = [];
        if ($payload['activity_date'] === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $payload['activity_date'])) {
            $errors[] = 'La fecha es obligatoria.';
        }
        if ($payload['subregion'] === '') {
            $errors[] = 'Debes seleccionar la subregión.';
        }
        if ($payload['municipality'] === '') {
            $errors[] = 'Debes seleccionar el municipio.';
        }
        if ($payload['subregion'] !== '' && $payload['municipality'] !== ''
            && !UserMunicipalities::canUse($userId, $payload['subregion'], $payload['municipality'])) {
            $errors[] = 'Solo puedes registrar actividades en los municipios que tienes asignados.';
        }
        if (!isset(self::ACTIVITY_TYPES[$payload['activity_type']])) {
            $errors[] = 'Debes seleccionar el tipo de actividad (AT, AS o Actividad).';
        }
        if ($payload['hora_inicio'] === '' || $payload['hora_fin'] === '') {
            $errors[] = 'Debes indicar hora de inicio y de fin.';
        } else {
            $start = $this->timeToMinutes($payload['hora_inicio']);
            $end = $this->timeToMinutes($payload['hora_fin']);
            if ($start === null || $end === null || ($end - $start) < 120) {
                $errors[] = 'El rango de hora debe ser de mínimo 2 horas.';
            }
        }
        if ($payload['tema'] === '') {
            $errors[] = 'Debes seleccionar el tema.';
        }
        if ($payload['tema'] === 'Otro' && $payload['tema_otro'] === '') {
            $errors[] = 'Describe el tema en el campo Otro.';
        }
        if ($payload['poblacion_atendida'] === '') {
            $errors[] = 'Indica la población atendida.';
        }

        $allowedTemas = array_column($this->topicOptionsForUserId($userId, $payload['professional_role']), 'value');
        $allowedTemas[] = 'Otro';
        if ($payload['tema'] !== '' && !in_array($payload['tema'], $allowedTemas, true)) {
            $errors[] = 'El tema seleccionado no es válido para tu rol.';
        }

        return $errors;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function topicOptionsForUserId(int $userId, string $role): array
    {
        $options = QualificationCatalog::topicOptionsForRole($role);
        if ($options === []) {
            return QualificationCatalog::allTopicOptions();
        }

        return $options;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function presentRows(array $rows): array
    {
        return array_map(fn (array $row): array => $this->presentRow($row), $rows);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function presentRow(array $row): array
    {
        $inicio = $this->normalizeTime((string) ($row['hora_inicio'] ?? ''));
        $fin = $this->normalizeTime((string) ($row['hora_fin'] ?? ''));
        $tema = (string) ($row['tema'] ?? '');
        $temaOtro = trim((string) ($row['tema_otro'] ?? ''));
        $temaLabel = $tema === 'Otro' && $temaOtro !== '' ? 'Otro: ' . $temaOtro : $tema;
        $type = (string) ($row['activity_type'] ?? '');

        return [
            'id' => (int) ($row['id'] ?? 0),
            'user_id' => (int) ($row['user_id'] ?? 0),
            'fecha' => (string) ($row['activity_date'] ?? ''),
            'hora_inicio' => $inicio,
            'hora_fin' => $fin,
            'hora' => $inicio !== '' && $fin !== '' ? $inicio . ' – ' . $fin : $inicio,
            'activity_type' => $type,
            'activity_type_label' => self::ACTIVITY_TYPES[$type] ?? $type,
            'tema' => $tema,
            'tema_otro' => $temaOtro,
            'tema_label' => $temaLabel,
            'titulo' => trim($type . ' · ' . $temaLabel, ' ·'),
            'poblacion_atendida' => (string) ($row['poblacion_atendida'] ?? ''),
            'subregion' => (string) ($row['subregion'] ?? ''),
            'municipality' => (string) ($row['municipality'] ?? ''),
            'municipio_nombre' => (string) ($row['municipality'] ?? ''),
            'professional_name' => (string) ($row['professional_name'] ?? ''),
            'usuario_nombre' => (string) ($row['professional_name'] ?? ''),
            'professional_role' => (string) ($row['professional_role'] ?? ''),
            'usuario_rol' => (string) ($row['professional_role'] ?? ''),
        ];
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function resolveExportRange(Request $request): array
    {
        $desde = (string) $request->input('fecha_desde', '');
        $hasta = (string) $request->input('fecha_hasta', '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
            if ($desde > $hasta) {
                $hasta = $desde;
            }

            return [$desde, $hasta, $this->formatDateShort($desde) . ' — ' . $this->formatDateShort($hasta)];
        }

        $mes = (string) $request->input('mes', date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
            $mes = date('Y-m');
        }
        $from = $mes . '-01';
        $to = $mes . '-' . str_pad((string) (int) date('t', strtotime($from)), 2, '0', STR_PAD_LEFT);
        $nombreMes = str_replace(
            ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
            ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'],
            date('F Y', strtotime($from))
        );

        return [$from, $to, $nombreMes];
    }

    private function formatDateShort(string $fecha): string
    {
        $d = strtotime($fecha);
        $dias = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];
        $meses = ['', 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

        return $dias[(int) date('w', $d)] . ' ' . (int) date('j', $d) . ' de ' . $meses[(int) date('n', $d)] . ' de ' . date('Y', $d);
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
     * @param list<string>|null $roleScope
     * @return array<int, array<string, mixed>>
     */
    private function loadProfessionals(?array $roleScope): array
    {
        $pdo = Connection::getPdo();
        $fieldPlaceholders = implode(', ', array_fill(0, count(self::FIELD_ROLES), '?'));
        $sql = "SELECT DISTINCT u.id, u.name,
                       GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ',') AS roles_list
                FROM users u
                INNER JOIN user_roles ur ON ur.user_id = u.id
                INNER JOIN roles r ON r.id = ur.role_id
                WHERE u.active = 1 AND r.name IN ({$fieldPlaceholders})
                GROUP BY u.id, u.name
                ORDER BY u.name ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(self::FIELD_ROLES);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $out = [];
        foreach ($rows as $row) {
            $roles = array_map('trim', explode(',', (string) ($row['roles_list'] ?? '')));
            $role = '';
            foreach ($roles as $candidate) {
                $normalized = QualificationCatalog::normalizeRole($candidate);
                if (QualificationCatalog::roleConfig($normalized) !== null) {
                    $role = $normalized;
                    break;
                }
            }
            if ($roleScope !== null && ($roleScope === [] || !in_array($role, $roleScope, true))) {
                continue;
            }
            $out[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'role' => $role,
            ];
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function hourOptions(): array
    {
        $out = [];
        for ($h = 6; $h <= 20; $h++) {
            foreach ([0, 30] as $m) {
                if ($h === 20 && $m === 30) {
                    continue;
                }
                $out[] = sprintf('%02d:%02d', $h, $m);
            }
        }

        return $out;
    }

    private function normalizeTime(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^(\d{1,2}):(\d{2})/', $value, $m) !== 1) {
            return '';
        }

        return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
    }

    private function timeToMinutes(string $value): ?int
    {
        $value = $this->normalizeTime($value);
        if ($value === '') {
            return null;
        }
        [$h, $m] = array_map('intval', explode(':', $value));

        return ($h * 60) + $m;
    }
}
