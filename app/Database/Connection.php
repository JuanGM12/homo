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

            $tz = (string) ($db['timezone'] ?? '');
            if (!preg_match('/^[+\-]\d{2}:\d{2}$/', $tz)) {
                $tz = '-05:00';
            }

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                // Primera operación al conectar: misma referencia horaria que PHP (Bogotá por defecto).
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '" . $tz . "'",
            ];

            self::$pdo = new PDO($dsn, (string) $db['username'], (string) $db['password'], $options);
        }

        return self::$pdo;
    }
}

