<?php
/** @var array $records */
?>
<section class="mt-5 mb-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
        <div>
            <h1 class="section-title mb-1">Consultar encuestas de opinión AoAT</h1>
            <p class="section-subtitle mb-0">
                Solo consulta y exportación. No se permite editar las respuestas registradas.
            </p>
        </div>
        <a href="/encuesta-opinion-aoat/exportar" class="btn btn-success">
            <i class="bi bi-file-earmark-excel me-1"></i>
            Exportar Excel (CSV)
        </a>
    </div>

    <?php if (empty($records)): ?>
        <div class="alert alert-info border-0 shadow-sm">
            No hay encuestas de opinión registradas.
        </div>
    <?php else: ?>
        <div class="table-responsive shadow-sm rounded-4 bg-white p-3">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Fecha registro</th>
                        <th scope="col">Asesor</th>
                        <th scope="col">Actividad</th>
                        <th scope="col">Lugar</th>
                        <th scope="col">Fecha actividad</th>
                        <th scope="col">Subregión</th>
                        <th scope="col">Municipio</th>
                        <th scope="col">Promedio (1-5)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $row): ?>
                        <?php
                        $prom = (
                            (int) ($row['score_objetivos'] ?? 0) +
                            (int) ($row['score_claridad'] ?? 0) +
                            (int) ($row['score_pertinencia'] ?? 0) +
                            (int) ($row['score_ayudas'] ?? 0) +
                            (int) ($row['score_relacion'] ?? 0) +
                            (int) ($row['score_puntualidad'] ?? 0)
                        ) / 6;
                        ?>
                        <tr>
                            <td><?= htmlspecialchars((string) ($row['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) ($row['advisor_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) ($row['actividad'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) ($row['lugar'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) ($row['activity_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) ($row['subregion'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) ($row['municipality'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= number_format($prom, 1, ',', '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
