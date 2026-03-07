<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    private function __construct(
        private string $method,
        private string $path,
        private array $query,
        private array $body
    ) {
    }

    public static function fromGlobals(): self
    {
        $uri = strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/';
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
        $path = '/' . ltrim(preg_replace('#^' . preg_quote($basePath, '#') . '#', '', $uri), '/');

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $path,
            $_GET,
            $_POST
        );
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }
}

