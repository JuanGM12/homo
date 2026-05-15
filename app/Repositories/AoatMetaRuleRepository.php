<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;
use PDO;
use PDOException;

final class AoatMetaRuleRepository
{
    private ?bool $supportsDualTargets = null;

    /**
     * @return list<array<string, mixed>>
     */
    public function allActive(): array
    {
        try {
            $pdo = Connection::getPdo();
            $stmt = $pdo->query($this->baseSelectSql(true));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if ($rows !== []) {
                return array_map([$this, 'normalizeRuleRow'], $rows);
            }
        } catch (PDOException) {
        }

        return $this->defaultRules();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function allForAdmin(): array
    {
        try {
            $pdo = Connection::getPdo();
            $stmt = $pdo->query($this->baseSelectSql(false));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if ($rows !== []) {
                return array_map([$this, 'normalizeRuleRow'], $rows);
            }
        } catch (PDOException) {
        }

        return $this->defaultRules();
    }

    /**
     * @param list<array<string, mixed>> $rules
     */
    public function replaceAll(array $rules): void
    {
        $pdo = Connection::getPdo();
        $pdo->beginTransaction();

        try {
            $pdo->exec('DELETE FROM aoat_meta_rules');
            $supportsDualTargets = $this->supportsDualTargets($pdo);
            $stmt = $pdo->prepare($supportsDualTargets
                ? 'INSERT INTO aoat_meta_rules (role_key, scope, target_value, target_safer, target_politica, month_from, month_to, rule_year, active, notes)
                   VALUES (:role_key, :scope, :target_value, :target_safer, :target_politica, :month_from, :month_to, :rule_year, :active, :notes)'
                : 'INSERT INTO aoat_meta_rules (role_key, scope, target_value, month_from, month_to, rule_year, active, notes)
                   VALUES (:role_key, :scope, :target_value, :month_from, :month_to, :rule_year, :active, :notes)'
            );

            foreach ($rules as $rule) {
                $params = [
                    ':role_key' => (string) ($rule['role_key'] ?? ''),
                    ':scope' => (string) ($rule['scope'] ?? 'per_territory'),
                    ':target_value' => (int) ($rule['target_value'] ?? 0),
                    ':month_from' => (int) ($rule['month_from'] ?? 1),
                    ':month_to' => (int) ($rule['month_to'] ?? 12),
                    ':rule_year' => $rule['rule_year'] === null || $rule['rule_year'] === '' ? null : (int) $rule['rule_year'],
                    ':active' => !empty($rule['active']) ? 1 : 0,
                    ':notes' => trim((string) ($rule['notes'] ?? '')) ?: null,
                ];
                if ($supportsDualTargets) {
                    $params[':target_safer'] = array_key_exists('target_safer', $rule) && $rule['target_safer'] !== null && $rule['target_safer'] !== ''
                        ? (int) $rule['target_safer']
                        : null;
                    $params[':target_politica'] = array_key_exists('target_politica', $rule) && $rule['target_politica'] !== null && $rule['target_politica'] !== ''
                        ? (int) $rule['target_politica']
                        : null;
                }

                $stmt->execute($params);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function defaultRules(): array
    {
        return [
            [
                'id' => 0,
                'role_key' => 'psicologo',
                'scope' => 'per_territory',
                'target_value' => 2,
                'target_safer' => null,
                'target_politica' => null,
                'month_from' => 1,
                'month_to' => 3,
                'rule_year' => null,
                'active' => 1,
                'notes' => 'Meta territorial enero-marzo',
            ],
            [
                'id' => 0,
                'role_key' => 'psicologo',
                'scope' => 'per_territory',
                'target_value' => 3,
                'target_safer' => null,
                'target_politica' => null,
                'month_from' => 4,
                'month_to' => 12,
                'rule_year' => null,
                'active' => 1,
                'notes' => 'Meta territorial desde abril',
            ],
            [
                'id' => 0,
                'role_key' => 'abogado',
                'scope' => 'per_territory',
                'target_value' => 2,
                'target_safer' => 2,
                'target_politica' => 2,
                'month_from' => 1,
                'month_to' => 3,
                'rule_year' => null,
                'active' => 1,
                'notes' => 'Meta territorial enero-marzo',
            ],
            [
                'id' => 0,
                'role_key' => 'abogado',
                'scope' => 'per_territory',
                'target_value' => 3,
                'target_safer' => 3,
                'target_politica' => 3,
                'month_from' => 4,
                'month_to' => 12,
                'rule_year' => null,
                'active' => 1,
                'notes' => 'Meta territorial desde abril',
            ],
            [
                'id' => 0,
                'role_key' => 'medico',
                'scope' => 'global_monthly',
                'target_value' => 8,
                'target_safer' => null,
                'target_politica' => null,
                'month_from' => 1,
                'month_to' => 12,
                'rule_year' => null,
                'active' => 1,
                'notes' => 'Meta global mensual entre todos los médicos',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeRuleRow(array $row): array
    {
        $ts = $row['target_safer'] ?? null;
        $tp = $row['target_politica'] ?? null;

        return [
            'id' => (int) ($row['id'] ?? 0),
            'role_key' => trim((string) ($row['role_key'] ?? '')),
            'scope' => trim((string) ($row['scope'] ?? 'per_territory')),
            'target_value' => (int) ($row['target_value'] ?? 0),
            'target_safer' => $ts === null || $ts === '' ? null : (int) $ts,
            'target_politica' => $tp === null || $tp === '' ? null : (int) $tp,
            'month_from' => (int) ($row['month_from'] ?? 1),
            'month_to' => (int) ($row['month_to'] ?? 12),
            'rule_year' => isset($row['rule_year']) && $row['rule_year'] !== null && $row['rule_year'] !== '' ? (int) $row['rule_year'] : null,
            'active' => (int) ($row['active'] ?? 1),
            'notes' => trim((string) ($row['notes'] ?? '')),
        ];
    }

    private function baseSelectSql(bool $onlyActive): string
    {
        $supportsDualTargets = $this->supportsDualTargets();
        $where = $onlyActive ? 'WHERE active = 1' : '';
        $targetSaferSelect = $supportsDualTargets ? 'target_safer' : 'NULL AS target_safer';
        $targetPoliticaSelect = $supportsDualTargets ? 'target_politica' : 'NULL AS target_politica';
        $orderBy = $onlyActive
            ? "ORDER BY FIELD(role_key, 'psicologo', 'abogado', 'medico'), COALESCE(rule_year, 0), month_from, month_to, id"
            : "ORDER BY active DESC, FIELD(role_key, 'psicologo', 'abogado', 'medico'), COALESCE(rule_year, 0), month_from, month_to, id";

        return "SELECT id, role_key, scope, target_value, {$targetSaferSelect}, {$targetPoliticaSelect}, month_from, month_to, rule_year, active, notes
                FROM aoat_meta_rules
                {$where}
                {$orderBy}";
    }

    private function supportsDualTargets(?PDO $pdo = null): bool
    {
        if ($this->supportsDualTargets !== null) {
            return $this->supportsDualTargets;
        }

        $pdo = $pdo ?? Connection::getPdo();
        $stmt = $pdo->query("SHOW COLUMNS FROM aoat_meta_rules LIKE 'target_safer'");
        $this->supportsDualTargets = (bool) ($stmt && $stmt->fetch(PDO::FETCH_ASSOC));

        return $this->supportsDualTargets;
    }
}
