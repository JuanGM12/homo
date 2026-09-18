<?php

declare(strict_types=1);

/**
 * Enunciados de cualificación por rol (misma fuente para AoAT y Plan de Entrenamiento).
 *
 * @return array<string, array<string, mixed>>
 */
$option = static function (string $value, ?string $label = null): array {
    return ['value' => $value, 'label' => $label ?? $value];
};

return [
    'abogado' => [
        'heading' => 'Temas de Política Pública en Salud Mental (Abogado)',
        'intro' => 'Marca todos los módulos que trabajaste durante esta AoAT. Puedes seleccionar varias opciones.',
        'show_otro_caso' => true,
        'sections' => [
            [
                'key' => 'mesa_salud_mental',
                'title' => 'Actualización de la Mesa Municipal de Salud Mental y Prevención de las Adicciones',
                'type' => 'checkbox',
                'required' => true,
                'hint' => 'Selección múltiple',
                'option_col' => 'col-md-6 col-lg-4',
                'options' => [
                    $option('Módulo 1', 'Módulo 1: Conformación y fortalecimiento de la mesa.'),
                    $option('Módulo 2', 'Módulo 2: Secretaría técnica, reglamento y plan de acción.'),
                    $option('Módulo 3', 'Módulo 3: Convocatoria para conformar la mesa.'),
                    $option('No aplica'),
                ],
            ],
            [
                'key' => 'ppmsmypa',
                'title' => 'Actualización de la Política Pública Municipal de Salud y Prevención de las Adicciones (PPMSMYPA)',
                'type' => 'checkbox',
                'required' => true,
                'hint' => 'Selección múltiple',
                'option_col' => 'col-md-6 col-lg-6',
                'options' => [
                    $option('Módulo 4', 'Módulo 4: Actualización de la política pública municipal de salud mental y prevención de las adicciones – ciclo agenda.'),
                    $option('Módulo 5', 'Módulo 5: Actualización de la política pública municipal de salud mental y prevención de las adicciones - ciclo formulación de la política pública de salud mental.'),
                    $option('No aplica'),
                ],
            ],
            [
                'key' => 'safer',
                'title' => 'SAFER',
                'type' => 'checkbox',
                'required' => true,
                'hint' => 'Selección múltiple',
                'option_col' => 'col-md-6 col-lg-4',
                'options' => [
                    $option('Módulo 1', 'Módulo 1: Socialización de la problemática pública del alcohol, generalidad estrategia SAFER, legislación actual.'),
                    $option('Módulo 2', 'Módulo 2: Socialización de la problemática pública del alcohol, generalidad estrategia SAFER, legislación actual.'),
                    $option('Módulo 3', 'Módulo 3: Legislación actual con énfasis en consumo de menores y mujeres en estado de gestación, socialización de la problemática pública del alcohol, violencias relacionadas por el alcohol.'),
                    $option('Módulo 4', 'Módulo 4: Legislación actual con énfasis en consumo de menores y mujeres en estado de gestación, socialización de la problemática pública del alcohol.'),
                    $option('Módulo 5', 'Módulo 5: Socialización de la problemática pública del alcohol, responsabilidad civil y penal.'),
                    $option('No aplica'),
                ],
            ],
        ],
    ],
    'politologo' => [
        'heading' => 'Cualificación de temas (Politólogo)',
        'intro' => 'Esta cualificación queda seleccionada automáticamente para los registros de este rol.',
        'show_otro_caso' => true,
        'sections' => [
            [
                'key' => 'ppmsmypa',
                'title' => 'Cualificación de temas',
                'type' => 'locked',
                'required' => false,
                'hint' => 'Selección automática',
                'option_col' => 'col-12',
                'options' => [
                    $option(
                        'Actualización de la Política Pública Municipal de Salud y Prevención de las Adicciones (PPMSMYPA)'
                    ),
                ],
            ],
        ],
    ],
    'medico' => [
        'heading' => 'Temas dictados en el Hospital del municipio visitado (Médico)',
        'intro' => 'Selecciona todos los temas que trabajaste en esta actividad. Es selección múltiple.',
        'show_otro_caso' => true,
        'sections' => [
            [
                'key' => 'temas_hospital',
                'title' => 'Seleccione el/los temas que dictó en el Hospital del municipio visitado',
                'type' => 'checkbox',
                'required' => true,
                'hint' => 'Selección múltiple',
                'option_col' => 'col-md-6 col-lg-4',
                'options' => [
                    $option('Abordaje del manejo de alcohol en el primer nivel de atención – Alcohol y embarazo.'),
                    $option('Abordaje del manejo de tabaco en el primer nivel.'),
                    $option('Adicciones en la baja complejidad'),
                    $option('Conducta suicida'),
                    $option('Desmonte de benzodiacepinas'),
                    $option('Desmonte de opioides'),
                    $option('Epilepsia'),
                    $option('Intoxicaciones por medicamentos de control'),
                    $option('Manejo del dolor'),
                    $option('Paciente agitado'),
                    $option('Pre Test'),
                    $option('Post Test'),
                    $option('Resolución 347 de 2026'),
                    $option('Trastorno Afectivo Bipolar'),
                    $option('Trastorno de Déficit de Atención e Hiperactividad'),
                    $option('Trastorno Depresivo'),
                    $option('Trastorno Psicótico'),
                    $option('Trastornos de Ansiedad'),
                    $option('Trastornos del sueño'),
                ],
            ],
            [
                'key' => 'espacios_participacion_medico',
                'title' => 'Espacios de participación',
                'type' => 'radio',
                'required' => true,
                'hint' => 'Selección única',
                'option_col' => 'col-md-6 col-lg-4',
                'options' => [
                    $option('COVE'),
                    $option('Mesa de salud Mental'),
                    $option('Eventos'),
                ],
            ],
        ],
    ],
    'psicologo' => [
        'heading' => 'Cualificación de temas (Psicólogo)',
        'intro' => 'Selecciona los temas que trabajaste en esta AoAT. Algunas preguntas son de selección múltiple y otras de selección única.',
        'show_otro_caso' => true,
        'sections' => [
            [
                'key' => 'prev_suicidio',
                'title' => 'Cualificación temas en prevención del suicidio',
                'type' => 'checkbox',
                'required' => true,
                'hint' => 'Selección múltiple',
                'option_col' => 'col-12',
                'options' => [
                    $option('Módulo 1', 'Módulo 1: Evolución histórica del suicidio, aproximación conceptual de la conducta suicida, teorías explicativas de primera generación, teorías explicativas de segunda generación, factores de riesgo (biológicos, psiquiátricos, psicológicos y sociales), factores de protección, señales de alarma, ruta de atención y articulación intersectorial, notificación y seguimiento, plan de seguridad.'),
                    $option('Módulo 2', 'Módulo 2: Comunicación y suicidio como factor de riesgo y de protección, impacto del lenguaje y los mensajes, efecto Werther, efecto Papageno, principios de la comunicación responsable, recomendaciones de la OMS para medios y contextos comunitarios, pautas de lo que se debe y no se debe comunicar, aplicación del efecto Papageno en contextos comunitarios e institucionales, roles y responsabilidades de actores clave, poder de la narrativa y reducción del estigma, recursos y guías para la comunicación responsable.'),
                    $option('Módulo 3', 'Módulo 3: Concepto y alcances de la posvención, posvención como estrategia de prevención y salud pública, impacto psicosocial del suicidio, duelo por suicidio y sus particularidades, duelo y tamizajes para suicidio (RQC, SRQ, Whooley, GAD-2, Zarit, Plutchick, PHQ-9, C-SSRS), estigma y silencios, principios orientadores de la posvención, acciones de posvención en el territorio, acompañamiento a familias e instituciones, comunicación posterior a una muerte por suicidio, identificación y seguimiento de personas en riesgo, articulación con servicios de salud mental, autocuidado del profesional psicosocial.'),
                    $option('Resolución 347 - Código Dorado'),
                    $option('No aplica'),
                ],
            ],
            [
                'key' => 'prev_violencias',
                'title' => 'Cualificación temas en prevención de Violencias',
                'type' => 'checkbox',
                'required' => true,
                'hint' => 'Selección múltiple',
                'option_col' => 'col-12',
                'options' => [
                    $option('Módulo 1', 'Módulo 1: Definición, marco normativo, epidemiología, tipología, características.'),
                    $option('Módulo 2', 'Módulo 2: Violencias interpersonales, violencia familiar y de pareja, violencia comunitaria, violencia juvenil, bullying.'),
                    $option('Módulo 3', 'Módulo 3: Modelos de prevención de las violencias interpersonales (prevención universal, selectiva, indicada y de recurrencias), programas basados en la evidencia para la prevención de las violencias (modelo INSPIRE, modelo RESPETO y otros).'),
                    $option('No aplica'),
                ],
            ],
            [
                'key' => 'prev_adicciones',
                'title' => 'Cualificación temas en prevención de Adicciones',
                'type' => 'checkbox',
                'required' => true,
                'hint' => 'Selección múltiple',
                'option_col' => 'col-12',
                'options' => [
                    $option('Módulo 1', 'Módulo 1: Modelos explicativos (biopsicosocial, aprendizaje y condicionamiento), neurobiología de las adicciones, determinantes sociales, factores de riesgo y de protección, prevención basada en evidencia, influencia normativa.'),
                    $option('Módulo 2', 'Módulo 2: Comprensión de las adicciones según tipo de sustancia, dependencias comportamentales (juego patológico, nomofobia, juegos electrónicos, oniomanía, adicción al trabajo, vigorexia), cigarrillos electrónicos, cannabis, patología dual.'),
                    $option('Módulo 3', 'Módulo 3: Rutas de atención, tamizajes (ASSIST, AUDIT, CRAFFT, Fagerström), intervenciones (entrevista motivacional, intervención única, mindfulness), grupos de apoyo, reducción de riesgos y daños.'),
                    $option('No aplica'),
                ],
            ],
            [
                'key' => 'salud_mental',
                'title' => 'Cualificación temas de Salud Mental',
                'type' => 'checkbox',
                'required' => true,
                'hint' => 'Selección múltiple',
                'option_col' => 'col-md-6 col-lg-4',
                'options' => [
                    $option('Cuidado al cuidador'),
                    $option('Cuidado del profesional – burnout'),
                    $option('Estigma'),
                    $option('Grupos de apoyo y ayuda mutua (violencias, SPA, suicidio): teoría y conformación'),
                    $option('Primeros auxilios psicológicos e intervención en crisis'),
                    $option('Trastornos mentales prioritarios de interés en salud pública'),
                    $option('No aplica'),
                ],
            ],
            [
                'key' => 'politica_publica_psicologo',
                'title' => 'Actualización de la Política Pública Municipal de Salud y Prevención de las Adicciones (PPMSMYPA)',
                'type' => 'checkbox',
                'required' => true,
                'hint' => 'Selección múltiple',
                'option_col' => 'col-md-6 col-lg-4',
                'options' => [
                    $option('Actualización de la Política Pública Municipal de Salud y Prevención de las Adicciones (PPMSMYPA)'),
                    $option('Mesa de Salud Mental'),
                    $option('COVE'),
                    $option('No aplica'),
                ],
            ],
            [
                'key' => 'proyecto',
                'title' => 'Proyectos',
                'type' => 'radio',
                'required' => true,
                'hint' => 'Selección única (obligatoria)',
                'option_col' => 'col-md-6 col-lg-4',
                'options' => [
                    $option('Competencias Parentales'),
                    $option('Familias que se Cuidan'),
                    $option('La Aventura de Crecer'),
                    $option('Veredas que se Cuidan'),
                    $option('Dispositivos comunitarios'),
                    $option('Presentación del programa salud para el alma'),
                    $option('SAFER'),
                    $option('No aplica'),
                ],
            ],
        ],
    ],
    'profesional social' => [
        'heading' => 'Actividades realizadas (Profesional Social)',
        'intro' => 'Selecciona la(s) actividad(es) que realizaste en esta AoAT. Es selección múltiple.',
        'show_otro_caso' => true,
        'sections' => [
            [
                'key' => 'actividad_social',
                'title' => 'Seleccione la actividad realizada',
                'type' => 'checkbox',
                'required' => true,
                'hint' => 'Selección múltiple',
                'option_col' => 'col-md-6 col-lg-4',
                'options' => [
                    $option('Formación (desarrollo de capacidades)'),
                    $option('Espacio de articulación'),
                    $option('Actividad de apoyo'),
                ],
            ],
        ],
    ],
];
