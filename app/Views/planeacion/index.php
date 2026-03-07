<?php
/** @var array<int, array<string, mixed>> $records */

use App\Services\Auth;

$user = Auth::user();
?>

<div class="py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-1">Planeación anual de capacitaciones</h1>
            <p class="text-muted mb-0">
                Visualiza tus planeaciones registradas por año, subregión y municipio.
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="/planeacion/exportar" class="btn btn-outline-success">
                <i class="bi bi-file-earmark-excel me-1"></i>
                Exportar (Excel)
            </a>
            <a href="/planeacion/nueva" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>
                Nueva planeación
            </a>
        </div>
    </div>

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
                            <th>Registrada</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($records as $plan): ?>
                            <tr>
                                <td><?= htmlspecialchars((string) $plan['plan_year'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) $plan['subregion'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) $plan['municipality'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <?php if (!empty($plan['created_at'])): ?>
                                        <?= htmlspecialchars((string) $plan['created_at'], ENT_QUOTES, 'UTF-8') ?>
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

