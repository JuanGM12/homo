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
            $filterBits[] = 'Fechas: ' . (string) ($filters['from_date'] ?: '…') . ' a ' . (string) ($filters['to_date'] ?: '…');
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

        $kpiHtml = '';
        $kpiItems = [
            ['AoAT', (int) ($kpis['aoat_total'] ?? 0)],
            ['Asesoría', (int) ($kpis['asesoria'] ?? 0)],
            ['AT', (int) ($kpis['at'] ?? 0)],
            ['Actividad', (int) ($kpis['actividad'] ?? 0)],
            ['Beneficiarios', (int) ($benef['total'] ?? 0)],
            ['Personas únicas', (int) ($benef['unicos'] ?? 0)],
        ];
        foreach ($kpiItems as [$label, $value]) {
            $kpiHtml .= '<td class="kpi"><div class="k-lab">' . $esc($label) . '</div><div class="k-val">' . $esc((string) $value) . '</div></td>';
        }

        $seriesHtml = static function (string $title, array $rows) use ($esc): string {
            $html = '<h3>' . $esc($title) . '</h3><table class="mini">';
            foreach ($rows as $row) {
                $html .= '<tr><td>' . $esc((string) ($row['label'] ?? '')) . '</td><td class="num">' . $esc((string) (int) ($row['value'] ?? 0)) . '</td></tr>';
            }

            return $html . '</table>';
        };

        $tableRows = '';
        foreach ($territory as $row) {
            $tableRows .= '<tr>'
                . '<td>' . $esc((string) ($row['subregion'] ?? '')) . '</td>'
                . '<td>' . $esc((string) ($row['municipality'] ?? '')) . '</td>'
                . '<td class="num">' . (int) ($row['aoat'] ?? 0) . '</td>'
                . '<td class="num">' . (int) ($row['asesoria'] ?? 0) . '</td>'
                . '<td class="num">' . (int) ($row['at'] ?? 0) . '</td>'
                . '<td class="num">' . (int) ($row['actividad'] ?? 0) . '</td>'
                . '<td class="num">' . (int) ($row['beneficiarios'] ?? 0) . '</td>'
                . '</tr>';
        }
        if ($tableRows === '') {
            $tableRows = '<tr><td colspan="7">Sin registros en el alcance actual.</td></tr>';
        }

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
            body{font-family:Arial,sans-serif;color:#203246;font-size:9px;margin:12px;}
            .head{width:100%;border-collapse:collapse;margin-bottom:8px;}
            .title{font-size:16px;font-weight:700;color:#1f5c45;}
            .range{font-size:13px;font-weight:700;color:#1f5c45;margin:3px 0;}
            .sub{font-size:10px;color:#5b6d8f;}
            .kpis{width:100%;border-collapse:separate;border-spacing:6px;margin:8px 0;}
            .kpi{background:#f3f7f4;border:1px solid #d5e4db;padding:8px;text-align:center;}
            .k-lab{font-size:8px;text-transform:uppercase;color:#5b6d8f;}
            .k-val{font-size:16px;font-weight:700;color:#1f5c45;}
            table.grid{width:100%;border-collapse:collapse;margin-top:8px;}
            table.grid th{background:#1f5c45;color:#fff;padding:5px;text-align:left;}
            table.grid td{border:1px solid #d7e1ec;padding:4px;}
            table.mini{width:100%;border-collapse:collapse;margin-bottom:8px;}
            table.mini td{border-bottom:1px solid #e6eef4;padding:3px 0;}
            .num{text-align:right;font-weight:700;}
            .cols td{vertical-align:top;width:25%;padding-right:8px;}
        </style></head><body>
            <table class="head"><tr>
                <td style="width:22%;">' . ($logoAntioquia !== '' ? '<img src="' . $esc($logoAntioquia) . '" style="height:36px;">' : '') . '</td>
                <td style="text-align:center;">
                    <div class="title">' . $esc($title) . '</div>
                    ' . $rangeHtml . '
                    <div class="sub">' . $metaLine . '</div>
                    ' . $corteLine . $filtersLine . '
                </td>
                <td style="width:22%;text-align:right;">' . ($logoHomo !== '' ? '<img src="' . $esc($logoHomo) . '" style="height:36px;">' : '') . '</td>
            </tr></table>
            <table class="kpis"><tr>' . $kpiHtml . '</tr></table>
            <table class="cols"><tr>
                <td>' . $seriesHtml('Sexo', is_array($benef['sexo'] ?? null) ? $benef['sexo'] : []) . '</td>
                <td>' . $seriesHtml('Edad', is_array($benef['edad'] ?? null) ? $benef['edad'] : []) . '</td>
                <td>' . $seriesHtml('Etnia', is_array($benef['etnia'] ?? null) ? $benef['etnia'] : []) . '</td>
                <td>' . $seriesHtml('Zona', is_array($benef['zona'] ?? null) ? $benef['zona'] : []) . '</td>
            </tr></table>
            ' . $seriesHtml('Grupo poblacional', is_array($benef['grupo_poblacional'] ?? null) ? $benef['grupo_poblacional'] : []) . '
            <h3>Tabla por subregión y municipio</h3>
            <table class="grid"><thead><tr>
                <th>Subregión</th><th>Municipio</th><th>AoAT</th><th>Asesoría</th><th>AT</th><th>Actividad</th><th>Beneficiarios</th>
            </tr></thead><tbody>' . $tableRows . '</tbody></table>
        </body></html>';
    }
}
