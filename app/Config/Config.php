<?php

declare(strict_types=1);

namespace App\Config;

final class Config
{
    public static function env(string $key, mixed $default = null): mixed
    {
        return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
    }

    public static function appEnv(): string
    {
        return (string) self::env('APP_ENV', 'production');
    }

    public static function isDev(): bool
    {
        return self::appEnv() === 'development';
    }

    /**
     * Zona horaria de la aplicación (PHP). Por defecto Bogotá para coincidir con operación en Colombia.
     */
    public static function timezone(): string
    {
        return (string) self::env('APP_TIMEZONE', 'America/Bogota');
    }

    /**
     * Offset para MySQL SET time_zone (ej. -05:00), alineado con APP_TIMEZONE.
     * Si DB_TIMEZONE es ±HH:MM se usa tal cual; si es un nombre IANA (p. ej. America/Bogota) se convierte a offset
     * para no depender de las tablas time_zone de MySQL (a menudo vacías en Windows/WAMP).
     */
    public static function mysqlSessionTimeZone(): string
    {
        $explicit = self::env('DB_TIMEZONE');
        if (is_string($explicit) && trim($explicit) !== '') {
            $explicit = trim($explicit);
            if (preg_match('/^[+\-]\d{2}:\d{2}$/', $explicit)) {
                return $explicit;
            }
            try {
                return (new \DateTimeImmutable('now', new \DateTimeZone($explicit)))->format('P');
            } catch (\Throwable) {
                // continúa con APP_TIMEZONE
            }
        }

        try {
            return (new \DateTimeImmutable('now', new \DateTimeZone(self::timezone())))->format('P');
        } catch (\Throwable) {
            return '-05:00';
        }
    }

    public static function dbConfig(): array
    {
        return [
            'driver' => self::env('DB_CONNECTION', 'mysql'),
            'host' => self::env('DB_HOST', '127.0.0.1'),
            'port' => (int) self::env('DB_PORT', 3306),
            'database' => self::env('DB_DATABASE', ''),
            'username' => self::env('DB_USERNAME', ''),
            'password' => self::env('DB_PASSWORD', ''),
            'charset' => self::env('DB_CHARSET', 'utf8mb4'),
            'collation' => self::env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'timezone' => self::mysqlSessionTimeZone(),
        ];
    }
}

