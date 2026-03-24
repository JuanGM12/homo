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

