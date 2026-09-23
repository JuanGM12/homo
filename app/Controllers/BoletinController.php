<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\BoletinEditionRepository;
use App\Services\Auth;
use App\Services\BoletinService;
use App\Services\Flash;
use App\Services\PdfImageHelper;
use App\Services\PdfService;
use App\Support\MunicipalityListRequest;

final class BoletinController
{
    private const ACCESS_ROLES = ['admin', 'coordinadora', 'coordinador'];

    public static function userMaySeeBoletin(?array $user): bool
    {
        if ($user === null) {
            return false;
        }

        $roles = array_map('strtolower', $user['roles'] ?? []);

        return (bool) array_intersect($roles, self::ACCESS_ROLES);
    }

    public function index(Request $request): Response
    {
        $user = Auth::user();
        if ($user === null) {
            return Response::redirect('/login');
        }
        if (!self::userMaySeeBoletin($user)) {
            return Response::view('errors/403', ['pageTitle' => 'Acceso denegado'], 403);
        }

        $filters = $this->parseFilters($request);
        $dashboard = (new BoletinService())->build($filters, $user);

        if ((string) $request->input('partial', '') === 'results') {
            $html = $this->renderDashboardPartial($dashboard, $filters);

            return Response::json(['html' => $html]);
        }

        return Response::view('boletin/index', [
            'pageTitle' => 'Boletín de resultados AoAT',
            'filters' => $filters,
            'dashboard' => $dashboard,
            'roleOptions' => BoletinService::ROLE_OPTIONS,
            'activityTypeOptions' => BoletinService::ACTIVITY_TYPE_OPTIONS,
            'topicOptions' => BoletinService::TOPIC_OPTIONS,
        ]);
    }

