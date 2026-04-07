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
                        <button type="button"
                            class="btn btn-outline-secondary btn-sm w-100"
                            data-bs-toggle="modal"
                            data-bs-target="#evalQrModal"
                            data-eval-qr-open
                            data-eval-name="<?= htmlspecialchars($test['name'], ENT_QUOTES, 'UTF-8') ?>"
                            data-eval-pre-path="/evaluaciones/<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>/pre"
                            data-eval-post-path="/evaluaciones/<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>/post">
                            <i class="bi bi-qr-code-scan me-1" aria-hidden="true"></i>
                            Ver códigos QR
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="modal fade" id="evalQrModal" tabindex="-1" aria-labelledby="evalQrModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-semibold" id="evalQrModalTitle" data-eval-qr-modal-title>Códigos QR</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body pt-2">
                <p class="text-muted small mb-3">
                    Escanea con la cámara del celular. Cada código lleva al formulario correspondiente (PRE o POST).
                </p>
                <div class="row g-4">
                    <div class="col-md-6 text-center">
                        <p class="small fw-semibold text-primary mb-2">PRE - TEST</p>
                        <div class="eval-qr-box mx-auto mb-2" data-eval-qr-pre></div>
                        <label class="visually-hidden" for="evalQrUrlPre">URL PRE</label>
                        <input type="text" class="form-control form-control-sm text-center mb-2" id="evalQrUrlPre" readonly data-eval-qr-url-pre value="">
                        <div class="d-flex flex-wrap justify-content-center gap-2">
                            <button type="button" class="btn btn-outline-primary btn-sm" data-eval-qr-copy="pre">
                                Copiar enlace
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm d-none" data-eval-qr-share="pre">
                                Compartir
                            </button>
                        </div>
                    </div>
                    <div class="col-md-6 text-center">
                        <p class="small fw-semibold text-primary mb-2">POST - TEST</p>
                        <div class="eval-qr-box mx-auto mb-2" data-eval-qr-post></div>
                        <label class="visually-hidden" for="evalQrUrlPost">URL POST</label>
                        <input type="text" class="form-control form-control-sm text-center mb-2" id="evalQrUrlPost" readonly data-eval-qr-url-post value="">
                        <div class="d-flex flex-wrap justify-content-center gap-2">
                            <button type="button" class="btn btn-outline-primary btn-sm" data-eval-qr-copy="post">
                                Copiar enlace
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm d-none" data-eval-qr-share="post">
                                Compartir
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
