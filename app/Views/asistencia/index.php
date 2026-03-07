<?php
/** @var array $records */
/** @var array $advisors */
/** @var array $filters */
?>
<section class="mt-5 mb-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
        <div>
            <h1 class="section-title mb-1">Listados de Asistencia</h1>
            <p class="section-subtitle mb-0">Gestión de actividades y registro de asistentes.</p>
        </div>
        <a href="/asistencia/nueva" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i>
            Nueva Actividad
        </a>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3">
            <form method="get" action="/asistencia" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small mb-0">Subregión</label>
                    <select name="subregion" class="form-select form-select-sm" data-subregion-filter>
                        <option value="">Todas</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0">Municipio</label>
                    <select name="municipality" class="form-select form-select-sm" data-municipality-filter disabled>
                        <option value="">Todos</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0">Asesor</label>
                    <select name="advisor_user_id" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach ($advisors as $a): ?>
                            <option value="<?= (int) $a['id'] ?>" <?= (isset($filters['advisor_user_id']) && (int) $filters['advisor_user_id'] === (int) $a['id']) ? 'selected' : '' ?>><?= htmlspecialchars((string) $a['name'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0">Estado</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <option value="Pendiente" <?= ($filters['status'] ?? '') === 'Pendiente' ? 'selected' : '' ?>>Pendiente</option>
                        <option value="Activo" <?= ($filters['status'] ?? '') === 'Activo' ? 'selected' : '' ?>>Activo</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-outline-primary btn-sm w-100">Filtrar</button>
                </div>
                <div class="col-md-2">
                    <a href="/asistencia" class="btn btn-outline-secondary btn-sm w-100">Limpiar</a>
                </div>
            </form>
        </div>
    </div>

    <?php if (empty($records)): ?>
        <div class="alert alert-info border-0 shadow-sm">
            No hay actividades registradas. Utiliza <strong>Nueva Actividad</strong> para crear la primera.
        </div>
    <?php else: ?>
        <div class="table-responsive shadow-sm rounded-4 bg-white p-3">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Código</th>
                        <th scope="col">Fecha</th>
                        <th scope="col">Subregión</th>
                        <th scope="col">Municipio</th>
                        <th scope="col">Lugar</th>
                        <th scope="col">Tipo Listado</th>
                        <th scope="col">Asesor</th>
                        <th scope="col">Asistentes</th>
                        <th scope="col">Estado</th>
                        <th scope="col"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $row): ?>
                        <?php
                        $tipos = $row['actividad_tipos'] ?? [];
                        $tiposStr = is_array($tipos) ? implode('; ', array_slice($tipos, 0, 2)) : (string) $tipos;
                        if (is_array($tipos) && count($tipos) > 2) {
                            $tiposStr .= '…';
                        }
                        ?>
                        <tr>
                            <td><span class="text-primary fw-medium"><?= htmlspecialchars((string) ($row['code'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><?= htmlspecialchars((string) ($row['activity_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) ($row['subregion'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) ($row['municipality'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) ($row['lugar'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="small"><?= htmlspecialchars($tiposStr, ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) ($row['advisor_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= (int) ($row['asistentes_count'] ?? 0) ?></td>
                            <td>
                                <span class="badge rounded-pill <?= ($row['status'] ?? '') === 'Activo' ? 'text-bg-success' : 'text-bg-warning' ?>">
                                    <?= htmlspecialchars((string) ($row['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td>
                                <a href="/asistencia/ver?id=<?= (int) $row['id'] ?>" class="btn btn-sm btn-outline-primary" title="Ver detalle">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var subregionSelect = document.querySelector('[data-subregion-filter]');
    var municipalitySelect = document.querySelector('[data-municipality-filter]');
    if (!subregionSelect || !municipalitySelect) return;
    fetch('/assets/js/municipios.json').then(function(r) { return r.json(); }).then(function(data) {
        Object.keys(data).forEach(function(sub) {
            var opt = document.createElement('option');
            opt.value = sub;
            opt.textContent = sub;
            subregionSelect.appendChild(opt);
        });
        var currentSub = new URLSearchParams(window.location.search).get('subregion');
        var currentMun = new URLSearchParams(window.location.search).get('municipality');
        if (currentSub) { subregionSelect.value = currentSub; municipalitySelect.disabled = false; (data[currentSub] || []).forEach(function(m) { var o = document.createElement('option'); o.value = m; o.textContent = m; if (m === currentMun) o.selected = true; municipalitySelect.appendChild(o); }); }
        subregionSelect.addEventListener('change', function() {
            municipalitySelect.innerHTML = '<option value="">Todos</option>';
            municipalitySelect.disabled = !subregionSelect.value;
            if (subregionSelect.value && data[subregionSelect.value]) { data[subregionSelect.value].forEach(function(m) { var o = document.createElement('option'); o.value = m; o.textContent = m; municipalitySelect.appendChild(o); }); }
        });
    });
});
</script>