    public function exportPdf(Request $request): Response
    {
        $user = Auth::user();
        if ($user === null) {
            return Response::redirect('/login');
        }
        if (!self::userMaySeeBoletin($user)) {
            return Response::view('errors/403', ['pageTitle' => 'Acceso denegado'], 403);
        }

        $filters = $this->parseFilters($request);
        $isOfficial = $request->getMethod() === 'POST'
            && (string) $request->input('official', '') === '1';

        $edition = null;
        if ($isOfficial) {
            $from = BoletinService::sanitizeIsoDate(trim((string) $request->input('from_date', '')));
            $to = BoletinService::sanitizeIsoDate(trim((string) $request->input('to_date', '')));
            if ($from === '' || $to === '' || $from > $to) {
                Flash::set([
                    'type' => 'error',
                    'title' => 'Rango de fechas',
                    'message' => 'Para generar un boletín real debes indicar un rango de fechas válido.',
                ]);

                return Response::redirect('/boletin');
            }

            $filters['from_date'] = $from;
            $filters['to_date'] = $to;
            $edition = [
                'numero' => (new BoletinEditionRepository())->create((int) ($user['id'] ?? 0), $from, $to),
                'from_date' => $from,
                'to_date' => $to,
            ];
        }

        $dashboard = (new BoletinService())->build($filters, $user);
        $html = $this->buildPdfHtml($dashboard, $filters, $edition);

        $binary = PdfService::renderHtml($html, 'L', $edition !== null
            ? 'Boletín #' . $edition['numero']
            : 'Boletín de resultados AoAT', true);

        $filename = $edition !== null
            ? sprintf(
                'boletin_%d_%s_%s.pdf',
                (int) $edition['numero'],
                str_replace('-', '', (string) $edition['from_date']),
                str_replace('-', '', (string) $edition['to_date'])
            )
            : 'boletin_resultados_' . date('Ymd_His') . '.pdf';

        return new Response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseFilters(Request $request): array
    {
        return [
            'subregion' => trim((string) $request->input('subregion', '')),
            'municipalities' => MunicipalityListRequest::parse($request),
            'from_date' => BoletinService::sanitizeIsoDate(trim((string) $request->input('from_date', ''))),
            'to_date' => BoletinService::sanitizeIsoDate(trim((string) $request->input('to_date', ''))),
            'role' => strtolower(trim((string) $request->input('role', ''))),
            'professional_id' => (int) $request->input('professional_id', 0),
            'activity_type' => trim((string) $request->input('activity_type', '')),
            'tema' => trim((string) $request->input('tema', '')),
            'period_id' => (int) $request->input('period_id', 0),
        ];
    }

    /**
     * @param array<string, mixed> $dashboard
     * @param array<string, mixed> $filters
     */
    private function renderDashboardPartial(array $dashboard, array $filters): string
    {
        ob_start();
        require dirname(__DIR__) . '/Views/boletin/_dashboard.php';
        $html = ob_get_clean();

        return is_string($html) ? $html : '';
    }

    /**
     * @param array<string, mixed> $dashboard
     * @param array<string, mixed> $filters
     * @param array{numero:int,from_date:string,to_date:string}|null $edition
     */
    private function buildPdfHtml(array $dashboard, array $filters, ?array $edition = null): string
    {
        $esc = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        $base = dirname(__DIR__, 2) . '/public/assets/img';
        $logoAntioquia = PdfImageHelper::imageDataUri($base . '/logoAntioquia.png');
        $logoHomo = PdfImageHelper::imageDataUri($base . '/logoHomo.png');
        $kpis = is_array($dashboard['kpis'] ?? null) ? $dashboard['kpis'] : [];
        $benef = is_array($dashboard['beneficiarios'] ?? null) ? $dashboard['beneficiarios'] : [];
        $territory = is_array($dashboard['territory'] ?? null) ? $dashboard['territory'] : [];

        $formatLabel = static function (string $iso): string {
            $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $iso);

            return $dt instanceof \DateTimeImmutable ? $dt->format('d/m/Y') : $iso;
        };

        $filterBits = [];
        if (($filters['subregion'] ?? '') !== '') {
            $filterBits[] = 'Subregión: ' . (string) $filters['subregion'];
        }
        $munis = is_array($filters['municipalities'] ?? null) ? $filters['municipalities'] : [];
        if ($munis !== []) {
            $filterBits[] = 'Municipio: ' . implode(', ', $munis);
        }
        if ($edition === null && (($filters['from_date'] ?? '') !== '' || ($filters['to_date'] ?? '') !== '')) {
            $fromBit = (string) ($filters['from_date'] ?: '');
            $toBit = (string) ($filters['to_date'] ?: '');
            $filterBits[] = 'Fechas: '
                . ($fromBit !== '' ? $formatLabel($fromBit) : '…')
                . ' a '
                . ($toBit !== '' ? $formatLabel($toBit) : '…');
        }

        $title = $edition !== null
            ? 'Boletín #' . (int) $edition['numero']
            : 'Boletín de resultados · AoAT';
        $rangeHtml = '';
        if ($edition !== null) {
            $rangeHtml = '<div class="range">Del '
                . $esc($formatLabel((string) $edition['from_date']))
                . ' al '
                . $esc($formatLabel((string) $edition['to_date']))
                . '</div>';
        }
        $metaLine = $esc((string) ($dashboard['program_name'] ?? '')) . ' · Contrato No. ' . $esc((string) ($dashboard['contrato'] ?? ''));
        if ($edition !== null) {
            $metaLine = 'Boletín de resultados · AoAT · ' . $metaLine;
        }
        $corteLine = $edition !== null
            ? ''
            : '<div class="sub">Corte: ' . $esc((string) ($dashboard['cutoff_label'] ?? ''))
                . ($filterBits !== [] ? ' · ' . $esc(implode(' | ', $filterBits)) : '')
                . '</div>';
        $filtersLine = ($edition !== null && $filterBits !== [])
            ? '<div class="sub">' . $esc(implode(' | ', $filterBits)) . '</div>'
            : '';

        $byRole = is_array($dashboard['by_role'] ?? null) ? $dashboard['by_role'] : [];
        $byActivity = is_array($dashboard['by_activity'] ?? null) ? $dashboard['by_activity'] : [];
        $byMonth = is_array($dashboard['by_month'] ?? null) ? $dashboard['by_month'] : [];
        $kpiHtml = '';
        $kpiItems = [
            ['AoAT registradas', (int) ($kpis['aoat_total'] ?? 0), '#1f8a4c', ''],
            ['Asesoría', (int) ($kpis['asesoria'] ?? 0), '#0f766e', ''],
            ['Asistencia técnica (AT)', (int) ($kpis['at'] ?? 0), '#d97706', 'Cálculo: Nº de AoAT con tipo Asistencia técnica'],
            ['Actividad', (int) ($kpis['actividad'] ?? 0), '#6d28d9', ''],
            ['Beneficiarios', (int) ($benef['total'] ?? 0), '#1d4ed8', 'Listado de asistencia'],
            ['Personas únicas', (int) ($benef['unicos'] ?? 0), '#b45309', ''],
        ];
        foreach ($kpiItems as [$label, $value, $color, $hint]) {
            $kpiHtml .= '<td class="kpi" style="background:' . $color . ';">'
                . '<div class="k-lab">' . $esc($label) . '</div>'
                . '<div class="k-val">' . $esc((string) $value) . '</div>'
                . ($hint !== '' ? '<div class="k-hint">' . $esc($hint) . '</div>' : '')
                . '</td>';
        }

        $seriesCard = static function (string $title, array $rows) use ($esc): string {
            $html = '<table class="mini"><thead><tr><th>Concepto</th><th class="num">Total</th></tr></thead><tbody>';
            $sum = 0;
            if ($rows === []) {
                $html .= '<tr><td colspan="2">Sin datos en este recorte.</td></tr>';
            }
            foreach ($rows as $row) {
                $value = (int) ($row['value'] ?? 0);
                $sum += $value;
                $html .= '<tr><td>' . $esc((string) ($row['label'] ?? '')) . '</td><td class="num">' . $esc((string) $value) . '</td></tr>';
            }
            if ($rows !== []) {
                $html .= '<tr><th>Total</th><th class="num">' . $esc((string) $sum) . '</th></tr>';
            }

            return '<td class="card"><div class="card-title">' . $esc($title) . '</div>' . $html . '</tbody></table></td>';
        };

        $activityRows = '';
        $activitySum = 0;
        foreach ($byActivity as $row) {
            $value = (int) ($row['value'] ?? 0);
            $activitySum += $value;
            $activityRows .= '<tr><td>' . $esc((string) ($row['label'] ?? '')) . '</td><td class="num">' . $value . '</td></tr>';
        }
        if ($activityRows === '') {
            $activityRows = '<tr><td colspan="2">Sin datos en este recorte.</td></tr>';
        } else {
            $activityRows .= '<tr><th>Total general</th><th class="num">' . $activitySum . '</th></tr>';
        }

        $monthRows = '';
        $monthSum = 0;
        foreach ($byMonth as $row) {
            $monthSum += (int) ($row['total'] ?? 0);
            $monthRows .= '<tr>'
                . '<td>' . $esc((string) ($row['label'] ?? '')) . '</td>'
                . '<td class="num">' . (int) ($row['asesoria'] ?? 0) . '</td>'
                . '<td class="num">' . (int) ($row['at'] ?? 0) . '</td>'
                . '<td class="num">' . (int) ($row['actividad'] ?? 0) . '</td>'
                . '<td class="num">' . (int) ($row['total'] ?? 0) . '</td>'
                . '</tr>';
        }
        if ($monthRows === '') {
            $monthRows = '<tr><td colspan="5">Sin datos en este recorte.</td></tr>';
        } else {
            $monthRows .= '<tr><th>Total general</th><th colspan="3"></th><th class="num">' . $monthSum . '</th></tr>';
        }

        $tableRows = '';
        foreach ($territory as $row) {
            $tableRows .= '<tr>'
                . '<td>' . $esc((string) ($row['subregion'] ?? '')) . '</td>'
                . '<td>' . $esc((string) ($row['municipality'] ?? '')) . '</td>'
                . '<td class="num">' . (int) ($row['aoat'] ?? 0) . '</td>'
                . '<td class="num">' . (int) ($row['asesoria'] ?? 0) . '</td>'
                . '<td class="num">' . (int) ($row['at'] ?? 0) . '</td>'
                . '<td class="num">' . (int) ($row['actividad'] ?? 0) . '</td>'
                . '<td class="num">' . (int) ($row['listados'] ?? 0) . '</td>'
                . '<td class="num">' . (int) ($row['beneficiarios'] ?? 0) . '</td>'
                . '</tr>';
        }
        if ($tableRows === '') {
            $tableRows = '<tr><td colspan="8">Sin registros en el alcance actual.</td></tr>';
        }

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
            body{font-family:Arial,sans-serif;color:#203246;font-size:9px;margin:12px;}
            .head{width:100%;border-collapse:collapse;margin-bottom:8px;}
            .title{font-size:16px;font-weight:700;color:#214f43;}
            .range{font-size:13px;font-weight:700;color:#2f6b57;margin:3px 0;}
            .sub{font-size:10px;color:#58708b;}
            .meta{margin:0 0 8px;padding:7px 10px;background:#f4f8fc;border:1px solid #d8e3ef;}
            .meta .sub{margin:0;}
            .kpis{width:100%;border-collapse:separate;border-spacing:6px;margin:4px 0 10px;}
            .kpi{border:1px solid #1f4a3c;padding:8px 6px;text-align:center;color:#fff;}
            .k-lab{font-size:8px;text-transform:uppercase;letter-spacing:.03em;opacity:.92;}
            .k-val{font-size:16px;font-weight:700;margin-top:2px;}
            .k-hint{font-size:7px;margin-top:3px;opacity:.9;text-transform:none;letter-spacing:0;}
            .cards{width:100%;border-collapse:separate;border-spacing:7px;margin:0 0 6px;}
            .cards td.card{width:25%;vertical-align:top;background:#fff;border:1px solid #d7e1ec;padding:7px 8px;}
            .cards-2 td.card{width:50%;}
            .card-title{font-size:10px;font-weight:700;color:#214f43;margin:0 0 6px;}
            table.mini{width:100%;border-collapse:collapse;}
            table.mini th{background:#eef5f0;color:#1f2a24;border:1px solid #cfdad3;padding:3px 5px;text-align:left;font-size:8px;}
            table.mini td{border:1px solid #d9e2dd;padding:3px 5px;}
            .section-title{background:#2f6b57;color:#fff;font-weight:700;padding:5px 8px;font-size:10px;margin:8px 0 0;}
            table.grid{width:100%;border-collapse:collapse;}
            table.grid th{background:#2f6b57;color:#fff;border:1px solid #1f4a3c;padding:5px 6px;text-align:left;}
            table.grid td{border:1px solid #d7e1ec;padding:4px 6px;vertical-align:top;}
            table.grid tr:nth-child(even) td{background:#fbfdff;}
            .num{text-align:right;font-weight:700;}
            .footer{margin-top:10px;font-size:8px;color:#64748b;text-align:right;}
        </style></head><body>
            <table class="head"><tr>
                <td style="width:22%;">' . ($logoAntioquia !== '' ? '<img src="' . $esc($logoAntioquia) . '" style="height:36px;">' : '') . '</td>
                <td style="text-align:center;">
                    <div class="title">' . $esc($title) . '</div>
                    ' . $rangeHtml . '
                </td>
                <td style="width:22%;text-align:right;">' . ($logoHomo !== '' ? '<img src="' . $esc($logoHomo) . '" style="height:36px;">' : '') . '</td>
            </tr></table>
            <div class="meta">
                <div class="sub">' . $metaLine . '</div>
                ' . $corteLine . $filtersLine . '
            </div>
            <table class="kpis"><tr>' . $kpiHtml . '</tr></table>
            <table class="cards cards-2"><tr>
                <td class="card">
                    <div class="card-title">Totales por actividad realizada</div>
                    <table class="mini"><thead><tr><th>Concepto</th><th class="num">Total</th></tr></thead><tbody>' . $activityRows . '</tbody></table>
                </td>
                <td class="card">
                    <div class="card-title">Por mes según actividad que realizó</div>
                    <table class="mini"><thead><tr><th>Mes</th><th class="num">Asesoría</th><th class="num">AT</th><th class="num">Actividad</th><th class="num">Total</th></tr></thead><tbody>' . $monthRows . '</tbody></table>
                </td>
            </tr></table>
            <table class="cards"><tr>
                ' . $seriesCard('Por rol profesional', $byRole) . '
                ' . $seriesCard('Sexo', is_array($benef['sexo'] ?? null) ? $benef['sexo'] : []) . '
                ' . $seriesCard('Zona', is_array($benef['zona'] ?? null) ? $benef['zona'] : []) . '
                ' . $seriesCard('Etnia', is_array($benef['etnia'] ?? null) ? $benef['etnia'] : []) . '
            </tr></table>
            <table class="cards cards-2"><tr>
                ' . $seriesCard('Total según edad', is_array($benef['edad'] ?? null) ? $benef['edad'] : []) . '
                ' . $seriesCard('Grupo poblacional', is_array($benef['grupo_poblacional'] ?? null) ? $benef['grupo_poblacional'] : []) . '
            </tr></table>
            <div class="section-title">Tabla por subregión y municipio</div>
            <table class="grid"><thead><tr>
                <th>Subregión</th><th>Municipio</th><th>AoAT</th><th>Asesoría</th><th>AT</th><th>Actividad</th><th>Listados</th><th>Beneficiarios</th>
            </tr></thead><tbody>' . $tableRows . '</tbody></table>
            <p class="footer">Documento generado automáticamente desde la plataforma Equipo de Promoción y Prevención.</p>
        </body></html>';
    }
}
