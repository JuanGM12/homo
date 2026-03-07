<section class="mt-5 mb-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
        <div>
            <h1 class="section-title mb-1">Registro de AoAT</h1>
            <p class="section-subtitle mb-0">
                Asesorías y Asistencias Técnicas diligenciadas por el profesional.
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="/aoat/reportes" class="btn btn-outline-secondary">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i>
                Reporte semanal
            </a>
            <a href="/aoat/nueva" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i>
                Nueva AoAT
            </a>
        </div>
    </div>

    <?php if (empty($records)): ?>
        <div class="alert alert-info border-0 shadow-sm">
            Aún no has registrado AoAT. Utiliza el botón <strong>Nueva AoAT</strong> para crear la primera.
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
                </tr>
                </thead>
                <tbody>
                <?php foreach ($records as $record): ?>
                    <tr>
                        <td><?= (int) $record['id'] ?></td>
                        <td><?= htmlspecialchars((string) $record['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <?= htmlspecialchars($record['professional_name'] . ' ' . $record['professional_last_name'], ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td><?= htmlspecialchars((string) $record['subregion'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) $record['municipality'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <span class="badge rounded-pill text-bg-light">
                                <?= htmlspecialchars((string) $record['state'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

