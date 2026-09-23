<?php
/** @var bool $canViewAll */
/** @var bool $isAdmin */
/** @var array<string, string> $activityTypes */
/** @var list<array{value: string, label: string}> $topicOptions */
/** @var list<string> $hourOptions */
/** @var array<int, array<string, mixed>> $professionals */
/** @var array<int, array{subregion:string, municipality:string}> $allowedMunicipalities */
/** @var int $currentUserId */

$canViewAll = (bool) ($canViewAll ?? false);
$isAdmin = (bool) ($isAdmin ?? false);
$activityTypes = is_array($activityTypes ?? null) ? $activityTypes : [];
$topicOptions = is_array($topicOptions ?? null) ? $topicOptions : [];
$hourOptions = is_array($hourOptions ?? null) ? $hourOptions : [];
$professionals = is_array($professionals ?? null) ? $professionals : [];
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
$professionalsJson = json_encode(
    $professionals,
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS
);
if (!is_string($professionalsJson) || $professionalsJson === '') {
    $professionalsJson = '[]';
}
?>

<section class="cronograma-page mt-4 mb-5">
    <div class="calendario-toolbar">
        <div class="calendario-nav">
            <button type="button" class="btn btn-outline-secondary btn-sm calendario-prev" title="Mes anterior">
                <i class="bi bi-chevron-left"></i>
            </button>
            <h1 class="calendario-mes-titulo" id="calMesTitulo">—</h1>
            <button type="button" class="btn btn-outline-secondary btn-sm calendario-next" title="Mes siguiente">
                <i class="bi bi-chevron-right"></i>
            </button>
        </div>
        <div class="calendario-actions">
            <?php if ($canViewAll): ?>
                <select id="calFiltroSubregion" class="form-select form-select-sm">
                    <option value="">Todas las subregiones</option>
                </select>
                <select id="calFiltroRol" class="form-select form-select-sm">
                    <option value="">Todos los roles</option>
                    <option value="psicologo">Psicólogo</option>
                    <option value="medico">Médico</option>
                    <option value="abogado">Abogado</option>
                    <option value="politologo">Politólogo</option>
                    <option value="profesional social">Profesional social</option>
                </select>
                <select id="calFiltroUsuario" class="form-select form-select-sm">
                    <option value="">Todos los profesionales</option>
                    <?php foreach ($professionals as $opt): ?>
                        <option value="<?= (int) ($opt['id'] ?? 0) ?>" data-rol="<?= htmlspecialchars((string) ($opt['role'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars((string) ($opt['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            <?php if (!empty($opt['role'])): ?>
                                — <?= htmlspecialchars((string) $opt['role'], ENT_QUOTES, 'UTF-8') ?>
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select id="calFiltroMunicipio" class="form-select form-select-sm">
                    <option value="">Todos los municipios</option>
                </select>
            <?php endif; ?>
            <button type="button" class="btn btn-sm btn-outline-success" id="calExportar" title="Exportar cronograma">
                <i class="bi bi-download me-1"></i> Exportar
            </button>
        </div>
    </div>

    <div class="calendario-wrap">
        <div class="calendario-header">
            <div class="calendario-dia-cab">Lun</div>
            <div class="calendario-dia-cab">Mar</div>
            <div class="calendario-dia-cab">Mié</div>
            <div class="calendario-dia-cab">Jue</div>
            <div class="calendario-dia-cab">Vie</div>
            <div class="calendario-dia-cab">Sáb</div>
            <div class="calendario-dia-cab">Dom</div>
        </div>
        <div class="calendario-grid" id="calGrid"></div>
    </div>
</section>

