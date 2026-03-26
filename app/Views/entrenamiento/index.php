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
$exportHref = '/entrenamiento/exportar';
if ($exportQuery !== []) {
    $exportHref .= '?' . http_build_query($exportQuery);
}
?>

<section class="mt-5 mb-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <h1 class="section-title mb-1">
                <?= $isAudit ? 'Plan de Entrenamiento · Auditoria' : 'Plan de Entrenamiento' ?>
            </h1>
            <p class="section-subtitle mb-0">
                <?= $isAudit
                    ? 'Consulta los planes de entrenamiento registrados por los profesionales a tu cargo.'
                    : 'Registra y consulta tus planes de entrenamiento.'
                ?>
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= htmlspecialchars($exportHref, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-success" data-entrenamiento-export-link>
                <i class="bi bi-file-earmark-excel me-1"></i>
                Exportar (Excel)
            </a>
            <?php if (!empty($canCreateOwnRecord)): ?>
                <a href="/entrenamiento/nuevo" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-1"></i>
                    Nuevo plan
                </a>
            <?php endif; ?>
        </div>
    </div>

    <form class="row g-2 mb-4 align-items-end entrenamiento-filter-bar" method="get" data-entrenamiento-filters>
        <input type="hidden" name="sort" value="<?= htmlspecialchars($currentSort, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="dir" value="<?= htmlspecialchars($currentDir, ENT_QUOTES, 'UTF-8') ?>">

        <div class="col-lg-4">
            <label class="form-label small text-muted">Buscar</label>
            <input
                type="text"
                name="q"
                class="form-control"
                placeholder="Profesional, subregion, municipio..."
                value="<?= htmlspecialchars((string) ($_GET['q'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
            >
        </div>

        <div class="col-lg-3">
            <label class="form-label small text-muted">Estado</label>
            <?php $currentState = (string) ($_GET['state'] ?? ''); ?>
            <select name="state" class="form-select">
                <option value="">Todos</option>
                <option value="Editable" <?= $currentState === 'Editable' ? 'selected' : '' ?>>Editable</option>
                <option value="Aprobado" <?= $currentState === 'Aprobado' ? 'selected' : '' ?>>Aprobado</option>
            </select>
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
            <a href="/entrenamiento" class="btn btn-outline-secondary">Limpiar</a>
        </div>
    </form>

    <div class="entrenamiento-results-panel" data-entrenamiento-results>
        <?php
        $isAuditViewLocal = $isAudit;
        $currentUser = \App\Services\Auth::user() ?? [];
        require __DIR__ . '/_results.php';
        ?>
    </div>
</section>
