<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;
use PDO;

final class CronogramaRepository
{
    /**
     * @param array{municipality?: string, subregion?: string, user_id?: int, role?: string} $filters
     * @return array<int, array<string, mixed>>
     */
    public function listByRange(string $from, string $to, array $filters = []): array
    {
        $pdo = Connection::getPdo();
        $sql = 'SELECT c.*
                FROM cronograma_actividades c
                WHERE c.activity_date >= :fecha_desde AND c.activity_date <= :fecha_hasta';
        $params = [
            ':fecha_desde' => $from,
            ':fecha_hasta' => $to,
        ];

        $sql .= $this->appendFilters($filters, $params);
        $sql .= ' ORDER BY c.activity_date ASC, c.hora_inicio ASC, c.id ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array{municipality?: string, subregion?: string, user_id?: int, role?: string} $filters
     * @return array<int, array<string, mixed>>
     */
    public function listByDay(string $date, array $filters = []): array
    {
        $pdo = Connection::getPdo();
        $sql = 'SELECT c.*
                FROM cronograma_actividades c
                WHERE c.activity_date = :fecha';
        $params = [':fecha' => $date];
        $sql .= $this->appendFilters($filters, $params);
        $sql .= ' ORDER BY c.hora_inicio ASC, c.id ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findById(int $id): ?array
    {
        $pdo = Connection::getPdo();
        $stmt = $pdo->prepare('SELECT * FROM cronograma_actividades WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function create(array $data): int
    {
        $pdo = Connection::getPdo();
        $sql = 'INSERT INTO cronograma_actividades (
                    user_id, professional_name, professional_email, professional_role,
                    subregion, municipality, activity_date, hora_inicio, hora_fin,
                    activity_type, tema, tema_otro, poblacion_atendida
                ) VALUES (
                    :user_id, :professional_name, :professional_email, :professional_role,
                    :subregion, :municipality, :activity_date, :hora_inicio, :hora_fin,
                    :activity_type, :tema, :tema_otro, :poblacion_atendida
                )';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($this->bindParams($data));

        return (int) $pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $pdo = Connection::getPdo();
        $sql = 'UPDATE cronograma_actividades
                SET subregion = :subregion,
                    municipality = :municipality,
                    activity_date = :activity_date,
                    hora_inicio = :hora_inicio,
                    hora_fin = :hora_fin,
                    activity_type = :activity_type,
                    tema = :tema,
                    tema_otro = :tema_otro,
                    poblacion_atendida = :poblacion_atendida
                WHERE id = :id';
        $params = $this->bindParams($data);
        unset($params[':user_id'], $params[':professional_name'], $params[':professional_email'], $params[':professional_role']);
        $params[':id'] = $id;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    public function deleteById(int $id): void
    {
        $pdo = Connection::getPdo();
        $stmt = $pdo->prepare('DELETE FROM cronograma_actividades WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<string, mixed> $params
     */
    private function appendFilters(array $filters, array &$params): string
    {
        $sql = '';
        $municipality = trim((string) ($filters['municipality'] ?? ''));
        if ($municipality !== '') {
            $sql .= ' AND c.municipality = :municipality';
            $params[':municipality'] = $municipality;
        }
        $subregion = trim((string) ($filters['subregion'] ?? ''));
        if ($subregion !== '') {
            $sql .= ' AND c.subregion = :subregion';
            $params[':subregion'] = $subregion;
        }
        $userId = (int) ($filters['user_id'] ?? 0);
        if ($userId > 0) {
            $sql .= ' AND c.user_id = :user_id';
            $params[':user_id'] = $userId;
        }
        $role = trim((string) ($filters['role'] ?? ''));
        if ($role !== '') {
            $sql .= ' AND c.professional_role = :role';
            $params[':role'] = $role;
        }
        $ownerId = (int) ($filters['owner_id'] ?? 0);
        if ($ownerId > 0) {
            $sql .= ' AND c.user_id = :owner_id';
            $params[':owner_id'] = $ownerId;
        }
        $roles = $filters['roles'] ?? null;
        if (is_array($roles) && $roles !== []) {
            $placeholders = [];
            foreach (array_values($roles) as $i => $roleName) {
                $key = ':role_scope_' . $i;
                $placeholders[] = $key;
                $params[$key] = (string) $roleName;
            }
            $sql .= ' AND c.professional_role IN (' . implode(', ', $placeholders) . ')';
        }

        return $sql;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function bindParams(array $data): array
    {
        $temaOtro = trim((string) ($data['tema_otro'] ?? ''));

        return [
            ':user_id' => (int) $data['user_id'],
            ':professional_name' => (string) $data['professional_name'],
            ':professional_email' => (string) $data['professional_email'],
            ':professional_role' => (string) $data['professional_role'],
            ':subregion' => (string) $data['subregion'],
            ':municipality' => (string) $data['municipality'],
            ':activity_date' => (string) $data['activity_date'],
            ':hora_inicio' => (string) $data['hora_inicio'],
            ':hora_fin' => (string) $data['hora_fin'],
            ':activity_type' => (string) $data['activity_type'],
            ':tema' => (string) $data['tema'],
            ':tema_otro' => $temaOtro !== '' ? $temaOtro : null,
            ':poblacion_atendida' => (string) $data['poblacion_atendida'],
        ];
    }
}
