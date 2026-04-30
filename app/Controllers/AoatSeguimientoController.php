<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\AoatRepository;
use App\Support\MunicipalityListRequest;
use App\Services\Auth;
use App\Services\AoatSeguimientoService;
use App\Services\PdfImageHelper;
use App\Services\PdfService;

final class AoatSeguimientoController
{
    private AoatSeguimientoService $service;

    public function __construct()
    {
        $this->service = new AoatSeguimientoService();
    }

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
        $records = $this->fetchScopedRecords($user, $repo);
        $defaultFilters = [
            'year' => (int) date('Y'),
            'period' => (int) date('n') <= 6 ? 'ene_jun' : 'jul_dic',
            'professional_user_id' => 0,
            'filter_month' => 0,
            'state' => '',
            'role' => '',
            'subregion' => '',
            'municipalities' => [],
            'vista' => 'meta',
            'total_periodo' => '',
        ];
        $defaultFilters = $this->withSeguimientoViewerFlags($user, $defaultFilters);
        $initialMeta = $this->service->buildMatrix($records, $defaultFilters);
        $filterOptions = $this->buildFilterOptions($records);

        return Response::view('aoat/seguimiento', [
            'pageTitle' => 'Seguimiento territorial AoAT · Metas',
            'filterOptions' => $filterOptions,
            'initialMeta' => $initialMeta,
        ]);
    }

    public function data(Request $request): Response
    {
        $user = Auth::user();
        if ($user === null) {
            return Response::json(['error' => 'No autenticado'], 401);
        }
        if (!$this->userCanAccessAoat($user)) {
            return Response::json(['error' => 'Acceso denegado'], 403);
        }

        $repo = new AoatRepository();
        $records = $this->fetchScopedRecords($user, $repo);

        $filters = [
            'year' => max(2020, (int) $request->input('year', (int) date('Y'))),
            'period' => trim((string) $request->input('period', 'ene_jun')) === 'jul_dic' ? 'jul_dic' : 'ene_jun',
            'professional_user_id' => max(0, (int) $request->input('professional_user_id', 0)),
            'filter_month' => (int) $request->input('filter_month', 0),
            'state' => $this->normalizeAoatStateFilter((string) $request->input('state', '')),
            'role' => trim((string) $request->input('role', '')),
            'subregion' => trim((string) $request->input('subregion', '')),
            'municipalities' => MunicipalityListRequest::parse($request, 'municipality'),
            'vista' => trim((string) $request->input('vista', 'meta')) === 'actividad' ? 'actividad' : 'meta',
            'total_periodo' => $this->normalizeTotalPeriodoFilter((string) $request->input('total_periodo', '')),
        ];
        $filters = $this->withSeguimientoViewerFlags($user, $filters);

        $matrix = $this->service->buildMatrix($records, $filters);
        $filterOptions = $this->buildFilterOptions($records);

        return Response::json([
            'ok' => true,
            'filters' => $filters,
            'matrix' => $matrix,
            'filterOptions' => $filterOptions,
        ]);
    }

    public function exportCsv(Request $request): Response
    {
        $user = Auth::user();
        if ($user === null) {
            return Response::redirect('/login');
        }
        if (!$this->userCanAccessAoat($user)) {
            return Response::view('errors/403', ['pageTitle' => 'Acceso denegado'], 403);
        }

        $repo = new AoatRepository();
        $records = $this->fetchScopedRecords($user, $repo);
        $filters = $this->withSeguimientoViewerFlags($user, $this->parseFiltersFromRequest($request));
        $matrix = $this->service->buildMatrix($records, $filters);
        $vista = ($filters['vista'] ?? 'meta') === 'actividad' ? 'actividad' : 'meta';

        $esc = static function (string $v): string {
            return '"' . str_replace('"', '""', $v) . '"';
        };

        $roleFilterRaw = trim((string) ($filters['role'] ?? ''));
        $roleNorm = preg_replace('/\s+/', ' ', strtolower(str_replace('_', ' ', $roleFilterRaw)));
        $colMetaA = 'Asesorías';
        $colMetaB = 'Asistencia técnica';
        if ($roleNorm === 'abogado') {
            $colMetaA = 'SAFER';
            $colMetaB = 'Política pública';
        } elseif ($roleNorm === '') {
            $colMetaA = 'Asesoría o SAFER';
            $colMetaB = 'AT o política pública';
        }

        $lines = [];
        $titleCsv = $vista === 'actividad'
            ? 'Seguimiento territorial AoAT — Actividades por municipio (tipo Actividad)'
            : 'Seguimiento territorial AoAT — Metas por municipio (' . $colMetaA . ' / ' . $colMetaB . ')';
        $lines[] = $esc($titleCsv);
        $lines[] = $esc('Fecha de exportación: ' . date('d/m/Y H:i'));
        $lines[] = $esc('Filtros aplicados: ' . $this->resolveSeguimientoFilterDescription($filters, $records));
        $lines[] = $esc('Cantidad de filas (territorios): ' . count($matrix['rows']));
        $lines[] = '';

        if ($vista === 'actividad') {
            $header = [
                'Municipio Nº (orden)',
                'Municipios en el grupo',
                'Subregión',
                'Municipio',
                'Profesional',
                'Rol',
            ];
            foreach ($matrix['months'] as $mh) {
                $mn = $this->monthNameEs((int) $mh['num']);
                $header[] = $mn . ' — Actividad';
            }
            $header[] = 'Total en el periodo';
        } else {
            $header = [
                'Municipio Nº (orden)',
                'Municipios en el grupo',
                'Subregión',
                'Municipio',
                'Profesional',
                'Rol',
                'Regla de meta mensual',
            ];
            foreach ($matrix['months'] as $mh) {
                $mn = $this->monthNameEs((int) $mh['num']);
                $header[] = $mn . ' — ' . $colMetaA;
                $header[] = $mn . ' — ' . $colMetaB;
                $header[] = $mn . ' — Total para meta';
            }
            $totalHdr = 'Total en el periodo';
            $hintCsv = trim((string) ($matrix['meta_total_column_hint'] ?? ''));
            if ($hintCsv !== '') {
                $totalHdr .= ' (' . $hintCsv . ')';
            }
            $header[] = $totalHdr;
            $metaHdr = 'Meta del periodo';
            $hintCsvMeta = trim((string) ($matrix['meta_period_column_hint'] ?? ''));
            if ($hintCsvMeta !== '') {
                $metaHdr .= ' (' . $hintCsvMeta . ')';
            }
            $header[] = $metaHdr;
            $header[] = 'Saldo';
            $header[] = 'Interpretación del saldo';
        }
        $lines[] = implode(';', array_map($esc, $header));

        foreach ($matrix['rows'] as $row) {
            $metaLabel = trim((string) ($row['meta_label'] ?? ''));
            $pl = (int) ($row['prof_line'] ?? 0);
            $pt = (int) ($row['prof_total_lines'] ?? 0);

            if ($vista === 'actividad') {
                $fields = [
                    $pl > 0 ? (string) $pl : '',
                    $pt > 0 ? (string) $pt : '',
                    (string) ($row['subregion'] ?? ''),
                    (string) ($row['municipality'] ?? ''),
                    (string) ($row['advisor_name'] ?? ''),
                    (string) ($row['professional_role_label'] ?? ''),
                ];
                foreach ($matrix['months'] as $mh) {
                    $k = $mh['key'];
                    $mc = is_array($row['month_cells'][$k] ?? null) ? $row['month_cells'][$k] : [];
                    $tot = (int) ($mc['count'] ?? ($row['months'][$k] ?? 0));
                    $fields[] = (string) $tot;
                }
                $fields[] = (string) ($row['consolidado_meta'] ?? '');
            } else {
                $fields = [
                    $pl > 0 ? (string) $pl : '',
                    $pt > 0 ? (string) $pt : '',
                    (string) ($row['subregion'] ?? ''),
                    (string) ($row['municipality'] ?? ''),
                    (string) ($row['advisor_name'] ?? ''),
                    (string) ($row['professional_role_label'] ?? ''),
                    $metaLabel,
                ];
                foreach ($matrix['months'] as $mh) {
                    $k = $mh['key'];
                    $mc = is_array($row['month_cells'][$k] ?? null) ? $row['month_cells'][$k] : [];
                    $tot = (int) ($mc['count'] ?? ($row['months'][$k] ?? 0));
                    $mode = (string) ($row['meta_breakdown_mode'] ?? 'standard');
                    if ($mode === 'abogado') {
                        $fields[] = (string) (int) ($mc['abogado_safer'] ?? 0);
                        $fields[] = (string) (int) ($mc['abogado_politica'] ?? 0);
                    } else {
                        $fields[] = (string) (int) ($mc['asesoria'] ?? 0);
                        $fields[] = (string) (int) ($mc['asistencia_tecnica'] ?? 0);
                    }
                    $fields[] = (string) $tot;
                }
                $fields[] = $this->seguimientoCsvTotalPeriodoMetaCell($row);
                $fields[] = $this->seguimientoCsvMetaPeriodoCell($row);
                $fields[] = $this->seguimientoCsvSaldoPeriodoCell($row);
                $fields[] = $this->interpretacionDebeCsvRow($row);
            }
            $lines[] = implode(';', array_map($esc, $fields));
        }

        $csv = "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n";
        $fn = 'seguimiento_aoat_' . date('Ymd_His') . '.csv';

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $fn . '"',
        ]);
    }

    public function exportPdf(Request $request): Response
    {
        $user = Auth::user();
        if ($user === null) {
            return Response::redirect('/login');
        }
        if (!$this->userCanAccessAoat($user)) {
            return Response::view('errors/403', ['pageTitle' => 'Acceso denegado'], 403);
        }

        $repo = new AoatRepository();
        $records = $this->fetchScopedRecords($user, $repo);
        $filters = $this->withSeguimientoViewerFlags($user, $this->parseFiltersFromRequest($request));
        $matrix = $this->service->buildMatrix($records, $filters);

        $html = $this->buildSeguimientoPdfHtml($matrix, $filters, $records);

        $pdfDocTitle = ($filters['vista'] ?? 'meta') === 'actividad'
            ? 'Seguimiento territorial AoAT — Actividades'
            : 'Seguimiento territorial AoAT — Metas';
        $pdfBinary = PdfService::renderHtml($html, 'L', $pdfDocTitle, true);
        $fn = 'seguimiento_aoat_' . date('Ymd_His') . '.pdf';

        return new Response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $fn . '"',
        ]);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function withSeguimientoViewerFlags(array $user, array $filters): array
    {
        $filters['include_global_monthly_targets'] = Auth::canViewAoatGlobalMonthlySummaries($user);

        return $filters;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseFiltersFromRequest(Request $request): array
    {
        return [
            'year' => max(2020, (int) $request->input('year', (int) date('Y'))),
            'period' => trim((string) $request->input('period', 'ene_jun')) === 'jul_dic' ? 'jul_dic' : 'ene_jun',
            'professional_user_id' => max(0, (int) $request->input('professional_user_id', 0)),
            'filter_month' => (int) $request->input('filter_month', 0),
            'state' => $this->normalizeAoatStateFilter((string) $request->input('state', '')),
            'role' => trim((string) $request->input('role', '')),
            'subregion' => trim((string) $request->input('subregion', '')),
            'municipalities' => MunicipalityListRequest::parse($request, 'municipality'),
            'vista' => trim((string) $request->input('vista', 'meta')) === 'actividad' ? 'actividad' : 'meta',
            'total_periodo' => $this->normalizeTotalPeriodoFilter((string) $request->input('total_periodo', '')),
        ];
    }

    private function normalizeAoatStateFilter(string $raw): string
    {
        $state = trim($raw);

        return in_array($state, ['Asignada', 'Devuelta', 'Realizado', 'Aprobada'], true) ? $state : '';
    }

    private function normalizeTotalPeriodoFilter(string $raw): string
    {
        $t = trim($raw);

        return match ($t) {
            'gt0', 'eq0', 'gt1' => $t,
            default => '',
        };
    }

    private function totalPeriodoFilterLabel(string $token): string
    {
        return match ($token) {
            'gt0' => 'Total del periodo: mayor que 0',
            'eq0' => 'Total del periodo: igual a 0',
            'gt1' => 'Total del periodo: mayor que 1',
            default => '',
        };
    }

    /**
     * @param array<int, array<string, mixed>> $records
     * @return array{subregions: list<string>, municipalities: list<string>, roles: list<array{value:string,label:string}>, professionals: list<array{value:string,label:string}>}
     */
    private function buildFilterOptions(array $records): array
    {
        $sub = [];
        $mun = [];
        $profById = [];
        foreach ($records as $r) {
            $s = trim((string) ($r['subregion'] ?? ''));
            $m = trim((string) ($r['municipality'] ?? ''));
            if ($s !== '') {
                $sub[$s] = true;
            }
            if ($m !== '') {
                $mun[$m] = true;
            }
            $uid = (int) ($r['user_id'] ?? 0);
            if ($uid > 0) {
                $nm = trim(trim((string) ($r['professional_name'] ?? '')) . ' ' . trim((string) ($r['professional_last_name'] ?? '')));
                if ($nm === '') {
                    $nm = 'Usuario #' . $uid;
                }
                $prev = $profById[$uid] ?? null;
                if ($prev === null) {
                    $profById[$uid] = $nm;
                } elseif (str_starts_with((string) $prev, 'Usuario #') && !str_starts_with($nm, 'Usuario #')) {
                    $profById[$uid] = $nm;
                }
            }
        }
        $subs = array_keys($sub);
        $muns = array_keys($mun);
        sort($subs, SORT_NATURAL | SORT_FLAG_CASE);
        sort($muns, SORT_NATURAL | SORT_FLAG_CASE);

        $professionals = [];
        foreach ($profById as $uid => $label) {
            $professionals[] = ['value' => (string) $uid, 'label' => (string) $label];
        }
        usort($professionals, static function (array $a, array $b): int {
            return strcasecmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
        });

        $roles = [
            ['value' => 'psicologo', 'label' => 'Psicólogo'],
            ['value' => 'profesional social', 'label' => 'Profesional social'],
            ['value' => 'abogado', 'label' => 'Abogado'],
            ['value' => 'medico', 'label' => 'Médico'],
        ];

        return [
            'subregions' => $subs,
            'municipalities' => $muns,
            'roles' => $roles,
            'professionals' => $professionals,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchScopedRecords(array $user, AoatRepository $repo): array
    {
        $roles = $user['roles'] ?? [];
        $canViewAll = Auth::canViewAllModuleRecords($user);
        $isSpecialist = in_array('especialista', $roles, true);
        $isCoordinator = in_array('coordinadora', $roles, true) || in_array('coordinador', $roles, true);
        $isAdmin = in_array('admin', $roles, true);

        if ($canViewAll) {
            $auditRoles = [];
            if ($isAdmin || $isCoordinator) {
                $auditRoles = [];
            } elseif ($isSpecialist) {
                $primaryRole = strtolower(trim((string) ($user['role'] ?? (($roles[0] ?? '') ?: ''))));
                if ($primaryRole === 'medico') {
                    $auditRoles = ['medico'];
                } elseif ($primaryRole === 'abogado') {
                    $auditRoles = ['abogado'];
                } elseif ($primaryRole === 'psicologo') {
                    $auditRoles = ['psicologo', 'profesional social', 'profesional_social'];
                } else {
                    $auditRoles = [];
                }
            }
            $records = $repo->findForAudit($auditRoles);
        } else {
            $records = $repo->findForUser((int) ($user['id'] ?? 0));
        }

        if (!$canViewAll) {
            $records = Auth::scopeRowsToOwnerUser($records, (int) ($user['id'] ?? 0));
        }

        return $records;
    }

    private function monthNameEs(int $month): string
    {
        $names = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
            7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];

        return $names[$month] ?? (string) $month;
    }

    /**
     * Texto legible para encabezados de exportación (filtros = mismos que la vista).
     *
     * @param array<string, mixed> $filters
     * @param array<int, array<string, mixed>> $records
     */
    private function resolveSeguimientoFilterDescription(array $filters, array $records = []): string
    {
        $parts = [];
        $y = (int) ($filters['year'] ?? (int) date('Y'));
        $parts[] = 'Año ' . $y;
        $period = ($filters['period'] ?? 'ene_jun') === 'jul_dic' ? 'Julio a diciembre' : 'Enero a junio';
        $parts[] = 'Corte ' . $period;

        $pid = (int) ($filters['professional_user_id'] ?? 0);
        if ($pid > 0) {
            $parts[] = 'Profesional: ' . $this->resolveProfessionalLabelFromRecords($pid, $records);
        }

        $fm = (int) ($filters['filter_month'] ?? 0);
        if ($fm >= 1 && $fm <= 12) {
            $parts[] = 'Solo mes calendario: ' . $this->monthNameEs($fm);
        }

        $state = $this->normalizeAoatStateFilter((string) ($filters['state'] ?? ''));
        if ($state !== '') {
            $parts[] = 'Estado AoAT: ' . $state;
        }

        $role = trim((string) ($filters['role'] ?? ''));
        if ($role !== '') {
            $parts[] = 'Rol: ' . $this->roleFilterLabel($role);
        }

        $sub = trim((string) ($filters['subregion'] ?? ''));
        if ($sub !== '') {
            $parts[] = 'Subregión: ' . $sub;
        }

        $muns = $filters['municipalities'] ?? [];
        if (is_array($muns) && $muns !== []) {
            $parts[] = 'Municipios: ' . implode(', ', $muns);
        }

        $vista = ($filters['vista'] ?? 'meta') === 'actividad' ? 'actividad' : 'meta';
        array_unshift($parts, $vista === 'actividad' ? 'Vista: Actividades (tipo Actividad)' : 'Vista: Asistencias técnicas y asesorías (metas)');

        $tp = $this->normalizeTotalPeriodoFilter((string) ($filters['total_periodo'] ?? ''));
        if ($tp !== '') {
            $parts[] = $this->totalPeriodoFilterLabel($tp);
        }

        return $parts !== [] ? implode(' | ', $parts) : 'Sin filtros adicionales (según permisos de tu usuario)';
    }

    /**
     * @param array<int, array<string, mixed>> $records
     */
    private function resolveProfessionalLabelFromRecords(int $userId, array $records): string
    {
        if ($userId <= 0) {
            return '';
        }
        foreach ($records as $r) {
            if ((int) ($r['user_id'] ?? 0) !== $userId) {
                continue;
            }
            $n = trim(trim((string) ($r['professional_name'] ?? '')) . ' ' . trim((string) ($r['professional_last_name'] ?? '')));
            if ($n !== '') {
                return $n;
            }
        }

        return 'Usuario #' . $userId;
    }

    private function roleFilterLabel(string $roleToken): string
    {
        $r = strtolower(trim(str_replace('_', ' ', $roleToken)));

        return match ($r) {
            'psicologo' => 'Psicólogo',
            'abogado' => 'Abogado',
            'medico' => 'Médico',
            'profesional social' => 'Profesional social',
            default => $roleToken,
        };
    }

    /**
     * Columna «Total en el periodo» en CSV (vista metas): abogados muestran SAFER y política pública acumulados.
     *
     * @param array<string, mixed> $row
     */
    private function seguimientoCsvTotalPeriodoMetaCell(array $row): string
    {
        $mode = (string) ($row['meta_breakdown_mode'] ?? 'standard');
        if ($mode === 'abogado') {
            $s = $row['consolidado_safer_periodo'] ?? null;
            $p = $row['consolidado_politica_periodo'] ?? null;
            if ($s !== null && $p !== null) {
                return 'SAFER ' . (string) (int) $s . ' · Pol.pública ' . (string) (int) $p;
            }
        }

        return (string) ($row['consolidado_meta'] ?? '');
    }

    /**
     * Columna «Meta del periodo» en CSV (vista metas): abogados muestran ambas metas acumuladas.
     *
     * @param array<string, mixed> $row
     */
    private function seguimientoCsvMetaPeriodoCell(array $row): string
    {
        $mode = (string) ($row['meta_breakdown_mode'] ?? 'standard');
        if ($mode === 'abogado') {
            $es = $row['expected_safer_periodo'] ?? null;
            $ep = $row['expected_politica_periodo'] ?? null;
            if ($es !== null && $ep !== null) {
                return 'SAFER ' . (string) (int) $es . ' · Pol.pública ' . (string) (int) $ep;
            }
        }

        return $row['expected'] === null ? '' : (string) (int) $row['expected'];
    }

    /**
     * Columna «Saldo» en CSV (vista metas): abogados muestran saldo por dimensión.
     *
     * @param array<string, mixed> $row
     */
    private function seguimientoCsvSaldoPeriodoCell(array $row): string
    {
        $mode = (string) ($row['meta_breakdown_mode'] ?? 'standard');
        if ($mode === 'abogado') {
            $ds = $row['debe_safer'] ?? null;
            $dp = $row['debe_politica'] ?? null;
            if ($ds !== null && $dp !== null) {
                return 'SAFER ' . (string) (int) $ds . ' · Pol.pública ' . (string) (int) $dp;
            }
        }

        return $row['debe'] === null ? '' : (string) (int) $row['debe'];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function interpretacionDebeCsvRow(array $row): string
    {
        $mode = (string) ($row['meta_breakdown_mode'] ?? 'standard');
        if ($mode === 'abogado') {
            $ds = $row['debe_safer'] ?? null;
            $dp = $row['debe_politica'] ?? null;
            if ($ds !== null && $dp !== null) {
                return 'SAFER: ' . $this->interpretacionDebeExport($ds)
                    . ' · Pol.pública: ' . $this->interpretacionDebeExport($dp);
            }
        }

        return $this->interpretacionDebeExport($row['debe'] ?? null);
    }

    private function interpretacionDebeExport(mixed $debe): string
    {
        if ($debe === null) {
            return 'Sin meta numérica para este rol';
        }
        $d = (int) $debe;
        if ($d < 0) {
            return 'Faltan ' . (string) abs($d) . ' actividades para la meta del periodo';
        }
        if ($d > 0) {
            return 'Va a favor con ' . (string) $d . ' actividades por encima de la meta del periodo';
        }

        return 'Cumple la meta del periodo';
    }

    /**
     * @param array<string, mixed> $matrix
     * @param array<string, mixed> $filters
     * @param array<int, array<string, mixed>> $records
     */
    private function buildSeguimientoPdfHtml(array $matrix, array $filters, array $records = []): string
    {
        $esc = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        $vista = ($filters['vista'] ?? 'meta') === 'actividad' ? 'actividad' : 'meta';
        $roleFilterRaw = trim((string) ($filters['role'] ?? ''));
        $roleNorm = preg_replace('/\s+/', ' ', strtolower(str_replace('_', ' ', $roleFilterRaw)));
        $abogadoPdfMeta = $vista !== 'actividad' && $roleNorm === 'abogado';
        $base = dirname(__DIR__, 2) . '/public/assets/img';
        $logoAntioquia = PdfImageHelper::imageDataUri($base . '/logoAntioquia.png');
        $logoHomo = PdfImageHelper::imageDataUri($base . '/logoHomo.png');

        $docSub = $vista === 'actividad'
            ? 'Actividades por municipio · Tipo «Actividad» (sin metas)'
            : ($abogadoPdfMeta
                ? 'Metas por municipio · Abogado (SAFER y política pública)'
                : 'Metas por municipio · Asesoría y Asistencia técnica');

        $headerLogos = '<td style="width:22%;">' . ($logoAntioquia !== '' ? '<img src="' . $esc($logoAntioquia) . '" alt="Gobernación de Antioquia" style="height:34px;width:auto;">' : '') . '</td>'
            . '<td style="width:56%;text-align:center;"><div class="doctitle">Seguimiento territorial AoAT</div>'
            . '<div class="docsub">' . $esc($docSub) . '</div></td>'
            . '<td style="width:22%;text-align:right;">' . ($logoHomo !== '' ? '<img src="' . $esc($logoHomo) . '" alt="HOMO" style="height:34px;width:auto;">' : '') . '</td>';

        $showGlobalMedLegend = $vista !== 'actividad' && !empty($filters['include_global_monthly_targets']);
        $legend = $vista === 'actividad'
            ? '<div class="legend"><strong>Cómo leer el cuadro:</strong> '
            . 'Solo se cuentan registros AoAT con tipo <em>Actividad</em>. '
            . 'No hay meta mensual ni saldo: cada celda es el total de ese mes.</div>'
            : ($abogadoPdfMeta
                ? '<div class="legend"><strong>Cómo leer el cuadro (abogado):</strong> '
                . 'Solo cuentan registros <em>Asesoría</em> o <em>Asistencia técnica</em> con respuesta útil en <strong>SAFER</strong> '
                . 'y/o en <strong>política pública</strong> (Mesa Municipal de Salud Mental o PPMSMYPA): al menos uno de esos dos bloques distinto de solo «No aplica». '
                . 'El <strong>total</strong> del mes no duplica el mismo registro. Las líneas muestran SAFER y política pública (un registro puede aparecer en ambas). '
                . '<strong>Meta del periodo y saldo</strong> se detallan por SAFER y política pública según los valores configurados en Administración · Metas AoAT. '
                . '<strong>Saldo:</strong> negativo = faltan registros en esa dimensión; cero = cumple; positivo = va a favor.</div>'
                : '<div class="legend"><strong>Cómo leer el cuadro:</strong> '
                . 'Solo cuentan actividades registradas como <em>Asesoría</em> o <em>Asistencia técnica</em>. '
                . 'En cada mes verás tres líneas: asesorías realizadas, asistencias técnicas realizadas y el <strong>total</strong> frente a la meta. '
                . ($showGlobalMedLegend
                    ? 'Psicología y Derecho pueden cambiar por tramo del año; Medicina usa una meta global mensual entre todos. '
                    : 'Psicología y Derecho pueden cambiar por tramo del año. ')
                . '<strong>Saldo al final:</strong> negativo = faltan actividades; cero = cumple la meta; positivo = va a favor, por encima de la meta del periodo mostrado.</div>');

        $filterBlock = '<div class="meta"><p><strong>Fecha de exportación:</strong> ' . $esc(date('d/m/Y H:i')) . '</p>'
            . '<p><strong>Filtros aplicados:</strong> ' . $esc($this->resolveSeguimientoFilterDescription($filters, $records)) . '</p>'
            . '<p><strong>Cantidad de filas (territorios):</strong> ' . $esc((string) count($matrix['rows'] ?? [])) . '</p></div>';

        $metaTotalHintPdf = trim((string) ($matrix['meta_total_column_hint'] ?? ''));
        $metaPeriodHintPdf = trim((string) ($matrix['meta_period_column_hint'] ?? ''));

        $thead = '<tr>'
            . '<th>Nº mun.</th><th>Subregión</th><th>Municipio</th><th>Profesional</th><th>Rol</th>';
        if ($vista !== 'actividad') {
            $thead .= '<th>Meta/mes</th>';
        }
        foreach ($matrix['months'] ?? [] as $mh) {
            $label = $this->monthNameEs((int) $mh['num']);
            $thead .= '<th>' . $esc($label) . '</th>';
        }
        $thead .= '<th>Total periodo';
        if ($vista !== 'actividad' && $metaTotalHintPdf !== '') {
            $thead .= '<br><span style="font-size:6px;font-weight:normal;line-height:1.15;">' . $esc($metaTotalHintPdf) . '</span>';
        }
        $thead .= '</th>';
        if ($vista !== 'actividad') {
            $thead .= '<th>Meta periodo';
            if ($metaPeriodHintPdf !== '') {
                $thead .= '<br><span style="font-size:6px;font-weight:normal;line-height:1.15;">' . $esc($metaPeriodHintPdf) . '</span>';
            }
            $thead .= '</th><th>Saldo</th>';
        }
        $thead .= '</tr>';

        $rowsHtml = '';
        foreach ($matrix['rows'] ?? [] as $row) {
            $pl = (int) ($row['prof_line'] ?? 0);
            $pt = (int) ($row['prof_total_lines'] ?? 0);
            $numLabel = $pl > 0 && $pt > 0 ? ($pl . ' de ' . $pt) : '—';
            $mmStr = trim((string) ($row['meta_label'] ?? ''));
            if ($mmStr === '') {
                $mmStr = '—';
            }

            $rowsHtml .= '<tr>'
                . '<td style="text-align:center;">' . $esc($numLabel) . '</td>'
                . '<td>' . $esc((string) ($row['subregion'] ?? '')) . '</td>'
                . '<td>' . $esc((string) ($row['municipality'] ?? '')) . '</td>'
                . '<td>' . $esc((string) ($row['advisor_name'] ?? '')) . '</td>'
                . '<td>' . $esc((string) ($row['professional_role_label'] ?? '')) . '</td>';
            if ($vista !== 'actividad') {
                $rowsHtml .= '<td style="text-align:center;">' . $esc($mmStr) . '</td>';
            }

            $modePdfRow = (string) ($row['meta_breakdown_mode'] ?? 'standard');

            foreach ($matrix['months'] ?? [] as $mh) {
                $k = $mh['key'];
                $mc = is_array($row['month_cells'][$k] ?? null) ? $row['month_cells'][$k] : [];
                $tot = (int) ($mc['count'] ?? ($row['months'][$k] ?? 0));
                if ($vista === 'actividad') {
                    $rowsHtml .= '<td class="monthcell" style="text-align:center;"><strong>' . $tot . '</strong></td>';
                } else {
                    if ($modePdfRow === 'abogado') {
                        $s = (int) ($mc['abogado_safer'] ?? 0);
                        $p = (int) ($mc['abogado_politica'] ?? 0);
                        $rowsHtml .= '<td class="monthcell">'
                            . '<div>SAFER: <strong>' . $s . '</strong></div>'
                            . '<div>Pol. pública: <strong>' . $p . '</strong></div>'
                            . '<div class="monthtot">Total: <strong>' . $tot . '</strong></div>'
                            . '</td>';
                    } else {
                        $a = (int) ($mc['asesoria'] ?? 0);
                        $at = (int) ($mc['asistencia_tecnica'] ?? 0);
                        $rowsHtml .= '<td class="monthcell">'
                            . '<div>Asesorías: <strong>' . $a . '</strong></div>'
                            . '<div>Asist. técnica: <strong>' . $at . '</strong></div>'
                            . '<div class="monthtot">Total: <strong>' . $tot . '</strong></div>'
                            . '</td>';
                    }
                }
            }

            $debe = $row['debe'] ?? null;
            $debeStr = $debe === null ? '—' : (string) (int) $debe;
            $totalPeriodoInner = '';
            if ($vista === 'actividad') {
                $totalPeriodoInner = (string) (int) ($row['consolidado_meta'] ?? 0);
            } else {
                if ($modePdfRow === 'abogado') {
                    $sp = $row['consolidado_safer_periodo'] ?? null;
                    $pp = $row['consolidado_politica_periodo'] ?? null;
                    if ($sp !== null && $pp !== null) {
                        $totalPeriodoInner = '<div>SAFER: <strong>' . (string) (int) $sp . '</strong></div>'
                            . '<div>Pol. pública: <strong>' . (string) (int) $pp . '</strong></div>';
                    }
                }
                if ($totalPeriodoInner === '') {
                    $totalPeriodoInner = (string) (int) ($row['consolidado_meta'] ?? 0);
                }
            }
            $rowsHtml .= '<td style="text-align:center;">' . $totalPeriodoInner . '</td>';
            if ($vista !== 'actividad') {
                $metaPeriodoInner = '—';
                if ($modePdfRow === 'abogado') {
                    $es = $row['expected_safer_periodo'] ?? null;
                    $ep = $row['expected_politica_periodo'] ?? null;
                    if ($es !== null && $ep !== null) {
                        $metaPeriodoInner = '<div>Meta SAFER: <strong>' . (string) (int) $es . '</strong></div>'
                            . '<div>Meta Pol.pública: <strong>' . (string) (int) $ep . '</strong></div>';
                    }
                }
                if ($metaPeriodoInner === '—' && ($row['expected'] ?? null) !== null) {
                    $metaPeriodoInner = (string) (int) $row['expected'];
                }

                $saldoInner = $esc($debeStr);
                if ($modePdfRow === 'abogado') {
                    $ds = $row['debe_safer'] ?? null;
                    $dp = $row['debe_politica'] ?? null;
                    if ($ds !== null && $dp !== null) {
                        $saldoInner = '<div>SAFER: <strong>' . (string) (int) $ds . '</strong></div>'
                            . '<div>Pol.pública: <strong>' . (string) (int) $dp . '</strong></div>';
                    }
                }

                $rowsHtml .= '<td style="text-align:center;">' . $metaPeriodoInner . '</td>'
                    . '<td style="text-align:center;">' . $saldoInner . '</td>';
            }
            $rowsHtml .= '</tr>';
        }

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Seguimiento territorial AoAT</title><style>'
            . 'body{font-family:sans-serif;color:#203246;font-size:8px;margin:8px;}'
            . '.header{width:100%;border-collapse:collapse;margin-bottom:6px;}'
            . '.doctitle{font-size:14px;font-weight:700;color:#214f43;}'
            . '.docsub{font-size:8px;color:#58708b;margin-top:2px;}'
            . '.meta{margin:0 0 8px;padding:6px 8px;background:#f4f8fc;border:1px solid #d8e3ef;font-size:8px;line-height:1.35;}'
            . '.meta p{margin:0 0 3px;}'
            . '.legend{margin:0 0 8px;padding:6px 8px;background:#f8faf6;border:1px solid #d5e8dc;font-size:7.5px;line-height:1.35;color:#334155;}'
            . '.report{width:100%;border-collapse:collapse;table-layout:fixed;}'
            . '.report th{background:#2f6b57;color:#ffffff;border:1px solid #1f4a3c;padding:4px 2px;text-align:center;font-size:7px;vertical-align:middle;}'
            . '.report td{border:1px solid #d7e1ec;padding:3px 2px;vertical-align:top;font-size:6.5px;word-wrap:break-word;}'
            . '.report td.monthcell{text-align:left;line-height:1.25;}'
            . '.monthtot{color:#1a4d3f;margin-top:2px;font-size:6.5px;}'
            . '.footer{margin-top:8px;font-size:7px;color:#64748b;text-align:right;}'
            . '</style></head><body>'
            . '<table class="header"><tr>' . $headerLogos . '</tr></table>'
            . $legend
            . $filterBlock
            . '<table class="report"><thead>' . $thead . '</thead><tbody>' . $rowsHtml . '</tbody></table>'
            . '<p class="footer">Documento generado automáticamente · Equipo de Promoción y Prevención · Acción en Territorio</p>'
            . '</body></html>';
    }

    private function userCanAccessAoat(array $user): bool
    {
        $roles = $user['roles'] ?? [];
        $allowed = ['abogado', 'medico', 'psicologo', 'profesional social', 'profesional_social', 'admin', 'especialista', 'coordinadora', 'coordinador'];

        return (bool) array_intersect($allowed, $roles);
    }
}
