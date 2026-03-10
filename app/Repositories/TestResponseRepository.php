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

    /**
     * Consulta respuestas de test con filtros opcionales.
     *
     * Filtros soportados (todas las claves son opcionales):
     *  - test_key: string
     *  - phase: 'pre'|'post'
     *  - document_number: string
     *  - subregion: string
     *  - municipality: string
     *  - date_from: 'Y-m-d'
     *  - date_to: 'Y-m-d'
     *
     * @param array<string,mixed> $filters
     * @return array<int,array<string,mixed>>
     */
    public function search(array $filters): array
    {
        $pdo = Connection::getPdo();

        $sql = 'SELECT * FROM test_responses';
        $where = [];
        $params = [];

        if (!empty($filters['test_key'])) {
            $where[] = 'test_key = :test_key';
            $params[':test_key'] = $filters['test_key'];
        }

        if (!empty($filters['phase']) && in_array($filters['phase'], ['pre', 'post'], true)) {
            $where[] = 'phase = :phase';
            $params[':phase'] = $filters['phase'];
        }

        if (!empty($filters['document_number'])) {
            $where[] = 'document_number = :document_number';
            $params[':document_number'] = $filters['document_number'];
        }

        if (!empty($filters['subregion'])) {
            $where[] = 'subregion = :subregion';
            $params[':subregion'] = $filters['subregion'];
        }

        if (!empty($filters['municipality'])) {
            $where[] = 'municipality = :municipality';
            $params[':municipality'] = $filters['municipality'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(created_at) >= :date_from';
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(created_at) <= :date_to';
            $params[':date_to'] = $filters['date_to'];
        }

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY created_at DESC, id DESC LIMIT 500';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

