<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;
use PDO;
use PDOException;

final class PicRepository
{
    private ?bool $supportsPeriodColumn = null;

    public function create(array $data): int
    {
        $pdo = Connection::getPdo();
        $hasPeriod = $this->supportsPeriodColumn($pdo);

        $columns = [
            'user_id',
            'professional_name',
            'professional_email',
            'subregion',
            'municipality',
            'editable',
            'payload',
        ];
        if ($hasPeriod) {
            array_splice($columns, 1, 0, ['period_id']);
        }

        $placeholders = array_map(static fn (string $col): string => ':' . $col, $columns);
        $sql = 'INSERT INTO pic_records (' . implode(', ', $columns) . ')
                VALUES (' . implode(', ', $placeholders) . ')';

        $params = [
            ':user_id' => $data['user_id'],
            ':professional_name' => $data['professional_name'],
            ':professional_email' => $data['professional_email'],
            ':subregion' => $data['subregion'],
            ':municipality' => $data['municipality'],
            ':editable' => $data['editable'] ?? 1,
            ':payload' => $data['payload'],
        ];
        if ($hasPeriod) {
            $periodId = $data['period_id'] ?? null;
            $params[':period_id'] = $periodId !== null && (int) $periodId > 0 ? (int) $periodId : null;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

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
        $sql = $this->selectSql('WHERE pr.user_id = :user_id ORDER BY pr.created_at DESC');
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findById(int $id): ?array
    {
        $pdo = Connection::getPdo();
        $sql = $this->selectSql('WHERE pr.id = :id');
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
            $sql = $this->selectSql('ORDER BY pr.created_at DESC');
            $stmt = $pdo->query($sql);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $placeholders = implode(', ', array_fill(0, count($professionalRoles), '?'));
        $where = sprintf(
            'INNER JOIN user_roles ur ON ur.user_id = pr.user_id
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE r.name IN (%s)
             ORDER BY pr.created_at DESC',
            $placeholders
        );
        $sql = $this->selectSql($where, true);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($professionalRoles);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function deleteById(int $id): void
    {
        $pdo = Connection::getPdo();
        $stmt = $pdo->prepare('DELETE FROM pic_records WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    private function selectSql(string $where = '', bool $distinct = false): string
    {
        $distinctKw = $distinct ? 'DISTINCT ' : '';
        $periodSelect = $this->supportsPeriodColumn()
            ? 'p.name AS period_name, COALESCE(p.active, 0) AS period_active'
            : 'NULL AS period_name, 0 AS period_active';
        $periodJoin = $this->supportsPeriodColumn()
            ? 'LEFT JOIN aoat_periods p ON p.id = pr.period_id'
            : '';
        $rolesJoin = 'LEFT JOIN (
                SELECT ur.user_id,
                       GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ", ") AS roles_list
                FROM user_roles ur
                INNER JOIN roles r ON r.id = ur.role_id
                GROUP BY ur.user_id
            ) rm ON rm.user_id = pr.user_id';

        return "SELECT {$distinctKw}pr.*,
                       rm.roles_list AS professional_role,
                       {$periodSelect}
                FROM pic_records pr
                {$periodJoin}
                {$rolesJoin}
                {$where}";
    }

    private function supportsPeriodColumn(?PDO $pdo = null): bool
    {
        if ($this->supportsPeriodColumn !== null) {
            return $this->supportsPeriodColumn;
        }

        try {
            $pdo = $pdo ?? Connection::getPdo();
            $stmt = $pdo->query("SHOW COLUMNS FROM pic_records LIKE 'period_id'");
            $this->supportsPeriodColumn = (bool) ($stmt && $stmt->fetch(PDO::FETCH_ASSOC));
        } catch (PDOException) {
            $this->supportsPeriodColumn = false;
        }

        return $this->supportsPeriodColumn;
    }
}
