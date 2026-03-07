<?php
/** @var array<int, array<string, mixed>> $records */
?>

<section class="mt-5 mb-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
        <div>
            <h1 class="section-title mb-1">Seguimiento PIC</h1>
            <p class="section-subtitle mb-0">
                Registra y consulta el seguimiento PIC por municipio (Médicos, Psicólogos, Profesional Social).
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="/pic/exportar" class="btn btn-outline-success">
                <i class="bi bi-file-earmark-excel me-1"></i>
                Exportar (Excel)
            </a>
            <a href="/pic/nuevo" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i>
                Nuevo registro
            </a>
        </div>
    </div>

    <?php if (empty($records)): ?>
        <div class="alert alert-info border-0 shadow-sm">
            Aún no has registrado ningún Seguimiento PIC. Utiliza el botón <strong>Nuevo registro</strong> para crear el primero.
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
                <?php foreach ($records as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars((string) ($row['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) ($row['subregion'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) ($row['municipality'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <?php if (!empty($row['editable'])): ?>
                                <span class="badge bg-secondary">Editable</span>
                            <?php else: ?>
                                <span class="badge bg-success">Aprobado</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($row['editable'])): ?>
                                <a href="/pic/editar?id=<?= (int) $row['id'] ?>" class="btn btn-sm btn-outline-primary">
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
    <?php endif; ?>
</section>
