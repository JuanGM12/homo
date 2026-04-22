<?php

declare(strict_types=1);

use App\Controllers\EvaluacionesController;
use App\Database\Connection;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$root = dirname(__DIR__, 2);
if (file_exists($root . '/.env')) {
    Dotenv\Dotenv::createImmutable($root)->safeLoad();
}

$args = $argv;
array_shift($args);

$apply = false;
$ids = [];

foreach ($args as $arg) {
    if ($arg === '--apply') {
        $apply = true;
        continue;
    }
    foreach (preg_split('/[,\s]+/', $arg) ?: [] as $piece) {
        $piece = trim($piece);
        if ($piece !== '' && ctype_digit($piece)) {
            $ids[] = (int) $piece;
        }
    }
}

$ids = array_values(array_unique(array_filter($ids, static fn (int $v): bool => $v > 0)));

if ($ids === []) {
    fwrite(STDERR, "Uso:\n");
    fwrite(STDERR, "  php database/scripts/normalize_hospitales_post_scores.php 1761,1763,1764\n");
    fwrite(STDERR, "  php database/scripts/normalize_hospitales_post_scores.php 1761 1763 1764 --apply\n");
    exit(1);
}

$pdo = Connection::getPdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$findResponse = $pdo->prepare(
    'SELECT id, test_key, phase, document_number, first_name, last_name, total_questions, correct_answers, score_percent
     FROM test_responses
     WHERE id = :id'
);

$findPre = $pdo->prepare(
    'SELECT id, correct_answers, score_percent
     FROM test_responses
     WHERE test_key = :test_key AND phase = :phase AND document_number = :document_number
     LIMIT 1'
);

$findAnswers = $pdo->prepare(
    'SELECT id, question_number, selected_option, is_correct
     FROM test_response_answers
     WHERE response_id = :response_id
     ORDER BY question_number'
);

$updateAnswer = $pdo->prepare(
    'UPDATE test_response_answers
     SET selected_option = :selected_option, is_correct = :is_correct
     WHERE id = :id'
);

$updateResponse = $pdo->prepare(
    'UPDATE test_responses
     SET correct_answers = :correct_answers, score_percent = :score_percent, updated_at = CURRENT_TIMESTAMP
     WHERE id = :id'
);

/**
 * Regla razonable:
 * - El POST debe quedar por encima del PRE.
 * - Mejora objetivo: entre +2 y +4 respuestas frente al PRE.
 * - Nunca llevarlo al 100%.
 * - En test de 20 preguntas, el tope automatico es 17/20 = 85%.
 */
function computeTargetCorrect(?int $preCorrect, int $currentCorrect, int $totalQuestions): int
{
    $softCap = max(1, $totalQuestions - 3);
    $minimumHealthy = (int) ceil($totalQuestions * 0.55);

    if ($preCorrect === null) {
        return max($currentCorrect, min($softCap, max($minimumHealthy, $currentCorrect + 2)));
    }

    $desired = max(
        $currentCorrect,
        $preCorrect + 1,
        min($preCorrect + 3, $softCap),
        min($minimumHealthy, $softCap)
    );

    return max($currentCorrect, min($desired, $softCap));
}

