<?php
/** @var string $mode */
/** @var array<string, mixed>|null $plan */
/** @var array<string, mixed> $professional */
$isEdit = $mode === 'edit' && $plan !== null;

$payload = [];
if (!empty($plan['payload'])) {
    $decoded = json_decode((string) $plan['payload'], true);
    if (is_array($decoded)) {
        $payload = $decoded;
    }
}

$suicidioOptions = [
    'Módulo 1: Evolución histórica del suicidio, aproximación conceptual de la conducta suicida, teorías explicativas de primera generación, teorías explicativas de segunda generación, factores de riesgo (biológicos, psiquiátricos, psicológicos y sociales), factores de protección, señales de alarma, ruta de atención y articulación intersectorial, notificación y seguimiento, plan de seguridad.',
    'Módulo 2:  Comunicación y suicidio como factor de riesgo y de protección, impacto del lenguaje y los mensajes, efecto Werther, efecto Papageno, principios de la comunicación responsable, recomendaciones de la OMS para medios y contextos comunitarios, pautas de lo que se debe y no se debe comunicar, aplicación del efecto Papageno en contextos comunitarios e institucionales, roles y responsabilidades de actores clave, poder de la narrativa y reducción del estigma, recursos y guías para la comunicación responsable',
    'Módulo 3: Concepto y alcances de la posvención, posvención como estrategia de prevención y salud pública, impacto psicosocial del suicidio, duelo por suicidio y sus particularidades, duelo y tamizajes para suicidio (RQC, SRQ, Whooley, GAD-2, Zarit, Plutchick, PHQ-9, C-SSRS), estigma y silencios, principios orientadores de la posvención, acciones de posvención en el territorio, acompañamiento a familias e instituciones, comunicación posterior a una muerte por suicidio, identificación y seguimiento de personas en riesgo, articulación con servicios de salud mental, autocuidado del profesional psicosocial.',
    'No aplica',
];

$violenciasOptions = [
    'Módulo 1: Definición, marco normativo, epidemiología, tipología, características.',
    'Módulo 2: Violencias interpersonales, violencia familiar y de pareja, violencia comunitaria, violencia juvenil, bullying.',
    'Módulo 3: Modelos de prevención de las violencias interpersonales (prevención universal, selectiva, indicada y de recurrencias), programas basados en la evidencia para la prevención de las violencias (modelo INSPIRE, modelo RESPETO y otros).',
    'No aplica',
];

$adiccionesOptions = [
    'Módulo 1: Modelos explicativos (biopsicosocial, aprendizaje y condicionamiento), neurobiología de las adicciones, determinantes sociales, factores de riesgo y de protección, prevención basada en evidencia, influencia normativa.',
    'Módulo 2: Comprensión de las adicciones según tipo de sustancia, dependencias comportamentales (juego patológico, nomofobia, juegos electrónicos, oniomanía, adicción al trabajo, vigorexia), cigarrillos electrónicos, cannabis, patología dual.',
    'Módulo 3: Rutas de atención, tamizajes (ASSIST, AUDIT, CRAFFT, Fagerström), intervenciones (entrevista motivacional, intervención única, mindfulness), grupos de apoyo, reducción de riesgos y daños.',
    'No aplica',
];

$otrosTemasOptions = [
    'Cuidado al cuidador',
    'Cuidado del profesional – burnout',
    'Dispositivos Comunitarios',
    'Estigma',
    'Grupos de apoyo y ayuda mutua (violencias, SPA, suicidio): teoría y conformación',
    'Normatividad en Salud Mental y Adicciones',
    'Primeros auxilios psicológicos e intervención en crisis',
    'Estrategias de Salud Mental (Aventura de Crecer, Competencias Parentales, Veredas que se Cuidan, Jóvenes pa\' Lante, Familias que se Cuidan, SAFER)',
    'Trastornos mentales prioritarios de interés en salud pública',
    'No aplica',
];

$selectedSuicidio = (array) ($payload['suicidio'] ?? []);
$selectedViolencias = (array) ($payload['violencias'] ?? []);
$selectedAdicciones = (array) ($payload['adicciones'] ?? []);
$selectedOtrosTemas = (array) ($payload['otros_temas_salud_mental'] ?? []);
?>

