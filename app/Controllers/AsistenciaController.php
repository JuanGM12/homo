<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\AsistenciaRepository;
use App\Repositories\UserRepository;
use App\Services\AsistenciaInformeService;
use App\Services\Auth;
use App\Services\Flash;
use App\Services\PdfImageHelper;
use App\Services\PdfService;
use App\Support\MunicipalityListRequest;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\SheetView;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class AsistenciaController
{
    private const INDEX_PAGE_SIZE = 20;
    private const MIN_ALLOWED_DATE = '2026-01-01';

    /** Contrato único para listados FIPC (cambiar solo aquí y en vistas vía datos pasados desde el controlador). */
    public const FIPC_CONTRATO_NUMERO = '4600018640';

    private const FIPC_LISTADO_TITULO = 'LISTADO DE ASISTENCIA FIPC';

    private const FIPC_PROCESO_LINE =
        'PROCESO: FORTALECIMIENTO INSTITUCIONAL Y DE LA PARTICIPACIÓN CIUDADANA';

    /** @var string[] */
    private const REGISTRO_SEX_ALLOWED = ['Hombre', 'Mujer', 'Intersexual'];

    /** @var string[] */
    private const REGISTRO_GENERO_IDENTIDAD_ALLOWED = ['Cisgenero', 'Transgenero', 'Otra'];

    /** @var string[] */
    private const REGISTRO_ORIENTACION_ALLOWED =
        ['Lesbiana', 'Gay', 'Bisexual', 'Heterosexual', 'Otra'];

    /** Etiquetas exactas del formulario público para grupo poblacional (PDF marca X). */
    private const GRUPO_POBLACIONAL_PDF_LABELS = [
        'Con discapacidad',
        'Víctima del conflicto armado',
        'Se considera campesino',
        'Considera que la comunidad donde vive es campesina',
    ];

    /** Columnas tabla listado export (CSV/XLSX) — mismo orden en ambos. */
    /** @var list<string> */
    private const EXPORT_TABLE_HEADERS = [
        '#',
        'Documento',
        'Nombres y apellidos completos',
        'Entidad/Organización',
        'Cargo',
        'Teléfono',
        'Correo',
        'Municipio',
        'Zona',
        'Sexo',
        'Identidad de género',
        'Orientación sexual',
        'Edad',
        'Etnia',
        'Etnia (otro)',
        'Grupo poblacional',
        'Registro',
    ];

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
            'canConfigureInformeScope' => $this->userCanViewAllAsistencia($user),
            'informeRoles' => self::informeRoleOptions(),
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
            'canDeleteActividad' => $this->userCanAccessActividad($user, $actividad),
        ]);
    }

    /**
     * Metadatos FIPC + filas de tabla para export CSV/XLSX.
     *
     * @param array<string, mixed> $actividad
     * @param list<array<string, mixed>> $asistentes
     * @return array{fipcRows: list<array{0:string,1:string}>, tableRows: list<array<int, string>>, code: string}
     */
    private function buildAsistenciaExportPayload(array $actividad, array $asistentes): array
    {
        $tiposRaw = $actividad['actividad_tipos'] ?? [];
        $tiposStr = is_array($tiposRaw) ? implode('; ', $tiposRaw) : (string) $tiposRaw;
        $tipoNorm = $this->normalizeActividadTipo((string) ($actividad['tipo'] ?? 'aoat'));
        $listadoTemasLabel = $tipoNorm === 'actividad' ? 'Actividad' : 'Listado AoAT';
        $municipioActividad = $this->resolveActividadMunicipality($actividad);

        $tableRows = [];
        foreach ($asistentes as $i => $a) {
            $tableRows[] = $this->mapAsistenteToExportValues($a, $i, $municipioActividad);
        }

        return [
            'fipcRows' => $this->csvFipcHeaderRows($actividad, $listadoTemasLabel, $tiposStr),
            'tableRows' => $tableRows,
            'code' => (string) ($actividad['code'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $a
     * @return array<int, string>
     */
    private function mapAsistenteToExportValues(array $a, int $indexZeroBased, string $municipioActividad): array
    {
        $grupo = $a['grupo_poblacional'] ?? [];
        $grupoStr = is_array($grupo) ? implode(', ', $grupo) : (string) $grupo;
        $gi = $this->formatCsvGeneroOrientacionField(
            (string) ($a['genero_identidad'] ?? ''),
            (string) ($a['genero_identidad_otro'] ?? ''),
            'Otra'
        );
        $or = $this->formatCsvGeneroOrientacionField(
            (string) ($a['orientacion_sexual'] ?? ''),
            (string) ($a['orientacion_sexual_otro'] ?? ''),
            'Otra'
        );

        return [
            (string) ($indexZeroBased + 1),
            (string) ($a['document_number'] ?? ''),
            (string) ($a['full_name'] ?? ''),
            (string) ($a['entity'] ?? ''),
            (string) ($a['cargo'] ?? ''),
            (string) ($a['phone'] ?? ''),
            (string) ($a['email'] ?? ''),
            $municipioActividad,
            (string) ($a['zone'] ?? ''),
            (string) ($a['sex'] ?? ''),
            $gi,
            $or,
            $a['age'] !== null && $a['age'] !== '' ? (string) $a['age'] : '',
            (string) ($a['etnia'] ?? ''),
            (string) ($a['etnia_otro'] ?? ''),
            $grupoStr,
            (string) ($a['registered_at'] ?? ''),
        ];
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

        $payload = $this->buildAsistenciaExportPayload($actividad, $asistentes);
        foreach ($payload['fipcRows'] as $pair) {
            $lines[] = $this->asistenciaCsvEscape((string) $pair[0]) . ';' . $this->asistenciaCsvEscape((string) $pair[1]);
        }

        $lines[] = implode(';', array_map(
            fn (string $h): string => $this->asistenciaCsvEscape($h),
            self::EXPORT_TABLE_HEADERS
        ));

        foreach ($payload['tableRows'] as $rowVals) {
            $lines[] = implode(';', array_map(
                fn (string $v): string => $this->asistenciaCsvEscape($v),
                $rowVals
            ));
        }

        $csv = "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n";
        $filename = 'asistencia_' . $payload['code'] . '_' . date('Ymd') . '.csv';

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Excel maqueta FIPC (encabezado de 3 filas, variables verticales, marcas X).
     */
    public function exportExcel(Request $request): Response
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
        $tiposRaw = $actividad['actividad_tipos'] ?? [];
        $tiposStr = is_array($tiposRaw) ? implode('; ', $tiposRaw) : (string) $tiposRaw;
        $tipoNorm = $this->normalizeActividadTipo((string) ($actividad['tipo'] ?? 'aoat'));
        $listadoTemasLabel = $tipoNorm === 'actividad' ? 'Actividad' : 'Listado AoAT';
        $fipcRows = $this->csvFipcHeaderRows($actividad, $listadoTemasLabel, $tiposStr);
        $code = (string) ($actividad['code'] ?? '');

        $spreadsheet = $this->createAsistenciaExcelMaquette($actividad, $asistentes, $fipcRows, $code);

        $tmpXlsxPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'asist_' . bin2hex(random_bytes(12)) . '.xlsx';
        $content = '';
        try {
            $writer = new Xlsx($spreadsheet);
            $writer->save($tmpXlsxPath);
            $content = file_get_contents($tmpXlsxPath) ?: '';
        } catch (\Throwable) {
            $content = '';
        } finally {
            @unlink($tmpXlsxPath);
            $spreadsheet->disconnectWorksheets();
        }

        if ($content === '') {
            Flash::set(['type' => 'error', 'title' => 'Exportación', 'message' => 'No se pudo generar el archivo Excel.']);
            return Response::redirect('/asistencia');
        }

        $safeCode = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $code) ?: 'export';
        $filename = 'asistencia_' . $safeCode . '_' . date('Ymd') . '.xlsx';

        return new Response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * @param list<array{0:string,1:string}> $fipcRows
     * @param list<array<string, mixed>> $asistentes
     */
    private function createAsistenciaExcelMaquette(array $actividad, array $asistentes, array $fipcRows, string $code): Spreadsheet
    {
        /** Hasta AD: última pregunta de grupo fusiona AB:AD (3 columnas angostas como plantilla). */
        $lastDemo = 30;
        $Lc = Coordinate::stringFromColumnIndex($lastDemo);
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr('FIPC-' . preg_replace('/[\*\:\/\\\\\?\[\]]+/', '-', $code ?: 'lista'), 0, 31));

        $r = 1;
        foreach ($fipcRows as [$k, $v]) {
            if ($k === '' && $v === '') {
                $r++;
                continue;
            }
            $sheet->setCellValueExplicit('A' . $r, $k, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('B' . $r, $v, DataType::TYPE_STRING);
            $r++;
        }
        $r++;

        $rt = $r;
        $rc = $rt + 1;
        $rv = $rt + 2;

        $mun = $this->resolveActividadMunicipality($actividad);
        $fecha = (string) ($actividad['activity_date'] ?? '');
        $actLine = 'Código: ' . $code . "\n" . 'Fecha: ' . $fecha . "\n" . 'Municipio: ' . $mun;

        $sheet->setCellValue('A' . $rt, 'Actividad');
        $sheet->mergeCells('B' . $rt . ':G' . $rt);
        $sheet->setCellValue('B' . $rt, $actLine);
        $sheet->mergeCells('H' . $rt . ':' . $Lc . $rt);
        $sheet->setCellValue('H' . $rt, 'Señale con una X la condición que cumpla');

        $sheet->getStyle('A' . $rt)->applyFromArray([
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'font' => ['bold' => true, 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'BFBFBF']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        ]);
        $sheet->getStyle('B' . $rt . ':G' . $rt)->applyFromArray([
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'font' => ['size' => 9],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        ]);
        $sheet->getStyle('H' . $rt . ':' . $Lc . $rt)->applyFromArray([
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'font' => ['bold' => true, 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8E8E8']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        ]);

        $leftLabels = [
            'Nombres y apellidos completos',
            'No. Documento de identidad',
            'Entidad/Organización',
            'Cargo',
            'Teléfono',
            'Correo electrónico',
            'Municipio',
        ];
        for ($ci = 1; $ci <= 7; ++$ci) {
            $Ltr = Coordinate::stringFromColumnIndex($ci);
            $sheet->mergeCells($Ltr . $rc . ':' . $Ltr . $rv);
            $sheet->setCellValue($Ltr . $rc, $leftLabels[$ci - 1]);
            $sheet->getStyle($Ltr . $rc)->applyFromArray([
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
                'font' => ['bold' => true, 'size' => 10],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
            ]);
        }

        $sheet->mergeCells('H' . $rc . ':I' . $rc);
        $sheet->setCellValue('H' . $rc, 'Zona');
        $sheet->mergeCells('J' . $rc . ':L' . $rc);
        $sheet->setCellValue('J' . $rc, 'Sexo');
        $sheet->mergeCells('M' . $rc . ':O' . $rc);
        $sheet->setCellValue('M' . $rc, 'Identidad de Genero');
        $sheet->mergeCells('P' . $rc . ':T' . $rc);
        $sheet->setCellValue('P' . $rc, 'orientación Sexual');
        $sheet->mergeCells('U' . $rc . ':U' . $rv);
        $sheet->setCellValue('U' . $rc, "Edad\nNo. de años");
        $sheet->mergeCells('V' . $rc . ':X' . $rc);
        $sheet->setCellValue('V' . $rc, 'Etnia');
        $sheet->mergeCells('Y' . $rc . ':' . $Lc . $rc);
        $sheet->setCellValue('Y' . $rc, 'Grupo poblacional');

        foreach (['H' . $rc . ':I' . $rc, 'J' . $rc . ':L' . $rc, 'M' . $rc . ':O' . $rc, 'P' . $rc . ':T' . $rc, 'V' . $rc . ':X' . $rc, 'Y' . $rc . ':' . $Lc . $rc] as $catRange) {
            $sheet->getStyle($catRange)->applyFromArray([
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
                'font' => ['bold' => true, 'size' => 10],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
            ]);
        }
        $sheet->getStyle('U' . $rc . ':U' . $rv)->applyFromArray([
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'font' => ['bold' => true, 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EFEFEF']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        ]);

        $sheet->getStyle('H' . $rc . ':I' . $rv)->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        ]);
        $sheet->getStyle('J' . $rc . ':L' . $rv)->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DDEBD7']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        ]);
        $sheet->getStyle('M' . $rc . ':O' . $rv)->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DDD6E9']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        ]);
        $sheet->getStyle('P' . $rc . ':T' . $rv)->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFACD']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        ]);
        $sheet->getStyle('V' . $rc . ':X' . $rv)->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        ]);
        $sheet->getStyle('Y' . $rc . ':' . $Lc . $rv)->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        ]);

        $vertSubs = [
            8 => 'Urbana',
            9 => 'Rural',
            10 => 'hombre',
            11 => 'mujer',
            12 => 'intersexual',
            13 => 'cisgenero',
            14 => 'transgenero',
            15 => 'otra, cual',
            16 => 'Lesbiana',
            17 => 'Gay',
            18 => 'Bisexual',
            19 => 'Heterosexual',
            20 => 'Otra, Cual',
            22 => 'Afrodescendiente',
            23 => 'Indigena',
            24 => 'Otro',
        ];

        /** @var list<string> $medirAlto */
        $medirAlto = array_values($vertSubs);
        $excelGrupoSubVert = [
            "Con\ndiscapacidad",
            "Víctima del\nconflicto\narmado",
            "¿Se considera\ncampesino?",
            "¿Considera que la comunidad\nen la que\nvive es campesina?",
        ];
        foreach ($excelGrupoSubVert as $gLab) {
            $medirAlto[] = $gLab;
        }

        $maxVertLineChars = 0;
        foreach ($medirAlto as $txtMeas) {
            foreach (preg_split("/\r\n|\n|\r/", (string) $txtMeas) as $ln) {
                $ln = trim($ln);
                if ($ln !== '') {
                    $maxVertLineChars = max($maxVertLineChars, mb_strlen($ln, 'UTF-8'));
                }
            }
        }
        $rvHeight = min(280.0, max(132.0, $maxVertLineChars * 5.95 + 24.0));
        $sheet->getRowDimension($rv)->setRowHeight($rvHeight);

        foreach ($vertSubs as $cIdx => $txt) {
            $Ltr = Coordinate::stringFromColumnIndex((int) $cIdx);
            $sheet->setCellValue($Ltr . $rv, $txt);
            $sheet->getStyle($Ltr . $rv)->applyFromArray([
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_BOTTOM,
                    'textRotation' => 90,
                    'wrapText' => true,
                ],
                'font' => ['size' => 8],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
            ]);
        }

        $c28 = Coordinate::stringFromColumnIndex(28);
        $c30 = Coordinate::stringFromColumnIndex(30);
        $sheet->mergeCells($c28 . $rv . ':' . $c30 . $rv);

        foreach ([25 => $excelGrupoSubVert[0], 26 => $excelGrupoSubVert[1], 27 => $excelGrupoSubVert[2]] as $idx => $gTxt) {
            $Ll = Coordinate::stringFromColumnIndex((int) $idx);
            $sheet->setCellValue($Ll . $rv, $gTxt);
            $sheet->getStyle($Ll . $rv)->applyFromArray([
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_BOTTOM,
                    'textRotation' => 90,
                    'wrapText' => true,
                ],
                'font' => ['size' => 8],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
            ]);
        }

        $sheet->setCellValue($c28 . $rv, $excelGrupoSubVert[3]);
        $sheet->getStyle($c28 . $rv . ':' . $c30 . $rv)->applyFromArray([
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_BOTTOM,
                'textRotation' => 90,
                'wrapText' => true,
            ],
            'font' => ['size' => 8],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        ]);
        $demoTopRange = 'H' . $rc . ':' . $Lc . $rv;
        $sheet->getStyle($demoTopRange)->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        ]);

        $sheet->getRowDimension($rc)->setRowHeight(30);
        $sheet->getRowDimension($rt)->setRowHeight(38);

        $colW = [
            1 => 28,
            2 => 16,
            3 => 24,
            4 => 19,
            5 => 12,
            6 => 28,
            7 => 19,
            8 => 6.5,
            9 => 6.5,
            10 => 6,
            11 => 6,
            12 => 6.5,
            13 => 6.5,
            14 => 7,
            15 => 7,
            16 => 6.5,
            17 => 5.5,
            18 => 6.5,
            19 => 7,
            20 => 7,
            21 => 6,
            22 => 7,
            23 => 6.5,
            24 => 6,
            25 => 7.5,
            26 => 7.5,
            27 => 7.5,
            28 => 6.75,
            29 => 6.75,
            30 => 6.75,
        ];
        for ($ci = 1; $ci <= $lastDemo; ++$ci) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex((int) $ci))
                ->setWidth((float) ($colW[$ci] ?? 12));
        }

        $firstBodyRow = $rv + 1;
        $rowCursor = $firstBodyRow;
        foreach ($asistentes as $a) {
            $this->writeExcelAttendanceTemplateRow($sheet, $rowCursor, $a, $mun);
            ++$rowCursor;
        }
        $lastFilledBodyRow = $rowCursor - 1;
        $totRow = ($lastFilledBodyRow >= $firstBodyRow) ? ($lastFilledBodyRow + 1) : $firstBodyRow;
        $this->writeExcelAttendanceTotalsRow(
            $sheet,
            $totRow,
            ($lastFilledBodyRow >= $firstBodyRow) ? $firstBodyRow : null,
            ($lastFilledBodyRow >= $firstBodyRow) ? $lastFilledBodyRow : null,
        );

        $lastTableRow = $totRow;

        $sheet->getStyle("A{$rt}:{$Lc}{$lastTableRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        ]);

        $sheet->unfreezePane();
        $sheet->getSheetView()->setView(SheetView::SHEETVIEW_NORMAL);
        $sheet->setShowGridlines(true);
        $sheet->setSelectedCells('A' . ($rv + 1));

        return $spreadsheet;
    }

    /** @param array<string, mixed> $a */
    private function writeExcelAttendanceTemplateRow(Worksheet $sheet, int $row, array $a, string $municipioActividad): void
    {
        $Cx = static function (int $colIndex): string {
            return Coordinate::stringFromColumnIndex($colIndex);
        };

        $mark = static fn (bool $on): string => $on ? 'X' : '';
        $grupoStored = $a['grupo_poblacional'] ?? [];
        if (is_string($grupoStored)) {
            $decoded = json_decode($grupoStored, true);
            $grupoStored = is_array($decoded) ? $decoded : [];
        }

        foreach ($grupoStored as &$gItem) {
            $gItem = is_string($gItem) ? $gItem : '';
        }
        unset($gItem);

        /** @var list<string> $grupoStored */
        $excelGrupoAliased = [
            ['Con discapacidad'],
            ['Víctima del conflicto armado'],
            ['¿Se considera campesino?', 'Se considera campesino'],
            [
                '¿Considera que la comunidad en la que vive es campesina?',
                'Considera que la comunidad donde vive es campesina',
            ],
        ];

        $zone = trim((string) ($a['zone'] ?? ''));
        $sex = trim((string) ($a['sex'] ?? ''));
        $gMain = trim((string) ($a['genero_identidad'] ?? ''));
        $gOtro = trim((string) ($a['genero_identidad_otro'] ?? ''));
        $oMain = trim((string) ($a['orientacion_sexual'] ?? ''));
        $oOtro = trim((string) ($a['orientacion_sexual_otro'] ?? ''));
        $etnia = trim((string) ($a['etnia'] ?? ''));
        $etniaOtro = trim((string) ($a['etnia_otro'] ?? ''));

        $sheet->setCellValueExplicit($Cx(1) . $row, (string) ($a['full_name'] ?? ''), DataType::TYPE_STRING);
        $sheet->setCellValueExplicit($Cx(2) . $row, (string) ($a['document_number'] ?? ''), DataType::TYPE_STRING);
        $sheet->setCellValueExplicit($Cx(3) . $row, (string) ($a['entity'] ?? ''), DataType::TYPE_STRING);
        $sheet->setCellValueExplicit($Cx(4) . $row, (string) ($a['cargo'] ?? ''), DataType::TYPE_STRING);
        $sheet->setCellValueExplicit($Cx(5) . $row, (string) ($a['phone'] ?? ''), DataType::TYPE_STRING);
        $sheet->setCellValueExplicit($Cx(6) . $row, (string) ($a['email'] ?? ''), DataType::TYPE_STRING);
        $sheet->setCellValueExplicit($Cx(7) . $row, $municipioActividad, DataType::TYPE_STRING);

        $sheet->setCellValue($Cx(8) . $row, $mark($zone === 'Urbana'));
        $sheet->setCellValue($Cx(9) . $row, $mark($zone === 'Rural'));
        $sheet->setCellValue($Cx(10) . $row, $mark($sex === 'Hombre'));
        $sheet->setCellValue($Cx(11) . $row, $mark($sex === 'Mujer'));
        $sheet->setCellValue($Cx(12) . $row, $mark($sex === 'Intersexual'));
        $sheet->setCellValue($Cx(13) . $row, $mark($gMain === 'Cisgenero'));
        $sheet->setCellValue($Cx(14) . $row, $mark($gMain === 'Transgenero'));
        $sheet->setCellValue($Cx(15) . $row, $mark($gMain === 'Otra') . ($gOtro !== '' && $gMain === 'Otra' ? ("\n" . $gOtro) : ''));

        $sheet->setCellValue($Cx(16) . $row, $mark($oMain === 'Lesbiana'));
        $sheet->setCellValue($Cx(17) . $row, $mark($oMain === 'Gay'));
        $sheet->setCellValue($Cx(18) . $row, $mark($oMain === 'Bisexual'));
        $sheet->setCellValue($Cx(19) . $row, $mark($oMain === 'Heterosexual'));
        $sheet->setCellValue($Cx(20) . $row, $mark($oMain === 'Otra') . ($oOtro !== '' && $oMain === 'Otra' ? ("\n" . $oOtro) : ''));

        $ageCell = (($a['age'] !== null && $a['age'] !== '') ? (string) (int) $a['age'] : '');
        $sheet->setCellValueExplicit($Cx(21) . $row, $ageCell, DataType::TYPE_STRING);

        $sheet->setCellValue($Cx(22) . $row, $mark($etnia === 'Afrodescendiente'));
        $sheet->setCellValue($Cx(23) . $row, $mark($etnia === 'Indígena'));
        $sheet->setCellValue($Cx(24) . $row, $mark($etnia === 'Otro') . ($etnia === 'Otro' && $etniaOtro !== '' ? ("\n" . $etniaOtro) : ''));

        foreach ([0, 1, 2] as $i) {
            $aliases = $excelGrupoAliased[$i];
            $on = false;
            foreach ($aliases as $al) {
                if (in_array($al, $grupoStored, true)) {
                    $on = true;
                    break;
                }
            }
            $sheet->setCellValue($Cx(25 + $i) . $row, $mark($on));
        }
        $aliasesCom = $excelGrupoAliased[3];
        $onCom = false;
        foreach ($aliasesCom as $alC) {
            if (in_array($alC, $grupoStored, true)) {
                $onCom = true;
                break;
            }
        }
        $cG1 = $Cx(28);
        $cG3 = $Cx(30);
        $sheet->mergeCells($cG1 . $row . ':' . $cG3 . $row);
        $sheet->setCellValue($cG1 . $row, $mark($onCom));

        $contentLeft = "{$Cx(1)}{$row}:{$Cx(7)}{$row}";
        $sheet->getStyle($contentLeft)->applyFromArray([
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_TOP,
                'wrapText' => true,
            ],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        ]);
        $marks = "{$Cx(8)}{$row}:{$Cx(30)}{$row}";
        $sheet->getStyle($marks)->applyFromArray([
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        ]);
    }

    /** Detecta X de marcación (primera línea de la celda; admite segunda línea con texto tipo “otro”). */
    private function excelCellHasMarkX(mixed $cellValue): bool
    {
        if ($cellValue === null || $cellValue === '') {
            return false;
        }
        $s = is_string($cellValue) ? $cellValue : (string) $cellValue;
        $s = str_replace(["\r\n", "\r"], "\n", $s);
        $parts = explode("\n", $s, 2);
        $first = trim($parts[0]);

        return $first !== '' && strtoupper($first) === 'X';
    }

    private function excelCountMarksInColumn(Worksheet $sheet, int $colIdx, int $firstRow, int $lastRow): int
    {
        if ($lastRow < $firstRow) {
            return 0;
        }
        $col = Coordinate::stringFromColumnIndex($colIdx);
        $n = 0;
        for ($r = $firstRow; $r <= $lastRow; ++$r) {
            if ($this->excelCellHasMarkX($sheet->getCell($col . $r)->getValue())) {
                ++$n;
            }
        }

        return $n;
    }

    private function excelSumNumericAgeColumn(Worksheet $sheet, int $firstRow, int $lastRow): int
    {
        if ($lastRow < $firstRow) {
            return 0;
        }
        $col = Coordinate::stringFromColumnIndex(21);
        $sum = 0;
        for ($r = $firstRow; $r <= $lastRow; ++$r) {
            $raw = $sheet->getCell($col . $r)->getValue();
            if ($raw === null || $raw === '') {
                continue;
            }
            if (is_numeric($raw)) {
                $sum += (int) $raw;
            } elseif (is_string($raw)) {
                $t = trim($raw);
                if ($t !== '' && ctype_digit($t)) {
                    $sum += (int) $t;
                }
            }
        }

        return $sum;
    }

    /**
     * Fila TOTALES: “TOTALES” en municipio (G); desde zona (H) conteos grises.
     *
     * @param null|int $firstDataRow null si no hubo filas de asistentes
     */
    private function writeExcelAttendanceTotalsRow(
        Worksheet $sheet,
        int $totRow,
        ?int $firstDataRow,
        ?int $lastDataRow,
    ): void {
        $Cx = static fn (int $idx): string => Coordinate::stringFromColumnIndex($idx);
        $hasRows = $firstDataRow !== null && $lastDataRow !== null && $lastDataRow >= $firstDataRow;

        $markCols = [8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 22, 23, 24, 25, 26, 27];
        foreach ($markCols as $c) {
            $n = $hasRows ? $this->excelCountMarksInColumn($sheet, $c, $firstDataRow, $lastDataRow) : 0;
            $sheet->setCellValueExplicit($Cx($c) . $totRow, (string) $n, DataType::TYPE_STRING);
        }

        if ($hasRows) {
            $sumAge = $this->excelSumNumericAgeColumn($sheet, $firstDataRow, $lastDataRow);
            $sheet->setCellValueExplicit($Cx(21) . $totRow, (string) $sumAge, DataType::TYPE_STRING);
        } else {
            $sheet->setCellValueExplicit($Cx(21) . $totRow, '', DataType::TYPE_STRING);
        }

        $comCnt = $hasRows ? $this->excelCountMarksInColumn($sheet, 28, $firstDataRow, $lastDataRow) : 0;
        $sheet->mergeCells($Cx(28) . $totRow . ':' . $Cx(30) . $totRow);
        $sheet->setCellValueExplicit($Cx(28) . $totRow, (string) $comCnt, DataType::TYPE_STRING);

        $sheet->setCellValueExplicit($Cx(7) . $totRow, 'TOTALES', DataType::TYPE_STRING);

        $sheet->getStyle($Cx(7) . $totRow)->applyFromArray([
            'font' => ['bold' => true, 'size' => 10],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $totMarks = "{$Cx(8)}{$totRow}:{$Cx(30)}{$totRow}";
        $sheet->getStyle($totMarks)->applyFromArray([
            'font' => ['bold' => true, 'size' => 10],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9D9D9']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        ]);

        $sheet->getStyle($Cx(1) . $totRow . ':' . $Cx(6) . $totRow)->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']],
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
        $pdfBinary = PdfService::renderHtml(
            $html,
            'L',
            self::FIPC_LISTADO_TITULO . ' ' . (string) ($actividad['code'] ?? ''),
            true,
        );

        $safeCode = preg_replace('/[^a-zA-Z0-9_-]+/', '_', (string) ($actividad['code'] ?? '')) ?: 'export';
        $filename = 'asistencia_' . $safeCode . '_' . date('Ymd') . '.pdf';

        return new Response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }

    public function exportInformeGestion(Request $request): Response
    {
        $resolved = $this->resolveInformeContext($request, false);
        if ($resolved['error'] instanceof Response) {
            return $resolved['error'];
        }

        $activities = $this->repo->findActivitiesForInforme($resolved['filters']);
        $service = new AsistenciaInformeService($this->repo);

        try {
            $content = $service->buildDocx(
                $activities,
                $resolved['user'],
                $resolved['subregion'],
                $resolved['municipality'],
                $resolved['from_date'],
                $resolved['to_date'],
                $resolved['is_consolidado_admin']
            );
        } catch (\Throwable) {
            $content = '';
        }

        if ($content === '') {
            Flash::set([
                'type' => 'error',
                'title' => 'Exportación',
                'message' => 'No se pudo generar el informe de gestión en Word.',
            ]);

            return Response::redirect('/asistencia');
        }

        $safeMun = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $resolved['municipality']) ?: 'municipio';
        $filename = 'informe_gestion_' . $safeMun . '_' . date('Ymd_His') . '.docx';

        return new Response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }

    public function informePreview(Request $request): Response
    {
        $resolved = $this->resolveInformeContext($request, true);
        if ($resolved['error'] instanceof Response) {
            return $resolved['error'];
        }

        $activities = $this->repo->findActivitiesForInforme($resolved['filters']);
        $service = new AsistenciaInformeService($this->repo);

        $payload = $service->buildPreviewPayload($activities, $resolved['filtros_aplicados']);

        return Response::json($payload);
    }

    /**
     * @return array{
     *     error: Response|null,
     *     user: array<string, mixed>,
     *     subregion: string,
     *     municipality: string,
     *     from_date: string,
     *     to_date: string,
     *     is_consolidado_admin: bool,
     *     filters: array<string, mixed>,
     *     filtros_aplicados: array<string, mixed>
     * }
     */
    private function resolveInformeContext(Request $request, bool $asJson = false): array
    {
        $user = Auth::user();
        if ($user === null) {
            return [
                'error' => Response::redirect('/login'),
                'user' => [],
                'subregion' => '',
                'municipality' => '',
                'from_date' => '',
                'to_date' => '',
                'is_consolidado_admin' => false,
                'filters' => [],
                'filtros_aplicados' => [],
            ];
        }

        $subregion = trim((string) $request->input('subregion', ''));
        $municipalities = MunicipalityListRequest::parse($request);
        $fromDate = trim((string) $request->input('from_date', ''));
        $toDate = trim((string) $request->input('to_date', ''));
        $activeTab = $this->normalizeActividadTipo((string) $request->input('tab', 'aoat'));
        $statusFilter = trim((string) $request->input('status', ''));
        if (!in_array($statusFilter, self::ASISTENCIA_STATUSES, true)) {
            $statusFilter = '';
        }
        $listAdvisorId = (int) $request->input('advisor_user_id', 0);

        if ($subregion === '' || count($municipalities) !== 1 || $fromDate === '' || $toDate === '') {
            $message = 'Para el informe de gestión debes elegir subregión, un solo municipio y el rango de fechas (desde y hasta).';

            return [
                'error' => $asJson
                    ? Response::json(['error' => $message], 422)
                    : $this->informeFlashRedirect('Datos incompletos', $message),
                'user' => $user,
                'subregion' => '',
                'municipality' => '',
                'from_date' => '',
                'to_date' => '',
                'is_consolidado_admin' => false,
                'filters' => [],
                'filtros_aplicados' => [],
            ];
        }

        if ($fromDate > $toDate) {
            $message = 'La fecha inicial no puede ser posterior a la fecha final.';

            return [
                'error' => $asJson
                    ? Response::json(['error' => $message], 422)
                    : $this->informeFlashRedirect('Fechas no válidas', $message),
                'user' => $user,
                'subregion' => '',
                'municipality' => '',
                'from_date' => '',
                'to_date' => '',
                'is_consolidado_admin' => false,
                'filters' => [],
                'filtros_aplicados' => [],
            ];
        }

        $municipality = $municipalities[0];
        $informeModo = strtolower(trim((string) $request->input('informe_modo', 'propio')));
        $isConsolidadoAdmin = false;

        $filters = [
            'subregion' => $subregion,
            'municipality' => $municipality,
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ];

        if ($statusFilter !== '') {
            $filters['status'] = $statusFilter;
        }

        $advisorLabel = 'Todos';

        if ($this->userCanViewAllAsistencia($user)) {
            if ($informeModo === 'consolidado') {
                $isConsolidadoAdmin = true;
            } elseif ($informeModo === 'rol') {
                $rolFiltro = strtolower(trim((string) $request->input('informe_rol', '')));
                $allowedRoles = array_keys(self::informeRoleOptions());
                if (!in_array($rolFiltro, $allowedRoles, true)) {
                    $message = 'Selecciona un rol profesional para el informe.';

                    return [
                        'error' => $asJson
                            ? Response::json(['error' => $message], 422)
                            : $this->informeFlashRedirect('Rol no válido', $message),
                        'user' => $user,
                        'subregion' => '',
                        'municipality' => '',
                        'from_date' => '',
                        'to_date' => '',
                        'is_consolidado_admin' => false,
                        'filters' => [],
                        'filtros_aplicados' => [],
                    ];
                }
                $advisorIds = $this->advisorIdsByRole($rolFiltro);
                $filters['advisor_user_ids'] = $advisorIds === [] ? [0] : $advisorIds;
                $advisorLabel = self::informeRoleOptions()[$rolFiltro] ?? $rolFiltro;
            } elseif ($informeModo === 'asesor') {
                $advisorId = (int) $request->input('informe_advisor_user_id', 0);
                if ($advisorId <= 0 || !$this->advisorIsVisibleForUser($user, $advisorId)) {
                    $message = 'Selecciona un asesor válido para generar el informe.';

                    return [
                        'error' => $asJson
                            ? Response::json(['error' => $message], 422)
                            : $this->informeFlashRedirect('Asesor no válido', $message),
                        'user' => $user,
                        'subregion' => '',
                        'municipality' => '',
                        'from_date' => '',
                        'to_date' => '',
                        'is_consolidado_admin' => false,
                        'filters' => [],
                        'filtros_aplicados' => [],
                    ];
                }
                $filters['advisor_user_id'] = $advisorId;
                $advisorLabel = $this->resolveAdvisorName($advisorId);
            } else {
                $isConsolidadoAdmin = true;
            }
        } elseif ($this->userIsEspecialista($user)) {
            $advisors = $this->visibleAdvisorsForUser($user);
            $allowedAdvisorIds = array_map(static fn (array $advisor): int => (int) ($advisor['id'] ?? 0), $advisors);
            $filters['advisor_user_ids'] = $allowedAdvisorIds === [] ? [0] : $allowedAdvisorIds;
            $advisorLabel = 'Equipo ' . (AsistenciaInformeService::roleLabelFromUser($user));
        } else {
            $filters['advisor_user_id'] = (int) $user['id'];
            $advisorLabel = (string) ($user['name'] ?? 'Mi listado');
        }

        if ($listAdvisorId > 0 && $this->advisorIsVisibleForUser($user, $listAdvisorId)) {
            unset($filters['advisor_user_ids']);
            $filters['advisor_user_id'] = $listAdvisorId;
            $advisorLabel = $this->resolveAdvisorName($listAdvisorId);
            $isConsolidadoAdmin = false;
        }

        $filtrosAplicados = [
            'subregion' => $subregion,
            'municipio' => $municipality,
            'desde' => $fromDate,
            'hasta' => $toDate,
            'estado' => $statusFilter !== '' ? $statusFilter : 'Todos (Pendiente solo con asistentes)',
            'asesor' => $advisorLabel,
            'informe_modo' => $informeModo,
            'tab' => $activeTab,
        ];

        return [
            'error' => null,
            'user' => $user,
            'subregion' => $subregion,
            'municipality' => $municipality,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'is_consolidado_admin' => $isConsolidadoAdmin,
            'filters' => $filters,
            'filtros_aplicados' => $filtrosAplicados,
        ];
    }

    private function informeFlashRedirect(string $title, string $message): Response
    {
        Flash::set([
            'type' => 'error',
            'title' => $title,
            'message' => $message,
        ]);

        return Response::redirect('/asistencia');
    }

    private function resolveAdvisorName(int $advisorId): string
    {
        $advisor = $this->userRepo->find($advisorId);

        return $advisor !== null ? (string) ($advisor['name'] ?? 'Asesor') : 'Asesor';
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
        if (!$this->userCanAccessActividad($user, $actividad)) {
            Flash::set(['type' => 'error', 'title' => 'Acceso denegado', 'message' => 'No puedes eliminar esta actividad de asistencia.']);
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
            'fipc' => [
                'titulo' => self::FIPC_LISTADO_TITULO,
                'proceso' => self::FIPC_PROCESO_LINE,
                'contrato' => self::FIPC_CONTRATO_NUMERO,
            ],
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

        [$generoMain, $generoOtroNorm] = $this->finalizeGeneroIdentidadStorage($request);
        [$orientMain, $orientOtroNorm] = $this->finalizeOrientacionStorage($request);
        $data = [
            'actividad_id' => (int) $actividad['id'],
            'document_number' => $documentNumber,
            'full_name' => trim((string) $request->input('full_name', '')),
            'entity' => trim((string) $request->input('entity', '')) ?: null,
            'cargo' => trim((string) $request->input('cargo', '')) ?: null,
            'phone' => trim((string) $request->input('phone', '')) ?: null,
            'email' => trim((string) $request->input('email', '')) ?: null,
            'zone' => trim((string) $request->input('zone', '')) ?: null,
            'sex' => $this->finalizeSexStorage($request),
            'genero_identidad' => $generoMain,
            'genero_identidad_otro' => $generoOtroNorm,
            'orientacion_sexual' => $orientMain,
            'orientacion_sexual_otro' => $orientOtroNorm,
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
            'genero_identidad' => (string) ($asistente['genero_identidad'] ?? ''),
            'genero_identidad_otro' => (string) ($asistente['genero_identidad_otro'] ?? ''),
            'orientacion_sexual' => (string) ($asistente['orientacion_sexual'] ?? ''),
            'orientacion_sexual_otro' => (string) ($asistente['orientacion_sexual_otro'] ?? ''),
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

        $sexRaw = trim((string) $request->input('sex', ''));
        if ($sexRaw !== '' && !in_array($sexRaw, self::REGISTRO_SEX_ALLOWED, true)) {
            $errors[] = 'El valor seleccionado en sexo no es válido.';
        }

        $gen = trim((string) $request->input('genero_identidad', ''));
        $genOt = trim((string) $request->input('genero_identidad_otro', ''));
        if ($gen !== '') {
            if (!in_array($gen, self::REGISTRO_GENERO_IDENTIDAD_ALLOWED, true)) {
                $errors[] = 'La identidad de género seleccionada no es válida.';
            } elseif ($gen === 'Otra' && $genOt === '') {
                $errors[] = 'Cuando marca «Otra» en identidad de género debe especificar el texto.';
            }
        }

        $ori = trim((string) $request->input('orientacion_sexual', ''));
        $oriOt = trim((string) $request->input('orientacion_sexual_otro', ''));
        if ($ori !== '') {
            if (!in_array($ori, self::REGISTRO_ORIENTACION_ALLOWED, true)) {
                $errors[] = 'La orientación sexual seleccionada no es válida.';
            } elseif ($ori === 'Otra' && $oriOt === '') {
                $errors[] = 'Cuando marca «Otra» en orientación sexual debe especificar el texto.';
            }
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

    /**
     * Municipio donde se realiza la actividad (columna en BD y compatibilidad con otras formas del arreglo).
     */
    private function resolveActividadMunicipality(array $actividad): string
    {
        foreach (['municipality', 'Municipality', 'municipio'] as $key) {
            if (!array_key_exists($key, $actividad)) {
                continue;
            }
            $v = trim((string) $actividad[$key]);
            if ($v !== '') {
                return $v;
            }
        }

        return '';
    }

    /**
     * Meta CSV alineado con cabecera FIPC y detalle operativo del listado.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private function csvFipcHeaderRows(array $actividad, string $listadoTemasLabel, string $tiposStr): array
    {
        $rows = [
            [self::FIPC_LISTADO_TITULO, ''],
            [self::FIPC_PROCESO_LINE, ''],
            ['Contrato No. ' . self::FIPC_CONTRATO_NUMERO, ''],
            ['Información del listado / actividad', ''],
            ['Código', (string) ($actividad['code'] ?? '')],
            ['Fecha', (string) ($actividad['activity_date'] ?? '')],
            ['Subregión', (string) ($actividad['subregion'] ?? '')],
            ['Municipio', $this->resolveActividadMunicipality($actividad)],
            ['Lugar', (string) ($actividad['lugar'] ?? '')],
            ['Asesor', (string) ($actividad['advisor_name'] ?? '')],
            [$listadoTemasLabel, $tiposStr],
            ['Estado', (string) ($actividad['status'] ?? '')],
            ['Enlace de registro público', $this->registrationUrl((string) ($actividad['code'] ?? ''))],
        ];
        $rows[] = ['', ''];
        return $rows;
    }

    private function formatCsvGeneroOrientacionField(string $principal, string $otroTexto, string $otraKey): string
    {
        $principal = trim($principal);
        if ($principal === '') {
            return '';
        }
        $otroTexto = trim($otroTexto);
        if ($principal !== $otraKey) {
            return $principal;
        }

        return $otroTexto !== '' ? $principal . ': ' . $otroTexto : $principal;
    }

    private function finalizeSexStorage(Request $request): ?string
    {
        $s = trim((string) $request->input('sex', ''));

        return $s !== '' ? $s : null;
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function finalizeGeneroIdentidadStorage(Request $request): array
    {
        $gen = trim((string) $request->input('genero_identidad', ''));
        if ($gen === '') {
            return [null, null];
        }
        if ($gen !== 'Otra') {
            return [$gen, null];
        }
        $o = trim((string) $request->input('genero_identidad_otro', ''));

        return [$gen, $o !== '' ? $o : null];
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function finalizeOrientacionStorage(Request $request): array
    {
        $ori = trim((string) $request->input('orientacion_sexual', ''));
        if ($ori === '') {
            return [null, null];
        }
        if ($ori !== 'Otra') {
            return [$ori, null];
        }
        $o = trim((string) $request->input('orientacion_sexual_otro', ''));

        return [$ori, $o !== '' ? $o : null];
    }

    private function pdfMarkCell(bool $on): string
    {
        return $on ? '<strong>X</strong>' : '';
    }

    /**
     * Agregados para fila TOTALES del PDF (mismas reglas que las filas de datos).
     *
     * @param list<array<string, mixed>> $asistentes
     * @return array<string, int>
     */
    private function pdfDemographicTotals(array $asistentes): array
    {
        $t = [
            'urbana' => 0,
            'rural' => 0,
            'hombre' => 0,
            'mujer' => 0,
            'intersexual' => 0,
            'cis' => 0,
            'trans' => 0,
            'nobin' => 0,
            'otra_gen' => 0,
            'les' => 0,
            'gay' => 0,
            'bi' => 0,
            'het' => 0,
            'otra_ori' => 0,
            'sum_age' => 0,
            'afro' => 0,
            'ind' => 0,
            'otro_et' => 0,
            'grp_disc' => 0,
            'grp_vic' => 0,
            'grp_camp' => 0,
            'grp_comun' => 0,
        ];
        $lbls = self::GRUPO_POBLACIONAL_PDF_LABELS;

        foreach ($asistentes as $a) {
            $grupo = $a['grupo_poblacional'] ?? [];
            if (!is_array($grupo)) {
                $grupo = [];
            }
            $zone = trim((string) ($a['zone'] ?? ''));
            $sex = trim((string) ($a['sex'] ?? ''));
            $gMain = trim((string) ($a['genero_identidad'] ?? ''));
            $oMain = trim((string) ($a['orientacion_sexual'] ?? ''));
            $etnia = trim((string) ($a['etnia'] ?? ''));
            $gNoBinario = strcasecmp($gMain, 'No binario') === 0;

            if ($zone === 'Urbana') {
                ++$t['urbana'];
            }
            if ($zone === 'Rural') {
                ++$t['rural'];
            }
            if ($sex === 'Hombre') {
                ++$t['hombre'];
            }
            if ($sex === 'Mujer') {
                ++$t['mujer'];
            }
            if ($sex === 'Intersexual') {
                ++$t['intersexual'];
            }
            if ($gMain === 'Cisgenero') {
                ++$t['cis'];
            }
            if ($gMain === 'Transgenero') {
                ++$t['trans'];
            }
            if ($gNoBinario) {
                ++$t['nobin'];
            }
            if ($gMain === 'Otra') {
                ++$t['otra_gen'];
            }
            if ($oMain === 'Lesbiana') {
                ++$t['les'];
            }
            if ($oMain === 'Gay') {
                ++$t['gay'];
            }
            if ($oMain === 'Bisexual') {
                ++$t['bi'];
            }
            if ($oMain === 'Heterosexual') {
                ++$t['het'];
            }
            if ($oMain === 'Otra') {
                ++$t['otra_ori'];
            }
            if ($a['age'] !== null && $a['age'] !== '') {
                $t['sum_age'] += (int) $a['age'];
            }
            if ($etnia === 'Afrodescendiente') {
                ++$t['afro'];
            }
            if ($etnia === 'Indígena') {
                ++$t['ind'];
            }
            if ($etnia === 'Otro') {
                ++$t['otro_et'];
            }

            if (in_array($lbls[0], $grupo, true)) {
                ++$t['grp_disc'];
            }
            if (in_array($lbls[1], $grupo, true)) {
                ++$t['grp_vic'];
            }
            if (in_array($lbls[2], $grupo, true) || in_array('¿Se considera campesino?', $grupo, true)) {
                ++$t['grp_camp'];
            }
            if (in_array($lbls[3], $grupo, true)
                || in_array('¿Considera que la comunidad en la que vive es campesina?', $grupo, true)) {
                ++$t['grp_comun'];
            }
        }

        return $t;
    }

    /**
     * @param array<string, int> $tot
     */
    private function pdfTotalsDataCells(callable $esc, array $tot): string
    {
        $n = static fn (int $v): string => $esc((string) $v);

        return '<td class="pdf-td-tot" style="text-align:center;">' . $n($tot['urbana']) . '</td>'
            . '<td class="pdf-td-tot" style="text-align:center;">' . $n($tot['rural']) . '</td>'
            . '<td class="pdf-td-tot" style="text-align:center;">' . $n($tot['hombre']) . '</td>'
            . '<td class="pdf-td-tot" style="text-align:center;">' . $n($tot['mujer']) . '</td>'
            . '<td class="pdf-td-tot" style="text-align:center;">' . $n($tot['intersexual']) . '</td>'
            . '<td class="pdf-td-tot" style="text-align:center;">' . $n($tot['cis']) . '</td>'
            . '<td class="pdf-td-tot" style="text-align:center;">' . $n($tot['trans']) . '</td>'
            . '<td class="pdf-td-tot" style="text-align:center;">' . $n($tot['nobin']) . '</td>'
            . '<td class="pdf-td-tot" style="text-align:center;">' . $n($tot['otra_gen']) . '</td>'
            . '<td class="pdf-td-tot" style="text-align:center;">' . $n($tot['les']) . '</td>'
            . '<td class="pdf-td-tot" style="text-align:center;">' . $n($tot['gay']) . '</td>'
            . '<td class="pdf-td-tot" style="text-align:center;">' . $n($tot['bi']) . '</td>'
            . '<td class="pdf-td-tot" style="text-align:center;">' . $n($tot['het']) . '</td>'
            . '<td class="pdf-td-tot" style="text-align:center;">' . $n($tot['otra_ori']) . '</td>'
            . '<td class="pdf-td-tot" style="text-align:center;">' . $n($tot['sum_age']) . '</td>'
            . '<td class="pdf-td-tot" style="text-align:center;">' . $n($tot['afro']) . '</td>'
            . '<td class="pdf-td-tot" style="text-align:center;">' . $n($tot['ind']) . '</td>'
            . '<td class="pdf-td-tot" style="text-align:center;">' . $n($tot['otro_et']) . '</td>'
            . '<td class="pdf-td-tot" style="text-align:center;">' . $n($tot['grp_disc']) . '</td>'
            . '<td class="pdf-td-tot" style="text-align:center;">' . $n($tot['grp_vic']) . '</td>'
            . '<td class="pdf-td-tot" style="text-align:center;">' . $n($tot['grp_camp']) . '</td>'
            . '<td colspan="4" class="pdf-td-tot" style="text-align:center;">' . $n($tot['grp_comun']) . '</td>'
            . '<td></td>';
    }

    /**
     * @param callable(string): string $esc
     */
    private function pdfVerticalLabelPortrait(string $label, callable $esc): string
    {
        $label = trim($label);

        return $label === '' ? '' : $esc(mb_strtoupper($label, 'UTF-8'));
    }

    /**
     * Grupo poblacional (cabeceras estrechas): una línea MAYÚSCULAS; mismo giro −90° que Zona/Sexo en mPDF.
     *
     * @param callable(string): string $esc
     */
    private function pdfGrupoPortraitLines(string $rawText, callable $esc): string
    {
        $rawText = str_replace(["\r\n", "\r"], "\n", trim($rawText));
        if ($rawText === '') {
            return '';
        }
        $collapse = preg_replace('/[\s\n]+/u', ' ', str_replace("\n", ' ', $rawText));
        $one = mb_strtoupper(trim((string) $collapse), 'UTF-8');

        return $one === '' ? '' : $esc($one);
    }

    private function buildPdfHtml(array $actividad, array $asistentes): string
    {
        $esc = static function (string $s): string {
            return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        };

        $base = dirname(__DIR__, 2) . '/public/assets/img';
        $logoAntSrc = PdfImageHelper::imageDataUri($base . '/logoAntioquia.png');
        $logoAntHtml = $logoAntSrc !== ''
            ? '<img src="' . $esc($logoAntSrc) . '" alt="Gobernación de Antioquia" style="height:40px;width:auto;">'
            : '';

        $contratoTxt = $esc(self::FIPC_CONTRATO_NUMERO);

        $fipcCenter =
            '<div style="font-size:10px;line-height:1.25;color:#14324b;">'
            . '<div style="font-size:11px;font-weight:800;color:#14324b;">'
            . $esc(self::FIPC_LISTADO_TITULO)
            . '</div>'
            . '<div style="font-weight:700;margin:.2rem 0;font-size:9px;">'
            . $esc(self::FIPC_PROCESO_LINE)
            . '</div>'
            . '<span style="display:inline-block;margin-top:.15rem;padding:.25rem .55rem;border:1.5px solid #2f6b57;border-radius:6px;background:#eaf5ef;color:#1d4f3f;font-weight:800;font-size:8px;text-decoration:underline;">Contrato No. '
            . $contratoTxt
            . '</span>'
            . '</div>';

        $banner = '<table style="width:100%;border-collapse:collapse;margin-bottom:7px;">'
            . '<tr><td style="width:32%;vertical-align:middle;text-align:left;">' . $logoAntHtml . '</td>'
            . '<td style="vertical-align:middle;text-align:center;">' . $fipcCenter . '</td></tr>'
            . '</table>';

        $tipos = $actividad['actividad_tipos'] ?? [];
        $tiposStr = is_array($tipos) ? implode('; ', $tipos) : (string) $tipos;
        $tipoListadoTitulo = $this->normalizeActividadTipo((string) ($actividad['tipo'] ?? 'aoat')) === 'actividad'
            ? 'Actividad'
            : 'Listado AoAT';

        $municipioValor = $this->resolveActividadMunicipality($actividad);
        $municipioActPdf = $esc($municipioValor);

        $metaBloc =
            '<p style="margin:0 0 4px;font-size:9.5px;line-height:1.3;"><strong>Listado código:</strong> '
            . $esc((string) ($actividad['code'] ?? ''))
            . ' · <strong>Fecha:</strong> '
            . $esc((string) ($actividad['activity_date'] ?? ''))
            . ' · <strong>Subregión:</strong> '
            . $esc((string) ($actividad['subregion'] ?? ''))
            . '</p>'
            . '<p style="margin:0 0 6px;font-size:9.5px;line-height:1.3;"><strong>Municipio:</strong> '
            . $municipioActPdf
            . ' · <strong>Lugar:</strong> '
            . $esc((string) ($actividad['lugar'] ?? ''))
            . ' · <strong>Asesor:</strong> '
            . $esc((string) ($actividad['advisor_name'] ?? ''))
            . ' · <strong>' . $esc($tipoListadoTitulo) . ':</strong> '
            . $esc($tiposStr)
            . '</p>';

        /** +90º en mPDF: lectura vertical opuesta a −90 (alineación tipo plantilla institucional). */
        $thRot = ' text-rotate="90" style="white-space:nowrap;"';
        $thead = '<thead>'
            . '<tr style="vertical-align:middle;text-align:center;">'
            . '<th rowspan="2" class="pdf-h-base">#</th>'
            . '<th rowspan="2" class="pdf-h-base">Documento</th>'
            . '<th rowspan="2" class="pdf-h-base">Nombres<br>completos</th>'
            . '<th rowspan="2" class="pdf-h-base">Entidad</th>'
            . '<th rowspan="2" class="pdf-h-base">Cargo</th>'
            . '<th rowspan="2" class="pdf-h-base">Teléfono</th>'
            . '<th rowspan="2" class="pdf-h-base">Correo</th>'
            . '<th rowspan="2" class="pdf-h-base">Municipio</th>'
            . '<th colspan="2" class="pdf-h-zona-head">Zona<br>residencia</th>'
            . '<th colspan="3" class="pdf-h-sexo-head">Sexo</th>'
            . '<th colspan="4" class="pdf-h-gen-head">Identidad de Genero</th>'
            . '<th colspan="5" class="pdf-h-ori-head">Orientación Sexual</th>'
            . '<th class="pdf-h-base">Edad</th>'
            . '<th colspan="3" class="pdf-h-etn-head">Etnia</th>'
            . '<th colspan="7" class="pdf-h-grp-head">Grupo poblacional</th>'
            . '<th rowspan="2" class="pdf-h-base">Registro</th>'
            . '</tr>'
            . '<tr style="vertical-align:middle;text-align:center;">'
            . '<th class="pdf-h-zona-sub pdf-h-rot90"' . $thRot . '>' . $this->pdfVerticalLabelPortrait('Urbana', $esc) . '</th>'
            . '<th class="pdf-h-zona-sub pdf-h-rot90"' . $thRot . '>' . $this->pdfVerticalLabelPortrait('Rural', $esc) . '</th>'
            . '<th class="pdf-h-sexo-sub pdf-h-rot90"' . $thRot . '>' . $this->pdfVerticalLabelPortrait('Hombre', $esc) . '</th>'
            . '<th class="pdf-h-sexo-sub pdf-h-rot90"' . $thRot . '>' . $this->pdfVerticalLabelPortrait('Mujer', $esc) . '</th>'
            . '<th class="pdf-h-sexo-sub pdf-h-rot90"' . $thRot . '>' . $this->pdfVerticalLabelPortrait('Intersexual', $esc) . '</th>'
            . '<th class="pdf-h-gen-sub pdf-h-rot90"' . $thRot . '>' . $this->pdfVerticalLabelPortrait('cisgenero', $esc) . '</th>'
            . '<th class="pdf-h-gen-sub pdf-h-rot90"' . $thRot . '>' . $this->pdfVerticalLabelPortrait('transgenero', $esc) . '</th>'
            . '<th class="pdf-h-gen-sub pdf-h-rot90"' . $thRot . '>' . $this->pdfVerticalLabelPortrait('NO BINARIO', $esc) . '</th>'
            . '<th class="pdf-h-gen-sub pdf-h-rot90"' . $thRot . '>' . $this->pdfVerticalLabelPortrait('Otra ¿cuál?', $esc) . '</th>'
            . '<th class="pdf-h-ori-sub pdf-h-rot90"' . $thRot . '>' . $this->pdfVerticalLabelPortrait('Lesbiana', $esc) . '</th>'
            . '<th class="pdf-h-ori-sub pdf-h-rot90"' . $thRot . '>' . $this->pdfVerticalLabelPortrait('Gay', $esc) . '</th>'
            . '<th class="pdf-h-ori-sub pdf-h-rot90"' . $thRot . '>' . $this->pdfVerticalLabelPortrait('Bisexual', $esc) . '</th>'
            . '<th class="pdf-h-ori-sub pdf-h-rot90"' . $thRot . '>' . $this->pdfVerticalLabelPortrait('Heterosexual', $esc) . '</th>'
            . '<th class="pdf-h-ori-sub pdf-h-rot90"' . $thRot . '>' . $this->pdfVerticalLabelPortrait('Otra ¿cuál?', $esc) . '</th>'
            . '<th class="pdf-h-base pdf-h-rot90"' . $thRot . '>' . $this->pdfVerticalLabelPortrait('Edad', $esc) . '</th>'
            . '<th class="pdf-h-etn-sub pdf-h-rot90"' . $thRot . '>' . $this->pdfVerticalLabelPortrait('Afrodescendiente', $esc) . '</th>'
            . '<th class="pdf-h-etn-sub pdf-h-rot90"' . $thRot . '>' . $this->pdfVerticalLabelPortrait('Indigena', $esc) . '</th>'
            . '<th class="pdf-h-etn-sub pdf-h-rot90"' . $thRot . '>' . $this->pdfVerticalLabelPortrait('Otro', $esc) . '</th>'
            . '<th class="pdf-h-grp-sub pdf-h-rot90"' . $thRot . '>' . $this->pdfGrupoPortraitLines("Con\ndiscapacidad", $esc) . '</th>'
            . '<th class="pdf-h-grp-sub pdf-h-rot90"' . $thRot . '>' . $this->pdfGrupoPortraitLines("Víctima del\nconflicto\narmado", $esc) . '</th>'
            . '<th class="pdf-h-grp-sub pdf-h-rot90"' . $thRot . '>' . $this->pdfGrupoPortraitLines("¿Se considera\ncampesino?", $esc) . '</th>'
            . '<th colspan="4" class="pdf-h-grp-wide-wrap pdf-h-rot90"' . $thRot . '>'
            . $this->pdfGrupoPortraitLines("¿Considera que la comunidad\nen la que vive es\ncampesina?", $esc) . '</th>';

        $thead .= '</tr></thead>';

        $tbody = '';
        foreach ($asistentes as $i => $a) {
            $grupo = $a['grupo_poblacional'] ?? [];
            if (!is_array($grupo)) {
                $grupo = [];
            }
            $zone = trim((string) ($a['zone'] ?? ''));
            $sex = trim((string) ($a['sex'] ?? ''));
            $gMain = trim((string) ($a['genero_identidad'] ?? ''));
            $gOtro = trim((string) ($a['genero_identidad_otro'] ?? ''));
            $gNoBinario = strcasecmp($gMain, 'No binario') === 0;
            $oMain = trim((string) ($a['orientacion_sexual'] ?? ''));
            $oOtro = trim((string) ($a['orientacion_sexual_otro'] ?? ''));
            $etnia = trim((string) ($a['etnia'] ?? ''));
            $etniaOtro = trim((string) ($a['etnia_otro'] ?? ''));

            $lbls = self::GRUPO_POBLACIONAL_PDF_LABELS;
            $onCamp = in_array($lbls[2], $grupo, true)
                || in_array('¿Se considera campesino?', $grupo, true);
            $onComunCamp = in_array($lbls[3], $grupo, true)
                || in_array('¿Considera que la comunidad en la que vive es campesina?', $grupo, true);
            $gpCells = ''
                . '<td style="text-align:center;">' . $this->pdfMarkCell(in_array($lbls[0], $grupo, true)) . '</td>'
                . '<td style="text-align:center;">' . $this->pdfMarkCell(in_array($lbls[1], $grupo, true)) . '</td>'
                . '<td style="text-align:center;">' . $this->pdfMarkCell($onCamp) . '</td>'
                . '<td colspan="4" style="text-align:center;">' . $this->pdfMarkCell($onComunCamp)
                . '</td>';

            $tbody .= '<tr>'
                . '<td style="text-align:center;">' . ($i + 1) . '</td>'
                . '<td>' . $esc((string) ($a['document_number'] ?? '')) . '</td>'
                . '<td>' . $esc((string) ($a['full_name'] ?? '')) . '</td>'
                . '<td>' . $esc((string) ($a['entity'] ?? '')) . '</td>'
                . '<td>' . $esc((string) ($a['cargo'] ?? '')) . '</td>'
                . '<td>' . $esc((string) ($a['phone'] ?? '')) . '</td>'
                . '<td style="font-size:8px;">' . $esc((string) ($a['email'] ?? '')) . '</td>'
                . '<td style="font-size:8px;">' . $municipioActPdf . '</td>'
                . '<td style="text-align:center;">' . $this->pdfMarkCell($zone === 'Urbana') . '</td>'
                . '<td style="text-align:center;">' . $this->pdfMarkCell($zone === 'Rural') . '</td>'
                . '<td style="text-align:center;">' . $this->pdfMarkCell($sex === 'Hombre') . '</td>'
                . '<td style="text-align:center;">' . $this->pdfMarkCell($sex === 'Mujer') . '</td>'
                . '<td style="text-align:center;">' . $this->pdfMarkCell($sex === 'Intersexual') . '</td>'
                . '<td style="text-align:center;">' . $this->pdfMarkCell($gMain === 'Cisgenero') . '</td>'
                . '<td style="text-align:center;">' . $this->pdfMarkCell($gMain === 'Transgenero') . '</td>'
                . '<td style="text-align:center;">' . $this->pdfMarkCell($gNoBinario) . '</td>'
                . '<td style="text-align:center;font-size:7px;">'
                . $this->pdfMarkCell($gMain === 'Otra')
                . ($gOtro !== '' && $gMain === 'Otra' ? ('<br>' . $esc($gOtro)) : '')
                . '</td>'
                . '<td style="text-align:center;">' . $this->pdfMarkCell($oMain === 'Lesbiana') . '</td>'
                . '<td style="text-align:center;">' . $this->pdfMarkCell($oMain === 'Gay') . '</td>'
                . '<td style="text-align:center;">' . $this->pdfMarkCell($oMain === 'Bisexual') . '</td>'
                . '<td style="text-align:center;">' . $this->pdfMarkCell($oMain === 'Heterosexual') . '</td>'
                . '<td style="text-align:center;font-size:7px;">'
                . $this->pdfMarkCell($oMain === 'Otra')
                . ($oOtro !== '' && $oMain === 'Otra' ? ('<br>' . $esc($oOtro)) : '')
                . '</td>'
                . '<td style="text-align:center;">'
                . (($a['age'] !== null && $a['age'] !== '') ? $esc((string) (int) $a['age']) : '')
                . '</td>'
                . '<td style="text-align:center;">' . $this->pdfMarkCell($etnia === 'Afrodescendiente') . '</td>'
                . '<td style="text-align:center;">' . $this->pdfMarkCell($etnia === 'Indígena') . '</td>'
                . '<td style="text-align:center;font-size:7px;">'
                . $this->pdfMarkCell($etnia === 'Otro')
                . ($etnia === 'Otro' && $etniaOtro !== '' ? ('<br>' . $esc($etniaOtro)) : '')
                . '</td>'
                . $gpCells
                . '<td style="font-size:8px;">' . $esc((string) ($a['registered_at'] ?? '')) . '</td>'
                . '</tr>';
        }

        $totAgg = $this->pdfDemographicTotals($asistentes);
        $tbody .= '<tr class="pdf-totales">'
            . str_repeat('<td></td>', 7)
            . '<td class="pdf-td-tot-lbl" style="text-align:center;font-weight:800;font-size:7.5px;">TOTALES</td>'
            . $this->pdfTotalsDataCells($esc, $totAgg)
            . '</tr>';

        $css =
            '@page{margin:16px;} body{font-family:DejaVu Sans,system-ui,sans-serif;margin:8px;color:#212529;} '
            . '.pdf-table-wrap{border:1px solid #000;}'
            . '.pdf-x-strip{margin:4px 0 0;text-align:center;font-size:9px;font-weight:700;background:#e8e8e8;color:#253649;padding:3px 4px;line-height:1.15;border-bottom:1px solid #000;}'
            . '.wrap{overflow:auto;}'
            . 'table.t-as{width:100%;border-collapse:collapse;font-size:6.75px;table-layout:fixed;border:none;margin:0;}'
            . 'table.t-as th,table.t-as td{border:1px solid #000;padding:1px 1px;} '
            . 'table.t-as thead th[rowspan]{vertical-align:top;padding-top:1px;} '
            . 'table.t-as thead tr:first-child th:not([rowspan]){vertical-align:top;padding-top:2px;padding-bottom:1px;line-height:1.1;} '
            . 'table.t-as thead th{font-weight:bold;font-size:7px;}'
            . 'table.t-as thead .pdf-h-base{background:#eaf0f7;color:#253649;} '
            . 'table.t-as thead .pdf-h-zona-head,table.t-as thead .pdf-h-zona-sub{background:#eaf0f7;color:#253649;} '
            . 'table.t-as thead .pdf-h-sexo-head,table.t-as thead .pdf-h-sexo-sub{background:#ddebd7;color:#253649;} '
            . 'table.t-as thead .pdf-h-gen-head,table.t-as thead .pdf-h-gen-sub{background:#ddd6e9;color:#253649;} '
            . 'table.t-as thead .pdf-h-ori-head,table.t-as thead .pdf-h-ori-sub{background:#fff3a8;color:#253649;} '
            . 'table.t-as thead .pdf-h-etn-head,table.t-as thead .pdf-h-etn-sub{background:#eaf0f7;color:#253649;} '
            . 'table.t-as thead .pdf-h-grp-head,table.t-as thead .pdf-h-grp-sub,table.t-as thead .pdf-h-grp-wide-wrap{background:#eaf0f7;color:#253649;} '
            . 'table.t-as thead th.pdf-h-sexo-sub,table.t-as thead th.pdf-h-gen-sub,table.t-as thead th.pdf-h-ori-sub,table.t-as thead th.pdf-h-etn-sub{padding:10px 8px;line-height:.86;width:27px;} '
            . 'table.t-as thead th.pdf-h-zona-sub{padding:10px 8px;line-height:.86;width:29px;} '
            . 'table.t-as thead th.pdf-h-grp-sub{padding:10px 8px;line-height:.95;width:21px;} '
            . 'table.t-as thead tr:nth-child(2) th.pdf-h-rot90{padding:8px 5px;line-height:1;vertical-align:middle;text-align:center;font-size:6.2px;font-weight:800;letter-spacing:0.38em;} '
            . 'table.t-as thead tr:nth-child(2) th.pdf-h-grp-sub.pdf-h-rot90{font-size:5.5px;letter-spacing:0.22em;} '
            . 'table.t-as thead tr:nth-child(2) th.pdf-h-base.pdf-h-rot90{width:24px;} '
            . 'table.t-as tbody tr.pdf-totales td.pdf-td-tot{background:#d9d9d9;font-weight:800;font-size:7.5px;vertical-align:middle;} '
            . 'table.t-as tbody tr.pdf-totales td.pdf-td-tot-lbl{background:#fff;font-weight:800;vertical-align:middle;} '
            . 'table.t-as tbody td{vertical-align:top;background:#fff;font-size:6.6px;padding:4px 5px;} '
            ;

        return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">'
            . '<title>' . $esc(self::FIPC_LISTADO_TITULO . ' ' . (string) ($actividad['code'] ?? '')) . '</title>'
            . '<style>' . $css . '</style>'
            . '</head><body>'
            . $banner
            . $metaBloc
            . '<div class="wrap"><div class="pdf-table-wrap">'
            . '<div class="pdf-x-strip">Señale con una X la condición que cumpla</div>'
            . '<table class="t-as">' . $thead . '<tbody>' . $tbody . '</tbody></table></div></div>'
            . '</body></html>';
    }

    /**
     * @return array<string, string>
     */
    private static function informeRoleOptions(): array
    {
        return [
            'psicologo' => 'Psicólogo',
            'medico' => 'Médico',
            'abogado' => 'Abogado',
            'trabajador_social' => 'Profesional social',
        ];
    }

    /**
     * @return list<int>
     */
    private function advisorIdsByRole(string $roleKey): array
    {
        $roleKey = strtolower(trim($roleKey));
        $ids = [];
        foreach ($this->userRepo->findNonAdminAdvisors() as $advisor) {
            $advisorId = (int) ($advisor['id'] ?? 0);
            if ($advisorId <= 0) {
                continue;
            }
            $advisorUser = $this->userRepo->find($advisorId);
            if ($advisorUser !== null && $this->resolveActividadRoleFromUser($advisorUser) === $roleKey) {
                $ids[] = $advisorId;
            }
        }

        return $ids;
    }
}