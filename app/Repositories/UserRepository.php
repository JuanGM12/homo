<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;
use PDO;
use PDOException;

final class UserRepository
{
    public function search(array $filters = []): array
    {
        $pdo = Connection::getPdo();

        $where = [];
        $params = [];

        if (!empty($filters['query'])) {
            $where[] = '(u.name LIKE :q_name OR u.email LIKE :q_email OR u.document_number LIKE :q_doc)';
            $params[':q_name'] = '%' . $filters['query'] . '%';
            $params[':q_email'] = '%' . $filters['query'] . '%';
            $params[':q_doc'] = '%' . $filters['query'] . '%';
        }

        if (!empty($filters['document'])) {
            $where[] = 'u.document_number LIKE :filter_document';
            $params[':filter_document'] = '%' . $filters['document'] . '%';
        }

        if (!empty($filters['role'])) {
            $where[] = 'EXISTS (
                SELECT 1 FROM user_roles ur
                INNER JOIN roles r ON r.id = ur.role_id
                WHERE ur.user_id = u.id AND r.name = :role
            )';
            $params[':role'] = $filters['role'];
        }

        if (isset($filters['active']) && $filters['active'] !== '') {
            $where[] = 'u.active = :active';
            $params[':active'] = (int) $filters['active'];
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "
            SELECT
                u.*,
                GROUP_CONCAT(DISTINCT COALESCE(r.description, r.name) ORDER BY r.name SEPARATOR ', ') AS roles_list,
                GROUP_CONCAT(DISTINCT CONCAT(um.subregion, '|', um.municipality) ORDER BY um.subregion, um.municipality SEPARATOR '||') AS municipalities_list
            FROM users u
            LEFT JOIN user_roles ur ON ur.user_id = u.id
            LEFT JOIN roles r ON r.id = ur.role_id
            LEFT JOIN user_municipalities um ON um.user_id = u.id
            $whereSql
            GROUP BY u.id
            ORDER BY u.created_at ASC, u.id ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $pdo = Connection::getPdo();

        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return null;
        }

        $rolesStmt = $pdo->prepare(
            'SELECT r.name FROM roles r
             INNER JOIN user_roles ur ON ur.role_id = r.id
             WHERE ur.user_id = :user_id'
        );
        $rolesStmt->execute([':user_id' => $id]);
        $roles = $rolesStmt->fetchAll(PDO::FETCH_COLUMN);

        $user['roles'] = $roles;
        $user['municipalities'] = $this->findMunicipalitiesForUser($id);

