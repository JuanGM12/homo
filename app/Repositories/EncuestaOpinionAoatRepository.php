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
            "SELECT
                e.*,
                GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ',') AS advisor_roles
            FROM encuesta_opinion_aoat e
            LEFT JOIN user_roles ur ON ur.user_id = e.advisor_user_id
            LEFT JOIN roles r ON r.id = ur.role_id
            GROUP BY e.id
            ORDER BY e.created_at DESC, e.id DESC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $pdo = Connection::getPdo();
        $stmt = $pdo->prepare('SELECT * FROM encuesta_opinion_aoat WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function deleteById(int $id): void
    {
        $pdo = Connection::getPdo();
        $stmt = $pdo->prepare('DELETE FROM encuesta_opinion_aoat WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }
}
