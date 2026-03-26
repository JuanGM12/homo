<?php
/** @var array<int, array<string, mixed>> $advisors */
/** @var array<string, mixed>|null $selectedAdvisor */
/** @var array<string, mixed>|null $shareAdvisor */
/** @var string|null $shareLink */
/** @var string|null $qrImageUrl */
/** @var array<string, mixed> $oldInput */

$oldInput = is_array($oldInput ?? null) ? $oldInput : [];

$value = function (string $key, string $default = '') use ($oldInput): string {
    return htmlspecialchars((string) ($oldInput[$key] ?? $default), ENT_QUOTES, 'UTF-8');
};

$scoreValue = function (string $key) use ($oldInput): string {
    return (string) ($oldInput[$key] ?? '');
};
?>
<section class="opinion-survey-shell mb-5">
    <style>
        :root {
            --survey-forest: #1d5b4b;
            --survey-forest-deep: #143d33;
            --survey-blue: #4062aa;
            --survey-sand: #f3ecdf;
            --survey-mint: #edf6f2;
            --survey-line: #d5e5dd;
            --survey-copy: #27403a;
            --survey-muted: #5d7470;
            --survey-shadow: 0 24px 60px rgba(20, 61, 51, 0.10);
        }

        .opinion-survey-shell {
            max-width: 1080px;
            margin: 2rem auto 0;
        }

        .opinion-survey-card {
            position: relative;
            overflow: hidden;
            border-radius: 30px;
            border: 1px solid var(--survey-line);
            background:
                radial-gradient(circle at top left, rgba(64, 98, 170, 0.10), transparent 26%),
                radial-gradient(circle at top right, rgba(29, 91, 75, 0.12), transparent 28%),
                linear-gradient(180deg, #ffffff 0%, #fbfdfc 100%);
            box-shadow: var(--survey-shadow);
        }

        .opinion-survey-card::before {
            content: "";
            position: absolute;
            inset: 0 auto auto 0;
            width: 100%;
            height: 8px;
            background: linear-gradient(90deg, var(--survey-blue), #5ea67c, #88bca8);
        }

        .opinion-survey-header {
            padding: 2.25rem 2.25rem 1.6rem;
            background: linear-gradient(135deg, rgba(243, 236, 223, 0.94), rgba(237, 246, 242, 0.94));
            border-bottom: 1px solid rgba(213, 229, 221, 0.9);
        }

        .opinion-brandbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1.5rem;
            flex-wrap: wrap;
            margin-bottom: 1.6rem;
        }

        .opinion-brand-logos {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .opinion-brand-logos img {
            height: 58px;
            width: auto;
            object-fit: contain;
            filter: drop-shadow(0 10px 18px rgba(20, 61, 51, 0.10));
        }

        .opinion-program-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            padding: 0.65rem 1rem;
            border-radius: 999px;
            background: rgba(64, 98, 170, 0.10);
            color: var(--survey-blue);
            font-size: 0.84rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .opinion-title {
            margin: 0;
            color: var(--survey-forest-deep);
            font-size: clamp(2.1rem, 4vw, 3.1rem);
            line-height: 1.05;
            font-weight: 800;
        }

        .opinion-copy {
            max-width: 820px;
            margin: 0.9rem 0 0;
            color: var(--survey-copy);
            font-size: 1rem;
            line-height: 1.7;
        }

        .opinion-subcopy {
            margin-top: 1rem;
            padding: 1rem 1.1rem;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.72);
            border: 1px solid rgba(213, 229, 221, 0.92);
            color: var(--survey-muted);
            line-height: 1.65;
        }

        .opinion-survey-body {
            padding: 2rem 2.25rem 2.2rem;
        }

        .opinion-section-label {
            margin: 0 0 1rem;
            color: var(--survey-blue);
            font-size: 0.82rem;
            font-weight: 800;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }

        .opinion-share-panel {
            margin: 0 0 1.75rem;
            padding: 1.3rem;
            border-radius: 24px;
            border: 1px solid rgba(213, 229, 221, 0.95);
            background: linear-gradient(135deg, rgba(64, 98, 170, 0.08), rgba(29, 91, 75, 0.08));
        }

        .opinion-share-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 220px;
            gap: 1.35rem;
            align-items: center;
        }

        .opinion-share-title {
            margin: 0 0 0.45rem;
            color: var(--survey-forest-deep);
            font-size: 1.1rem;
            font-weight: 800;
        }

        .opinion-share-copy {
            margin: 0;
            color: var(--survey-copy);
            line-height: 1.65;
        }

        .opinion-share-linkbox {
            margin-top: 0.95rem;
            padding: 0.95rem 1rem;
            border-radius: 18px;
            border: 1px solid rgba(64, 98, 170, 0.18);
            background: rgba(255, 255, 255, 0.82);
            word-break: break-all;
            color: var(--survey-blue);
            font-weight: 700;
        }

        .opinion-share-hint {
            margin-top: 0.7rem;
            color: var(--survey-muted);
            font-size: 0.92rem;
        }

        .opinion-share-qr {
            display: flex;
            justify-content: center;
        }

        .opinion-share-qr img {
            width: 190px;
            height: 190px;
            padding: 0.8rem;
            border-radius: 22px;
            border: 1px solid rgba(213, 229, 221, 0.95);
            background: #ffffff;
            box-shadow: 0 18px 32px rgba(20, 61, 51, 0.10);
        }

        .opinion-input-grid {
            margin-bottom: 2rem;
        }

        .opinion-associated-box {
            padding: 1.1rem 1.15rem;
            border-radius: 20px;
            background: linear-gradient(135deg, rgba(237, 246, 242, 0.95), rgba(255, 255, 255, 0.92));
            border: 1px solid rgba(213, 229, 221, 0.95);
        }

        .opinion-associated-title {
            margin: 0 0 0.4rem;
            color: var(--survey-forest-deep);
            font-size: 0.96rem;
            font-weight: 800;
        }

        .opinion-associated-copy {
            margin: 0;
            color: var(--survey-copy);
            line-height: 1.6;
        }

        .opinion-free-link {
            display: inline-flex;
            margin-top: 0.75rem;
            color: var(--survey-blue);
            font-weight: 700;
            text-decoration: none;
        }

        .opinion-free-link:hover {
            text-decoration: underline;
        }

        .opinion-survey-card .form-label {
            margin-bottom: 0.55rem;
            color: var(--survey-copy);
            font-weight: 700;
        }

        .opinion-survey-card .form-control,
        .opinion-survey-card .form-select {
            min-height: 54px;
            border-radius: 16px;
            border: 1px solid #cdded7;
            box-shadow: none;
            color: var(--survey-copy);
            background-color: #ffffff;
        }

        .opinion-survey-card .form-control:focus,
        .opinion-survey-card .form-select:focus {
            border-color: rgba(64, 98, 170, 0.55);
            box-shadow: 0 0 0 0.25rem rgba(64, 98, 170, 0.12);
        }

        .opinion-scale-shell {
            padding: 1.5rem;
            border-radius: 26px;
            border: 1px solid var(--survey-line);
            background: linear-gradient(180deg, var(--survey-mint), #ffffff);
        }

        .opinion-scale-heading {
            margin: 0 0 1.25rem;
            color: var(--survey-forest-deep);
            font-size: 1rem;
            line-height: 1.65;
            font-weight: 700;
        }

        .opinion-table {
            margin-bottom: 0;
            border-collapse: separate;
            border-spacing: 0 12px;
        }

        .opinion-table thead th {
            border: 0;
            padding: 0 0.35rem 0.7rem;
            background: transparent;
            color: var(--survey-blue);
            font-size: 0.84rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .opinion-table thead th:first-child {
            padding-left: 0;
            text-align: left;
        }

        .opinion-table tbody td {
            padding: 1rem 0.45rem;
            border: 0;
            background: #ffffff;
            vertical-align: middle;
        }

        .opinion-table tbody tr td:first-child {
            border-radius: 18px 0 0 18px;
            padding-left: 1.1rem;
            border: 1px solid rgba(213, 229, 221, 0.92);
            border-right: 0;
        }

        .opinion-table tbody tr td:last-child {
            border-radius: 0 18px 18px 0;
            border: 1px solid rgba(213, 229, 221, 0.92);
            border-left: 0;
        }

        .opinion-table tbody tr td:not(:first-child):not(:last-child) {
            border-top: 1px solid rgba(213, 229, 221, 0.92);
            border-bottom: 1px solid rgba(213, 229, 221, 0.92);
        }

        .opinion-question {
            color: var(--survey-copy);
            font-size: 0.98rem;
            line-height: 1.48;
            font-weight: 800;
        }

        .opinion-scale-cell {
            min-width: 76px;
            text-align: center;
        }

        .opinion-scale-option {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            position: relative;
            cursor: pointer;
        }

        .opinion-scale-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .opinion-scale-bubble {
            width: 48px;
            height: 48px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            border: 2px solid #98b7ad;
            background: #ffffff;
            color: var(--survey-forest);
            font-size: 1rem;
            font-weight: 800;
            box-shadow: 0 8px 18px rgba(20, 61, 51, 0.08);
            transition: transform 0.15s ease, border-color 0.15s ease, background-color 0.15s ease, box-shadow 0.15s ease, color 0.15s ease;
        }

        .opinion-scale-option:hover .opinion-scale-bubble {
            transform: translateY(-1px);
            border-color: var(--survey-blue);
        }

        .opinion-scale-option input[type="radio"]:checked + .opinion-scale-bubble {
            border-color: var(--survey-blue);
            background: var(--survey-blue);
            color: #ffffff;
            box-shadow: 0 12px 24px rgba(64, 98, 170, 0.24);
        }

        .opinion-comments {
            margin-top: 2rem;
        }

        .opinion-comments textarea {
            min-height: 140px;
            border-radius: 20px;
        }

        .opinion-submit {
            min-height: 54px;
            padding-inline: 1.4rem;
            border-radius: 16px;
            font-weight: 700;
        }

        @media (max-width: 991.98px) {
            .opinion-survey-header,
            .opinion-survey-body {
                padding: 1.4rem;
            }

            .opinion-share-grid {
                grid-template-columns: 1fr;
            }

            .opinion-table {
                min-width: 760px;
            }
        }

        @media (max-width: 575.98px) {
            .opinion-brandbar {
                align-items: flex-start;
            }

            .opinion-brand-logos img {
                height: 48px;
            }

            .opinion-scale-shell {
                padding: 1rem;
            }

            .opinion-table tbody td:first-child {
                min-width: 280px;
            }

            .opinion-scale-bubble {
                width: 42px;
                height: 42px;
            }
        }
    </style>

    <?php if (empty($advisors)): ?>
        <div class="alert alert-warning border-0 shadow-sm">
            No hay asesores disponibles en este momento. Contacte al administrador.
        </div>
    <?php else: ?>
        <div class="opinion-survey-card">
            <div class="opinion-survey-header">
                <div class="opinion-brandbar">
                    <div class="opinion-brand-logos">
                        <img src="/assets/img/logoAntioquia.png" alt="Gobernación de Antioquia">
                        <img src="/assets/img/logoHomo.png" alt="Equipo de Promoción y Prevención">
                    </div>
                    <span class="opinion-program-badge">Programa Acción en Territorio</span>
                </div>

                <h1 class="opinion-title">Encuesta de Opinión AoAT</h1>
                <p class="opinion-copy">
                    Este formulario permite evaluar a cada asesor por parte de la población en general. Su opinión nos ayuda a fortalecer la calidad de las asesorías y asistencias técnicas realizadas en territorio.
                </p>
                <div class="opinion-subcopy">
                    Puedes ingresar por un <strong>QR o enlace directo</strong> asociado a un asesor específico, o usar el modo libre para buscarlo manualmente antes de responder.
                </div>
            </div>

            <div class="opinion-survey-body">
                <?php if (!empty($shareAdvisor) && !empty($shareLink) && !empty($qrImageUrl)): ?>
                    <div class="opinion-share-panel">
                        <div class="opinion-share-grid">
                            <div>
                                <p class="opinion-section-label">Compartir encuesta</p>
                                <h2 class="opinion-share-title">Generar mi QR y enlace de evaluación</h2>
                                <p class="opinion-share-copy">
                                    Comparte este código QR o este enlace con la población para que califique tu atención. Al abrirlo, la encuesta quedará asociada directamente a <strong><?= htmlspecialchars((string) ($shareAdvisor['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>.
                                </p>
                                <div class="opinion-share-linkbox"><?= htmlspecialchars((string) $shareLink, ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="opinion-share-hint">Puedes copiar el enlace o escanear el QR desde cualquier celular.</div>
                            </div>
                            <div class="opinion-share-qr">
                                <img src="<?= htmlspecialchars((string) $qrImageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="QR de encuesta de opinión AoAT">
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="post" action="/encuesta-opinion-aoat" id="form-encuesta-aoat">
                    <p class="opinion-section-label">Información general</p>
                    <div class="row g-3 opinion-input-grid">
                        <div class="col-12">
                            <?php if (!empty($selectedAdvisor)): ?>
                                <input type="hidden" name="advisor_user_id" value="<?= (int) ($selectedAdvisor['id'] ?? 0) ?>">
                                <div class="opinion-associated-box">
                                    <p class="opinion-associated-title">Encuesta asociada al asesor</p>
                                    <p class="opinion-associated-copy">
                                        Esta encuesta quedará registrada para <strong><?= htmlspecialchars((string) ($selectedAdvisor['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>.
                                    </p>
                                    <a href="/encuesta-opinion-aoat" class="opinion-free-link">Ingresar en modo libre y buscar otro asesor</a>
                                </div>
                            <?php else: ?>
                                <label for="advisor_user_id" class="form-label">Seleccione el nombre del asesor con el que realizó la actividad <span class="text-danger">*</span></label>
                                <select name="advisor_user_id" id="advisor_user_id" class="form-select" required>
                                    <option value="">Seleccione el asesor</option>
                                    <?php foreach ($advisors as $advisor): ?>
                                        <option value="<?= (int) ($advisor['id'] ?? 0) ?>" <?= (string) ($advisor['id'] ?? '') === (string) ($oldInput['advisor_user_id'] ?? '') ? 'selected' : '' ?>>
                                            <?= htmlspecialchars((string) ($advisor['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label for="actividad" class="form-label">Actividad <span class="text-danger">*</span></label>
                            <input type="text" name="actividad" id="actividad" class="form-control" required maxlength="500" placeholder="Describa la actividad realizada" value="<?= $value('actividad') ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="lugar" class="form-label">Lugar <span class="text-danger">*</span></label>
                            <input type="text" name="lugar" id="lugar" class="form-control" required maxlength="300" placeholder="Lugar donde se desarrolló la actividad" value="<?= $value('lugar') ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="activity_date" class="form-label">Fecha <span class="text-danger">*</span></label>
                            <input type="date" name="activity_date" id="activity_date" class="form-control" min="2026-01-01" required value="<?= $value('activity_date') ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="subregion" class="form-label">Seleccione la subregión de pertenencia <span class="text-danger">*</span></label>
                            <select name="subregion" id="subregion" class="form-select" required data-subregion-select data-selected-value="<?= $value('subregion') ?>">
                                <option value="">Seleccione la subregión de pertenencia</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="municipality" class="form-label">Seleccione el municipio de pertenencia <span class="text-danger">*</span></label>
                            <select name="municipality" id="municipality" class="form-select" required data-municipality-select data-selected-value="<?= $value('municipality') ?>" disabled>
                                <option value="">Seleccione el municipio de pertenencia</option>
                            </select>
                        </div>
                    </div>

                    <div class="opinion-scale-shell">
                        <p class="opinion-section-label">Valoración del servicio</p>
                        <p class="opinion-scale-heading">
                            Marque su respuesta en la escala de <strong>1 a 5</strong> según su grado de satisfacción por el servicio recibido. <span class="text-danger">*</span>
                        </p>

                        <div class="table-responsive">
                            <table class="table opinion-table align-middle">
                                <thead>
                                    <tr>
                                        <th style="width: 50%">Pregunta</th>
                                        <th class="text-center">1</th>
                                        <th class="text-center">2</th>
                                        <th class="text-center">3</th>
                                        <th class="text-center">4</th>
                                        <th class="text-center">5</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $items = [
                                        'score_objetivos' => 'Cumplimiento de los objetivos programados',
                                        'score_claridad' => 'Claridad y organización del asesor en el desarrollo de la asesoría o asistencia técnica',
                                        'score_pertinencia' => 'Pertinencia de los temas asesorados como apoyo para la gestión local',
                                        'score_ayudas' => 'Manejo apropiado de las ayudas y materiales utilizados en el desarrollo de las actividades',
                                        'score_relacion' => 'Relación profesional del asesor con el usuario del servicio',
                                        'score_puntualidad' => 'Puntualidad del asesor con los horarios establecidos para la actividad',
                                    ];
                                    foreach ($items as $name => $label):
                                        $currentScore = $scoreValue($name);
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="opinion-question"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></div>
                                            </td>
                                            <?php for ($score = 1; $score <= 5; $score++): ?>
                                                <td class="opinion-scale-cell">
                                                    <label class="opinion-scale-option">
                                                        <input type="radio" name="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>" value="<?= $score ?>" <?= $currentScore === (string) $score ? 'checked' : '' ?> required>
                                                        <span class="opinion-scale-bubble"><?= $score ?></span>
                                                    </label>
                                                </td>
                                            <?php endfor; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="opinion-comments">
                        <label for="comments" class="form-label">Recomendaciones o comentarios sobre la asesoría</label>
                        <textarea name="comments" id="comments" class="form-control" rows="4" placeholder="Su opinión servirá para prestar un mejor servicio"><?= $value('comments') ?></textarea>
                    </div>

                    <div class="d-flex gap-2 justify-content-end mt-4">
                        <button type="submit" class="btn btn-primary opinion-submit">
                            <i class="bi bi-send me-1"></i>
                            Enviar encuesta
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</section>
