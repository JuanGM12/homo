<?php
/** @var array $config */
/** @var array $prefill */
$isPre = $config['phase'] === 'pre';
$prefill = $prefill ?? [
    'document_number' => '',
    'first_name' => '',
    'last_name' => '',
    'subregion' => '',
    'municipality' => '',
    'profession' => '',
];
?>

<section class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="/evaluaciones">Evaluaciones</a></li>
            <li class="breadcrumb-item active" aria-current="page">
                <?= htmlspecialchars($config['title'], ENT_QUOTES, 'UTF-8') ?>
            </li>
        </ol>
    </nav>

    <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-9">
            <div class="card border-0 app-form-card">
                <div class="card-body p-4 p-md-5">
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-4 flex-wrap">
                        <div>
                            <h1 class="h4 fw-bold mb-1">
                                <?= htmlspecialchars($config['title'], ENT_QUOTES, 'UTF-8') ?>
                            </h1>
                            <p class="text-muted mb-0 small">
                                <?php
                                if ($isPre) {
                                    echo 'Este formulario se diligencia antes de la charla o intervención.';
                                } elseif (($config['key'] ?? '') === 'hospitales') {
                                    echo 'Este formulario se diligencia después de la charla o intervención. Si ya existe un PRE - TEST con el mismo documento, los datos personales y de territorio se completan automáticamente; si no, complételos manualmente (el cálculo por municipio no exige PRE previo).';
                                } else {
                                    echo 'Este formulario se diligencia después de la charla o intervención, usando el mismo número de documento del PRE - TEST.';
                                }
                                ?>
                            </p>
                        </div>
                        <span class="badge rounded-pill text-bg-light">
                            <?= $isPre ? 'Paso 1 · Diagnóstico' : 'Paso 2 · Evaluación final' ?>
                        </span>
                    </div>

                    <form
                            method="post"
                            action=""
                            data-test-key="<?= htmlspecialchars($config['key'], ENT_QUOTES, 'UTF-8') ?>"
                            data-phase="<?= htmlspecialchars($config['phase'], ENT_QUOTES, 'UTF-8') ?>"
                    >
                        <input type="hidden" name="test_key" value="<?= htmlspecialchars($config['key'], ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="phase" value="<?= htmlspecialchars($config['phase'], ENT_QUOTES, 'UTF-8') ?>">

                        <div class="row g-3 mb-4 app-form-fields">
                            <div class="col-md-6">
                                <label class="form-label">Número de documento <span class="text-danger">*</span></label>
                                <input
                                        type="text"
                                        name="document_number"
                                        class="form-control"
                                        inputmode="numeric"
                                        oninput="this.value = this.value.replace(/[^0-9]/g, '');"
                                        required
                                        value="<?= htmlspecialchars((string) ($prefill['document_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                >
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Nombres <span class="text-danger">*</span></label>
                                <input
                                    type="text"
                                    name="first_name"
                                    class="form-control"
                                    required
                                    value="<?= htmlspecialchars((string) ($prefill['first_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                >
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Apellidos <span class="text-danger">*</span></label>
                                <input
                                    type="text"
                                    name="last_name"
                                    class="form-control"
                                    required
                                    value="<?= htmlspecialchars((string) ($prefill['last_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                >
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Subregión <span class="text-danger">*</span></label>
                                <select
                                    name="subregion"
                                    class="form-select"
                                    required
                                    data-subregion-select
                                    data-current-value="<?= htmlspecialchars((string) ($prefill['subregion'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                >
                                    <option value="">Seleccione la subregión de pertenencia</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Municipio <span class="text-danger">*</span></label>
                                <select
                                    name="municipality"
                                    class="form-select"
                                    required
                                    data-municipality-select
                                    data-current-value="<?= htmlspecialchars((string) ($prefill['municipality'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    disabled
                                >
                                    <option value="">Seleccione el municipio de pertenencia</option>
                                </select>
                            </div>
                            <?php if ($config['isHospital']): ?>
                                <div class="col-md-6">
                                    <label class="form-label">Profesión <span class="text-danger">*</span></label>
                                    <input
                                        type="text"
                                        name="profession"
                                        class="form-control"
                                        required
                                        value="<?= htmlspecialchars((string) ($prefill['profession'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    >
                                </div>
                            <?php endif; ?>
                        </div>

                        <hr class="my-4 app-form-divider">

                        <div class="mb-4 app-form-section-title">
                            <h2 class="h6 fw-semibold mb-1">Preguntas del test</h2>
                            <p class="text-muted small mb-0">
                                Responda marcando la opción que considere correcta en cada caso.
                            </p>
                        </div>

                        <div class="app-form-questions">
                        <?php if ($config['key'] === 'violencias'): ?>
                            <div class="mb-4 app-form-question">
                                <p class="fw-semibold mb-1">
                                    1. Un programa de prevención que está dirigido exclusivamente a mujeres gestantes y a familias en
                                    situación de vulnerabilidad, identificadas a partir de factores de riesgo como la pobreza y el bajo
                                    nivel educativo, y que su objetivo es prevenir el maltrato infantil antes de que este ocurra.
                                    ¿A qué modelo de prevención, según los enfoques de la Salud Pública, corresponde esta intervención?
                                </p>
                                <div class="text-muted small mb-2">Seleccione una opción *</div>
                                <?php
                                $options1 = [
                                    'A' => 'Prevención universal',
                                    'B' => 'Prevención indicada',
                                    'C' => 'Prevención de la recurrencia',
                                    'D' => 'Prevención selectiva',
                                    'E' => 'Prevención Ambiental',
                                ];
                                foreach ($options1 as $value => $label): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q1" id="q1_<?= $value ?>"
                                               value="<?= $value ?>" required>
                                        <label class="form-check-label" for="q1_<?= $value ?>">
                                            <?= $value ?>. <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mb-4 app-form-question">
                                <p class="fw-semibold mb-1">
                                    2. Dentro del Modelo Ecológico de la OMS para el estudio de la violencia, el factor de riesgo que
                                    abarca las normas culturales que legitiman el dominio masculino sobre mujeres y niños, así como las
                                    políticas sanitarias que perpetúan la desigualdad, se ubica en el:
                                </p>
                                <div class="text-muted small mb-2">Seleccione una opción *</div>
                                <?php
                                $options2 = [
                                    'A' => 'Nivel individual',
                                    'B' => 'Nivel comunitario',
                                    'C' => 'Nivel social',
                                    'D' => 'Nivel de las relaciones',
                                    'E' => 'Nivel económico',
                                ];
                                foreach ($options2 as $value => $label): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q2" id="q2_<?= $value ?>"
                                               value="<?= $value ?>" required>
                                        <label class="form-check-label" for="q2_<?= $value ?>">
                                            <?= $value ?>. <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mb-4 app-form-question">
                                <p class="fw-semibold mb-1">
                                    3. En el marco estratégico INSPIRE de la OMS/OPS para la prevención de la violencia contra la niñez,
                                    la letra “P” corresponde al componente:
                                </p>
                                <div class="text-muted small mb-2">Seleccione una opción *</div>
                                <?php
                                $options3 = [
                                    'A' => 'Reducción de la pobreza y acceso a ingresos familiares.',
                                    'B' => 'Programas de habilidades sociales y emocionales para niños.',
                                    'C' => 'Padres, madres y cuidadores reciben apoyo (programas de crianza positiva).',
                                    'D' => 'Policía comunitaria y estrategias de vigilancia.',
                                    'E' => 'Política pública',
                                ];
                                foreach ($options3 as $value => $label): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q3" id="q3_<?= $value ?>"
                                               value="<?= $value ?>" required>
                                        <label class="form-check-label" for="q3_<?= $value ?>">
                                            <?= $value ?>. <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mb-4 app-form-question">
                                <p class="fw-semibold mb-1">
                                    4. El enfoque de la salud pública para la prevención de la violencia se estructura en cuatro pasos
                                    fundamentales. ¿Cuál de estos pasos se centra específicamente en la acción de diseñar, implementar y
                                    evaluar de manera rigurosa intervenciones preventivas basadas en evidencia?
                                </p>
                                <div class="text-muted small mb-2">Seleccione una opción *</div>
                                <?php
                                $options4 = [
                                    'A' => 'Primer paso: Recopilar datos y establecer la magnitud del problema.',
                                    'B' => 'Segundo paso: Investigar las causas y los factores de riesgo y de protección.',
                                    'C' => 'Tercer paso: Diseñar, implementar y evaluar intervenciones preventivas.',
                                    'D' => 'Cuarto paso: Implementar y ampliar estrategias eficaces a gran escala.',
                                    'E' => 'Quinto paso: Detección temprana',
                                ];
                                foreach ($options4 as $value => $label): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q4" id="q4_<?= $value ?>"
                                               value="<?= $value ?>" required>
                                        <label class="form-check-label" for="q4_<?= $value ?>">
                                            <?= $value ?>. <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mb-4 app-form-question">
                                <p class="fw-semibold mb-1">
                                    5. Las violencias interpersonales se caracterizan principalmente por:
                                </p>
                                <div class="text-muted small mb-2">Seleccione una opción *</div>
                                <?php
                                $options5 = [
                                    'A' => 'Darse en relaciones donde existe interacción directa y asimetrías de poder.',
                                    'B' => 'Ocurrir únicamente en el ámbito familiar.',
                                    'C' => 'Ser producto exclusivo de trastornos mentales individuales.',
                                    'D' => 'Estar asociadas a contextos de criminalidad organizada.',
                                    'E' => 'A y B son correctas',
                                ];
                                foreach ($options5 as $value => $label): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q5" id="q5_<?= $value ?>"
                                               value="<?= $value ?>" required>
                                        <label class="form-check-label" for="q5_<?= $value ?>">
                                            <?= $value ?>. <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mb-4 app-form-question">
                                <p class="fw-semibold mb-1">
                                    6. La violencia comunitaria se diferencia de otras formas de violencia porque:
                                </p>
                                <div class="text-muted small mb-2">Seleccione una opción *</div>
                                <?php
                                $options6 = [
                                    'A' => 'Se limita a conflictos armados.',
                                    'B' => 'Ocurre exclusivamente entre desconocidos.',
                                    'C' => 'No tiene relación con factores estructurales.',
                                    'D' => 'Afecta el sentido de seguridad colectiva y el tejido social del territorio.',
                                    'E' => 'Es menos relevante para la salud pública.',
                                ];
                                foreach ($options6 as $value => $label): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q6" id="q6_<?= $value ?>"
                                               value="<?= $value ?>" required>
                                        <label class="form-check-label" for="q6_<?= $value ?>">
                                            <?= $value ?>. <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mb-4 app-form-question">
                                <p class="fw-semibold mb-1">
                                    7. ¿Cuál de las siguientes afirmaciones describe de manera más precisa el bullying?
                                </p>
                                <div class="text-muted small mb-2">Seleccione una opción *</div>
                                <?php
                                $options7 = [
                                    'A' => 'Conductas agresivas entre estudiantes que surgen como respuesta a conflictos situacionales y se resuelven mediante mediación escolar.',
                                    'B' => 'Interacciones negativas entre pares que pueden incluir burlas, exclusión o agresiones, independientemente de su frecuencia o intencionalidad.',
                                    'C' => 'Comportamientos hostiles entre estudiantes que se presentan de forma reiterada, con intencionalidad de daño y un desequilibrio de poder real o percibido.',
                                    'D' => 'Manifestaciones de violencia escolar asociadas principalmente a dificultades de regulación emocional y problemas de convivencia institucional.',
                                    'E' => 'Acciones de intimidación que ocurren exclusivamente en espacios físicos de la institución educativa y durante la jornada académica.',
                                ];
                                foreach ($options7 as $value => $label): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q7" id="q7_<?= $value ?>"
                                               value="<?= $value ?>" required>
                                        <label class="form-check-label" for="q7_<?= $value ?>">
                                            <?= $value ?>. <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mb-4 app-form-question">
                                <p class="fw-semibold mb-1">
                                    8. En relación con la violencia intrafamiliar, es correcto afirmar que:
                                </p>
                                <div class="text-muted small mb-2">Seleccione una opción *</div>
                                <?php
                                $options8 = [
                                    'A' => 'Son experiencias adversas en el entorno familiar que generan malestar emocional, pero que no constituyen una vulneración de los derechos.',
                                    'B' => 'Incluye exclusivamente la agresión física debido a patrones de disfunción familiar relacionados con estilos de crianza inadecuados o límites difusos.',
                                    'C' => 'Puede manifestarse de manera psicológica, económica, sexual y simbólica.',
                                    'D' => 'Situaciones de tensión en el hogar caracterizadas por altos niveles de conflicto y discusiones frecuentes asociadas a estrés crónico y consumo de sustancias psicoactivas.',
                                    'E' => 'Debe abordarse solo desde el ámbito legal.',
                                ];
                                foreach ($options8 as $value => $label): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q8" id="q8_<?= $value ?>"
                                               value="<?= $value ?>" required>
                                        <label class="form-check-label" for="q8_<?= $value ?>">
                                            <?= $value ?>. <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mb-4 app-form-question">
                                <p class="fw-semibold mb-1">
                                    9. La violencia de pareja se sostiene frecuentemente por:
                                </p>
                                <div class="text-muted small mb-2">Seleccione una opción *</div>
                                <?php
                                $options9 = [
                                    'A' => 'Conflictos comunicativos aislados.',
                                    'B' => 'Patologías individuales exclusivamente.',
                                    'C' => 'Dinámicas de control, dependencia emocional y normalización cultural de la violencia.',
                                    'D' => 'Falta de información sobre derechos.',
                                    'E' => 'Consumo de sustancias como única causa.',
                                ];
                                foreach ($options9 as $value => $label): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q9" id="q9_<?= $value ?>"
                                               value="<?= $value ?>" required>
                                        <label class="form-check-label" for="q9_<?= $value ?>">
                                            <?= $value ?>. <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php elseif ($config['key'] === 'suicidios'): ?>
                            <div class="mb-4 app-form-question">
                                <p class="fw-semibold mb-1">
                                    1. María, 17 años, consulta por ánimo bajo, sentimientos de desconexión con sus pares y la percepción
                                    persistente de que “no aporta nada a su familia”. Su maestra refiere que ha perdido interés por
                                    actividades donde antes sobresalía. No se identifican conductas peligrosas ni exposición reciente a
                                    dolor físico ni sensación de derrota, pero sí expresa sentirse “totalmente sola”. Con la información
                                    suministrada, ¿qué teoría se ajusta mejor a las variables de riesgo descritas?
                                </p>
                                <div class="text-muted small mb-2">Seleccione una opción *</div>
                                <?php
                                $s1 = [
                                    'A' => 'Modelo Motivacional Volitivo Integrado.',
                                    'B' => 'Teoría Interpersonal del Suicidio.',
                                    'C' => 'Teoría de la Vulnerabilidad Fluida.',
                                    'D' => 'Teoría de los 3 Pasos.',
                                    'E' => 'Teoría del dolor psicológico.',
                                ];
                                foreach ($s1 as $value => $label): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q1" id="sq1_<?= $value ?>"
                                               value="<?= $value ?>" required>
                                        <label class="form-check-label" for="sq1_<?= $value ?>">
                                            <?= $value ?>. <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mb-4 app-form-question">
                                <p class="fw-semibold mb-1">
                                    2. En relación con los intentos de suicidio, ¿Cuál de las siguientes afirmaciones se ajusta mejor a la
                                    evidencia y al enfoque preventivo en salud mental?
                                </p>
                                <div class="text-muted small mb-2">Seleccione una opción *</div>
                                <?php
                                $s2 = [
                                    'A' => 'La mayoría de los intentos de suicidio ocurren de manera impulsiva y sin señales previas observables, lo que limita su valor preventivo.',
                                    'B' => 'Un intento de suicidio debe comprenderse principalmente como un predictor estadístico de muerte futura, más que como una oportunidad de intervención.',
                                    'C' => 'Los intentos de suicidio cumplen funciones distintas según el contexto, y su abordaje debe centrarse únicamente en la letalidad del método utilizado.',
                                    'D' => 'Un intento de suicidio es uno de los factores de riesgo más relevantes para futuros intentos, pero también constituye una ventana crítica para intervenciones indicadas y seguimiento continuo.',
                                    'E' => 'La repetición de intentos se asocia únicamente a trastornos psiquiátricos graves y no a variables psicosociales o contextuales.',
                                ];
                                foreach ($s2 as $value => $label): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q2" id="sq2_<?= $value ?>"
                                               value="<?= $value ?>" required>
                                        <label class="form-check-label" for="sq2_<?= $value ?>">
                                            <?= $value ?>. <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mb-4 app-form-question">
                                <p class="fw-semibold mb-1">
                                    3. Desde el enfoque de crisis suicida, ¿Cuál de las siguientes formulaciones refleja una comprensión
                                    adecuada de su naturaleza y manejo?
                                </p>
                                <div class="text-muted small mb-2">Seleccione una opción *</div>
                                <?php
                                $s3 = [
                                    'A' => 'La crisis suicida se define por la presencia exclusiva de ideación suicida activa y requiere siempre hospitalización inmediata.',
                                    'B' => 'Una crisis suicida implica un estado transitorio de desorganización emocional en el que las estrategias habituales de afrontamiento se ven desbordadas, aumentando el riesgo de conductas autolesivas.',
                                    'C' => 'Las crisis suicidas son procesos prolongados y estables en el tiempo, lo que permite una intervención planificada sin urgencia.',
                                    'D' => 'El manejo de la crisis debe centrarse prioritariamente en la exploración exhaustiva de antecedentes psicopatológicos.',
                                    'E' => 'La resolución de la crisis se alcanza cuando la persona verbaliza la ausencia de ideación suicida, sin necesidad de seguimiento posterior.',
                                ];
                                foreach ($s3 as $value => $label): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q3" id="sq3_<?= $value ?>"
                                               value="<?= $value ?>" required>
                                        <label class="form-check-label" for="sq3_<?= $value ?>">
                                            <?= $value ?>. <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mb-4 app-form-question">
                                <p class="fw-semibold mb-1">
                                    4. En el marco de la posvención tras una muerte por suicidio, ¿cuál de las siguientes acciones es más
                                    consistente con las buenas prácticas?
                                </p>
                                <div class="text-muted small mb-2">Seleccione una opción *</div>
                                <?php
                                $s4 = [
                                    'A' => 'Evitar abordar el tema del suicidio con las personas cercanas para prevenir efectos de imitación.',
                                    'B' => 'Focalizar las acciones exclusivamente en los familiares directos, ya que el impacto no suele extenderse a otros entornos.',
                                    'C' => 'Implementar intervenciones que reconozcan el duelo complejo, identifiquen personas en riesgo y promuevan narrativas no sensacionalistas ni culpabilizantes.',
                                    'D' => 'Priorizar la atención clínica individual, dejando en segundo plano las intervenciones comunitarias.',
                                    'E' => 'Centrar la posvención en el cierre administrativo del caso y la reducción de la exposición mediática.',
                                ];
                                foreach ($s4 as $value => $label): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q4" id="sq4_<?= $value ?>"
                                               value="<?= $value ?>" required>
                                        <label class="form-check-label" for="sq4_<?= $value ?>">
                                            <?= $value ?>. <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mb-4 app-form-question">
                                <p class="fw-semibold mb-1">
                                    5. ¿Cuál de las siguientes acciones corresponde a la prevención universal del suicidio?
                                </p>
                                <div class="text-muted small mb-2">Seleccione una opción *</div>
                                <?php
                                $s5 = [
                                    'A' => 'Capacitar a un grupo de adolescentes que recientemente han presentado ideación suicida.',
                                    'B' => 'Implementar protocolos de atención inmediata para personas que se encuentran en un intento suicida.',
                                    'C' => 'Desarrollar una campaña comunitaria que promueva habilidades para la vida y el uso de líneas de apoyo emocional.',
                                    'D' => 'Realizar seguimiento clínico semanal a personas con antecedentes de intentos de suicidio.',
                                    'E' => 'Seguimiento de casos reportados en SIVIGILA',
                                ];
                                foreach ($s5 as $value => $label): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q5" id="sq5_<?= $value ?>"
                                               value="<?= $value ?>" required>
                                        <label class="form-check-label" for="sq5_<?= $value ?>">
                                            <?= $value ?>. <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mb-4 app-form-question">
                                <p class="fw-semibold mb-1">
                                    6. Luis, 24 años, perdió recientemente su empleo y ha tenido conflictos continuos con su pareja. En
                                    consulta describe una intensa sensación de estar “acorralado” entre responsabilidades económicas y
                                    presiones familiares. Expresa que “no ve salida”, pero mantiene un comportamiento funcional mínimo y
                                    conserva apoyo social disponible. No manifiesta intención de hacerse daño, pero sí un marcado
                                    agotamiento psicológico y dificultad para tomar decisiones. ¿Cuál constructo del modelo de O’Connor
                                    explica mejor el riesgo potencial en este escenario?
                                </p>
                                <div class="text-muted small mb-2">Seleccione una opción *</div>
                                <?php
                                $s6 = [
                                    'A' => 'Rumiar sobre la derrota.',
                                    'B' => 'Atrapamiento, que dependiendo de su interacción con los moderadores motivacionales puede conducir a ideación suicidia.',
                                    'C' => 'Pertenencia frustrada, que se puede manifestar como aislamiento social y conducir a ideación suicida, en especial si se asocia a la percepción de ser una carga.',
                                    'D' => 'Proceso volitivo desencadenado por planificación.',
                                ];
                                foreach ($s6 as $value => $label): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q6" id="sq6_<?= $value ?>"
                                               value="<?= $value ?>" required>
                                        <label class="form-check-label" for="sq6_<?= $value ?>">
                                            <?= $value ?>. <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mb-4 app-form-question">
                                <p class="fw-semibold mb-1">
                                    7. Una estrategia de prevención universal del suicidio es:
                                </p>
                                <div class="text-muted small mb-2">Seleccione una opción *</div>
                                <?php
                                $s7 = [
                                    'A' => 'Establecer grupos de apoyo para familias afectadas por una muerte por suicidio.',
                                    'B' => 'Implementar barreras físicas en puentes y controlar la disponibilidad de pesticidas y armas de fuego.',
                                    'C' => 'Desarrollar talleres emocionales para jóvenes con dificultades académicas.',
                                    'D' => 'Ofrecer terapia cognitivo-conductual a personas con trastornos depresivos',
                                    'E' => 'Talleres con el entorno escolar',
                                ];
                                foreach ($s7 as $value => $label): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q7" id="sq7_<?= $value ?>"
                                               value="<?= $value ?>" required>
                                        <label class="form-check-label" for="sq7_<?= $value ?>">
                                            <?= $value ?>. <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mb-4 app-form-question">
                                <p class="fw-semibold mb-1">
                                    8. Una adolescente de 17 años consulta por “no ver sentido a su vida”, dice sentirse constantemente
                                    desesperanzada y expresa que “todo le da igual”. No refiere un plan concreto. ¿Cuál actitud es correcta
                                    por parte del personal del primer nivel?
                                </p>
                                <div class="text-muted small mb-2">Seleccione una opción *</div>
                                <?php
                                $s8 = [
                                    'A' => 'Minimizar lo que dice, asumiendo que “son cosas de la edad”.',
                                    'B' => 'Considerar que se trata de sintomatología esperada para ese momento del curso de vida.',
                                    'C' => 'Reconocer las señales de riesgo, realizar valoración completa del riesgo de suicidio para definir criterios de manejo en urgencias.',
                                    'D' => 'Recomendar que siga tomando sus medicamentos ISRS y decirle que vuelva en varias semanas.',
                                    'E' => 'Decirle que no es grave y recomendar pedir con medicina orden para valoración ambulatoria por parte de psicología.',
                                ];
                                foreach ($s8 as $value => $label): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q8" id="sq8_<?= $value ?>"
                                               value="<?= $value ?>" required>
                                        <label class="form-check-label" for="sq8_<?= $value ?>">
                                            <?= $value ?>. <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mb-4 app-form-question">
                                <p class="fw-semibold mb-1">
                                    9. Una estrategia de prevención selectiva del suicidio se orienta mejor hacia:
                                </p>
                                <div class="text-muted small mb-2">Seleccione una opción *</div>
                                <?php
                                $s9 = [
                                    'A' => 'Personas que no presentan un riesgo identificado y pertenecen a la población general.',
                                    'B' => 'Individuos que han tenido una tentativa suicida y requieren una intervención intensiva.',
                                    'C' => 'Grupos que comparten factores de riesgo elevados, aunque no presenten síntomas actuales.',
                                    'D' => 'Equipos de emergencia que atienden crisis en curso.',
                                    'E' => 'Campañas sociales y comunitaria',
                                ];
                                foreach ($s9 as $value => $label): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q9" id="sq9_<?= $value ?>"
                                               value="<?= $value ?>" required>
                                        <label class="form-check-label" for="sq9_<?= $value ?>">
                                            <?= $value ?>. <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mb-4 app-form-question">
                                <p class="fw-semibold mb-1">
                                    10. ¿Cuál de las siguientes acciones describe mejor una intervención indicada en la prevención del
                                    suicidio?
                                </p>
                                <div class="text-muted small mb-2">Seleccione una opción *</div>
                                <?php
                                $s10 = [
                                    'A' => 'Capacitar a docentes para identificar señales de alarma en los estudiantes.',
                                    'B' => 'Realizar tamizajes poblacionales para detectar riesgos de manera temprana.',
                                    'C' => 'Busqueda activa comunitaria',
                                    'D' => 'Diseñar entornos urbanos más seguros para disminuir el acceso a medios letales.',
                                    'E' => 'Ofrecer una intervención breve y un plan de seguridad a una persona que presenta desesperanza persistente y señales claras de riesgo inminente.',
                                ];
                                foreach ($s10 as $value => $label): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q10" id="sq10_<?= $value ?>"
                                               value="<?= $value ?>" required>
                                        <label class="form-check-label" for="sq10_<?= $value ?>">
                                            <?= $value ?>. <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mb-4 app-form-question">
                                <p class="fw-semibold mb-1">
                                    11. ¿Cuál de las siguientes combinaciones de instrumentos corresponde de manera más adecuada a
                                    tamizajes útiles para la detección del riesgo suicida?
                                </p>
                                <div class="text-muted small mb-2">Seleccione una opción *</div>
                                <?php
                                $s11 = [
                                    'A' => 'DAST-10, CAGE y AUDIT, debido a su capacidad para identificar conductas de riesgo asociadas a ideación suicida.',
                                    'B' => 'APGAR Familiar, Zarit y RQC, ya que evalúan factores protectores y contextuales directamente vinculados al riesgo suicida.',
                                    'C' => 'Fagerström y CAGE, por su utilidad en la identificación de síntomas depresivos como principal predictor de suicidio.',
                                    'D' => 'Columbia-SSRS, Plutchik y Sad Person, considerando que ambas incluyen exploración directa o indirecta de ideación suicida.',
                                    'E' => 'RQC, Whooley y Zarit, debido a que permiten inferir riesgo suicida a partir de malestar psicológico y sobrecarga del cuidador.',
                                ];
                                foreach ($s11 as $value => $label): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q11" id="sq11_<?= $value ?>"
                                               value="<?= $value ?>" required>
                                        <label class="form-check-label" for="sq11_<?= $value ?>">
                                            <?= $value ?>. <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mb-4 app-form-question">
                                <p class="fw-semibold mb-1">
                                    12. En relación con la comunicación sobre suicidio y sus efectos en la población, ¿cuál de las
                                    siguientes afirmaciones describe de manera más precisa la diferencia entre el Efecto Werther y el
                                    Efecto Papageno?
                                </p>
                                <div class="text-muted small mb-2">Seleccione una opción *</div>
                                <?php
                                $s12 = [
                                    'A' => 'El efecto Werther se refiere al aumento de conductas suicidas tras la difusión de información falsa, mientras que el efecto Papageno se asocia a campañas institucionales de prevención.',
                                    'B' => 'El efecto Papageno ocurre cuando se evita hablar de suicidio en medios de comunicación, y el efecto Werther aparece cuando se abordan casos reales con datos estadísticos.',
                                    'C' => 'El efecto Werther está relacionado con la cobertura sensacionalista, repetitiva o detallada de suicidios, que puede incrementar conductas imitativas, mientras que el efecto Papageno se vincula a narrativas que muestran alternativas de afrontamiento y búsqueda de ayuda.',
                                    'D' => 'Ambos efectos hacen referencia al impacto negativo de los medios de comunicación, diferenciándose únicamente por el tipo de población expuesta.',
                                    'E' => 'El efecto Papageno consiste en minimizar el impacto emocional del suicidio, y el efecto Werther en visibilizar el sufrimiento de las personas sobrevivientes.',
                                ];
                                foreach ($s12 as $value => $label): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q12" id="sq12_<?= $value ?>"
                                               value="<?= $value ?>" required>
                                        <label class="form-check-label" for="sq12_<?= $value ?>">
                                            <?= $value ?>. <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php elseif ($config['key'] === 'adicciones'): ?>
                            <div class="mb-4 app-form-question">
                                <h3 class="h6 fw-semibold mb-2">Caso de contexto</h3>
                                <p class="mb-2">
                                    Andrés es un adolescente de 13 años que cursa octavo grado y vive con ambos padres. Hace seis meses inició consumo de alcohol cuando su padre le ofreció cerveza, y tres meses después consumió marihuana por primera vez también en compañía de su padre, quien tiene un consumo problemático de alcohol y marihuana desde hace ocho años.
                                </p>
                                <p class="mb-2">
                                    La madre descubrió la situación al encontrarlos consumiendo juntos en casa hace un mes, lo que desencadenó una crisis familiar. El padre minimiza la situación argumentando que prefiere que el hijo consuma bajo supervisión y considera que son experimentaciones normales de la edad, mientras que Andrés niega tener un problema y afirma poder dejar el consumo cuando quiera.
                                </p>
                                <p class="mb-2">
                                    El adolescente presenta deterioro académico significativo con pérdida de tres materias, cambios comportamentales marcados como aislamiento, irritabilidad, alteraciones del sueño, cambio de grupo de amigos hacia jóvenes mayores e inasistencias escolares frecuentes.
                                </p>
                                <p class="mb-0">
                                    La institución educativa carece de departamento de orientación escolar, por lo que la coordinadora sugirió buscar ayuda profesional externa. La madre reconoce la gravedad de la situación y manifiesta alta preocupación, sintiéndose sola e impotente ante la falta de colaboración del padre y la resistencia de su hijo. Recientemente encontró evidencia de consumo de marihuana en la mochila del adolescente, lo que aumentó su urgencia por buscar atención especializada para toda la familia.
                                </p>
                            </div>

                            <div class="mb-4 app-form-question">
                                <p class="fw-semibold mb-1">
                                    1. El consumo de sustancias psicoactivas de Andrés tiene su origen posiblemente en:
                                </p>
                                <div class="text-muted small mb-2">Seleccione una opción *</div>
                                <?php
                                $a1 = [
                                    'A' => 'La falta de competencias parentales en ambos padres',
                                    'B' => 'La Influencia normativa por parte de uno de los padres',
                                    'C' => 'La presión social por parte de lo pares',
                                    'D' => 'La ausencia de psicoeducación en el entorno escolar',
                                    'E' => 'La falta de oferta en la atención especializada en salud',
                                ];
                                foreach ($a1 as $value => $label): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q1" id="aq1_<?= $value ?>"
                                               value="<?= $value ?>" required>
                                        <label class="form-check-label" for="aq1_<?= $value ?>">
                                            <?= $value ?>. <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php
                            $adiccionesQuestions = [
                                2 => [
                                    'text' => 'Desde la oferta de la Secretaría de salud e Inclusión Social el programa de intervención que mejor puede beneficiar a la familia de Andrés es:',
                                    'options' => [
                                        'A' => 'Programa de Competencias Parentales',
                                        'B' => 'Dispositivos Comunitarios',
                                        'C' => 'Ecosistema de Salud Mental',
                                        'D' => 'SAFER',
                                        'E' => 'La Aventura de crecer',
                                    ],
                                ],
                                3 => [
                                    'text' => 'Desde los criterios diagnósticos del DSM previo a la versión 5.0 el consumo de sustancias psicoactivas del adolescente Andrés corresponde a:',
                                    'options' => [
                                        'A' => 'Uso',
                                        'B' => 'Abuso',
                                        'C' => 'Dependencia',
                                        'D' => 'Adicción',
                                        'E' => 'Tolerancia',
                                    ],
                                ],
                                4 => [
                                    'text' => '¿Cuál norma implementa la estrategia Nacional de Alcohol y el Sistema Nacional de Atención al consumo de sustancias psicoactivas?',
                                    'options' => [
                                        'A' => 'Ley 2518',
                                        'B' => 'Ley 1616',
                                        'C' => 'Resolución 2100',
                                        'D' => 'Ley 729',
                                        'E' => 'Ley 2460',
                                    ],
                                ],
                                5 => [
                                    'text' => 'Según lo establecido por la política, ¿Cuál de las siguientes combinaciones refleja de manera más completa y coherente el carácter transversal de la Reducción de Riesgos y Daños dentro del sistema de salud pública?',
                                    'options' => [
                                        'A' => 'La integración de programas de sensibilización sobre el uso nocivo de sustancias psicoactivas',
                                        'B' => 'La incorporación simultánea de mantenimiento con agonistas opioides, análisis de sustancias, distribución de naloxona, dispositivos de consumo supervisado y acciones comunitarias contra el estigma, como componentes articulados que actúan en distintos niveles del sistema.',
                                        'C' => 'La realización de tamizajes para la detección temprana de trastornos por uso de sustancias psicoactivas',
                                        'D' => 'El fortalecimiento de acciones comunitarias de sensibilización como eje único de la política, dejando en segundo plano las intervenciones clínicas y de reducción de daños químico-biológicos.',
                                        'E' => 'Ninguna de las anteriores',
                                    ],
                                ],
                                6 => [
                                    'text' => 'En una consulta del centro de escucha, un hombre de 38 años refiere que “sale a tomar con amigos todos los fines de semana” y admite “beber bastante”. No hay signos físicos graves. ¿Cuál sería la mejor estrategia para detectar consumo riesgoso en este entorno?',
                                    'options' => [
                                        'A' => 'Aplicar un cuestionario breve de tamizaje, como el DAST-10',
                                        'B' => 'Aplicar un cuestionario breve de tamizaje, como el AUDIT-C.',
                                        'C' => 'Aplicar un cuestionario breve de tamizaje, como el Fagerstrom.',
                                        'D' => 'Esperar a que el paciente consulte por un problema concreto.',
                                        'E' => 'Enviar a urgencias de inmediato por alto riesgo de abstinencia.',
                                    ],
                                ],
                                7 => [
                                    'text' => 'Si se identifica un consumo riesgoso de alcohol, ¿Qué intervención inicial puede ofrecer un profesional en atención primaria de salud mental?',
                                    'options' => [
                                        'A' => 'Informar los riesgos sin indagar motivación para cambiar.',
                                        'B' => 'Dar una “charla moral” enfatizando consecuencias graves.',
                                        'C' => 'Realizar una breve entrevista motivacional, explorar su disposición al cambio y proponer pasos pequeños (por ejemplo, reducir frecuencia).',
                                        'D' => 'Exigir abandono inmediato del alcohol sin acompañamiento.',
                                        'E' => 'Recomendar medicación reconocida de manera ambulatoria e iniciar desmonte.',
                                    ],
                                ],
                                8 => [
                                    'text' => 'La estrategia internacional SAFER propende por las siguientes intervenciones, excepto.',
                                    'options' => [
                                        'A' => 'FORTALECER las restricciones sobre la disponibilidad de alcohol.',
                                        'B' => 'IMPULSAR y hacer cumplir las medidas de lucha contra el consumo de alcohol.',
                                        'C' => 'CREAR programas de prevención para mitigar el daño del alcohol',
                                        'D' => 'HACER CUMPLIR las medidas de prohibición o restricción con respecto a la publicidad, el patrocinio y la promoción del alcohol.',
                                        'E' => 'AUMENTAR los precios del alcohol a través de impuestos al consumo y políticas de precios.',
                                    ],
                                ],
                                9 => [
                                    'text' => 'En la Sentencia C-127 de  2023, la Corte Constitucional se pronunció sobre la relación con el porte y consumo de sustancias psicoactivas en parques y espacios públicos. ¿Cuál fue la decisión principal adoptada por la Corte en esta sentencia?',
                                    'options' => [
                                        'A' => 'La Corte declaró inexequibles todas las restricciones al consumo de sustancias por considerar que vulneran el libre desarrollo de la personalidad sin excepciones.',
                                        'B' => 'La Corte mantuvo la restricción del consumo de sustancias psicoactivas, incluso la dosis mínima, en parques y espacios públicos ordenando al Gobierno expedir un protocolo de aplicación.',
                                        'C' => 'La Corte se declaró inhibida para pronunciarse sobre el fondo del asunto al considerar la cosa juzgada.',
                                        'D' => 'La Corte permitió el consumo, pero prohibió de manera absoluta el porte de sustancias psicoactivas en cualquier cantidad en parques y espacios públicos.',
                                        'E' => 'La Corte declaró que las autoridades municipales no tienen competencia para regular el consumo de sustancias psicoactivas en espacios públicos.',
                                    ],
                                ],
                            ];
                            foreach ($adiccionesQuestions as $number => $question): ?>
                                <div class="mb-4 app-form-question">
                                    <p class="fw-semibold mb-1">
                                        <?= $number ?>. <?= htmlspecialchars((string) $question['text'], ENT_QUOTES, 'UTF-8') ?>
                                    </p>
                                    <div class="text-muted small mb-2">Seleccione una opción *</div>
                                    <?php foreach ($question['options'] as $value => $label): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="q<?= $number ?>" id="aq<?= $number ?>_<?= $value ?>"
                                                   value="<?= $value ?>" required>
                                            <label class="form-check-label" for="aq<?= $number ?>_<?= $value ?>">
                                                <?= $value ?>. <?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php elseif ($config['key'] === 'hospitales' && ($config['phase'] === 'pre' || $config['phase'] === 'post')): ?>
                            <div class="mb-4 app-form-question">
                                <p class="fw-semibold mb-1">
                                    1. Llega a urgencias un joven de 22 años con antecedente de epilepsia, debido a que se le acabó la
                                    medicación y requiere reformulación, durante la atención en triage presenta pérdida súbita del tono
                                    postural, movimientos tónico-clónicos generalizados y supraversión de la mirada. ¿Qué debe hacer el
                                    personal de atención?
                                </p>
                                <div class="text-muted small mb-2">Seleccione una opción *</div>
                                <?php
                                $h1 = [
                                    'A' => 'Sujetarlo firmemente para evitar movimientos.',
                                    'B' => 'Colocar algo entre sus dientes para prevenir mordeduras.',
                                    'C' => 'Retirar objetos peligrosos alrededor, proteger la cabeza, mantener vía aérea y observar respiración.',
                                    'D' => 'Administrar sedantes inmediatamente para realizar intubación orotraqueal.',
                                    'E' => 'Remitir a valoración urgente por neurología',
                                ];
                                foreach ($h1 as $value => $label): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q1" id="hq1_<?= $value ?>"
                                               value="<?= $value ?>" required>
                                        <label class="form-check-label" for="hq1_<?= $value ?>">
                                            <?= $value ?>. <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mb-4 app-form-question">
                                <p class="fw-semibold mb-1">
                                    2. Si se identifica un consumo riesgoso de alcohol, ¿Qué intervención inicial puede ofrecer un
                                    profesional en primer nivel de atención?
                                </p>
                                <div class="text-muted small mb-2">Seleccione una opción *</div>
                                <?php
                                $h2 = [
                                    'A' => 'Informar los riesgos sin indagar motivación para cambiar.',
                                    'B' => 'Dar una “charla moral” enfatizando consecuencias graves.',
                                    'C' => 'Realizar una breve entrevista motivacional.',
                                    'D' => 'Exigir abandono inmediato del alcohol sin acompañamiento.',
                                    'E' => 'Prescribir benzodiacepinas de manera ambulatoria e iniciar desmonte.',
                                ];
                                foreach ($h2 as $value => $label): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q2" id="hq2_<?= $value ?>"
                                               value="<?= $value ?>" required>
                                        <label class="form-check-label" for="hq2_<?= $value ?>">
                                            <?= $value ?>. <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mb-4 app-form-question">
                                <p class="fw-semibold mb-1">
                                    3. Una mujer de 30 años fuma un paquete diario desde hace 10 años. Tras un abordaje inicial, expresa
                                    que en algún momento quisiera dejar de fumar. ¿Qué estrategia breve puede ofrecer el profesional del
                                    primer nivel para iniciar cesación?
                                </p>
                                <div class="text-muted small mb-2">Seleccione una opción *</div>
                                <?php
                                $h3 = [
                                    'A' => 'Sugerir que aumente cigarrillos antes de intentar dejar.',
                                    'B' => 'Iniciar estrategia de las 5 A.',
                                    'C' => 'Inciar estrategia de las 5 R.',
                                    'D' => 'Decirle que la cesación debe hacerse solo en niveles especializados.',
                                    'E' => 'Prescribir benzodiacepinas.',
                                ];
                                foreach ($h3 as $value => $label): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q3" id="hq3_<?= $value ?>"
                                               value="<?= $value ?>" required>
                                        <label class="form-check-label" for="hq3_<?= $value ?>">
                                            <?= $value ?>. <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mb-4 app-form-question">
                                <p class="fw-semibold mb-1">
                                    4. En urgencias llega un paciente claramente alterado, gritando, con postura tensa, gestos bruscos y
                                    desorientación. ¿Cuál es la primera estrategia recomendada para calmar la situación?
                                </p>
                                <div class="text-muted small mb-2">Seleccione una opción *</div>
                                <?php
                                $h4 = [
                                    'A' => 'Gritar órdenes firmes para que se calme.',
                                    'B' => 'Realizar contención farmacológicamente con benzodiacepinas, para realizar sujeción mecánica y poder realizar el interrogatorio',
                                    'C' => 'Mantener distancia segura, hablar con tono calmado y claro, mostrar empatía, iniciar desescalada verbal',
                                    'D' => 'Sujetarlo físicamente de inmediato.',
                                    'E' => 'Contener farmacológicamente como primera opción.',
                                ];
                                foreach ($h4 as $value => $label): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q4" id="hq4_<?= $value ?>"
                                               value="<?= $value ?>" required>
                                        <label class="form-check-label" for="hq4_<?= $value ?>">
                                            <?= $value ?>. <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mb-4 app-form-question">
                                <p class="fw-semibold mb-1">
                                    5. Una mujer de 20 años acude acompañada al servicio de urgencias debido a ingesta de 4 tabletas de
                                    ibuprofeno con intencionalidad suicida. Se encuentra emocionalmente desbordada, reconoce malestar
                                    intenso, pero en este momento está tranquila y accesible. En la evaluación inicial de TRIAGE no
                                    presenta lesiones que comprometan su vida y sus signos vitales son normales. ¿Cuál debe ser la
                                    conducta en el primer nivel de atención?
                                </p>
                                <div class="text-muted small mb-2">Seleccione una opción *</div>
                                <?php
                                $h5 = [
                                    'A' => 'Considerar triage 5 porque no tiene criterios de manejo en urgencias',
                                    'B' => 'Realizar lavado gástrico y dejar en observación.',
                                    'C' => 'Ingresar para manejo en servicio de urgencias y activar ruta de atención.',
                                    'D' => 'Derivar a cita prioritaria.',
                                    'E' => 'Dar recomendaciones generales y permitir que se retire sola.',
                                ];
                                foreach ($h5 as $value => $label): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q5" id="hq5_<?= $value ?>"
                                               value="<?= $value ?>" required>
                                        <label class="form-check-label" for="hq5_<?= $value ?>">
                                            <?= $value ?>. <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mb-4 app-form-question">
                                <p class="fw-semibold mb-1">
                                    6. Un hombre de 60 años con diagnóstico de cáncer de estómago en tratamiento paliativo consulta por
                                    dolor agudo muy intenso, descrito como 8/10 en la escala análoga del dolor. Su familiar informa que el
                                    paciente venía recibiendo morfina en casa formulada previamente, pero desde la noche anterior el dolor
                                    aumentó de manera marcada. Está consciente, orientado, y sin signos inmediatos de compromiso vital,
                                    pero visiblemente angustiado por el dolor. ¿Cuál es la conducta más adecuada en el primer nivel de
                                    atención?
                                </p>
                                <div class="text-muted small mb-2">Seleccione una opción *</div>
                                <?php
                                $h6 = [
                                    'A' => 'Decirle que continúe con la misma dosis en casa y esperar a que “le haga más efecto”.',
                                    'B' => 'Suspender completamente los analgésicos y observar la evolución durante varias horas.',
                                    'C' => 'Realizar analgésia escalonada, iniciando con acetaminofen y AINES orales',
                                    'D' => 'Iniciar opioides IV, con dosis equivalentes a dosificación oral manejada en casa, ajustar esquema según respuesta.',
                                    'E' => 'Iniciar con AINES parenterales y aumentar potencia analgésica según respuesta.',
                                ];
                                foreach ($h6 as $value => $label): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q6" id="hq6_<?= $value ?>"
                                               value="<?= $value ?>" required>
                                        <label class="form-check-label" for="hq6_<?= $value ?>">
                                            <?= $value ?>. <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mb-4 app-form-question">
                                <p class="fw-semibold mb-1">
                                    7. Paciente con síntomas depresivos leves, funcionalidad conservada y sin riesgo suicida. ¿Cuál es el
                                    manejo más adecuado?
                                </p>
                                <div class="text-muted small mb-2">Seleccione una opción *</div>
                                <?php
                                $h7 = [
                                    'A' => 'Hospitalización',
                                    'B' => 'Antidepresivo',
                                    'C' => 'Psicoterapia breve y seguimiento',
                                    'D' => 'Antipsicótico atípico',
                                    'E' => 'Terapia electroconvulsiva',
                                ];
                                foreach ($h7 as $value => $label): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q7" id="hq7_<?= $value ?>"
                                               value="<?= $value ?>" required>
                                        <label class="form-check-label" for="hq7_<?= $value ?>">
                                            <?= $value ?>. <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mb-4 app-form-question">
                                <p class="fw-semibold mb-1">
                                    8. En un paciente con diagnóstico de ansiedad leve, ¿Cuál es la primera línea de manejo en la baja
                                    complejidad?
                                </p>
                                <div class="text-muted small mb-2">Seleccione una opción *</div>
                                <?php
                                $h8 = [
                                    'A' => 'Benzodiacepinas de forma crónica',
                                    'B' => 'Antipsicóticos',
                                    'C' => 'Psicoeducación y técnicas de relajación',
                                    'D' => 'Hospitalización',
                                    'E' => 'Ansiolíticos como la difenhidramina',
                                ];
                                foreach ($h8 as $value => $label): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q8" id="hq8_<?= $value ?>"
                                               value="<?= $value ?>" required>
                                        <label class="form-check-label" for="hq8_<?= $value ?>">
                                            <?= $value ?>. <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mb-4 app-form-question">
                                <p class="fw-semibold mb-1">
                                    9. Niño de 8 años con inatención e hiperactividad que afectan su rendimiento escolar y la convivencia
                                    familiar. ¿Cuál criterio es indispensable para el diagnóstico?
                                </p>
                                <div class="text-muted small mb-2">Seleccione una opción *</div>
                                <?php
                                $h9 = [
                                    'A' => 'Inicio después de los 12 años',
                                    'B' => 'Síntomas solo en el hogar',
                                    'C' => 'Afectación en más de un entorno',
                                    'D' => 'Presencia obligatoria de agresividad',
                                    'E' => 'Uso previo de estimulantes',
                                ];
                                foreach ($h9 as $value => $label): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q9" id="hq9_<?= $value ?>"
                                               value="<?= $value ?>" required>
                                        <label class="form-check-label" for="hq9_<?= $value ?>">
                                            <?= $value ?>. <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mb-4 app-form-question">
                                <p class="fw-semibold mb-1">
                                    10. Paciente de 22 años con alucinaciones auditivas, ideas delirantes y deterioro funcional desde hace
                                    un mes. ¿Cuál es la conducta inicial más adecuada?
                                </p>
                                <div class="text-muted small mb-2">Seleccione una opción *</div>
                                <?php
                                $h10 = [
                                    'A' => 'Observación sin intervención',
                                    'B' => 'Iniciar antipsicótico',
                                    'C' => 'Psicoterapia',
                                    'D' => 'Tratar como ansiedad',
                                    'E' => 'Dar alta con seguimiento anual',
                                ];
                                foreach ($h10 as $value => $label): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q10" id="hq10_<?= $value ?>"
                                               value="<?= $value ?>" required>
                                        <label class="form-check-label" for="hq10_<?= $value ?>">
                                            <?= $value ?>. <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mb-4 app-form-question">
                                <p class="fw-semibold mb-1">
                                    11. ¿Qué conducta debe evitarse ante sospecha de trastorno bipolar?
                                </p>
                                <div class="text-muted small mb-2">Seleccione una opción *</div>
                                <?php
                                $h11 = [
                                    'A' => 'Remisión a psiquiatría',
                                    'B' => 'Psicoeducación al paciente',
                                    'C' => 'Uso de antidepresivo',
                                    'D' => 'Uso de estabilizador del estado de ánimo',
                                    'E' => 'Evaluación del riesgo',
                                ];
                                foreach ($h11 as $value => $label): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q11" id="hq11_<?= $value ?>"
                                               value="<?= $value ?>" required>
                                        <label class="form-check-label" for="hq11_<?= $value ?>">
                                            <?= $value ?>. <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mb-4 app-form-question">
                                <p class="fw-semibold mb-1">
                                    12. ¿Cuál es la primera línea de manejo del insomnio?
                                </p>
                                <div class="text-muted small mb-2">Seleccione una opción *</div>
                                <?php
                                $h12 = [
                                    'A' => 'Benzodiacepinas',
                                    'B' => 'Antipsicóticos',
                                    'C' => 'Hipnóticos de acción prolongada',
                                    'D' => 'Terapia no farmacológica.',
                                    'E' => 'Antihistamínicos',
                                ];
                                foreach ($h12 as $value => $label): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q12" id="hq12_<?= $value ?>"
                                               value="<?= $value ?>" required>
                                        <label class="form-check-label" for="hq12_<?= $value ?>">
                                            <?= $value ?>. <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mb-4 app-form-question">
                                <p class="fw-semibold mb-1">
                                    13. Paciente de 28 años consulta por ansiedad y tristeza. Durante la entrevista expresa que “a veces
                                    piensa que no valdría la pena seguir viviendo”, sin plan ni intento previo. Niega consumo de sustancias.
                                    ¿Cuál es la conducta correcta en el primer nivel?
                                </p>
                                <div class="text-muted small mb-2">Seleccione una opción *</div>
                                <?php
                                $h13 = [
                                    'A' => 'No profundizar para no inducir ideas',
                                    'B' => 'Evaluar riesgo suicida y explorar factores protectores',
                                    'C' => 'Hospitalizar inmediatamente',
                                    'D' => 'Minimizar la expresión por no haber plan',
                                    'E' => 'Remitir sin valoración',
                                ];
                                foreach ($h13 as $value => $label): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q13" id="hq13_<?= $value ?>"
                                               value="<?= $value ?>" required>
                                        <label class="form-check-label" for="hq13_<?= $value ?>">
                                            <?= $value ?>. <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mb-4 app-form-question">
                                <p class="fw-semibold mb-1">
                                    14. Paciente de 40 años, con ideación suicida y plan poco estructurado. Es valorado en el servicio de
                                    urgencias, durante la evaluación se identifica red de apoyo familiar activa y disposición a buscar
                                    ayuda. ¿Cómo se clasifica este hallazgo?
                                </p>
                                <div class="text-muted small mb-2">Seleccione una opción *</div>
                                <?php
                                $h14 = [
                                    'A' => 'Factor de riesgo',
                                    'B' => 'Factor precipitante',
                                    'C' => 'Factor contributivo',
                                    'D' => 'Factor de riesgo proximal',
                                    'E' => 'Factor protector',
                                ];
                                foreach ($h14 as $value => $label): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q14" id="hq14_<?= $value ?>"
                                               value="<?= $value ?>" required>
                                        <label class="form-check-label" for="hq14_<?= $value ?>">
                                            <?= $value ?>. <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mb-4 app-form-question">
                                <p class="fw-semibold mb-1">
                                    15. ¿Cuál es la herramienta de tamizaje que permite evaluar el riesgo de consumo de múltiples sustancias
                                    psicoactivas?
                                </p>
                                <div class="text-muted small mb-2">Seleccione una opción *</div>
                                <?php
                                $h15 = [
                                    'A' => 'AUDIT',
                                    'B' => 'CAGE',
                                    'C' => 'CIWA-Ar',
                                    'D' => 'ASSIST',
                                    'E' => 'APGAR Familiar',
                                ];
                                foreach ($h15 as $value => $label): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q15" id="hq15_<?= $value ?>"
                                               value="<?= $value ?>" required>
                                        <label class="form-check-label" for="hq15_<?= $value ?>">
                                            <?= $value ?>. <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mb-4 app-form-question">
                                <p class="fw-semibold mb-1">
                                    16. ¿Cuál es una velocidad de reducción gradual recomendada para pacientes con uso prolongado (≥1 año)
                                    de opioides?
                                </p>
                                <div class="text-muted small mb-2">Seleccione una opción *</div>
                                <?php
                                $h16 = [
                                    'A' => '10% por mes o más lento.',
                                    'B' => 'Detención abrupta (suspensión repentina).',
                                    'C' => 'Entre 10% al 20% cada mes.',
                                    'D' => '50% de la dosis original por semana.',
                                    'E' => '15% cada semana.',
                                ];
                                foreach ($h16 as $value => $label): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q16" id="hq16_<?= $value ?>"
                                               value="<?= $value ?>" required>
                                        <label class="form-check-label" for="hq16_<?= $value ?>">
                                            <?= $value ?>. <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mb-4 app-form-question">
                                <p class="fw-semibold mb-1">
                                    17. Según la guía ASAM, ¿Cuál es el ritmo de reducción de dosis inicial recomendado para el desmonte de
                                    benzodiacepinas en un paciente con dependencia física?
                                </p>
                                <div class="text-muted small mb-2">Seleccione una opción *</div>
                                <?php
                                $h17 = [
                                    'A' => 'Reducción del 5% al 10% de la dosis actual cada 2 a 4 semanas.',
                                    'B' => 'Reducción del 50% de la dosis cada mes.',
                                    'C' => 'Interrupción abrupta si el paciente se siente listo.',
                                    'D' => 'Reducción del 25% de la dosis cada semana.',
                                    'E' => 'Reducción del 15% al 20% cada 15 días.',
                                ];
                                foreach ($h17 as $value => $label): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q17" id="hq17_<?= $value ?>"
                                               value="<?= $value ?>" required>
                                        <label class="form-check-label" for="hq17_<?= $value ?>">
                                            <?= $value ?>. <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mb-4 app-form-question">
                                <p class="fw-semibold mb-1">
                                    18. Paciente de 62 años es llevado a urgencias por su familia por somnolencia marcada, dificultad para
                                    articular palabras y marcha inestable. Tiene antecedente de ansiedad crónica y se encuentra en
                                    tratamiento con clonazepam formulado hace varios meses. No se conoce la dosis ingerida. Signos vitales
                                    estables, respiración conservada. ¿Cuál debe ser la prioridad inicial del manejo en este escenario?
                                </p>
                                <div class="text-muted small mb-2">Seleccione una opción *</div>
                                <?php
                                $h18 = [
                                    'A' => 'Administrar flumazenil de forma inmediata',
                                    'B' => 'Realizar lavado gástrico',
                                    'C' => 'Evaluar y asegurar la vía aérea, respiración y circulación',
                                    'D' => 'Solicitar niveles séricos del medicamento',
                                    'E' => 'Evaluar riesgo de suicidio para definir criterios de remisión',
                                ];
                                foreach ($h18 as $value => $label): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q18" id="hq18_<?= $value ?>"
                                               value="<?= $value ?>" required>
                                        <label class="form-check-label" for="hq18_<?= $value ?>">
                                            <?= $value ?>. <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mb-4 app-form-question">
                                <p class="fw-semibold mb-1">
                                    19. Paciente de 42 años es llevado a urgencias por la policía tras presentar conducta alterada en vía
                                    pública. A la llegada se muestra irritable, con aumento del tono de voz, inquietud motora y dificultad
                                    para mantener la atención. Niega consumo de sustancias, pero el acompañante refiere ingesta reciente de
                                    alcohol. No hay signos de trauma ni compromiso neurológico evidente. Desde el primer nivel de atención,
                                    ¿Cuál debe ser el enfoque inicial más adecuado?
                                </p>
                                <div class="text-muted small mb-2">Seleccione una opción *</div>
                                <?php
                                $h19 = [
                                    'A' => 'Asumir que se trata de un trastorno psiquiátrico primario',
                                    'B' => 'Priorizar la evaluación médica para descartar causas orgánicas o tóxicas',
                                    'C' => 'Iniciar antipsicótico intramuscular de forma inmediata',
                                    'D' => 'Aplicar contención física preventiva',
                                    'E' => 'Dar alta sin intervención hasta que ceda la agitación',
                                ];
                                foreach ($h19 as $value => $label): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q19" id="hq19_<?= $value ?>"
                                               value="<?= $value ?>" required>
                                        <label class="form-check-label" for="hq19_<?= $value ?>">
                                            <?= $value ?>. <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mb-4 app-form-question">
                                <p class="fw-semibold mb-1">
                                    20. Durante una consulta en atención primaria, un paciente de 24 años expresa que en semanas recientes
                                    ha tenido pensamientos de hacerse daño, pero aclara que no tiene un plan definido. Un profesional en
                                    formación comenta: “Es mejor no preguntar mucho por suicidio, porque hablar del tema puede inducir la
                                    conducta”. ¿Cuál de las siguientes afirmaciones es correcta?
                                </p>
                                <div class="text-muted small mb-2">Seleccione una opción *</div>
                                <?php
                                $h20 = [
                                    'A' => 'Hablar sobre suicidio puede aumentar la probabilidad de que el paciente intente hacerse daño',
                                    'B' => 'Solo se debe indagar por suicidio cuando el paciente lo menciona de forma explícita',
                                    'C' => 'Preguntar de manera directa y respetuosa por ideación suicida permite identificar riesgo y no incrementa la conducta suicida',
                                    'D' => 'Indagar por suicidio debe evitarse en atención primaria y dejarse solo a especialistas',
                                    'E' => 'Explorar ideación suicida genera dependencia emocional del paciente hacia el profesional',
                                ];
                                foreach ($h20 as $value => $label): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q20" id="hq20_<?= $value ?>"
                                               value="<?= $value ?>" required>
                                        <label class="form-check-label" for="hq20_<?= $value ?>">
                                            <?= $value ?>. <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                Pronto configuraremos las preguntas específicas para este test.
                            </div>
                        <?php endif; ?>
                        </div>

                        <div class="app-form-submit">
                            <button type="submit" class="btn btn-primary btn-lg px-4">
                                Enviar respuestas
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

