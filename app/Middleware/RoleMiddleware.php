<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Services\Auth;

final class RoleMiddleware
{
    public function __construct(private string $role)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        if (!Auth::hasRole($this->role)) {
            // 403 Prohibido
            return Response::view('errors/403', [], 403);
        }

        return $next($request);
    }
}

