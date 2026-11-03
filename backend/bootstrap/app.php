<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request; // ✅ ADD

// Sanctum (Laravel 11)
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

use App\Http\Middleware\CanViewModule;
use App\Http\Middleware\CanManageModule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {

        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return null; // => responde 401 JSON, no HTML
            }
            return null; // (si no usas rutas web de login)
        });

        // Grupo API: Sanctum SPA (cookies) + throttle simple
        $middleware->group('api', [
            EnsureFrontendRequestsAreStateful::class,
            'throttle:600,1',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'api/*',       // Desactiva CSRF para todas las rutas API
            'bodega/*',    // Por si acaso
        ]);

        // Alias de middlewares de rutas
        $middleware->alias([
            'role' => \App\Http\Middleware\RequireRole::class,
            'can_view_module' => CanViewModule::class,
            'can_manage_module' => CanManageModule::class,
            'marketplace.auth' => \App\Http\Middleware\MarketplaceAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->create();
