<?php
/** @var array<int, array<string, mixed>> $records */
/** @var bool|null $isAuditView */

use App\Services\Auth;

$user = Auth::user();
$userId = $user['id'] ?? null;
$userRoles = $user['roles'] ?? [];
$isAudit = (bool) ($isAuditView ?? false);
$isSpecialist = in_array('especialista', $userRoles ?? [], true);
$isCoordinator = in_array('coordinadora', $userRoles ?? [], true) || in_array('coordinador', $userRoles ?? [], true);
$isAdmin = in_array('admin', $userRoles ?? [], true);
$canUseWeeklyReport = $isAdmin || $isCoordinator;
$exportQuery = $_GET ? ('?' . http_build_query($_GET)) : '';
?>

<section class="mt-5 mb-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
        <div>
            <h1 class="section-title mb-1"><?= $isAudit ? 'Registro de AoAT · Auditoría' : 'Registro de AoAT' ?></h1>
            <p class="section-subtitle mb-0">
                <?= $isAudit
                    ? 'Visualiza y audita los registros de AoAT de los profesionales a tu cargo.'
                    : 'Asesorías y Asistencias Técnicas diligenciadas por el profesional.'
                ?>
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <?php if ($isAudit): ?>
                <a href="/aoat/exportar<?= htmlspecialchars($exportQuery, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-success">
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
            <?php if (!$isAudit): ?>
                <a href="/aoat/nueva" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-1"></i>
                    Nueva AoAT
                </a>
            <?php endif; ?>
        </div>
    </div>

    <form class="row g-2 mb-3 align-items-end" method="get" data-aoat-filters>
        <div class="col-md-4">
            <label class="form-label small text-muted">Buscar</label>
            <input
                type="text"
                name="q"
                class="form-control form-control-sm"
                placeholder="Profesional, subregión, municipio..."
                value="<?= htmlspecialchars((string) ($_GET['q'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
            >
        </div>
        <div class="col-md-3">
            <label class="form-label small text-muted">Estado AoAT</label>
            <?php $currentState = (string) ($_GET['state'] ?? ''); ?>
            <select name="state" class="form-select form-select-sm">
                <option value="">Todos</option>
                <option value="Asignada" <?= $currentState === 'Asignada' ? 'selected' : '' ?>>Asignada</option>
                <option value="Devuelta" <?= $currentState === 'Devuelta' ? 'selected' : '' ?>>Devuelta</option>
                <option value="Aprobada" <?= $currentState === 'Aprobada' ? 'selected' : '' ?>>Aprobada</option>
            </select>
        </div>
        <div class="col-md-4">
            <div class="row g-2">
                <div class="col-6">
                    <label class="form-label small text-muted">Desde (fecha registro)</label>
                    <input
                        type="date"
                        name="from_date"
                        class="form-control form-control-sm"
                        value="<?= htmlspecialchars((string) ($_GET['from_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                    >
                </div>
                <div class="col-6">
                    <label class="form-label small text-muted">Hasta (fecha registro)</label>
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

    <?php if (empty($records)): ?>
        <div class="alert alert-info border-0 shadow-sm">
            <?= $isAudit
                ? 'Aún no hay registros de AoAT para auditar en tu perfil.'
                : 'Aún no has registrado AoAT. Utiliza el botón <strong>Nueva AoAT</strong> para crear la primera.'
            ?>
        </div>
    <?php else: ?>
        <div class="table-responsive shadow-sm rounded-4 bg-white p-3">
            <table class="table align-middle mb-0">
                <thead>
                <tr>
                    <th scope="col">ID</th>
                    <th scope="col">Fecha</th>
                    <th scope="col">Profesional</th>
                    <th scope="col">Subregión</th>
                    <th scope="col">Municipio</th>
                    <th scope="col">Estado AoAT</th>
                    <th scope="col">Acciones</th>
                </tr>
                </thead>
                <tbody data-aoat-tbody>
                <?php require __DIR__ . '/_rows.php'; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<form id="aoat-state-form" method="post" action="/aoat/cambiar-estado" class="d-none">
    <input type="hidden" name="id" id="aoat-state-id">
    <input type="hidden" name="state" id="aoat-state-value">
    <input type="hidden" name="observation" id="aoat-state-observation">
    <input type="hidden" name="motive" id="aoat-state-motive">
</form>

