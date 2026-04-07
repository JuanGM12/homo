<?php
/** @var array|null $record */
/** @var array $professional */
/** @var array $oldInput */

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
$prevSuicidio = isset($formData['prev_suicidio']) && is_array($formData['prev_suicidio']) ? $formData['prev_suicidio'] : [];
$prevViolencias = isset($formData['prev_violencias']) && is_array($formData['prev_violencias']) ? $formData['prev_violencias'] : [];
$prevAdicciones = isset($formData['prev_adicciones']) && is_array($formData['prev_adicciones']) ? $formData['prev_adicciones'] : [];
$saludMental = isset($formData['salud_mental']) && is_array($formData['salud_mental']) ? $formData['salud_mental'] : [];
$proyectoSeleccionado = isset($formData['proyecto']) ? (string) $formData['proyecto'] : '';
$mesaSaludMental = isset($formData['mesa_salud_mental']) && is_array($formData['mesa_salud_mental']) ? $formData['mesa_salud_mental'] : [];
$ppmsmypaSel = isset($formData['ppmsmypa']) && is_array($formData['ppmsmypa']) ? $formData['ppmsmypa'] : [];
$saferSel = isset($formData['safer']) && is_array($formData['safer']) ? $formData['safer'] : [];
$temasHospital = isset($formData['temas_hospital']) && is_array($formData['temas_hospital']) ? $formData['temas_hospital'] : [];
$actividadSocial = isset($formData['actividad_social']) && is_array($formData['actividad_social']) ? $formData['actividad_social'] : [];
?>