        return $user;
    }

    public function create(array $data, array $roles, array $municipalities = []): int
    {
        $pdo = Connection::getPdo();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO users (name, email, document_number, password, active) VALUES (:name, :email, :document_number, :password, :active)'
            );
            $stmt->execute([
                ':name' => $data['name'],
                ':email' => $data['email'],
                ':document_number' => $data['document_number'] !== '' ? $data['document_number'] : null,
                ':password' => $data['password'],
                ':active' => (int) $data['active'],
            ]);

            $userId = (int) $pdo->lastInsertId();

            $roles = $this->sanitizeAssignableRoles($roles);
            if ($roles !== []) {
                $this->syncRoles($userId, $roles, $pdo);
            }
            $this->syncMunicipalities($userId, $municipalities, $pdo);

            $pdo->commit();

            return $userId;
        } catch (PDOException $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function update(int $id, array $data, array $roles, array $municipalities = []): void
    {
        $pdo = Connection::getPdo();
        $pdo->beginTransaction();

        try {
            $set = ['name = :name', 'email = :email', 'document_number = :document_number', 'active = :active'];
            $params = [
                ':id' => $id,
                ':name' => $data['name'],
                ':email' => $data['email'],
                ':document_number' => ($data['document_number'] ?? '') !== '' ? $data['document_number'] : null,
                ':active' => (int) $data['active'],
            ];

            if (!empty($data['password'])) {
                $set[] = 'password = :password';
                $params[':password'] = $data['password'];
            }

            $sql = 'UPDATE users SET ' . implode(', ', $set) . ' WHERE id = :id';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $roles = $this->sanitizeAssignableRoles($roles);
            if ($this->userHasRole($id, 'abogado', $pdo)) {
                $roles[] = 'abogado';
            }

            $this->syncRoles($id, array_values(array_unique($roles)), $pdo);
            $this->syncMunicipalities($id, $municipalities, $pdo);

            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function deactivate(int $id): void
    {
        $pdo = Connection::getPdo();
        $stmt = $pdo->prepare('UPDATE users SET active = 0 WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public function getAllRoles(): array
    {
        $pdo = Connection::getPdo();
        $stmt = $pdo->query('SELECT id, name, description FROM roles ORDER BY name ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findMunicipalitiesForUser(int $userId): array
    {
        $pdo = Connection::getPdo();
        $stmt = $pdo->prepare(
            'SELECT subregion, municipality
             FROM user_municipalities
             WHERE user_id = :user_id
             ORDER BY subregion ASC, municipality ASC'
        );
        $stmt->execute([':user_id' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Usuarios activos con al menos un rol distinto de admin (asesores para Encuesta de Opinión AoAT).
     */
    /**
     * @return list<int>
     */
    public function findActiveIdsByRole(string $role): array
    {
        $role = strtolower(trim($role));
        if ($role === '') {
            return [];
        }
        $aliases = [$role];
        if ($role === 'profesional social' || $role === 'profesional_social') {
            $aliases = ['profesional social', 'profesional_social'];
        }

        $pdo = Connection::getPdo();
        $placeholders = [];
        $params = [];
        foreach ($aliases as $i => $alias) {
            $ph = ':role_' . $i;
            $placeholders[] = $ph;
            $params[$ph] = $alias;
        }
        $stmt = $pdo->prepare(
            'SELECT DISTINCT u.id
             FROM users u
             INNER JOIN user_roles ur ON ur.user_id = u.id
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE u.active = 1
               AND LOWER(r.name) IN (' . implode(', ', $placeholders) . ')
             ORDER BY u.id ASC'
        );
        $stmt->execute($params);

        return array_map(static fn (array $row): int => (int) $row['id'], $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function findNonAdminAdvisors(): array
    {
        $pdo = Connection::getPdo();
        $stmt = $pdo->prepare("
            SELECT u.id, u.name
            FROM users u
            INNER JOIN user_roles ur ON ur.user_id = u.id
            INNER JOIN roles r ON r.id = ur.role_id
            WHERE u.active = 1
              AND NOT EXISTS (
                  SELECT 1 FROM user_roles ur2
                  INNER JOIN roles r2 ON r2.id = ur2.role_id
                  WHERE ur2.user_id = u.id AND r2.name = 'admin'
              )
            GROUP BY u.id, u.name
            ORDER BY u.name ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function syncRoles(int $userId, array $roles, PDO $pdo): void
    {
        $pdo->prepare('DELETE FROM user_roles WHERE user_id = :user_id')
            ->execute([':user_id' => $userId]);

        if ($roles === []) {
            return;
        }

        $roleStmt = $pdo->prepare('SELECT id FROM roles WHERE name = :name');
        $insertStmt = $pdo->prepare(
            'INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)'
        );

        foreach ($roles as $roleName) {
            $roleStmt->execute([':name' => $roleName]);
            $roleId = $roleStmt->fetchColumn();
            if ($roleId) {
                $insertStmt->execute([
                    ':user_id' => $userId,
                    ':role_id' => $roleId,
                ]);
            }
        }
    }

    private function sanitizeAssignableRoles(array $roles): array
    {
        return array_values(array_filter(array_map(static function (mixed $role): string {
            return strtolower(trim((string) $role));
        }, $roles), static fn (string $role): bool => $role !== '' && $role !== 'abogado'));
    }

    private function userHasRole(int $userId, string $roleName, PDO $pdo): bool
    {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM user_roles ur
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE ur.user_id = :user_id AND r.name = :role
             LIMIT 1'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':role' => $roleName,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    private function syncMunicipalities(int $userId, array $municipalities, PDO $pdo): void
    {
        $pdo->prepare('DELETE FROM user_municipalities WHERE user_id = :user_id')
            ->execute([':user_id' => $userId]);

        if ($municipalities === []) {
            return;
        }

        $insertStmt = $pdo->prepare(
            'INSERT INTO user_municipalities (user_id, subregion, municipality)
             VALUES (:user_id, :subregion, :municipality)'
        );

        $seen = [];
        foreach ($municipalities as $row) {
            if (!is_array($row)) {
                continue;
            }

            $subregion = trim((string) ($row['subregion'] ?? ''));
            $municipality = trim((string) ($row['municipality'] ?? ''));
            if ($subregion === '' || $municipality === '') {
                continue;
            }

            $key = mb_strtolower($municipality, 'UTF-8');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $insertStmt->execute([
                ':user_id' => $userId,
                ':subregion' => $subregion,
                ':municipality' => $municipality,
            ]);
        }
    }
}

