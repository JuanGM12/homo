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

    public static function timezone(): string
    {
        return (string) self::env('APP_TIMEZONE', 'UTC');
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
            'timezone' => self::env('DB_TIMEZONE', '-05:00'),
        ];
    }
}

