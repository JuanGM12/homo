<?php

declare(strict_types=1);

/**
 * Banco de enunciados y opciones (alineado con app/Views/evaluaciones/form.php).
 */
return array_merge(
    require __DIR__ . '/EvaluacionesQuestions/violencias.php',
    require __DIR__ . '/EvaluacionesQuestions/suicidios.php',
    require __DIR__ . '/EvaluacionesQuestions/adicciones.php',
    require __DIR__ . '/EvaluacionesQuestions/hospitales.php',
);
