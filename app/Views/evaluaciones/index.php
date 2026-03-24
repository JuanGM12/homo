<?php
/** @var array $tests */
/** @var array $filters */
/** @var array $records */
/** @var array $comparisonRows */
/** @var array $impactSummary */
/** @var string $exportQuery */
/** @var array|null $currentUser */
/** @var bool $canSeeAll */

$impactGlobal = $impactSummary['global'] ?? null;
$impactByMun = $impactSummary['by_municipality'] ?? [];
?>

<section class="mb-5">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
        <div>
            <h1 class="section-title mb-1">Evaluaciones · Test</h1>
            <p class="section-subtitle mb-0">
                Pre y Post Test para medir el cambio en el conocimiento de las personas después de cada temática.
            </p>
        </div>
    </div>

    <?php require __DIR__ . '/_listado.php'; ?>
</section>

<?php if ($currentUser): ?>
    <section class="mb-5">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
            <div>
                <h2 class="section-title mb-1">
                    <?= $canSeeAll ? 'Todas las evaluaciones registradas' : 'Mis evaluaciones registradas' ?>
                </h2>
                <p class="section-subtitle mb-0">
                    Vista comparativa por persona (PRE y POST). Usa los filtros y exporta a Excel o PDF con el mismo criterio.
                </p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="/evaluaciones/exportar-csv?<?= htmlspecialchars($exportQuery ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   class="btn btn-success btn-sm">
                    <i class="bi bi-file-earmark-spreadsheet me-1"></i> Excel (CSV)
                </a>
                <a href="/evaluaciones/exportar-pdf?<?= htmlspecialchars($exportQuery ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   class="btn btn-danger btn-sm">
                    <i class="bi bi-file-earmark-pdf me-1"></i> PDF
                </a>
            </div>
        </div>

        <form method="get" action="/evaluaciones" class="card border-0 shadow-sm mb-3" data-territory-filter>
            <div class="card-body p-3 p-md-4">
                <div class="row g-3 align-items-end">
                    <div class="col-sm-6 col-md-4 col-lg-2">
                        <label class="form-label small">Temática</label>
                        <select name="test_key" class="form-select form-select-sm">
                            <option value="">Todas</option>
                            <?php foreach ($tests as $key => $info): ?>
                                <option value="<?= htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8') ?>"
                                    <?= ($filters['test_key'] ?? '') === $key ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string) ($info['name'] ?? $key), ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-6 col-md-4 col-lg-2">
                        <label class="form-label small">Documento</label>
                        <input
                            type="text"
                            name="document_number"
                            class="form-control form-control-sm"
                            value="<?= htmlspecialchars((string) ($filters['document_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            <?= !$canSeeAll && !empty($currentUser['document_number']) ? 'readonly' : '' ?>
                        >
                    </div>
                    <div class="col-sm-6 col-md-4 col-lg-2">
                        <label class="form-label small">Subregión</label>
                        <select
                            name="subregion"
                            class="form-select form-select-sm"
                            data-subregion-select
                            data-current-value="<?= htmlspecialchars((string) ($filters['subregion'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        >
                            <option value="">Todas las subregiones</option>
                        </select>
                    </div>
                    <div class="col-sm-6 col-md-4 col-lg-2">
                        <label class="form-label small">Municipio</label>
                        <select
                            name="municipality"
                            class="form-select form-select-sm"
                            data-municipality-select
                            data-current-value="<?= htmlspecialchars((string) ($filters['municipality'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            disabled
                        >
                            <option value="">Todos los municipios</option>
                        </select>
                    </div>
                    <div class="col-sm-6 col-md-4 col-lg-2">
                        <label class="form-label small">Desde</label>
                        <input
                            type="date"
                            name="date_from"
                            class="form-control form-control-sm"
                            value="<?= htmlspecialchars((string) ($filters['date_from'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        >
                    </div>
                    <div class="col-sm-6 col-md-4 col-lg-2">
                        <label class="form-label small">Hasta</label>
                        <input
                            type="date"
                            name="date_to"
                            class="form-control form-control-sm"
                            value="<?= htmlspecialchars((string) ($filters['date_to'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        >
                    </div>
                    <div class="col-sm-6 col-md-4 col-lg-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                            <i class="bi bi-funnel me-1"></i> Filtrar
                        </button>
                        <a href="/evaluaciones" class="btn btn-outline-secondary btn-sm">Limpiar</a>
                    </div>
                </div>
                <?php if (!$canSeeAll && !empty($currentUser['document_number'])): ?>
                    <p class="text-muted small mt-2 mb-0">
                        Solo se muestran las evaluaciones asociadas a tu número de documento:
                        <strong><?= htmlspecialchars((string) $currentUser['document_number'], ENT_QUOTES, 'UTF-8') ?></strong>.
                    </p>
                <?php elseif ($canSeeAll): ?>
                    <p class="text-muted small mt-2 mb-0">
                        Como <?= in_array('admin', $currentUser['roles'] ?? [], true) ? 'administrador' : 'coordinador/a o especialista' ?> puedes ver todas las evaluaciones registradas en el sistema.
                    </p>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($comparisonRows !== [] && is_array($impactGlobal)): ?>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white border-0 py-3">
                    <h3 class="h6 mb-0 fw-semibold">
                        <i class="bi bi-bar-chart me-1 text-success"></i>
                        Resultado impacto global (filtro actual) — solo personas con PRE y POST
                    </h3>
                </div>
                <div class="card-body pt-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                            <tr>
                                <th>Municipio</th>
                                <th class="text-end">N° con PRE+POST</th>
                                <th class="text-end text-success">Con mejoría</th>
                                <th class="text-end text-secondary">Sin cambios</th>
                                <th class="text-end text-danger">Sin mejoría</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr class="table-success bg-opacity-25">
                                <td><strong><?= htmlspecialchars((string) ($impactGlobal['municipality'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong></td>
                                <td class="text-end"><?= (int) ($impactGlobal['con_ambos'] ?? 0) ?></td>
                                <td class="text-end">
                                    <span class="badge bg-success"><?= htmlspecialchars((string) ($impactGlobal['pct_mejoria'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>%</span>
                                    <span class="text-muted small">(<?= (int) ($impactGlobal['mejoria'] ?? 0) ?>)</span>
                                </td>
                                <td class="text-end">
                                    <span class="badge bg-secondary"><?= htmlspecialchars((string) ($impactGlobal['pct_sin_cambios'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>%</span>
                                    <span class="text-muted small">(<?= (int) ($impactGlobal['sin_cambios'] ?? 0) ?>)</span>
                                </td>
                                <td class="text-end">
                                    <span class="badge bg-danger"><?= htmlspecialchars((string) ($impactGlobal['pct_sin_mejoria'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>%</span>
                                    <span class="text-muted small">(<?= (int) ($impactGlobal['sin_mejoria'] ?? 0) ?>)</span>
                                </td>
                            </tr>
                            <?php foreach ($impactByMun as $munRow): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string) ($munRow['municipality'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-end"><?= (int) ($munRow['con_ambos'] ?? 0) ?></td>
                                    <td class="text-end">
                                        <span class="badge bg-success"><?= htmlspecialchars((string) ($munRow['pct_mejoria'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>%</span>
                                    </td>
                                    <td class="text-end">
                                        <span class="badge bg-secondary"><?= htmlspecialchars((string) ($munRow['pct_sin_cambios'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>%</span>
                                    </td>
                                    <td class="text-end">
                                        <span class="badge bg-danger"><?= htmlspecialchars((string) ($munRow['pct_sin_mejoria'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>%</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 py-3">
                <h3 class="h6 mb-0 fw-semibold">
                    <i class="bi bi-people me-1 text-primary"></i>
                    Comparativo por persona (una fila por documento y temática)
                </h3>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th>Temática</th>
                        <th>Documento</th>
                        <th>Persona</th>
                        <th>Subregión</th>
                        <th>Municipio</th>
                        <th class="text-end">PRE</th>
                        <th class="text-end">POST</th>
                        <th class="text-end">Δ</th>
                        <th>Resultado impacto</th>
                        <th class="text-end">Detalle</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($comparisonRows === []): ?>
                        <tr>
                            <td colspan="10" class="text-center text-muted py-4">
                                No se encontraron evaluaciones con los filtros seleccionados.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($comparisonRows as $row): ?>
                            <tr>
                                <td>
                                    <?php
                                    $key = (string) ($row['test_key'] ?? '');
                                    $info = $tests[$key] ?? ['name' => $key];
                                    echo htmlspecialchars((string) ($info['name'] ?? $key), ENT_QUOTES, 'UTF-8');
                                    ?>
                                </td>
                                <td><span class="text-primary fw-medium"><?= htmlspecialchars((string) ($row['document_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td><?= htmlspecialchars(trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($row['subregion'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($row['municipality'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="text-end">
                                    <?php if ($row['pre_score'] !== null): ?>
                                        <span class="fw-semibold"><?= number_format((float) $row['pre_score'], 0) ?>%</span>
                                        <div class="small text-muted"><?= htmlspecialchars(substr((string) ($row['pre_at'] ?? ''), 0, 10), ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border">Sin diligenciar</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($row['post_score'] !== null): ?>
                                        <span class="fw-semibold"><?= number_format((float) $row['post_score'], 0) ?>%</span>
                                        <div class="small text-muted"><?= htmlspecialchars(substr((string) ($row['post_at'] ?? ''), 0, 10), ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border">Sin diligenciar</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if (isset($row['delta']) && $row['delta'] !== null): ?>
                                        <span class="fw-semibold"><?= $row['delta'] > 0 ? '+' : '' ?><?= htmlspecialchars(number_format((float) $row['delta'], 1), ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $code = (string) ($row['impact'] ?? '');
                                    $lbl = (string) ($row['impact_label'] ?? '');
                                    $badge = 'bg-secondary';
                                    if ($code === 'mejoria') {
                                        $badge = 'bg-success';
                                    } elseif ($code === 'sin_mejoria') {
                                        $badge = 'bg-danger';
                                    } elseif ($code === 'sin_cambios') {
                                        $badge = 'bg-secondary';
                                    } elseif ($code === 'pendiente_post') {
                                        $badge = 'bg-warning text-dark';
                                    }
                                    ?>
                                    <span class="badge <?= $badge ?>"><?= htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8') ?></span>
                                </td>
                                <td class="text-end text-nowrap">
                                    <?php
                                    $preRow = is_array($row['pre'] ?? null) ? $row['pre'] : null;
                                    $postRow = is_array($row['post'] ?? null) ? $row['post'] : null;
                                    $idPre = $preRow !== null ? (int) ($preRow['id'] ?? 0) : 0;
                                    $idPost = $postRow !== null ? (int) ($postRow['id'] ?? 0) : 0;
                                    ?>
                                    <?php if ($idPre > 0): ?>
                                        <a href="/evaluaciones/detalle?id=<?= $idPre ?>"
                                           class="btn btn-outline-primary btn-sm mb-1 mb-md-0">
                                            <i class="bi bi-eye me-1"></i> PRE
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($idPost > 0): ?>
                                        <a href="/evaluaciones/detalle?id=<?= $idPost ?>"
                                           class="btn btn-outline-success btn-sm">
                                            <i class="bi bi-eye me-1"></i> POST
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($idPre <= 0 && $idPost <= 0): ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
<?php endif; ?>
