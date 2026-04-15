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
$isAdmin = in_array('admin', $currentUser['roles'] ?? [], true);

$query = $_GET;
unset($query['partial']);

$buildPageHref = static function (int $page) use ($query): string {
    $query['page'] = $page;
    $qs = http_build_query($query);
    return '/pic' . ($qs !== '' ? '?' . $qs : '');
};

$buildSortHref = static function (string $sortKey) use ($query, $currentSort, $currentDir): string {
    $nextDir = ($currentSort === $sortKey && $currentDir === 'asc') ? 'desc' : 'asc';
    $query['sort'] = $sortKey;
    $query['dir'] = $nextDir;
    $query['page'] = 1;
    $qs = http_build_query($query);
    return '/pic' . ($qs !== '' ? '?' . $qs : '');
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

<div class="pic-results-shell">
    <div class="pic-results-head">
        <div>
            <p class="pic-results-kicker mb-1">Seguimiento operativo PIC</p>
            <h2 class="pic-results-title mb-1">Registros encontrados</h2>
            <p class="pic-results-meta mb-0">
                <?= $totalItems > 0
                    ? 'Mostrando ' . $from . ' a ' . $to . ' de ' . $totalItems . ' registros'
                    : 'No hay registros con los filtros actuales'
                ?>
            </p>
        </div>
        <?php if ($totalItems > 0): ?>
            <div class="pic-results-chip">
                Pagina <?= $currentPage ?> de <?= $totalPages ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($totalItems === 0): ?>
        <div class="pic-empty-state">
            <div class="pic-empty-icon"><i class="bi bi-search"></i></div>
            <h3 class="pic-empty-title">Sin coincidencias</h3>
            <p class="pic-empty-copy mb-0">Ajusta la busqueda o limpia los filtros para volver a ver registros PIC.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive pic-table-wrap">
            <table class="table pic-table align-middle mb-0">
                <thead>
                <tr>
                    <?php
                    $headers = [
                        'created_at' => 'Fecha registro',
                        'professional_name' => 'Profesional',
                        'professional_role' => 'Rol',
                        'subregion' => 'Subregion',
                        'municipality' => 'Municipio',
                        'state' => 'Estado',
                    ];
                    foreach ($headers as $sortKey => $label):
                        $isActive = $currentSort === $sortKey;
                    ?>
                        <th scope="col">
                            <a
                                class="pic-sort-link <?= $isActive ? 'is-active' : '' ?>"
                                href="<?= htmlspecialchars($buildSortHref($sortKey), ENT_QUOTES, 'UTF-8') ?>"
                                data-pic-sort="<?= htmlspecialchars($sortKey, ENT_QUOTES, 'UTF-8') ?>"
                                data-pic-dir="<?= htmlspecialchars(($isActive && $currentDir === 'asc') ? 'desc' : 'asc', ENT_QUOTES, 'UTF-8') ?>"
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
                <?php foreach ($records as $row): ?>
                    <?php
                    $payload = [];
                    if (!empty($row['payload'])) {
                        $decoded = json_decode((string) $row['payload'], true);
                        if (is_array($decoded)) {
                            $payload = $decoded;
                        }
                    }

                    $detail = [
                        'professional' => (string) ($row['professional_name'] ?? ''),
                        'email' => (string) ($row['professional_email'] ?? ''),
                        'subregion' => (string) ($row['subregion'] ?? ''),
                        'municipality' => (string) ($row['municipality'] ?? ''),
                        'created_at' => (string) ($row['created_at'] ?? ''),
                        'state' => !empty($row['editable']) ? 'Editable' : 'Aprobado',
                        'payload' => [
                            'zona_orientacion_escolar' => (string) ($payload['zona_orientacion_escolar'] ?? ''),
                            'personas_zona_orientacion_escolar' => (string) ($payload['personas_zona_orientacion_escolar'] ?? ''),
                            'centro_escucha' => (string) ($payload['centro_escucha'] ?? ''),
                            'personas_centro_escucha' => (string) ($payload['personas_centro_escucha'] ?? ''),
                            'zona_orientacion_universitaria' => (string) ($payload['zona_orientacion_universitaria'] ?? ''),
                            'personas_zona_orientacion_universitaria' => (string) ($payload['personas_zona_orientacion_universitaria'] ?? ''),
                            'redes_comunitarias_activas' => (string) ($payload['redes_comunitarias_activas'] ?? ''),
                            'personas_red_comunitaria' => (string) ($payload['personas_red_comunitaria'] ?? ''),
                        ],
                    ];

                    $detailJson = htmlspecialchars(json_encode($detail, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                    $isOwner = $userId > 0 && (int) ($row['user_id'] ?? 0) === $userId;
                    $createdAt = trim((string) ($row['created_at'] ?? ''));
                    $timestamp = $createdAt !== '' ? strtotime($createdAt) : false;
                    $createdDate = $timestamp ? date('d/m/Y', $timestamp) : ($createdAt !== '' ? $createdAt : 'Sin fecha');
                    $createdTime = $timestamp ? date('H:i', $timestamp) : '';
                    ?>
                    <tr class="pic-row">
                        <td>
                            <div class="pic-date-stack">
                                <span class="pic-date-main"><?= htmlspecialchars($createdDate, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php if ($createdTime !== ''): ?>
                                    <span class="pic-date-sub"><?= htmlspecialchars($createdTime, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div class="pic-professional">
                                <span class="pic-professional-name">
                                    <?= htmlspecialchars((string) ($row['professional_name'] ?? 'Sin nombre'), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <?php if (!empty($row['professional_email'])): ?>
                                    <span class="pic-professional-role">
                                        <?= htmlspecialchars((string) $row['professional_email'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="pic-cell-strong">
                            <?= htmlspecialchars(ucwords(str_replace('_', ' ', (string) ($row['professional_role'] ?? ''))), ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td class="pic-cell-strong"><?= htmlspecialchars((string) ($row['subregion'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="pic-cell-strong"><?= htmlspecialchars((string) ($row['municipality'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <span class="pic-status-pill <?= !empty($row['editable']) ? 'is-editable' : 'is-approved' ?>">
                                <?= !empty($row['editable']) ? 'Editable' : 'Aprobado' ?>
                            </span>
                        </td>
                        <td>
                            <div class="pic-actions">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-secondary"
                                    data-pic-details
                                    data-pic="<?= $detailJson ?>"
                                >
                                    <i class="bi bi-eye me-1"></i>
                                    Ver detalles
                                </button>
                                <?php
                                $canEditPic = !empty($row['editable']) && $isOwner;
                                ?>
                                <?php if ($canEditPic): ?>
                                    <a href="/pic/editar?id=<?= (int) $row['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil me-1"></i>
                                        Editar
                                    </a>
                                <?php endif; ?>
                                <?php if ($isAdmin): ?>
                                    <form
                                        method="post"
                                        action="/pic/eliminar"
                                        class="d-inline"
                                        data-sw-confirm="1"
                                        data-sw-title="Eliminar registro PIC"
                                        data-sw-text="¿Eliminar este registro PIC de forma permanente? Esta acción no se puede deshacer."
                                    >
                                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash me-1"></i>
                                            Eliminar
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <?php if (!$canEditPic && !$isAdmin): ?>
                                    <span class="pic-no-actions">No editable</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="pic-pagination-wrap" aria-label="Paginacion de PIC">
                <div class="pic-pagination-summary">
                    <?= $totalItems ?> registros en total
                </div>
                <ul class="pagination pic-pagination mb-0">
                    <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                        <a
                            class="page-link"
                            href="<?= htmlspecialchars($buildPageHref(max(1, $currentPage - 1)), ENT_QUOTES, 'UTF-8') ?>"
                            data-pic-page="<?= max(1, $currentPage - 1) ?>"
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
                                data-pic-page="<?= $page ?>"
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
                            data-pic-page="<?= min($totalPages, $currentPage + 1) ?>"
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
