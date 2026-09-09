<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;
use PDO;
use PDOException;

final class AoatPeriodRepository
{
    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        try {
            $pdo = Connection::getPdo();
            $stmt = $pdo->query('SELECT id, name, active, created_at, updated_at FROM aoat_periods ORDER BY active DESC, name DESC, id DESC');

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException) {
            return $this->fallbackPeriods();
        }
    }

    public function active(): ?array
    {
        try {
            $pdo = Connection::getPdo();
            $stmt = $pdo->query('SELECT id, name, active FROM aoat_periods WHERE active = 1 ORDER BY id DESC LIMIT 1');
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row === false ? null : $row;
        } catch (PDOException) {
            return null;
        }
    }

    public function create(string $name, bool $active): void
    {
        $name = $this->normalizeName($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Nombre de periodo requerido.');
        }

        $pdo = Connection::getPdo();
        $pdo->beginTransaction();

        try {
            if ($active) {
                $pdo->exec('UPDATE aoat_periods SET active = 0');
            }

            $stmt = $pdo->prepare(
                'INSERT INTO aoat_periods (name, active) VALUES (:name, :active)
                 ON DUPLICATE KEY UPDATE active = IF(:active_update = 1, 1, active)'
            );
            $stmt->execute([
                ':name' => $name,
                ':active' => $active ? 1 : 0,
                ':active_update' => $active ? 1 : 0,
            ]);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function activate(int $id): void
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('Periodo no valido.');
        }

        $pdo = Connection::getPdo();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare('SELECT id FROM aoat_periods WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $id]);
            if ($stmt->fetch(PDO::FETCH_ASSOC) === false) {
                throw new \RuntimeException('Periodo no encontrado.');
            }

            $pdo->exec('UPDATE aoat_periods SET active = 0');
            $stmt = $pdo->prepare('UPDATE aoat_periods SET active = 1 WHERE id = :id');
            $stmt->execute([':id' => $id]);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function normalizeName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/\s+/', '', $name) ?? $name;

        return mb_substr($name, 0, 40);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fallbackPeriods(): array
    {
        return [
            ['id' => 0, 'name' => '2026-1', 'active' => 1],
            ['id' => 0, 'name' => '2026-2', 'active' => 0],
        ];
    }
}
