<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;
use PDO;
use PDOException;

final class AoatActivityWithOptionRepository
{
    /**
     * @var list<string>
     */
    public const DEFAULT_LABELS = [
        'Adultos mayores',
        'Canales de TV',
        'Comisaría de Familia',
        'Comunidad en general',
        'Coordinación PIC',
        'Directivos Docentes',
        'Docentes',
        'Emisoras',
        'Enfermeros',
        'EPS',
        'Estudiantes',
        'Funcionarios Públicos',
        'Grupos de socorro',
        'ICBF',
        'Iglesias',
        'Jóvenes',
        'Juntas comunales',
        'Médicos',
        'Padres de familia',
        'Policía Nacional',
        'Profesionales Psicosociales',
        'Secretarías Municipales de Salud',
        'SRPA',
        'Universitarios',
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public function allForAdmin(): array
    {
        try {
            $pdo = Connection::getPdo();
            $stmt = $pdo->query(
                'SELECT id, label, sort_order, active
                 FROM aoat_activity_with_options
                 ORDER BY sort_order ASC, label ASC'
            );
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if ($rows !== []) {
                return $rows;
            }
        } catch (PDOException) {
        }

        return $this->defaultRows();
    }

    /**
     * Etiquetas activas para el select de registro.
     *
     * @return list<string>
     */
    public function activeLabels(): array
    {
        try {
            $pdo = Connection::getPdo();
            $stmt = $pdo->query(
                'SELECT label
                 FROM aoat_activity_with_options
                 WHERE active = 1
                 ORDER BY sort_order ASC, label ASC'
            );
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $labels = [];
            foreach ($rows as $row) {
                $label = trim((string) ($row['label'] ?? ''));
                if ($label !== '') {
                    $labels[] = $label;
                }
            }
            if ($labels !== []) {
                return $labels;
            }
        } catch (PDOException) {
        }

        return self::DEFAULT_LABELS;
    }

    /**
     * Opciones del select, incluyendo un valor legado que ya no esté activo.
     *
     * @return list<string>
     */
    public function labelsForSelect(?string $current = null): array
    {
        $labels = $this->activeLabels();
        $current = trim((string) $current);
        if ($current === '') {
            return $labels;
        }

        foreach ($labels as $label) {
            if (strcasecmp($label, $current) === 0) {
                return $labels;
            }
        }

        array_unshift($labels, $current);

        return $labels;
    }

    public function isAllowed(string $value, ?string $legacyCurrent = null): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }

        foreach ($this->activeLabels() as $label) {
            if (strcasecmp($label, $value) === 0) {
                return true;
            }
        }

        $legacyCurrent = trim((string) $legacyCurrent);

        return $legacyCurrent !== '' && strcasecmp($legacyCurrent, $value) === 0;
    }

    /**
     * @param list<array<string, mixed>> $options
     */
    public function replaceAll(array $options): void
    {
        $pdo = Connection::getPdo();
        $pdo->beginTransaction();

        try {
            $pdo->exec('DELETE FROM aoat_activity_with_options');
            $stmt = $pdo->prepare(
                'INSERT INTO aoat_activity_with_options (label, sort_order, active)
                 VALUES (:label, :sort_order, :active)'
            );

            foreach ($options as $index => $option) {
                $stmt->execute([
                    ':label' => (string) ($option['label'] ?? ''),
                    ':sort_order' => (int) ($option['sort_order'] ?? (($index + 1) * 10)),
                    ':active' => !empty($option['active']) ? 1 : 0,
                ]);
            }

            $pdo->commit();
        } catch (PDOException $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function defaultRows(): array
    {
        $rows = [];
        foreach (self::DEFAULT_LABELS as $index => $label) {
            $rows[] = [
                'id' => $index + 1,
                'label' => $label,
                'sort_order' => ($index + 1) * 10,
                'active' => 1,
            ];
        }

        return $rows;
    }
}
