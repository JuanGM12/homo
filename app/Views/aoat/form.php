<?php
/** @var array|null $record */
/** @var array $professional */
/** @var array $oldInput */
/** @var list<string>|null $activityWithOptions */
/** @var bool|null $readOnly */

$isEdit = isset($record) && isset($record['id']);
$role = strtolower((string) ($professional['role'] ?? ''));

$formData = [];
if ($isEdit && isset($record['payload'])) {
    $decoded = json_decode((string) $record['payload'], true);
    if (is_array($decoded)) {
        $formData = $decoded;
    }
}

if ($isEdit) {
    $formData['subregion'] = (string) ($record['subregion'] ?? '');
    $formData['municipality'] = (string) ($record['municipality'] ?? '');
}

if (isset($oldInput) && is_array($oldInput) && $oldInput !== []) {
    $formData = array_replace($formData, $oldInput);
}

$oldPayload = $formData;
$currentState = $isEdit ? (string) ($record['state'] ?? 'Asignada') : 'Asignada';
$numberOnlyEdit = $isEdit && in_array($currentState, ['Aprobada', 'Realizado'], true);
$periodReadOnly = !empty($readOnly);
$formReadOnly = $numberOnlyEdit || $periodReadOnly;
$lockNonNumberFields = $formReadOnly;
?>

