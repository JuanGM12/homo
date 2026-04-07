<?php
/** @var array<int, array<string, mixed>> $records */
/** @var bool|null $isAuditView */
/** @var array<string, mixed> $pagination */

use App\Services\Auth;

$user = Auth::user();
$userRoles = $user['roles'] ?? [];
$isAudit = (bool) ($isAuditView ?? false);
$isSpecialist = in_array('especialista', $userRoles ?? [], true);
$isCoordinator = in_array('coordinadora', $userRoles ?? [], true) || in_array('coordinador', $userRoles ?? [], true);
$isAdmin = in_array('admin', $userRoles ?? [], true);
$canUseWeeklyReport = $isAdmin || $isCoordinator;

$exportParams = $_GET;
unset($exportParams['partial']);
$exportQuery = $exportParams ? ('?' . http_build_query($exportParams)) : '';
?>

<section class="mt-5 mb-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
        <div>
            <h1 class="section-title mb-1"><?= $isAudit ? 'Registro de AoAT · Auditoría' : 'Registro de AoAT' ?></h1>
            <p class="section-subtitle mb-0">
                <?= $isAudit
                    ? 'Visualiza y audita los registros de AoAT de los profesionales a tu cargo.'
                    : 'Asesorías y asistencias técnicas diligenciadas por el profesional.'
                ?>
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <?php if ($isAudit): ?>
                <a
                    href="/aoat/exportar<?= htmlspecialchars($exportQuery, ENT_QUOTES, 'UTF-8') ?>"
                    class="btn btn-outline-success"
                    data-aoat-export-link
                >
                    <i class="bi bi-download me-1"></i>
                    Exportar CSV
                </a>
            <?php endif; ?>
            <?php if ($canUseWeeklyReport): ?>
                <a href="/aoat/reportes" class="btn btn-outline-secondary">
                    <i class="bi bi-file-earmark-spreadsheet me-1"></i>
                    Reporte semanal
                </a>
            <?php endif; ?>
            <?php $canRegisterNewAoat = !$isAudit || $isSpecialist; ?>
            <?php if ($canRegisterNewAoat): ?>
                <a href="/aoat/nueva" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-1"></i>
                    Nueva AoAT
                </a>
            <?php endif; ?>
        </div>
    </div>

    <form class="row g-2 mb-4 align-items-end aoat-filter-bar" method="get" data-aoat-filters>
        <input type="hidden" name="sort" value="<?= htmlspecialchars((string) ($_GET['sort'] ?? 'activity_date'), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="dir" value="<?= htmlspecialchars((string) ($_GET['dir'] ?? 'desc'), ENT_QUOTES, 'UTF-8') ?>">
        <div class="col-md-3">
            <label class="form-label small text-muted">Buscar</label>
            <input
                type="text"
                name="q"
                class="form-control form-control-sm"
                placeholder="Profesional, subregión, municipio..."
                value="<?= htmlspecialchars((string) ($_GET['q'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
            >
        </div>
        <div class="col-md-2">
            <label class="form-label small text-muted">Estado AoAT</label>
            <?php $currentState = (string) ($_GET['state'] ?? ''); ?>
            <select name="state" class="form-select form-select-sm">
                <option value="">Todos</option>
                <option value="Asignada" <?= $currentState === 'Asignada' ? 'selected' : '' ?>>Asignada</option>
                <option value="Devuelta" <?= $currentState === 'Devuelta' ? 'selected' : '' ?>>Devuelta</option>
                <option value="Realizado" <?= $currentState === 'Realizado' ? 'selected' : '' ?>>Realizado</option>
                <option value="Aprobada" <?= $currentState === 'Aprobada' ? 'selected' : '' ?>>Aprobada</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small text-muted">Actividad que realizó</label>
            <?php $currentActivityType = (string) ($_GET['activity_type'] ?? ''); ?>
            <select name="activity_type" class="form-select form-select-sm">
                <option value="">Todas</option>
                <option value="Asistencia técnica" <?= $currentActivityType === 'Asistencia técnica' ? 'selected' : '' ?>>Asistencia técnica</option>
                <option value="Asesoría" <?= $currentActivityType === 'Asesoría' ? 'selected' : '' ?>>Asesoría</option>
                <option value="Actividad" <?= $currentActivityType === 'Actividad' ? 'selected' : '' ?>>Actividad</option>
            </select>
        </div>
        <div class="col-md-3">
            <div class="row g-2">
                <div class="col-6">
                    <label class="form-label small text-muted">Desde (fecha actividad)</label>
                    <input
                        type="date"
                        name="from_date"
                        class="form-control form-control-sm"
                        value="<?= htmlspecialchars((string) ($_GET['from_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                    >
                </div>
                <div class="col-6">
                    <label class="form-label small text-muted">Hasta (fecha actividad)</label>
                    <input
                        type="date"
                        name="to_date"
                        class="form-control form-control-sm"
                        value="<?= htmlspecialchars((string) ($_GET['to_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                    >
                </div>
            </div>
        </div>
        <div class="col-md-1 d-flex gap-2">
            <a href="/aoat" class="btn btn-sm btn-outline-secondary w-100">Limpiar</a>
        </div>
    </form>

    <div class="aoat-results-panel shadow-sm" data-aoat-results>
        <?php require __DIR__ . '/_results.php'; ?>
    </div>
</section>

<form id="aoat-state-form" method="post" action="/aoat/cambiar-estado" class="d-none">
    <input type="hidden" name="id" id="aoat-state-id">
    <input type="hidden" name="state" id="aoat-state-value">
    <input type="hidden" name="observation" id="aoat-state-observation">
    <input type="hidden" name="motive" id="aoat-state-motive">
</form>
