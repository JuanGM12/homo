<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\AsistenciaRepository;
use App\Repositories\UserRepository;
use App\Services\Auth;
use App\Services\Flash;
use App\Services\PdfImageHelper;
use App\Services\PdfService;
use App\Support\MunicipalityListRequest;

final class AsistenciaController
{
    private const INDEX_PAGE_SIZE = 20;
    private const MIN_ALLOWED_DATE = '2026-01-01';

    /** Estados válidos del listado (asistencia_actividades.status). */
    private const ASISTENCIA_STATUSES = ['Pendiente', 'Activo', 'Cerrado'];

    private AsistenciaRepository $repo;
    private UserRepository $userRepo;

    public function __construct()
    {
        $this->repo = new AsistenciaRepository();
        $this->userRepo = new UserRepository();
    }

    /** Tipos de listado / Actividad (select2 múltiple) */
    /**
     * Tipos de listado / actividad por rol profesional.
     *
     * @return array<string, array<int, string>>
     */
    private static function getTiposActividadCatalog(): array
    {
        return [
            'medico' => [
                'Abordaje del manejo de alcohol en el primer nivel de atención - Alcohol y embarazo.',
                'Abordaje del manejo de tabaco en el primer nivel.',
                'Adicciones en la baja complejidad',
                'Conducta suicida',
                'Desmonte de benzodiacepinas',
                'Desmonte de opioides',
                'Epilepsia',
                'Intoxicaciones por medicamentos de control',
                'Manejo del dolor',
                'Paciente agitado',
                'Pre Test',
                'Post Test',
                'Resolución 347 de 2026',
                'Trastorno Afectivo Bipolar',
                'Trastorno de Déficit de Atención e Hiperactividad',
                'Trastorno Depresivo',
                'Trastorno Psicótico',
                'Trastornos de Ansiedad',
                'Trastornos del Sueño',
            ],
            'psicologo' => [
                'Adicciones - Módulo 1: Modelos explicativos (biopsicosocial, aprendizaje y condicionamiento), neurobiología de las adicciones, determinantes sociales, factores de riesgo y de protección, prevención basada en evidencia, influencia normativa.',
                'Adicciones - Módulo 2: Comprensión de las adicciones según tipo de sustancia, dependencias comportamentales (juego patológico, nomofobia, juegos electrónicos, oniomanía, adicción al trabajo, vigorexia), cigarrillos electrónicos, cannabis, patología dual.',
                'Adicciones - Módulo 3: Rutas de atención, tamizajes (ASSIST, AUDIT, CRAFFT, Fagerstróm), intervenciones (entrevista motivacional, intervención única, mindfulness), grupos de apoyo, reducción de riesgos y daños.',
                'Presentación del programa Salud para el Alma',
                'Salud Mental - Análisis de Caso y Recomendaciones Técnicas a Aplicar',
                'Salud Mental - Cuidado al cuidador',
                'Salud Mental - Cuidado del profesional - burnout',
                'Salud Mental - Dispositivos Comunitarios',
                'Salud Mental - Estigma',
                'Salud Mental - Estrategias de Salud Mental (Aventura Crecer, Comp Parent, VQSC, JPL, FQSC, SAFER)',
                'Salud Mental - Grupos de apoyo y ayuda mutua (violencias, SPA, suicidio): teoría y conformación',
                'Salud Mental - Normatividad en Salud Mental y Adicciones',
                'Salud Mental - Primeros auxilios psicológicos e intervención en crisis',
                'Salud Mental - Trastornos mentales prioritarios de interés en salud pública',
                'Suicidio - Módulo 1: Evolución histórica del suicidio, aproximación conceptual de la conducta suicida, teorías explicativas de primera generación, teorías explicativas de segunda generación, factores de riesgo (biológicos, psiquiátricos, psicológicos y sociales), factores de protección, señales de alarma, ruta de atención y articulación intersectorial, notificación y seguimiento, plan de seguridad.',
                'Suicidio - Módulo 2: Comunicación y suicidio como factor de riesgo y de protección, impacto del lenguaje y los mensajes, efecto Werther, efecto Papageno, principios de la comunicación responsable, recomendaciones de la OMS para medios y contextos comunitarios, pautas de lo que se debe y no se debe comunicar, aplicación del efecto Papageno en contextos comunitarios e institucionales, roles y responsabilidades de actores clave, poder de la narrativa y reducción del estigma, recursos y guías para la comunicación responsable.',
                'Suicidio - Módulo 3: Concepto y alcances de la posvención, posvención como estrategia de prevención y salud pública, impacto psicosocial del suicidio, duelo por suicidio y sus particularidades, duelo y tamizajes para suicidio (RQC, SRQ, Whooley, GAD-2, Zarit, Plutchick, PHQ-9, C-SSRS), estigma y silencios, principios orientadores de la posvención, acciones de posvención en el territorio, acompañamiento a familias e instituciones, comunicación posterior a una muerte por suicidio, identificación y seguimiento de personas en riesgo, articulación con servicios de salud mental, autocuidado del profesional psicosocial.',
                'Violencias - Módulo 1: Definición, marco normativo, epidemiología, tipología, característica.',
                'Violencias - Módulo 2: Violencias interpersonales, violencia familiar y de pareja, violencia comunitaria, violencia juvenil, bullying.',
                'Violencias - Módulo 3: Modelos de prevención de las violencias interpersonales (prevención universal, selectiva, indicada y de recurrencias), programas basados en la evidencia para la prevención de las violencias.',
            ],
            'abogado' => [
                'Actualización de la Mesa Municipal de Salud Mental y Prevención de las Adicciones',
                'Actualización de la Política pública Municipal de Salud y Prevención de las Adicciones',
                'Presentación inicial',
                'SAFER - Módulo 1: Socialización de la problemática pública del alcohol, generalidades.',
                'SAFER - Módulo 2: Socialización de la problemática pública del alcohol, generalidades.',
                'SAFER - Módulo 3: Legislación actual con énfasis en consumo de menores y mujeres.',
                'SAFER - Módulo 4: Legislación actual con énfasis en consumo de menores y mujeres.',
                'SAFER - Módulo 5: Socialización de la problemática pública del alcohol.',
            ],
            'trabajador_social' => [
                'Actividad de apoyo',
                'Espacio de articulación',
                'Formación (desarrollo de capacidades)',
                'Profesional social actividades',
            ],
        ];
    }

