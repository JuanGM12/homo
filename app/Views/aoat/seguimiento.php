<?php
/** @var array $filterOptions */
/** @var array $initialMeta */
use App\Services\Auth;

$user = Auth::user();
$canViewAll = Auth::canViewAllModuleRecords($user);
$y0 = (int) date('Y');
$period0 = (int) date('n') <= 6 ? 'ene_jun' : 'jul_dic';
$fo = $filterOptions ?? ['subregions' => [], 'municipalities' => [], 'roles' => [], 'professionals' => []];
$filterDefaultsJs = [
    'year' => $y0,
    'period' => $period0,
    'professional_user_id' => '0',
    'filter_month' => '0',
    'state' => '',
    'role' => '',
    'subregion' => '',
    'municipalities' => [],
    'vista' => 'meta',
    'total_periodo' => '',
];
?>

<section class="mt-4 mb-5 aoat-seguimiento-page aoat-seg-compact">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb" class="mb-2">
                <ol class="breadcrumb small mb-0">
                    <li class="breadcrumb-item"><a href="/aoat">AoAT</a></li>
                    <li class="breadcrumb-item active">Seguimiento territorial</li>
                </ol>
            </nav>
            <h1 class="section-title mb-1 aoat-seg-page-title" id="aoat-seg-page-title">Seguimiento territorial · metas AoAT</h1>
            <p class="section-subtitle mb-0 text-body-secondary aoat-seg-page-lead" id="aoat-seg-page-lead">
                <span id="aoat-seg-lead-main">Una fila por <strong>territorio y profesional</strong> (subregión + municipio + quien registra) según registros AoAT. Cada mes: total que cuenta para la meta (A+AT); abajo el detalle.</span>
                <?php if (!$canViewAll): ?>
                    <span class="d-block mt-1">Solo tus registros.</span>
                <?php elseif (Auth::hasRole('especialista')): ?>
                    <span class="d-block mt-1">Alcance según auditoría.</span>
                <?php endif; ?>
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <a href="#" class="btn btn-outline-success btn-sm" id="aoat-seg-export-csv"><i class="bi bi-filetype-csv me-1"></i>Excel (CSV)</a>
            <a href="#" class="btn btn-outline-primary btn-sm" id="aoat-seg-export-pdf" target="_blank"><i class="bi bi-file-pdf me-1"></i>PDF</a>
            <span class="small text-muted d-none d-md-inline">Incluyen filtros activos, textos claros y cabecera institucional (PDF).</span>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4 aoat-seg-filters-card">
        <div class="card-body p-3">
            <h2 class="h6 fw-semibold mb-3"><i class="bi bi-funnel me-2 text-secondary"></i>Filtros</h2>
            <form id="aoat-seg-filters" class="aoat-seg-filters-form" autocomplete="off" data-aoat-seg-filters>
                <div class="row g-3 align-items-start">
                    <div class="col-12 col-lg-4">
                        <label class="form-label small text-muted mb-1">Qué contar (AoAT)</label>
                        <select name="vista" class="form-select form-select-sm" id="aoat-seg-vista">
                            <option value="meta">Asistencias técnicas y asesorías</option>
                            <option value="actividad">Actividades</option>
                        </select>
                    </div>
                    <div class="col-6 col-lg-2">
                        <label class="form-label small text-muted mb-1">Año</label>
                        <input type="number" name="year" class="form-control form-control-sm" min="2020" max="2100" value="<?= $y0 ?>">
                    </div>
                    <div class="col-6 col-lg-2">
                        <label class="form-label small text-muted mb-1">Corte</label>
                        <select name="period" class="form-select form-select-sm">
                            <option value="ene_jun" <?= $period0 === 'ene_jun' ? 'selected' : '' ?>>Enero – Junio</option>
                            <option value="jul_dic" <?= $period0 === 'jul_dic' ? 'selected' : '' ?>>Julio – Diciembre</option>
                        </select>
                    </div>
                    <div class="col-12 col-lg-4">
                        <label class="form-label small text-muted mb-1">Profesional</label>
                        <select name="professional_user_id" class="form-select form-select-sm">
                            <option value="0">Todos</option>
                            <?php foreach ($fo['professionals'] ?? [] as $pr): ?>
                                <option value="<?= htmlspecialchars((string) ($pr['value'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($pr['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <p class="form-text small text-muted lh-sm mb-0 aoat-seg-filters-hint">Son dos vistas excluyentes: metas (A+AT) o solo registros tipo «Actividad».</p>

                <div class="row g-3 align-items-start mt-1">
                    <div class="col-6 col-lg-2">
                        <label class="form-label small text-muted mb-1">Mes (opcional)</label>
                        <select name="filter_month" class="form-select form-select-sm">
                            <option value="0">Todos los meses del corte</option>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>"><?= ['','Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'][$m] ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-6 col-lg-2">
                        <label class="form-label small text-muted mb-1">Estado AoAT</label>
                        <select name="state" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <option value="Asignada">Asignada</option>
                            <option value="Devuelta">Devuelta</option>
                            <option value="Realizado">Realizado</option>
                            <option value="Aprobada">Aprobada</option>
                        </select>
                    </div>
                    <div class="col-6 col-lg-2">
                        <label class="form-label small text-muted mb-1" id="aoat-seg-role-label">Rol (meta)</label>
                        <select name="role" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <?php foreach ($fo['roles'] ?? [] as $ro): ?>
                                <option value="<?= htmlspecialchars((string) ($ro['value'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($ro['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-lg-2">
                        <label class="form-label small text-muted mb-1">Subregión</label>
                        <select name="subregion" class="form-select form-select-sm">
                            <option value="">Todas</option>
                            <?php foreach ($fo['subregions'] ?? [] as $s): ?>
                                <option value="<?= htmlspecialchars($s, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($s, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-lg-4 aoat-seg-muni-wrap">
                        <label class="form-label small text-muted mb-1">Municipio(s)</label>
                        <select
                            name="municipality[]"
                            id="aoat-seg-municipality"
                            class="form-select form-select-sm"
                            multiple
                            data-homo-static-multiselect="1"
                            data-homo-multi-empty-label="Todos los municipios"
                            data-homo-multi-word="municipios"
                            data-homo-multi-title="Elija uno o varios municipios"
                        >
                            <?php foreach ($fo['municipalities'] ?? [] as $mu): ?>
                                <option value="<?= htmlspecialchars($mu, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($mu, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-lg-2">
                        <label class="form-label small text-muted mb-1">Total del periodo</label>
                        <select name="total_periodo" class="form-select form-select-sm" id="aoat-seg-total-periodo" title="Según la columna Total (suma en el corte o mes filtrado)">
                            <option value="">Todos</option>
                            <option value="eq0">Igual a 0 (sin registros que cuenten)</option>
                            <option value="gt0">Mayor que 0</option>
                            <option value="gt1">Mayor que 1</option>
                        </select>
                    </div>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-12 d-flex flex-wrap justify-content-lg-end align-items-center gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="aoat-seg-clear">
                            <i class="bi bi-x-lg me-1"></i>Limpiar filtros
                        </button>
                        <span class="small text-muted" id="aoat-seg-status"></span>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="aoat-seg-toolbar rounded-4 px-3 py-3 mb-3 d-flex flex-wrap align-items-center justify-content-between gap-2" id="aoat-seg-toolbar-wrap">
        <div class="d-flex flex-wrap align-items-center gap-3 small" id="aoat-seg-legend-meta">
            <span class="fw-semibold text-body-secondary"><i class="bi bi-palette me-1"></i>Colores del mes</span>
            <span class="aoat-seg-chip"><span class="aoat-seg-swatch aoat-seg-swatch--ok"></span> Cumple meta</span>
            <span class="aoat-seg-chip"><span class="aoat-seg-swatch aoat-seg-swatch--warn"></span> Falta algo</span>
            <span class="aoat-seg-chip"><span class="aoat-seg-swatch aoat-seg-swatch--bad"></span> Cero (mes ya iniciado)</span>
            <span class="aoat-seg-chip"><span class="aoat-seg-swatch aoat-seg-swatch--upcoming"></span> Mes futuro (aún no aplica)</span>
            <span class="aoat-seg-chip"><span class="aoat-seg-swatch aoat-seg-swatch--na"></span> Sin meta</span>
        </div>
        <div class="d-none flex-wrap align-items-center gap-3 small" id="aoat-seg-legend-actividad">
            <span class="fw-semibold text-body-secondary"><i class="bi bi-circle-half me-1"></i>Vista actividades</span>
            <span class="aoat-seg-chip"><span class="aoat-seg-swatch aoat-seg-swatch--plain"></span> Total mensual (tipo «Actividad»)</span>
            <span class="text-muted">Sin meta ni colores de cumplimiento.</span>
        </div>
        <button class="btn btn-sm btn-link text-decoration-none p-0" type="button" data-bs-toggle="collapse" data-bs-target="#aoat-seg-readme-more" aria-expanded="false">
            Detalle de reglas <i class="bi bi-chevron-down"></i>
        </button>
    </div>
    <div class="collapse mb-4" id="aoat-seg-readme-more">
        <div class="card border-0 shadow-sm rounded-4 aoat-seg-readme">
            <div class="card-body p-3 small text-body-secondary">
                <div id="aoat-seg-readme-meta">
                    <p class="mb-2"><strong>Profesional social:</strong> no tiene meta A+AT en este seguimiento; en la vista «Asistencias técnicas y asesorías» no se muestran esas filas (sí en «Actividades» si aplica).</p>
                    <p class="mb-2"><strong>Meta:</strong> solo <strong>Asesoría</strong> y <strong>Asistencia técnica</strong>. Psicólogo/Abogado: <strong>2/mes</strong> de enero a marzo y <strong>3/mes</strong> desde abril. <strong>Médico:</strong> meta global mensual de <strong>8</strong> entre todos.</p>
                    <p class="mb-2"><strong>Total / Meta / Saldo:</strong> suma del periodo vs meta (meses × meta mensual). <strong>Saldo negativo</strong> = faltan actividades; <strong>saldo positivo</strong> = va a favor, por encima de la meta.</p>
                    <p class="mb-2"><strong>Colores:</strong> un mes <strong>posterior al mes calendario actual</strong> con total 0 se muestra en gris azulado (no es un incumplimiento: ese mes aún no «cuenta» en la práctica).</p>
                    <p class="mb-0"><strong>Municipios:</strong> aparecen si ya hay al menos un registro AoAT en esa subregión y municipio (según rol y filtros).</p>
                </div>
                <div id="aoat-seg-readme-actividad" class="d-none">
                    <p class="mb-2"><strong>Actividades:</strong> aquí solo se cuentan los registros AoAT cuyo campo «Actividad que realizó» es <strong>Actividad</strong> (no se mezclan con asesorías ni asistencias técnicas).</p>
                    <p class="mb-2"><strong>Sin metas:</strong> no hay columnas Meta ni Saldo; el total del periodo es la suma de esos registros por municipio y mes.</p>
                    <p class="mb-0"><strong>Misma grilla territorial:</strong> las filas siguen saliendo del historial AoAT del profesional en cada municipio (igual que en la vista de metas).</p>
                </div>
            </div>
        </div>
    </div>

    <div id="aoat-seg-global-targets" class="mb-3"></div>

    <div class="card border-0 shadow-sm rounded-4 aoat-seg-card">
        <div class="table-responsive aoat-seg-table-wrap">
            <table class="table table-sm table-hover align-middle mb-0 aoat-seg-table" id="aoat-seg-table">
                <thead class="table-light" id="aoat-seg-thead"></thead>
                <tbody id="aoat-seg-tbody"></tbody>
            </table>
        </div>
        <p class="aoat-seg-scroll-hint small text-muted d-lg-none text-center px-3 py-2 mb-0 border-top border-light">
            <i class="bi bi-arrow-left-right me-1" aria-hidden="true"></i>
            Desliza horizontalmente para ver todos los meses y totales.
        </p>
        <div class="card-body border-top text-center text-muted small py-5 d-none" id="aoat-seg-empty">
            No hay filas para los filtros seleccionados (o aún no hay registros AoAT que definan territorios).
        </div>
    </div>
