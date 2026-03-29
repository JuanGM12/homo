<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;
use PDO;
use PDOException;

final class AsistenciaRepository
{
    public function create(array $data): int
    {
        $pdo = Connection::getPdo();
        $pdo->beginTransaction();

        try {
            $data['actividad_tipos'] = is_string($data['actividad_tipos'] ?? '')
                ? $data['actividad_tipos']
                : json_encode($data['actividad_tipos'] ?? [], JSON_UNESCAPED_UNICODE);

            $columns = array_keys($data);
            $placeholders = array_map(static fn(string $col): string => ':' . $col, $columns);

            $sql = sprintf(
                'INSERT INTO asistencia_actividades (%s) VALUES (%s)',
                implode(', ', $columns),
                implode(', ', $placeholders)
            );

            $stmt = $pdo->prepare($sql);
            foreach ($data as $column => $value) {
                $stmt->bindValue(':' . $column, $value);
            }
            $stmt->execute();

            $id = (int) $pdo->lastInsertId();
            $pdo->commit();

            return $id;
        } catch (PDOException $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function findById(int $id): ?array
    {
        $pdo = Connection::getPdo();
        $stmt = $pdo->prepare('SELECT * FROM asistencia_actividades WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $this->decodeActividadTipos($row);
    }

    public function findByCode(string $code): ?array
    {
        $pdo = Connection::getPdo();
        $stmt = $pdo->prepare('SELECT * FROM asistencia_actividades WHERE code = :code LIMIT 1');
        $stmt->execute([':code' => $code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $this->decodeActividadTipos($row);
    }

    /**
     * Lista actividades con filtros opcionales.
     *
     * @param array{subregion?: string, municipality?: string, advisor_user_id?: int, advisor_user_ids?: array<int, int>, status?: string, tipo?: string} $filters
     * @return array
     */
    public function findWithFilters(array $filters = []): array
    {
        $pdo = Connection::getPdo();
        $where = [];
        $params = [];

        if (!empty($filters['subregion'])) {
            $where[] = 'subregion = :subregion';
            $params[':subregion'] = $filters['subregion'];
        }
        if (!empty($filters['municipality'])) {
            $where[] = 'municipality = :municipality';
            $params[':municipality'] = $filters['municipality'];
        }
        if (!empty($filters['advisor_user_id'])) {
            $where[] = 'advisor_user_id = :advisor_user_id';
            $params[':advisor_user_id'] = (int) $filters['advisor_user_id'];
        } elseif (!empty($filters['advisor_user_ids']) && is_array($filters['advisor_user_ids'])) {
            $advisorIds = array_values(array_filter(
                array_map(static fn (mixed $id): int => (int) $id, $filters['advisor_user_ids']),
                static fn (int $id): bool => $id > 0
            ));
            if ($advisorIds !== []) {
                $placeholders = [];
                foreach ($advisorIds as $index => $advisorId) {
                    $placeholder = ':advisor_user_id_' . $index;
                    $placeholders[] = $placeholder;
                    $params[$placeholder] = $advisorId;
                }
                $where[] = 'advisor_user_id IN (' . implode(', ', $placeholders) . ')';
            }
        }
        if (!empty($filters['status'])) {
            $where[] = 'status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['tipo'])) {
            $where[] = 'tipo = :tipo';
            $params[':tipo'] = $filters['tipo'];
        }
        if (!empty($filters['from_date'])) {
            $where[] = 'activity_date >= :from_date';
            $params[':from_date'] = $filters['from_date'];
        }
        if (!empty($filters['to_date'])) {
            $where[] = 'activity_date <= :to_date';
            $params[':to_date'] = $filters['to_date'];
        }

        $sql = 'SELECT * FROM asistencia_actividades';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY created_at DESC, id DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'decodeActividadTipos'], $rows);
    }

    public function countAsistentesByActividad(int $actividadId): int
    {
        $pdo = Connection::getPdo();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM asistencia_asistentes WHERE actividad_id = :id');
        $stmt->execute([':id' => $actividadId]);
        return (int) $stmt->fetchColumn();
    }

    public function findAsistentesByActividad(int $actividadId): array
    {
        $pdo = Connection::getPdo();
        $stmt = $pdo->prepare(
            'SELECT * FROM asistencia_asistentes WHERE actividad_id = :id ORDER BY registered_at ASC, id ASC'
        );
        $stmt->execute([':id' => $actividadId]);
        return array_map([$this, 'decodeGrupoPoblacional'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Comprueba si ya existe un registro para esta actividad y documento (evitar duplicados).
     */
    public function findAsistenteByActividadAndDocument(int $actividadId, string $documentNumber): ?array
    {
        $pdo = Connection::getPdo();
        $stmt = $pdo->prepare(
            'SELECT id FROM asistencia_asistentes WHERE actividad_id = :aid AND document_number = :doc LIMIT 1'
        );
        $stmt->execute([':aid' => $actividadId, ':doc' => trim($documentNumber)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * Último registro de asistencia por número de documento (para autocompletar formulario).
     */
    public function findLastAsistenteByDocumento(string $documentNumber): ?array
    {
        $pdo = Connection::getPdo();
        $stmt = $pdo->prepare(
            'SELECT * FROM asistencia_asistentes WHERE document_number = :doc ORDER BY registered_at DESC, id DESC LIMIT 1'
        );
        $stmt->execute([':doc' => trim($documentNumber)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $this->decodeGrupoPoblacional($row);
    }

    public function createAsistente(array $data): int
    {
        $pdo = Connection::getPdo();
        if (isset($data['grupo_poblacional']) && is_array($data['grupo_poblacional'])) {
            $data['grupo_poblacional'] = json_encode(array_values($data['grupo_poblacional']), JSON_UNESCAPED_UNICODE);
        }
        $columns = array_keys($data);
        $placeholders = array_map(static fn(string $c): string => ':' . $c, $columns);
        $sql = sprintf(
            'INSERT INTO asistencia_asistentes (%s) VALUES (%s)',
            implode(', ', $columns),
            implode(', ', $placeholders)
        );
        $stmt = $pdo->prepare($sql);
        foreach ($data as $col => $val) {
            $stmt->bindValue(':' . $col, $val);
        }
        $stmt->execute();
        return (int) $pdo->lastInsertId();
    }

    public function delete(int $id): void
    {
        $pdo = Connection::getPdo();
        $stmt = $pdo->prepare('DELETE FROM asistencia_actividades WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public function updateStatus(int $id, string $status): void
    {
        $pdo = Connection::getPdo();
        $stmt = $pdo->prepare('UPDATE asistencia_actividades SET status = :status WHERE id = :id');
        $stmt->execute([':status' => $status, ':id' => $id]);
    }

    /**
     * Genera un código único de 6 dígitos para la actividad.
     */
    public function generateUniqueCode(): string
    {
        $pdo = Connection::getPdo();
        $maxAttempts = 20;
        for ($i = 0; $i < $maxAttempts; $i++) {
            $code = (string) random_int(100000, 999999);
            $stmt = $pdo->prepare('SELECT 1 FROM asistencia_actividades WHERE code = :code LIMIT 1');
            $stmt->execute([':code' => $code]);
            if ($stmt->fetchColumn() === false) {
                return $code;
            }
        }
        return (string) time(); // fallback
    }

    private function decodeActividadTipos(array $row): array
    {
        $row['tipo'] = isset($row['tipo']) && is_string($row['tipo']) && trim($row['tipo']) !== ''
            ? trim((string) $row['tipo'])
            : 'aoat';

        if (isset($row['actividad_tipos']) && is_string($row['actividad_tipos'])) {
            $decoded = json_decode($row['actividad_tipos'], true);
            $row['actividad_tipos'] = is_array($decoded) ? $decoded : [];
        }
        return $row;
    }

    private function decodeGrupoPoblacional(array $row): array
    {
        if (isset($row['grupo_poblacional']) && is_string($row['grupo_poblacional'])) {
            $decoded = json_decode($row['grupo_poblacional'], true);
            $row['grupo_poblacional'] = is_array($decoded) ? $decoded : [];
        }
        return $row;
    }
}
