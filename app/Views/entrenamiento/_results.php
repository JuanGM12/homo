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

$query = $_GET;
unset($query['partial']);

$buildPageHref = static function (int $page) use ($query): string {
    $query['page'] = $page;
    $qs = http_build_query($query);
    return '/entrenamiento' . ($qs !== '' ? '?' . $qs : '');
};

$buildSortHref = static function (string $sortKey) use ($query, $currentSort, $currentDir): string {
    $nextDir = ($currentSort === $sortKey && $currentDir === 'asc') ? 'desc' : 'asc';
    $query['sort'] = $sortKey;
    $query['dir'] = $nextDir;
    $query['page'] = 1;
    $qs = http_build_query($query);
    return '/entrenamiento' . ($qs !== '' ? '?' . $qs : '');
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

<div class="entrenamiento-results-shell">
    <div class="entrenamiento-results-head">
        <div>
            <p class="entrenamiento-results-kicker mb-1">Seguimiento de entrenamiento</p>
            <h2 class="entrenamiento-results-title mb-1">Planes encontrados</h2>
            <p class="entrenamiento-results-meta mb-0">
                <?= $totalItems > 0
                    ? 'Mostrando ' . $from . ' a ' . $to . ' de ' . $totalItems . ' registros'
                    : 'No hay registros con los filtros actuales'
                ?>
            </p>
        </div>
        <?php if ($totalItems > 0): ?>
            <div class="entrenamiento-results-chip">
                Pagina <?= $currentPage ?> de <?= $totalPages ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($totalItems === 0): ?>
        <div class="entrenamiento-empty-state">
            <div class="entrenamiento-empty-icon"><i class="bi bi-search"></i></div>
            <h3 class="entrenamiento-empty-title">Sin coincidencias</h3>
            <p class="entrenamiento-empty-copy mb-0">Ajusta la busqueda o limpia los filtros para volver a ver planes.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive entrenamiento-table-wrap">
            <table class="table entrenamiento-table align-middle mb-0">
                <thead>
                <tr>
                    <?php
                    $headers = [
                        'created_at' => 'Fecha registro',
                        'professional_name' => 'Profesional',
                        'subregion' => 'Subregion',
                        'municipality' => 'Municipio',
                        'state' => 'Estado',
                    ];
                    foreach ($headers as $sortKey => $label):
                        $isActive = $currentSort === $sortKey;
                    ?>
                        <th scope="col">
                            <a
                                class="entrenamiento-sort-link <?= $isActive ? 'is-active' : '' ?>"
                                href="<?= htmlspecialchars($buildSortHref($sortKey), ENT_QUOTES, 'UTF-8') ?>"
                                data-entrenamiento-sort="<?= htmlspecialchars($sortKey, ENT_QUOTES, 'UTF-8') ?>"
                                data-entrenamiento-dir="<?= htmlspecialchars(($isActive && $currentDir === 'asc') ? 'desc' : 'asc', ENT_QUOTES, 'UTF-8') ?>"
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

                    $trainingDetail = [
                        'professional' => (string) ($plan['professional_name'] ?? ''),
                        'email' => (string) ($plan['professional_email'] ?? ''),
                        'subregion' => (string) ($plan['subregion'] ?? ''),
                        'municipality' => (string) ($plan['municipality'] ?? ''),
                        'created_at' => (string) ($plan['created_at'] ?? ''),
                        'state' => !empty($plan['editable']) ? 'Editable' : 'Aprobado',
                        'payload' => [
                            'suicidio' => is_array($payload['suicidio'] ?? null) ? array_values($payload['suicidio']) : [],
                            'violencias' => is_array($payload['violencias'] ?? null) ? array_values($payload['violencias']) : [],
                            'adicciones' => is_array($payload['adicciones'] ?? null) ? array_values($payload['adicciones']) : [],
                            'otros_temas_salud_mental' => is_array($payload['otros_temas_salud_mental'] ?? null) ? array_values($payload['otros_temas_salud_mental']) : [],
                            'tema_propuesto_1' => (string) ($payload['tema_propuesto_1'] ?? ''),
                            'tema_propuesto_2' => (string) ($payload['tema_propuesto_2'] ?? ''),
                            'tema_propuesto_3' => (string) ($payload['tema_propuesto_3'] ?? ''),
                            'tema_propuesto_4' => (string) ($payload['tema_propuesto_4'] ?? ''),
                            'justificacion_temas' => (string) ($payload['justificacion_temas'] ?? ''),
                        ],
                    ];

                    $detailJson = htmlspecialchars(json_encode($trainingDetail, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                    $isOwner = $userId > 0 && (int) ($plan['user_id'] ?? 0) === $userId;
                    $createdAt = trim((string) ($plan['created_at'] ?? ''));
                    $timestamp = $createdAt !== '' ? strtotime($createdAt) : false;
                    $createdDate = $timestamp ? date('d/m/Y', $timestamp) : ($createdAt !== '' ? $createdAt : 'Sin fecha');
                    $createdTime = $timestamp ? date('H:i', $timestamp) : '';
                    ?>
                    <tr class="entrenamiento-row">
                        <td>
                            <div class="entrenamiento-date-stack">
                                <span class="entrenamiento-date-main"><?= htmlspecialchars($createdDate, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php if ($createdTime !== ''): ?>
                                    <span class="entrenamiento-date-sub"><?= htmlspecialchars($createdTime, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div class="entrenamiento-professional">
                                <span class="entrenamiento-professional-name">
                                    <?= htmlspecialchars((string) ($plan['professional_name'] ?? 'Sin nombre'), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <?php if (!empty($plan['professional_email'])): ?>
                                    <span class="entrenamiento-professional-role">
                                        <?= htmlspecialchars((string) $plan['professional_email'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="entrenamiento-cell-strong"><?= htmlspecialchars((string) ($plan['subregion'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="entrenamiento-cell-strong"><?= htmlspecialchars((string) ($plan['municipality'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <span class="entrenamiento-status-pill <?= !empty($plan['editable']) ? 'is-editable' : 'is-approved' ?>">
                                <?= !empty($plan['editable']) ? 'Editable' : 'Aprobado' ?>
                            </span>
                        </td>
                        <td>
                            <div class="entrenamiento-actions">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-secondary"
                                    data-entrenamiento-details
                                    data-entrenamiento="<?= $detailJson ?>"
                                >
                                    <i class="bi bi-eye me-1"></i>
                                    Ver detalles
                                </button>
                                <?php if (!empty($plan['editable']) && $isOwner): ?>
                                    <a href="/entrenamiento/editar?id=<?= (int) $plan['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil me-1"></i>
                                        Editar
                                    </a>
                                <?php else: ?>
                                    <span class="entrenamiento-no-actions">Sin acciones</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="entrenamiento-pagination-wrap" aria-label="Paginacion de entrenamiento">
                <div class="entrenamiento-pagination-summary">
                    <?= $totalItems ?> registros en total
                </div>
                <ul class="pagination entrenamiento-pagination mb-0">
                    <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                        <a
                            class="page-link"
                            href="<?= htmlspecialchars($buildPageHref(max(1, $currentPage - 1)), ENT_QUOTES, 'UTF-8') ?>"
                            data-entrenamiento-page="<?= max(1, $currentPage - 1) ?>"
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
                                data-entrenamiento-page="<?= $page ?>"
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
                            data-entrenamiento-page="<?= min($totalPages, $currentPage + 1) ?>"
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
