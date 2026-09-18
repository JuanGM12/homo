<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;
use PDO;

final class BoletinEditionRepository
{
    public function create(int $userId, string $fromDate, string $toDate): int
    {
        $pdo = Connection::getPdo();
        $stmt = $pdo->prepare(
            'INSERT INTO boletin_ediciones (from_date, to_date, created_by)
             VALUES (:from_date, :to_date, :created_by)'
        );
        $stmt->execute([
            ':from_date' => $fromDate,
            ':to_date' => $toDate,
            ':created_by' => $userId,
        ]);

        return (int) $pdo->lastInsertId();
    }
}
