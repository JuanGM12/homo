<?php
/** @var array $actividad */
/** @var string $tituloListado */
$code = (string) ($actividad['code'] ?? '');
?>
<section class="py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <h1 class="h3 mb-4 text-center">Registro de Asistencia</h1>

            <div class="card border-primary border-2 rounded-4 mb-4">
                <div class="card-body">
                    <h2 class="h6 fw-bold mb-3"><?= htmlspecialchars($tituloListado, ENT_QUOTES, 'UTF-8') ?></h2>
                    <div class="row g-2 small">
                        <div class="col-md-4"><strong>Subregión:</strong> <?= htmlspecialchars((string) ($actividad['subregion'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="col-md-4"><strong>Municipio:</strong> <?= htmlspecialchars((string) ($actividad['municipality'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="col-md-4"><strong>Lugar:</strong> <?= htmlspecialchars((string) ($actividad['lugar'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="col-md-4"><strong>Fecha:</strong> <?= htmlspecialchars((string) ($actividad['activity_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="col-md-4"><strong>Asesor:</strong> <?= htmlspecialchars((string) ($actividad['advisor_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-light rounded-top-4 py-3">
                    <i class="bi bi-person-vcard me-2"></i>
                    <strong>Documento y datos personales</strong>
                </div>
                <div class="card-body p-4">
                    <form method="post" action="/asistencia/registrar" id="form-registro-asistencia">
                        <input type="hidden" name="code" value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>">

                        <div class="mb-3">
                            <label for="document_number" class="form-label">Documento de identidad <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="text" name="document_number" id="document_number" class="form-control" required
                                       placeholder="Número de documento" value="">
                                <button type="button" class="btn btn-outline-secondary" id="btn-buscar-doc" title="Buscar datos anteriores">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                            <small class="text-muted">Si ya te registraste antes, tus datos se cargarán solos.</small>
                        </div>

                        <div class="mb-3">
                            <label for="full_name" class="form-label">Nombres y apellidos <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" id="full_name" class="form-control" required>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="entity" class="form-label">Entidad / Organización</label>
                                <input type="text" name="entity" id="entity" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label for="cargo" class="form-label">Cargo</label>
                                <input type="text" name="cargo" id="cargo" class="form-control">
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Teléfono / Celular</label>
                                <input type="text" name="phone" id="phone" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">Correo electrónico</label>
                                <input type="email" name="email" id="email" class="form-control">
                            </div>
                        </div>

                        <div class="mb-3">
                            <h3 class="h6 text-muted mb-2"><i class="bi bi-geo-alt me-1"></i>Zona, sexo y edad</h3>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="zone" class="form-label">Zona</label>
                                    <select name="zone" id="zone" class="form-select">
                                        <option value="">Seleccione...</option>
                                        <option value="Urbana">Urbana</option>
                                        <option value="Rural">Rural</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="sex" class="form-label">Sexo</label>
                                    <select name="sex" id="sex" class="form-select">
                                        <option value="">Seleccione...</option>
                                        <option value="Masculino">Masculino</option>
                                        <option value="Femenino">Femenino</option>
                                        <option value="No binario">No binario</option>
                                        <option value="Transgénero, transexual o travesti">Transgénero, transexual o travesti</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="age" class="form-label">Edad</label>
                                    <input type="number" name="age" id="age" class="form-control" min="1" max="120" placeholder="Años">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="etnia" class="form-label">Etnia</label>
                            <select name="etnia" id="etnia" class="form-select">
                                <option value="">Seleccione...</option>
                                <option value="Afrodescendiente">Afrodescendiente</option>
                                <option value="Indígena">Indígena</option>
                                <option value="Otro">Otro</option>
                            </select>
                            <div id="etnia_otro_wrap" class="mt-2 d-none">
                                <label for="etnia_otro" class="form-label small">Especifique</label>
                                <input type="text" name="etnia_otro" id="etnia_otro" class="form-control" placeholder="Indique cuál">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Grupo poblacional</label>
                            <p class="small text-muted mb-2">Puede seleccionar más de uno</p>
                            <div class="border rounded p-3 bg-light">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="grupo_poblacional[]" id="gp_discapacidad" value="Con discapacidad">
                                    <label class="form-check-label" for="gp_discapacidad">Con discapacidad</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="grupo_poblacional[]" id="gp_victima" value="Víctima del conflicto armado">
                                    <label class="form-check-label" for="gp_victima">Víctima del conflicto armado</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="grupo_poblacional[]" id="gp_campesino" value="Se considera campesino">
                                    <label class="form-check-label" for="gp_campesino">Se considera campesino</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="grupo_poblacional[]" id="gp_comunidad" value="Considera que la comunidad donde vive es campesina">
                                    <label class="form-check-label" for="gp_comunidad">Considera que la comunidad donde vive es campesina</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="grupo_poblacional[]" id="gp_ninguno" value="Ninguno">
                                    <label class="form-check-label" for="gp_ninguno">Ninguno</label>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 btn-lg">
                            <i class="bi bi-person-plus me-1"></i>
                            Registrar asistencia
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var docInput = document.getElementById('document_number');
    var btnBuscar = document.getElementById('btn-buscar-doc');
    function buscarDocumento() {
        var doc = (docInput && docInput.value) ? docInput.value.trim() : '';
        if (!doc) return;
        fetch('/asistencia/buscar-documento?documento=' + encodeURIComponent(doc))
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.found) return;
                var id = function(s) { return document.getElementById(s); };
                if (id('full_name')) id('full_name').value = data.full_name || '';
                if (id('entity')) id('entity').value = data.entity || '';
                if (id('cargo')) id('cargo').value = data.cargo || '';
                if (id('phone')) id('phone').value = data.phone || '';
                if (id('email')) id('email').value = data.email || '';
                if (id('zone')) id('zone').value = data.zone || '';
                if (id('sex')) id('sex').value = data.sex || '';
                if (id('age')) id('age').value = data.age !== null && data.age !== '' ? data.age : '';
                if (id('etnia')) id('etnia').value = data.etnia || '';
                if (id('etnia_otro')) id('etnia_otro').value = data.etnia_otro || '';
                var g = data.grupo_poblacional || [];
                ['gp_discapacidad','gp_victima','gp_campesino','gp_comunidad','gp_ninguno'].forEach(function(idStr, idx) {
                    var vals = ['Con discapacidad','Víctima del conflicto armado','Se considera campesino','Considera que la comunidad donde vive es campesina','Ninguno'];
                    var cb = document.getElementById(idStr);
                    if (cb) cb.checked = g.indexOf(vals[idx]) !== -1;
                });
            });
    }
    if (btnBuscar) btnBuscar.addEventListener('click', buscarDocumento);
    if (docInput) docInput.addEventListener('blur', buscarDocumento);

    var etniaSelect = document.getElementById('etnia');
    var etniaOtroWrap = document.getElementById('etnia_otro_wrap');
    if (etniaSelect && etniaOtroWrap) {
        etniaSelect.addEventListener('change', function() {
            etniaOtroWrap.classList.toggle('d-none', etniaSelect.value !== 'Otro');
        });
    }
});
</script>
