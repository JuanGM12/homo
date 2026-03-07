<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;
use PDO;
use PDOException;

final class TestResponseRepository
{
    public function create(array $data, array $answers): int
    {
        $pdo = Connection::getPdo();
        $pdo->beginTransaction();

        try {
            // Inserta en test_responses de forma dinámica
            $columns = array_keys($data);
            $placeholders = array_map(static fn(string $col): string => ':' . $col, $columns);

            $sql = sprintf(
                'INSERT INTO test_responses (%s) VALUES (%s)',
                implode(', ', $columns),
                implode(', ', $placeholders)
            );

            $stmt = $pdo->prepare($sql);
            foreach ($data as $column => $value) {
                $stmt->bindValue(':' . $column, $value);
            }
            $stmt->execute();

            $responseId = (int) $pdo->lastInsertId();

            // Inserta respuestas individuales
            if ($answers !== []) {
                $sqlAnswer = 'INSERT INTO test_response_answers 
                    (response_id, question_number, selected_option, is_correct)
                    VALUES (:response_id, :question_number, :selected_option, :is_correct)';

                $stmtAnswer = $pdo->prepare($sqlAnswer);

                foreach ($answers as $answer) {
                    $stmtAnswer->execute([
                        ':response_id' => $responseId,
                        ':question_number' => $answer['question_number'],
                        ':selected_option' => $answer['selected_option'],
                        ':is_correct' => $answer['is_correct'],
                    ]);
                }
            }

            $pdo->commit();

            return $responseId;
        } catch (PDOException $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function existsForPerson(string $testKey, string $phase, string $documentNumber): bool
    {
        $pdo = Connection::getPdo();

        $sql = 'SELECT 1 FROM test_responses 
                WHERE test_key = :test_key AND phase = :phase AND document_number = :document_number
                LIMIT 1';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':test_key' => $testKey,
            ':phase' => $phase,
            ':document_number' => $documentNumber,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    public function findByPerson(string $testKey, string $phase, string $documentNumber): ?array
    {
        $pdo = Connection::getPdo();

        $sql = 'SELECT * FROM test_responses 
                WHERE test_key = :test_key AND phase = :phase AND document_number = :document_number
                LIMIT 1';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':test_key' => $testKey,
            ':phase' => $phase,
            ':document_number' => $documentNumber,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }
}

