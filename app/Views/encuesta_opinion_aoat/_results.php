<?php
/** @var array<int, array<string, mixed>> $records */
/** @var array<string, mixed> $pagination */

$totalItems  = (int) ($pagination['total_items'] ?? 0);
$currentPage = (int) ($pagination['current_page'] ?? 1);
$totalPages  = (int) ($pagination['total_pages'] ?? 1);
$from        = (int) ($pagination['from'] ?? 0);
$to          = (int) ($pagination['to'] ?? 0);

$currentSort = (string) ($_GET['sort'] ?? 'created_at');
$currentDir  = strtolower((string) ($_GET['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

$sortIcon = static function (string $key) use ($currentSort, $currentDir): string {
    if ($currentSort !== $key) return 'bi-arrow-down-up';
    return $currentDir === 'asc' ? 'bi-sort-up' : 'bi-sort-down';
};

$pages = [];
if ($totalPages <= 7) {
    $pages = range(1, max(1, $totalPages));
} else {
    $pages = array_unique([1, 2, max(1,$currentPage-1), $currentPage, min($totalPages,$currentPage+1), $totalPages-1, $totalPages]);
    sort($pages);
}

$scoreClass = static function (float $v): string {
    if ($v >= 4.5) return 'enc-score-high';
    if ($v >= 3.5) return 'enc-score-mid';
    return 'enc-score-low';
};

$subColors = ['#1e3a5f','#2d4a3e','#3d2a5c','#1a3f5c','#4a2c1e','#2a3d1a','#1e4a4a','#4a3a1e'];
$getSubStyle = static function (string $sub) use ($subColors): string {
    $idx = abs(crc32(strtolower(trim($sub)))) % count($subColors);
    return 'background:' . $subColors[$idx] . ';color:#fff;';
};
?>

<?php if ($totalItems === 0): ?>
    <div class="asi-empty-state">
        <div class="asi-empty-icon"><i class="bi bi-search"></i></div>
        <p class="asi-empty-title">Sin resultados</p>
        <p class="asi-empty-copy">No hay encuestas que coincidan con los filtros aplicados.</p>
    </div>
<?php else: ?>
<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="asi-table-head-bar px-4 py-3 d-flex align-items-center justify-content-between gap-2 flex-wrap">
        <p class="mb-0 asi-table-summary">
            Mostrando <strong><?= $from ?>–<?= $to ?></strong> de <strong><?= $totalItems ?></strong> encuestas
        </p>
        <?php if ($totalPages > 1): ?>
            <span class="asi-page-chip">Página <?= $currentPage ?> de <?= $totalPages ?></span>
        <?php endif; ?>
    </div>

    <div class="table-responsive">
        <table class="table align-middle mb-0 enc-table">
            <thead>
                <tr>
                    <?php
                    $cols = [
                        'created_at'   => 'Fecha registro',
                        'advisor_name' => 'Asesor',
                        ''             => 'Actividad',
                        ''             => 'Lugar',
                        'activity_date'=> 'Fecha actividad',
                        'subregion'    => 'Subregión',
                        'municipality' => 'Municipio',
                        'promedio'     => 'Promedio (1–5)',
                    ];
                    foreach ($cols as $colKey => $colLabel):
                        $isActive = $colKey !== '' && $currentSort === $colKey;
                        $nextDir  = ($isActive && $currentDir === 'asc') ? 'desc' : 'asc';
                    ?>
                        <th>
                            <?php if ($colKey !== ''): ?>
                                <a class="asi-sort-link <?= $isActive ? 'is-active' : '' ?>"
                                    href="#"
                                    data-encuesta-sort="<?= htmlspecialchars($colKey, ENT_QUOTES, 'UTF-8') ?>"
                                    data-encuesta-dir="<?= htmlspecialchars($nextDir, ENT_QUOTES, 'UTF-8') ?>">
                                    <span><?= htmlspecialchars($colLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                    <i class="bi <?= $sortIcon($colKey) ?>"></i>
                                </a>
                            <?php else: ?>
                                <span class="enc-col-static"><?= htmlspecialchars($colLabel, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $row): ?>
                    <?php
                    $prom = (float) ($row['promedio'] ?? 0);
                    $createdAt = (string) ($row['created_at'] ?? '');
                    $dateOnly  = substr($createdAt, 0, 10);
                    $timeOnly  = substr($createdAt, 11, 5);
                    ?>
                    <tr>
                        <td>
                            <div class="enc-date-stack">
                                <span class="enc-date-main"><?= htmlspecialchars($dateOnly, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php if ($timeOnly !== ''): ?>
                                    <span class="enc-date-sub"><?= htmlspecialchars($timeOnly, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="enc-advisor"><?= htmlspecialchars((string) ($row['advisor_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="enc-actividad" title="<?= htmlspecialchars((string) ($row['actividad'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($row['actividad'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="enc-lugar"><?= htmlspecialchars((string) ($row['lugar'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) ($row['activity_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <?php if (!empty($row['subregion'])): ?>
                                <span class="asi-subregion-pill" style="<?= $getSubStyle((string) $row['subregion']) ?>">
                                    <?= htmlspecialchars((string) $row['subregion'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="small"><?= htmlspecialchars((string) ($row['municipality'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="text-center">
                            <span class="enc-score-badge <?= $scoreClass($prom) ?>"><?= number_format($prom, 1) ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav class="asi-pagination-wrap" aria-label="Paginación de encuestas">
            <p class="asi-pagination-summary mb-0"><?= $totalItems ?> encuestas en total</p>
            <ul class="pagination asi-pagination mb-0">
                <li class="page-item <?= $currentPage<=1?'disabled':'' ?>">
                    <a class="page-link" href="#" data-encuesta-page="<?= max(1,$currentPage-1) ?>"><i class="bi bi-chevron-left"></i></a>
                </li>
                <?php $prev=null; foreach($pages as $pg): if($prev!==null&&$pg>$prev+1): ?>
                    <li class="page-item disabled"><span class="page-link">…</span></li>
                <?php endif; ?>
                    <li class="page-item <?= $pg===$currentPage?'active':'' ?>">
                        <a class="page-link" href="#" data-encuesta-page="<?= $pg ?>"><?= $pg ?></a>
                    </li>
                <?php $prev=$pg; endforeach; ?>
                <li class="page-item <?= $currentPage>=$totalPages?'disabled':'' ?>">
                    <a class="page-link" href="#" data-encuesta-page="<?= min($totalPages,$currentPage+1) ?>"><i class="bi bi-chevron-right"></i></a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>
<?php endif; ?>
