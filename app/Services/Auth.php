<?php

declare(strict_types=1);

namespace App\Services;

final class Auth
{
    public static function check(): bool
    {
        return isset($_SESSION['user']);
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function hasRole(string $role): bool
    {
        $user = self::user();
        if (!$user) {
            return false;
        }

        $roles = $user['roles'] ?? [];
        return in_array($role, $roles, true);
    }

    public static function isAdmin(?array $user = null): bool
    {
        $user = $user ?? self::user();
        if ($user === null) {
            return false;
        }

        return in_array('admin', $user['roles'] ?? [], true);
    }

    /**
     * Puede ver registros de todos los usuarios en módulos operativos (auditoría / listados globales).
     * El resto de perfiles solo ve sus propios registros (por user_id o documento).
     */
    public static function canViewAllModuleRecords(?array $user = null): bool
    {
        $user = $user ?? self::user();
        if ($user === null) {
            return false;
        }

        $roles = $user['roles'] ?? [];

        return in_array('admin', $roles, true)
            || in_array('coordinadora', $roles, true)
            || in_array('coordinador', $roles, true)
            || in_array('especialista', $roles, true);
    }

    /**
     * Resumen de metas globales mensuales (p. ej. medicina) en seguimiento AoAT.
     * Solo médicos, especialistas con ámbito médico, administración y coordinación.
     */
    public static function canViewAoatGlobalMonthlySummaries(?array $user = null): bool
    {
        $user = $user ?? self::user();
        if ($user === null) {
            return false;
        }

        $roles = $user['roles'] ?? [];
        if (in_array('admin', $roles, true)) {
            return true;
        }
        if (in_array('coordinador', $roles, true) || in_array('coordinadora', $roles, true)) {
            return true;
        }
        if (in_array('medico', $roles, true)) {
            return true;
        }
        if (in_array('especialista', $roles, true)) {
            $primary = strtolower(trim((string) ($user['role'] ?? '')));

            return $primary === 'medico' || in_array('medico', $roles, true);
        }

        return false;
    }

    /**
     * Roles profesionales cuyos usuarios puede considerar un especialista en paneles consolidados
     * (misma regla que auditoría AoAT / PIC). null = sin restricción (admin o coordinación).
     *
     * @return list<string>|null null = toda la plataforma; [] = especialista sin perfil reconocido
     */
    public static function dashboardProfessionalRoleScope(?array $user = null): ?array
    {
        $user = $user ?? self::user();
        if ($user === null) {
            return null;
        }

        $roles = array_map('strtolower', $user['roles'] ?? []);
        if (in_array('admin', $roles, true)
            || in_array('coordinadora', $roles, true)
            || in_array('coordinador', $roles, true)) {
            return null;
        }

        if (!in_array('especialista', $roles, true)) {
            return null;
        }

        $primaryRole = strtolower(trim((string) ($user['role'] ?? (($roles[0] ?? '') ?: ''))));
        if ($primaryRole === 'psicologo' || in_array('psicologo', $roles, true)) {
            return ['psicologo', 'profesional social', 'profesional_social'];
        }
        if ($primaryRole === 'medico' || in_array('medico', $roles, true)) {
            return ['medico'];
        }
        if ($primaryRole === 'abogado' || in_array('abogado', $roles, true)) {
            return ['abogado'];
        }

        return [];
    }

    /**
     * Filtra filas al propio usuario (defensa en profundidad si una consulta devolvió de más).
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    public static function scopeRowsToOwnerUser(array $rows, int $ownerUserId, string $userIdColumn = 'user_id'): array
    {
        return array_values(array_filter(
            $rows,
            static function (array $row) use ($ownerUserId, $userIdColumn): bool {
                return (int) ($row[$userIdColumn] ?? 0) === $ownerUserId;
            }
        ));
    }
}

