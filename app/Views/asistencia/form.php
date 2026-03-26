<?php
/** @var array $advisors */
/** @var array $tiposActividad */
/** @var array $activityOptionsByAdvisor */
/** @var int $selectedAdvisorId */
/** @var bool $canChooseAdvisor */
?>
<section class="mb-5">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
        <div>
            <a href="/asistencia" class="btn btn-outline-secondary btn-sm mb-2">
                <i class="bi bi-arrow-left me-1"></i>
                Volver
            </a>
            <h1 class="section-title mb-1"><i class="bi bi-plus-circle me-2"></i>Nueva Actividad de Asistencia</h1>
            <p class="section-subtitle mb-0">Complete los datos para generar el código QR de registro.</p>
        </div>
    </div>

    <?php if (empty($advisors)): ?>
        <div class="alert alert-warning border-0 shadow-sm">
            No hay asesores disponibles. Contacte al administrador para dar de alta usuarios con rol distinto a administrador.
        </div>
    <?php else: ?>
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
            <form method="post" action="/asistencia/nueva" id="form-nueva-actividad">
                <div class="mb-4">
                    <h2 class="h6 fw-semibold text-uppercase text-muted mb-3">Lugar de la actividad</h2>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="subregion" class="form-label">Subregión <span class="text-danger">*</span></label>
                            <select name="subregion" id="subregion" class="form-select" required data-subregion-select>
                                <option value="">Seleccione...</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="municipality" class="form-label">Municipio <span class="text-danger">*</span></label>
                            <select name="municipality" id="municipality" class="form-select" required data-municipality-select disabled>
                                <option value="">Primero seleccione subregión...</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="lugar" class="form-label">Lugar <span class="text-danger">*</span></label>
                            <input type="text" name="lugar" id="lugar" class="form-control" required placeholder="Ej: Coliseo Municipal, Salón Comunal...">
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <h2 class="h6 fw-semibold text-uppercase text-muted mb-3">Datos de la actividad</h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="advisor_user_id" class="form-label">Asesor <span class="text-danger">*</span></label>
                            <select name="advisor_user_id" id="advisor_user_id" class="form-select" required <?= !empty($canChooseAdvisor) ? '' : 'disabled' ?>>
                                <option value=""><?= !empty($canChooseAdvisor) ? 'Seleccione...' : 'Asignado automáticamente' ?></option>
                                <?php foreach ($advisors as $a): ?>
                                    <option value="<?= (int) $a['id'] ?>" <?= (int) ($selectedAdvisorId ?? 0) === (int) $a['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $a['name'], ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($canChooseAdvisor) && !empty($selectedAdvisorId)): ?>
                                <input type="hidden" name="advisor_user_id" value="<?= (int) $selectedAdvisorId ?>">
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label for="activity_date" class="form-label">Fecha de la Actividad <span class="text-danger">*</span></label>
                            <input type="date" name="activity_date" id="activity_date" class="form-control" min="2026-01-01" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Tipo de Listado (Actividad) <span class="text-danger">*</span></label>
                            <input
                                type="text"
                                class="form-control form-control-sm mb-2"
                                placeholder="Escribe para filtrar los tipos de listado…"
                                data-actividad-search
                            >
                            <div class="border rounded-3 p-2 bg-light" style="max-height: 220px; overflow-y: auto;" data-actividad-options>
                                <?php foreach ($tiposActividad as $index => $tipo): ?>
                                    <?php $id = 'actividad_tipo_' . $index; ?>
                                    <div class="form-check mb-1">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            name="actividad_tipos[]"
                                            id="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>"
                                            value="<?= htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8') ?>"
                                        >
                                        <label class="form-check-label small" for="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8') ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                                <?php if ($tiposActividad === []): ?>
                                    <p class="small text-muted mb-0" data-actividad-empty-message>Selecciona un asesor para ver los tipos de listado disponibles para su rol.</p>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted d-block mt-1">
                                Haz clic en cada casilla para seleccionar uno o varios tipos de listado. Usa el buscador de arriba para filtrar las opciones.
                                No es necesario usar la tecla Shift.
                            </small>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-qr-code me-1"></i>
                        Crear Actividad y Generar Código
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
</section>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var activityOptionsByAdvisor = <?= json_encode($activityOptionsByAdvisor ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var advisorSelect = document.getElementById('advisor_user_id');
    var subregionSelect = document.querySelector('[data-subregion-select]');
    var municipalitySelect = document.querySelector('[data-municipality-select]');
    if (subregionSelect && municipalitySelect) {
        fetch('/assets/js/municipios.json').then(function(r) { return r.json(); }).then(function(data) {
            Object.keys(data).forEach(function(sub) {
                var opt = document.createElement('option');
                opt.value = sub;
                opt.textContent = sub;
                subregionSelect.appendChild(opt);
            });
            subregionSelect.addEventListener('change', function() {
                municipalitySelect.innerHTML = '<option value="">Primero seleccione subregión...</option>';
                municipalitySelect.disabled = !subregionSelect.value;
                if (subregionSelect.value && data[subregionSelect.value]) {
                    data[subregionSelect.value].forEach(function(m) {
                        var o = document.createElement('option');
                        o.value = m;
                        o.textContent = m;
                        municipalitySelect.appendChild(o);
                    });
                    municipalitySelect.disabled = false;
                    municipalitySelect.firstChild.textContent = 'Seleccione municipio...';
                }
            });
        });
    }
    var searchInput = document.querySelector('[data-actividad-search]');
    var optionsContainer = document.querySelector('[data-actividad-options]');
    var renderActivityOptions = function () {
        if (!optionsContainer) return;

        var advisorId = advisorSelect ? advisorSelect.value : '';
        var tipos = activityOptionsByAdvisor[advisorId] || [];
        optionsContainer.innerHTML = '';

        if (!tipos.length) {
            optionsContainer.innerHTML = '<p class=\"small text-muted mb-0\" data-actividad-empty-message>Selecciona un asesor para ver los tipos de listado disponibles para su rol.</p>';
            return;
        }

        tipos.forEach(function (tipo, index) {
            var wrapper = document.createElement('div');
            wrapper.className = 'form-check mb-1';

            var input = document.createElement('input');
            input.className = 'form-check-input';
            input.type = 'checkbox';
            input.name = 'actividad_tipos[]';
            input.id = 'actividad_tipo_' + advisorId + '_' + index;
            input.value = tipo;

            var label = document.createElement('label');
            label.className = 'form-check-label small';
            label.setAttribute('for', input.id);
            label.textContent = tipo;

            wrapper.appendChild(input);
            wrapper.appendChild(label);
            optionsContainer.appendChild(wrapper);
        });
    };

    if (searchInput && optionsContainer) {
        searchInput.addEventListener('input', function () {
            var term = searchInput.value.trim().toLowerCase();
            var items = optionsContainer.querySelectorAll('.form-check');
            items.forEach(function (item) {
                var label = item.textContent || '';
                item.style.display = term === '' || label.toLowerCase().indexOf(term) !== -1 ? '' : 'none';
            });
        });
    }
    if (advisorSelect) {
        advisorSelect.addEventListener('change', function () {
            renderActivityOptions();
            if (searchInput) {
                searchInput.value = '';
            }
        });
    }
    renderActivityOptions();
});
</script>