</section>

<script>
(function () {
    var STORAGE_KEY = 'homo_aoat_seguimiento_filters_v6';
    var FILTER_DEFAULTS = <?= json_encode($filterDefaultsJs, JSON_UNESCAPED_UNICODE) ?>;
    var initial = <?= json_encode($initialMeta ?? ['months' => [], 'rows' => [], 'vista' => 'meta'], JSON_UNESCAPED_UNICODE) ?>;

    function monthBubbleTitle(tier) {
        switch (tier) {
            case 'ok': return 'Cumple la meta este mes (A+AT)';
            case 'warn': return 'Hay actividades pero aún no alcanza la meta del mes';
            case 'bad': return 'Sin actividades que cuenten para la meta en este mes (ya iniciado)';
            case 'upcoming': return 'Mes calendario futuro: aún no aplica exigencia de registros';
            case 'na': return 'Sin meta numérica mensual para este rol';
            case 'plain': return 'Registros tipo «Actividad» en este mes (sin meta)';
            default: return 'Total A+AT que cuenta para la meta este mes';
        }
    }

    function monthCellHtml(mc, vista) {
        var a = mc && mc.asesoria != null ? mc.asesoria : 0;
        var at = mc && mc.asistencia_tecnica != null ? mc.asistencia_tecnica : 0;
        var total = mc && mc.count != null ? mc.count : 0;
        var tier = mc && mc.tier ? mc.tier : 'na';
        if (vista === 'actividad') {
            return (
                '<div class="aoat-seg-month-visual">' +
                '<div class="aoat-seg-month-bubble aoat-seg-month-bubble--plain" title="' + escapeHtml(monthBubbleTitle('plain')) + '">' + total + '</div>' +
                '<div class="aoat-seg-month-mini aoat-seg-month-mini--act">Actividad</div>' +
                '</div>'
            );
        }
        return (
            '<div class="aoat-seg-month-visual">' +
            '<div class="aoat-seg-month-bubble aoat-seg-month-bubble--' + tier + '" title="' + escapeHtml(monthBubbleTitle(tier)) + '">' + total + '</div>' +
            '<div class="aoat-seg-month-mini">A' + a + ' · AT' + at + '</div>' +
            '</div>'
        );
    }

    function aggregateRows(rows, months, vista) {
        var summary = {
            territories: rows.length,
            consolidado_meta: 0,
            expected: vista === 'meta' ? 0 : null,
            debe: vista === 'meta' ? 0 : null,
            hasExpected: false,
            month_cells: {}
        };

        months.forEach(function (m) {
            summary.month_cells[m.key] = {
                count: 0,
                asesoria: 0,
                asistencia_tecnica: 0,
                tier: vista === 'actividad' ? 'plain' : 'na'
            };
        });

        rows.forEach(function (row) {
            summary.consolidado_meta += Number(row.consolidado_meta || 0);
            if (vista === 'meta') {
                if (row.expected !== null && row.expected !== undefined) {
                    summary.expected += Number(row.expected || 0);
                    summary.hasExpected = true;
                }
                if (row.debe !== null && row.debe !== undefined) {
                    summary.debe += Number(row.debe || 0);
                }
            }

            months.forEach(function (m) {
                var source = row.month_cells && row.month_cells[m.key] ? row.month_cells[m.key] : null;
                var target = summary.month_cells[m.key];
                target.count += Number(source && source.count != null ? source.count : 0);
                target.asesoria += Number(source && source.asesoria != null ? source.asesoria : 0);
                target.asistencia_tecnica += Number(source && source.asistencia_tecnica != null ? source.asistencia_tecnica : 0);
            });
        });

        if (vista === 'meta' && !summary.hasExpected) {
            summary.expected = null;
            summary.debe = null;
        }

        return summary;
    }

    function buildSummaryRow(summary, months, vista, type, label, detail) {
        var tr = document.createElement('tr');
        tr.className = 'aoat-seg-summary-row aoat-seg-summary-row--' + type;

        tr.innerHTML =
            '<td class="aoat-seg-sticky aoat-seg-td-item aoat-seg-summary-sticky">' +
                '<span class="aoat-seg-item-big">Σ</span>' +
                '<span class="aoat-seg-item-hint">' + escapeHtml(type === 'group' ? 'subtotal' : 'total') + '</span>' +
            '</td>' +
            '<td class="aoat-seg-sticky aoat-seg-td-place aoat-seg-summary-sticky">' +
                '<div class="aoat-seg-muni fw-semibold">' + escapeHtml(label) + '</div>' +
                '<div class="aoat-seg-sub small">' + escapeHtml(detail) + '</div>' +
            '</td>' +
            '<td class="aoat-seg-sticky aoat-seg-td-prof aoat-seg-summary-sticky">' +
                '<div class="aoat-seg-prof-name fw-semibold">' + escapeHtml(type === 'group' ? 'Subtotal por profesional' : 'Total general') + '</div>' +
            '</td>' +
            '<td class="aoat-seg-td-role aoat-seg-summary-label">' +
                '<span class="aoat-seg-summary-pill">' + escapeHtml(vista === 'actividad' ? 'Totales de actividades' : 'Totales A+AT') + '</span>' +
            '</td>';

        months.forEach(function (m) {
            var td = document.createElement('td');
            td.className = 'text-center aoat-seg-month-td aoat-seg-summary-month';
            td.innerHTML = monthCellHtml(summary.month_cells[m.key], vista);
            tr.appendChild(td);
        });

        var tdCons = document.createElement('td');
        tdCons.className = 'text-center fw-semibold align-middle aoat-seg-summary-kpi';
        tdCons.innerHTML = '<span class="aoat-seg-kpi">' + String(summary.consolidado_meta) + '</span>';
        tr.appendChild(tdCons);

        if (vista === 'meta') {
            var tdMeta = document.createElement('td');
            tdMeta.className = 'text-center align-middle aoat-seg-summary-kpi';
            tdMeta.innerHTML = summary.expected === null ? '<span class="text-muted">—</span>' : '<span class="aoat-seg-kpi">' + String(summary.expected) + '</span>';
            tr.appendChild(tdMeta);

            var tdDebe = document.createElement('td');
            var debeTier = summary.debe === null ? 'na' : (summary.debe >= 0 ? 'ok' : 'bad');
            var debeNote = saldoNoteHtml(summary.debe);
            tdDebe.className = 'text-center fw-semibold aoat-seg-debe-td aoat-seg-debe--' + debeTier + ' aoat-seg-summary-kpi';
            tdDebe.innerHTML = '<span class="aoat-seg-kpi">' + (summary.debe === null ? '—' : String(summary.debe)) + '</span>' + debeNote;
            tr.appendChild(tdDebe);
        }

        return tr;
    }

    function renderGlobalTargets(matrix) {
        var wrap = document.getElementById('aoat-seg-global-targets');
        if (!wrap) return;
        var vista = matrix && matrix.vista === 'actividad' ? 'actividad' : 'meta';
        var items = matrix && Array.isArray(matrix.global_targets) ? matrix.global_targets : [];
        if (vista !== 'meta' || items.length === 0) {
            wrap.innerHTML = '';
            return;
        }

        wrap.innerHTML = items.map(function (item) {
            var months = Array.isArray(item.months) ? item.months : [];
            var chips = months.map(function (month) {
                var tier = month && month.tier ? String(month.tier) : 'na';
                var saldo = Number(month && month.saldo != null ? month.saldo : 0);
                var saldoText = saldo > 0 ? 'A favor ' + saldo : (saldo < 0 ? 'Falta ' + Math.abs(saldo) : 'Al dia');
                return '<div class="aoat-seg-global-chip aoat-seg-global-chip--' + escapeHtml(tier) + '">' +
                    '<div class="aoat-seg-global-chip-head">' + escapeHtml(String(month.label || '')) + '</div>' +
                    '<div class="aoat-seg-global-chip-main">' + escapeHtml(String(month.count || 0)) + ' / ' + escapeHtml(String(month.target || 0)) + '</div>' +
                    '<div class="aoat-seg-global-chip-sub">' + escapeHtml(saldoText) + '</div>' +
                '</div>';
            }).join('');

            return '<div class="card border-0 shadow-sm rounded-4 aoat-seg-global-card">' +
                '<div class="card-body p-3 p-lg-4">' +
                    '<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">' +
                        '<div>' +
                            '<div class="aoat-seg-global-title">' + escapeHtml(String(item.title || 'Meta global mensual')) + '</div>' +
                            '<div class="aoat-seg-global-copy">' + escapeHtml(String(item.description || '')) + '</div>' +
                        '</div>' +
                    '</div>' +
                    '<div class="aoat-seg-global-grid">' + chips + '</div>' +
                '</div>' +
            '</div>';
        }).join('');
    }

    function renderBody(matrix) {
        var vista = matrix.vista === 'actividad' ? 'actividad' : 'meta';
        var months = matrix.months || [];
        var rows = matrix.rows || [];
        var tbody = document.getElementById('aoat-seg-tbody');
        tbody.innerHTML = '';
        document.getElementById('aoat-seg-empty').classList.toggle('d-none', rows.length > 0);

        var currentUserId = null;
        var currentGroupRows = [];

        rows.forEach(function (row, idx) {
            var tr = document.createElement('tr');
            tr.className = 'aoat-seg-row aoat-seg-row--band' + (row.group_band === 1 ? '1' : '0');
            tr.setAttribute('data-user-id', String(row.user_id || ''));

            var pl = row.prof_line != null ? row.prof_line : (idx + 1);
            var pt = row.prof_total_lines != null ? row.prof_total_lines : 1;
            var metaShort = '';
            if (vista === 'meta') {
                metaShort = row.meta_label
                    ? '<span class="aoat-seg-meta-pill">' + escapeHtml(String(row.meta_label)) + '</span>'
                    : '<span class="aoat-seg-meta-pill">Sin meta</span>';
            }

            tr.innerHTML =
                '<td class="aoat-seg-sticky aoat-seg-td-item">' +
                    '<span class="aoat-seg-item-big">' + pl + '</span><span class="aoat-seg-item-of">/' + pt + '</span>' +
                    '<span class="aoat-seg-item-hint">orden</span>' +
                '</td>' +
                '<td class="aoat-seg-sticky aoat-seg-td-place">' +
                    '<div class="aoat-seg-muni text-uppercase fw-semibold">' + escapeHtml(row.municipality) + '</div>' +
                    '<div class="aoat-seg-sub small text-muted">' + escapeHtml(row.subregion) + '</div>' +
                '</td>' +
                '<td class="aoat-seg-sticky aoat-seg-td-prof">' +
                    '<div class="aoat-seg-prof-name fw-semibold text-body">' + escapeHtml(row.advisor_name) + '</div>' +
                '</td>' +
                '<td class="aoat-seg-td-role">' +
                    '<span class="aoat-seg-role-chip">' + escapeHtml(row.professional_role_label) + '</span>' + (metaShort ? ' ' + metaShort : '') +
                '</td>';

            months.forEach(function (m) {
                var mc = row.month_cells && row.month_cells[m.key] ? row.month_cells[m.key] : { count: 0, tier: 'na', asesoria: 0, asistencia_tecnica: 0 };
                var td = document.createElement('td');
                td.className = 'text-center aoat-seg-month-td';
                td.innerHTML = monthCellHtml(mc, vista);
                tr.appendChild(td);
            });

            var tdCons = document.createElement('td');
            tdCons.className = 'text-center fw-semibold align-middle';
            tdCons.innerHTML = '<span class="aoat-seg-kpi">' + String(row.consolidado_meta) + '</span>';
            tr.appendChild(tdCons);

            if (vista === 'meta') {
                var tdMeta = document.createElement('td');
                tdMeta.className = 'text-center align-middle';
                tdMeta.innerHTML = row.expected === null ? '<span class="text-muted">—</span>' : '<span class="aoat-seg-kpi">' + String(row.expected) + '</span>';
                tr.appendChild(tdMeta);

                var tdDebe = document.createElement('td');
                tdDebe.className = 'text-center fw-semibold aoat-seg-debe-td aoat-seg-debe--' + (row.debe_tier || 'na');
                var debeVal = row.debe === null ? '—' : String(row.debe);
                var debeNote = saldoNoteHtml(row.debe);
                tdDebe.innerHTML = '<span class="aoat-seg-kpi">' + debeVal + '</span>' + debeNote;
                tr.appendChild(tdDebe);
            }

            tbody.appendChild(tr);

            if (currentUserId === null) {
                currentUserId = row.user_id;
            }
            currentGroupRows.push(row);

            var nextRow = rows[idx + 1] || null;
            var groupEnds = !nextRow || String(nextRow.user_id || '') !== String(row.user_id || '');
            if (groupEnds) {
                var detail = currentGroupRows.length === 1
                    ? '1 territorio en el filtro actual'
                    : String(currentGroupRows.length) + ' territorios en el filtro actual';
                var subtotal = aggregateRows(currentGroupRows, months, vista);
                tbody.appendChild(buildSummaryRow(subtotal, months, vista, 'group', row.advisor_name || 'Profesional', detail));
                currentUserId = nextRow ? nextRow.user_id : null;
                currentGroupRows = [];
            }
        });

        if (rows.length > 0) {
            var grandTotal = aggregateRows(rows, months, vista);
            var grandDetail = rows.length === 1
                ? '1 fila visible en el cuadro'
                : String(rows.length) + ' filas visibles en el cuadro';
            tbody.appendChild(buildSummaryRow(grandTotal, months, vista, 'grand', 'Total general', grandDetail));
        }
    }

    function escapeHtml(s) {
        if (s === null || s === undefined) return '';
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    function saldoNoteHtml(value) {
        if (value === null || value === undefined || value === '') return '';
        var n = Number(value);
        if (!Number.isFinite(n)) return '';
        if (n < 0) return '<div class="aoat-seg-debe-note">Falta ' + Math.abs(n) + '</div>';
        if (n > 0) return '<div class="aoat-seg-debe-note">A favor ' + n + '</div>';
        return '<div class="aoat-seg-debe-note">Al día</div>';
    }

    function rebuildHead(months, vista) {
        var v = vista === 'actividad' ? 'actividad' : 'meta';
        var monthSub = v === 'actividad' ? 'Act.' : 'A+AT';
        var thead = document.getElementById('aoat-seg-thead');
        var tail = '<th class="text-center aoat-seg-th-kpi">Total</th>';
        if (v === 'meta') {
            tail += '<th class="text-center aoat-seg-th-kpi">Meta</th><th class="text-center aoat-seg-th-kpi">Saldo<div class="aoat-seg-th-hint">Falta / A favor</div></th>';
        }
        thead.innerHTML = '<tr>' +
            '<th class="aoat-seg-sticky"><span class="d-block">N.º</span><span class="aoat-seg-th-hint">en grupo</span></th>' +
            '<th class="aoat-seg-sticky">Territorio</th>' +
            '<th class="aoat-seg-sticky">Profesional</th>' +
            '<th>Rol</th>' +
            months.map(function (m) {
                return '<th class="text-center aoat-seg-month-col">' +
                    '<span class="d-block fw-semibold">' + escapeHtml(m.label) + '</span>' +
                    '<span class="aoat-seg-th-sub">' + monthSub + '</span></th>';
            }).join('') +
            tail +
            '</tr>';
    }

    function applyVistaChrome(vista) {
        var v = vista === 'actividad' ? 'actividad' : 'meta';
        var titleEl = document.getElementById('aoat-seg-page-title');
        var leadMain = document.getElementById('aoat-seg-lead-main');
        var roleLab = document.getElementById('aoat-seg-role-label');
        var legMeta = document.getElementById('aoat-seg-legend-meta');
        var legAct = document.getElementById('aoat-seg-legend-actividad');
        var readmeMeta = document.getElementById('aoat-seg-readme-meta');
        var readmeAct = document.getElementById('aoat-seg-readme-actividad');
        if (titleEl) {
            titleEl.textContent = v === 'actividad' ? 'Seguimiento territorial · actividades AoAT' : 'Seguimiento territorial · metas AoAT';
        }
        if (leadMain) {
            if (v === 'actividad') {
                leadMain.innerHTML = 'Una fila por <strong>territorio y profesional</strong> según registros AoAT. Cada mes: cantidad de registros con tipo <strong>Actividad</strong> (sin meta ni saldo).';
            } else {
                leadMain.innerHTML = 'Una fila por <strong>territorio y profesional</strong> (subregión + municipio + quien registra) según registros AoAT. Cada mes: total que cuenta para la meta (A+AT); abajo el detalle.';
            }
        }
        if (roleLab) {
            roleLab.textContent = v === 'actividad' ? 'Rol' : 'Rol (meta)';
        }
        if (legMeta && legAct) {
            if (v === 'actividad') {
                legMeta.classList.add('d-none');
                legAct.classList.remove('d-none');
                legAct.classList.add('d-flex');
            } else {
                legMeta.classList.remove('d-none');
                legAct.classList.add('d-none');
                legAct.classList.remove('d-flex');
            }
        }
        if (readmeMeta && readmeAct) {
            readmeMeta.classList.toggle('d-none', v === 'actividad');
            readmeAct.classList.toggle('d-none', v !== 'actividad');
        }
    }

    function saveFiltersToStorage() {
        try {
            var form = document.getElementById('aoat-seg-filters');
            if (!form) return;
            var muni = document.getElementById('aoat-seg-municipality');
            var municipalities = [];
            if (muni) {
                Array.from(muni.options).forEach(function (opt) {
                    if (opt.selected && opt.value) municipalities.push(opt.value);
                });
            }
            var profSel = form.querySelector('[name="professional_user_id"]');
            var vistaSel = form.querySelector('[name="vista"]');
            var totalSel = form.querySelector('[name="total_periodo"]');
            var payload = {
                year: String(form.querySelector('[name="year"]').value || FILTER_DEFAULTS.year),
                period: form.querySelector('[name="period"]').value,
                professional_user_id: profSel ? String(profSel.value || '0') : '0',
                filter_month: String(form.querySelector('[name="filter_month"]').value || '0'),
                state: String((form.querySelector('[name="state"]') || {}).value || ''),
                role: form.querySelector('[name="role"]').value,
                subregion: form.querySelector('[name="subregion"]').value,
                municipalities: municipalities,
                vista: vistaSel ? vistaSel.value : 'meta',
                total_periodo: totalSel ? String(totalSel.value || '') : ''
            };
            localStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
        } catch (e) { /* ignore */ }
    }

    function applyFiltersFromStorage() {
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) {
                raw = localStorage.getItem('homo_aoat_seguimiento_filters_v4');
            }
            if (!raw) {
                raw = localStorage.getItem('homo_aoat_seguimiento_filters_v2');
            }
            if (!raw) return false;
            var data = JSON.parse(raw);
            if (!data || typeof data !== 'object') return false;
            var form = document.getElementById('aoat-seg-filters');
            if (!form) return false;
            if (data.year != null) form.querySelector('[name="year"]').value = String(data.year);
            if (data.period != null) form.querySelector('[name="period"]').value = data.period;
            var profSel = form.querySelector('[name="professional_user_id"]');
            if (profSel) {
                if (data.professional_user_id != null) {
                    profSel.value = String(data.professional_user_id);
                } else if (data.state != null && data.state !== '') {
                    profSel.value = '0';
                }
            }
            if (data.filter_month != null) form.querySelector('[name="filter_month"]').value = String(data.filter_month);
            if (data.state != null) form.querySelector('[name="state"]').value = String(data.state);
            if (data.role != null) form.querySelector('[name="role"]').value = data.role;
            if (data.subregion != null) form.querySelector('[name="subregion"]').value = data.subregion;
            var vistaSel = form.querySelector('[name="vista"]');
            if (vistaSel && data.vista != null && (data.vista === 'meta' || data.vista === 'actividad')) {
                vistaSel.value = data.vista;
            }
            var totalSel = form.querySelector('[name="total_periodo"]');
            if (totalSel && data.total_periodo != null) {
                var tp = String(data.total_periodo);
                if (tp === 'gt0' || tp === 'eq0' || tp === 'gt1' || tp === '') {
                    totalSel.value = tp;
                }
            }
            var muni = document.getElementById('aoat-seg-municipality');
            if (muni && Array.isArray(data.municipalities)) {
                var set = {};
                data.municipalities.forEach(function (v) { if (v) set[String(v)] = true; });
                Array.from(muni.options).forEach(function (opt) {
                    opt.selected = !!set[opt.value];
                });
            }
            return true;
        } catch (e) {
            return false;
        }
    }

    var debounceTimer = null;
    function scheduleReload() {
        if (debounceTimer) clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function () {
            saveFiltersToStorage();
            loadData();
        }, 420);
    }

    function loadData() {
        var form = document.getElementById('aoat-seg-filters');
        var params = new URLSearchParams(new FormData(form));
        var status = document.getElementById('aoat-seg-status');
        status.textContent = 'Actualizando…';
        fetch('/aoat/seguimiento/datos?' + params.toString(), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                status.textContent = '';
                if (!j.ok) throw new Error(j.error || 'Error');
                var m = j.matrix;
                var vista = m.vista === 'actividad' ? 'actividad' : 'meta';
                applyVistaChrome(vista);
                rebuildHead(m.months || [], vista);
                renderGlobalTargets(m);
                renderBody(m);
            })
            .catch(function () {
                status.textContent = 'No se pudo cargar.';
            });
    }

    var form = document.getElementById('aoat-seg-filters');
    form.addEventListener('submit', function (e) { e.preventDefault(); });
    form.addEventListener('change', scheduleReload);
    form.addEventListener('input', function (e) {
        if (e.target && e.target.name === 'year') scheduleReload();
    });

    document.getElementById('aoat-seg-clear').addEventListener('click', function () {
        try {
            localStorage.removeItem(STORAGE_KEY);
        } catch (e) { /* ignore */ }
        window.location.reload();
    });

    document.getElementById('aoat-seg-export-csv').addEventListener('click', function (e) {
        e.preventDefault();
        saveFiltersToStorage();
        var params = new URLSearchParams(new FormData(form));
        window.location.href = '/aoat/seguimiento/exportar-csv?' + params.toString();
    });
    document.getElementById('aoat-seg-export-pdf').addEventListener('click', function (e) {
        e.preventDefault();
        saveFiltersToStorage();
        var params = new URLSearchParams(new FormData(form));
        window.open('/aoat/seguimiento/exportar-pdf?' + params.toString(), '_blank');
    });

    var hadStorage = applyFiltersFromStorage();
    if (hadStorage) {
        var vs0 = document.getElementById('aoat-seg-vista');
        applyVistaChrome(vs0 && vs0.value === 'actividad' ? 'actividad' : 'meta');
        document.getElementById('aoat-seg-status').textContent = 'Cargando…';
        loadData();
    } else {
        var iv = initial.vista === 'actividad' ? 'actividad' : 'meta';
        applyVistaChrome(iv);
        rebuildHead(initial.months || [], iv);
        renderGlobalTargets(initial);
        renderBody(initial);
    }
})();
</script>
