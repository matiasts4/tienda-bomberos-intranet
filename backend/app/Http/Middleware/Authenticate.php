<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Si es API/JSON, no redirigir a login (que no existe en SPA).
     */
    protected function redirectTo($request): ?string
    {
        if ($request->expectsJson()) {
            return null;
        }

        // Si quisieras redirección web, pon una URL real:
        // return route('login');
        return null;
    }
}
