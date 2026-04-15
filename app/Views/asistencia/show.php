<?php
/** @var array $actividad */
/** @var array $asistentes */
/** @var string $registrationUrl */
/** @var bool $canDeleteActividad */

$canDeleteActividad = (bool) ($canDeleteActividad ?? false);
$tipos = $actividad['actividad_tipos'] ?? [];
$tiposList = is_array($tipos) ? $tipos : [];
$tipoActividad = (string) ($actividad['tipo'] ?? 'aoat');
$tipoActividadLabel = $tipoActividad === 'actividad' ? 'Actividades' : 'AoAT';
$backUrl = '/asistencia?tab=' . rawurlencode($tipoActividad === 'actividad' ? 'actividad' : 'aoat');
$estadoListado = (string) ($actividad['status'] ?? '');
$estadoPillClass = match ($estadoListado) {
    'Activo' => 'is-active',
    'Cerrado' => 'is-closed',
    default => 'is-pending',
};
$code = (string) ($actividad['code'] ?? '');
$qrImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . rawurlencode($registrationUrl);

$subColors = ['#1e3a5f', '#2d4a3e', '#3d2a5c', '#1a3f5c', '#4a2c1e', '#2a3d1a', '#1e4a4a', '#4a3a1e'];
$subregion = (string) ($actividad['subregion'] ?? '');
$subStyle = '';
if ($subregion !== '') {
    $idx = abs(crc32(strtolower(trim($subregion)))) % count($subColors);
    $subStyle = 'background:' . $subColors[$idx] . ';color:#fff;';
}
?>
<section class="mt-5 mb-5">
    <div class="mb-4">
        <a href="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') ?>" class="asi-show-back-link">
            <i class="bi bi-arrow-left me-1"></i>
            Volver al listado
        </a>
        <h1 class="section-title mt-2 mb-0">
            Actividad: <span class="text-primary"><?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?></span>
        </h1>
    </div>

    <div class="row g-4 align-items-start">

        <!-- Columna izquierda: Info + QR + acciones -->
        <div class="col-lg-4">

            <!-- Tarjeta info -->
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="asi-show-card-header">
                    <i class="bi bi-calendar-event me-2"></i>Información de la Actividad
                </div>
                <div class="card-body asi-show-info-body">
                    <div class="asi-show-field">
                        <span class="asi-show-label">Código QR</span>
                        <span class="asi-show-code"><?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="asi-show-field">
                        <span class="asi-show-label">Fecha</span>
                        <span class="asi-show-value"><?= htmlspecialchars((string) ($actividad['activity_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="asi-show-field">
                        <span class="asi-show-label">Subregión</span>
                        <?php if ($subregion !== ''): ?>
                            <span class="asi-subregion-pill mt-1" style="<?= $subStyle ?>"><?= htmlspecialchars($subregion, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php else: ?>
                            <span class="asi-show-value text-muted">—</span>
                        <?php endif; ?>
                    </div>
                    <div class="asi-show-field">
                        <span class="asi-show-label">Municipio</span>
                        <span class="asi-show-value"><?= htmlspecialchars((string) ($actividad['municipality'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="asi-show-field">
                        <span class="asi-show-label">Lugar</span>
                        <span class="asi-show-value"><?= htmlspecialchars((string) ($actividad['lugar'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="asi-show-field">
                        <span class="asi-show-label">Tipo</span>
                        <span class="asi-show-value"><?= htmlspecialchars($tipoActividadLabel, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="asi-show-field">
                        <span class="asi-show-label">Asesor</span>
                        <span class="asi-show-value"><?= htmlspecialchars((string) ($actividad['advisor_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="asi-show-field">
                        <span class="asi-show-label"><?= $tipoActividad === 'actividad' ? 'Actividad' : 'Listado AoAT' ?></span>
                        <?php if ($tiposList !== []): ?>
                            <div class="d-flex flex-column gap-1 mt-1">
                                <?php foreach ($tiposList as $tipo): ?>
                                    <span class="asi-show-tipo"><?= htmlspecialchars((string) $tipo, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <span class="asi-show-value text-muted">—</span>
                        <?php endif; ?>
                    </div>
                    <div class="asi-show-field asi-show-field--last">
                        <span class="asi-show-label">Estado</span>
                        <span class="asi-status-pill <?= htmlspecialchars($estadoPillClass, ENT_QUOTES, 'UTF-8') ?> mt-1">
                            <?= htmlspecialchars($estadoListado, ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-top px-4 py-3">
                    <?php if ($estadoListado === 'Cerrado'): ?>
                        <p class="small text-muted mb-2 mb-md-0">El registro público por QR o enlace está deshabilitado mientras el listado esté cerrado.</p>
                    <?php endif; ?>
                    <form method="post" action="/asistencia/cambiar-estado" class="d-flex align-items-center gap-2 flex-wrap">
                        <input type="hidden" name="id" value="<?= (int) ($actividad['id'] ?? 0) ?>">
                        <select name="status" class="form-select form-select-sm flex-grow-1" style="min-width: 10rem;">
                            <option value="Pendiente" <?= $estadoListado === 'Pendiente' ? 'selected' : '' ?>>Pendiente</option>
                            <option value="Activo" <?= $estadoListado === 'Activo' ? 'selected' : '' ?>>Activo</option>
                            <option value="Cerrado" <?= $estadoListado === 'Cerrado' ? 'selected' : '' ?>>Cerrado</option>
                        </select>
                        <button type="submit" class="btn btn-sm btn-outline-secondary">Cambiar</button>
                    </form>
                </div>
            </div>

            <!-- Tarjeta QR -->
            <div class="card border-0 shadow-sm rounded-4 mt-4">
                <div class="card-body p-4">
                    <h3 class="asi-show-section-title mb-3">Código QR para Registro</h3>
                    <div class="text-center mb-3">
                        <img src="<?= htmlspecialchars($qrImageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="QR para registro" width="180" height="180" class="img-fluid">
                    </div>
                    <p class="small text-center text-muted mb-2">Escanear para registrar asistencia</p>
                    <input type="text" class="form-control form-control-sm font-monospace mb-3" value="<?= htmlspecialchars($registrationUrl, ENT_QUOTES, 'UTF-8') ?>" readonly>
                    <a href="<?= htmlspecialchars($registrationUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm w-100">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Abrir enlace
                    </a>
                </div>
            </div>

            <!-- Tarjeta acciones -->
            <div class="card border-0 shadow-sm rounded-4 mt-4">
                <div class="card-body p-4 d-flex flex-column gap-3">
                    <a href="/asistencia/exportar-csv?id=<?= (int) ($actividad['id'] ?? 0) ?>" class="asi-show-action-link text-success">
                        <i class="bi bi-download me-2"></i>Exportar CSV
                    </a>
                    <a href="/asistencia/exportar-pdf?id=<?= (int) ($actividad['id'] ?? 0) ?>" target="_blank" class="asi-show-action-link text-primary">
                        <i class="bi bi-file-pdf me-2"></i>Exportar PDF
                    </a>
                    <?php if ($canDeleteActividad): ?>
                        <form
                            method="post"
                            action="/asistencia/eliminar"
                            data-sw-confirm="1"
                            data-sw-title="Eliminar actividad"
                            data-sw-text="¿Eliminar esta actividad y todos los asistentes registrados? Esta acción no se puede deshacer."
                        >
                            <input type="hidden" name="id" value="<?= (int) ($actividad['id'] ?? 0) ?>">
                            <button type="submit" class="asi-show-action-link text-danger border-0 bg-transparent p-0 w-100 text-start">
                                <i class="bi bi-trash me-2"></i>Eliminar Actividad
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Columna derecha: Asistentes -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-0 rounded-top-4 px-4 py-3 d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-people text-secondary fs-5"></i>
                        <h2 class="h6 mb-0 fw-semibold">Asistentes Registrados</h2>
                    </div>
                    <span class="asi-count-badge"><?= count($asistentes) ?></span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($asistentes)): ?>
                        <div class="asi-show-empty">
                            <div class="asi-empty-icon"><i class="bi bi-person-x"></i></div>
                            <p class="asi-empty-title">Aún no hay asistentes registrados.</p>
                            <p class="asi-empty-copy">Comparta el código QR para comenzar.</p>
                        </div>
                    <?php else: ?>
                        <table class="table align-middle mb-0 asi-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Documento</th>
                                    <th>Nombres y Apellidos</th>
                                    <th>Entidad</th>
                                    <th>Teléfono</th>
                                    <th>Zona</th>
                                    <th>Registro</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($asistentes as $i => $a):
                                    $grupo = $a['grupo_poblacional'] ?? [];
                                    $grupoStr = is_array($grupo) ? implode(', ', $grupo) : (string) $grupo;
                                    $etnia = (string) ($a['etnia'] ?? '');
                                    if (!empty($a['etnia_otro'])) {
                                        $etnia .= ' (' . (string) $a['etnia_otro'] . ')';
                                    }
                                    $regRaw = (string) ($a['registered_at'] ?? '');
                                    $regDisplay = $regRaw;
                                    try {
                                        $dt = new DateTimeImmutable($regRaw);
                                        $regDisplay = $dt->format('H:i');
                                    } catch (Exception) {}
                                    $detailJson = htmlspecialchars(json_encode([
                                        'doc'    => $a['document_number'] ?? '',
                                        'name'   => $a['full_name'] ?? '',
                                        'entity' => $a['entity'] ?? '',
                                        'cargo'  => $a['cargo'] ?? '',
                                        'phone'  => $a['phone'] ?? '',
                                        'email'  => $a['email'] ?? '',
                                        'zone'   => $a['zone'] ?? '',
                                        'sex'    => $a['sex'] ?? '',
                                        'age'    => ($a['age'] !== null && $a['age'] !== '') ? $a['age'] : '',
                                        'etnia'  => $etnia,
                                        'grupo'  => $grupoStr,
                                        'reg'    => $regRaw,
                                    ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                                ?>
                                    <tr>
                                        <td class="text-muted"><?= $i + 1 ?></td>
                                        <td><span class="asi-code-link"><?= htmlspecialchars((string) ($a['document_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span></td>
                                        <td class="fw-medium"><?= htmlspecialchars((string) ($a['full_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="text-muted small"><?= htmlspecialchars((string) ($a['entity'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="small"><?= htmlspecialchars((string) ($a['phone'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="small"><?= htmlspecialchars((string) ($a['zone'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="asi-date"><?= htmlspecialchars($regDisplay, ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <button
                                                type="button"
                                                class="asi-show-detail-btn"
                                                data-asi-asistente="<?= $detailJson ?>"
                                            >
                                                <i class="bi bi-eye me-1"></i>Ver detalle
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Modal detalle asistente -->
<div class="modal fade" id="asi-detail-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header border-0 pb-2 px-4 pt-4">
                <h5 class="modal-title fw-bold">Detalle del Asistente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 pb-4" id="asi-detail-body"></div>
        </div>
    </div>
</div>

<script>
(function () {
    var fields = [
        { key: 'doc',    label: 'Documento' },
        { key: 'name',   label: 'Nombres y Apellidos' },
        { key: 'entity', label: 'Entidad' },
        { key: 'cargo',  label: 'Cargo' },
        { key: 'phone',  label: 'Teléfono' },
        { key: 'email',  label: 'Correo' },
        { key: 'zone',   label: 'Zona' },
        { key: 'sex',    label: 'Sexo' },
        { key: 'age',    label: 'Edad' },
        { key: 'etnia',  label: 'Etnia' },
        { key: 'grupo',  label: 'Grupo poblacional' },
        { key: 'reg',    label: 'Fecha de registro' },
    ];
    document.querySelectorAll('[data-asi-asistente]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var data = JSON.parse(btn.getAttribute('data-asi-asistente'));
            var html = '<div class="asi-detail-grid">';
            fields.forEach(function (f) {
                var val = data[f.key];
                if (val === '' || val === null || val === undefined) val = '—';
                html += '<div class="asi-detail-field">'
                    + '<span class="asi-show-label">' + f.label + '</span>'
                    + '<span class="asi-show-value fw-medium">' + String(val).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;') + '</span>'
                    + '</div>';
            });
            html += '</div>';
            document.getElementById('asi-detail-body').innerHTML = html;
            new bootstrap.Modal(document.getElementById('asi-detail-modal')).show();
        });
    });
}());
</script>
