<?php
/** @var string $mode */
/** @var array<string, mixed>|null $record */
/** @var array<string, mixed> $professional */
$isEdit = $mode === 'edit' && $record !== null;

$payload = [];
if (!empty($record['payload'])) {
    $decoded = json_decode((string) $record['payload'], true);
    if (is_array($decoded)) {
        $payload = $decoded;
    }
}
?>

<section class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="/pic">Seguimiento PIC</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= $isEdit ? 'Editar registro' : 'Nuevo registro' ?></li>
        </ol>
    </nav>

    <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-9">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4 p-md-5">
                    <h1 class="h4 fw-bold mb-4"><?= $isEdit ? 'Editar registro Seguimiento PIC' : 'Nuevo registro Seguimiento PIC' ?></h1>

                    <form method="post" action="<?= $isEdit ? '/pic/editar' : '/pic/nuevo' ?>" id="form-pic">
                        <?php if ($isEdit): ?>
                            <input type="hidden" name="id" value="<?= (int) $record['id'] ?>">
                        <?php endif; ?>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Nombre <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars((string) $professional['name'], ENT_QUOTES, 'UTF-8') ?>" disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Fecha registro</label>
                                <input type="text" class="form-control" value="<?= $isEdit && !empty($record['created_at']) ? htmlspecialchars((string) $record['created_at'], ENT_QUOTES, 'UTF-8') : date('d/m/Y H:i') ?>" disabled>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="subregion" class="form-label">Seleccione la subregión <span class="text-danger">*</span></label>
                                <select id="subregion" name="subregion" class="form-select" required data-subregion-select>
                                    <option value="">Seleccione la subregión</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="municipality" class="form-label">Seleccione el municipio <span class="text-danger">*</span></label>
                                <select id="municipality" name="municipality" class="form-select" required data-municipality-select disabled>
                                    <option value="">Seleccione el municipio</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">¿El municipio cuenta con Zona de orientación Escolar? <span class="text-danger">*</span></label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="zona_orientacion_escolar" id="zona_escolar_si" value="Si" required <?= ($payload['zona_orientacion_escolar'] ?? '') === 'Si' ? 'checked' : '' ?> data-pic-toggle="zona-escolar">
                                    <label class="form-check-label" for="zona_escolar_si">Sí</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="zona_orientacion_escolar" id="zona_escolar_no" value="No" <?= ($payload['zona_orientacion_escolar'] ?? '') === 'No' ? 'checked' : '' ?> data-pic-toggle="zona-escolar">
                                    <label class="form-check-label" for="zona_escolar_no">No</label>
                                </div>
                            </div>
                            <div class="mt-2" id="wrap-zona-escolar" style="display:<?= ($payload['zona_orientacion_escolar'] ?? '') === 'Si' ? 'block' : 'none' ?>;">
                                <label class="form-label">¿Cuántas personas fueron atendidas en la zona de orientación escolar? <span class="text-danger">*</span></label>
                                <input type="number" name="personas_zona_orientacion_escolar" class="form-control" min="0" step="1" placeholder="Número" value="<?= htmlspecialchars((string) ($payload['personas_zona_orientacion_escolar'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">¿El municipio cuenta con Centro de escucha? <span class="text-danger">*</span></label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="centro_escucha" id="centro_escucha_si" value="Si" required data-pic-toggle="centro-escucha" <?= ($payload['centro_escucha'] ?? '') === 'Si' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="centro_escucha_si">Sí</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="centro_escucha" id="centro_escucha_no" value="No" data-pic-toggle="centro-escucha" <?= ($payload['centro_escucha'] ?? '') === 'No' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="centro_escucha_no">No</label>
                                </div>
                            </div>
                            <div class="mt-2" id="wrap-centro-escucha" style="display:<?= ($payload['centro_escucha'] ?? '') === 'Si' ? 'block' : 'none' ?>;">
                                <label class="form-label">¿Cuántas personas fueron atendidas en el centro de escucha?</label>
                                <input type="number" name="personas_centro_escucha" class="form-control" min="0" step="1" placeholder="Número" value="<?= htmlspecialchars((string) ($payload['personas_centro_escucha'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">¿El municipio cuenta con Zona de orientación Universitaria? <span class="text-danger">*</span></label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="zona_orientacion_universitaria" id="zona_uni_si" value="Si" required data-pic-toggle="zona-uni" <?= ($payload['zona_orientacion_universitaria'] ?? '') === 'Si' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="zona_uni_si">Sí</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="zona_orientacion_universitaria" id="zona_uni_no" value="No" data-pic-toggle="zona-uni" <?= ($payload['zona_orientacion_universitaria'] ?? '') === 'No' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="zona_uni_no">No</label>
                                </div>
                            </div>
                            <div class="mt-2" id="wrap-zona-uni" style="display:<?= ($payload['zona_orientacion_universitaria'] ?? '') === 'Si' ? 'block' : 'none' ?>;">
                                <label class="form-label">¿Cuántas personas fueron atendidas en la Zona de orientación Universitaria?</label>
                                <input type="number" name="personas_zona_orientacion_universitaria" class="form-control" min="0" step="1" placeholder="Número" value="<?= htmlspecialchars((string) ($payload['personas_zona_orientacion_universitaria'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">¿El municipio cuenta con Redes Comunitarias activas? <span class="text-danger">*</span></label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="redes_comunitarias_activas" id="redes_si" value="Si" required data-pic-toggle="redes" <?= ($payload['redes_comunitarias_activas'] ?? '') === 'Si' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="redes_si">Sí</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="redes_comunitarias_activas" id="redes_no" value="No" data-pic-toggle="redes" <?= ($payload['redes_comunitarias_activas'] ?? '') === 'No' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="redes_no">No</label>
                                </div>
                            </div>
                            <div class="mt-2" id="wrap-redes" style="display:<?= ($payload['redes_comunitarias_activas'] ?? '') === 'Si' ? 'block' : 'none' ?>;">
                                <label class="form-label">¿Con cuántas personas está conformada la red comunitaria? <span class="text-danger">*</span></label>
                                <input type="number" name="personas_red_comunitaria" class="form-control" min="0" step="1" placeholder="Número" value="<?= htmlspecialchars((string) ($payload['personas_red_comunitaria'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="/pic" class="btn btn-outline-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check2-circle me-1"></i>
                                <?= $isEdit ? 'Guardar cambios' : 'Guardar registro' ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var toggles = {
        'zona-escolar': 'wrap-zona-escolar',
        'centro-escucha': 'wrap-centro-escucha',
        'zona-uni': 'wrap-zona-uni',
        'redes': 'wrap-redes'
    };
    document.querySelectorAll('[data-pic-toggle]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            var key = this.getAttribute('data-pic-toggle');
            var wrapId = toggles[key];
            if (!wrapId) return;
            var wrap = document.getElementById(wrapId);
            if (wrap) wrap.style.display = this.value === 'Si' ? 'block' : 'none';
        });
    });
    document.querySelectorAll('[data-pic-toggle]').forEach(function(radio) {
        if (radio.checked && radio.value === 'Si') {
            var key = radio.getAttribute('data-pic-toggle');
            var wrapId = toggles[key];
            if (wrapId) document.getElementById(wrapId).style.display = 'block';
        }
    });
});
</script>
<?php if ($isEdit && !empty($record['subregion'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var subregionSelect = document.querySelector('#subregion');
    var municipalitySelect = document.querySelector('#municipality');
    if (!subregionSelect || !municipalitySelect) return;
    var selectedSubregion = <?= json_encode((string) $record['subregion'], JSON_UNESCAPED_UNICODE) ?>;
    var selectedMunicipality = <?= json_encode((string) ($record['municipality'] ?? ''), JSON_UNESCAPED_UNICODE) ?>;
    setTimeout(function() {
        subregionSelect.value = selectedSubregion;
        subregionSelect.dispatchEvent(new Event('change'));
        setTimeout(function() {
            municipalitySelect.value = selectedMunicipality;
            municipalitySelect.disabled = false;
        }, 50);
    }, 100);
});
</script>
<?php endif; ?>
