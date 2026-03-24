<?php
/** @var array $response */
/** @var array<int, array<string, mixed>> $answerRows */
/** @var array $tests */

use App\Services\EvaluacionesQuestionCatalog;

$testKey = (string) ($response['test_key'] ?? '');
$phase = (string) ($response['phase'] ?? '');
$testInfo = $tests[$testKey] ?? ['name' => $testKey, 'color' => 'primary'];
$phaseLabel = $phase === 'pre' ? 'PRE - TEST' : 'POST - TEST';
$totalQ = count($answerRows);
$correctN = 0;
foreach ($answerRows as $r) {
    if (!empty($r['is_correct'])) {
        $correctN++;
    }
}
$wrongN = $totalQ - $correctN;
$scorePct = (float) ($response['score_percent'] ?? 0);
$adiccionesContext = $testKey === 'adicciones' ? EvaluacionesQuestionCatalog::getAdiccionesContext() : null;
?>

<section class="mb-5">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="/evaluaciones">Evaluaciones</a></li>
            <li class="breadcrumb-item active" aria-current="page">Detalle de respuestas</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <h1 class="section-title mb-1">Detalle del <?= htmlspecialchars($phaseLabel, ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="section-subtitle mb-0">
                <?= htmlspecialchars((string) ($testInfo['name'] ?? $testKey), ENT_QUOTES, 'UTF-8') ?>
            </p>
        </div>
        <a href="/evaluaciones" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Volver al listado
        </a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6 col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h6 text-muted mb-2">Persona</h2>
                    <p class="mb-1 fw-semibold">
                        <?= htmlspecialchars(trim((string) ($response['first_name'] ?? '') . ' ' . (string) ($response['last_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
                    </p>
                    <p class="mb-0 small">
                        <span class="text-muted">Documento:</span>
                        <span class="text-primary"><?= htmlspecialchars((string) ($response['document_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h6 text-muted mb-2">Territorio</h2>
                    <p class="mb-1 small"><?= htmlspecialchars((string) ($response['subregion'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="mb-0 small"><?= htmlspecialchars((string) ($response['municipality'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-12 col-lg-4">
            <div class="card border-0 shadow-sm h-100 border-start border-4 border-primary">
                <div class="card-body">
                    <h2 class="h6 text-muted mb-2">Resultado</h2>
                    <p class="mb-1">
                        <span class="display-6 fw-bold text-primary"><?= number_format($scorePct, 0) ?>%</span>
                        <span class="text-muted small ms-1">puntaje</span>
                    </p>
                    <p class="mb-0 small">
                        <span class="badge bg-success"><?= (int) $correctN ?> correctas</span>
                        <span class="badge bg-danger"><?= (int) $wrongN ?> incorrectas</span>
                        <span class="text-muted">de <?= (int) $totalQ ?> preguntas</span>
                    </p>
                    <p class="mb-0 mt-2 small text-muted">
                        Fecha: <?= htmlspecialchars((string) ($response['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <?php if ($adiccionesContext !== null): ?>
        <div class="alert alert-light border shadow-sm mb-3" role="note">
            <h3 class="h6 fw-semibold mb-2"><i class="bi bi-journal-text me-1"></i> Caso de contexto</h3>
            <p class="small mb-0 text-body-secondary"><?= htmlspecialchars($adiccionesContext, ENT_QUOTES, 'UTF-8') ?></p>
        </div>
    <?php endif; ?>

    <div class="alert alert-info border-0 shadow-sm mb-3 d-flex gap-2 align-items-start">
        <i class="bi bi-info-circle flex-shrink-0 mt-1"></i>
        <div class="small">
            <strong>Cómo ver el enunciado:</strong> pulsa <strong>«Ver enunciado»</strong> en cada fila para desplegar el texto completo de la pregunta y el detalle de las opciones (incluida la que marcó la persona y la correcta).
            También puedes usar el número de pregunta como acceso rápido.
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 py-3">
            <h2 class="h6 mb-0 fw-semibold">
                <i class="bi bi-list-check me-1"></i>
                Respuesta por pregunta (seguimiento de aciertos y errores)
            </h2>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th style="width:5rem">#</th>
                    <th style="width:11rem">Enunciado</th>
                    <th>Respuesta del evaluado</th>
                    <th>Opción correcta</th>
                    <th class="text-center">Estado</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($answerRows === []): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">No hay respuestas guardadas para este registro.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($answerRows as $r): ?>
                        <?php
                        $ok = !empty($r['is_correct']);
                        $qNum = (int) ($r['question_number'] ?? 0);
                        $meta = EvaluacionesQuestionCatalog::getQuestion($testKey, $qNum);
                        $collapseId = 'eval-enunciado-' . $qNum;
                        $selLetter = strtoupper((string) ($r['selected'] ?? ''));
                        $corrLetter = $r['correct'] !== null ? strtoupper((string) $r['correct']) : '';
                        $selText = $meta && $selLetter !== '' && isset($meta['options'][$selLetter])
                            ? $meta['options'][$selLetter]
                            : '';
                        $corrText = $meta && $corrLetter !== '' && isset($meta['options'][$corrLetter])
                            ? $meta['options'][$corrLetter]
                            : '';
                        ?>
                        <tr class="<?= $ok ? '' : 'table-danger' ?>">
                            <td class="fw-semibold">
                                <button
                                    type="button"
                                    class="btn btn-link p-0 fw-semibold text-decoration-none"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#<?= htmlspecialchars($collapseId, ENT_QUOTES, 'UTF-8') ?>"
                                    aria-expanded="false"
                                    aria-controls="<?= htmlspecialchars($collapseId, ENT_QUOTES, 'UTF-8') ?>"
                                    title="Mostrar u ocultar enunciado"
                                >
                                    <?= $qNum ?>
                                </button>
                            </td>
                            <td>
                                <?php if ($meta !== null): ?>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-primary"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#<?= htmlspecialchars($collapseId, ENT_QUOTES, 'UTF-8') ?>"
                                        aria-expanded="false"
                                    >
                                        <i class="bi bi-chat-text me-1"></i> Ver enunciado
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border"><?= htmlspecialchars($selLetter, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php if ($selText !== ''): ?>
                                    <div class="small text-muted mt-1"><?= htmlspecialchars($selText, ENT_QUOTES, 'UTF-8') ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($r['correct'] !== null): ?>
                                    <span class="badge bg-success-subtle text-success"><?= htmlspecialchars($corrLetter, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php if ($corrText !== ''): ?>
                                        <div class="small text-muted mt-1"><?= htmlspecialchars($corrText, ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($ok): ?>
                                    <span class="badge bg-success"><i class="bi bi-check-lg"></i> Correcta</span>
                                <?php else: ?>
                                    <span class="badge bg-danger"><i class="bi bi-x-lg"></i> Incorrecta</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr class="<?= $ok ? 'bg-light bg-opacity-25' : '' ?>">
                            <td colspan="5" class="small border-top-0 p-0">
                                <div class="collapse px-3 pb-3" id="<?= htmlspecialchars($collapseId, ENT_QUOTES, 'UTF-8') ?>">
                                    <div class="p-3 rounded-3 border shadow-sm <?= $ok ? 'bg-white' : 'bg-white border-danger' ?>">
                                        <?php if ($meta !== null): ?>
                                            <p class="fw-semibold mb-2 text-body">Pregunta <?= $qNum ?></p>
                                            <p class="mb-3"><?= nl2br(htmlspecialchars($meta['text'], ENT_QUOTES, 'UTF-8')) ?></p>
                                            <p class="text-muted mb-1 small fw-semibold">Opciones del cuestionario</p>
                                            <ul class="list-unstyled mb-0 small">
                                                <?php foreach ($meta['options'] as $letter => $label): ?>
                                                    <li class="mb-1">
                                                        <span class="badge bg-secondary-subtle text-dark me-1"><?= htmlspecialchars((string) $letter, ENT_QUOTES, 'UTF-8') ?></span>
                                                        <?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php else: ?>
                                            <p class="text-muted mb-0 fst-italic">El texto de esta pregunta no está disponible en el catálogo. Contacta a soporte si necesitas el enunciado.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-light border-0 small text-muted">
            Las filas en <span class="text-danger">rojo</span> son las preguntas falladas. Las letras muestran la opción elegida y la correcta; debajo aparece el texto de cada opción cuando está en el catálogo.
        </div>
    </div>
</section>
