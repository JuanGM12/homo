<?php
/** @var array<int, array<string, mixed>> $records */
/** @var array<string, mixed> $pagination */
/** @var bool $isAuditViewLocal */
/** @var array<string, mixed> $currentUser */

$totalItems = (int) ($pagination['total_items'] ?? 0);
$currentPage = (int) ($pagination['current_page'] ?? 1);
$totalPages = (int) ($pagination['total_pages'] ?? 1);
$from = (int) ($pagination['from'] ?? 0);
$to = (int) ($pagination['to'] ?? 0);
$currentSort = (string) ($_GET['sort'] ?? 'created_at');
$currentDir = strtolower((string) ($_GET['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
$userId = (int) ($currentUser['id'] ?? 0);

$monthOrder = [
    'enero' => 'Enero',
    'febrero' => 'Febrero',
    'marzo' => 'Marzo',
    'abril' => 'Abril',
    'mayo' => 'Mayo',
    'junio' => 'Junio',
    'julio' => 'Julio',
    'agosto' => 'Agosto',
    'septiembre' => 'Septiembre',
    'octubre' => 'Octubre',
    'noviembre' => 'Noviembre',
    'diciembre' => 'Diciembre',
];

$query = $_GET;
unset($query['partial']);

$buildPageHref = static function (int $page) use ($query): string {
    $query['page'] = $page;
    $qs = http_build_query($query);
    return '/planeacion' . ($qs !== '' ? '?' . $qs : '');
};

$buildSortHref = static function (string $sortKey) use ($query, $currentSort, $currentDir): string {
    $nextDir = ($currentSort === $sortKey && $currentDir === 'asc') ? 'desc' : 'asc';
    $query['sort'] = $sortKey;
    $query['dir'] = $nextDir;
    $query['page'] = 1;
    $qs = http_build_query($query);
    return '/planeacion' . ($qs !== '' ? '?' . $qs : '');
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

<div class="planeacion-results-shell">
    <div class="planeacion-results-head">
        <div>
            <p class="planeacion-results-kicker mb-1">Seguimiento anual</p>
            <h2 class="planeacion-results-title mb-1">Planeaciones encontradas</h2>
            <p class="planeacion-results-meta mb-0">
                <?= $totalItems > 0
                    ? 'Mostrando ' . $from . ' a ' . $to . ' de ' . $totalItems . ' registros'
                    : 'No hay registros con los filtros actuales'
                ?>
            </p>
        </div>
        <?php if ($totalItems > 0): ?>
            <div class="planeacion-results-chip">
                Pagina <?= $currentPage ?> de <?= $totalPages ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($totalItems === 0): ?>
        <div class="planeacion-empty-state">
            <div class="planeacion-empty-icon"><i class="bi bi-journal-x"></i></div>
            <h3 class="planeacion-empty-title">Sin coincidencias</h3>
            <p class="planeacion-empty-copy mb-0">
                Ajusta los filtros o limpia la busqueda para volver a ver planeaciones.
            </p>
        </div>
    <?php else: ?>
        <div class="table-responsive planeacion-table-wrap">
            <table class="table planeacion-table align-middle mb-0">
                <thead>
                <tr>
                    <?php
                    $headers = [
                        'plan_year' => 'Ano',
                        'professional_name' => 'Asesor',
                        'subregion' => 'Subregion',
                        'municipality' => 'Municipio',
                        'state' => 'Estado',
                        'created_at' => 'Registrada',
                    ];
                    foreach ($headers as $sortKey => $label):
                        $isActive = $currentSort === $sortKey;
                    ?>
                        <th scope="col">
                            <a
                                class="planeacion-sort-link <?= $isActive ? 'is-active' : '' ?>"
                                href="<?= htmlspecialchars($buildSortHref($sortKey), ENT_QUOTES, 'UTF-8') ?>"
                                data-planeacion-sort="<?= htmlspecialchars($sortKey, ENT_QUOTES, 'UTF-8') ?>"
                                data-planeacion-dir="<?= htmlspecialchars(($isActive && $currentDir === 'asc') ? 'desc' : 'asc', ENT_QUOTES, 'UTF-8') ?>"
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
                <?php foreach ($records as $plan): ?>
                    <?php
                    $payload = [];
                    if (!empty($plan['payload'])) {
                        $decoded = json_decode((string) $plan['payload'], true);
                        if (is_array($decoded)) {
                            $payload = $decoded;
                        }
                    }

                    $monthsSummary = [];
                    foreach ($monthOrder as $monthKey => $monthLabel) {
                        $monthData = $payload[$monthKey] ?? null;
                        if (!is_array($monthData)) {
                            continue;
                        }

                        $topics = $monthData['topics'] ?? [];
                        if (!is_array($topics)) {
                            $topics = [];
                        }

                        $topics = array_values(array_filter(array_map(static function ($topic): string {
                            return trim((string) $topic);
                        }, $topics), static function (string $topic): bool {
                            return $topic !== '';
                        }));

                        $population = trim((string) ($monthData['population'] ?? ''));
                        if ($topics === [] && $population === '') {
                            continue;
                        }

                        $monthsSummary[] = [
                            'key' => $monthKey,
                            'label' => $monthLabel,
                            'topics' => $topics,
                            'population' => $population,
                        ];
                    }

                    $planForJs = [
                        'year' => (int) ($plan['plan_year'] ?? date('Y')),
                        'professional' => (string) ($plan['professional_name'] ?? ''),
                        'professional_role' => (string) ($plan['professional_role'] ?? ''),
                        'subregion' => (string) ($plan['subregion'] ?? ''),
                        'municipality' => (string) ($plan['municipality'] ?? ''),
                        'months' => $monthsSummary,
                    ];

                    $planJson = htmlspecialchars(json_encode($planForJs, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                    $isOwner = $userId > 0 && (int) ($plan['user_id'] ?? 0) === $userId;
                    $createdAt = trim((string) ($plan['created_at'] ?? ''));
                    $timestamp = $createdAt !== '' ? strtotime($createdAt) : false;
                    $createdDate = $timestamp ? date('d/m/Y', $timestamp) : ($createdAt !== '' ? $createdAt : 'Sin fecha');
                    $createdTime = $timestamp ? date('H:i', $timestamp) : '';
                    ?>
                    <tr class="planeacion-row">
                        <td class="planeacion-cell-strong">
                            <?= htmlspecialchars((string) ($plan['plan_year'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td>
                            <div class="planeacion-professional">
                                <span class="planeacion-professional-name">
                                    <?= htmlspecialchars((string) ($plan['professional_name'] ?? 'Sin nombre'), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <?php if (!empty($plan['professional_role'])): ?>
                                    <span class="planeacion-professional-role">
                                        <?= htmlspecialchars((string) $plan['professional_role'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="planeacion-cell-strong">
                            <?= htmlspecialchars((string) ($plan['subregion'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td class="planeacion-cell-strong">
                            <?= htmlspecialchars((string) ($plan['municipality'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td>
                            <span class="planeacion-status-pill <?= !empty($plan['editable']) ? 'is-editable' : 'is-approved' ?>">
                                <?= !empty($plan['editable']) ? 'Editable' : 'Aprobada' ?>
                            </span>
                        </td>
                        <td>
                            <div class="planeacion-date-stack">
                                <span class="planeacion-date-main"><?= htmlspecialchars($createdDate, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php if ($createdTime !== ''): ?>
                                    <span class="planeacion-date-sub"><?= htmlspecialchars($createdTime, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div class="planeacion-actions">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-secondary"
                                    data-plan-details
                                    data-plan="<?= $planJson ?>"
                                >
                                    <i class="bi bi-eye me-1"></i>
                                    Ver detalles
                                </button>
                                <?php if (!empty($plan['editable']) && $isOwner): ?>
                                    <a href="/planeacion/editar?id=<?= (int) $plan['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil me-1"></i>
                                        Editar
                                    </a>
                                <?php else: ?>
                                    <span class="planeacion-no-actions">No editable</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="planeacion-pagination-wrap" aria-label="Paginacion de planeaciones">
                <div class="planeacion-pagination-summary">
                    <?= $totalItems ?> registros en total
                </div>
                <ul class="pagination planeacion-pagination mb-0">
                    <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                        <a
                            class="page-link"
                            href="<?= htmlspecialchars($buildPageHref(max(1, $currentPage - 1)), ENT_QUOTES, 'UTF-8') ?>"
                            data-planeacion-page="<?= max(1, $currentPage - 1) ?>"
                            aria-label="Pagina anterior"
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
                                data-planeacion-page="<?= $page ?>"
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
                            data-planeacion-page="<?= min($totalPages, $currentPage + 1) ?>"
                            aria-label="Pagina siguiente"
                        >
                            <i class="bi bi-arrow-right-short"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>
