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
}

