<?php
/** @var array $actividad */
/** @var string $tituloListado */
$code = (string) ($actividad['code'] ?? '');
$tipos = $actividad['actividad_tipos'] ?? [];
$tiposList = is_array($tipos) ? $tipos : [];
$listadoCerrado = ((string) ($actividad['status'] ?? '')) === 'Cerrado';
?>
<style>
    .attendance-public {
        --attendance-green: #2f6b57;
        --attendance-green-soft: #e7f2ea;
        --attendance-blue: #4a82d8;
        --attendance-ink: #1d2d3f;
        --attendance-copy: #5b6678;
        --attendance-line: #d8e4ef;
        --attendance-panel: rgba(255, 255, 255, 0.94);
        --attendance-shadow: 0 28px 80px rgba(31, 62, 51, 0.14);
        position: relative;
        padding: 3.5rem 1rem 4.5rem;
        overflow: hidden;
    }

    .attendance-public::before,
    .attendance-public::after {
        content: "";
        position: absolute;
        inset: auto;
        border-radius: 999px;
        filter: blur(10px);
        pointer-events: none;
    }

    .attendance-public::before {
        width: 28rem;
        height: 28rem;
        top: 2rem;
        left: -9rem;
        background: radial-gradient(circle, rgba(156, 220, 170, 0.35) 0%, rgba(156, 220, 170, 0) 72%);
    }

    .attendance-public::after {
        width: 24rem;
        height: 24rem;
        right: -7rem;
        bottom: 1rem;
        background: radial-gradient(circle, rgba(103, 174, 235, 0.2) 0%, rgba(103, 174, 235, 0) 72%);
    }

    .attendance-public-shell {
        max-width: 1020px;
        margin: 0 auto;
        background: var(--attendance-panel);
        border: 1px solid rgba(120, 154, 190, 0.18);
        border-radius: 28px;
        box-shadow: var(--attendance-shadow);
        position: relative;
        z-index: 1;
        overflow: hidden;
    }

    .attendance-public-shell::before {
        content: "";
        display: block;
        width: 100%;
        height: 6px;
        background: linear-gradient(90deg, #73c4f3 0%, var(--attendance-blue) 50%, var(--attendance-green) 100%);
    }

    .attendance-public-header {
        padding: 3rem 2.5rem 2rem;
        text-align: center;
    }

    .attendance-public-logos {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 1.75rem;
        flex-wrap: wrap;
        margin-bottom: 1.75rem;
    }

    .attendance-public-logos img {
        display: block;
        width: auto;
        max-width: 150px;
        max-height: 68px;
        object-fit: contain;
    }

    .attendance-public-kicker {
        margin: 0 0 .5rem;
        font-size: .92rem;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: var(--attendance-green);
        font-weight: 700;
    }

    .attendance-public-title {
        margin: 0;
        font-size: clamp(2.1rem, 4vw, 3.35rem);
        line-height: 1.04;
        color: var(--attendance-ink);
        font-weight: 800;
    }

    .attendance-public-subtitle {
        margin: .9rem auto 0;
        max-width: 620px;
        font-size: 1.1rem;
        color: var(--attendance-copy);
    }

    .attendance-public-divider {
        height: 1px;
        margin: 0 2.5rem;
        background: linear-gradient(90deg, rgba(216, 228, 239, 0) 0%, rgba(216, 228, 239, 1) 15%, rgba(216, 228, 239, 1) 85%, rgba(216, 228, 239, 0) 100%);
    }

    .attendance-public-body {
        padding: 2rem 2rem 2.75rem;
    }

    .attendance-summary-card {
        background: linear-gradient(135deg, #eef7ff 0%, #f6fbf6 100%);
        border: 1px solid rgba(82, 144, 208, 0.35);
        border-radius: 18px;
        padding: 1.4rem 1.6rem;
        margin-bottom: 1.5rem;
    }

    .attendance-summary-title {
        margin: 0 0 1rem;
        color: var(--attendance-ink);
        font-size: 1.28rem;
        font-weight: 800;
    }

    .attendance-summary-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .9rem 2rem;
    }

    .attendance-summary-item {
        display: flex;
        flex-wrap: wrap;
        gap: .3rem;
        color: var(--attendance-copy);
    }

    .attendance-summary-item strong {
        color: #30455d;
    }

    .attendance-type-stack {
        display: flex;
        flex-wrap: wrap;
        gap: .55rem;
        margin-top: .4rem;
    }

    .attendance-type-pill {
        display: inline-flex;
        align-items: center;
        min-height: 2rem;
        padding: .35rem .8rem;
        border-radius: 999px;
        background: rgba(47, 107, 87, 0.08);
        color: var(--attendance-green);
        border: 1px solid rgba(47, 107, 87, 0.16);
        font-size: .86rem;
        font-weight: 600;
    }

    .attendance-form-card {
        background: #fff;
        border: 1px solid rgba(216, 228, 239, 0.8);
        border-radius: 22px;
        padding: 1.15rem;
    }

    .attendance-form-section {
        border: 1px solid rgba(216, 228, 239, 0.72);
        border-radius: 18px;
        padding: 1.35rem;
        background: linear-gradient(180deg, rgba(255,255,255,0.98) 0%, rgba(248, 251, 255, 0.94) 100%);
    }

    .attendance-section-head {
        display: flex;
        align-items: center;
        gap: .95rem;
        margin-bottom: 1.5rem;
        padding: .95rem 1.1rem;
        border-radius: 16px;
        background: linear-gradient(90deg, rgba(116, 198, 255, 0.16) 0%, rgba(231, 242, 234, 0.6) 100%);
        border-left: 4px solid #62adf5;
    }

    .attendance-section-icon {
        width: 2.6rem;
        height: 2.6rem;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #fff;
        color: var(--attendance-ink);
        box-shadow: 0 10px 30px rgba(80, 124, 167, 0.12);
        font-size: 1.1rem;
    }

    .attendance-section-title {
        margin: 0;
        font-size: 1.5rem;
        color: var(--attendance-ink);
        font-weight: 800;
    }

    .attendance-section-copy {
        margin: .2rem 0 0;
        color: var(--attendance-copy);
        font-size: .95rem;
    }

    .attendance-public .form-label {
        font-size: .95rem;
        font-weight: 700;
        color: #22354a;
        margin-bottom: .55rem;
    }

    .attendance-public .form-control,
    .attendance-public .form-select {
        border-radius: 16px;
        border: 1px solid #cfe0ef;
        min-height: 54px;
        padding: .9rem 1rem;
        color: var(--attendance-ink);
        background-color: #fff;
        box-shadow: inset 0 1px 0 rgba(255,255,255,0.6);
    }

    .attendance-public .form-control:focus,
    .attendance-public .form-select:focus {
        border-color: #7eb6ee;
        box-shadow: 0 0 0 .2rem rgba(74, 130, 216, 0.14);
    }

    .attendance-search-wrap {
        position: relative;
    }

    .attendance-search-wrap .form-control {
        padding-right: 3.7rem;
    }

    .attendance-search-btn {
        position: absolute;
        top: 7px;
        right: 7px;
        width: 40px;
        height: 40px;
        border: 0;
        border-radius: 12px;
        background: linear-gradient(135deg, var(--attendance-blue) 0%, #6eb9ef 100%);
        color: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .attendance-help {
        margin-top: .45rem;
        color: var(--attendance-copy);
        font-size: .92rem;
    }

    .attendance-form-divider {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 1rem;
        margin: 1.5rem 0;
    }

    .attendance-mini-card {
        padding: .95rem 1rem;
        border-radius: 16px;
        background: #f8fbff;
        border: 1px solid rgba(216, 228, 239, 0.92);
    }

    .attendance-mini-label {
        display: block;
        margin-bottom: .28rem;
        font-size: .8rem;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #6d7a8d;
        font-weight: 700;
    }

    .attendance-mini-value {
        color: var(--attendance-ink);
        font-weight: 700;
    }

    .attendance-check-panel {
        border-radius: 18px;
        border: 1px solid rgba(216, 228, 239, 0.88);
        background: linear-gradient(180deg, #fbfdff 0%, #f6fbf8 100%);
        padding: 1rem;
    }

    .attendance-check-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .8rem;
    }

    .attendance-check-card {
        position: relative;
        display: flex;
        align-items: flex-start;
        gap: .85rem;
        min-height: 100%;
        padding: .9rem 1rem;
        border-radius: 16px;
        border: 1px solid rgba(207, 224, 239, 0.95);
        background: #fff;
        transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
    }

    .attendance-check-card:hover {
        transform: translateY(-1px);
        border-color: rgba(98, 173, 245, 0.6);
        box-shadow: 0 14px 26px rgba(71, 116, 162, 0.08);
    }

    .attendance-check-card .form-check-input {
        margin-top: .15rem;
        flex-shrink: 0;
    }

    .attendance-check-card .form-check-label {
        margin: 0;
        font-size: .96rem;
        line-height: 1.45;
        color: #2f4054;
        font-weight: 600;
        cursor: pointer;
    }

    .attendance-submit {
        width: 100%;
        min-height: 58px;
        border: 0;
        border-radius: 18px;
        background: linear-gradient(135deg, #4c73c6 0%, #3d86d1 46%, #2f6b57 100%);
        color: #fff;
        font-size: 1.06rem;
        font-weight: 700;
        box-shadow: 0 18px 35px rgba(60, 99, 140, 0.24);
    }

    .attendance-submit:hover,
    .attendance-submit:focus {
        color: #fff;
        transform: translateY(-1px);
    }

    @media (max-width: 991.98px) {
        .attendance-public-header {
            padding: 2.5rem 1.4rem 1.6rem;
        }

        .attendance-public-body {
            padding: 1.4rem 1rem 2rem;
        }

        .attendance-public-divider {
            margin: 0 1rem;
        }

        .attendance-summary-grid,
        .attendance-form-divider,
        .attendance-check-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<section class="attendance-public">
    <div class="attendance-public-shell">
        <div class="attendance-public-header">
            <div class="attendance-public-logos">
                <img src="/assets/img/logoAntioquia.png" alt="Gobernación de Antioquia">
                <img src="/assets/img/logoHomo.png" alt="HOMO">
            </div>
            <p class="attendance-public-kicker">Equipo de Promoción y Prevención</p>
            <h1 class="attendance-public-title">Registro de Asistencia</h1>
            <p class="attendance-public-subtitle">
                <?= htmlspecialchars((string) $tituloListado, ENT_QUOTES, 'UTF-8') ?> · diligenciamiento individual de asistentes.
            </p>
        </div>

        <div class="attendance-public-divider"></div>

        <div class="attendance-public-body">
            <div class="attendance-summary-card">
                <h2 class="attendance-summary-title">
                    <?= htmlspecialchars((string) $tituloListado, ENT_QUOTES, 'UTF-8') ?>
                </h2>

                <div class="attendance-summary-grid">
                    <div class="attendance-summary-item">
                        <strong>Subregión:</strong>
                        <span><?= htmlspecialchars((string) ($actividad['subregion'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="attendance-summary-item">
                        <strong>Fecha:</strong>
                        <span><?= htmlspecialchars((string) ($actividad['activity_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="attendance-summary-item">
                        <strong>Municipio:</strong>
                        <span><?= htmlspecialchars((string) ($actividad['municipality'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="attendance-summary-item">
                        <strong>Asesor:</strong>
                        <span><?= htmlspecialchars((string) ($actividad['advisor_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="attendance-summary-item">
                        <strong>Lugar:</strong>
                        <span><?= htmlspecialchars((string) ($actividad['lugar'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="attendance-summary-item">
                        <strong>Código:</strong>
                        <span><?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>

                <?php if ($tiposList !== []): ?>
                    <div class="attendance-type-stack">
                        <?php foreach ($tiposList as $tipo): ?>
                            <span class="attendance-type-pill"><?= htmlspecialchars((string) $tipo, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="attendance-form-card">
                <?php if ($listadoCerrado): ?>
                    <div class="alert alert-secondary border-0 rounded-3 mb-0" role="alert">
                        <strong>Listado cerrado.</strong> El responsable cerró este listado de asistencia; ya no es posible registrar nuevos asistentes por este enlace.
                    </div>
                <?php else: ?>
                <form method="post" action="/asistencia/registrar" id="form-registro-asistencia" class="attendance-form-section">
                    <input type="hidden" name="code" value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="attendance-section-head">
                        <span class="attendance-section-icon"><i class="bi bi-briefcase"></i></span>
                        <div>
                            <h3 class="attendance-section-title">Documento y datos personales</h3>
                            <p class="attendance-section-copy">Diligencia la información principal del asistente. Si ya tiene registros previos, el sistema intentará autocompletar los datos.</p>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="document_number" class="form-label">Documento de identidad <span class="text-danger">*</span></label>
                        <div class="attendance-search-wrap">
                            <input type="text" name="document_number" id="document_number" class="form-control" required placeholder="Número de documento">
                            <button type="button" class="attendance-search-btn" id="btn-buscar-doc" title="Buscar datos anteriores">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                        <div class="attendance-help">Si ya te registraste antes, tus datos se cargarán automáticamente.</div>
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

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label for="phone" class="form-label">Teléfono / Celular</label>
                            <input type="text" name="phone" id="phone" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Correo electrónico</label>
                            <input type="email" name="email" id="email" class="form-control">
                        </div>
                    </div>

                    <div class="attendance-form-divider">
                        <div class="attendance-mini-card">
                            <span class="attendance-mini-label">Zona</span>
                            <select name="zone" id="zone" class="form-select">
                                <option value="">Seleccione...</option>
                                <option value="Urbana">Urbana</option>
                                <option value="Rural">Rural</option>
                            </select>
                        </div>
                        <div class="attendance-mini-card">
                            <span class="attendance-mini-label">Sexo</span>
                            <select name="sex" id="sex" class="form-select">
                                <option value="">Seleccione...</option>
                                <option value="Masculino">Masculino</option>
                                <option value="Femenino">Femenino</option>
                                <option value="No binario">No binario</option>
                                <option value="Transgénero, transexual o travesti">Transgénero, transexual o travesti</option>
                            </select>
                        </div>
                        <div class="attendance-mini-card">
                            <span class="attendance-mini-label">Edad</span>
                            <input type="number" name="age" id="age" class="form-control" min="1" max="120" placeholder="Años">
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
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
                        <div class="col-md-6">
                            <label class="form-label">Grupo poblacional</label>
                            <div class="attendance-check-panel">
                                <div class="attendance-check-grid">
                                    <div class="attendance-check-card form-check">
                                        <input class="form-check-input" type="checkbox" name="grupo_poblacional[]" id="gp_discapacidad" value="Con discapacidad">
                                        <label class="form-check-label" for="gp_discapacidad">Con discapacidad</label>
                                    </div>
                                    <div class="attendance-check-card form-check">
                                        <input class="form-check-input" type="checkbox" name="grupo_poblacional[]" id="gp_victima" value="Víctima del conflicto armado">
                                        <label class="form-check-label" for="gp_victima">Víctima del conflicto armado</label>
                                    </div>
                                    <div class="attendance-check-card form-check">
                                        <input class="form-check-input" type="checkbox" name="grupo_poblacional[]" id="gp_campesino" value="Se considera campesino">
                                        <label class="form-check-label" for="gp_campesino">Se considera campesino</label>
                                    </div>
                                    <div class="attendance-check-card form-check">
                                        <input class="form-check-input" type="checkbox" name="grupo_poblacional[]" id="gp_comunidad" value="Considera que la comunidad donde vive es campesina">
                                        <label class="form-check-label" for="gp_comunidad">Considera que la comunidad donde vive es campesina</label>
                                    </div>
                                    <div class="attendance-check-card form-check">
                                        <input class="form-check-input" type="checkbox" name="grupo_poblacional[]" id="gp_ninguno" value="Ninguno">
                                        <label class="form-check-label" for="gp_ninguno">Ninguno</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="attendance-submit">
                        <i class="bi bi-person-plus me-2"></i>
                        Registrar asistencia
                    </button>
                </form>
                <?php endif; ?>
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
        var syncEtniaOtro = function () {
            etniaOtroWrap.classList.toggle('d-none', etniaSelect.value !== 'Otro');
        };
        etniaSelect.addEventListener('change', syncEtniaOtro);
        syncEtniaOtro();
    }
});
</script>
