<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    public function __construct(
        private string $content,
        private int $status = 200,
        private array $headers = ['Content-Type' => 'text/html; charset=utf-8']
    ) {
    }

    public static function view(string $view, array $data = [], int $status = 200): self
    {
        extract($data, EXTR_SKIP);
        $viewFile = dirname(__DIR__) . '/Views/' . $view . '.php';
        ob_start();
        require dirname(__DIR__) . '/Views/layouts/main.php';
        $content = ob_get_clean() ?: '';

        return new self($content, $status);
    }

    public static function redirect(string $url): self
    {
        return new self('', 302, ['Location' => $url]);
    }

    public static function json(array $data, int $status = 200, array $headers = []): self
    {
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
        $headers = array_merge(['Content-Type' => 'application/json; charset=utf-8'], $headers);

        return new self($payload === false ? '{}' : $payload, $status, $headers);
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }
        echo $this->content;
    }
}

