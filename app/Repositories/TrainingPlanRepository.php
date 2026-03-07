<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;
use PDO;

final class TrainingPlanRepository
{
    public function create(array $data): int
    {
        $pdo = Connection::getPdo();

        $sql = 'INSERT INTO training_plans (
                    user_id,
                    professional_name,
                    professional_email,
                    professional_role,
                    subregion,
                    municipality,
                    plan_year,
                    editable,
                    payload
                ) VALUES (
                    :user_id,
                    :professional_name,
                    :professional_email,
                    :professional_role,
                    :subregion,
                    :municipality,
                    :plan_year,
                    :editable,
                    :payload
                )';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $data['user_id'],
            ':professional_name' => $data['professional_name'],
            ':professional_email' => $data['professional_email'],
            ':professional_role' => $data['professional_role'],
            ':subregion' => $data['subregion'],
            ':municipality' => $data['municipality'],
            ':plan_year' => $data['plan_year'],
            ':editable' => $data['editable'] ?? 1,
            ':payload' => $data['payload'],
        ]);

        return (int) $pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $pdo = Connection::getPdo();

        $sql = 'UPDATE training_plans
                SET subregion = :subregion,
                    municipality = :municipality,
                    plan_year = :plan_year,
                    payload = :payload
                WHERE id = :id';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':subregion' => $data['subregion'],
            ':municipality' => $data['municipality'],
            ':plan_year' => $data['plan_year'],
            ':payload' => $data['payload'],
            ':id' => $id,
        ]);
    }

    public function findForUser(int $userId): array
    {
        $pdo = Connection::getPdo();

        $sql = 'SELECT *
                FROM training_plans
                WHERE user_id = :user_id
                ORDER BY plan_year DESC, created_at DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findById(int $id): ?array
    {
        $pdo = Connection::getPdo();

        $sql = 'SELECT * FROM training_plans WHERE id = :id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}

