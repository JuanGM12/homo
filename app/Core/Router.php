<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    private array $routes = [];
    private array $namedRoutes = [];

    public function get(string $path, callable|array $action): Route
    {
        return $this->addRoute('GET', $path, $action);
    }

    public function post(string $path, callable|array $action): Route
    {
        return $this->addRoute('POST', $path, $action);
    }

    private function addRoute(string $method, string $path, callable|array $action): Route
    {
        $route = new Route($method, $path, $action);
        $this->routes[] = $route;
        return $route;
    }

    public function dispatch(Request $request): Response
    {
        foreach ($this->routes as $route) {
            if ($route->matches($request)) {
                return $route->run($request);
            }
        }

        return Response::view('errors/404', [], 404);
    }
}

