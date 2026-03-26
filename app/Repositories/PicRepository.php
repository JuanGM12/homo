<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;
use PDO;

final class PicRepository
{
    public function create(array $data): int
    {
        $pdo = Connection::getPdo();

        $sql = 'INSERT INTO pic_records (
                    user_id,
                    professional_name,
                    professional_email,
                    subregion,
                    municipality,
                    editable,
                    payload
                ) VALUES (
                    :user_id,
                    :professional_name,
                    :professional_email,
                    :subregion,
                    :municipality,
                    :editable,
                    :payload
                )';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $data['user_id'],
            ':professional_name' => $data['professional_name'],
            ':professional_email' => $data['professional_email'],
            ':subregion' => $data['subregion'],
            ':municipality' => $data['municipality'],
            ':editable' => $data['editable'] ?? 1,
            ':payload' => $data['payload'],
        ]);

        return (int) $pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $pdo = Connection::getPdo();

        $sql = 'UPDATE pic_records
                SET subregion = :subregion,
                    municipality = :municipality,
                    payload = :payload
                WHERE id = :id';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':subregion' => $data['subregion'],
            ':municipality' => $data['municipality'],
            ':payload' => $data['payload'],
            ':id' => $id,
        ]);
    }

    public function findForUser(int $userId): array
    {
        $pdo = Connection::getPdo();

        $sql = 'SELECT pr.*,
                       rm.roles_list AS professional_role
                FROM pic_records pr
                LEFT JOIN (
                    SELECT ur.user_id,
                           GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ", ") AS roles_list
                    FROM user_roles ur
                    INNER JOIN roles r ON r.id = ur.role_id
                    GROUP BY ur.user_id
                ) rm ON rm.user_id = pr.user_id
                WHERE pr.user_id = :user_id
                ORDER BY pr.created_at DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findById(int $id): ?array
    {
        $pdo = Connection::getPdo();

        $sql = 'SELECT * FROM pic_records WHERE id = :id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Registros PIC para auditoría (especialistas / coordinación).
     *
     * @return array<int, array<string, mixed>> 
     */
    public function findForAudit(array $professionalRoles = []): array
    {
        $pdo = Connection::getPdo();

        if ($professionalRoles === []) {
            $sql = 'SELECT pr.*,
                           rm.roles_list AS professional_role
                    FROM pic_records pr
                    LEFT JOIN (
                        SELECT ur.user_id,
                               GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ", ") AS roles_list
                        FROM user_roles ur
                        INNER JOIN roles r ON r.id = ur.role_id
                        GROUP BY ur.user_id
                    ) rm ON rm.user_id = pr.user_id
                    ORDER BY pr.created_at DESC';
            $stmt = $pdo->query($sql);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $placeholders = implode(', ', array_fill(0, count($professionalRoles), '?'));
        $sql = sprintf(
            'SELECT DISTINCT pr.*,
                    rm.roles_list AS professional_role
             FROM pic_records pr
             LEFT JOIN (
                 SELECT ur2.user_id,
                        GROUP_CONCAT(DISTINCT r2.name ORDER BY r2.name SEPARATOR ", ") AS roles_list
                 FROM user_roles ur2
                 INNER JOIN roles r2 ON r2.id = ur2.role_id
                 GROUP BY ur2.user_id
             ) rm ON rm.user_id = pr.user_id
             INNER JOIN user_roles ur ON ur.user_id = pr.user_id
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE r.name IN (%s)
             ORDER BY pr.created_at DESC',
            $placeholders
        );

        $stmt = $pdo->prepare($sql);
        $stmt->execute($professionalRoles);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
