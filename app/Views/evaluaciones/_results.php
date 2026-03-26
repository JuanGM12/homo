<?php
/** @var array<int, array<string, mixed>> $comparisonRows */
/** @var array<string, mixed> $pagination */
/** @var array<string, mixed> $impactSummary */
/** @var array<string, mixed> $tests */

$impactGlobal = $impactSummary['global'] ?? null;
$impactByMun  = $impactSummary['by_municipality'] ?? [];

$totalItems  = (int) ($pagination['total_items'] ?? 0);
$currentPage = (int) ($pagination['current_page'] ?? 1);
$totalPages  = (int) ($pagination['total_pages'] ?? 1);
$from        = (int) ($pagination['from'] ?? 0);
$to          = (int) ($pagination['to'] ?? 0);

$currentSort = (string) ($_GET['sort'] ?? 'municipality');
$currentDir  = strtolower((string) ($_GET['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

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

$impactMeta = [
    'mejoria'       => ['label' => 'Con mejoría',   'class' => 'eval-impact-mejoria'],
    'sin_cambios'   => ['label' => 'Sin cambios',   'class' => 'eval-impact-neutral'],
    'sin_mejoria'   => ['label' => 'Sin mejoría',   'class' => 'eval-impact-danger'],
    'pendiente_post'=> ['label' => 'Pendiente POST','class' => 'eval-impact-pending'],
    ''              => ['label' => '—',              'class' => 'eval-impact-neutral'],
];
?>

<?php if ($comparisonRows !== [] && is_array($impactGlobal)): ?>
<!-- Impacto global accordion -->
<div class="accordion mb-4" id="evalImpactAccordion">
    <div class="accordion-item border-0 shadow-sm rounded-4 overflow-hidden">
        <h2 class="accordion-header" id="evalImpactHeading">
            <button class="accordion-button collapsed eval-impact-accordion-btn" type="button"
                data-bs-toggle="collapse" data-bs-target="#evalImpactCollapse"
                aria-expanded="false" aria-controls="evalImpactCollapse">
                <i class="bi bi-bar-chart me-2"></i>Resultado impacto global — personas con PRE y POST
            </button>
        </h2>
        <div id="evalImpactCollapse" class="accordion-collapse collapse"
            aria-labelledby="evalImpactHeading" data-bs-parent="#evalImpactAccordion">
            <div class="accordion-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0 eval-impact-table">
                        <thead>
                            <tr>
                                <th>Municipio</th>
                                <th class="text-end">Con PRE+POST</th>
                                <th class="text-end">Con mejoría</th>
                                <th class="text-end">Sin cambios</th>
                                <th class="text-end">Sin mejoría</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="eval-impact-total-row">
                                <td><strong><?= htmlspecialchars((string) ($impactGlobal['municipality'] ?? 'Total'), ENT_QUOTES, 'UTF-8') ?></strong></td>
                                <td class="text-end fw-semibold"><?= (int) ($impactGlobal['con_ambos'] ?? 0) ?></td>
                                <td class="text-end"><span class="eval-impact-mejoria eval-pct-chip"><?= $impactGlobal['pct_mejoria'] ?? '0' ?>%</span></td>
                                <td class="text-end"><span class="eval-impact-neutral eval-pct-chip"><?= $impactGlobal['pct_sin_cambios'] ?? '0' ?>%</span></td>
                                <td class="text-end"><span class="eval-impact-danger eval-pct-chip"><?= $impactGlobal['pct_sin_mejoria'] ?? '0' ?>%</span></td>
                            </tr>
                            <?php foreach ($impactByMun as $mun): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string) ($mun['municipality'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-end"><?= (int) ($mun['con_ambos'] ?? 0) ?></td>
                                    <td class="text-end"><span class="eval-impact-mejoria eval-pct-chip"><?= $mun['pct_mejoria'] ?? '0' ?>%</span></td>
                                    <td class="text-end"><span class="eval-impact-neutral eval-pct-chip"><?= $mun['pct_sin_cambios'] ?? '0' ?>%</span></td>
                                    <td class="text-end"><span class="eval-impact-danger eval-pct-chip"><?= $mun['pct_sin_mejoria'] ?? '0' ?>%</span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Tabla comparativa -->
<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="asi-table-head-bar px-4 py-3 d-flex align-items-center justify-content-between gap-2 flex-wrap">
        <p class="mb-0 asi-table-summary">
            <?php if ($totalItems > 0): ?>
                Mostrando <strong><?= $from ?>–<?= $to ?></strong> de <strong><?= $totalItems ?></strong> registros
            <?php else: ?>
                Sin registros con los filtros actuales
            <?php endif; ?>
        </p>
        <?php if ($totalPages > 1): ?>
            <span class="asi-page-chip">Página <?= $currentPage ?> de <?= $totalPages ?></span>
        <?php endif; ?>
    </div>

    <div class="table-responsive">
        <table class="table align-middle mb-0 eval-table">
            <thead>
                <tr>
                    <?php
                    $cols = [
                        'test_key'        => 'Temática',
                        'document_number' => 'Documento',
                        'persona'         => 'Persona',
                        'subregion'       => 'Subregión',
                        'municipality'    => 'Municipio',
                        'pre_score'       => 'PRE',
                        'post_score'      => 'POST',
                        'delta'           => 'Δ',
                        'impact'          => 'Impacto',
                    ];
                    $alignEnd = ['pre_score', 'post_score', 'delta'];
                    foreach ($cols as $colKey => $colLabel):
                        $isActive = $currentSort === $colKey;
                        $nextDir  = ($isActive && $currentDir === 'asc') ? 'desc' : 'asc';
                    ?>
                        <th<?= in_array($colKey, $alignEnd, true) ? ' class="text-end"' : '' ?>>
                            <a class="asi-sort-link <?= $isActive ? 'is-active' : '' ?>"
                                href="#"
                                data-eval-sort="<?= htmlspecialchars($colKey, ENT_QUOTES, 'UTF-8') ?>"
                                data-eval-dir="<?= htmlspecialchars($nextDir, ENT_QUOTES, 'UTF-8') ?>">
                                <span><?= htmlspecialchars($colLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                <i class="bi <?= $sortIcon($colKey) ?>"></i>
                            </a>
                        </th>
                    <?php endforeach; ?>
                    <th class="text-end">Detalle</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($comparisonRows === []): ?>
                <tr><td colspan="10" class="text-center text-muted py-5">No se encontraron evaluaciones con los filtros seleccionados.</td></tr>
            <?php else: ?>
                <?php foreach ($comparisonRows as $row): ?>
                    <?php
                    $key    = (string) ($row['test_key'] ?? '');
                    $info   = $tests[$key] ?? ['name' => $key];
                    $impact = (string) ($row['impact'] ?? '');
                    $meta   = $impactMeta[$impact] ?? $impactMeta[''];
                    $preRow  = is_array($row['pre']  ?? null) ? $row['pre']  : null;
                    $postRow = is_array($row['post'] ?? null) ? $row['post'] : null;
                    $idPre   = $preRow  !== null ? (int) ($preRow['id']  ?? 0) : 0;
                    $idPost  = $postRow !== null ? (int) ($postRow['id'] ?? 0) : 0;
                    $singleExportQuery = http_build_query([
                        'single' => 1,
                        'test_key' => (string) ($row['test_key'] ?? ''),
                        'document_number' => (string) ($row['document_number'] ?? ''),
                    ]);
                    ?>
                    <tr>
                        <td class="eval-tematica"><?= htmlspecialchars((string) ($info['name'] ?? $key), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><span class="eval-doc-link"><?= htmlspecialchars((string) ($row['document_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td class="eval-persona"><?= htmlspecialchars(trim((string)($row['first_name']??'').' '.(string)($row['last_name']??'')), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="small text-muted"><?= htmlspecialchars((string) ($row['subregion'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="small"><?= htmlspecialchars((string) ($row['municipality'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="text-end">
                            <?php if ($row['pre_score'] !== null): ?>
                                <span class="eval-score-chip"><?= number_format((float)$row['pre_score'],0) ?>%</span>
                                <div class="eval-score-date"><?= htmlspecialchars(substr((string)($row['pre_at']??''),0,10),ENT_QUOTES,'UTF-8') ?></div>
                            <?php else: ?>
                                <span class="eval-no-score">Sin PRE</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <?php if ($row['post_score'] !== null): ?>
                                <span class="eval-score-chip eval-score-post"><?= number_format((float)$row['post_score'],0) ?>%</span>
                                <div class="eval-score-date"><?= htmlspecialchars(substr((string)($row['post_at']??''),0,10),ENT_QUOTES,'UTF-8') ?></div>
                            <?php else: ?>
                                <span class="eval-no-score">Sin POST</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <?php if (isset($row['delta']) && $row['delta'] !== null): ?>
                                <?php $d = (float) $row['delta']; ?>
                                <span class="eval-delta <?= $d > 0 ? 'is-pos' : ($d < 0 ? 'is-neg' : '') ?>">
                                    <?= $d > 0 ? '+' : '' ?><?= number_format($d, 1) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="eval-impact-pill <?= $meta['class'] ?>"><?= htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td class="text-end">
                            <div class="d-flex gap-1 justify-content-end flex-wrap">
                                <?php if ($idPre > 0): ?>
                                    <a href="/evaluaciones/detalle?id=<?= $idPre ?>" class="eval-btn-detail" title="Ver PRE">PRE</a>
                                <?php else: ?>
                                    <span class="eval-btn-detail is-disabled" title="Sin PRE">PRE</span>
                                <?php endif; ?>
                                <?php if ($idPost > 0): ?>
                                    <a href="/evaluaciones/detalle?id=<?= $idPost ?>" class="eval-btn-detail is-post" title="Ver POST">POST</a>
                                <?php else: ?>
                                    <span class="eval-btn-detail is-disabled" title="Sin POST">POST</span>
                                <?php endif; ?>
                                <a href="/evaluaciones/exportar-csv?<?= htmlspecialchars($singleExportQuery, ENT_QUOTES, 'UTF-8') ?>" class="eval-btn-detail" title="Exportar Excel por persona">Excel</a>
                                <a href="/evaluaciones/exportar-pdf?<?= htmlspecialchars($singleExportQuery, ENT_QUOTES, 'UTF-8') ?>" class="eval-btn-detail is-post" title="Exportar PDF por persona">PDF</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav class="asi-pagination-wrap" aria-label="Paginación de evaluaciones">
            <p class="asi-pagination-summary mb-0"><?= $totalItems ?> registros en total</p>
            <ul class="pagination asi-pagination mb-0">
                <li class="page-item <?= $currentPage<=1?'disabled':'' ?>">
                    <a class="page-link" href="#" data-eval-page="<?= max(1,$currentPage-1) ?>"><i class="bi bi-chevron-left"></i></a>
                </li>
                <?php $prev=null; foreach($pages as $pg): if($prev!==null&&$pg>$prev+1): ?>
                    <li class="page-item disabled"><span class="page-link">…</span></li>
                <?php endif; ?>
                    <li class="page-item <?= $pg===$currentPage?'active':'' ?>">
                        <a class="page-link" href="#" data-eval-page="<?= $pg ?>"><?= $pg ?></a>
                    </li>
                <?php $prev=$pg; endforeach; ?>
                <li class="page-item <?= $currentPage>=$totalPages?'disabled':'' ?>">
                    <a class="page-link" href="#" data-eval-page="<?= min($totalPages,$currentPage+1) ?>"><i class="bi bi-chevron-right"></i></a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>
