<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        abort_unless($request->user(), 401);
        abort_unless(in_array($request->user()->role, $roles, true), 403, 'No tienes permisos para realizar esta acción.');

        return $next($request);
    }
}