    /**
     * @return string[]
     */
    public static function getTiposActividadByRole(?string $role): array
    {
        $catalog = self::getTiposActividadCatalog();
        $normalizedRole = self::normalizeActividadRole($role);

        return $catalog[$normalizedRole] ?? [];
    }


    public function index(Request $request): Response
    {
        $user = Auth::user();
        if (!$user) {
            return Response::redirect('/login');
        }

        $activeTab   = $this->normalizeActividadTipo((string) $request->input('tab', 'aoat'));
        $sort        = trim((string) $request->input('sort', 'activity_date'));
        $dir         = strtolower(trim((string) $request->input('dir', 'desc')));
        $currentPage = max(1, (int) $request->input('page', 1));

        $statusFilter = trim((string) $request->input('status', ''));
        if (!in_array($statusFilter, self::ASISTENCIA_STATUSES, true)) {
            $statusFilter = '';
        }

        $municipalities = MunicipalityListRequest::parse($request);
        $filters = [
            'subregion'       => trim((string) $request->input('subregion', '')),
            'advisor_user_id' => $request->input('advisor_user_id') !== '' ? (int) $request->input('advisor_user_id') : null,
            'status'          => $statusFilter,
            'from_date'       => trim((string) $request->input('from_date', '')),
            'to_date'         => trim((string) $request->input('to_date', '')),
            'tipo'            => $activeTab,
            'municipalities'  => $municipalities,
        ];

        $advisors = $this->visibleAdvisorsForUser($user);
        if ($this->userCanViewAllAsistencia($user)) {
            // sin restricción adicional
        } elseif ($this->userIsEspecialista($user)) {
            $allowedAdvisorIds = array_map(static fn (array $advisor): int => (int) ($advisor['id'] ?? 0), $advisors);
            $requestedAdvisorId = (int) ($filters['advisor_user_id'] ?? 0);

            if ($requestedAdvisorId > 0) {
                if (!in_array($requestedAdvisorId, $allowedAdvisorIds, true)) {
                    $filters['advisor_user_id'] = null;
                    $filters['advisor_user_ids'] = [0];
                }
            } else {
                $filters['advisor_user_ids'] = $allowedAdvisorIds === [] ? [0] : $allowedAdvisorIds;
            }
        } else {
            $filters['advisor_user_id'] = (int) $user['id'];
        }

        $records = $this->repo->findWithFilters(array_filter($filters, static function (mixed $v): bool {
            if (is_array($v)) {
                return $v !== [];
            }

            return $v !== null && $v !== '';
        }));

        foreach ($records as &$row) {
            $row['asistentes_count'] = $this->repo->countAsistentesByActividad((int) $row['id']);
        }
        unset($row);

        $records    = $this->sortRecords($records, $sort, $dir);
        $pagination = $this->paginateRecords($records, $currentPage, self::INDEX_PAGE_SIZE);

        return Response::view('asistencia/index', [
            'pageTitle'  => 'Listados de Asistencia',
            'records'    => $pagination['items'],
            'pagination' => $pagination,
            'advisors'   => $advisors,
            'filters'    => $filters,
            'activeTab'  => $activeTab,
            'canFilterAdvisor' => count($advisors) > 1,
        ]);
    }

    public function create(Request $request): Response
    {
        $user = Auth::user();
        if (!$user) {
            return Response::redirect('/login');
        }

        if ($this->userCanViewAllAsistencia($user)) {
            $advisors = $this->visibleAdvisorsForUser($user);
            $selectedAdvisorId = count($advisors) === 1 ? (int) ($advisors[0]['id'] ?? 0) : 0;
            $canChooseAdvisor = count($advisors) > 1;
        } else {
            $advisors = [[
                'id' => (int) ($user['id'] ?? 0),
                'name' => (string) ($user['name'] ?? 'Mi usuario'),
            ]];
            $selectedAdvisorId = (int) ($user['id'] ?? 0);
            $canChooseAdvisor = false;
        }
        $activityOptionsByAdvisor = $this->buildActivityOptionsByAdvisor($advisors, $user);
        $tiposActividad = $selectedAdvisorId > 0
            ? ($activityOptionsByAdvisor[$selectedAdvisorId] ?? [])
            : [];

        return Response::view('asistencia/form', [
            'pageTitle' => 'Nueva Actividad de Asistencia',
            'advisors' => $advisors,
            'tiposActividad' => $tiposActividad,
            'activityOptionsByAdvisor' => $activityOptionsByAdvisor,
            'selectedAdvisorId' => $selectedAdvisorId,
            'canChooseAdvisor' => $canChooseAdvisor,
            'defaultTipo' => 'aoat',
        ]);
    }