<div class="modal fade" id="modalDia" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-calendar-event me-2"></i><span id="modalDiaFechaTexto">—</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div id="modalDiaLista"></div>
                <div id="modalDiaFormWrap" class="mt-3 border-top pt-3 d-none">
                    <h6 class="small text-muted mb-2">Nueva actividad</h6>
                    <form id="formActividad">
                        <input type="hidden" name="id" id="actId">
                        <input type="hidden" name="fecha" id="actFecha">
                        <div class="row g-2 mb-2">
                            <div class="col-md-6">
                                <label class="form-label small" for="actSubregion">Subregión <span class="text-danger">*</span></label>
                                <select
                                    id="actSubregion"
                                    name="subregion"
                                    class="form-select form-select-sm"
                                    required
                                    data-subregion-select
                                    data-allowed-territories="<?= $allowedTerritoriesJson ?>"
                                >
                                    <option value="">Seleccione la subregión</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small" for="actMunicipio">Municipio <span class="text-danger">*</span></label>
                                <select
                                    id="actMunicipio"
                                    name="municipality"
                                    class="form-select form-select-sm"
                                    required
                                    data-municipality-select
                                    disabled
                                >
                                    <option value="">Seleccione el municipio</option>
                                </select>
                            </div>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-md-6">
                                <label class="form-label small" for="actHoraInicio">Hora inicio <span class="text-danger">*</span></label>
                                <select name="hora_inicio" id="actHoraInicio" class="form-select form-select-sm" required>
                                    <option value="">Seleccione</option>
                                    <?php foreach ($hourOptions as $hour): ?>
                                        <option value="<?= htmlspecialchars($hour, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($hour, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small" for="actHoraFin">Hora fin <span class="text-danger">*</span></label>
                                <select name="hora_fin" id="actHoraFin" class="form-select form-select-sm" required disabled>
                                    <option value="">Seleccione</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">Tipo de actividad <span class="text-danger">*</span></label>
                            <div class="d-flex flex-wrap gap-3">
                                <?php foreach ($activityTypes as $value => $label): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="activity_type" id="actTipo<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?>" required>
                                        <label class="form-check-label small" for="actTipo<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small" for="actTema">Tema <span class="text-danger">*</span></label>
                            <select name="tema" id="actTema" class="form-select form-select-sm" required>
                                <option value="">Seleccione el tema</option>
                                <?php foreach ($topicOptions as $topic): ?>
                                    <option value="<?= htmlspecialchars((string) $topic['value'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars((string) $topic['label'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                                <option value="Otro">Otro</option>
                            </select>
                        </div>
                        <div class="mb-2 d-none" id="wrapTemaOtro">
                            <label class="form-label small" for="actTemaOtro">Otro tema <span class="text-danger">*</span></label>
                            <input type="text" name="tema_otro" id="actTemaOtro" class="form-control form-control-sm" placeholder="Describe el tema">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small" for="actPoblacion">Población atendida <span class="text-danger">*</span></label>
                            <textarea name="poblacion_atendida" id="actPoblacion" class="form-control form-control-sm" rows="2" required placeholder="¿A quién se atiende?"></textarea>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="bi bi-check2-circle me-1"></i> Guardar
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnCerrarForm">Cancelar</button>
                        </div>
                    </form>
                </div>
                <div id="modalDiaBtnNueva" class="mt-3 d-none">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="btnNuevaEnDia">
                        <i class="bi bi-plus-lg me-1"></i> Añadir actividad
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalDetalle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-info-circle me-2"></i><span id="detalleTitulo">—</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detalleCuerpo"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-danger btn-sm" id="btnEliminarActividad">Eliminar</button>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalExportar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-download me-2"></i> Exportar cronograma</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="exportarTipo" id="exportarMesActual" value="mes" checked>
                        <label class="form-check-label" for="exportarMesActual">Exportar mes actual</label>
                    </div>
                    <p class="small text-muted ms-4 mb-0">Descarga todo el mes que estás viendo.</p>
                </div>
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="exportarTipo" id="exportarRango" value="rango">
                        <label class="form-check-label" for="exportarRango">Rango de fechas</label>
                    </div>
                    <div id="exportarRangoCampos" class="ms-4 mt-2 d-none">
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label small mb-0">Desde</label>
                                <input type="date" class="form-control form-control-sm" id="exportarDesde">
                            </div>
                            <div class="col-6">
                                <label class="form-label small mb-0">Hasta</label>
                                <input type="date" class="form-control form-control-sm" id="exportarHasta">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <a href="#" id="btnDescargarCsv" class="btn btn-outline-success btn-sm">
                    <i class="bi bi-file-earmark-excel me-1"></i> Excel
                </a>
                <a href="#" id="btnDescargarPdf" class="btn btn-danger btn-sm">
                    <i class="bi bi-file-earmark-pdf me-1"></i> PDF
                </a>
            </div>
        </div>
    </div>
</div>

<script>
window.CRONOGRAMA_CONFIG = {
    canViewAll: <?= $canViewAll ? 'true' : 'false' ?>,
    isAdmin: <?= $isAdmin ? 'true' : 'false' ?>,
    currentUserId: <?= (int) $currentUserId ?>,
    professionals: <?= $professionalsJson !== '' ? $professionalsJson : '[]' ?>,
    hourOptions: <?= json_encode($hourOptions, JSON_UNESCAPED_UNICODE) ?>
};
</script>
<script src="<?= asset_url('/assets/js/cronograma.js') ?>"></script>
