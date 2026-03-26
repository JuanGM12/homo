<?php
/** @var string $mode */
/** @var array<string, mixed>|null $plan */
/** @var array<string, mixed> $professional */
/** @var string $role */
/** @var int $planYear */
/** @var array<string, mixed> $oldInput */
$shortenLabel = function (string $text, int $max = 72): string {
    $t = trim($text);
    if (mb_strlen($t) <= $max) {
        return $t;
    }
    return mb_substr($t, 0, $max - 1, 'UTF-8') . '…';
};
$oldInput = is_array($oldInput ?? null) ? $oldInput : [];
?>

<section class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="/planeacion">Planeación anual</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= $mode === 'edit' ? 'Editar' : 'Nueva' ?></li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h4 mb-1">
                <?= $mode === 'edit' ? 'Editar planeación anual' : 'Nueva planeación anual' ?>
            </h1>
            <p class="text-muted small mb-0">
                <?= htmlspecialchars(mb_convert_case($role, MB_CASE_TITLE, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?> · Año <?= (int) $planYear ?>
            </p>
        </div>
        <a href="/planeacion" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Volver
        </a>
    </div>

    <div class="card border-0 app-form-card">
        <div class="card-body p-4 p-md-5">
            <form method="post" action="<?= $mode === 'edit' ? '/planeacion/editar' : '/planeacion/nueva' ?>" class="app-plan-form">
                <?php if ($mode === 'edit' && $plan !== null): ?>
                    <input type="hidden" name="id" value="<?= htmlspecialchars((string) $plan['id'], ENT_QUOTES, 'UTF-8') ?>">
                <?php endif; ?>
                <input type="hidden" name="plan_year" value="<?= htmlspecialchars((string) ($oldInput['plan_year'] ?? $planYear), ENT_QUOTES, 'UTF-8') ?>">

                <div class="app-form-section mb-4">
                    <h2 class="h6 fw-semibold text-secondary mb-3">Datos del profesional</h2>
                    <div class="row g-3 app-form-fields">
                        <div class="col-md-4">
                            <label class="form-label">Nombre</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars((string) $professional['name'], ENT_QUOTES, 'UTF-8') ?>" disabled>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Correo</label>
                            <input type="email" class="form-control" value="<?= htmlspecialchars((string) $professional['email'], ENT_QUOTES, 'UTF-8') ?>" disabled>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Rol</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars((string) $role, ENT_QUOTES, 'UTF-8') ?>" disabled>
                        </div>
                    </div>
                </div>

                        <div class="app-form-section mb-4">
                    <h2 class="h6 fw-semibold text-secondary mb-3">Ubicación</h2>
                    <div class="row g-3 app-form-fields">
                        <div class="col-md-6">
                            <label for="subregion" class="form-label">Subregión visitada <span class="text-danger">*</span></label>
                            <select
                                id="subregion"
                                name="subregion"
                                class="form-select"
                                required
                                data-subregion-select
                                data-current-value="<?= htmlspecialchars((string) ($oldInput['subregion'] ?? ($plan['subregion'] ?? '')), ENT_QUOTES, 'UTF-8') ?>"
                            >
                                <option value="">Seleccione la subregión</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="municipality" class="form-label">Municipio visitado <span class="text-danger">*</span></label>
                            <select
                                id="municipality"
                                name="municipality"
                                class="form-select"
                                required
                                data-municipality-select
                                data-current-value="<?= htmlspecialchars((string) ($oldInput['municipality'] ?? ($plan['municipality'] ?? '')), ENT_QUOTES, 'UTF-8') ?>"
                                disabled
                            >
                                <option value="">Seleccione el municipio</option>
                            </select>
                        </div>
                    </div>
                </div>

                <?php
                $months = [
                    'enero' => 'Enero',
                    'febrero' => 'Febrero',
                    'marzo' => 'Marzo',
                    'abril' => 'Abril',
                    'mayo' => 'Mayo',
                    'junio' => 'Junio',
                    'julio' => 'Julio',
                    'agosto' => 'Agosto',
                    'septiembre' => 'Septiembre',
                    'octubre' => 'Octubre',
                    'noviembre' => 'Noviembre',
                    'diciembre' => 'Diciembre',
                ];

                $existingPayload = [];
                if ($oldInput !== []) {
                    foreach ($months as $monthKey => $monthLabel) {
                        $rawTopics = $oldInput[$monthKey . '_temas'] ?? [];
                        $topics = is_array($rawTopics)
                            ? array_values(array_filter(array_map('strval', $rawTopics)))
                            : [];
                        $population = trim((string) ($oldInput[$monthKey . '_poblacion'] ?? ''));

                        if ($topics !== [] || $population !== '') {
                            $existingPayload[$monthKey] = [
                                'label' => $monthLabel,
                                'topics' => $topics,
                                'population' => $population,
                            ];
                        }
                    }
                } elseif (!empty($plan['payload'])) {
                    $decoded = json_decode((string) $plan['payload'], true);
                    if (is_array($decoded)) {
                        $existingPayload = $decoded;
                    }
                }

                $topicOptionsAbogado = [
                    'Presentación inicial',
                    'Actualización de la Mesa Municipal de Salud Mental y Prevención de las Adicciones - Módulo 1: Conformación, fortalecimiento o actualización de la Mesa y funciones de la secretaria técnica y funcional, plan de acción de la mesa, Matriz de seguimiento a reuniones. (Guía 1 y 2)',
                    'Actualización de la Mesa Municipal de Salud Mental y Prevención de las Adicciones - Módulo 2: Comisiones de trabajo y plan de acción, Matriz e seguimiento al plan de acción a la política pública municipal de salud mental y prevención de las adicciones. (Guía 3)',
                    'Actualización de la Mesa Municipal de Salud Mental y Prevención de las Adicciones - Módulo 3: Proceso convocatoria de los integrantes para la conformación. (Guía 4)',
                    'Actualización de la Política Pública Municipal de Salud Y Prevención de las Adiciones (PPMSMYPA) - Módulo 4: Realización de las estrategias de la Agenda',
                    'Actualización de la Política Pública Municipal de Salud Y Prevención de las Adiciones (PPMSMYPA) - Módulo 5: Diseño y formulación de la actualización de la PPMSMYPA',
                    'SAFER - Módulo 1: Socialización de la problemática pública del alcohol, Generalidad estrategia SAFER, Legislación actual',
                    'SAFER - Módulo 2: Socialización de la problemática pública del alcohol, Generalidad estrategia SAFER, Legislación actual',
                    'SAFER - Módulo 3: Legislación actual con énfasis en consumo de menores y mujeres en estado de gestación, Socialización de la problemática pública del alcohol, Legislación actual con énfasis en consumo de menores y mujeres en estado de gestación, Violencias relacionadas por el alcohol.',
                    'SAFER - Módulo 4: Legislación actual con énfasis en consumo de menores y mujeres en estado de gestación, Socialización de la problemática pública del alcohol.',
                    'SAFER - Módulo 5: Socialización de la problemática pública del alcohol, Responsabilidad civil y penal.',
                    'No Aplica',
                ];

                $topicOptionsMedico = [
                    'Presentación del programa Salud para el Alma',
                    'Abordaje del manejo de alcohol en el primer nivel de atención – Alcohol y embarazo.',
                    'Abordaje del manejo de tabaco en el primer nivel.',
                    'Adicciones en la baja complejidad',
                    'Conducta suicida',
                    'Desmonte de benzodiacepinas',
                    'Desmonte de opioides',
                    'Epilepsia',
                    'Intoxicaciones por medicamentos de control',
                    'Manejo del dolor',
                    'Paciente agitado',
                    'Pre Test',
                    'Post Test',
                    'Trastorno Afectivo Bipolar',
                    'Trastorno de Déficit de Atención e Hiperactividad',
                    'Trastorno Depresivo',
                    'Trastorno Psicótico',
                    'Trastornos de Ansiedad',
                    'Trastornos del sueño',
                    'No Aplica',
                ];

                $topicOptionsPsicologo = [
                    'Presentación del programa Salud para el Alma',
                    'Suicidio - Módulo 1: Evolución histórica del suicidio, aproximación conceptual de la conducta suicida, teorías explicativas de primera generación, teorías explicativas de segunda generación, factores de riesgo (biológicos, psiquiátricos, psicológicos y sociales), factores de protección, señales de alarma, ruta de atención y articulación intersectorial, notificación y seguimiento, plan de seguridad.',
                    'Suicidio - Módulo 2:  Comunicación y suicidio como factor de riesgo y de protección, impacto del lenguaje y los mensajes, efecto Werther, efecto Papageno, principios de la comunicación responsable, recomendaciones de la OMS para medios y contextos comunitarios, pautas de lo que se debe y no se debe comunicar, aplicación del efecto Papageno en contextos comunitarios e institucionales, roles y responsabilidades de actores clave, poder de la narrativa y reducción del estigma, recursos y guías para la comunicación responsable',
                    'Suicidio - Módulo 3: Concepto y alcances de la posvención, posvención como estrategia de prevención y salud pública, impacto psicosocial del suicidio, duelo por suicidio y sus particularidades, duelo y tamizajes para suicidio (RQC, SRQ, Whooley, GAD-2, Zarit, Plutchick, PHQ-9, C-SSRS), estigma y silencios, principios orientadores de la posvención, acciones de posvención en el territorio, acompañamiento a familias e instituciones, comunicación posterior a una muerte por suicidio, identificación y seguimiento de personas en riesgo, articulación con servicios de salud mental, autocuidado del profesional psicosocial.',
                    'Violencias - Módulo 1: Definición, marco normativo, epidemiología, tipología, características.',
                    'Violencias - Módulo 2: Violencias interpersonales, violencia familiar y de pareja, violencia comunitaria, violencia juvenil, bullying.',
                    'Violencias - Módulo 3: Modelos de prevención de las violencias interpersonales (prevención universal, selectiva, indicada y de recurrencias), programas basados en la evidencia para la prevención de las violencias (modelo INSPIRE, modelo RESPETO y otros).',
                    'Adicciones - Módulo 1: Modelos explicativos (biopsicosocial, aprendizaje y condicionamiento), neurobiología de las adicciones, determinantes sociales, factores de riesgo y de protección, prevención basada en evidencia, influencia normativa.',
                    'Adicciones - Módulo 2: Comprensión de las adicciones según tipo de sustancia, dependencias comportamentales (juego patológico, nomofobia, juegos electrónicos, oniomanía, adicción al trabajo, vigorexia), cigarrillos electrónicos, cannabis, patología dual.',
                    'Adicciones - Módulo 3: Rutas de atención, tamizajes (ASSIST, AUDIT, CRAFFT, Fagerström), intervenciones (entrevista motivacional, intervención única, mindfulness), grupos de apoyo, reducción de riesgos y daños.',
                    'Salud Mental - Cuidado al cuidador',
                    'Salud Mental - Cuidado del profesional – burnout',
                    'Salud Mental - Estigma',
                    'Salud Mental - Grupos de apoyo y ayuda mutua (violencias, SPA, suicidio): teoría y conformación',
                    'Salud Mental - Primeros auxilios psicológicos e intervención en crisis',
                    'Salud Mental - Trastornos mentales prioritarios de interés en salud pública',
                    'Salud Mental - Dispositivos Comunitarios',
                    'Salud Mental - Normatividad en Salud Mental y Adicciones',
                    'Salud Mental - Estrategias de Salud Mental (Aventura de Crecer, Competencias Parentales, Veredas que se Cuidan, Jóvenes pa\' Lante, Familias que se Cuidan, SAFER)',
                ];

                $roleKey = strtolower((string) $role);
                if ($roleKey === 'medico') {
                    $topicOptions = $topicOptionsMedico;
                } elseif ($roleKey === 'psicologo') {
                    $topicOptions = $topicOptionsPsicologo;
                } else {
                    $topicOptions = $topicOptionsAbogado;
                }
                ?>

                <div class="app-form-section">
                    <h2 class="h6 fw-semibold text-secondary mb-3">Temas y población por mes</h2>
                    <p class="text-muted small mb-3">
                        Puedes diligenciar solo los meses que ya tengas definidos. Para cada mes que uses,
                        selecciona al menos un tema y describe la población objetivo.
                    </p>
                    <div class="accordion app-accordion-plan" id="monthsAccordion">
                        <?php foreach ($months as $key => $label): ?>
                            <?php
                            $monthData = $existingPayload[$key] ?? null;
                            $selectedTopics = $monthData['topics'] ?? [];
                            $population = $monthData['population'] ?? '';
                            ?>
                            <div class="accordion-item app-accordion-item">
                                <h2 class="accordion-header" id="heading-<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>">
                                    <button class="accordion-button<?= $key !== 'enero' ? ' collapsed' : '' ?>" type="button"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#collapse-<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"
                                            aria-expanded="<?= $key === 'enero' ? 'true' : 'false' ?>"
                                            aria-controls="collapse-<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>">
                                        <span class="fw-semibold"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                                    </button>
                                </h2>
                                <div id="collapse-<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"
                                     class="accordion-collapse collapse<?= $key === 'enero' ? ' show' : '' ?>"
                                     aria-labelledby="heading-<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"
                                     data-bs-parent="#monthsAccordion">
                                    <div class="accordion-body">
                                        <div class="app-form-question mb-4">
                                            <label class="form-label small text-secondary mb-2">
                                                Temas / módulos a desarrollar (selección múltiple)
                                            </label>
                                            <div class="row g-2">
                                                <?php foreach ($topicOptions as $topic): ?>
                                                    <?php
                                                    $id = $key . '_tema_' . md5($topic);
                                                    $isChecked = in_array($topic, $selectedTopics ?? [], true);
                                                    ?>
                                                    <div class="col-md-6">
                                                        <div class="form-check app-form-check-option">
                                                            <input class="form-check-input" type="checkbox"
                                                                   name="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>_temas[]"
                                                                   id="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>"
                                                                   value="<?= htmlspecialchars($topic, ENT_QUOTES, 'UTF-8') ?>"
                                                                   <?= $isChecked ? 'checked' : '' ?>>
                                                            <label class="form-check-label" for="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($topic, ENT_QUOTES, 'UTF-8') ?>">
                                                                <?= htmlspecialchars($shortenLabel($topic), ENT_QUOTES, 'UTF-8') ?>
                                                            </label>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="form-label small">Población objetivo</label>
                                            <textarea
                                                class="form-control"
                                                name="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>_poblacion"
                                                rows="2"
                                                placeholder="A quién se dirigirán las capacitaciones"
                                            ><?= htmlspecialchars((string) $population, ENT_QUOTES, 'UTF-8') ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="app-form-submit d-flex justify-content-end gap-2">
                    <a href="/planeacion" class="btn btn-outline-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2-circle me-1"></i> Guardar planeación
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>

