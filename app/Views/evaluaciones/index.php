<?php
/** @var array $tests */
/** @var array $filters */
/** @var array $records */
/** @var array $comparisonRows */
/** @var array $pagination */
/** @var array $impactSummary */
/** @var string $exportQuery */
/** @var array|null $currentUser */
/** @var bool $canSeeAll */

$currentSort = (string) ($_GET['sort'] ?? 'municipality');
$currentDir  = strtolower((string) ($_GET['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
?>

<section class="mb-5">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
        <div>
            <h1 class="section-title mb-1">Evaluaciones · Test</h1>
            <p class="section-subtitle mb-0">Pre y Post Test para medir el cambio en el conocimiento de las personas después de cada temática.</p>
        </div>
    </div>
    <?php require __DIR__ . '/_listado.php'; ?>
</section>

<?php if ($currentUser): ?>
<section class="mb-5">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h2 class="section-title mb-1"><?= $canSeeAll ? 'Todas las evaluaciones registradas' : 'Mis evaluaciones registradas' ?></h2>
            <p class="section-subtitle mb-0">Vista comparativa por persona (PRE y POST). Usa los filtros y exporta con el mismo criterio.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="/evaluaciones/exportar-csv?<?= htmlspecialchars($exportQuery ?? '', ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-success btn-sm" data-eval-export-link data-eval-export-base="/evaluaciones/exportar-csv">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i> Excel
            </a>
            <a href="/evaluaciones/exportar-pdf?<?= htmlspecialchars($exportQuery ?? '', ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-danger btn-sm" data-eval-export-pdf-link>
                <i class="bi bi-file-earmark-pdf me-1"></i> PDF
            </a>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card border-0 shadow-sm rounded-4 mb-4" data-territory-filter>
        <div class="card-body p-4">
            <form method="get" action="/evaluaciones" id="eval-filter-form" data-eval-filters class="row g-3 align-items-end">
                <input type="hidden" name="sort" value="<?= htmlspecialchars($currentSort, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="dir"  value="<?= htmlspecialchars($currentDir,  ENT_QUOTES, 'UTF-8') ?>">
                <div class="col-sm-6 col-md-4 col-lg-2">
                    <label class="form-label small fw-semibold text-muted mb-1">Temática</label>
                    <select name="test_key" class="form-select">
                        <option value="">Todas</option>
                        <?php foreach ($tests as $key => $info): ?>
                            <option value="<?= htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8') ?>" <?= ($filters['test_key'] ?? '') === $key ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string) ($info['name'] ?? $key), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-6 col-md-4 col-lg-2">
                    <label class="form-label small fw-semibold text-muted mb-1">Persona o documento</label>
                    <input type="text" name="search" class="form-control"
                        value="<?= htmlspecialchars((string) ($filters['search'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        <?= !$canSeeAll && !empty($currentUser['document_number']) ? 'readonly' : '' ?>
                        placeholder="Nombre o documento">
                </div>
                <div class="col-sm-6 col-md-4 col-lg-2">
                    <label class="form-label small fw-semibold text-muted mb-1">Subregión</label>
                    <select name="subregion" class="form-select" data-subregion-select
                        data-current-value="<?= htmlspecialchars((string) ($filters['subregion'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <option value="">Todas</option>
                    </select>
                </div>
                <div class="col-sm-6 col-md-4 col-lg-2">
                    <label class="form-label small fw-semibold text-muted mb-1">Municipio</label>
                    <select name="municipality" class="form-select" data-municipality-select
                        data-current-value="<?= htmlspecialchars((string) ($filters['municipality'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" disabled>
                        <option value="">Todos</option>
                    </select>
                </div>
                <div class="col-sm-6 col-md-4 col-lg-2">
                    <label class="form-label small fw-semibold text-muted mb-1">Impacto</label>
                    <select name="impact" class="form-select">
                        <option value="">Todos</option>
                        <option value="mejoria" <?= ($filters['impact'] ?? '') === 'mejoria' ? 'selected' : '' ?>>Con mejoría</option>
                        <option value="sin_cambios" <?= ($filters['impact'] ?? '') === 'sin_cambios' ? 'selected' : '' ?>>Sin cambios</option>
                        <option value="sin_mejoria" <?= ($filters['impact'] ?? '') === 'sin_mejoria' ? 'selected' : '' ?>>Sin mejoría</option>
                        <option value="pendiente_post" <?= ($filters['impact'] ?? '') === 'pendiente_post' ? 'selected' : '' ?>>Pendiente POST</option>
                        <option value="pendiente_pre" <?= ($filters['impact'] ?? '') === 'pendiente_pre' ? 'selected' : '' ?>>Sin PRE</option>
                    </select>
                </div>
                <div class="col-sm-6 col-md-4 col-lg-2">
                    <label class="form-label small fw-semibold text-muted mb-1">Desde</label>
                    <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars((string) ($filters['date_from'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-sm-6 col-md-4 col-lg-2">
                    <label class="form-label small fw-semibold text-muted mb-1">Hasta</label>
                    <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars((string) ($filters['date_to'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-12 d-flex align-items-center gap-3">
                    <a href="/evaluaciones" class="asi-filter-clear-link">Limpiar</a>
                    <?php if (!$canSeeAll && !empty($currentUser['document_number'])): ?>
                        <span class="text-muted small">Mostrando resultados de: <strong><?= htmlspecialchars((string) $currentUser['document_number'], ENT_QUOTES, 'UTF-8') ?></strong></span>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div data-eval-results>
        <?php require __DIR__ . '/_results.php'; ?>
    </div>
</section>
<?php endif; ?>
