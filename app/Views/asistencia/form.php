<?php
/** @var array $advisors */
/** @var array $tiposActividad */
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
                            <select name="advisor_user_id" id="advisor_user_id" class="form-select" required>
                                <option value="">Seleccione...</option>
                                <?php foreach ($advisors as $a): ?>
                                    <option value="<?= (int) $a['id'] ?>"><?= htmlspecialchars((string) $a['name'], ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="activity_date" class="form-label">Fecha de la Actividad <span class="text-danger">*</span></label>
                            <input type="date" name="activity_date" id="activity_date" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label for="actividad_tipos" class="form-label">Tipo de Listado (Actividad) <span class="text-danger">*</span></label>
                            <select name="actividad_tipos[]" id="actividad_tipos" class="form-select" multiple required data-select2>
                                <?php foreach ($tiposActividad as $tipo): ?>
                                    <option value="<?= htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Puede seleccionar más de un tipo. Busque o desplácese en la lista.</small>
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
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.7.1.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
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
    if (typeof jQuery !== 'undefined' && document.querySelector('[data-select2]')) {
        jQuery('#actividad_tipos').select2({ theme: 'bootstrap-5', width: '100%', placeholder: 'Seleccione uno o más tipos de listado...' });
    }
});
</script>
