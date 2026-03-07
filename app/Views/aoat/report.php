<?php
/** @var string $defaultFrom */
/** @var string $defaultTo */
?>

<section class="mb-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
        <div>
            <h1 class="section-title mb-1">Reporte semanal de AoAT</h1>
            <p class="section-subtitle mb-0">
                Genera y envía al correo de la coordinación el resumen semanal de AoAT por perfil profesional.
            </p>
        </div>
        <a href="/aoat" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>
            Volver a mis AoAT
        </a>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-7">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4 p-md-5">
                    <h2 class="h5 fw-semibold mb-3">Selecciona el rango de fechas</h2>
                    <p class="text-muted small">
                        El sistema usará la <strong>fecha de la actividad</strong> registrada en cada AoAT para armar el reporte
                        de Psicología, Profesional Social, Médicos y Abogados, y lo enviará al correo configurado para la
                        coordinadora.
                    </p>

                    <form method="post" action="/aoat/reportes/enviar" class="mt-3">
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Desde (inclusive) <span class="text-danger">*</span></label>
                                <input
                                    type="date"
                                    name="from_date"
                                    class="form-control"
                                    value="<?= htmlspecialchars($defaultFrom ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    required
                                >
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Hasta (inclusive) <span class="text-danger">*</span></label>
                                <input
                                    type="date"
                                    name="to_date"
                                    class="form-control"
                                    value="<?= htmlspecialchars($defaultTo ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    required
                                >
                            </div>
                        </div>

                        <div class="alert alert-warning border-0 small">
                            El reporte se enviará automáticamente al correo definido en la configuración
                            (<code>AOAT_COORDINATOR_EMAIL</code>). Asegúrate de que los datos de correo estén correctamente
                            configurados en el servidor.
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-envelope-check me-1"></i>
                                Generar y enviar reporte
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

