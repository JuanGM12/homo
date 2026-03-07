<?php

declare(strict_types=1);

namespace App\Core;

use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;

final class Route
{
    /**
     * @var callable|array
     */
    private $action;

    private string $method;
    private string $path;
    private array $middlewares;

    /**
     * @param callable|array $action
     */
    public function __construct(
        string $method,
        string $path,
        $action,
        array $middlewares = []
    ) {
        $this->method = $method;
        $this->path = $path;
        $this->action = $action;
        $this->middlewares = $middlewares;
    }

    public function name(string $name): self
    {
        // Reservado para generar URLs por nombre más adelante
        return $this;
    }

    public function middleware(string ...$middlewares): self
    {
        $this->middlewares = array_merge($this->middlewares, $middlewares);
        return $this;
    }

    public function matches(Request $request): bool
    {
        return $request->getMethod() === $this->method
            && rtrim($request->getPath(), '/') === rtrim($this->path, '/');
    }

    public function run(Request $request): Response
    {
        $handler = function (Request $req): Response {
            if (is_array($this->action)) {
                [$class, $method] = $this->action;
                $controller = new $class();
                $result = $controller->$method($req);
            } else {
                $result = call_user_func($this->action, $req);
            }

            if ($result instanceof Response) {
                return $result;
            }

            return new Response((string) $result);
        };

        // Aplicar middlewares (en orden inverso para tipo "onion")
        foreach (array_reverse($this->middlewares) as $middlewareName) {
            if ($middlewareName === 'auth') {
                $middleware = new AuthMiddleware();
            } elseif (str_starts_with($middlewareName, 'role:')) {
                [, $role] = explode(':', $middlewareName, 2);
                $middleware = new RoleMiddleware($role);
            } else {
                continue;
            }

            $next = $handler;
            $handler = function (Request $req) use ($middleware, $next) {
                return $middleware->handle($req, $next);
            };
        }

        return $handler($request);
    }
}

