<?php
/** @var array $response */
/** @var array<int, array<string, mixed>> $answerRows */
/** @var array $tests */
/** @var bool $canDeleteResponse */

use App\Services\EvaluacionesQuestionCatalog;

$canDeleteResponse = (bool) ($canDeleteResponse ?? false);

$testKey    = (string) ($response['test_key'] ?? '');
$phase      = (string) ($response['phase'] ?? '');
$testInfo   = $tests[$testKey] ?? ['name' => $testKey, 'color' => 'primary'];
$phaseLabel = $phase === 'pre' ? 'PRE - TEST' : 'POST - TEST';
$totalQ     = count($answerRows);
$correctN   = 0;
foreach ($answerRows as $r) { if (!empty($r['is_correct'])) $correctN++; }
$wrongN     = $totalQ - $correctN;
$scorePct   = (float) ($response['score_percent'] ?? 0);
$adiccionesContext = $testKey === 'adicciones' ? EvaluacionesQuestionCatalog::getAdiccionesContext() : null;
$scoreColor = $scorePct >= 70 ? 'var(--app-primary-deep)' : ($scorePct >= 50 ? '#b36b00' : '#c0392b');
?>

<section class="mb-5">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb small mb-0">
            <li class="breadcrumb-item"><a href="/evaluaciones" class="text-decoration-none">Evaluaciones</a></li>
            <li class="breadcrumb-item active">Detalle de respuestas</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <h1 class="section-title mb-1">Detalle del <?= htmlspecialchars($phaseLabel, ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="section-subtitle mb-0"><?= htmlspecialchars((string) ($testInfo['name'] ?? $testKey), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="d-flex flex-column align-items-end gap-2">
            <a href="/evaluaciones" class="asi-show-back-link">
                <i class="bi bi-arrow-left me-1"></i> Volver al listado
            </a>
            <?php if ($canDeleteResponse): ?>
                <form
                    method="post"
                    action="/evaluaciones/eliminar"
                    class="d-inline"
                    data-sw-confirm="1"
                    data-sw-title="Eliminar respuesta del test"
                    data-sw-text="¿Eliminar de forma permanente esta respuesta y todas las respuestas por pregunta asociadas? Esta acción no se puede deshacer."
                >
                    <input type="hidden" name="id" value="<?= (int) ($response['id'] ?? 0) ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-trash me-1"></i>Eliminar registro
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tarjetas de resumen -->
    <div class="row g-3 mb-4">
        <div class="col-md-6 col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4">
                    <p class="eval-card-label">Persona</p>
                    <p class="eval-card-name mb-1"><?= htmlspecialchars(trim((string)($response['first_name']??'').' '.(string)($response['last_name']??'')), ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="mb-0 small"><span class="text-muted">Documento:</span> <span class="eval-doc-link"><?= htmlspecialchars((string)($response['document_number']??''), ENT_QUOTES, 'UTF-8') ?></span></p>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4">
                    <p class="eval-card-label">Territorio</p>
                    <p class="eval-card-name mb-1"><?= htmlspecialchars((string)($response['subregion']??''), ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="mb-0 small text-muted"><?= htmlspecialchars((string)($response['municipality']??''), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-12 col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 eval-score-card">
                <div class="card-body p-4">
                    <p class="eval-card-label">Resultado</p>
                    <div class="d-flex align-items-baseline gap-2 mb-2">
                        <span class="eval-score-big" style="color:<?= $scoreColor ?>"><?= number_format($scorePct, 0) ?>%</span>
                        <span class="text-muted small">puntaje</span>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                        <span class="eval-answer-chip is-correct"><i class="bi bi-check-lg me-1"></i><?= $correctN ?> correctas</span>
                        <span class="eval-answer-chip is-wrong"><i class="bi bi-x-lg me-1"></i><?= $wrongN ?> incorrectas</span>
                        <span class="text-muted small">de <?= $totalQ ?> preguntas</span>
                    </div>
                    <p class="mb-0 small text-muted">Fecha: <?= htmlspecialchars((string)($response['created_at']??''), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>
        </div>
    </div>

    <?php if ($adiccionesContext !== null): ?>
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <h3 class="eval-card-label mb-2"><i class="bi bi-journal-text me-1"></i>Caso de contexto</h3>
                <p class="small mb-0 text-muted"><?= htmlspecialchars($adiccionesContext, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="eval-detail-header">
            <i class="bi bi-list-check me-2"></i>Respuesta por pregunta
            <span class="eval-detail-header-hint">Haz clic en el número o en «Ver enunciado» para expandir</span>
        </div>

        <table class="table align-middle mb-0 eval-detail-table">
            <thead>
                <tr>
                    <th style="width:4rem">#</th>
                    <th style="width:10rem">Enunciado</th>
                    <th>Respuesta del evaluado</th>
                    <th>Opción correcta</th>
                    <th class="text-center" style="width:9rem">Estado</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($answerRows === []): ?>
                <tr><td colspan="5" class="text-center text-muted py-5">No hay respuestas guardadas para este registro.</td></tr>
            <?php else: ?>
                <?php foreach ($answerRows as $r):
                    $ok         = !empty($r['is_correct']);
                    $qNum       = (int) ($r['question_number'] ?? 0);
                    $meta       = EvaluacionesQuestionCatalog::getQuestion($testKey, $qNum);
                    $collapseId = 'eval-q-' . $qNum;
                    $selLetter  = strtoupper((string)($r['selected']??''));
                    $corrLetter = $r['correct'] !== null ? strtoupper((string)$r['correct']) : '';
                    $selText    = $meta && $selLetter !== '' && isset($meta['options'][$selLetter]) ? $meta['options'][$selLetter] : '';
                    $corrText   = $meta && $corrLetter !== '' && isset($meta['options'][$corrLetter]) ? $meta['options'][$corrLetter] : '';
                ?>
                <tr class="eval-answer-row <?= $ok ? 'is-correct' : 'is-wrong' ?>">
                    <td>
                        <button type="button" class="eval-qnum-btn" data-bs-toggle="collapse"
                            data-bs-target="#<?= htmlspecialchars($collapseId,ENT_QUOTES,'UTF-8') ?>"
                            aria-expanded="false" title="Ver enunciado"><?= $qNum ?></button>
                    </td>
                    <td>
                        <?php if ($meta !== null): ?>
                            <button type="button" class="eval-enunciado-btn" data-bs-toggle="collapse"
                                data-bs-target="#<?= htmlspecialchars($collapseId,ENT_QUOTES,'UTF-8') ?>" aria-expanded="false">
                                <i class="bi bi-chat-text me-1"></i>Ver enunciado
                            </button>
                        <?php else: ?>
                            <span class="text-muted small">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="eval-letter-badge"><?= htmlspecialchars($selLetter,ENT_QUOTES,'UTF-8') ?></span>
                        <?php if ($selText !== ''): ?>
                            <div class="eval-option-text"><?= htmlspecialchars($selText,ENT_QUOTES,'UTF-8') ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($r['correct'] !== null): ?>
                            <span class="eval-letter-badge is-correct"><?= htmlspecialchars($corrLetter,ENT_QUOTES,'UTF-8') ?></span>
                            <?php if ($corrText !== ''): ?>
                                <div class="eval-option-text"><?= htmlspecialchars($corrText,ENT_QUOTES,'UTF-8') ?></div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <?php if ($ok): ?>
                            <span class="eval-status-badge is-correct"><i class="bi bi-check-lg me-1"></i>Correcta</span>
                        <?php else: ?>
                            <span class="eval-status-badge is-wrong"><i class="bi bi-x-lg me-1"></i>Incorrecta</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr class="eval-collapse-row <?= $ok ? '' : 'is-wrong' ?>">
                    <td colspan="5" class="p-0 border-top-0">
                        <div class="collapse" id="<?= htmlspecialchars($collapseId,ENT_QUOTES,'UTF-8') ?>">
                            <div class="eval-enunciado-body <?= $ok ? '' : 'is-wrong' ?>">
                                <?php if ($meta !== null): ?>
                                    <p class="eval-enunciado-title">Pregunta <?= $qNum ?></p>
                                    <p class="mb-3"><?= nl2br(htmlspecialchars($meta['text'],ENT_QUOTES,'UTF-8')) ?></p>
                                    <p class="eval-enunciado-options-label">Opciones</p>
                                    <ul class="list-unstyled mb-0">
                                        <?php foreach ($meta['options'] as $letter => $label): ?>
                                            <li class="eval-option-item <?= $letter===$corrLetter?'is-correct':'' ?> <?= $letter===$selLetter&&!$ok?'is-selected-wrong':'' ?>">
                                                <span class="eval-letter-badge <?= $letter===$corrLetter?'is-correct':'' ?>"><?= htmlspecialchars((string)$letter,ENT_QUOTES,'UTF-8') ?></span>
                                                <?= htmlspecialchars((string)$label,ENT_QUOTES,'UTF-8') ?>
                                                <?php if ($letter===$corrLetter): ?><span class="eval-option-correct-mark">✓ correcta</span><?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p class="text-muted fst-italic mb-0">El texto de esta pregunta no está disponible en el catálogo.</p>
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
</section>
