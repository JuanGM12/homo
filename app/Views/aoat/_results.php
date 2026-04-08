<?php
/** @var array<int, array<string, mixed>> $records */
/** @var bool $isAudit */
/** @var array<string, mixed> $pagination */

$totalItems = (int) ($pagination['total_items'] ?? 0);
$currentPage = (int) ($pagination['current_page'] ?? 1);
$totalPages = (int) ($pagination['total_pages'] ?? 1);
$from = (int) ($pagination['from'] ?? 0);
$to = (int) ($pagination['to'] ?? 0);
$currentSort = (string) ($_GET['sort'] ?? 'activity_date');
$currentDir = strtolower((string) ($_GET['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

$query = $_GET;
unset($query['partial']);

$buildPageHref = static function (int $page) use ($query): string {
    $query['page'] = $page;
    $qs = http_build_query($query);
    return '/aoat' . ($qs !== '' ? '?' . $qs : '');
};

$buildSortHref = static function (string $sortKey) use ($query, $currentSort, $currentDir): string {
    $nextDir = ($currentSort === $sortKey && $currentDir === 'asc') ? 'desc' : 'asc';
    $query['sort'] = $sortKey;
    $query['dir'] = $nextDir;
    $query['page'] = 1;
    $qs = http_build_query($query);
    return '/aoat' . ($qs !== '' ? '?' . $qs : '');
};

$sortIcon = static function (string $sortKey) use ($currentSort, $currentDir): string {
    if ($currentSort !== $sortKey) {
        return 'bi-arrow-down-up';
    }

    return $currentDir === 'asc' ? 'bi-sort-up' : 'bi-sort-down';
};

$pages = [];
if ($totalPages <= 7) {
    $pages = range(1, max(1, $totalPages));
} else {
    $pages = array_unique([
        1,
        2,
        max(1, $currentPage - 1),
        $currentPage,
        min($totalPages, $currentPage + 1),
        $totalPages - 1,
        $totalPages,
    ]);
    sort($pages);
}
?>

<div class="aoat-results-shell">
    <div class="aoat-results-head">
        <div>
            <p class="aoat-results-kicker mb-1">Seguimiento operativo</p>
            <h2 class="aoat-results-title mb-1">Registros encontrados</h2>
            <p class="aoat-results-meta mb-0">
                <?= $totalItems > 0
                    ? 'Mostrando ' . $from . ' a ' . $to . ' de ' . $totalItems . ' registros'
                    : 'No hay registros con los filtros actuales'
                ?>
            </p>
        </div>
        <?php if ($totalItems > 0): ?>
            <div class="aoat-results-chip">
                Página <?= $currentPage ?> de <?= $totalPages ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($totalItems === 0): ?>
        <div class="aoat-empty-state">
            <div class="aoat-empty-icon"><i class="bi bi-search"></i></div>
            <h3 class="aoat-empty-title">Sin coincidencias</h3>
            <p class="aoat-empty-copy mb-0">Ajusta la búsqueda o limpia los filtros para volver a ver registros.</p>
        </div>
    <?php else: ?>
        <?php if (!empty($isAudit)): ?>
            <div class="aoat-bulk-toolbar card border-0 shadow-sm mb-3" data-aoat-bulk-toolbar>
                <div class="card-body py-3 d-flex flex-wrap align-items-center gap-2 justify-content-between">
                    <div class="small text-muted mb-0">
                        <span data-aoat-bulk-count>0</span> seleccionado(s) en esta página
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-sm btn-primary" data-aoat-bulk-aprobar disabled>
                            <i class="bi bi-patch-check me-1"></i>
                            Aprobar seleccionadas
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-warning" data-aoat-bulk-devolver disabled>
                            <i class="bi bi-arrow-return-left me-1"></i>
                            Devolver seleccionadas
                        </button>
                    </div>
                </div>
                <p class="px-3 pb-3 mb-0 small text-muted">
                    Solo se incluyen filas en estado <strong>Asignada</strong> o <strong>Realizado</strong> que puedas auditar.
                    La devolución aplica únicamente a las que estén en <strong>Asignada</strong>.
                </p>
            </div>
        <?php endif; ?>
        <div class="table-responsive aoat-table-wrap">
            <table class="table aoat-table align-middle mb-0">
                <thead>
                <tr>
                    <?php if (!empty($isAudit)): ?>
                        <th scope="col" class="aoat-bulk-col text-center" style="width:2.5rem" title="Selección masiva">
                            <input type="checkbox" class="form-check-input" data-aoat-bulk-select-all aria-label="Seleccionar todos en esta página">
                        </th>
                    <?php endif; ?>
                    <?php
                    $headers = [
                        'activity_date' => 'Fecha',
                        'professional' => 'Profesional',
                        'subregion' => 'Subregión',
                        'municipality' => 'Municipio',
                        'activity_type' => 'Actividad que realizó',
                        'state' => 'Estado',
                    ];
                    foreach ($headers as $sortKey => $label):
                        $isActive = $currentSort === $sortKey;
                    ?>
                        <th scope="col">
                            <a
                                class="aoat-sort-link <?= $isActive ? 'is-active' : '' ?>"
                                href="<?= htmlspecialchars($buildSortHref($sortKey), ENT_QUOTES, 'UTF-8') ?>"
                                data-aoat-sort="<?= htmlspecialchars($sortKey, ENT_QUOTES, 'UTF-8') ?>"
                                data-aoat-dir="<?= htmlspecialchars(($isActive && $currentDir === 'asc') ? 'desc' : 'asc', ENT_QUOTES, 'UTF-8') ?>"
                            >
                                <span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                                <i class="bi <?= $sortIcon($sortKey) ?>"></i>
                            </a>
                        </th>
                    <?php endforeach; ?>
                    <th scope="col" class="text-end">Acciones</th>
                </tr>
                </thead>
                <tbody>
                <?php require __DIR__ . '/_rows.php'; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="aoat-pagination-wrap" aria-label="Paginación de AoAT">
                <div class="aoat-pagination-summary">
                    <?= $totalItems ?> registros en total
                </div>
                <ul class="pagination aoat-pagination mb-0">
                    <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                        <a
                            class="page-link"
                            href="<?= htmlspecialchars($buildPageHref(max(1, $currentPage - 1)), ENT_QUOTES, 'UTF-8') ?>"
                            data-aoat-page="<?= max(1, $currentPage - 1) ?>"
                            aria-label="Página anterior"
                        >
                            <i class="bi bi-arrow-left-short"></i>
                        </a>
                    </li>
                    <?php
                    $previousPage = null;
                    foreach ($pages as $page):
                        if ($previousPage !== null && $page > $previousPage + 1):
                    ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php
                        endif;
                    ?>
                        <li class="page-item <?= $page === $currentPage ? 'active' : '' ?>">
                            <a
                                class="page-link"
                                href="<?= htmlspecialchars($buildPageHref($page), ENT_QUOTES, 'UTF-8') ?>"
                                data-aoat-page="<?= $page ?>"
                            >
                                <?= $page ?>
                            </a>
                        </li>
                    <?php
                        $previousPage = $page;
                    endforeach;
                    ?>
                    <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                        <a
                            class="page-link"
                            href="<?= htmlspecialchars($buildPageHref(min($totalPages, $currentPage + 1)), ENT_QUOTES, 'UTF-8') ?>"
                            data-aoat-page="<?= min($totalPages, $currentPage + 1) ?>"
                            aria-label="Página siguiente"
                        >
                            <i class="bi bi-arrow-right-short"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>
