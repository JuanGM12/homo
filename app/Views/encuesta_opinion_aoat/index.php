<?php
/** @var array<int, array<string, mixed>> $records */
/** @var array<string, mixed> $pagination */
/** @var array<string, mixed> $filters */
/** @var array<int, string> $advisors */

$currentSort = (string) ($_GET['sort'] ?? 'created_at');
$currentDir = strtolower((string) ($_GET['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

$exportParams = $_GET;
unset($exportParams['partial'], $exportParams['page']);
$exportExcelParams = $exportParams;
$exportExcelParams['format'] = 'excel';
$exportPdfParams = $exportParams;
$exportPdfParams['format'] = 'pdf';
$exportExcelQuery = '?' . http_build_query($exportExcelParams);
$exportPdfQuery = '?' . http_build_query($exportPdfParams);
?>

<section class="mt-5 mb-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h1 class="section-title mb-1">Consultar encuestas de opinión AoAT</h1>
            <p class="section-subtitle mb-0">Consulta y exportación según el alcance de tu rol. No se permite editar las respuestas registradas.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="/encuesta-opinion-aoat/exportar<?= htmlspecialchars($exportExcelQuery, ENT_QUOTES, 'UTF-8') ?>"
                class="btn btn-outline-success btn-sm" data-encuesta-export-link="excel">
                <i class="bi bi-file-earmark-excel me-1"></i> Exportar Excel
            </a>
            <a href="/encuesta-opinion-aoat/exportar<?= htmlspecialchars($exportPdfQuery, ENT_QUOTES, 'UTF-8') ?>"
                class="btn btn-outline-danger btn-sm" data-encuesta-export-link="pdf">
                <i class="bi bi-file-earmark-pdf me-1"></i> Exportar PDF
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <form method="get" action="/encuesta-opinion-aoat" id="enc-filter-form" data-encuesta-filters class="row g-3 align-items-end">
                <input type="hidden" name="sort" value="<?= htmlspecialchars($currentSort, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="dir" value="<?= htmlspecialchars($currentDir, ENT_QUOTES, 'UTF-8') ?>">

                <div class="col-sm-6 col-md-4 col-lg-3">
                    <label class="form-label small fw-semibold text-muted mb-1">Buscar</label>
                    <input type="text" name="q" class="form-control"
                        placeholder="Asesor, actividad, lugar..."
                        value="<?= htmlspecialchars((string) ($filters['q'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-sm-6 col-md-4 col-lg-2">
                    <label class="form-label small fw-semibold text-muted mb-1">Asesor</label>
                    <select name="advisor" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach ($advisors as $advisor): ?>
                            <option value="<?= htmlspecialchars((string) $advisor, ENT_QUOTES, 'UTF-8') ?>"
                                <?= strtolower((string) ($filters['advisor'] ?? '')) === strtolower((string) $advisor) ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string) $advisor, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-6 col-md-4 col-lg-2">
                    <label class="form-label small fw-semibold text-muted mb-1">Subregión</label>
                    <select name="subregion" class="form-select" data-subregion-select data-current-value="<?= htmlspecialchars((string) ($filters['subregion'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <option value="">Todas</option>
                    </select>
                </div>
                <div class="col-sm-6 col-md-4 col-lg-2">
                    <label class="form-label small fw-semibold text-muted mb-1">Municipio</label>
                    <select name="municipality" class="form-select" data-municipality-select data-current-value="<?= htmlspecialchars((string) ($filters['municipality'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" disabled>
                        <option value="">Todos</option>
                    </select>
                </div>
                <div class="col-sm-6 col-md-4 col-lg-2">
                    <label class="form-label small fw-semibold text-muted mb-1">Desde</label>
                    <input type="date" name="from_date" class="form-control"
                        value="<?= htmlspecialchars((string) ($filters['from_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-sm-6 col-md-4 col-lg-2">
                    <label class="form-label small fw-semibold text-muted mb-1">Hasta</label>
                    <input type="date" name="to_date" class="form-control"
                        value="<?= htmlspecialchars((string) ($filters['to_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-12">
                    <a href="/encuesta-opinion-aoat/listar" class="asi-filter-clear-link">Limpiar filtros</a>
                </div>
            </form>
        </div>
    </div>

    <div data-encuesta-results>
        <?php require __DIR__ . '/_results.php'; ?>
    </div>
</section>
