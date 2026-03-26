<?php
/** @var array<int, array<string, mixed>> $records */
/** @var array<string, mixed> $pagination */
/** @var bool|null $isAuditView */
/** @var bool|null $canCreateOwnRecord */

$isAudit = (bool) ($isAuditView ?? false);
$currentSort = (string) ($_GET['sort'] ?? 'created_at');
$currentDir = strtolower((string) ($_GET['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
$exportQuery = $_GET;
unset($exportQuery['partial']);
$exportExcelQuery = $exportQuery;
$exportExcelQuery['format'] = 'excel';
$exportPdfQuery = $exportQuery;
$exportPdfQuery['format'] = 'pdf';
$exportExcelHref = '/planeacion/exportar?' . http_build_query($exportExcelQuery);
$exportPdfHref = '/planeacion/exportar?' . http_build_query($exportPdfQuery);
?>

<div class="py-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <h1 class="h4 mb-1">
                <?= $isAudit ? 'Planeacion anual de capacitaciones · Auditoria' : 'Planeacion anual de capacitaciones' ?>
            </h1>
            <p class="text-muted mb-0">
                <?= $isAudit
                    ? 'Visualiza las planeaciones registradas por asesor, ano, subregion y municipio de los profesionales a tu cargo.'
                    : 'Visualiza tus planeaciones registradas por ano, subregion y municipio.'
                ?>
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= htmlspecialchars($exportExcelHref, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-success" data-planeacion-export-link="excel">
                <i class="bi bi-file-earmark-excel me-1"></i>
                Exportar (Excel)
            </a>
            <a href="<?= htmlspecialchars($exportPdfHref, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-danger" data-planeacion-export-link="pdf">
                <i class="bi bi-file-earmark-pdf me-1"></i>
                Exportar (PDF)
            </a>
            <?php if (!empty($canCreateOwnRecord)): ?>
                <a href="/planeacion/nueva" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i>
                    Nueva planeacion
                </a>
            <?php endif; ?>
        </div>
    </div>

    <form class="row g-2 mb-4 align-items-end planeacion-filter-bar" method="get" data-planeacion-filters>
        <input type="hidden" name="sort" value="<?= htmlspecialchars($currentSort, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="dir" value="<?= htmlspecialchars($currentDir, ENT_QUOTES, 'UTF-8') ?>">

        <div class="col-lg-4">
            <label class="form-label small text-muted">Buscar</label>
            <input
                type="text"
                name="q"
                class="form-control"
                placeholder="Asesor, subregion, municipio, ano..."
                value="<?= htmlspecialchars((string) ($_GET['q'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
            >
        </div>

        <div class="col-lg-4">
            <div class="row g-2">
                <div class="col-sm-6">
                    <label class="form-label small text-muted">Desde (fecha registro)</label>
                    <input
                        type="date"
                        name="from_date"
                        class="form-control"
                        value="<?= htmlspecialchars((string) ($_GET['from_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                    >
                </div>
                <div class="col-sm-6">
                    <label class="form-label small text-muted">Hasta (fecha registro)</label>
                    <input
                        type="date"
                        name="to_date"
                        class="form-control"
                        value="<?= htmlspecialchars((string) ($_GET['to_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                    >
                </div>
            </div>
        </div>

        <div class="col-lg-1 d-grid">
            <a href="/planeacion" class="btn btn-outline-secondary">Limpiar</a>
        </div>
    </form>

    <div class="planeacion-results-panel" data-planeacion-results>
        <?php
        $isAuditViewLocal = $isAudit;
        $currentUser = \App\Services\Auth::user() ?? [];
        require __DIR__ . '/_results.php';
        ?>
    </div>
</div>
