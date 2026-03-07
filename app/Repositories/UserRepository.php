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
            $where[] = '(u.name LIKE :q OR u.email LIKE :q)';
            $params[':q'] = '%' . $filters['query'] . '%';
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
                GROUP_CONCAT(COALESCE(r.description, r.name) ORDER BY r.name SEPARATOR ', ') AS roles_list
            FROM users u
            LEFT JOIN user_roles ur ON ur.user_id = u.id
            LEFT JOIN roles r ON r.id = ur.role_id
            $whereSql
            GROUP BY u.id
            ORDER BY u.created_at DESC, u.id DESC
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

        return $user;
    }

    public function create(array $data, array $roles): int
    {
        $pdo = Connection::getPdo();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO users (name, email, password, active) VALUES (:name, :email, :password, :active)'
            );
            $stmt->execute([
                ':name' => $data['name'],
                ':email' => $data['email'],
                ':password' => $data['password'],
                ':active' => (int) $data['active'],
            ]);

            $userId = (int) $pdo->lastInsertId();

            if ($roles !== []) {
                $this->syncRoles($userId, $roles, $pdo);
            }

            $pdo->commit();

            return $userId;
        } catch (PDOException $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function update(int $id, array $data, array $roles): void
    {
        $pdo = Connection::getPdo();
        $pdo->beginTransaction();

        try {
            $set = ['name = :name', 'email = :email', 'active = :active'];
            $params = [
                ':id' => $id,
                ':name' => $data['name'],
                ':email' => $data['email'],
                ':active' => (int) $data['active'],
            ];

            if (!empty($data['password'])) {
                $set[] = 'password = :password';
                $params[':password'] = $data['password'];
            }

            $sql = 'UPDATE users SET ' . implode(', ', $set) . ' WHERE id = :id';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $this->syncRoles($id, $roles, $pdo);

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

    /**
     * Usuarios activos con al menos un rol distinto de admin (asesores para Encuesta de Opinión AoAT).
     */
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
}

