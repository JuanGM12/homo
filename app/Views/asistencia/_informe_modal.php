<?php
/** @var array<int, array<string, mixed>> $advisors */
/** @var bool $canFilterAdvisor */
/** @var bool $canConfigureInformeScope */
/** @var array<string, string> $informeRoles */
/** @var string $modalId */
/** @var string $formId */

$modalId = (string) ($modalId ?? 'homeInformeModal');
$formId = (string) ($formId ?? 'home-informe-form');
$canConfigureInformeScope = (bool) ($canConfigureInformeScope ?? false);
$canFilterAdvisor = (bool) ($canFilterAdvisor ?? false);
$informeRoles = is_array($informeRoles ?? null) ? $informeRoles : [];
$advisors = is_array($advisors ?? null) ? $advisors : [];
$defaultAdvisorId = (int) ($defaultAdvisorId ?? 0);
?>
<div class="modal fade" id="<?= htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8') ?>" tabindex="-1" aria-labelledby="<?= htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8') ?>Label" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h2 class="modal-title h5 fw-bold mb-1" id="<?= htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8') ?>Label">
                        Informe de gestión (Word)
                    </h2>
                    <p class="small text-muted mb-0">Define municipio, fechas y filtros antes de previsualizar y descargar.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body pt-3">
                <form id="<?= htmlspecialchars($formId, ENT_QUOTES, 'UTF-8') ?>" class="row g-3" data-asi-informe-form>
                    <input type="hidden" name="tab" value="aoat">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-muted mb-1">Subregión</label>
                        <select
                            name="subregion"
                            class="form-select"
                            data-subregion-select
                            data-current-value=""
                            required
                        >
                            <option value="">Seleccione subregión</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-muted mb-1">Municipio</label>
                        <select
                            name="municipality"
                            class="form-select"
                            data-municipality-select
                            data-current-value=""
                            disabled
                            required
                        >
                            <option value="">Seleccione municipio</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-muted mb-1">Fecha desde</label>
                        <input type="date" name="from_date" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-muted mb-1">Fecha hasta</label>
                        <input type="date" name="to_date" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-muted mb-1">Asesor</label>
                        <?php if ($canFilterAdvisor): ?>
                            <select name="advisor_user_id" class="form-select">
                                <option value="">Todos</option>
                                <?php foreach ($advisors as $a): ?>
                                    <option value="<?= (int) $a['id'] ?>">
                                        <?= htmlspecialchars((string) $a['name'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <select class="form-select" disabled aria-disabled="true">
                                <option selected>Mi listado</option>
                            </select>
                            <?php if ($defaultAdvisorId > 0): ?>
                                <input type="hidden" name="advisor_user_id" value="<?= $defaultAdvisorId ?>">
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-muted mb-1">Estado</label>
                        <select name="status" class="form-select">
                            <option value="">Todos (solo con asistentes)</option>
                            <option value="Pendiente">Pendiente</option>
                            <option value="Activo">Activo</option>
                            <option value="Cerrado">Cerrado</option>
                        </select>
                    </div>
                    <?php if ($canConfigureInformeScope): ?>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-muted mb-1">Alcance del informe</label>
                        <select name="informe_modo" class="form-select" data-asi-informe-modo>
                            <option value="consolidado">Consolidado (todos los roles)</option>
                            <option value="rol">Por rol profesional</option>
                            <option value="asesor">Por asesor</option>
                        </select>
                    </div>
                    <div class="col-md-6 d-none" data-asi-informe-rol-wrap>
                        <label class="form-label small fw-semibold text-muted mb-1">Rol del informe</label>
                        <select name="informe_rol" class="form-select">
                            <option value="">Seleccione rol</option>
                            <?php foreach ($informeRoles as $roleKey => $roleLabel): ?>
                                <option value="<?= htmlspecialchars((string) $roleKey, ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars((string) $roleLabel, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 d-none" data-asi-informe-asesor-wrap>
                        <label class="form-label small fw-semibold text-muted mb-1">Asesor del informe</label>
                        <select name="informe_advisor_user_id" class="form-select">
                            <option value="">Seleccione asesor</option>
                            <?php foreach ($advisors as $a): ?>
                                <option value="<?= (int) $a['id'] ?>">
                                    <?= htmlspecialchars((string) $a['name'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="col-12">
                        <div class="alert alert-light border small mb-0 py-2 px-3">
                            Los totales incluyen solo listados con al menos un asistente. La previsualización permite validar cifras antes de descargar el Word.
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button
                    type="button"
                    class="btn btn-primary"
                    data-asi-export-informe
                    data-export-base="/asistencia/exportar-informe"
                    data-preview-base="/asistencia/informe-preview"
                    data-informe-modal="<?= htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8') ?>"
                >
                    <i class="bi bi-eye me-1"></i>Previsualizar y descargar
                </button>
            </div>
        </div>
    </div>
</div>
