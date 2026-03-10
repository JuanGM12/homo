<?php
/** @var array $actividad */
/** @var array $asistentes */
/** @var string $registrationUrl */
$tipos = $actividad['actividad_tipos'] ?? [];
$tiposList = is_array($tipos) ? $tipos : [];
$code = (string) ($actividad['code'] ?? '');
$qrImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . rawurlencode($registrationUrl);
?>
<section class="mt-5 mb-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
        <div>
            <a href="/asistencia" class="btn btn-outline-secondary btn-sm mb-2">
                <i class="bi bi-arrow-left me-1"></i>
                Volver
            </a>
            <h1 class="section-title mb-1">Actividad: <?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?></h1>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-primary text-white rounded-top-4 py-3">
                    <h2 class="h6 mb-0">Información de la Actividad</h2>
                </div>
                <div class="card-body">
                    <p class="mb-2"><span class="text-primary fw-bold">Código QR:</span> <span class="text-primary"><?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?></span></p>
                    <p class="mb-2"><strong>Fecha:</strong> <?= htmlspecialchars((string) ($actividad['activity_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="mb-2"><strong>Subregión:</strong> <span class="badge bg-secondary"><?= htmlspecialchars((string) ($actividad['subregion'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span></p>
                    <p class="mb-2"><strong>Municipio:</strong> <?= htmlspecialchars((string) ($actividad['municipality'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="mb-2"><strong>Lugar:</strong> <?= htmlspecialchars((string) ($actividad['lugar'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="mb-2"><strong>Asesor:</strong> <?= htmlspecialchars((string) ($actividad['advisor_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                    <div class="mb-2">
                        <strong>Tipo de Listado:</strong>
                        <?php if ($tiposList !== []): ?>
                            <div class="mt-1">
                                <?php foreach ($tiposList as $tipo): ?>
                                    <div class="badge bg-light text-dark border small d-block text-start mb-1 text-wrap" style="white-space: normal;">
                                        <?= htmlspecialchars((string) $tipo, ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <span class="text-muted small d-block mt-1">Sin tipos de listado registrados.</span>
                        <?php endif; ?>
                    </div>
                    <p class="mb-3"><strong>Estado:</strong> <span class="badge rounded-pill <?= ($actividad['status'] ?? '') === 'Activo' ? 'text-bg-success' : 'text-bg-warning' ?>"><?= htmlspecialchars((string) ($actividad['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span></p>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <label class="mb-0 small">Cambiar estado:</label>
                        <form method="post" action="/asistencia/cambiar-estado" class="d-inline-flex gap-2 align-items-center">
                            <input type="hidden" name="id" value="<?= (int) ($actividad['id'] ?? 0) ?>">
                            <select name="status" class="form-select form-select-sm" style="width: auto;">
                                <option value="Pendiente" <?= ($actividad['status'] ?? '') === 'Pendiente' ? 'selected' : '' ?>>Pendiente</option>
                                <option value="Activo" <?= ($actividad['status'] ?? '') === 'Activo' ? 'selected' : '' ?>>Activo</option>
                            </select>
                            <button type="submit" class="btn btn-sm btn-outline-secondary">Cambiar</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 mt-4">
                <div class="card-body">
                    <h3 class="h6 fw-semibold mb-3">Código QR para Registro</h3>
                    <div class="text-center mb-3">
                        <img src="<?= htmlspecialchars($qrImageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="QR para registro" width="200" height="200" class="img-fluid">
                    </div>
                    <p class="small text-muted mb-2">Escanear para registrar asistencia</p>
                    <div class="input-group input-group-sm mb-2">
                        <input type="text" class="form-control font-monospace small" value="<?= htmlspecialchars($registrationUrl, ENT_QUOTES, 'UTF-8') ?>" id="url-registro" readonly>
                    </div>
                    <a href="<?= htmlspecialchars($registrationUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-box-arrow-up-right me-1"></i>
                        Abrir enlace
                    </a>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 mt-4">
                <div class="card-body">
                    <a href="/asistencia/exportar-csv?id=<?= (int) ($actividad['id'] ?? 0) ?>" class="btn btn-link btn-sm text-success text-decoration-none p-0 d-block">
                        <i class="bi bi-download me-1"></i>Exportar CSV
                    </a>
                    <a href="/asistencia/exportar-pdf?id=<?= (int) ($actividad['id'] ?? 0) ?>" target="_blank" class="btn btn-link btn-sm text-primary text-decoration-none p-0 d-block mt-2">
                        <i class="bi bi-file-pdf me-1"></i>Exportar PDF
                    </a>
                    <form method="post" action="/asistencia/eliminar" class="mt-3" onsubmit="return confirm('¿Está seguro de eliminar esta actividad y todos sus asistentes?');">
                        <input type="hidden" name="id" value="<?= (int) ($actividad['id'] ?? 0) ?>">
                        <button type="submit" class="btn btn-link btn-sm text-danger text-decoration-none p-0">
                            <i class="bi bi-trash me-1"></i>Eliminar Actividad
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-light rounded-top-4 py-3 d-flex align-items-center justify-content-between">
                    <h2 class="h6 mb-0"><i class="bi bi-people me-2"></i>Asistentes Registrados</h2>
                    <span class="badge bg-primary rounded-pill"><?= count($asistentes) ?></span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($asistentes)): ?>
                        <p class="text-muted p-4 mb-0">Aún no hay asistentes registrados. Comparta el enlace o el código QR para el registro.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0 table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Documento</th>
                                        <th>Nombres y Apellidos</th>
                                        <th>Entidad</th>
                                        <th>Cargo</th>
                                        <th>Teléfono</th>
                                        <th>Correo</th>
                                        <th>Zona</th>
                                        <th>Sexo</th>
                                        <th>Edad</th>
                                        <th>Etnia</th>
                                        <th>Grupo poblacional</th>
                                        <th>Registro</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($asistentes as $i => $a):
                                        $grupo = $a['grupo_poblacional'] ?? [];
                                        $grupoStr = is_array($grupo) ? implode(', ', $grupo) : (string) $grupo;
                                        $etnia = (string) ($a['etnia'] ?? '');
                                        if (!empty($a['etnia_otro'])) {
                                            $etnia .= ' (' . htmlspecialchars((string) $a['etnia_otro'], ENT_QUOTES, 'UTF-8') . ')';
                                        }
                                    ?>
                                        <tr>
                                            <td><?= $i + 1 ?></td>
                                            <td><span class="text-primary"><?= htmlspecialchars((string) ($a['document_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span></td>
                                            <td><?= htmlspecialchars((string) ($a['full_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars((string) ($a['entity'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars((string) ($a['cargo'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars((string) ($a['phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars((string) ($a['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars((string) ($a['zone'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars((string) ($a['sex'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= $a['age'] !== null && $a['age'] !== '' ? (int) $a['age'] : '' ?></td>
                                            <td><?= htmlspecialchars($etnia, ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="small"><?= htmlspecialchars($grupoStr, ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars((string) ($a['registered_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>
