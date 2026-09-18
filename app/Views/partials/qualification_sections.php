<?php

use App\Services\QualificationCatalog;

/** @var string $qualificationRole */
/** @var array<string, mixed> $formData */
/** @var bool $readOnly */
/** @var string|null $qualificationIntro */

$qualificationRole = QualificationCatalog::normalizeRole((string) ($qualificationRole ?? ''));
$formData = is_array($formData ?? null) ? $formData : [];
$readOnly = (bool) ($readOnly ?? false);
$config = QualificationCatalog::roleConfig($qualificationRole);

if ($config === null): ?>
    <div class="mb-4">
        <p class="text-muted small mb-0">
            Próximamente configuraremos las preguntas específicas para tu perfil profesional.
        </p>
    </div>
<?php else:
    $heading = (string) ($config['heading'] ?? 'Cualificación de temas');
    $intro = (string) ($qualificationIntro ?? ($config['intro'] ?? ''));
    $disabledAttr = $readOnly ? ' disabled' : '';
?>
    <div class="mb-3 app-form-section-title">
        <h2 class="h6 fw-semibold mb-1"><?= htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') ?></h2>
        <?php if ($intro !== ''): ?>
            <p class="text-muted small mb-0"><?= htmlspecialchars($intro, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
    </div>

    <div class="app-form-questions">
        <?php foreach (QualificationCatalog::sectionsForRole($qualificationRole) as $section): ?>
            <?php
            $key = (string) ($section['key'] ?? '');
            $title = (string) ($section['title'] ?? $key);
            $type = (string) ($section['type'] ?? 'checkbox');
            $required = !empty($section['required']);
            $hint = (string) ($section['hint'] ?? '');
            $optionCol = (string) ($section['option_col'] ?? 'col-md-6 col-lg-4');
            $options = is_array($section['options'] ?? null) ? $section['options'] : [];
            $rawSelected = $formData[$key] ?? [];
            $selectedValues = is_array($rawSelected)
                ? array_values(array_map('strval', $rawSelected))
                : [trim((string) $rawSelected)];
            $selectedValues = array_values(array_filter($selectedValues, static fn (string $v): bool => $v !== ''));
            $radioValue = $type === 'radio' ? (string) ($selectedValues[0] ?? '') : '';
            ?>
            <div class="mb-4 app-form-question">
                <div class="aoat-qual-section-header mb-3">
                    <h3 class="aoat-qual-section-title mb-1">
                        <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>
                        <?php if ($required && $type !== 'locked'): ?>
                            <span class="text-danger">*</span>
                        <?php endif; ?>
                    </h3>
                    <?php if ($hint !== ''): ?>
                        <p class="text-muted small mb-0"><span class="aoat-qual-hint"><?= htmlspecialchars($hint, ENT_QUOTES, 'UTF-8') ?></span></p>
                    <?php endif; ?>
                </div>
                <div class="row g-2">
                    <?php foreach ($options as $index => $option): ?>
                        <?php
                        if (!is_array($option)) {
                            continue;
                        }
                        $value = (string) ($option['value'] ?? '');
                        $label = (string) ($option['label'] ?? $value);
                        if ($value === '') {
                            continue;
                        }
                        $inputId = $key . '-' . md5($value);
                        $checked = in_array($value, $selectedValues, true);
                        if ($type === 'locked') {
                            $checked = true;
                        }
                        $colClass = $optionCol . ($index > 0 ? ' mt-2' : '');
                        ?>
                        <div class="<?= htmlspecialchars($colClass, ENT_QUOTES, 'UTF-8') ?>">
                            <?php if ($type === 'locked'): ?>
                                <?php if (!$readOnly): ?>
                                    <input type="hidden" name="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>[]" value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>">
                                <?php endif; ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="<?= htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8') ?>" checked disabled>
                                    <label class="form-check-label small" for="<?= htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                    </label>
                                </div>
                            <?php elseif ($type === 'radio'): ?>
                                <div class="form-check">
                                    <input
                                        class="form-check-input"
                                        type="radio"
                                        name="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"
                                        id="<?= htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8') ?>"
                                        value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"
                                        <?= $required && $index === 0 ? 'required' : '' ?>
                                        <?= $radioValue === $value ? 'checked' : '' ?>
                                        <?= $disabledAttr ?>
                                    >
                                    <label class="form-check-label small" for="<?= htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                    </label>
                                </div>
                            <?php else: ?>
                                <div class="form-check">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        name="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>[]"
                                        id="<?= htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8') ?>"
                                        value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"
                                        <?= $checked ? 'checked' : '' ?>
                                        <?= $disabledAttr ?>
                                    >
                                    <label class="form-check-label small" for="<?= htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                    </label>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (!empty($config['show_otro_caso'])): ?>
        <div class="mb-4">
            <label class="form-label">¿Identifica otro caso diferente? Describa cuál</label>
            <textarea
                name="otro_caso"
                class="form-control"
                rows="3"
                placeholder="Describa aquí otro caso diferente, si aplica."
                <?= $disabledAttr ?>
            ><?= htmlspecialchars((string) ($formData['otro_caso'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
    <?php endif; ?>
<?php endif; ?>
