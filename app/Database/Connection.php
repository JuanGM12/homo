<?php

declare(strict_types=1);

namespace App\Database;

use App\Config\Config;
use PDO;
use PDOException;

final class Connection
{
    private static ?PDO $pdo = null;

    public static function getPdo(): PDO
    {
        if (self::$pdo === null) {
            $db = Config::dbConfig();

            if ($db['driver'] !== 'mysql') {
                throw new PDOException('Solo se ha configurado soporte para MySQL por ahora.');
            }

            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $db['host'],
                $db['port'],
                $db['database'],
                $db['charset']
            );

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            self::$pdo = new PDO($dsn, (string) $db['username'], (string) $db['password'], $options);

            // Forzar zona horaria de la sesión de la base de datos (ej: Bogotá -05:00)
            if (!empty($db['timezone'])) {
                self::$pdo->exec(sprintf("SET time_zone = '%s'", addslashes((string) $db['timezone'])));
            }
        }

        return self::$pdo;
    }
}

