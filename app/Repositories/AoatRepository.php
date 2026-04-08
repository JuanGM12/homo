<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;
use PDO;
use PDOException;

final class AoatRepository
{
    public function create(array $data): int
    {
        $pdo = Connection::getPdo();
        $pdo->beginTransaction();

        try {
            $columns = array_keys($data);
            $placeholders = array_map(static fn(string $col): string => ':' . $col, $columns);

            $sql = sprintf(
                'INSERT INTO aoat_records (%s) VALUES (%s)',
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

    public function update(int $id, array $data): void
    {
        if ($data === []) {
            return;
        }

        $pdo = Connection::getPdo();

        $setParts = [];
        foreach (array_keys($data) as $column) {
            $setParts[] = sprintf('%s = :%s', $column, $column);
        }

        $sql = sprintf('UPDATE aoat_records SET %s WHERE id = :id', implode(', ', $setParts));

        $stmt = $pdo->prepare($sql);
        foreach ($data as $column => $value) {
            $stmt->bindValue(':' . $column, $value);
        }
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function findById(int $id): ?array
    {
        $pdo = Connection::getPdo();

        $stmt = $pdo->prepare('SELECT * FROM aoat_records WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function findForUser(int $userId): array
    {
        $pdo = Connection::getPdo();

        $stmt = $pdo->prepare(
            'SELECT * FROM aoat_records WHERE user_id = :user_id ORDER BY created_at DESC, id DESC'
        );
        $stmt->execute([':user_id' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Registros de AoAT para auditoría (especialistas / coordinación).
     * Si se suministra un arreglo de roles profesionales, se filtra por ellos;
     * de lo contrario, se devuelven todos los registros.
     *
     * @param string[] $professionalRoles
     * @return array<int, array<string, mixed>>
     */
    public function findForAudit(array $professionalRoles = []): array
    {
        $pdo = Connection::getPdo();

        if ($professionalRoles === []) {
            $stmt = $pdo->query('SELECT * FROM aoat_records ORDER BY created_at DESC, id DESC');
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $placeholders = implode(', ', array_fill(0, count($professionalRoles), '?'));
        $sql = sprintf(
            'SELECT * FROM aoat_records WHERE professional_role IN (%s) ORDER BY created_at DESC, id DESC',
            $placeholders
        );

        $stmt = $pdo->prepare($sql);
        $stmt->execute($professionalRoles);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Obtiene registros de AoAT para reportes, filtrando por rol profesional y rango de fechas
     * con base en la fecha de la actividad almacenada en el payload (activity_date).
     */
    public function findByRoleAndDateRange(string $role, string $fromDate, string $toDate): array
    {
        $pdo = Connection::getPdo();

        $sql = "
            SELECT
                aoat_records.*,
                JSON_UNQUOTE(JSON_EXTRACT(payload, '$.activity_date')) AS activity_date_json,
                JSON_UNQUOTE(JSON_EXTRACT(payload, '$.activity_type')) AS activity_type_json
            FROM aoat_records
            WHERE professional_role = :role
              AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.activity_date')) BETWEEN :from_date AND :to_date
            ORDER BY activity_date_json ASC, id ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':role' => $role,
            ':from_date' => $fromDate,
            ':to_date' => $toDate,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deleteById(int $id): void
    {
        $pdo = Connection::getPdo();
        $stmt = $pdo->prepare('DELETE FROM aoat_records WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
    }
}

