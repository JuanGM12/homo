<?php
/** @var array<int, array<string, mixed>> $advisors */
/** @var bool $canFilterAdvisor */
/** @var string $activeTab */
/** @var int $mergedPdfMaxListados */

$canFilterAdvisor = (bool) ($canFilterAdvisor ?? false);
$advisors = is_array($advisors ?? null) ? $advisors : [];
$activeTab = (string) ($activeTab ?? 'aoat');
$mergedPdfMaxListados = (int) ($mergedPdfMaxListados ?? 40);
?>
<div class="modal fade" id="asiExportListadosModal" tabindex="-1" aria-labelledby="asiExportListadosModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h2 class="modal-title h5 fw-bold mb-1" id="asiExportListadosModalLabel">
                        Unir listados y exportar PDF
                    </h2>
                    <p class="small text-muted mb-0">
                        Busca, selecciona uno o más listados y descarga un solo PDF con cada tabla FIPC en páginas separadas.
                    </p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body pt-3">
                <form id="asi-export-listados-form" class="row g-3" data-asi-export-listados-form data-territory-filter>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-muted mb-1">Buscar</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input
                                type="search"
                                name="q"
                                class="form-control"
                                placeholder="Código, lugar, municipio, temática…"
                                autocomplete="off"
                            >
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-muted mb-1">Tipo</label>
                        <select name="tab" class="form-select">
                            <option value="aoat" <?= $activeTab === 'aoat' ? 'selected' : '' ?>>AoAT</option>
                            <option value="actividad" <?= $activeTab === 'actividad' ? 'selected' : '' ?>>Actividades</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-muted mb-1">Estado</label>
                        <select name="status" class="form-select">
                            <option value="">Todos</option>
                            <option value="Pendiente">Pendiente</option>
                            <option value="Activo">Activo</option>
                            <option value="Cerrado">Cerrado</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-muted mb-1">Subregión</label>
                        <select name="subregion" class="form-select" data-subregion-select data-current-value="">
                            <option value="">Todas</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-muted mb-1">Municipio(s)</label>
                        <select
                            name="municipality[]"
                            class="form-select"
                            multiple
                            data-municipality-select
                            data-municipality-multi="1"
                            data-current-values="[]"
                            disabled
                        ></select>
                    </div>
                    <div class="col-md-4">
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
                            <?php if (count($advisors) === 1): ?>
                                <input type="hidden" name="advisor_user_id" value="<?= (int) ($advisors[0]['id'] ?? 0) ?>">
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-muted mb-1">Fecha desde</label>
                        <input type="date" name="from_date" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-muted mb-1">Fecha hasta</label>
                        <input type="date" name="to_date" class="form-control">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-funnel me-1"></i>Buscar listados
                        </button>
                    </div>
                </form>

                <div class="asi-export-picker mt-4" data-asi-export-picker data-max-listados="<?= $mergedPdfMaxListados ?>">
                    <div class="asi-export-picker-toolbar">
                        <label class="asi-export-select-all mb-0">
                            <input type="checkbox" class="form-check-input" data-asi-export-select-all>
                            <span>Seleccionar todos</span>
                        </label>
                        <span class="asi-export-picker-count small text-muted" data-asi-export-count>
                            0 seleccionados · 0 asistentes
                        </span>
                    </div>
                    <div class="asi-export-picker-list" data-asi-export-list>
                        <div class="asi-export-picker-empty text-muted small">
                            Usa los filtros y pulsa «Buscar listados» para ver resultados.
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 flex-wrap gap-2">
                <span class="small text-muted me-auto">
                    Máximo <?= $mergedPdfMaxListados ?> listados por PDF.
                </span>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button
                    type="button"
                    class="btn btn-danger"
                    data-asi-export-pdf-unidos
                    data-export-base="/asistencia/exportar-pdf-unidos"
                    disabled
                >
                    <i class="bi bi-file-earmark-pdf me-1"></i>Exportar PDF unido
                </button>
            </div>
        </div>
    </div>
</div>