    public function store(Request $request): Response
    {
        $user = Auth::user();
        if (!$user) {
            return Response::redirect('/login');
        }

        $errors = $this->validateActivityForm($request);
        if ($errors !== []) {
            Flash::set([
                'type' => 'error',
                'title' => 'Revisa el formulario',
                'message' => implode("\n", $errors),
            ]);
            return Response::redirect('/asistencia/nueva');
        }

        $advisorUserId = (int) $request->input('advisor_user_id');
        if (
            !$this->userCanViewAllAsistencia($user)
            && $advisorUserId !== (int) ($user['id'] ?? 0)
        ) {
            Flash::set([
                'type' => 'error',
                'title' => 'Asesor no permitido',
                'message' => 'Solo puedes crear actividades de asistencia para tu propio usuario.',
            ]);
            return Response::redirect('/asistencia/nueva');
        }

        if ($this->userCanViewAllAsistencia($user) && !$this->advisorIsVisibleForUser($user, $advisorUserId)) {
            Flash::set([
                'type' => 'error',
                'title' => 'Asesor no permitido',
                'message' => 'No puedes crear actividades para ese asesor.',
            ]);
            return Response::redirect('/asistencia/nueva');
        }
        $advisor = $this->userRepo->find($advisorUserId);
        $advisorName = $advisor ? (string) $advisor['name'] : 'Asesor';

        $tipo = $this->normalizeActividadTipo((string) $request->input('tipo', 'aoat'));
        $actividadTipos = $this->resolveActividadPayload($request, $tipo);
        if ($actividadTipos === []) {
            Flash::set([
                'type' => 'error',
                'title' => 'Actividad requerida',
                'message' => $tipo === 'actividad'
                    ? 'Debes escribir el nombre de la actividad.'
                    : 'Debes seleccionar al menos un tipo de listado AoAT.',
            ]);
            return Response::redirect('/asistencia/nueva');
        }

        if ($tipo === 'aoat') {
            $advisorActivityRole = $this->resolveActividadRoleFromUser($advisor ?? $user);
            $allowedActivityTypes = self::getTiposActividadByRole($advisorActivityRole);
            $invalidActivityTypes = array_values(array_diff($actividadTipos, $allowedActivityTypes));
            if ($invalidActivityTypes !== []) {
                Flash::set([
                    'type' => 'error',
                    'title' => 'Tipo de actividad no permitido',
                    'message' => 'Seleccionaste actividades que no corresponden al rol del asesor.',
                ]);
                return Response::redirect('/asistencia/nueva');
            }
        }

        $code = $this->repo->generateUniqueCode();

        $data = [
            'code' => $code,
            'subregion' => trim((string) $request->input('subregion', '')),
            'municipality' => trim((string) $request->input('municipality', '')),
            'lugar' => trim((string) $request->input('lugar', '')),
            'advisor_user_id' => $advisorUserId,
            'advisor_name' => $advisorName,
            'activity_date' => trim((string) $request->input('activity_date', '')),
            'tipo' => $tipo,
            'actividad_tipos' => json_encode($actividadTipos, JSON_UNESCAPED_UNICODE),
            'status' => 'Pendiente',
        ];

        try {
            $id = $this->repo->create($data);
        } catch (\PDOException $e) {
            Flash::set([
                'type' => 'error',
                'title' => 'No se pudo crear',
                'message' => 'Ocurrió un error al crear la actividad. Intenta de nuevo.',
            ]);
            return Response::redirect('/asistencia/nueva');
        }

        Flash::set([
            'type' => 'success',
            'title' => 'Actividad creada',
            'message' => 'La actividad se ha creado correctamente. Código: ' . $code,
        ]);
        return Response::redirect('/asistencia/ver?id=' . $id);
    }

    public function show(Request $request): Response
    {
        $user = Auth::user();
        if (!$user) {
            return Response::redirect('/login');
        }

        $id = (int) $request->input('id', 0);
        if ($id <= 0) {
            Flash::set(['type' => 'error', 'title' => 'No encontrado', 'message' => 'Actividad no especificada.']);
            return Response::redirect('/asistencia');
        }

        $actividad = $this->repo->findById($id);
        if (!$actividad) {
            Flash::set(['type' => 'error', 'title' => 'No encontrado', 'message' => 'La actividad no existe.']);
            return Response::redirect('/asistencia');
        }
        if (!$this->userCanAccessActividad($user, $actividad)) {
            Flash::set(['type' => 'error', 'title' => 'Acceso denegado', 'message' => 'No puedes consultar esta actividad de asistencia.']);
            return Response::redirect('/asistencia');
        }

        $asistentes = $this->repo->findAsistentesByActividad($id);
        $registrationUrl = $this->registrationUrl($actividad['code']);

        return Response::view('asistencia/show', [
            'pageTitle' => 'Detalle de actividad',
            'actividad' => $actividad,
            'asistentes' => $asistentes,
            'registrationUrl' => $registrationUrl,
            'canDeleteActividad' => Auth::isAdmin($user),
        ]);
    }

