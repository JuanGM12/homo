<?php

declare(strict_types=1);

namespace App\Services;

final class Flash
{
    public static function set(array $data): void
    {
        $_SESSION['flash'] = $data;
    }

    public static function get(): ?array
    {
        if (!isset($_SESSION['flash'])) {
            return null;
        }

        $data = $_SESSION['flash'];
        unset($_SESSION['flash']);

        return is_array($data) ? $data : null;
    }
}