<section class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="/entrenamiento">Plan de Entrenamiento</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= $isEdit ? 'Editar plan' : 'Nuevo plan' ?></li>
        </ol>
    </nav>

    <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-9">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4 p-md-5">
                    <h1 class="h4 fw-bold mb-4"><?= $isEdit ? 'Editar plan de entrenamiento' : 'Nuevo plan de entrenamiento' ?></h1>

                    <form method="post" action="<?= $isEdit ? '/entrenamiento/editar' : '/entrenamiento/nuevo' ?>">
                        <?php if ($isEdit): ?>
                            <input type="hidden" name="id" value="<?= (int) $plan['id'] ?>">
                        <?php endif; ?>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Nombre <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars((string) $professional['name'], ENT_QUOTES, 'UTF-8') ?>" disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Fecha registro</label>
                                <input type="text" class="form-control" value="<?= $isEdit && !empty($plan['created_at']) ? htmlspecialchars((string) $plan['created_at'], ENT_QUOTES, 'UTF-8') : date('d/m/Y H:i') ?>" disabled>
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
                            <label class="form-label fw-semibold">SUICIDIO <span class="text-danger">*</span> <span class="text-muted small">(selección múltiple)</span></label>
                            <div class="row">
                                <?php foreach ($suicidioOptions as $opt): ?>
                                    <div class="col-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="suicidio[]" value="<?= htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') ?>" id="suicidio-<?= md5($opt) ?>" <?= in_array($opt, $selectedSuicidio, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small" for="suicidio-<?= md5($opt) ?>"><?= htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') ?></label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">VIOLENCIAS <span class="text-danger">*</span> <span class="text-muted small">(selección múltiple)</span></label>
                            <div class="row">
                                <?php foreach ($violenciasOptions as $opt): ?>
                                    <div class="col-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="violencias[]" value="<?= htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') ?>" id="violencias-<?= md5($opt) ?>" <?= in_array($opt, $selectedViolencias, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small" for="violencias-<?= md5($opt) ?>"><?= htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') ?></label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">ADICCIONES <span class="text-danger">*</span> <span class="text-muted small">(selección múltiple)</span></label>
                            <div class="row">
                                <?php foreach ($adiccionesOptions as $opt): ?>
                                    <div class="col-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="adicciones[]" value="<?= htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') ?>" id="adicciones-<?= md5($opt) ?>" <?= in_array($opt, $selectedAdicciones, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small" for="adicciones-<?= md5($opt) ?>"><?= htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') ?></label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">OTROS TEMAS DE INTERÉS EN SALUD MENTAL <span class="text-danger">*</span> <span class="text-muted small">(selección múltiple)</span></label>
                            <div class="row">
                                <?php foreach ($otrosTemasOptions as $opt): ?>
                                    <div class="col-md-6">
                                        <div class="form-check mb-1">
                                            <input class="form-check-input" type="checkbox" name="otros_temas_salud_mental[]" value="<?= htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') ?>" id="otros-<?= md5($opt) ?>" <?= in_array($opt, $selectedOtrosTemas, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small" for="otros-<?= md5($opt) ?>"><?= htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') ?></label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <p class="text-muted small mb-2">
                            Con el fin de ampliar y fortalecer la oferta de capacitación, lo invitamos a proponer temas que considere relevantes y que no se encuentren incluidos en el listado anterior.
                        </p>
                        <div class="row g-3 mb-3">
                            <div class="col-12">
                                <label class="form-label">1. Tema propuesto</label>
                                <input type="text" name="tema_propuesto_1" class="form-control" value="<?= htmlspecialchars((string) ($payload['tema_propuesto_1'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="Tema propuesto 1">
                            </div>
                            <div class="col-12">
                                <label class="form-label">2. Tema propuesto</label>
                                <input type="text" name="tema_propuesto_2" class="form-control" value="<?= htmlspecialchars((string) ($payload['tema_propuesto_2'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="Tema propuesto 2">
                            </div>
                            <div class="col-12">
                                <label class="form-label">3. Tema propuesto</label>
                                <input type="text" name="tema_propuesto_3" class="form-control" value="<?= htmlspecialchars((string) ($payload['tema_propuesto_3'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="Tema propuesto 3">
                            </div>
                            <div class="col-12">
                                <label class="form-label">4. Tema propuesto</label>
                                <input type="text" name="tema_propuesto_4" class="form-control" value="<?= htmlspecialchars((string) ($payload['tema_propuesto_4'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="Tema propuesto 4">
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Breve justificación o necesidad del tema en su contexto institucional o comunitario <span class="text-muted">(opcional)</span></label>
                            <textarea name="justificacion_temas" class="form-control" rows="3" placeholder="Describa la justificación o necesidad..."><?= htmlspecialchars((string) ($payload['justificacion_temas'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="/entrenamiento" class="btn btn-outline-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check2-circle me-1"></i>
                                <?= $isEdit ? 'Guardar cambios' : 'Guardar plan' ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if ($isEdit && !empty($plan['subregion'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var subregionSelect = document.querySelector('#subregion');
    var municipalitySelect = document.querySelector('#municipality');
    if (!subregionSelect || !municipalitySelect) return;
    var selectedSubregion = <?= json_encode((string) $plan['subregion'], JSON_UNESCAPED_UNICODE) ?>;
    var selectedMunicipality = <?= json_encode((string) ($plan['municipality'] ?? ''), JSON_UNESCAPED_UNICODE) ?>;
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
