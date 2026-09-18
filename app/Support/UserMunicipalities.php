<?php

declare(strict_types=1);

namespace App\Support;

use App\Repositories\UserRepository;

final class UserMunicipalities
{
    /**
     * @return array<int, array{subregion:string, municipality:string}>
     */
    public static function assignedTo(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $rows = (new UserRepository())->findMunicipalitiesForUser($userId);

        return array_values(array_filter(array_map(static function (array $row): array {
            return [
                'subregion' => trim((string) ($row['subregion'] ?? '')),
                'municipality' => trim((string) ($row['municipality'] ?? '')),
            ];
        }, $rows), static function (array $row): bool {
            return $row['subregion'] !== '' && $row['municipality'] !== '';
        }));
    }

    public static function canUse(int $userId, string $subregion, string $municipality): bool
    {
        $subregion = trim($subregion);
        $municipality = trim($municipality);
        if ($subregion === '' || $municipality === '') {
            return false;
        }

        $assigned = self::assignedTo($userId);
        if ($assigned === []) {
            return true;
        }

        foreach ($assigned as $row) {
            if ($row['subregion'] === $subregion && $row['municipality'] === $municipality) {
                return true;
            }
        }

        return false;
    }
}
