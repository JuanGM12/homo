<?php
/** @var array $records */
/** @var array $advisors */
/** @var array $filters */
/** @var array $pagination */
/** @var bool $canFilterAdvisor */
/** @var string $activeTab */

$totalItems  = (int) ($pagination['total_items'] ?? 0);
$currentPage = (int) ($pagination['current_page'] ?? 1);
$totalPages  = (int) ($pagination['total_pages'] ?? 1);
$from        = (int) ($pagination['from'] ?? 0);
$to          = (int) ($pagination['to'] ?? 0);

$currentSort = (string) ($_GET['sort'] ?? 'activity_date');
$currentDir  = strtolower((string) ($_GET['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

$query = $_GET;
unset($query['partial']);

$buildPageHref = static function (int $page) use ($query): string {
    $query['page'] = $page;
    return '/asistencia?' . http_build_query($query);
};

$buildSortHref = static function (string $key) use ($query, $currentSort, $currentDir): string {
    $query['sort'] = $key;
    $query['dir']  = ($currentSort === $key && $currentDir === 'asc') ? 'desc' : 'asc';
    $query['page'] = 1;
    return '/asistencia?' . http_build_query($query);
};

$buildTabHref = static function (string $tab) use ($query): string {
    $query['tab'] = $tab;
    $query['page'] = 1;
    return '/asistencia?' . http_build_query($query);
};

$sortIcon = static function (string $key) use ($currentSort, $currentDir): string {
    if ($currentSort !== $key) {
        return 'bi-arrow-down-up';
    }
    return $currentDir === 'asc' ? 'bi-sort-up' : 'bi-sort-down';
};

$pages = [];
if ($totalPages <= 7) {
    $pages = range(1, max(1, $totalPages));
} else {
    $pages = array_unique([
        1, 2,
        max(1, $currentPage - 1),
        $currentPage,
        min($totalPages, $currentPage + 1),
        $totalPages - 1, $totalPages,
    ]);
    sort($pages);
}

$subColors = ['#1e3a5f', '#2d4a3e', '#3d2a5c', '#1a3f5c', '#4a2c1e', '#2a3d1a', '#1e4a4a', '#4a3a1e'];
$getSubStyle = static function (string $sub) use ($subColors): string {
    $idx = abs(crc32(strtolower(trim($sub)))) % count($subColors);
    return 'background:' . $subColors[$idx] . ';color:#fff;';
};

$tabLabel = $activeTab === 'actividad' ? 'Actividades' : 'AoAT';
?>
<section class="mt-5 mb-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h1 class="section-title mb-1">Listados de Asistencia</h1>
            <p class="section-subtitle mb-0">Gestión de actividades y registro de asistentes.</p>
        </div>
        <a href="/asistencia/nueva" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i>
            Nueva Actividad
        </a>
    </div>

    <div class="mb-4">
        <div class="nav nav-pills gap-2">
            <a href="<?= htmlspecialchars($buildTabHref('aoat'), ENT_QUOTES, 'UTF-8') ?>" class="btn <?= $activeTab === 'aoat' ? 'btn-primary' : 'btn-outline-primary' ?>">
                AoAT
            </a>
            <a href="<?= htmlspecialchars($buildTabHref('actividad'), ENT_QUOTES, 'UTF-8') ?>" class="btn <?= $activeTab === 'actividad' ? 'btn-primary' : 'btn-outline-primary' ?>">
                Actividades
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <form method="get" action="/asistencia" id="asi-filter-form" class="row g-3 align-items-end">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab, ENT_QUOTES, 'UTF-8') ?>">
                <div class="col-md-3 col-sm-6">
                    <label class="form-label small fw-semibold text-muted mb-1">Subregión</label>
                    <select name="subregion" class="form-select" data-subregion-filter data-asi-autosubmit>
                        <option value="">Todas</option>
                    </select>
                </div>
                <div class="col-md-3 col-sm-6">
                    <label class="form-label small fw-semibold text-muted mb-1">Municipio</label>
                    <select name="municipality" class="form-select" data-municipality-filter data-asi-autosubmit disabled>
                        <option value="">Todos</option>
                    </select>
                </div>
                <div class="col-md-3 col-sm-6">
                    <label class="form-label small fw-semibold text-muted mb-1">Asesor</label>
                    <select name="advisor_user_id" class="form-select" data-asi-autosubmit <?= !empty($canFilterAdvisor) ? '' : 'disabled' ?>>
                        <option value=""><?= !empty($canFilterAdvisor) ? 'Todos' : 'Mi listado' ?></option>
                        <?php foreach ($advisors as $a): ?>
                            <option value="<?= (int) $a['id'] ?>" <?= (isset($filters['advisor_user_id']) && (int) $filters['advisor_user_id'] === (int) $a['id']) ? 'selected' : '' ?>><?= htmlspecialchars((string) $a['name'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($canFilterAdvisor)): ?>
                        <input type="hidden" name="advisor_user_id" value="<?= (int) ($filters['advisor_user_id'] ?? ($advisors[0]['id'] ?? 0)) ?>">
                    <?php endif; ?>
                </div>
                <div class="col-md-3 col-sm-6">
                    <label class="form-label small fw-semibold text-muted mb-1">Estado</label>
                    <select name="status" class="form-select" data-asi-autosubmit>
                        <option value="">Todos</option>
                        <option value="Pendiente" <?= ($filters['status'] ?? '') === 'Pendiente' ? 'selected' : '' ?>>Pendiente</option>
                        <option value="Activo" <?= ($filters['status'] ?? '') === 'Activo' ? 'selected' : '' ?>>Activo</option>
                        <option value="Cerrado" <?= ($filters['status'] ?? '') === 'Cerrado' ? 'selected' : '' ?>>Cerrado</option>
                    </select>
                </div>
                <div class="col-md-3 col-sm-6">
                    <label class="form-label small fw-semibold text-muted mb-1">Fecha desde</label>
                    <input type="date" name="from_date" class="form-control" value="<?= htmlspecialchars((string) ($filters['from_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-asi-autosubmit>
                </div>
                <div class="col-md-3 col-sm-6">
                    <label class="form-label small fw-semibold text-muted mb-1">Fecha hasta</label>
                    <input type="date" name="to_date" class="form-control" value="<?= htmlspecialchars((string) ($filters['to_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-asi-autosubmit>
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <a href="/asistencia?<?= http_build_query(['tab' => $activeTab]) ?>" class="asi-filter-clear-link" data-homo-filter-clear="/asistencia">Limpiar</a>
                </div>
            </form>
        </div>
    </div>

    <?php if ($totalItems === 0): ?>
        <div class="asi-empty-state">
            <div class="asi-empty-icon"><i class="bi bi-calendar-x"></i></div>
            <p class="asi-empty-title">Sin <?= htmlspecialchars(strtolower($tabLabel), ENT_QUOTES, 'UTF-8') ?></p>
            <p class="asi-empty-copy">No hay registros en la pestaña actual que coincidan con los filtros aplicados.</p>
        </div>
    <?php else: ?>
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="asi-table-head-bar px-4 py-3 d-flex align-items-center justify-content-between gap-2 flex-wrap">
                <p class="mb-0 asi-table-summary">
                    Mostrando <strong><?= $from ?>-<?= $to ?></strong> de <strong><?= $totalItems ?></strong> <?= htmlspecialchars($tabLabel, ENT_QUOTES, 'UTF-8') ?>
                </p>
                <span class="asi-page-chip">Página <?= $currentPage ?> de <?= $totalPages ?></span>
            </div>

            <div class="table-responsive">
                <table class="table align-middle mb-0 asi-table">
                    <thead>
                        <tr>
                            <th><a class="asi-sort-link <?= $currentSort === 'activity_date' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($buildSortHref('activity_date'), ENT_QUOTES, 'UTF-8') ?>"><span>Fecha</span><i class="bi <?= $sortIcon('activity_date') ?>"></i></a></th>
                            <th><a class="asi-sort-link <?= $currentSort === 'subregion' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($buildSortHref('subregion'), ENT_QUOTES, 'UTF-8') ?>"><span>Subregión</span><i class="bi <?= $sortIcon('subregion') ?>"></i></a></th>
                            <th><a class="asi-sort-link <?= $currentSort === 'municipality' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($buildSortHref('municipality'), ENT_QUOTES, 'UTF-8') ?>"><span>Municipio</span><i class="bi <?= $sortIcon('municipality') ?>"></i></a></th>
                            <th>Lugar</th>
                            <th><?= $activeTab === 'actividad' ? 'Actividad' : 'Listado AoAT' ?></th>
                            <th><a class="asi-sort-link <?= $currentSort === 'advisor_name' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($buildSortHref('advisor_name'), ENT_QUOTES, 'UTF-8') ?>"><span>Asesor</span><i class="bi <?= $sortIcon('advisor_name') ?>"></i></a></th>
                            <th class="text-center"><a class="asi-sort-link <?= $currentSort === 'asistentes_count' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($buildSortHref('asistentes_count'), ENT_QUOTES, 'UTF-8') ?>"><span>Asistentes</span><i class="bi <?= $sortIcon('asistentes_count') ?>"></i></a></th>
                            <th><a class="asi-sort-link <?= $currentSort === 'status' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($buildSortHref('status'), ENT_QUOTES, 'UTF-8') ?>"><span>Estado</span><i class="bi <?= $sortIcon('status') ?>"></i></a></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $row): ?>
                            <?php
                            $tipos       = $row['actividad_tipos'] ?? [];
                            $tiposList   = is_array($tipos) ? $tipos : [];
                            $tiposView   = array_slice($tiposList, 0, $activeTab === 'actividad' ? 1 : 2);
                            $tiposExtra  = count($tiposList) - count($tiposView);
                            $stRow = (string) ($row['status'] ?? '');
                            $statusClass = match ($stRow) {
                                'Activo' => 'is-active',
                                'Cerrado' => 'is-closed',
                                default => 'is-pending',
                            };
                            ?>
                            <tr>
                                <td class="asi-date"><?= htmlspecialchars((string) ($row['activity_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <?php if (!empty($row['subregion'])): ?>
                                        <span class="asi-subregion-pill" style="<?= $getSubStyle((string) $row['subregion']) ?>">
                                            <?= htmlspecialchars((string) $row['subregion'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars((string) ($row['municipality'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="asi-lugar" title="<?= htmlspecialchars((string) ($row['lugar'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars((string) ($row['lugar'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td>
                                    <?php if ($tiposView !== []): ?>
                                        <div class="asi-tipos-list">
                                            <?php foreach ($tiposView as $tipo): ?>
                                                <span class="asi-tipo-item" title="<?= htmlspecialchars((string) $tipo, ENT_QUOTES, 'UTF-8') ?>">
                                                    <?= htmlspecialchars((string) $tipo, ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            <?php endforeach; ?>
                                            <?php if ($tiposExtra > 0): ?>
                                                <span class="asi-tipos-more">+<?= $tiposExtra ?> más</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="asi-cell-advisor">
                                    <span class="asi-advisor-name"><?= htmlspecialchars((string) ($row['advisor_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="asi-count-badge"><?= (int) ($row['asistentes_count'] ?? 0) ?></span>
                                </td>
                                <td>
                                    <span class="asi-status-pill <?= $statusClass ?>">
                                        <?= htmlspecialchars((string) ($row['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="/asistencia/ver?id=<?= (int) $row['id'] ?>" class="asi-btn-view" title="Ver detalle">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <nav class="asi-pagination-wrap" aria-label="Paginación de asistencia">
                    <p class="asi-pagination-summary mb-0"><?= $totalItems ?> registros en total</p>
                    <ul class="pagination asi-pagination mb-0">
                        <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= htmlspecialchars($buildPageHref(max(1, $currentPage - 1)), ENT_QUOTES, 'UTF-8') ?>" aria-label="Anterior">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>
                        <?php
                        $prev = null;
                        foreach ($pages as $pg):
                            if ($prev !== null && $pg > $prev + 1):
                        ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                            <li class="page-item <?= $pg === $currentPage ? 'active' : '' ?>">
                                <a class="page-link" href="<?= htmlspecialchars($buildPageHref($pg), ENT_QUOTES, 'UTF-8') ?>"><?= $pg ?></a>
                            </li>
                        <?php
                            $prev = $pg;
                        endforeach;
                        ?>
                        <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= htmlspecialchars($buildPageHref(min($totalPages, $currentPage + 1)), ENT_QUOTES, 'UTF-8') ?>" aria-label="Siguiente">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Defer tras app.js (restauración de filtros en sessionStorage puede actualizar la URL antes).
    setTimeout(function () {
        document.querySelectorAll('[data-asi-autosubmit]').forEach(function (el) {
            el.addEventListener('change', function () {
                document.getElementById('asi-filter-form').submit();
            });
        });

        var subregionSelect = document.querySelector('[data-subregion-filter]');
        var municipalitySelect = document.querySelector('[data-municipality-filter]');
        if (!subregionSelect || !municipalitySelect) return;

        fetch('/assets/js/municipios.json').then(function (r) { return r.json(); }).then(function (data) {
            Object.keys(data).forEach(function (sub) {
                var opt = document.createElement('option');
                opt.value = sub;
                opt.textContent = sub;
                subregionSelect.appendChild(opt);
            });
            var currentSub = new URLSearchParams(window.location.search).get('subregion');
            var currentMun = new URLSearchParams(window.location.search).get('municipality');
            if (currentSub) {
                subregionSelect.value = currentSub;
                municipalitySelect.disabled = false;
                (data[currentSub] || []).forEach(function (m) {
                    var o = document.createElement('option');
                    o.value = m;
                    o.textContent = m;
                    if (m === currentMun) o.selected = true;
                    municipalitySelect.appendChild(o);
                });
            }
            subregionSelect.addEventListener('change', function () {
                municipalitySelect.innerHTML = '<option value="">Todos</option>';
                municipalitySelect.disabled = !subregionSelect.value;
                if (subregionSelect.value && data[subregionSelect.value]) {
                    data[subregionSelect.value].forEach(function (m) {
                        var o = document.createElement('option');
                        o.value = m;
                        o.textContent = m;
                        municipalitySelect.appendChild(o);
                    });
                }
            });
        });
    }, 0);
});
</script>
