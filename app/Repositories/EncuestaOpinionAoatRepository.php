<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;
use PDO;
use PDOException;

final class EncuestaOpinionAoatRepository
{
    public function create(array $data): int
    {
        $pdo = Connection::getPdo();
        $pdo->beginTransaction();

        try {
            $columns = array_keys($data);
            $placeholders = array_map(static fn(string $col): string => ':' . $col, $columns);

            $sql = sprintf(
                'INSERT INTO encuesta_opinion_aoat (%s) VALUES (%s)',
                implode(', ', $columns),
                implode(', ', $placeholders)
            );

            $stmt = $pdo->prepare($sql);
            foreach ($data as $column => $value) {
                $stmt->bindValue(':' . $column, $value);
            }
            $stmt->execute();

            $id = (int) $pdo->lastInsertId();
            $pdo->commit();

            return $id;
        } catch (PDOException $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Lista todas las encuestas (solo para roles con permiso de consulta).
     */
    public function findAll(): array
    {
        $pdo = Connection::getPdo();
        $stmt = $pdo->query(
            'SELECT * FROM encuesta_opinion_aoat ORDER BY created_at DESC, id DESC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
