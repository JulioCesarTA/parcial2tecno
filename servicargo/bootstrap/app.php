<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Alias de middleware del proyecto
        $middleware->alias([
            'jwt' => \App\Http\Middleware\JwtAuth::class,
            'rol' => \App\Http\Middleware\CheckRole::class,
            'bitacora' => \App\Http\Middleware\RegistrarBitacora::class,
        ]);

        // Inertia en el grupo web (puente SPA)
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        // PagoFácil llama a /callback y /return sin token CSRF.
        $middleware->validateCsrfTokens(except: [
            'callback',
            'return',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Errores de la capa de negocio (Services): mensaje + código HTTP.
        // JSON/AJAX -> { message }; Inertia/web -> redirect back con error.
        $exceptions->render(function (\App\Servicios\ErrorDominio $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
            }

            return back()->withErrors(['dominio' => $e->getMessage()]);
        });
    })->create();
