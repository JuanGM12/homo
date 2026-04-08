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

    public function findById(int $id): ?array
    {
        $pdo = Connection::getPdo();
        $stmt = $pdo->prepare('SELECT * FROM test_responses WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findAnswersByResponseId(int $responseId): array
    {
        $pdo = Connection::getPdo();
        $stmt = $pdo->prepare(
            'SELECT question_number, selected_option, is_correct
             FROM test_response_answers
             WHERE response_id = :response_id
             ORDER BY question_number ASC'
        );
        $stmt->execute([':response_id' => $responseId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Consulta respuestas de test con filtros opcionales.
     *
     * Filtros soportados (todas las claves son opcionales):
     *  - test_key: string
     *  - phase: 'pre'|'post'
     *  - document_number: string (coincidencia exacta en document_number; no usar LIKE para no filtrar por subcadena)
     *  - search: string
     *  - subregion: string
     *  - municipality: string (un solo valor; compatibilidad)
     *  - municipalities: list<string> (varios municipios; tiene prioridad sobre municipality)
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
        } elseif (!empty($filters['allowed_test_keys']) && is_array($filters['allowed_test_keys'])) {
            $keys = array_values(array_unique(array_filter(
                $filters['allowed_test_keys'],
                static fn (mixed $k): bool => is_string($k) && $k !== ''
            )));
            if ($keys !== []) {
                $placeholders = [];
                foreach ($keys as $i => $k) {
                    $ph = ':atk' . $i;
                    $placeholders[] = $ph;
                    $params[$ph] = $k;
                }
                $where[] = 'test_key IN (' . implode(', ', $placeholders) . ')';
            }
        }

        if (!empty($filters['phase']) && in_array($filters['phase'], ['pre', 'post'], true)) {
            $where[] = 'phase = :phase';
            $params[':phase'] = $filters['phase'];
        }

        if (!empty($filters['document_number'])) {
            $where[] = 'TRIM(document_number) = :document_number';
            $params[':document_number'] = trim((string) $filters['document_number']);
        }

        if (!empty($filters['search'])) {
            $where[] = '(document_number LIKE :search
                OR first_name LIKE :search
                OR last_name LIKE :search
                OR CONCAT(first_name, " ", last_name) LIKE :search)';
            $params[':search'] = '%' . trim((string) $filters['search']) . '%';
        }

        if (!empty($filters['subregion'])) {
            $where[] = 'subregion = :subregion';
            $params[':subregion'] = $filters['subregion'];
        }

        $municipalities = [];
        if (!empty($filters['municipalities']) && is_array($filters['municipalities'])) {
            $municipalities = array_values(array_unique(array_filter(
                array_map(static fn (mixed $m): string => trim((string) $m), $filters['municipalities']),
                static fn (string $m): bool => $m !== ''
            )));
        } elseif (!empty($filters['municipality'])) {
            $one = trim((string) $filters['municipality']);
            if ($one !== '') {
                $municipalities = [$one];
            }
        }
        if ($municipalities !== []) {
            $placeholders = [];
            foreach ($municipalities as $i => $mun) {
                $ph = ':mun_f_' . $i;
                $placeholders[] = $ph;
                $params[$ph] = $mun;
            }
            $where[] = 'municipality IN (' . implode(', ', $placeholders) . ')';
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

        $limit = isset($filters['limit']) ? (int) $filters['limit'] : 500;
        if ($limit < 1) {
            $limit = 500;
        }
        if ($limit > 10000) {
            $limit = 10000;
        }

        $sql .= ' ORDER BY created_at DESC, id DESC LIMIT ' . $limit;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