<section class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="/aoat">AoAT</a></li>
            <li class="breadcrumb-item active" aria-current="page">
                <?= $isEdit ? ($periodReadOnly && !$numberOnlyEdit ? 'Ver AoAT' : 'Editar AoAT') : 'Nueva AoAT' ?>
            </li>
        </ol>
    </nav>

    <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-9">
            <div class="card border-0 app-form-card">
                <div class="card-body p-4 p-md-5">
                    <h1 class="h4 fw-bold mb-4"><?= $isEdit ? ($periodReadOnly && !$numberOnlyEdit ? 'Consultar AoAT' : 'Editar AoAT') : 'Registrar nueva AoAT' ?></h1>

                    <?php if ($periodReadOnly && !$numberOnlyEdit): ?>
                        <div class="alert alert-info border-0 shadow-sm mb-4">
                            Este registro pertenece a un periodo anterior. Puedes consultar la información, pero no modificarla.
                        </div>
                    <?php endif; ?>

                    <form
                        class="aoat-form"
                        method="post"
                        action="<?= $isEdit ? '/aoat/editar' : '/aoat/nueva' ?>"
                        <?= $numberOnlyEdit ? 'data-number-only-edit="1"' : '' ?>
                        <?= $periodReadOnly && !$numberOnlyEdit ? 'data-read-only="1"' : '' ?>
                    >
                        <?php if ($isEdit && isset($record['id'])): ?>
                            <input type="hidden" name="id" value="<?= (int) $record['id'] ?>">
                        <?php endif; ?>
                        <!-- Datos del profesional (automáticos, no editables) -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label class="form-label">Correo (automático)</label>
                                <input
                                    type="email"
                                    class="form-control"
                                    value="<?= htmlspecialchars($professional['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    disabled
                                >
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Nombre (automático)</label>
                                <input
                                    type="text"
                                    class="form-control"
                                    value="<?= htmlspecialchars(trim(($professional['name'] ?? '') . ' ' . ($professional['last_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?>"
                                    disabled
                                >
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Rol / Profesión</label>
                                <input
                                    type="text"
                                    class="form-control"
                                    value="<?= htmlspecialchars($professional['profession'] ?: ($professional['role'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    disabled
                                >
                            </div>
                        </div>

                        <!-- Datos básicos de la AoAT -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label class="form-label">Número de la AoAT o actividad <span class="text-danger">*</span></label>
                                <input
                                    type="text"
                                    name="aoat_number"
                                    class="form-control"
                                    value="<?= htmlspecialchars((string) ($oldPayload['aoat_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    required
                                    <?= $periodReadOnly && !$numberOnlyEdit ? 'disabled' : '' ?>
                                >
                                <?php if ($numberOnlyEdit): ?>
                                    <div class="form-text">
                                        Puedes corregir o actualizar el número de la AoAT o actividad en cualquier momento; el resto del formulario permanece bloqueado mientras el registro esté en estado <?= htmlspecialchars($currentState, ENT_QUOTES, 'UTF-8') ?>.
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Fecha de la actividad <span class="text-danger">*</span></label>
                                <input
                                    type="date"
                                    name="activity_date"
                                    class="form-control"
                                    min="2026-01-01"
                                    value="<?= htmlspecialchars((string) ($oldPayload['activity_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    required
                                    aria-describedby="activity-date-help"
                                >
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Actividad que realizó <span class="text-danger">*</span></label>
                                <?php $activityType = (string) ($oldPayload['activity_type'] ?? ''); ?>
                                <select name="activity_type" class="form-select" required>
                                    <option value="">Seleccione una opción</option>
                                    <option value="Asistencia técnica" <?= $activityType === 'Asistencia técnica' ? 'selected' : '' ?>>Asistencia técnica</option>
                                    <option value="Asesoría" <?= $activityType === 'Asesoría' ? 'selected' : '' ?>>Asesoría</option>
                                    <option value="Actividad" <?= $activityType === 'Actividad' ? 'selected' : '' ?>>Actividad</option>
                                </select>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-8">
                                <label class="form-label">Con quién realizó la actividad <span class="text-danger">*</span></label>
                                <?php
                                $activityWith = (string) ($oldPayload['activity_with'] ?? '');
                                $activityWithOptions = is_array($activityWithOptions ?? null) ? $activityWithOptions : [];
                                ?>
                                <select name="activity_with" class="form-select" required>
                                    <option value="">Seleccione una opción</option>
                                    <?php foreach ($activityWithOptions as $option): ?>
                                        <?php $optionLabel = trim((string) $option); ?>
                                        <?php if ($optionLabel === '') { continue; } ?>
                                        <option value="<?= htmlspecialchars($optionLabel, ENT_QUOTES, 'UTF-8') ?>" <?= $activityWith === $optionLabel ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($optionLabel, ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Estado de la AoAT</label>
                                <input
                                    type="text"
                                    class="form-control"
                                    value="<?= htmlspecialchars(
                                        $currentState,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                    disabled
                                >
                                <div class="form-text">
                                    <?php if ($periodReadOnly && !$numberOnlyEdit): ?>
                                        Periodo cerrado: el registro se muestra en modo lectura.
                                    <?php elseif ($numberOnlyEdit): ?>
                                        Solo el número AoAT está habilitado temporalmente. Los demás campos quedan bloqueados para conservar el registro aprobado o realizado.
                                    <?php elseif (!$isEdit): ?>
                                        Se registra como <strong>Asignada</strong>. Luego el especialista puede aprobarla o devolverla.
                                    <?php elseif (($record['state'] ?? '') === 'Devuelta'): ?>
                                        <strong>Devuelta</strong> para ajustes. Realiza los cambios y al final usa <strong>Guardar cambios y marcar como realizado</strong>.
                                    <?php elseif (($record['state'] ?? '') === 'Realizado'): ?>
                                        En revisión del especialista (no editable hasta aprobación o nueva devolución).
                                    <?php else: ?>
                                        Flujo: Asignada → (auditoría) Devuelta o Aprobada; si fue devuelta: el profesional pasa a Realizado → el especialista aprueba.
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <?php if ($isEdit && ($record['state'] ?? '') === 'Devuelta' && !$numberOnlyEdit && !$periodReadOnly): ?>
                            <div class="alert alert-warning border-0 shadow-sm mb-4">
                                <strong>AoAT devuelta.</strong> Ajusta la información necesaria y al final del formulario guarda los cambios para enviarla nuevamente a revisión.
                            </div>
                            <div class="mb-4">
                                <label class="form-label" for="professional_compliance_note">
                                    ¿Qué hiciste para atender la devolución? <span class="text-danger">*</span>
                                </label>
                                <textarea
                                    name="professional_compliance_note"
                                    id="professional_compliance_note"
                                    class="form-control"
                                    rows="4"
                                    minlength="15"
                                    required
                                    placeholder="Describe de forma breve los ajustes o acciones realizadas (mínimo 15 caracteres). El especialista leerá esto al revisar tu registro."
                                ><?= htmlspecialchars((string) ($oldPayload[\App\Controllers\AoatController::PAYLOAD_PROFESSIONAL_COMPLIANCE_NOTE] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                                <div class="form-text">
                                    Este texto se guarda en el registro y lo verá el especialista junto con los demás datos de la AoAT.
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Lugar visitado -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Subregión que visitó <span class="text-danger">*</span></label>
                                <select
                                    name="subregion"
                                    class="form-select"
                                    data-subregion-select
                                    data-current-value="<?= htmlspecialchars((string) ($formData['subregion'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    required
                                    <?= $lockNonNumberFields ? 'disabled' : '' ?>
                                >
                                    <option value="">Seleccione la subregión que visitó</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Municipio visitado <span class="text-danger">*</span></label>
                                <select
                                    name="municipality"
                                    class="form-select"
                                    data-municipality-select
                                    data-current-value="<?= htmlspecialchars((string) ($formData['municipality'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    required
                                    disabled
                                >
                                    <option value="">Seleccione el municipio visitado</option>
                                </select>
                            </div>
                        </div>

                        <hr class="my-4 app-form-divider">

                        <?php
                        $qualificationRole = $role;
                        $readOnly = $formReadOnly;
                        require dirname(__DIR__) . '/partials/qualification_sections.php';
                        ?>

                        <?php if (!($periodReadOnly && !$numberOnlyEdit)): ?>
                            <div class="d-flex justify-content-end app-form-submit">
                                <button type="submit" class="btn btn-primary">
                                    <?= $numberOnlyEdit
                                        ? 'Guardar número AoAT'
                                        : ($isEdit && (($record['state'] ?? '') === 'Devuelta')
                                        ? 'Guardar cambios y marcar como realizado'
                                        : 'Guardar AoAT') ?>
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="d-flex justify-content-end app-form-submit">
                                <a href="/aoat" class="btn btn-outline-secondary">Volver al listado</a>
                            </div>
                        <?php endif; ?>
                    </form>
                    <?php if ($lockNonNumberFields): ?>
                        <script>
                            document.addEventListener('DOMContentLoaded', function () {
                                var form = document.querySelector('.aoat-form');
                                if (!form) {
                                    return;
                                }

                                form.querySelectorAll('input, select, textarea').forEach(function (field) {
                                    if (
                                        (field.name === 'aoat_number' && <?= $numberOnlyEdit ? 'true' : 'false' ?>) ||
                                        field.name === 'id' ||
                                        field.type === 'hidden' ||
                                        field.type === 'submit'
                                    ) {
                                        return;
                                    }

                                    field.disabled = true;
                                });
                            });
                        </script>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>
