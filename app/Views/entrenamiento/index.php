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
$exportPdfHref = '/entrenamiento/exportar-pdf';
if ($exportQuery !== []) {
    $qs = http_build_query($exportQuery);
    $exportHref .= '?' . $qs;
    $exportPdfHref .= '?' . $qs;
}

$filterSubregion = (string) ($_GET['subregion'] ?? '');
$filterMunicipalities = $_GET['municipality'] ?? [];
if (!is_array($filterMunicipalities)) {
    $filterMunicipalities = $filterMunicipalities !== '' && $filterMunicipalities !== null ? [(string) $filterMunicipalities] : [];
}
$filterMunicipalities = array_values(array_filter(array_map('strval', $filterMunicipalities), static fn (string $m): bool => $m !== ''));
$municipalitiesJson = htmlspecialchars(json_encode($filterMunicipalities, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
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
            <a href="<?= htmlspecialchars($exportPdfHref, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-danger" data-entrenamiento-export-pdf-link>
                <i class="bi bi-file-earmark-pdf me-1"></i>
                Exportar (PDF)
            </a>
            <?php if (!empty($canCreateOwnRecord)): ?>
                <a href="/entrenamiento/nuevo" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-1"></i>
                    Nuevo plan
                </a>
            <?php endif; ?>
        </div>
    </div>

    <form class="row g-2 mb-4 align-items-end entrenamiento-filter-bar" method="get" data-entrenamiento-filters data-territory-filter>
        <input type="hidden" name="sort" value="<?= htmlspecialchars($currentSort, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="dir" value="<?= htmlspecialchars($currentDir, ENT_QUOTES, 'UTF-8') ?>">

        <div class="col-lg-2">
            <label class="form-label small text-muted">Buscar</label>
            <input
                type="text"
                name="q"
                class="form-control"
                placeholder="Profesional, correo..."
                value="<?= htmlspecialchars((string) ($_GET['q'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
            >
        </div>
        <div class="col-lg-2">
            <label class="form-label small text-muted">Subregión</label>
            <select
                name="subregion"
                class="form-select"
                data-subregion-select
                data-current-value="<?= htmlspecialchars($filterSubregion, ENT_QUOTES, 'UTF-8') ?>"
            >
                <option value="">Todas</option>
            </select>
        </div>
        <div class="col-lg-2">
            <label class="form-label small text-muted">Municipio(s)</label>
            <select
                name="municipality[]"
                class="form-select"
                multiple
                data-municipality-select
                data-municipality-multi="1"
                data-current-values="<?= $municipalitiesJson ?>"
                disabled
            ></select>
        </div>

        <div class="col-lg-2">
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
            <a href="/entrenamiento" class="btn btn-outline-secondary" data-homo-filter-clear="/entrenamiento">Limpiar</a>
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