    public function exportCsv(Request $request): Response
    {
        $user = Auth::user();
        if (!$user) {
            return Response::redirect('/login');
        }

        $id = (int) $request->input('id', 0);
        $actividad = $id > 0 ? $this->repo->findById($id) : null;
        if (!$actividad) {
            Flash::set(['type' => 'error', 'title' => 'No encontrado', 'message' => 'Actividad no encontrada.']);
            return Response::redirect('/asistencia');
        }
        if (!$this->userCanAccessActividad($user, $actividad)) {
            Flash::set(['type' => 'error', 'title' => 'Acceso denegado', 'message' => 'No puedes exportar esta actividad de asistencia.']);
            return Response::redirect('/asistencia');
        }

        $asistentes = $this->repo->findAsistentesByActividad($id);
        $lines = [];

        $tiposRaw = $actividad['actividad_tipos'] ?? [];
        $tiposStr = is_array($tiposRaw) ? implode('; ', $tiposRaw) : (string) $tiposRaw;
        $tipoNorm = $this->normalizeActividadTipo((string) ($actividad['tipo'] ?? 'aoat'));
        $listadoTemasLabel = $tipoNorm === 'actividad' ? 'Actividad' : 'Listado AoAT';

        $metaRows = [
            ['Información de la actividad', ''],
            ['Código QR', (string) ($actividad['code'] ?? '')],
            ['Fecha', (string) ($actividad['activity_date'] ?? '')],
            ['Subregión', (string) ($actividad['subregion'] ?? '')],
            ['Municipio', (string) ($actividad['municipality'] ?? '')],
            ['Lugar', (string) ($actividad['lugar'] ?? '')],
            ['Tipo', $this->actividadTipoLabel((string) ($actividad['tipo'] ?? 'aoat'))],
            ['Asesor', (string) ($actividad['advisor_name'] ?? '')],
            [$listadoTemasLabel, $tiposStr],
            ['Estado', (string) ($actividad['status'] ?? '')],
            ['Enlace de registro', $this->registrationUrl((string) ($actividad['code'] ?? ''))],
        ];
        foreach ($metaRows as $pair) {
            $lines[] = $this->asistenciaCsvEscape((string) $pair[0]) . ';' . $this->asistenciaCsvEscape((string) $pair[1]);
        }
        $lines[] = '';

        $lines[] = implode(';', ['#', 'Documento', 'Nombres y Apellidos', 'Entidad', 'Cargo', 'Teléfono', 'Correo', 'Zona', 'Sexo', 'Edad', 'Etnia', 'Etnia (otro)', 'Grupo poblacional', 'Registro']);
        foreach ($asistentes as $i => $a) {
            $grupo = $a['grupo_poblacional'] ?? [];
            $grupoStr = is_array($grupo) ? implode(', ', $grupo) : (string) $grupo;
            $lines[] = implode(';', array_map(
                fn ($v): string => $this->asistenciaCsvEscape((string) $v),
                [
                    $i + 1,
                    $a['document_number'] ?? '',
                    $a['full_name'] ?? '',
                    $a['entity'] ?? '',
                    $a['cargo'] ?? '',
                    $a['phone'] ?? '',
                    $a['email'] ?? '',
                    $a['zone'] ?? '',
                    $a['sex'] ?? '',
                    $a['age'] !== null ? $a['age'] : '',
                    $a['etnia'] ?? '',
                    $a['etnia_otro'] ?? '',
                    $grupoStr,
                    $a['registered_at'] ?? '',
                ]
            ));
        }

        $csv = "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n";
        $filename = 'asistencia_' . $actividad['code'] . '_' . date('Ymd') . '.csv';

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function exportPdf(Request $request): Response
    {
        $user = Auth::user();
        if (!$user) {
            return Response::redirect('/login');
        }

        $id = (int) $request->input('id', 0);
        $actividad = $id > 0 ? $this->repo->findById($id) : null;
        if (!$actividad) {
            Flash::set(['type' => 'error', 'title' => 'No encontrado', 'message' => 'Actividad no encontrada.']);
            return Response::redirect('/asistencia');
        }
        if (!$this->userCanAccessActividad($user, $actividad)) {
            Flash::set(['type' => 'error', 'title' => 'Acceso denegado', 'message' => 'No puedes exportar esta actividad de asistencia.']);
            return Response::redirect('/asistencia');
        }

        $asistentes = $this->repo->findAsistentesByActividad($id);
        $html = $this->buildPdfHtml($actividad, $asistentes);
        $pdfBinary = PdfService::renderHtml($html, 'L', 'Listado de Asistencia ' . (string) ($actividad['code'] ?? ''), true);

        $safeCode = preg_replace('/[^a-zA-Z0-9_-]+/', '_', (string) ($actividad['code'] ?? 'export')) ?? 'export';
        $filename = 'asistencia_' . $safeCode . '_' . date('Ymd') . '.pdf';

        return new Response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function delete(Request $request): Response
    {
        $user = Auth::user();
        if (!$user) {
            return Response::redirect('/login');
        }

        $id = (int) $request->input('id', 0);
        if ($id <= 0) {
            Flash::set(['type' => 'error', 'title' => 'Error', 'message' => 'Actividad no especificada.']);
            return Response::redirect('/asistencia');
        }

        $actividad = $this->repo->findById($id);
        if (!$actividad) {
            Flash::set(['type' => 'error', 'title' => 'No encontrado', 'message' => 'La actividad no existe.']);
            return Response::redirect('/asistencia');
        }
        if (!Auth::isAdmin($user)) {
            Flash::set(['type' => 'error', 'title' => 'Acceso denegado', 'message' => 'Solo un administrador puede eliminar actividades de asistencia.']);
            return Response::redirect('/asistencia');
        }

        $this->repo->delete($id);
        Flash::set([
            'type' => 'success',
            'title' => 'Actividad eliminada',
            'message' => 'La actividad ha sido eliminada correctamente.',
        ]);
        return Response::redirect('/asistencia');
    }

    public function updateStatus(Request $request): Response
    {
        $user = Auth::user();
        if (!$user) {
            return Response::redirect('/login');
        }

        $id = (int) $request->input('id', 0);
        $status = trim((string) $request->input('status', ''));
        if ($id <= 0 || !in_array($status, self::ASISTENCIA_STATUSES, true)) {
            Flash::set(['type' => 'error', 'title' => 'Datos inválidos', 'message' => 'Estado no válido.']);
            return Response::redirect('/asistencia');
        }

        $actividad = $this->repo->findById($id);
        if (!$actividad) {
            Flash::set(['type' => 'error', 'title' => 'No encontrado', 'message' => 'La actividad no existe.']);
            return Response::redirect('/asistencia');
        }
        if (!$this->userCanAccessActividad($user, $actividad)) {
            Flash::set(['type' => 'error', 'title' => 'Acceso denegado', 'message' => 'No puedes modificar esta actividad de asistencia.']);
            return Response::redirect('/asistencia');
        }

        $this->repo->updateStatus($id, $status);
        Flash::set([
            'type' => 'success',
            'title' => 'Estado actualizado',
            'message' => 'El estado de la actividad se ha actualizado.',
        ]);
        return Response::redirect('/asistencia/ver?id=' . $id);
    }

    private function paginateRecords(array $records, int $page, int $perPage): array
    {
        $totalItems  = count($records);
        $totalPages  = max(1, (int) ceil($totalItems / $perPage));
        $currentPage = min(max(1, $page), $totalPages);
        $offset      = ($currentPage - 1) * $perPage;

        return [
            'items'        => array_slice($records, $offset, $perPage),
            'total_items'  => $totalItems,
            'per_page'     => $perPage,
            'current_page' => $currentPage,
            'total_pages'  => $totalPages,
            'from'         => $totalItems === 0 ? 0 : $offset + 1,
            'to'           => min($offset + $perPage, $totalItems),
        ];
    }

    private function userCanViewAllAsistencia(array $user): bool
    {
        $roles = array_map('strtolower', $user['roles'] ?? []);

        return in_array('admin', $roles, true)
            || in_array('coordinador', $roles, true)
            || in_array('coordinadora', $roles, true);
    }

    private function userIsEspecialista(array $user): bool
    {
        $roles = array_map('strtolower', $user['roles'] ?? []);

        return in_array('especialista', $roles, true);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function visibleAdvisorsForUser(array $user): array
    {
        $all = $this->userRepo->findNonAdminAdvisors();
        if ($this->userCanViewAllAsistencia($user)) {
            return $all;
        }
        if (!$this->userIsEspecialista($user)) {
            return array_values(array_filter($all, static fn (array $advisor): bool => (int) ($advisor['id'] ?? 0) === (int) ($user['id'] ?? 0)));
        }

        $allowedRole = $this->especialistaAdvisorRole($user);
        if ($allowedRole === null) {
            return [];
        }

        $visible = [];
        foreach ($all as $advisor) {
            $advisorId = (int) ($advisor['id'] ?? 0);
            if ($advisorId <= 0) {
                continue;
            }
            $advisorUser = $this->userRepo->find($advisorId);
            if ($advisorUser !== null && in_array($allowedRole, array_map('strtolower', $advisorUser['roles'] ?? []), true)) {
                $visible[] = $advisor;
            }
        }

        return $visible;
    }

    private function advisorIsVisibleForUser(array $user, int $advisorUserId): bool
    {
        if ($advisorUserId <= 0) {
            return false;
        }

        foreach ($this->visibleAdvisorsForUser($user) as $advisor) {
            if ((int) ($advisor['id'] ?? 0) === $advisorUserId) {
                return true;
            }
        }

        return false;
    }


    /**
     * @param array<int, array<string, mixed>> $advisors
     * @return array<int, array<int, string>>
     */
    private function buildActivityOptionsByAdvisor(array $advisors, array $currentUser): array
    {
        $optionsByAdvisor = [];

        foreach ($advisors as $advisor) {
            $advisorId = (int) ($advisor['id'] ?? 0);
            if ($advisorId <= 0) {
                continue;
            }

            $advisorUser = $advisorId === (int) ($currentUser['id'] ?? 0)
                ? $currentUser
                : $this->userRepo->find($advisorId);
            $activityRole = $this->resolveActividadRoleFromUser($advisorUser ?? []);
            $optionsByAdvisor[$advisorId] = self::getTiposActividadByRole($activityRole);
        }

        return $optionsByAdvisor;
    }

    private function resolveActividadRoleFromUser(array $user): ?string
    {
        $roles = array_map('strtolower', $user['roles'] ?? []);
        $primaryRole = strtolower(trim((string) ($user['role'] ?? '')));

        if (in_array('psicologo', $roles, true) || $primaryRole === 'psicologo') {
            return 'psicologo';
        }
        if (in_array('medico', $roles, true) || $primaryRole === 'medico') {
            return 'medico';
        }
        if (in_array('abogado', $roles, true) || $primaryRole === 'abogado') {
            return 'abogado';
        }
        if (
            in_array('profesional social', $roles, true)
            || in_array('profesional_social', $roles, true)
            || in_array('trabajador social', $roles, true)
            || $primaryRole === 'profesional social'
            || $primaryRole === 'profesional_social'
            || $primaryRole === 'trabajador social'
        ) {
            return 'trabajador_social';
        }

        return null;
    }

    private static function normalizeActividadRole(?string $role): string
    {
        $normalized = strtolower(trim((string) $role));
        if ($normalized === 'profesional social' || $normalized === 'profesional_social' || $normalized === 'trabajador social') {
            return 'trabajador_social';
        }

        return $normalized;
    }

    private function especialistaAdvisorRole(array $user): ?string
    {
        $roles = array_map('strtolower', $user['roles'] ?? []);
        $primaryRole = strtolower(trim((string) ($user['role'] ?? '')));

        if (in_array('psicologo', $roles, true) || $primaryRole === 'psicologo') {
            return 'psicologo';
        }
        if (in_array('medico', $roles, true) || $primaryRole === 'medico') {
            return 'medico';
        }
        if (in_array('abogado', $roles, true) || $primaryRole === 'abogado') {
            return 'abogado';
        }

        return null;
    }

    private function userCanAccessActividad(array $user, array $actividad): bool
    {
        if ($this->userCanViewAllAsistencia($user)) {
            return true;
        }

        $advisorUserId = (int) ($actividad['advisor_user_id'] ?? 0);
        if (!$this->userIsEspecialista($user)) {
            return $advisorUserId === (int) ($user['id'] ?? 0);
        }

        foreach ($this->visibleAdvisorsForUser($user) as $advisor) {
            if ((int) ($advisor['id'] ?? 0) === $advisorUserId) {
                return true;
            }
        }

        return false;
    }

    private function sortRecords(array $records, string $sort, string $dir): array
    {
        $allowed = ['activity_date', 'subregion', 'municipality', 'advisor_name', 'status', 'asistentes_count'];
        if (!in_array($sort, $allowed, true)) {
            $sort = 'activity_date';
        }
        $direction = $dir === 'asc' ? 'asc' : 'desc';

        usort($records, function (array $a, array $b) use ($sort, $direction): int {
            $av = strtolower(trim((string) ($a[$sort] ?? '')));
            $bv = strtolower(trim((string) ($b[$sort] ?? '')));
            if ($av === $bv) {
                return 0;
            }
            $cmp = $av <=> $bv;
            return $direction === 'asc' ? $cmp : -$cmp;
        });

        return $records;
    }

    private function validateForm(Request $request): array
    {
        $errors = [];
        if (trim((string) $request->input('subregion', '')) === '') {
            $errors[] = 'Debes seleccionar la subregión.';
        }
        if (trim((string) $request->input('municipality', '')) === '') {
            $errors[] = 'Debes seleccionar el municipio.';
        }
        if (trim((string) $request->input('lugar', '')) === '') {
            $errors[] = 'El campo Lugar es obligatorio.';
        }
        if ((int) $request->input('advisor_user_id', 0) <= 0) {
            $errors[] = 'Debes seleccionar el asesor.';
        }
        if (trim((string) $request->input('activity_date', '')) === '') {
            $errors[] = 'La fecha de la actividad es obligatoria.';
        } elseif (!$this->isAllowedPlatformDate((string) $request->input('activity_date', ''))) {
            $errors[] = 'La fecha de la actividad no puede ser anterior al 1 de enero de 2026.';
        }
        return $errors;
    }

    private function isAllowedPlatformDate(string $date): bool
    {
        $normalizedDate = trim($date);
        if ($normalizedDate === '') {
            return false;
        }

        return $normalizedDate >= self::MIN_ALLOWED_DATE;
    }

    private function validateActivityForm(Request $request): array
    {
        $errors = $this->validateForm($request);
        $tipo = $this->normalizeActividadTipo((string) $request->input('tipo', 'aoat'));

        if ($tipo === 'actividad') {
            if (trim((string) $request->input('actividad_libre', '')) === '') {
                $errors[] = 'Debes escribir el nombre de la actividad.';
            }
        } elseif ($this->resolveActividadPayload($request, 'aoat') === []) {
            $errors[] = 'Debes seleccionar al menos un tipo de listado AoAT.';
        }

        return $errors;
    }

    private function normalizeActividadTipo(string $tipo): string
    {
        return strtolower(trim($tipo)) === 'actividad' ? 'actividad' : 'aoat';
    }

    /**
     * @return string[]
     */
    private function resolveActividadPayload(Request $request, string $tipo): array
    {
        if ($tipo === 'actividad') {
            $actividadLibre = trim((string) $request->input('actividad_libre', ''));

            return $actividadLibre !== '' ? [$actividadLibre] : [];
        }

        $actividadTipos = $request->input('actividad_tipos');
        if (!is_array($actividadTipos)) {
            return [];
        }

        return array_values(array_filter(array_map(static fn ($item): string => trim((string) $item), $actividadTipos)));
    }

    private function actividadTipoLabel(string $tipo): string
    {
        return $this->normalizeActividadTipo($tipo) === 'actividad' ? 'Actividades' : 'AoAT';
    }

    /**
     * Formulario público de registro de asistencia (enlace automático por código).
     */
    public function registrarForm(Request $request): Response
    {
        $code = trim((string) $request->input('code', ''));
        if ($code === '') {
            return Response::view('errors/404', ['pageTitle' => 'No encontrado'], 404);
        }
        $actividad = $this->repo->findByCode($code);
        if (!$actividad) {
            return Response::view('errors/404', ['pageTitle' => 'Actividad no encontrada'], 404);
        }
        $tipos = $actividad['actividad_tipos'] ?? [];
        $tituloListado = is_array($tipos) && count($tipos) > 0 ? $tipos[0] : 'Listado de asistencia';
        $tituloListado = $this->actividadTipoLabel((string) ($actividad['tipo'] ?? 'aoat')) . ': ' . $tituloListado;

        return Response::view('asistencia/registrar', [
            'pageTitle' => 'Registro de Asistencia',
            'actividad' => $actividad,
            'tituloListado' => $tituloListado,
        ]);
    }

    /**
     * Guardar registro de asistencia (POST desde formulario público).
     */
    public function registrarStore(Request $request): Response
    {
        $code = trim((string) $request->input('code', ''));
        if ($code === '') {
            return Response::view('errors/404', ['pageTitle' => 'No encontrado'], 404);
        }
        $actividad = $this->repo->findByCode($code);
        if (!$actividad) {
            return Response::view('errors/404', ['pageTitle' => 'Actividad no encontrada'], 404);
        }

        if (($actividad['status'] ?? '') === 'Cerrado') {
            Flash::set([
                'type' => 'warning',
                'title' => 'Listado cerrado',
                'message' => 'Este listado de asistencia está cerrado y no admite nuevos registros.',
            ]);

            return Response::redirect('/asistencia/registrar?code=' . rawurlencode($code));
        }

        $errors = $this->validateRegistrarForm($request);
        if ($errors !== []) {
            Flash::set([
                'type' => 'error',
                'title' => 'Revisa el formulario',
                'message' => implode("\n", $errors),
            ]);
            return Response::redirect('/asistencia/registrar?code=' . rawurlencode($code));
        }

        $documentNumber = trim((string) $request->input('document_number', ''));
        $existing = $this->repo->findAsistenteByActividadAndDocument(
            (int) $actividad['id'],
            $documentNumber
        );
        if ($existing) {
            Flash::set([
                'type' => 'warning',
                'title' => 'Ya registrado',
                'message' => 'Este documento ya está registrado en esta actividad.',
            ]);
            return Response::redirect('/asistencia/registrar?code=' . rawurlencode($code));
        }

        $grupo = $request->input('grupo_poblacional');
        $grupoArray = is_array($grupo) ? array_values(array_filter(array_map('trim', $grupo))) : [];

        $data = [
            'actividad_id' => (int) $actividad['id'],
            'document_number' => $documentNumber,
            'full_name' => trim((string) $request->input('full_name', '')),
            'entity' => trim((string) $request->input('entity', '')) ?: null,
            'cargo' => trim((string) $request->input('cargo', '')) ?: null,
            'phone' => trim((string) $request->input('phone', '')) ?: null,
            'email' => trim((string) $request->input('email', '')) ?: null,
            'zone' => trim((string) $request->input('zone', '')) ?: null,
            'sex' => trim((string) $request->input('sex', '')) ?: null,
            'age' => $request->input('age') !== '' ? (int) $request->input('age') : null,
            'etnia' => trim((string) $request->input('etnia', '')) ?: null,
            'etnia_otro' => trim((string) $request->input('etnia_otro', '')) ?: null,
            'grupo_poblacional' => $grupoArray,
        ];

        try {
            $this->repo->createAsistente($data);
            $this->repo->promoteToActivoIfPending((int) $actividad['id']);
        } catch (\PDOException $e) {
            Flash::set([
                'type' => 'error',
                'title' => 'Error',
                'message' => 'No se pudo registrar la asistencia. Intenta de nuevo.',
            ]);
            return Response::redirect('/asistencia/registrar?code=' . rawurlencode($code));
        }

        Flash::set([
            'type' => 'success',
            'title' => 'Asistencia registrada',
            'message' => 'Tu asistencia ha sido registrada correctamente.',
        ]);
        return Response::redirect('/asistencia/registrar?code=' . rawurlencode($code));
    }

    /**
     * API para autocompletar por documento (sin auth).
     */
    public function buscarPorDocumento(Request $request): Response
    {
        $doc = trim((string) $request->input('documento', ''));
        if ($doc === '') {
            return Response::json(['found' => false], 200);
        }
        $asistente = $this->repo->findLastAsistenteByDocumento($doc);
        if (!$asistente) {
            return Response::json(['found' => false], 200);
        }
        $grupo = $asistente['grupo_poblacional'] ?? [];
        if (is_string($grupo)) {
            $grupo = json_decode($grupo, true);
            $grupo = is_array($grupo) ? $grupo : [];
        }
        return Response::json([
            'found' => true,
            'full_name' => (string) ($asistente['full_name'] ?? ''),
            'entity' => (string) ($asistente['entity'] ?? ''),
            'cargo' => (string) ($asistente['cargo'] ?? ''),
            'phone' => (string) ($asistente['phone'] ?? ''),
            'email' => (string) ($asistente['email'] ?? ''),
            'zone' => (string) ($asistente['zone'] ?? ''),
            'sex' => (string) ($asistente['sex'] ?? ''),
            'age' => $asistente['age'] !== null ? (int) $asistente['age'] : null,
            'etnia' => (string) ($asistente['etnia'] ?? ''),
            'etnia_otro' => (string) ($asistente['etnia_otro'] ?? ''),
            'grupo_poblacional' => $grupo,
        ], 200);
    }

    private function validateRegistrarForm(Request $request): array
    {
        $errors = [];
        if (trim((string) $request->input('document_number', '')) === '') {
            $errors[] = 'El documento de identidad es obligatorio.';
        }
        if (trim((string) $request->input('full_name', '')) === '') {
            $errors[] = 'Nombres y apellidos son obligatorios.';
        }
        return $errors;
    }

    private function registrationUrl(string $code): string
    {
        $base = ($_SERVER['REQUEST_SCHEME'] ?? 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $path = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
        return $base . $path . '/asistencia/registrar?code=' . rawurlencode($code);
    }

    private function asistenciaCsvEscape(string $value): string
    {
        return '"' . str_replace('"', '""', $value) . '"';
    }

    private function buildPdfHtml(array $actividad, array $asistentes): string
    {
        $esc = static function (string $s): string {
            return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        };

        $base = dirname(__DIR__, 2) . '/public/assets/img';
        $logoAntSrc = PdfImageHelper::imageDataUri($base . '/logoAntioquia.png');
        $logoHomoSrc = PdfImageHelper::imageDataUri($base . '/logoHomo.png');
        $logoAntHtml = $logoAntSrc !== ''
            ? '<img src="' . $esc($logoAntSrc) . '" alt="Gobernación de Antioquia" style="height:48px;width:auto;">'
            : '';
        $logoHomoHtml = $logoHomoSrc !== ''
            ? '<img src="' . $esc($logoHomoSrc) . '" alt="HOMO" style="height:48px;width:auto;">'
            : '';

        $tipos = $actividad['actividad_tipos'] ?? [];
        $tiposStr = is_array($tipos) ? implode('; ', $tipos) : (string) $tipos;
        $tipoLabel = $this->actividadTipoLabel((string) ($actividad['tipo'] ?? 'aoat'));
        $rows = '';
        foreach ($asistentes as $i => $a) {
            $grupo = $a['grupo_poblacional'] ?? [];
            $grupoStr = is_array($grupo) ? implode(', ', $grupo) : (string) $grupo;
            $rows .= '<tr><td>' . ($i + 1) . '</td><td>' . htmlspecialchars($a['document_number'] ?? '') . '</td><td>' . htmlspecialchars($a['full_name'] ?? '') . '</td><td>' . htmlspecialchars($a['entity'] ?? '') . '</td><td>' . htmlspecialchars($a['cargo'] ?? '') . '</td><td>' . htmlspecialchars($a['phone'] ?? '') . '</td><td>' . htmlspecialchars($a['email'] ?? '') . '</td><td>' . htmlspecialchars($a['zone'] ?? '') . '</td><td>' . htmlspecialchars($a['sex'] ?? '') . '</td><td>' . ($a['age'] !== null ? (int) $a['age'] : '') . '</td><td>' . htmlspecialchars($a['etnia'] ?? '') . '</td><td>' . htmlspecialchars($grupoStr) . '</td><td>' . htmlspecialchars($a['registered_at'] ?? '') . '</td></tr>';
        }

        $header = '<table style="width:100%;border-collapse:collapse;margin-bottom:14px;"><tr>'
            . '<td style="width:28%;vertical-align:middle;">' . $logoAntHtml . '</td>'
            . '<td style="width:44%;text-align:center;vertical-align:middle;"><span style="font-size:12px;color:#0f5132;font-weight:600;">Acción en Territorio</span></td>'
            . '<td style="width:28%;text-align:right;vertical-align:middle;">' . $logoHomoHtml . '</td>'
            . '</tr></table>';

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Asistencia ' . htmlspecialchars($actividad['code']) . '</title><style>body{font-family:system-ui,sans-serif;margin:16px;}table{border-collapse:collapse;width:100%;font-size:12px}th,td{border:1px solid #ddd;padding:6px}th{background:#f5f5f5}</style></head><body>'
            . $header
            . '<h1 style="font-size:18px;margin:0 0 8px;">Listado de Asistencia - ' . $esc((string) ($actividad['code'] ?? '')) . '</h1>'
            . '<p><strong>Fecha:</strong> ' . $esc((string) ($actividad['activity_date'] ?? '')) . ' | <strong>Lugar:</strong> ' . $esc((string) ($actividad['lugar'] ?? '')) . ' | <strong>Asesor:</strong> ' . $esc((string) ($actividad['advisor_name'] ?? '')) . '</p>'
            . '<p><strong>Tipo:</strong> ' . $esc($tipoLabel) . ' | <strong>Actividad(es):</strong> ' . $esc($tiposStr) . '</p>'
            . '<table><thead><tr><th>#</th><th>Documento</th><th>Nombres</th><th>Entidad</th><th>Cargo</th><th>Teléfono</th><th>Correo</th><th>Zona</th><th>Sexo</th><th>Edad</th><th>Etnia</th><th>Grupo pobl.</th><th>Registro</th></tr></thead><tbody>' . $rows . '</tbody></table></body></html>';
    }
}
