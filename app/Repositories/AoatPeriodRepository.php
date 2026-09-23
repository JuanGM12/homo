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
            $stmt = $pdo->query($this->selectSql() . ' ORDER BY active DESC, name DESC, id DESC');

            return array_map([$this, 'hydratePeriod'], $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
        } catch (PDOException) {
            return $this->fallbackPeriods();
        }
    }

    public function findById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        try {
            $pdo = Connection::getPdo();
            $stmt = $pdo->prepare($this->selectSql() . ' WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row === false ? null : $this->hydratePeriod($row);
        } catch (PDOException) {
            return null;
        }
    }

    public function active(): ?array
    {
        try {
            $pdo = Connection::getPdo();
            $stmt = $pdo->query($this->selectSql() . ' WHERE active = 1 ORDER BY id DESC LIMIT 1');
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row === false ? null : $this->hydratePeriod($row);
        } catch (PDOException) {
            return null;
        }
    }

    public function isActivePeriodId(?int $periodId): bool
    {
        $active = $this->active();
        $activeId = (int) ($active['id'] ?? 0);
        if ($activeId <= 0) {
            return true;
        }

        return (int) $periodId === $activeId;
    }

    public function contractNumberForPeriodId(?int $periodId): string
    {
        $period = $periodId !== null && $periodId > 0 ? $this->findById($periodId) : null;
        if ($period === null) {
            $period = $this->active();
        }

        $contract = trim((string) ($period['contract_number'] ?? ''));
        if ($contract !== '') {
            return $contract;
        }

        return $this->legacyContractForName((string) ($period['name'] ?? ''));
    }

    /**
     * @param list<int|string|null> $periodIds
     */
    public function formatContractsForPeriodIds(array $periodIds): string
    {
        $seen = [];
        foreach ($periodIds as $rawId) {
            $id = (int) $rawId;
            if ($id <= 0) {
                continue;
            }
            $contract = $this->contractNumberForPeriodId($id);
            if ($contract !== '') {
                $seen[$contract] = true;
            }
        }

        if ($seen === []) {
            $fallback = $this->contractNumberForPeriodId(null);
            return $fallback;
        }

        return implode(' / ', array_keys($seen));
    }

    public function create(string $name, bool $active, string $contractNumber = ''): void
    {
        $name = $this->normalizeName($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Nombre de periodo requerido.');
        }
        $contractNumber = $this->normalizeContract($contractNumber);

        $pdo = Connection::getPdo();
        $pdo->beginTransaction();

        try {
            if ($active) {
                $pdo->exec('UPDATE aoat_periods SET active = 0');
            }

            if ($this->supportsContractColumn()) {
                $stmt = $pdo->prepare(
                    'INSERT INTO aoat_periods (name, contract_number, active)
                     VALUES (:name, :contract_number, :active)
                     ON DUPLICATE KEY UPDATE
                        contract_number = VALUES(contract_number),
                        active = IF(:active_update = 1, 1, active)'
                );
                $stmt->execute([
                    ':name' => $name,
                    ':contract_number' => $contractNumber !== '' ? $contractNumber : null,
                    ':active' => $active ? 1 : 0,
                    ':active_update' => $active ? 1 : 0,
                ]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO aoat_periods (name, active) VALUES (:name, :active)
                     ON DUPLICATE KEY UPDATE active = IF(:active_update = 1, 1, active)'
                );
                $stmt->execute([
                    ':name' => $name,
                    ':active' => $active ? 1 : 0,
                    ':active_update' => $active ? 1 : 0,
                ]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function update(int $id, string $name, string $contractNumber): void
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('Periodo no valido.');
        }

        $name = $this->normalizeName($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Nombre de periodo requerido.');
        }
        $contractNumber = $this->normalizeContract($contractNumber);

        $pdo = Connection::getPdo();
        if ($this->supportsContractColumn()) {
            $stmt = $pdo->prepare(
                'UPDATE aoat_periods
                 SET name = :name, contract_number = :contract_number
                 WHERE id = :id'
            );
            $stmt->execute([
                ':id' => $id,
                ':name' => $name,
                ':contract_number' => $contractNumber !== '' ? $contractNumber : null,
            ]);
        } else {
            $stmt = $pdo->prepare('UPDATE aoat_periods SET name = :name WHERE id = :id');
            $stmt->execute([
                ':id' => $id,
                ':name' => $name,
            ]);
        }

        if ($stmt->rowCount() === 0 && $this->findById($id) === null) {
            throw new \RuntimeException('Periodo no encontrado.');
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

    public function normalizeContract(string $contract): string
    {
        $contract = trim($contract);
        $contract = preg_replace('/\s+/', '', $contract) ?? $contract;

        return mb_substr($contract, 0, 40);
    }

    private function selectSql(): string
    {
        if ($this->supportsContractColumn()) {
            return 'SELECT id, name, contract_number, active, created_at, updated_at FROM aoat_periods';
        }

        return 'SELECT id, name, NULL AS contract_number, active, created_at, updated_at FROM aoat_periods';
    }

    private function supportsContractColumn(): bool
    {
        static $supported = null;
        if ($supported !== null) {
            return $supported;
        }

        try {
            $stmt = Connection::getPdo()->query("SHOW COLUMNS FROM aoat_periods LIKE 'contract_number'");
            $supported = $stmt !== false && $stmt->fetch(PDO::FETCH_ASSOC) !== false;
        } catch (PDOException) {
            $supported = false;
        }

        return $supported;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydratePeriod(array $row): array
    {
        $name = (string) ($row['name'] ?? '');
        $contract = trim((string) ($row['contract_number'] ?? ''));
        if ($contract === '') {
            $contract = $this->legacyContractForName($name);
        }
        $row['contract_number'] = $contract;

        return $row;
    }

    private function legacyContractForName(string $name): string
    {
        return match (trim($name)) {
            '2026-1' => '4600018640',
            '2026-2' => '4600019278',
            default => '',
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fallbackPeriods(): array
    {
        return [
            ['id' => 0, 'name' => '2026-1', 'contract_number' => '4600018640', 'active' => 1],
            ['id' => 0, 'name' => '2026-2', 'contract_number' => '4600019278', 'active' => 0],
        ];
    }
}
