<?php
/** @var array<int, array<string, mixed>> $records */
/** @var bool|null $isAuditView */

use App\Services\Auth;

$user = Auth::user();
$userId = $user['id'] ?? null;
$roles = $user['roles'] ?? [];
$isAudit = (bool) ($isAuditView ?? false);
?>

<div class="py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-1">
                <?= $isAudit ? 'Planeación anual de capacitaciones · Auditoría' : 'Planeación anual de capacitaciones' ?>
            </h1>
            <p class="text-muted mb-0">
                <?= $isAudit
                    ? 'Visualiza las planeaciones registradas por año, subregión y municipio de los profesionales a tu cargo.'
                    : 'Visualiza tus planeaciones registradas por año, subregión y municipio.'
                ?>
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="/planeacion/exportar" class="btn btn-outline-success">
                <i class="bi bi-file-earmark-excel me-1"></i>
                Exportar (Excel)
            </a>
            <?php if (!$isAudit): ?>
                <a href="/planeacion/nueva" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i>
                    Nueva planeación
                </a>
            <?php endif; ?>
        </div>
    </div>

    <form class="row g-2 mb-3 align-items-end" method="get">
        <div class="col-md-4">
            <label class="form-label small text-muted">Buscar</label>
            <input
                type="text"
                name="q"
                class="form-control form-control-sm"
                placeholder="Subregión, municipio, año…"
                value="<?= htmlspecialchars((string) ($_GET['q'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
            >
        </div>
        <div class="col-md-3">
            <label class="form-label small text-muted">Estado</label>
            <?php $currentState = (string) ($_GET['state'] ?? ''); ?>
            <select name="state" class="form-select form-select-sm">
                <option value="">Todos</option>
                <option value="Editable" <?= $currentState === 'Editable' ? 'selected' : '' ?>>Editable</option>
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
            <a href="/planeacion" class="btn btn-sm btn-outline-secondary w-100">Limpiar</a>
        </div>
    </form>

    <?php if (empty($records)): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <p class="mb-2">Aún no has registrado ninguna planeación anual.</p>
                <a href="/planeacion/nueva" class="btn btn-outline-primary btn-sm">
                    Crear primera planeación
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>Año</th>
                            <th>Subregión</th>
                            <th>Municipio</th>
                            <th>Estado</th>
                            <th>Registrada</th>
                            <th>Acciones</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($records as $plan): ?>
                            <?php
                            $payload = [];
                            if (!empty($plan['payload'])) {
                                $decoded = json_decode((string) $plan['payload'], true);
                                if (is_array($decoded)) {
                                    $payload = $decoded;
                                }
                            }

                                $monthsSummary = [];
                            foreach ($payload as $key => $monthData) {
                                $label = $monthData['label'] ?? ucfirst((string) $key);
                                $topics = $monthData['topics'] ?? [];
                                $population = (string) ($monthData['population'] ?? '');

                                if (($topics && is_array($topics)) || $population !== '') {
                                    $monthsSummary[] = [
                                        'label' => (string) $label,
                                        'topics' => $topics,
                                        'population' => $population,
                                    ];
                                }
                            }

                            $planForJs = [
                                'year' => (int) ($plan['plan_year'] ?? date('Y')),
                                'subregion' => (string) ($plan['subregion'] ?? ''),
                                'municipality' => (string) ($plan['municipality'] ?? ''),
                                'months' => $monthsSummary,
                            ];
                            $planJson = htmlspecialchars(json_encode($planForJs, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                            $isOwner = $userId !== null && (int) ($plan['user_id'] ?? 0) === (int) $userId;
                            ?>
                            <tr>
                                <td><?= htmlspecialchars((string) $plan['plan_year'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) $plan['subregion'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) $plan['municipality'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <?php if (!empty($plan['editable'])): ?>
                                        <span class="badge bg-secondary">Editable</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Aprobada</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($plan['created_at'])): ?>
                                        <?= htmlspecialchars((string) $plan['created_at'], ENT_QUOTES, 'UTF-8') ?>
                                    <?php endif; ?>
                                </td>
                                <td class="d-flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-secondary"
                                        data-plan-details
                                        data-plan="<?= $planJson ?>"
                                    >
                                        <i class="bi bi-eye me-1"></i>
                                        Ver detalles
                                    </button>
                                    <?php if (!empty($plan['editable']) && $isOwner && !$isAudit): ?>
                                        <a href="/planeacion/editar?id=<?= (int) $plan['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-pencil me-1"></i>
                                            Editar
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted small">No editable</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

