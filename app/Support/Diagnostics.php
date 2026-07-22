<?php

declare(strict_types=1);

namespace App\Support;

use App\Config\Config;
use Throwable;

final class Diagnostics
{
    private static float $startedAt = 0.0;
    private static string $requestId = '';
    private static bool $finished = false;

    public static function bootstrap(float $startedAt): void
    {
        self::$startedAt = $startedAt;
        self::$requestId = bin2hex(random_bytes(8));

        if (!self::enabled()) {
            return;
        }

        ini_set('log_errors', '1');
        ini_set('error_log', self::path('php-error.log'));

        register_shutdown_function(static function (): void {
            $error = error_get_last();
            if (is_array($error) && self::isFatal((int) ($error['type'] ?? 0))) {
                self::write('fatal', [
                    'type' => $error['type'] ?? null,
                    'message' => $error['message'] ?? '',
                    'file' => $error['file'] ?? '',
                    'line' => $error['line'] ?? null,
                ]);
            }

            if (!self::$finished) {
                self::finish(http_response_code() ?: 0, 'shutdown');
            }
        });
    }

    public static function requestId(): string
    {
        return self::$requestId;
    }

    public static function exception(Throwable $exception): void
    {
        if (!self::enabled()) {
            return;
        }

        self::write('exception', [
            'class' => $exception::class,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]);
    }

    public static function finish(int $status, string $event = 'request'): void
    {
        if (self::$finished) {
            return;
        }

        self::$finished = true;

        if (!self::enabled()) {
            return;
        }

        self::write($event, [
            'status' => $status,
            'duration_ms' => (int) round((microtime(true) - self::$startedAt) * 1000),
            'memory_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
            'connection_aborted' => function_exists('connection_aborted') ? connection_aborted() : 0,
        ]);
    }

    private static function enabled(): bool
    {
        return filter_var(Config::env('DIAGNOSTICS_ENABLED', true), FILTER_VALIDATE_BOOLEAN);
    }

    private static function write(string $event, array $context): void
    {
        $payload = array_merge(self::requestContext(), [
            'event' => $event,
        ], $context);

        file_put_contents(
            self::path('app-requests.log'),
            json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }

    private static function requestContext(): array
    {
        return [
            'ts' => date('c'),
            'request_id' => self::$requestId,
            'method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'host' => $_SERVER['HTTP_HOST'] ?? '',
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
            'scheme' => self::scheme(),
            'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? '',
            'forwarded_for' => self::limitedHeader('HTTP_X_FORWARDED_FOR'),
            'real_ip' => self::limitedHeader('HTTP_X_REAL_IP'),
            'user_agent' => self::limitedHeader('HTTP_USER_AGENT', 300),
            'referer' => self::limitedHeader('HTTP_REFERER', 300),
            'https' => $_SERVER['HTTPS'] ?? '',
        ];
    }

    private static function scheme(): string
    {
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            return (string) $_SERVER['HTTP_X_FORWARDED_PROTO'];
        }

        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    }

    private static function limitedHeader(string $key, int $limit = 180): string
    {
        $value = (string) ($_SERVER[$key] ?? '');
        return mb_substr($value, 0, $limit, 'UTF-8');
    }

    private static function path(string $file): string
    {
        $dir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir . '/' . $file;
    }

    private static function isFatal(int $type): bool
    {
        return in_array($type, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true);
    }
}