<section class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="/aoat">AoAT</a></li>
            <li class="breadcrumb-item active" aria-current="page">
                <?= $isEdit ? 'Editar AoAT' : 'Nueva AoAT' ?>
            </li>
        </ol>
    </nav>

    <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-9">
            <div class="card border-0 app-form-card">
                <div class="card-body p-4 p-md-5">
                    <h1 class="h4 fw-bold mb-4"><?= $isEdit ? 'Editar AoAT' : 'Registrar nueva AoAT' ?></h1>

                    <form
                        class="aoat-form"
                        method="post"
                        action="<?= $isEdit ? '/aoat/editar' : '/aoat/nueva' ?>"
                        <?= $numberOnlyEdit ? 'data-number-only-edit="1"' : '' ?>
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
                                <input
                                    type="text"
                                    name="activity_with"
                                    class="form-control"
                                    value="<?= htmlspecialchars((string) ($oldPayload['activity_with'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    required
                                >
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
                                    <?php if ($numberOnlyEdit): ?>
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

                        <?php if ($isEdit && ($record['state'] ?? '') === 'Devuelta'): ?>
                            <div class="alert alert-warning border-0 shadow-sm mb-4">
                                <strong>AoAT devuelta.</strong> Ajusta la información necesaria y al final del formulario guarda los cambios para enviarla nuevamente a revisión.
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
                                    <?= $numberOnlyEdit ? 'disabled' : '' ?>
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
                                    <?= $numberOnlyEdit ? 'disabled' : 'disabled' ?>
                                >
                                    <option value="">Seleccione el municipio visitado</option>
                                </select>
                            </div>
                        </div>

                        <hr class="my-4 app-form-divider">

                        <?php if ($role === 'abogado'): ?>
                            <div class="mb-3 app-form-section-title">
                                <h2 class="h6 fw-semibold mb-1">Temas de Política Pública en Salud Mental (Abogado)</h2>
                                <p class="text-muted small mb-0">
                                    Marca todos los módulos que trabajaste durante esta AoAT. Puedes seleccionar varias opciones.
                                </p>
                            </div>

                            <div class="app-form-questions">
                            <!-- Actualización Mesa Municipal de Salud Mental y Prevención de las Adicciones -->
                            <div class="mb-4 app-form-question">
                                <div class="aoat-qual-section-header mb-3">
                                    <h3 class="aoat-qual-section-title mb-1">Actualización de la Mesa Municipal de Salud Mental y Prevención de las Adicciones</h3>
                                    <p class="text-muted small mb-0"><span class="aoat-qual-hint">Selección múltiple</span></p>
                                </div>
                                <div class="row g-2">
                                    <div class="col-md-6 col-lg-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="mesa_salud_mental[]" value="Módulo 1" <?= in_array('Módulo 1', $mesaSaludMental, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                Módulo 1: Conformación y fortalecimiento de la mesa.
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="mesa_salud_mental[]" value="Módulo 2" <?= in_array('Módulo 2', $mesaSaludMental, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                Módulo 2: Secretaría técnica, reglamento y plan de acción.
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="mesa_salud_mental[]" value="Módulo 3" <?= in_array('Módulo 3', $mesaSaludMental, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                Módulo 3: Convocatoria para conformar la mesa.
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-lg-4 mt-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="mesa_salud_mental[]" value="No aplica" <?= in_array('No aplica', $mesaSaludMental, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                No aplica
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Actualización Política Pública Municipal de Salud y Prevención de las Adicciones -->
                            <div class="mb-4 app-form-question">
                                <div class="aoat-qual-section-header mb-3">
                                    <h3 class="aoat-qual-section-title mb-1">Actualización de la Política Pública Municipal de Salud y Prevención de las Adicciones (PPMSMYPA)</h3>
                                    <p class="text-muted small mb-0"><span class="aoat-qual-hint">Selección múltiple</span></p>
                                </div>
                                <div class="row g-2">
                                    <div class="col-md-6 col-lg-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="ppmsmypa[]" value="Módulo 4" <?= in_array('Módulo 4', $ppmsmypaSel, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                Módulo 4: Actualización de la política pública municipal de salud mental y prevención de las adicciones – ciclo agenda.
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-lg-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="ppmsmypa[]" value="Módulo 5" <?= in_array('Módulo 5', $ppmsmypaSel, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                Módulo 5: Actualización de la política pública municipal de salud mental y prevención de las adicciones - ciclo formulación de la política pública de salud mental.
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-lg-4 mt-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="ppmsmypa[]" value="No aplica" <?= in_array('No aplica', $ppmsmypaSel, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                No aplica
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- SAFER -->
                            <div class="mb-4 app-form-question">
                                <div class="aoat-qual-section-header mb-3">
                                    <h3 class="aoat-qual-section-title mb-1">SAFER</h3>
                                    <p class="text-muted small mb-0"><span class="aoat-qual-hint">Selección múltiple</span></p>
                                </div>
                                <div class="row g-2">
                                    <div class="col-md-6 col-lg-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="safer[]" value="Módulo 1" <?= in_array('Módulo 1', $saferSel, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                Módulo 1: Socialización de la problemática pública del alcohol, generalidad estrategia SAFER, legislación actual.
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="safer[]" value="Módulo 2" <?= in_array('Módulo 2', $saferSel, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                Módulo 2: Socialización de la problemática pública del alcohol, generalidad estrategia SAFER, legislación actual.
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="safer[]" value="Módulo 3" <?= in_array('Módulo 3', $saferSel, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                Módulo 3: Legislación actual con énfasis en consumo de menores y mujeres en estado de gestación, socialización de la problemática pública del alcohol, violencias relacionadas por el alcohol.
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-lg-4 mt-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="safer[]" value="Módulo 4" <?= in_array('Módulo 4', $saferSel, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                Módulo 4: Legislación actual con énfasis en consumo de menores y mujeres en estado de gestación, socialización de la problemática pública del alcohol.
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-lg-4 mt-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="safer[]" value="Módulo 5" <?= in_array('Módulo 5', $saferSel, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                Módulo 5: Socialización de la problemática pública del alcohol, responsabilidad civil y penal.
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-lg-4 mt-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="safer[]" value="No aplica" <?= in_array('No aplica', $saferSel, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                No aplica
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            </div>

                            <!-- Pregunta al final de la cualificación -->
                            <div class="mb-4">
                                <label class="form-label">
                                    ¿Identifica otro caso diferente? Describa cuál
                                </label>
                                <textarea name="otro_caso" class="form-control" rows="3" placeholder="Describa aquí otro caso diferente, si aplica."><?= htmlspecialchars((string) ($formData['otro_caso'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>

                        <?php elseif ($role === 'medico'): ?>
                            <div class="mb-3 app-form-section-title">
                                <h2 class="h6 fw-semibold mb-1">Temas dictados en el Hospital del municipio visitado (Médico)</h2>
                                <p class="text-muted small mb-0">
                                    Selecciona todos los temas que trabajaste en esta actividad. Es selección múltiple.
                                </p>
                            </div>

                            <div class="mb-4">
                                <label class="form-label d-block">
                                    Seleccione el/los temas que dictó en el Hospital del municipio visitado
                                    <span class="text-muted small">(selección múltiple)</span>
                                </label>
                                <div class="row">
                                    <div class="col-md-6 col-lg-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="temas_hospital[]" value="Abordaje del manejo de alcohol en el primer nivel de atención – Alcohol y embarazo." <?= in_array('Abordaje del manejo de alcohol en el primer nivel de atención – Alcohol y embarazo.', $temasHospital, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                Abordaje del manejo de alcohol en el primer nivel de atención – Alcohol y embarazo.
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="temas_hospital[]" value="Abordaje del manejo de tabaco en el primer nivel." <?= in_array('Abordaje del manejo de tabaco en el primer nivel.', $temasHospital, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                Abordaje del manejo de tabaco en el primer nivel.
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="temas_hospital[]" value="Adicciones en la baja complejidad" <?= in_array('Adicciones en la baja complejidad', $temasHospital, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                Adicciones en la baja complejidad
                                            </label>
                                        </div>
                                    </div>

                                    <div class="col-md-6 col-lg-4 mt-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="temas_hospital[]" value="Conducta suicida" <?= in_array('Conducta suicida', $temasHospital, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                Conducta suicida
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-lg-4 mt-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="temas_hospital[]" value="Desmonte de benzodiacepinas" <?= in_array('Desmonte de benzodiacepinas', $temasHospital, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                Desmonte de benzodiacepinas
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-lg-4 mt-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="temas_hospital[]" value="Desmonte de opioides" <?= in_array('Desmonte de opioides', $temasHospital, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                Desmonte de opioides
                                            </label>
                                        </div>
                                    </div>

                                    <div class="col-md-6 col-lg-4 mt-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="temas_hospital[]" value="Epilepsia" <?= in_array('Epilepsia', $temasHospital, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                Epilepsia
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-lg-4 mt-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="temas_hospital[]" value="Intoxicaciones por medicamentos de control" <?= in_array('Intoxicaciones por medicamentos de control', $temasHospital, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                Intoxicaciones por medicamentos de control
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-lg-4 mt-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="temas_hospital[]" value="Manejo del dolor" <?= in_array('Manejo del dolor', $temasHospital, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                Manejo del dolor
                                            </label>
                                        </div>
                                    </div>

                                    <div class="col-md-6 col-lg-4 mt-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="temas_hospital[]" value="Paciente agitado" <?= in_array('Paciente agitado', $temasHospital, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                Paciente agitado
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-lg-4 mt-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="temas_hospital[]" value="Pre Test" <?= in_array('Pre Test', $temasHospital, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                Pre Test
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-lg-4 mt-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="temas_hospital[]" value="Post Test" <?= in_array('Post Test', $temasHospital, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                Post Test
                                            </label>
                                        </div>
                                    </div>

                                    <div class="col-md-6 col-lg-4 mt-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="temas_hospital[]" value="Trastorno Afectivo Bipolar" <?= in_array('Trastorno Afectivo Bipolar', $temasHospital, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                Trastorno Afectivo Bipolar
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-lg-4 mt-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="temas_hospital[]" value="Trastorno de Déficit de Atención e Hiperactividad" <?= in_array('Trastorno de Déficit de Atención e Hiperactividad', $temasHospital, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                Trastorno de Déficit de Atención e Hiperactividad
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-lg-4 mt-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="temas_hospital[]" value="Trastorno Depresivo" <?= in_array('Trastorno Depresivo', $temasHospital, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                Trastorno Depresivo
                                            </label>
                                        </div>
                                    </div>

                                    <div class="col-md-6 col-lg-4 mt-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="temas_hospital[]" value="Trastorno Psicótico" <?= in_array('Trastorno Psicótico', $temasHospital, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                Trastorno Psicótico
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-lg-4 mt-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="temas_hospital[]" value="Trastornos de Ansiedad" <?= in_array('Trastornos de Ansiedad', $temasHospital, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                Trastornos de Ansiedad
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-lg-4 mt-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="temas_hospital[]" value="Trastornos del sueño" <?= in_array('Trastornos del sueño', $temasHospital, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                Trastornos del sueño
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Pregunta al final de la cualificación -->
                            <div class="mb-4">
                                <label class="form-label">
                                    ¿Identifica otro caso diferente? Describa cuál
                                </label>
                                <textarea name="otro_caso" class="form-control" rows="3" placeholder="Describa aquí otro caso diferente, si aplica."><?= htmlspecialchars((string) ($formData['otro_caso'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>

                        <?php elseif ($role === 'psicologo'): ?>
                            <div class="mb-3 app-form-section-title">
                                <h2 class="h6 fw-semibold mb-1">Cualificación de temas (Psicólogo)</h2>
                                <p class="text-muted small mb-0">
                                    Selecciona los temas que trabajaste en esta AoAT. Algunas preguntas son de selección múltiple y otras de selección única.
                                </p>
                            </div>

                            <div class="app-form-questions">
                            <!-- Cualificación temas en prevención del suicidio -->
                            <div class="mb-4 app-form-question">
                                <div class="aoat-qual-section-header mb-3">
                                    <h3 class="aoat-qual-section-title mb-1">Cualificación temas en prevención del suicidio</h3>
                                    <p class="text-muted small mb-0"><span class="aoat-qual-hint">Selección múltiple</span></p>
                                </div>
                                <div class="row g-2">
                                    <div class="col-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="prev_suicidio[]" value="Módulo 1" <?= in_array('Módulo 1', $prevSuicidio, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                Módulo 1: Evolución histórica del suicidio, aproximación conceptual de la conducta suicida, teorías explicativas de primera generación, teorías explicativas de segunda generación, factores de riesgo (biológicos, psiquiátricos, psicológicos y sociales), factores de protección, señales de alarma, ruta de atención y articulación intersectorial, notificación y seguimiento, plan de seguridad.
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-12 mt-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="prev_suicidio[]" value="Módulo 2" <?= in_array('Módulo 2', $prevSuicidio, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                Módulo 2: Comunicación y suicidio como factor de riesgo y de protección, impacto del lenguaje y los mensajes, efecto Werther, efecto Papageno, principios de la comunicación responsable, recomendaciones de la OMS para medios y contextos comunitarios, pautas de lo que se debe y no se debe comunicar, aplicación del efecto Papageno en contextos comunitarios e institucionales, roles y responsabilidades de actores clave, poder de la narrativa y reducción del estigma, recursos y guías para la comunicación responsable.
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-12 mt-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="prev_suicidio[]" value="Módulo 3" <?= in_array('Módulo 3', $prevSuicidio, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                Módulo 3: Concepto y alcances de la posvención, posvención como estrategia de prevención y salud pública, impacto psicosocial del suicidio, duelo por suicidio y sus particularidades, duelo y tamizajes para suicidio (RQC, SRQ, Whooley, GAD-2, Zarit, Plutchick, PHQ-9, C-SSRS), estigma y silencios, principios orientadores de la posvención, acciones de posvención en el territorio, acompañamiento a familias e instituciones, comunicación posterior a una muerte por suicidio, identificación y seguimiento de personas en riesgo, articulación con servicios de salud mental, autocuidado del profesional psicosocial.
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-12 mt-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="prev_suicidio[]" value="No aplica" <?= in_array('No aplica', $prevSuicidio, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                No aplica
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Cualificación temas en prevención de Violencias -->
                            <div class="mb-4 app-form-question">
                                <div class="aoat-qual-section-header mb-3">
                                    <h3 class="aoat-qual-section-title mb-1">Cualificación temas en prevención de Violencias</h3>
                                    <p class="text-muted small mb-0"><span class="aoat-qual-hint">Selección múltiple</span></p>
                                </div>
                                <div class="row g-2">
                                    <div class="col-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="prev_violencias[]" value="Módulo 1" <?= in_array('Módulo 1', $prevViolencias, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                Módulo 1: Definición, marco normativo, epidemiología, tipología, características.
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-12 mt-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="prev_violencias[]" value="Módulo 2" <?= in_array('Módulo 2', $prevViolencias, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                Módulo 2: Violencias interpersonales, violencia familiar y de pareja, violencia comunitaria, violencia juvenil, bullying.
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-12 mt-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="prev_violencias[]" value="Módulo 3" <?= in_array('Módulo 3', $prevViolencias, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                Módulo 3: Modelos de prevención de las violencias interpersonales (prevención universal, selectiva, indicada y de recurrencias), programas basados en la evidencia para la prevención de las violencias (modelo INSPIRE, modelo RESPETO y otros).
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-12 mt-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="prev_violencias[]" value="No aplica" <?= in_array('No aplica', $prevViolencias, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                No aplica
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Cualificación temas en prevención de Adicciones -->
                            <div class="mb-4 app-form-question">
                                <div class="aoat-qual-section-header mb-3">
                                    <h3 class="aoat-qual-section-title mb-1">Cualificación temas en prevención de Adicciones</h3>
                                    <p class="text-muted small mb-0"><span class="aoat-qual-hint">Selección múltiple</span></p>
                                </div>
                                <div class="row g-2">
                                    <div class="col-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="prev_adicciones[]" value="Módulo 1" <?= in_array('Módulo 1', $prevAdicciones, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                Módulo 1: Modelos explicativos (biopsicosocial, aprendizaje y condicionamiento), neurobiología de las adicciones, determinantes sociales, factores de riesgo y de protección, prevención basada en evidencia, influencia normativa.
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-12 mt-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="prev_adicciones[]" value="Módulo 2" <?= in_array('Módulo 2', $prevAdicciones, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                Módulo 2: Comprensión de las adicciones según tipo de sustancia, dependencias comportamentales (juego patológico, nomofobia, juegos electrónicos, oniomanía, adicción al trabajo, vigorexia), cigarrillos electrónicos, cannabis, patología dual.
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-12 mt-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="prev_adicciones[]" value="Módulo 3" <?= in_array('Módulo 3', $prevAdicciones, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                Módulo 3: Rutas de atención, tamizajes (ASSIST, AUDIT, CRAFFT, Fagerström), intervenciones (entrevista motivacional, intervención única, mindfulness), grupos de apoyo, reducción de riesgos y daños.
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-12 mt-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="prev_adicciones[]" value="No aplica" <?= in_array('No aplica', $prevAdicciones, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                No aplica
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Cualificación temas de Salud Mental -->
                            <div class="mb-4 app-form-question">
                                <div class="aoat-qual-section-header mb-3">
                                    <h3 class="aoat-qual-section-title mb-1">Cualificación temas de Salud Mental</h3>
                                    <p class="text-muted small mb-0"><span class="aoat-qual-hint">Selección múltiple</span></p>
                                </div>
                                <div class="row g-2">
                                    <div class="col-md-6 col-lg-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="salud_mental[]" value="Cuidado al cuidador" <?= in_array('Cuidado al cuidador', $saludMental, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                Cuidado al cuidador
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="salud_mental[]" value="Cuidado del profesional – burnout" <?= in_array('Cuidado del profesional – burnout', $saludMental, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                Cuidado del profesional – burnout
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="salud_mental[]" value="Estigma" <?= in_array('Estigma', $saludMental, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                Estigma
                                            </label>
                                        </div>
                                    </div>

                                    <div class="col-md-6 col-lg-6 mt-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="salud_mental[]" value="Grupos de apoyo y ayuda mutua (violencias, SPA, suicidio): teoría y conformación" <?= in_array('Grupos de apoyo y ayuda mutua (violencias, SPA, suicidio): teoría y conformación', $saludMental, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                Grupos de apoyo y ayuda mutua (violencias, SPA, suicidio): teoría y conformación
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-lg-6 mt-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="salud_mental[]" value="Primeros auxilios psicológicos e intervención en crisis" <?= in_array('Primeros auxilios psicológicos e intervención en crisis', $saludMental, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                Primeros auxilios psicológicos e intervención en crisis
                                            </label>
                                        </div>
                                    </div>

                                    <div class="col-md-6 col-lg-6 mt-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="salud_mental[]" value="Trastornos mentales prioritarios de interés en salud pública" <?= in_array('Trastornos mentales prioritarios de interés en salud pública', $saludMental, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                Trastornos mentales prioritarios de interés en salud pública
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-lg-6 mt-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="salud_mental[]" value="No aplica" <?= in_array('No aplica', $saludMental, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                No aplica
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Proyectos (selección única) -->
                            <div class="mb-4 app-form-question">
                                <div class="aoat-qual-section-header mb-3">
                                    <h3 class="aoat-qual-section-title mb-1">Proyectos <span class="text-danger">*</span></h3>
                                    <p class="text-muted small mb-0"><span class="aoat-qual-hint">Selección única (obligatoria)</span></p>
                                </div>
                                <div class="row g-2">
                                    <div class="col-md-6 col-lg-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="proyecto" value="Competencias Parentales" required <?= $proyectoSeleccionado === 'Competencias Parentales' ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                Competencias Parentales
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="proyecto" value="Familias que se Cuidan" <?= $proyectoSeleccionado === 'Familias que se Cuidan' ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                Familias que se Cuidan
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="proyecto" value="La Aventura de Crecer" <?= $proyectoSeleccionado === 'La Aventura de Crecer' ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                La Aventura de Crecer
                                            </label>
                                        </div>
                                    </div>

                                    <div class="col-md-6 col-lg-4 mt-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="proyecto" value="Veredas que se Cuidan" <?= $proyectoSeleccionado === 'Veredas que se Cuidan' ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                Veredas que se Cuidan
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-lg-4 mt-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="proyecto" value="Dispositivos comunitarios" <?= $proyectoSeleccionado === 'Dispositivos comunitarios' ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                Dispositivos comunitarios
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-lg-4 mt-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="proyecto" value="Presentación del programa salud para el alma" <?= $proyectoSeleccionado === 'Presentación del programa salud para el alma' ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                Presentación del programa salud para el alma
                                            </label>
                                        </div>
                                    </div>

                                    <div class="col-md-6 col-lg-4 mt-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="proyecto" value="No aplica" <?= $proyectoSeleccionado === 'No aplica' ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                No aplica
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Pregunta al final de la cualificación -->
                            <div class="mb-4">
                                <label class="form-label">
                                    ¿Identifica otro caso diferente? Describa cuál
                                </label>
                                <textarea name="otro_caso" class="form-control" rows="3" placeholder="Describa aquí otro caso diferente, si aplica."><?= htmlspecialchars((string) ($formData['otro_caso'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>

                        <?php elseif ($role === 'profesional social' || $role === 'profesional_social'): ?>
                            <div class="mb-3 app-form-section-title">
                                <h2 class="h6 fw-semibold mb-1">Actividades realizadas (Profesional Social)</h2>
                                <p class="text-muted small mb-0">
                                    Selecciona la(s) actividad(es) que realizaste en esta AoAT. Es selección múltiple.
                                </p>
                            </div>

                            <div class="app-form-questions">
                            <div class="mb-4 app-form-question">
                                <div class="aoat-qual-section-header mb-3">
                                    <h3 class="aoat-qual-section-title mb-1">Seleccione la actividad realizada</h3>
                                    <p class="text-muted small mb-0"><span class="aoat-qual-hint">Selección múltiple</span></p>
                                </div>
                                <div class="row g-2">
                                    <div class="col-md-6 col-lg-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="actividad_social[]" value="Formación (desarrollo de capacidades)" <?= in_array('Formación (desarrollo de capacidades)', $actividadSocial, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                Formación (desarrollo de capacidades)
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="actividad_social[]" value="Espacio de articulación" <?= in_array('Espacio de articulación', $actividadSocial, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                Espacio de articulación
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="actividad_social[]" value="Actividad de apoyo" <?= in_array('Actividad de apoyo', $actividadSocial, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label small">
                                                Actividad de apoyo
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            </div>

                            <!-- Pregunta al final de la cualificación -->
                            <div class="mb-4">
                                <label class="form-label">
                                    ¿Identifica otro caso diferente? Describa cuál
                                </label>
                                <textarea name="otro_caso" class="form-control" rows="3" placeholder="Describa aquí otro caso diferente, si aplica."><?= htmlspecialchars((string) ($formData['otro_caso'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>

                        <?php else: ?>
                            <div class="mb-4">
                                <p class="text-muted small mb-0">
                                    Próximamente configuraremos las preguntas específicas para tu perfil profesional.
                                </p>
                            </div>
                        <?php endif; ?>

                        <div class="d-flex justify-content-end app-form-submit">
                            <button type="submit" class="btn btn-primary">
                                <?= $numberOnlyEdit
                                    ? 'Guardar número AoAT'
                                    : ($isEdit && (($record['state'] ?? '') === 'Devuelta')
                                    ? 'Guardar cambios y marcar como realizado'
                                    : 'Guardar AoAT') ?>
                            </button>
                        </div>
                    </form>
                    <?php if ($numberOnlyEdit): ?>
                        <script>
                            document.addEventListener('DOMContentLoaded', function () {
                                var form = document.querySelector('.aoat-form');
                                if (!form) {
                                    return;
                                }

                                form.querySelectorAll('input, select, textarea').forEach(function (field) {
                                    if (
                                        field.name === 'aoat_number' ||
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