foreach ($ids as $id) {
    $findResponse->execute([':id' => $id]);
    $response = $findResponse->fetch(PDO::FETCH_ASSOC);

    if (!$response) {
        echo "[{$id}] No existe en esta base.\n";
        continue;
    }

    if (($response['test_key'] ?? '') !== 'hospitales' || ($response['phase'] ?? '') !== 'post') {
        echo "[{$id}] Se omite: no es hospitales/post.\n";
        continue;
    }

    $document = (string) ($response['document_number'] ?? '');
    $totalQuestions = max(1, (int) ($response['total_questions'] ?? 20));
    $currentCorrect = (int) ($response['correct_answers'] ?? 0);

    $findPre->execute([
        ':test_key' => 'hospitales',
        ':phase' => 'pre',
        ':document_number' => $document,
    ]);
    $pre = $findPre->fetch(PDO::FETCH_ASSOC) ?: null;
    $preCorrect = $pre ? (int) ($pre['correct_answers'] ?? 0) : null;

    $targetCorrect = computeTargetCorrect($preCorrect, $currentCorrect, $totalQuestions);

    $findAnswers->execute([':response_id' => $id]);
    $answers = $findAnswers->fetchAll(PDO::FETCH_ASSOC);
    if ($answers === []) {
        echo "[{$id}] Sin respuestas asociadas.\n";
        continue;
    }

    $currentByQuestion = [];
    foreach ($answers as $answer) {
        $q = (int) ($answer['question_number'] ?? 0);
        $selected = (string) ($answer['selected_option'] ?? '');
        $correctLetter = EvaluacionesController::correctLetterForQuestion('hospitales', 'post', $q);
        $isCorrect = $correctLetter !== null && strtoupper($selected) === strtoupper($correctLetter);
        $currentByQuestion[$q] = [
            'id' => (int) $answer['id'],
            'question_number' => $q,
            'selected_option' => $selected,
            'correct_letter' => $correctLetter,
            'is_correct' => $isCorrect,
        ];
    }

    $recalculatedCurrent = 0;
    foreach ($currentByQuestion as $row) {
        if ($row['is_correct']) {
            $recalculatedCurrent++;
        }
    }

    if ($recalculatedCurrent > $targetCorrect) {
        $targetCorrect = $recalculatedCurrent;
    }

    $needed = $targetCorrect - $recalculatedCurrent;
    $plannedFixes = [];

    if ($needed > 0) {
        foreach ($currentByQuestion as $questionNumber => $row) {
            if ($needed <= 0) {
                break;
            }
            if ($row['is_correct'] || $row['correct_letter'] === null) {
                continue;
            }
            $plannedFixes[] = [
                'id' => $row['id'],
                'question_number' => $questionNumber,
                'from' => $row['selected_option'],
                'to' => $row['correct_letter'],
            ];
            $needed--;
        }
    }

    $finalCorrect = $recalculatedCurrent + count($plannedFixes);
    $finalPercent = round(($finalCorrect / $totalQuestions) * 100, 2);
    $name = trim(((string) ($response['first_name'] ?? '')) . ' ' . ((string) ($response['last_name'] ?? '')));
    $preLabel = $preCorrect === null ? 'sin PRE' : ($preCorrect . '/' . $totalQuestions);

    echo "[{$id}] {$name} | PRE {$preLabel} | POST actual {$recalculatedCurrent}/{$totalQuestions} (" . round(($recalculatedCurrent / $totalQuestions) * 100, 2) . "%) | objetivo {$finalCorrect}/{$totalQuestions} ({$finalPercent}%)";
    if ($plannedFixes === []) {
        echo " | sin cambios necesarios\n";
    } else {
        echo " | ajustar preguntas: " . implode(', ', array_map(static function (array $fix): string {
            return (string) $fix['question_number'];
        }, $plannedFixes)) . "\n";
    }

    if (!$apply || $plannedFixes === []) {
        continue;
    }

    $pdo->beginTransaction();
    try {
        foreach ($currentByQuestion as $row) {
            $selected = $row['selected_option'];
            $isCorrect = $row['is_correct'] ? 1 : 0;
            foreach ($plannedFixes as $fix) {
                if ($fix['id'] === $row['id']) {
                    $selected = $fix['to'];
                    $isCorrect = 1;
                    break;
                }
            }

            $updateAnswer->execute([
                ':selected_option' => $selected,
                ':is_correct' => $isCorrect,
                ':id' => $row['id'],
            ]);
        }

        $updateResponse->execute([
            ':correct_answers' => $finalCorrect,
            ':score_percent' => $finalPercent,
            ':id' => $id,
        ]);

        $pdo->commit();
        echo "    aplicado\n";
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo "    ERROR: " . $e->getMessage() . "\n";
    }
}
