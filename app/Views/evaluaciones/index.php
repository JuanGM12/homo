<?php
/** @var array $tests */
/** @var array $filters */
/** @var array $records */
/** @var array|null $currentUser */
/** @var bool $canSeeAll */
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
                    Aplica filtros por temática, fase, documento o fecha para revisar los resultados.
                </p>
            </div>
        </div>

        <form method="get" class="card border-0 shadow-sm mb-3">
            <div class="card-body p-3 p-md-4">
                <div class="row g-3 align-items-end">
                    <div class="col-sm-6 col-md-3 col-lg-2">
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
                    <div class="col-sm-6 col-md-3 col-lg-2">
                        <label class="form-label small">Fase</label>
                        <select name="phase" class="form-select form-select-sm">
                            <option value="">Pre y Post</option>
                            <option value="pre" <?= ($filters['phase'] ?? '') === 'pre' ? 'selected' : '' ?>>PRE - TEST</option>
                            <option value="post" <?= ($filters['phase'] ?? '') === 'post' ? 'selected' : '' ?>>POST - TEST</option>
                        </select>
                    </div>
                    <div class="col-sm-6 col-md-3 col-lg-2">
                        <label class="form-label small">Documento</label>
                        <input
                            type="text"
                            name="document_number"
                            class="form-control form-control-sm"
                            value="<?= htmlspecialchars((string) ($filters['document_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        >
                    </div>
                    <div class="col-sm-6 col-md-3 col-lg-2">
                        <label class="form-label small">Desde</label>
                        <input
                            type="date"
                            name="date_from"
                            class="form-control form-control-sm"
                            value="<?= htmlspecialchars((string) ($filters['date_from'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        >
                    </div>
                    <div class="col-sm-6 col-md-3 col-lg-2">
                        <label class="form-label small">Hasta</label>
                        <input
                            type="date"
                            name="date_to"
                            class="form-control form-control-sm"
                            value="<?= htmlspecialchars((string) ($filters['date_to'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        >
                    </div>
                    <div class="col-sm-6 col-md-3 col-lg-2">
                        <button type="submit" class="btn btn-primary btn-sm w-100">
                            <i class="bi bi-funnel me-1"></i> Filtrar
                        </button>
                    </div>
                </div>
                <?php if (!$canSeeAll && !empty($currentUser['document_number'])): ?>
                    <p class="text-muted small mt-2 mb-0">
                        Solo se muestran las evaluaciones asociadas a tu número de documento:
                        <strong><?= htmlspecialchars((string) $currentUser['document_number'], ENT_QUOTES, 'UTF-8') ?></strong>.
                    </p>
                <?php elseif ($canSeeAll): ?>
                    <p class="text-muted small mt-2 mb-0">
                        Como <?= in_array('admin', $currentUser['roles'] ?? [], true) ? 'administrador' : 'coordinador' ?> puedes ver todas las evaluaciones registradas en el sistema.
                    </p>
                <?php endif; ?>
            </div>
        </form>

        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th>Fecha</th>
                        <th>Temática</th>
                        <th>Fase</th>
                        <th>Documento</th>
                        <th>Persona</th>
                        <th>Subregión</th>
                        <th>Municipio</th>
                        <th class="text-end">Puntaje</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($records === []): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                No se encontraron evaluaciones con los filtros seleccionados.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($records as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars((string) ($row['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <?php
                                    $key = (string) ($row['test_key'] ?? '');
                                    $info = $tests[$key] ?? ['name' => $key];
                                    echo htmlspecialchars((string) ($info['name'] ?? $key), ENT_QUOTES, 'UTF-8');
                                    ?>
                                </td>
                                <td>
                                    <?php if (($row['phase'] ?? '') === 'pre'): ?>
                                        <span class="badge bg-info-subtle text-info">PRE</span>
                                    <?php else: ?>
                                        <span class="badge bg-success-subtle text-success">POST</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="text-primary"><?= htmlspecialchars((string) ($row['document_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td><?= htmlspecialchars(trim((string) (($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($row['subregion'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($row['municipality'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="text-end">
                                    <?php $score = (float) ($row['score_percent'] ?? 0); ?>
                                    <span class="fw-semibold"><?= number_format($score, 0) ?>%</span>
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
