<?php
/** @var array<string, array{name: string, color: string}> $tests */
?>
<div class="alert alert-info border-0 shadow-sm mb-4">
    <div class="d-flex flex-wrap align-items-center gap-2">
        <i class="bi bi-clipboard2-pulse-fill me-2"></i>
        <div>
            <strong>Regla clave:</strong> cada persona debe diligenciar su
            <strong>PRE - TEST</strong> y su <strong>POST - TEST</strong> con el mismo número de documento.
        </div>
    </div>
</div>

<div class="row g-4">
    <?php foreach ($tests as $key => $test): ?>
        <div class="col-md-6 col-lg-3">
            <div class="menu-card text-decoration-none h-100">
                <div class="menu-card-inner">
                    <div class="menu-card-icon bg-<?= htmlspecialchars($test['color'], ENT_QUOTES, 'UTF-8') ?>-subtle text-<?= htmlspecialchars($test['color'], ENT_QUOTES, 'UTF-8') ?>">
                        <i class="bi bi-qr-code"></i>
                    </div>
                    <h2 class="h6 fw-semibold mb-1">
                        <?= htmlspecialchars($test['name'], ENT_QUOTES, 'UTF-8') ?>
                    </h2>
                    <p class="text-muted mb-3 small">
                        Evalúa el nivel de conocimiento antes y después de la intervención.
                    </p>
                    <div class="d-flex flex-column gap-2">
                        <a href="/evaluaciones/<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>/pre"
                           class="btn btn-outline-primary btn-sm w-100">
                            PRE - TEST
                        </a>
                        <a href="/evaluaciones/<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>/post"
                           class="btn btn-primary btn-sm w-100">
                            POST - TEST
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
