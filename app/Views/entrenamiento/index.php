<?php
/** @var array<int, array<string, mixed>> $records */
/** @var bool|null $isAuditView */

use App\Services\Auth;

$user = Auth::user();
$userId = $user['id'] ?? null;
$isAudit = (bool) ($isAuditView ?? false);
?>

<section class="mt-5 mb-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
        <div>
            <h1 class="section-title mb-1">
                <?= $isAudit ? 'Plan de Entrenamiento · Auditoría' : 'Plan de Entrenamiento' ?>
            </h1>
            <p class="section-subtitle mb-0">
                <?= $isAudit
                    ? 'Consulta los planes de entrenamiento registrados por los profesionales a tu cargo.'
                    : 'Registra y consulta tus planes de entrenamiento (Psicólogos).'
                ?>
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="/entrenamiento/exportar" class="btn btn-outline-success">
                <i class="bi bi-file-earmark-excel me-1"></i>
                Exportar (Excel)
            </a>
            <?php if (!$isAudit): ?>
                <a href="/entrenamiento/nuevo" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-1"></i>
                    Nuevo plan
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (empty($records)): ?>
        <div class="alert alert-info border-0 shadow-sm">
            Aún no has registrado ningún plan de entrenamiento. Utiliza el botón <strong>Nuevo plan</strong> para crear el primero.
        </div>
    <?php else: ?>
        <div class="table-responsive shadow-sm rounded-4 bg-white p-3">
            <table class="table align-middle mb-0">
                <thead>
                <tr>
                    <th scope="col">Fecha registro</th>
                    <th scope="col">Subregión</th>
                    <th scope="col">Municipio</th>
                    <th scope="col">Estado</th>
                    <th scope="col">Acciones</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($records as $plan): ?>
                    <?php $isOwner = $userId !== null && (int) ($plan['user_id'] ?? 0) === (int) $userId; ?>
                    <tr>
                        <td><?= htmlspecialchars((string) ($plan['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) ($plan['subregion'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) ($plan['municipality'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <?php if (!empty($plan['editable'])): ?>
                                <span class="badge bg-secondary">Editable</span>
                            <?php else: ?>
                                <span class="badge bg-success">Aprobado</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($plan['editable']) && $isOwner && !$isAudit): ?>
                                <a href="/entrenamiento/editar?id=<?= (int) $plan['id'] ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-pencil me-1"></i>
                                    Editar
                                </a>
                            <?php else: ?>
                                <span class="text-muted small">Sin acciones</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
