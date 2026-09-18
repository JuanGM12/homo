<?php
/** @var string $mode */
/** @var array<string, mixed>|null $plan */
/** @var array<string, mixed> $professional */
/** @var array<int, array{subregion:string, municipality:string}>|null $allowedMunicipalities */
/** @var bool|null $readOnly */

use App\Services\QualificationCatalog;

$isEdit = $mode === 'edit' && $plan !== null;
$formReadOnly = !empty($readOnly);

$payload = [];
if (!empty($plan['payload'])) {
    $decoded = json_decode((string) $plan['payload'], true);
    if (is_array($decoded)) {
        $payload = $decoded;
    }
}

$qualificationRole = QualificationCatalog::normalizeRole((string) ($professional['role'] ?? ''));
$payload = QualificationCatalog::hydrateLegacyPayload($payload, $qualificationRole);

$allowedMunicipalities = is_array($allowedMunicipalities ?? null) ? $allowedMunicipalities : [];
$allowedTerritories = [];
foreach ($allowedMunicipalities as $row) {
    $subregion = trim((string) ($row['subregion'] ?? ''));
    $municipality = trim((string) ($row['municipality'] ?? ''));
    if ($subregion === '' || $municipality === '') {
        continue;
    }
    $allowedTerritories[$subregion][] = $municipality;
}
$allowedTerritoriesJson = htmlspecialchars(json_encode($allowedTerritories, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');

$periodName = trim((string) ($plan['period_name'] ?? ''));
?>

<section class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="/entrenamiento">Plan de Entrenamiento</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= $isEdit ? ($formReadOnly ? 'Ver' : 'Editar') : 'Nuevo' ?></li>
        </ol>
    </nav>

    <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-9">
            <h1 class="h4 fw-bold mb-4"><?= $isEdit ? ($formReadOnly ? 'Consultar plan de entrenamiento' : 'Editar plan de entrenamiento') : 'Nuevo plan de entrenamiento' ?></h1>

            <?php if ($formReadOnly): ?>
                <div class="alert alert-info border-0 shadow-sm mb-4">
                    Este plan se muestra en modo lectura<?= $periodName !== '' ? ' (periodo ' . htmlspecialchars($periodName, ENT_QUOTES, 'UTF-8') . ')' : '' ?>. No es posible guardar cambios.
                </div>
            <?php endif; ?>

            <div class="card border-0 app-form-card">
                <div class="card-body p-4 p-md-5">
                    <form method="post" action="<?= $isEdit ? '/entrenamiento/editar' : '/entrenamiento/nuevo' ?>" class="app-plan-form" <?= $formReadOnly ? 'data-read-only="1"' : '' ?>>
                        <?php if ($isEdit): ?>
                            <input type="hidden" name="id" value="<?= (int) $plan['id'] ?>">
                        <?php endif; ?>

                        <div class="app-form-section mb-4">
                            <h2 class="h6 fw-semibold text-secondary mb-3">Datos del profesional</h2>
                            <div class="row g-3 app-form-fields">
                                <div class="col-md-6">
                                    <label class="form-label">Nombre</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars((string) $professional['name'], ENT_QUOTES, 'UTF-8') ?>" disabled>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Fecha registro</label>
                                    <input type="text" class="form-control" value="<?= $isEdit && !empty($plan['created_at']) ? htmlspecialchars((string) $plan['created_at'], ENT_QUOTES, 'UTF-8') : date('d/m/Y H:i') ?>" disabled>
                                </div>
                            </div>
                        </div>

                        <div class="app-form-section mb-4">
                            <h2 class="h6 fw-semibold text-secondary mb-3">Ubicación</h2>
                            <div class="row g-3 app-form-fields">
                                <div class="col-md-6">
                                    <label for="subregion" class="form-label">Subregión <span class="text-danger">*</span></label>
                                    <select
                                        id="subregion"
                                        name="subregion"
                                        class="form-select"
                                        required
                                        data-subregion-select
                                        data-allowed-territories="<?= $allowedTerritoriesJson ?>"
                                        data-current-value="<?= htmlspecialchars((string) ($plan['subregion'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        <?= $formReadOnly ? 'disabled' : '' ?>
                                    >
                                        <option value="">Seleccione la subregión</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="municipality" class="form-label">Municipio <span class="text-danger">*</span></label>
                                    <select
                                        id="municipality"
                                        name="municipality"
                                        class="form-select"
                                        required
                                        data-municipality-select
                                        data-current-value="<?= htmlspecialchars((string) ($plan['municipality'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        disabled
                                    >
                                        <option value="">Seleccione el municipio</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <?php
                        $formData = $payload;
                        $readOnly = $formReadOnly;
                        $qualificationIntro = 'Selecciona los temas de este plan de entrenamiento según tu rol. Algunas preguntas son de selección múltiple y otras de selección única.';
                        require dirname(__DIR__) . '/partials/qualification_sections.php';
                        ?>

                        <div class="app-form-section mb-4">
                            <h2 class="h6 fw-semibold text-secondary mb-2">Temas propuestos</h2>
                            <p class="text-muted small mb-3">Proponga hasta 4 temas que no estén en el listado anterior (opcional).</p>
                            <div class="row g-3 app-form-fields">
                                <div class="col-md-6">
                                    <label class="form-label">Tema 1</label>
                                    <input type="text" name="tema_propuesto_1" class="form-control" value="<?= htmlspecialchars((string) ($payload['tema_propuesto_1'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="Tema propuesto" <?= $formReadOnly ? 'disabled' : '' ?>>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Tema 2</label>
                                    <input type="text" name="tema_propuesto_2" class="form-control" value="<?= htmlspecialchars((string) ($payload['tema_propuesto_2'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="Tema propuesto" <?= $formReadOnly ? 'disabled' : '' ?>>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Tema 3</label>
                                    <input type="text" name="tema_propuesto_3" class="form-control" value="<?= htmlspecialchars((string) ($payload['tema_propuesto_3'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="Tema propuesto" <?= $formReadOnly ? 'disabled' : '' ?>>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Tema 4</label>
                                    <input type="text" name="tema_propuesto_4" class="form-control" value="<?= htmlspecialchars((string) ($payload['tema_propuesto_4'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="Tema propuesto" <?= $formReadOnly ? 'disabled' : '' ?>>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Justificación o necesidad <span class="text-muted">(opcional)</span></label>
                                    <textarea name="justificacion_temas" class="form-control" rows="2" placeholder="Describa la justificación..." <?= $formReadOnly ? 'disabled' : '' ?>><?= htmlspecialchars((string) ($payload['justificacion_temas'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="app-form-submit d-flex justify-content-end gap-2">
                            <a href="/entrenamiento" class="btn btn-outline-secondary"><?= $formReadOnly ? 'Volver' : 'Cancelar' ?></a>
                            <?php if (!$formReadOnly): ?>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check2-circle me-1"></i>
                                    <?= $isEdit ? 'Guardar cambios' : 'Guardar plan' ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
